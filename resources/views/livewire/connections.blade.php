<main
    class="mx-auto min-h-dvh w-full max-w-3xl px-[max(1.25rem,env(safe-area-inset-right))] pb-[max(2rem,env(safe-area-inset-bottom))] pl-[max(1.25rem,env(safe-area-inset-left))] pt-[max(1rem,env(safe-area-inset-top))]"
    x-data="{ notice: '' }"
    x-on:connection-tested.window="notice = $event.detail.message; setTimeout(() => notice = '', 3500)"
>
    <header class="mb-8 flex items-center gap-3">
        <flux:button href="{{ route('voice.index') }}" variant="ghost" icon="arrow-left" square aria-label="Back to voice" />
        <div>
            <flux:text class="text-[.68rem] font-bold uppercase tracking-[.14em] text-zinc-400">Personal OS</flux:text>
            <flux:heading size="xl" level="1">Connections</flux:heading>
        </div>
        <flux:badge color="emerald" variant="outline" rounded class="ml-auto">MCP</flux:badge>
    </header>

    <flux:callout
        x-show="notice"
        x-cloak
        variant="success"
        icon="check-circle"
        class="mb-5"
        x-text="notice"
    />

    <section aria-labelledby="quick-connect">
        <flux:heading id="quick-connect" size="lg">Quick connect</flux:heading>
        <flux:text class="mt-1 text-zinc-400">Official hosted endpoints plus your Backbone gateway.</flux:text>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ($presets as $slug => $preset)
                @php($installed = $servers->firstWhere('slug', $slug))
                <div class="rounded-2xl border border-white/10 bg-zinc-900/70 p-4">
                    <div class="flex items-start gap-3">
                        <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent/10 text-sm font-bold text-accent">
                            {{ str($preset['name'])->substr(0, 1) }}
                        </div>
                        <div class="min-w-0">
                            <flux:heading size="sm">{{ $preset['name'] }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-400">{{ $preset['description'] }}</flux:text>
                        </div>
                    </div>

                    <flux:button
                        class="mt-4 w-full"
                        variant="{{ $installed ? 'ghost' : 'primary' }}"
                        wire:click="installPreset('{{ $slug }}')"
                        :disabled="(bool) $installed"
                    >
                        {{ $installed ? 'Added' : 'Add connection' }}
                    </flux:button>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-9" aria-labelledby="configured-connections">
        <div class="flex items-end justify-between gap-4">
            <div>
                <flux:heading id="configured-connections" size="lg">Configured</flux:heading>
                <flux:text class="mt-1 text-zinc-400">Secrets are encrypted before they reach SQLite.</flux:text>
            </div>
            <flux:badge rounded>{{ $servers->count() }}</flux:badge>
        </div>

        <div class="mt-4 space-y-4">
            @forelse ($servers as $server)
                <article wire:key="server-{{ $server->id }}" class="rounded-2xl border border-white/10 bg-zinc-900/70 p-5">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading size="sm">{{ $server->name }}</flux:heading>
                                @if ($server->is_connected)
                                    <flux:badge color="emerald" size="sm" rounded>Connected</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm" rounded>Needs credentials</flux:badge>
                                @endif
                                @unless ($server->enabled)
                                    <flux:badge size="sm" rounded>Paused</flux:badge>
                                @endunless
                            </div>
                            <flux:text class="mt-1 truncate text-xs text-zinc-500">{{ $server->url }}</flux:text>
                        </div>

                        <flux:switch
                            :checked="$server->enabled"
                            wire:click="toggle({{ $server->id }})"
                            aria-label="Enable {{ $server->name }}"
                        />
                    </div>

                    @error("server.{$server->id}")
                        <flux:callout variant="danger" icon="exclamation-triangle" class="mt-4">{{ $message }}</flux:callout>
                    @enderror

                    @if ($server->last_error)
                        <flux:callout variant="warning" icon="exclamation-circle" class="mt-4">
                            This server failed during its last tool discovery. Reconnect or test it again.
                        </flux:callout>
                    @endif

                    <div class="mt-4 grid gap-3">
                        @if ($server->auth_type === 'bearer')
                            <flux:input
                                wire:model="tokens.{{ $server->id }}"
                                type="password"
                                label="Bearer token"
                                placeholder="{{ $server->is_connected ? 'Replace saved token' : 'Paste token' }}"
                                viewable
                            />
                            <flux:button wire:click="saveCredentials({{ $server->id }})">Save token</flux:button>
                        @elseif ($server->auth_type === 'oauth')
                            <div class="grid gap-3 sm:grid-cols-2">
                                <flux:input
                                    wire:model="clientIds.{{ $server->id }}"
                                    label="OAuth client ID"
                                    placeholder="{{ filled($server->oauth_client_id) ? 'Saved' : 'Optional for Linear/Notion' }}"
                                />
                                <flux:input
                                    wire:model="clientSecrets.{{ $server->id }}"
                                    type="password"
                                    label="OAuth client secret"
                                    placeholder="{{ filled($server->oauth_client_secret) ? 'Saved' : 'Required by Slack' }}"
                                    viewable
                                />
                            </div>
                            <flux:input
                                wire:model="scopes.{{ $server->id }}"
                                label="OAuth scopes"
                                placeholder="{{ $server->oauth_scope ?: 'Provider defaults' }}"
                            />
                            <div class="rounded-xl border border-white/10 bg-black/20 p-3">
                                <flux:text class="text-xs font-semibold text-zinc-400">OAuth callback URL</flux:text>
                                <code class="mt-1 block break-all text-xs text-zinc-500">{{ route('connections.oauth.callback', $server->slug) }}</code>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <flux:button wire:click="saveCredentials({{ $server->id }})">Save OAuth settings</flux:button>
                                <flux:button
                                    href="{{ route('connections.oauth.connect', $server->slug) }}"
                                    variant="primary"
                                    icon="arrow-top-right-on-square"
                                >
                                    {{ $server->is_connected ? 'Reconnect' : 'Authorize' }}
                                </flux:button>
                            </div>
                        @endif
                    </div>

                    <div class="mt-5 border-t border-white/10 pt-4">
                        <flux:text class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Approval policy</flux:text>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (['writes' => 'Writes', 'always' => 'Always', 'never' => 'Never'] as $mode => $label)
                                <flux:button
                                    size="sm"
                                    variant="{{ $server->approval_mode === $mode ? 'primary' : 'ghost' }}"
                                    wire:click="setApprovalMode({{ $server->id }}, '{{ $mode }}')"
                                >
                                    {{ $label }}
                                </flux:button>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 flex justify-between gap-3">
                        <flux:button
                            size="sm"
                            variant="danger"
                            wire:click="remove({{ $server->id }})"
                            wire:confirm="Remove {{ $server->name }} and its saved credentials?"
                        >
                            Remove
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="signal"
                            wire:click="test({{ $server->id }})"
                            :disabled="! $server->is_connected"
                        >
                            Test
                        </flux:button>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-white/15 p-7 text-center">
                    <flux:text class="text-zinc-400">No MCP servers yet.</flux:text>
                </div>
            @endforelse
        </div>
    </section>

    <section class="mt-7">
        <flux:accordion transition>
            <flux:accordion.item heading="Add a custom MCP server">
                <form wire:submit="addCustom" class="grid gap-4 pt-2">
                    <flux:input wire:model="name" label="Name" placeholder="My MCP server" />
                    <flux:input wire:model="url" type="url" label="HTTPS endpoint" placeholder="https://example.com/mcp" />
                    <flux:select wire:model.live="authType" label="Authentication">
                        <flux:select.option value="none">No authentication</flux:select.option>
                        <flux:select.option value="bearer">Bearer token</flux:select.option>
                        <flux:select.option value="oauth">OAuth 2.1</flux:select.option>
                    </flux:select>
                    @if ($authType === 'bearer')
                        <flux:input wire:model="token" type="password" label="Bearer token" viewable />
                    @endif
                    <flux:select wire:model="approvalMode" label="Approval policy">
                        <flux:select.option value="writes">Approve writes and unknown tools</flux:select.option>
                        <flux:select.option value="always">Approve every tool call</flux:select.option>
                        <flux:select.option value="never">Never ask</flux:select.option>
                    </flux:select>
                    <flux:button type="submit" variant="primary">Add server</flux:button>
                </form>
            </flux:accordion.item>
        </flux:accordion>
    </section>
</main>
