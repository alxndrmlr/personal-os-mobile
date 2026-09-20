<div
    class="voice-shell mx-auto grid min-h-dvh w-full max-w-5xl grid-rows-[auto_minmax(12rem,1fr)_auto] px-[max(1.25rem,env(safe-area-inset-right))] pb-[max(1rem,env(safe-area-inset-bottom))] pl-[max(1.25rem,env(safe-area-inset-left))] pt-[max(1rem,env(safe-area-inset-top))] lg:max-w-6xl lg:grid-cols-[minmax(18rem,.78fr)_minmax(22rem,1.22fr)] lg:grid-rows-[auto_1fr] lg:gap-x-8"
    data-state="{{ $state }}"
    x-data="voiceSurface"
    x-on:start-browser-recording="startBrowserRecording()"
    x-on:stop-browser-recording="stopBrowserRecording()"
    x-on:assistant-spoken="speak($event.detail.url, $event.detail.text)"
>
    <header class="flex items-center gap-3 pb-4 lg:col-span-2">
        <div class="grid size-11 place-items-center rounded-2xl border border-accent/25 bg-zinc-900 shadow-[inset_0_0_1.25rem_rgba(95,229,162,.08)]">
            <span class="block h-5 w-3 rounded-full bg-accent shadow-[0_0_1.2rem_rgba(131,229,179,.55)]"></span>
        </div>

        <div>
            <flux:text class="text-[.68rem] font-bold uppercase tracking-[.14em] text-zinc-400">Personal OS</flux:text>
            <flux:heading size="lg" level="1">Voice</flux:heading>
        </div>

        <flux:badge color="emerald" variant="solid" icon="lock-closed" rounded class="ml-auto">Private</flux:badge>
    </header>

    <section class="min-h-0 overflow-y-auto py-5 [scrollbar-width:none] lg:col-start-2 lg:row-start-2 [&::-webkit-scrollbar]:hidden" aria-live="polite" aria-label="Conversation">
        @forelse ($messages as $message)
            <article @class([
                'mb-5 max-w-[88%]',
                'ml-auto' => $message['role'] === 'user',
            ])>
                <flux:text class="mb-1.5 ml-1 text-[.68rem] font-bold uppercase tracking-[.08em] text-zinc-500">
                    {{ $message['role'] === 'user' ? 'You' : 'Assistant' }}
                </flux:text>
                <p @class([
                    'm-0 whitespace-pre-wrap rounded-[1.15rem] px-4 py-3.5 leading-6',
                    'rounded-br-sm bg-accent text-accent-foreground' => $message['role'] === 'user',
                    'rounded-bl-sm border border-white/10 bg-zinc-900 text-zinc-100' => $message['role'] === 'assistant',
                ])>{{ $message['content'] }}</p>
            </article>
        @empty
            <div class="flex min-h-72 flex-col items-center justify-center text-center">
                <div class="voice-orb flex size-32 items-center justify-center gap-1.5 rounded-full border border-accent/20 bg-[radial-gradient(circle,rgba(84,216,149,.18),rgba(14,37,29,.4)_55%,transparent_70%)] shadow-[0_0_4rem_rgba(70,199,134,.08)]" aria-hidden="true">
                    <i></i><i></i><i></i><i></i><i></i>
                </div>
                <flux:heading size="xl" class="mt-6">Ready when you are.</flux:heading>
                <flux:text class="mt-1.5 max-w-xs text-zinc-400">Tap once to speak. Tap again when you’re done.</flux:text>
            </div>
        @endforelse
    </section>

    <section class="flex flex-col items-center border-t border-white/10 pt-4 lg:col-start-1 lg:row-start-2 lg:justify-center lg:border-t-0 lg:border-r lg:pr-8" aria-label="Voice controls">
        <flux:text @class([
            'mb-3.5 min-h-5 text-center text-sm tracking-wide',
            'text-zinc-400' => $state !== 'error',
            'text-red-400' => $state === 'error',
        ])>{{ $status }}</flux:text>

        <flux:button
            class="talk-button !size-20 !min-h-20 !min-w-20 !rounded-full !p-0 [&>svg]:!size-8"
            variant="{{ $state === 'recording' ? 'danger' : 'primary' }}"
            icon="{{ $state === 'recording' ? 'stop' : 'microphone' }}"
            square
            :loading="$state === 'thinking'"
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
