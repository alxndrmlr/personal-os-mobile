<?php

namespace Tests\Feature;

use App\Ai\Agents\PersonalAssistant;
use App\Models\AgentActivity;
use App\NativeComponents\Voice;
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Transcription;
use Native\Mobile\AsyncTask;
use Native\Mobile\Events\Microphone\MicrophoneRecorded;
use Native\Mobile\Testing\Native;
use NativePHP\MediaPlayer\Facades\MediaPlayer;
use Tests\TestCase;

class VoiceConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        AsyncTask::clearFake();

        parent::tearDown();
    }

    public function test_the_voice_screen_is_available_without_authentication(): void
    {
        Native::visit('/')
            ->assertScreen(Voice::class)
            ->assertSee('What can I help with?')
            ->assertSee('Tap to speak')
            ->assertAccessible();
    }

    public function test_a_text_turn_is_persisted_and_returns_spoken_audio(): void
    {
        Storage::fake('mobile_public');
        PersonalAssistant::fake(['It is 8:15 PM.']);
        Audio::fake([base64_encode('fake audio')]);
        AsyncTask::fake();
        LocalNotifications::shouldReceive('schedule')
            ->once()
            ->withArgs(fn (array $notification): bool => $notification['title'] === 'Your agent is done')
            ->andReturn([]);
        MediaPlayer::shouldReceive('play')->once()->andReturn(true);

        Native::test(Voice::class)
            ->set('draft', 'What time is it?')
            ->call('sendText')
            ->assertSet('draft', '')
            ->assertSet('status', 'Tap to speak')
            ->assertSee('What time is it?')
            ->assertSee('It is 8:15 PM.');

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

        Native::test(Voice::class)
            ->call('enableNotifications')
            ->assertSet('notificationPermissionRequested', true)
            ->assertSee('Permission requested');
    }

    public function test_a_native_recording_is_transcribed_before_prompting(): void
    {
        Storage::fake('mobile_public');
        PersonalAssistant::fake(['You asked about tomorrow.']);
        Transcription::fake(['What is on my calendar tomorrow?']);
        Audio::fake([base64_encode('fake audio')]);
        AsyncTask::fake();
        LocalNotifications::shouldReceive('schedule')->once()->andReturn([]);
        MediaPlayer::shouldReceive('play')->once()->andReturn(true);
        $path = storage_path('app/test-voice.m4a');
        file_put_contents($path, 'fake audio bytes');

        Native::test(Voice::class)
            ->emitNative(MicrophoneRecorded::class, [
                'path' => $path,
                'mimeType' => 'audio/m4a',
            ])
            ->assertSee('What is on my calendar tomorrow?')
            ->assertSee('You asked about tomorrow.');

        PersonalAssistant::assertPrompted('What is on my calendar tomorrow?');
        @unlink($path);
    }

    public function test_an_existing_conversation_must_belong_to_the_personal_user(): void
    {
        PersonalAssistant::fake(['No.']);
        AsyncTask::fake();

        Native::test(Voice::class)
            ->set('conversationId', fake()->uuid())
            ->set('draft', 'Continue')
            ->call('sendText')
            ->assertSet('state', 'error');
    }

    public function test_an_empty_text_turn_is_ignored(): void
    {
        PersonalAssistant::fake(['Nope.']);
        AsyncTask::fake();

        Native::test(Voice::class)
            ->set('draft', '   ')
            ->call('sendText')
            ->assertSee('What can I help with?');

        PersonalAssistant::assertNeverPrompted();
    }
}
