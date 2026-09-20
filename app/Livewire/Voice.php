<?php

namespace App\Livewire;

use App\Services\ConversationTurn;
use App\Services\PersonalUser;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Models\Conversation;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Native\Mobile\Attributes\OnNative;
use Native\Mobile\Events\Microphone\MicrophoneCancelled;
use Native\Mobile\Events\Microphone\MicrophoneRecorded;
use Native\Mobile\Facades\Microphone;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Voice extends Component
{
    use WithFileUploads;

    public string $state = 'idle';

    public string $status = 'Tap to speak';

    public string $draft = '';

    public ?string $conversationId = null;

    /** @var list<array{role: string, content: string}> */
    public array $messages = [];

    /** @var list<array{id: string, tool: string, arguments: array<string, mixed>, reason: string|null}> */
    public array $pendingApprovals = [];

    /** @var array<string, string> */
    public array $approvalChoices = [];

    public bool $usingNativeRecorder = false;

    #[Validate('nullable|file|mimetypes:audio/m4a,audio/mp4,audio/mpeg,audio/wav,audio/x-wav,audio/webm|max:25600')]
    public mixed $recording = null;

    public function mount(PersonalUser $personalUser): void
    {
        $user = $personalUser->get();
        $conversation = Conversation::query()
            ->where('participant_type', Conversation::participantType($user))
            ->where('participant_id', $user->getKey())
            ->latest('updated_at')
            ->first();

        $this->conversationId = $conversation?->id;
        $this->messages = $this->visibleMessages($conversation);
        $this->pendingApprovals = $this->visibleApprovals($conversation);

        if ($this->pendingApprovals !== []) {
            $this->state = 'awaiting_approval';
            $this->status = 'Your approval is needed';
        }
    }

    public function sendText(): void
    {
        $message = trim($this->draft);

        if ($message === '') {
            return;
        }

        $this->draft = '';
        $this->completeTurn(message: $message);
    }

    public function updatedRecording(): void
    {
        if ($this->recording instanceof TemporaryUploadedFile) {
            $this->processRecording();
        }
    }

    public function processRecording(): void
    {
        $this->validateOnly('recording');

        if (! $this->recording) {
            return;
        }

        $this->completeTurn(audio: $this->recording);
        $this->recording = null;
    }

    public function toggleRecording(): void
    {
        if (in_array($this->state, ['thinking', 'awaiting_approval'], true)) {
            return;
        }

        if ($this->state === 'recording') {
            $this->stopRecording();

            return;
        }

        $this->startRecording();
    }

    public function startRecording(): void
    {
        $started = Microphone::record()->start();

        if ($started) {
            $this->usingNativeRecorder = true;
            $this->listen();

            return;
        }

        $this->usingNativeRecorder = false;
        $this->dispatch('start-browser-recording');
    }

    public function listen(): void
    {
        $this->state = 'recording';
        $this->status = 'Listening… tap when finished';
    }

    public function stopRecording(): void
    {
        $this->state = 'thinking';
        $this->status = 'Finishing recording…';

        if ($this->usingNativeRecorder) {
            Microphone::stop();

            return;
        }

        $this->dispatch('stop-browser-recording');
    }

    public function recordingFailed(string $message): void
    {
        $this->state = 'error';
        $this->status = $message;
    }

    #[OnNative(MicrophoneRecorded::class)]
    public function microphoneRecorded(string $path, string $mimeType = 'audio/m4a'): void
    {
        $this->completeTurn(audioPath: $path, mimeType: $mimeType);
    }

    #[OnNative(MicrophoneCancelled::class)]
    public function microphoneCancelled(): void
    {
        $this->usingNativeRecorder = false;
        $this->state = 'idle';
        $this->status = 'Recording cancelled';
    }

    public function render()
    {
        return view('livewire.voice')->layout('layouts.app');
    }

    public function chooseApproval(string $id, string $choice): void
    {
        abort_unless(in_array($choice, ['approve', 'reject'], true), 422);
        abort_unless(collect($this->pendingApprovals)->contains('id', $id), 404);

        $this->approvalChoices[$id] = $choice;
    }

    public function submitApprovals(): void
    {
        if (! $this->conversationId || $this->pendingApprovals === []) {
            return;
        }

        $missing = collect($this->pendingApprovals)
            ->pluck('id')
            ->contains(fn (string $id): bool => ! isset($this->approvalChoices[$id]));

        if ($missing) {
            $this->addError('approvals', 'Choose allow or deny for every action.');

            return;
        }

        $decisions = Decisions::from(
            collect($this->pendingApprovals)->mapWithKeys(
                fn (array $approval): array => [
                    $approval['id'] => $this->approvalChoices[$approval['id']] === 'approve'
                        ? Decision::approve()
                        : Decision::reject('The user did not approve this action.'),
                ],
            )->all(),
        );

        $this->state = 'thinking';
        $this->status = 'Continuing…';

        try {
            $result = app(ConversationTurn::class)->decide(
                conversationId: $this->conversationId,
                decisions: $decisions,
                onDelta: fn (string $delta) => $this->stream($delta, to: 'assistant-response'),
            );

            $this->finishTurn($result);
        } catch (HttpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->state = 'error';
            $this->status = 'The approval could not be completed.';
        }
    }

    private function completeTurn(
        ?string $message = null,
        mixed $audio = null,
        ?string $audioPath = null,
        ?string $mimeType = null,
    ): void {
        $this->state = 'thinking';
        $this->status = 'Thinking…';

        try {
            $result = app(ConversationTurn::class)->handle(
                message: $message,
                audio: $audio,
                audioPath: $audioPath,
                mimeType: $mimeType,
                conversationId: $this->conversationId,
                onDelta: fn (string $delta) => $this->stream($delta, to: 'assistant-response'),
            );

            $this->finishTurn($result);
        } catch (ValidationException $exception) {
            $this->state = 'error';
            $this->status = (string) $exception->validator->errors()->first();
        } catch (HttpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->state = 'error';
            $this->status = 'Something went wrong. Tap to retry.';
        }
    }

    /**
     * @param  array{conversation_id: string|null, transcript: string, response: string, audio_url: string|null, approvals: array<int, array{id: string, tool: string, arguments: array<string, mixed>, reason: string|null}>}  $result
     */
    private function finishTurn(array $result): void
    {
        $this->conversationId = $result['conversation_id'];

        if (filled($result['transcript'])) {
            $this->messages[] = ['role' => 'user', 'content' => $result['transcript']];
        }

        if (filled($result['response'])) {
            $this->messages[] = ['role' => 'assistant', 'content' => $result['response']];
        }

        $this->pendingApprovals = $result['approvals'];
        $this->approvalChoices = [];
        $this->resetErrorBag('approvals');

        if ($this->pendingApprovals !== []) {
            $this->state = 'awaiting_approval';
            $this->status = 'Your approval is needed';

            return;
        }

        $this->state = 'idle';
        $this->status = 'Tap to speak';
        $this->dispatch('assistant-spoken', url: $result['audio_url'], text: $result['response']);
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function visibleMessages(?Conversation $conversation): array
    {
        if (! $conversation) {
            return [];
        }

        return $conversation->messages()
            ->oldest()
            ->get()
            ->filter(fn ($message): bool => in_array($message->role, ['user', 'assistant'], true))
            ->map(fn ($message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, tool: string, arguments: array<string, mixed>, reason: string|null}>
     */
    private function visibleApprovals(?Conversation $conversation): array
    {
        $message = $conversation?->messages()
            ->whereNotNull('approval_state')
            ->latest('id')
            ->first();

        if (! $message || $conversation->messages()->where('id', '>', $message->id)->exists()) {
            return [];
        }

        $pending = $message->approval_state['pending'] ?? [];

        return collect($message->tool_calls)
            ->filter(fn (array $tool): bool => array_key_exists($tool['id'], $pending))
            ->map(fn (array $tool): array => [
                'id' => $tool['id'],
                'tool' => $tool['name'],
                'arguments' => $tool['arguments'] ?? [],
                'reason' => $pending[$tool['id']] ?? null,
            ])
            ->values()
            ->all();
    }
}
