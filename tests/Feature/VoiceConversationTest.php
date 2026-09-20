<?php

namespace Tests\Feature;

use App\Ai\Agents\PersonalAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Transcription;
use Tests\TestCase;

class VoiceConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_voice_screen_is_available_without_authentication(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Ready when you are.')
            ->assertSee('Tap to speak');
    }

    public function test_a_text_turn_is_persisted_and_returns_spoken_audio(): void
    {
        Storage::fake('mobile_public');
        PersonalAssistant::fake(['It is 8:15 PM.']);
        Audio::fake([base64_encode('fake audio')]);

        $response = $this->postJson('/voice/turn', [
            'message' => 'What time is it?',
            'speak' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('transcript', 'What time is it?')
            ->assertJsonPath('response', 'It is 8:15 PM.')
            ->assertJsonStructure(['conversation_id', 'audio_url']);

        $this->assertDatabaseCount('agent_conversations', 1);
        $this->assertDatabaseCount('agent_conversation_messages', 2);
        $this->assertNotNull(Conversation::query()->first()?->participant);
    }

    public function test_an_uploaded_recording_is_transcribed_before_prompting(): void
    {
        PersonalAssistant::fake(['You asked about tomorrow.']);
        Transcription::fake(['What is on my calendar tomorrow?']);
        $audio = UploadedFile::fake()->create('voice.m4a', 64, 'audio/m4a');
        file_put_contents($audio->getRealPath(), 'fake audio bytes');

        $response = $this->post('/voice/turn', [
            'audio' => $audio,
            'speak' => false,
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('transcript', 'What is on my calendar tomorrow?')
            ->assertJsonPath('audio_url', null);

        PersonalAssistant::assertPrompted('What is on my calendar tomorrow?');
    }

    public function test_an_existing_conversation_must_belong_to_the_personal_user(): void
    {
        PersonalAssistant::fake(['No.']);

        $this->postJson('/voice/turn', [
            'message' => 'Continue',
            'conversation_id' => fake()->uuid(),
            'speak' => false,
        ])->assertNotFound();
    }
}
