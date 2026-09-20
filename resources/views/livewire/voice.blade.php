<div
    class="mx-auto grid min-h-dvh w-full max-w-5xl grid-rows-[auto_minmax(12rem,1fr)_auto] px-[max(1rem,env(safe-area-inset-right))] pb-[max(1rem,env(safe-area-inset-bottom))] pl-[max(1rem,env(safe-area-inset-left))] pt-[max(1rem,env(safe-area-inset-top))] lg:grid-cols-[minmax(16rem,.7fr)_minmax(22rem,1.3fr)] lg:grid-rows-[auto_1fr] lg:gap-x-6"
    data-state="{{ $state }}"
    x-data="voiceSurface"
    x-on:start-browser-recording="startBrowserRecording()"
    x-on:stop-browser-recording="stopBrowserRecording()"
    x-on:assistant-spoken="speak($event.detail.url, $event.detail.text)"
>
    <header class="flex items-center gap-3 pb-4 lg:col-span-2">
        <flux:avatar icon="microphone" />
        <flux:heading size="lg" level="1">Personal OS</flux:heading>

        <flux:button
            href="{{ route('connections.index') }}"
            variant="ghost"
            icon="squares-plus"
            square
            class="ml-auto"
            aria-label="Manage MCP connections"
        />
        <flux:badge icon="lock-closed">Private</flux:badge>
    </header>

    <section class="min-h-0 overflow-y-auto py-5 [scrollbar-width:none] lg:col-start-2 lg:row-start-2 [&::-webkit-scrollbar]:hidden" aria-live="polite" aria-label="Conversation">
        @forelse ($messages as $message)
            <article @class([
                'mb-5 max-w-[88%]',
                'ml-auto' => $message['role'] === 'user',
            ])>
                <flux:text class="mb-1 ml-1 text-xs">
                    {{ $message['role'] === 'user' ? 'You' : 'Assistant' }}
                </flux:text>
                <p @class([
                    'm-0 whitespace-pre-wrap rounded-xl px-4 py-3 leading-6',
                    'rounded-br-sm bg-zinc-200 dark:bg-zinc-700' => $message['role'] === 'user',
                    'rounded-bl-sm border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' => $message['role'] === 'assistant',
                ])>{{ $message['content'] }}</p>
            </article>
        @empty
            <div class="flex min-h-72 flex-col items-center justify-center text-center">
                <flux:avatar icon="microphone" size="xl" />
                <flux:heading size="xl" class="mt-4">Ready when you are.</flux:heading>
                <flux:text class="mt-1 max-w-xs">Tap once to speak. Tap again when you’re done.</flux:text>
            </div>
        @endforelse

        <p
            wire:stream="assistant-response"
            class="mb-5 max-w-[88%] whitespace-pre-wrap rounded-xl rounded-bl-sm border border-zinc-200 bg-white px-4 py-3 leading-6 empty:hidden dark:border-zinc-700 dark:bg-zinc-900"
        ></p>

        @if ($pendingApprovals !== [])
            <div class="mt-6 space-y-3" aria-label="Tool approvals">
                <div class="flex items-center gap-2">
                    <flux:icon.shield-check class="size-5" />
                    <flux:heading size="sm">Review requested actions</flux:heading>
                </div>

                @foreach ($pendingApprovals as $approval)
                    <flux:callout wire:key="approval-{{ $approval['id'] }}" variant="warning" icon="shield-exclamation">
                        <flux:callout.heading>{{ str($approval['tool'])->replace('_', ' ')->headline() }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ $approval['reason'] ?: 'This connected tool wants to perform an action.' }}
                        </flux:callout.text>
                        <pre class="mt-3 max-h-36 overflow-auto whitespace-pre-wrap break-words text-xs leading-5">{{ json_encode($approval['arguments'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <flux:button
                                size="sm"
                                variant="{{ ($approvalChoices[$approval['id']] ?? null) === 'reject' ? 'danger' : 'ghost' }}"
                                wire:click="chooseApproval('{{ $approval['id'] }}', 'reject')"
                            >
                                Deny
                            </flux:button>
                            <flux:button
                                size="sm"
                                variant="{{ ($approvalChoices[$approval['id']] ?? null) === 'approve' ? 'primary' : 'ghost' }}"
                                wire:click="chooseApproval('{{ $approval['id'] }}', 'approve')"
                            >
                                Allow
                            </flux:button>
                        </div>
                    </flux:callout>
                @endforeach

                @error('approvals')
                    <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror

                <flux:button wire:click="submitApprovals" variant="primary" class="w-full">
                    Continue
                </flux:button>
            </div>
        @endif
    </section>

    <section class="flex flex-col items-center border-t border-zinc-200 pt-4 dark:border-zinc-700 lg:col-start-1 lg:row-start-2 lg:justify-center lg:border-t-0 lg:border-r lg:pr-6" aria-label="Voice controls">
        <flux:text @class([
            'mb-3 min-h-5 text-center text-sm',
            'text-red-600 dark:text-red-400' => $state === 'error',
        ])>{{ $status }}</flux:text>

        <flux:button
            class="!size-20 !min-h-20 !min-w-20 !rounded-full !p-0 [&>svg]:!size-8"
            variant="{{ $state === 'recording' ? 'danger' : 'primary' }}"
            icon="{{ $state === 'recording' ? 'stop' : 'microphone' }}"
            square
            :loading="$state === 'thinking'"
            :disabled="$state === 'awaiting_approval'"
            wire:click="toggleRecording"
            aria-label="{{ $state === 'recording' ? 'Stop recording' : 'Start recording' }}"
        />

        @if ($state === 'error')
            <flux:callout variant="danger" icon="exclamation-triangle" class="mt-4 w-full max-w-sm" :heading="$status" />
        @endif

        <div class="mt-4 w-full max-w-sm">
            <flux:accordion exclusive transition>
                <flux:accordion.item heading="Type instead" class="text-center">
                    <form class="flex flex-col gap-3" wire:submit="sendText">
                        <flux:textarea
                            wire:model="draft"
                            rows="2"
                            resize="none"
                            placeholder="What’s on your mind?"
                            maxlength="12000"
                        />
                        <flux:button type="submit" variant="primary" class="w-full">Send</flux:button>
                    </form>

                    <div class="mt-4">
                        <flux:file-upload wire:model="recording">
                            <flux:file-upload.dropzone
                                heading="Test an audio file"
                                text="M4A, MP3, WAV, or WebM"
                                icon="microphone"
                                inline
                            />
                        </flux:file-upload>
                    </div>
                </flux:accordion.item>
            </flux:accordion>
        </div>
    </section>

    <audio x-ref="player" preload="auto" class="hidden"></audio>
</div>
