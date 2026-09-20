<?php

namespace App\Services;

use App\Ai\Agents\PersonalAssistant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Audio;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Transcription;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ConversationTurn
{
    public function __construct(private readonly PersonalUser $personalUser) {}

    /**
     * @return array{conversation_id: string|null, transcript: string, response: string, audio_url: string|null}
     */
    public function handle(
        ?string $message = null,
        UploadedFile|TemporaryUploadedFile|null $audio = null,
        ?string $audioPath = null,
        ?string $mimeType = null,
        ?string $conversationId = null,
        bool $speak = true,
    ): array {
        $transcript = trim((string) $message);

        if ($transcript === '') {
            $transcript = $this->transcribe($audio, $audioPath, $mimeType);
        }

        if ($transcript === '') {
            throw ValidationException::withMessages([
                'message' => 'Record audio or enter a message before sending.',
            ]);
        }

        $user = $this->personalUser->get();
        $assistant = new PersonalAssistant;

        if ($conversationId) {
            $belongsToUser = Conversation::query()
                ->whereKey($conversationId)
                ->where('participant_type', Conversation::participantType($user))
                ->where('participant_id', $user->getKey())
                ->exists();

            abort_unless($belongsToUser, 404);
            $assistant->continue($conversationId, $user);
        } else {
            $assistant->forUser($user);
        }

        $response = $assistant->prompt($transcript);

        return [
            'conversation_id' => $response->conversationId,
            'transcript' => $transcript,
            'response' => (string) $response,
            'audio_url' => $speak ? $this->synthesize((string) $response) : null,
        ];
    }

    private function transcribe(
        UploadedFile|TemporaryUploadedFile|null $audio,
        ?string $audioPath,
        ?string $mimeType,
    ): string {
        if ($audio) {
            return trim((string) Transcription::fromUpload($audio)->generate());
        }

        if ($audioPath) {
            if (! is_file($audioPath) || ! is_readable($audioPath)) {
                throw ValidationException::withMessages([
                    'audio_path' => 'The native recording could not be read.',
                ]);
            }

            return trim((string) Transcription::fromPath($audioPath, $mimeType)->generate());
        }

        return '';
    }

    private function synthesize(string $text): ?string
    {
        try {
            $audio = Audio::of($text)
                ->voice(config('personal.voice'))
                ->instructions('Warm, natural, concise personal assistant delivery.')
                ->generate();

            $path = $audio->store('assistant-audio', 'mobile_public');

            return $path ? Storage::disk('mobile_public')->url($path) : null;
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Voice synthesis failed; returning text response.', [
                'exception' => $exception::class,
            ]);

            return null;
        }
    }
}
