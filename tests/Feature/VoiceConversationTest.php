<?php

namespace Tests\Feature;

use App\Ai\Agents\PersonalAssistant;
use App\Livewire\Voice;
use App\Models\AgentActivity;
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Transcription;
use Livewire\Livewire;
use Tests\TestCase;

class VoiceConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_voice_screen_is_available_without_authentication(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeLivewire(Voice::class)
            ->assertSee('Ready when you are.')
            ->assertSee('Tap to speak');
    }

    public function test_a_text_turn_is_persisted_and_returns_spoken_audio(): void
    {
        Storage::fake('mobile_public');
        PersonalAssistant::fake(['It is 8:15 PM.']);
        Audio::fake([base64_encode('fake audio')]);
        LocalNotifications::shouldReceive('schedule')
            ->once()
            ->withArgs(fn (array $notification): bool => $notification['title'] === 'Your agent is done')
            ->andReturn([]);

        Livewire::test(Voice::class)
            ->set('draft', 'What time is it?')
            ->call('sendText')
            ->assertSet('draft', '')
            ->assertSet('status', 'Tap to speak')
            ->assertSee('What time is it?')
            ->assertSee('It is 8:15 PM.')
            ->assertDispatched('assistant-spoken');

        $this->assertDatabaseCount('agent_conversations', 1);
        $this->assertDatabaseCount('agent_conversation_messages', 2);
        $this->assertDatabaseHas('agent_activities', [
            'title' => 'What time is it?',
            'status' => AgentActivity::STATUS_COMPLETED,
        ]);
        $this->assertNotNull(Conversation::query()->first()?->participant);
    }

    public function test_system_notification_permission_can_be_requested(): void
    {
        LocalNotifications::shouldReceive('requestPermission')->once()->andReturn([]);

        Livewire::test(Voice::class)
            ->call('enableNotifications')
            ->assertSet('notificationPermissionRequested', true)
            ->assertSee('Permission requested');
    }

    public function test_an_uploaded_recording_is_transcribed_before_prompting(): void
    {
        PersonalAssistant::fake(['You asked about tomorrow.']);
        Transcription::fake(['What is on my calendar tomorrow?']);
        $audio = UploadedFile::fake()->create('voice.m4a', 64, 'audio/m4a');
        file_put_contents($audio->getRealPath(), 'fake audio bytes');

        Livewire::test(Voice::class)
            ->set('recording', $audio)
            ->assertSee('What is on my calendar tomorrow?')
            ->assertSee('You asked about tomorrow.');

        PersonalAssistant::assertPrompted('What is on my calendar tomorrow?');
    }

    public function test_an_existing_conversation_must_belong_to_the_personal_user(): void
    {
        PersonalAssistant::fake(['No.']);

        Livewire::test(Voice::class)
            ->set('conversationId', fake()->uuid())
            ->set('draft', 'Continue')
            ->call('sendText')
            ->assertStatus(404);
    }

    public function test_an_empty_text_turn_is_ignored(): void
    {
        PersonalAssistant::fake(['Nope.']);

        Livewire::test(Voice::class)
            ->set('draft', '   ')
            ->call('sendText')
            ->assertSee('Ready when you are.');

        PersonalAssistant::assertNeverPrompted();
    }
}
