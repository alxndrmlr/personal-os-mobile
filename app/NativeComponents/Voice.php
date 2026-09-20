<?php

namespace App\NativeComponents;

use App\AsyncTasks\DecideConversationTurn;
use App\AsyncTasks\RunConversationTurn;
use App\Models\AgentActivity;
use App\Services\PersonalUser;
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Illuminate\View\View;
use Laravel\Ai\Models\Conversation;
use Native\Mobile\Attributes\On;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\Layouts\Builders\TabBarOptions;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Microphone\MicrophoneCancelled;
use Native\Mobile\Events\Microphone\MicrophoneRecorded;
use Native\Mobile\Exceptions\AsyncTaskException;
use Native\Mobile\Facades\Microphone;
use NativePHP\MediaPlayer\Facades\MediaPlayer;

class Voice extends NativeComponent
{
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

    /** @var list<array{id: string, title: string, status: string, detail: string|null}> */
    public array $activities = [];

    public int $activeActivityCount = 0;

    public bool $notificationPermissionRequested = false;

    public string $approvalError = '';

    public function mount(): void
    {
        $user = app(PersonalUser::class)->get();
        $conversation = Conversation::query()
            ->where('participant_type', Conversation::participantType($user))
            ->where('participant_id', $user->getKey())
            ->latest('updated_at')
            ->first();

        $this->conversationId = $conversation?->id;
        $this->messages = $this->visibleMessages($conversation);
        $this->pendingApprovals = $this->visibleApprovals($conversation);
        $this->refreshActivities();

        if ($this->pendingApprovals !== []) {
            $this->state = 'awaiting_approval';
            $this->status = 'Your approval is needed';
        }
    }

    public function navTitle(): string
    {
        return 'Personal OS';
    }

    public function tabBarOptions(): ?TabBarOptions
    {
        return TabBarOptions::make()->highlight('/');
    }

    public function sendText(?string $value = null): void
    {
        if ($value !== null) {
            $this->draft = $value;
        }

        $message = trim($this->draft);

        if ($message === '') {
            return;
        }

        $this->draft = '';
        $this->runTurn(message: $message);
    }

    public function toggleRecording(): void
    {
        if (in_array($this->state, ['thinking', 'awaiting_approval'], true)) {
            return;
        }

        if ($this->state === 'recording') {
            $this->state = 'thinking';
            $this->status = 'Finishing recording…';
            Microphone::stop();

            return;
        }

        if (! Microphone::record()->start()) {
            $this->state = 'error';
            $this->status = 'Microphone access is unavailable.';

            return;
        }

        $this->state = 'recording';
        $this->status = 'Listening… tap when finished';
    }

    #[On(MicrophoneRecorded::class)]
    public function microphoneRecorded(string $path, string $mimeType = 'audio/m4a'): void
    {
        $this->runTurn(audioPath: $path, mimeType: $mimeType);
    }

    #[On(MicrophoneCancelled::class)]
    public function microphoneCancelled(): void
    {
        $this->state = 'idle';
        $this->status = 'Recording cancelled';
    }

    public function chooseApproval(string $id, string $choice): void
    {
        if (! in_array($choice, ['approve', 'reject'], true)
            || ! collect($this->pendingApprovals)->contains('id', $id)) {
            return;
        }

        $this->approvalChoices[$id] = $choice;
        $this->approvalError = '';
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
            $this->approvalError = 'Choose allow or deny for every action.';

            return;
        }

        $this->state = 'thinking';
        $this->status = 'Continuing…';

        DecideConversationTurn::dispatch($this->conversationId, $this->approvalChoices)
            ->timeout(180)
            ->finished(fn (array $result) => $this->finishTurn($result))
            ->failed(fn (AsyncTaskException $exception) => $this->turnFailed(
                'The approval could not be completed.',
                $exception,
            ));
    }

    public function enableNotifications(): void
    {
        LocalNotifications::requestPermission();
        $this->notificationPermissionRequested = true;
    }

    #[Poll(5000)]
    public function refreshActivities(): void
    {
        $this->activities = AgentActivity::query()
            ->latest('started_at')
            ->limit(5)
            ->get()
            ->map(fn (AgentActivity $activity): array => [
                'id' => $activity->id,
                'title' => $activity->title,
                'status' => $activity->status,
                'detail' => $activity->detail,
            ])
            ->all();

        $this->activeActivityCount = AgentActivity::query()
            ->whereIn('status', [AgentActivity::STATUS_WORKING, AgentActivity::STATUS_NEEDS_INPUT])
            ->count();
    }

    public function render(): View
    {
        return view('native.voice');
    }

    private function runTurn(
        ?string $message = null,
        ?string $audioPath = null,
        ?string $mimeType = null,
    ): void {
        if ($this->pendingApprovals !== []) {
            $this->state = 'awaiting_approval';
            $this->status = 'Review the requested action first';

            return;
        }

        $this->state = 'thinking';
        $this->status = $audioPath ? 'Transcribing and thinking…' : 'Thinking…';

        RunConversationTurn::dispatch($message, $audioPath, $mimeType, $this->conversationId)
            ->timeout(180)
            ->finished(fn (array $result) => $this->finishTurn($result))
            ->failed(fn (AsyncTaskException $exception) => $this->turnFailed(
                'Something went wrong. Tap to retry.',
                $exception,
            ));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function finishTurn(array $result): void
    {
        $this->conversationId = $result['conversation_id'] ?? null;

        if (filled($result['transcript'] ?? null)) {
            $this->messages[] = ['role' => 'user', 'content' => $result['transcript']];
        }

        if (filled($result['response'] ?? null)) {
            $this->messages[] = ['role' => 'assistant', 'content' => $result['response']];
        }

        $this->pendingApprovals = $result['approvals'] ?? [];
        $this->approvalChoices = [];
        $this->approvalError = '';
        $this->refreshActivities();

        if ($this->pendingApprovals !== []) {
            $this->state = 'awaiting_approval';
            $this->status = 'Your approval is needed';

            return;
        }

        $this->state = 'idle';
        $this->status = 'Tap to speak';

        if (filled($result['audio_path'] ?? null)) {
            MediaPlayer::play($result['audio_path']);
        }
    }

    private function turnFailed(string $message, AsyncTaskException $exception): void
    {
        report($exception);
        $this->state = 'error';
        $this->status = $message;
        $this->refreshActivities();
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
