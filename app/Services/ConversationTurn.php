<?php

namespace App\Services;

use App\Ai\Agents\PersonalAssistant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Audio;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;
use Laravel\Ai\Transcription;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ConversationTurn
{
    public function __construct(
        private readonly PersonalUser $personalUser,
        private readonly AgentActivityTracker $activities,
    ) {}

    /**
     * @param  callable(string): void|null  $onDelta
     * @return array{conversation_id: string|null, transcript: string, response: string, audio_url: string|null, approvals: array<int, array{id: string, tool: string, arguments: array<string, mixed>, reason: string|null}>}
     */
    public function handle(
        ?string $message = null,
        UploadedFile|TemporaryUploadedFile|null $audio = null,
        ?string $audioPath = null,
        ?string $mimeType = null,
        ?string $conversationId = null,
        bool $speak = true,
        ?callable $onDelta = null,
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

        $assistant = $this->assistant($conversationId);
        $activity = $this->activities->start($transcript, $conversationId);

        try {
            $result = $this->prompt($assistant, $transcript, $onDelta);
            $this->activities->finish($activity, $result);
        } catch (Throwable $exception) {
            $this->activities->fail($activity);

            throw $exception;
        }

        return [
            'conversation_id' => $result['conversation_id'],
            'transcript' => $transcript,
            'response' => $result['response'],
            'audio_url' => $speak && $result['approvals'] === [] && filled($result['response'])
                ? $this->synthesize($result['response'])
                : null,
            'approvals' => $result['approvals'],
        ];
    }

    /**
     * @param  callable(string): void|null  $onDelta
     * @return array{conversation_id: string|null, transcript: string, response: string, audio_url: string|null, approvals: array<int, array{id: string, tool: string, arguments: array<string, mixed>, reason: string|null}>}
     */
    public function decide(
        string $conversationId,
        Decisions $decisions,
        bool $speak = true,
        ?callable $onDelta = null,
    ): array {
        $assistant = $this->assistant($conversationId);
        $activity = $this->activities->resume($conversationId);

        try {
            $result = $this->prompt($assistant, $decisions, $onDelta);
            $this->activities->finish($activity, $result);
        } catch (Throwable $exception) {
            $this->activities->fail($activity);

            throw $exception;
        }

        return [
            'conversation_id' => $result['conversation_id'],
            'transcript' => '',
            'response' => $result['response'],
            'audio_url' => $speak && $result['approvals'] === [] && filled($result['response'])
                ? $this->synthesize($result['response'])
                : null,
            'approvals' => $result['approvals'],
        ];
    }

    private function assistant(?string $conversationId): PersonalAssistant
    {
        $user = $this->personalUser->get();
        $assistant = new PersonalAssistant;

        if (! $conversationId) {
            return $assistant->forUser($user);
        }

        $belongsToUser = Conversation::query()
            ->whereKey($conversationId)
            ->where('participant_type', Conversation::participantType($user))
            ->where('participant_id', $user->getKey())
            ->exists();

        abort_unless($belongsToUser, 404);

        return $assistant->continue($conversationId, $user);
    }

    /**
     * @param  callable(string): void|null  $onDelta
     * @return array{conversation_id: string|null, response: string, approvals: array<int, array{id: string, tool: string, arguments: array<string, mixed>, reason: string|null}>}
     */
    private function prompt(PersonalAssistant $assistant, string|Decisions $prompt, ?callable $onDelta): array
    {
        if (! $onDelta) {
            $response = $assistant->prompt($prompt);

            return [
                'conversation_id' => $response->conversationId,
                'response' => (string) $response,
                'approvals' => $response->pendingApprovals
                    ->map->toArray()
                    ->values()
                    ->all(),
            ];
        }

        $stream = $assistant->stream($prompt);

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $onDelta($event->delta);
            }
        }

        $approvals = $stream->events
            ->whereInstanceOf(ToolApprovalRequest::class)
            ->flatMap(fn (ToolApprovalRequest $event) => $event->pendingApprovals)
            ->map->toArray()
            ->values()
            ->all();

        return [
            'conversation_id' => $stream->conversationId,
            'response' => $stream->text ?? '',
            'approvals' => $approvals,
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
