<main
    class="mx-auto min-h-dvh w-full max-w-2xl px-[max(1rem,env(safe-area-inset-right))] pb-[max(1.5rem,env(safe-area-inset-bottom))] pl-[max(1rem,env(safe-area-inset-left))] pt-[max(1rem,env(safe-area-inset-top))]"
    x-data="{ notice: '' }"
    x-on:connection-tested.window="notice = $event.detail.message; setTimeout(() => notice = '', 3500)"
>
    <header class="mb-6 flex items-center gap-3">
        <flux:button href="{{ route('voice.index') }}" variant="ghost" icon="arrow-left" square aria-label="Back to voice" />
        <div>
            <flux:heading size="xl" level="1">Connections</flux:heading>
            <flux:text>Remote MCP servers available to your assistant.</flux:text>
        </div>
    </header>

    <flux:callout x-show="notice" x-cloak variant="success" icon="check-circle" class="mb-4" x-text="notice" />

    <flux:fieldset>
        <flux:legend>Add server</flux:legend>

        <form wire:submit="addServer" class="grid gap-3">
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="name" size="sm" label="Name" placeholder="Work" />
                <flux:input wire:model="url" size="sm" type="url" label="MCP URL" placeholder="https://example.com/mcp" />
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model.live="authType" size="sm" label="Authentication">
                    <flux:select.option value="oauth">OAuth 2.1</flux:select.option>
                    <flux:select.option value="bearer">Bearer token</flux:select.option>
                    <flux:select.option value="none">None</flux:select.option>
                </flux:select>

                <flux:select wire:model="approvalMode" size="sm" label="Approvals">
                    <flux:select.option value="writes">Writes and unknown tools</flux:select.option>
                    <flux:select.option value="always">Every tool call</flux:select.option>
                    <flux:select.option value="never">Never</flux:select.option>
                </flux:select>
            </div>

            @if ($authType === 'bearer')
                <flux:input wire:model="token" size="sm" type="password" label="Bearer token" viewable />
            @endif

            <div>
                <flux:button type="submit" size="sm" variant="primary" icon="plus">Add server</flux:button>
            </div>
        </form>
    </flux:fieldset>

    <flux:separator class="my-6" />

    <div class="mb-2 flex items-center justify-between">
        <flux:heading size="lg">Servers</flux:heading>
        <flux:badge size="sm">{{ $servers->count() }}</flux:badge>
    </div>

    @if ($servers->isEmpty())
        <flux:callout icon="server-stack">
            Add an MCP URL above to make its tools available to the assistant.
        </flux:callout>
    @else
        <flux:accordion exclusive transition>
            @foreach ($servers as $server)
                <flux:accordion.item wire:key="server-{{ $server->id }}" :heading="$server->name">
                    <div class="grid gap-4 pb-2">
                        <div class="flex min-w-0 items-center gap-2">
                            @if ($server->is_connected)
                                <flux:badge color="green" size="sm">Connected</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">Credentials needed</flux:badge>
                            @endif
                            <flux:badge size="sm">{{ strtoupper($server->auth_type) }}</flux:badge>
                            <flux:text class="min-w-0 flex-1 truncate text-xs">{{ $server->url }}</flux:text>
                            <flux:switch
                                :checked="$server->enabled"
                                wire:click="toggle({{ $server->id }})"
                                aria-label="Enable {{ $server->name }}"
                            />
                        </div>

                        @error("server.{$server->id}")
                            <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
                        @enderror

                        @if ($server->last_error)
                            <flux:callout variant="warning" icon="exclamation-circle">
                                Tool discovery failed on the last connection attempt.
                            </flux:callout>
                        @endif

                        @if ($server->auth_type === 'bearer')
                            <div class="flex items-end gap-2">
                                <flux:input
                                    wire:model="tokens.{{ $server->id }}"
                                    size="sm"
                                    type="password"
                                    label="Bearer token"
                                    placeholder="{{ $server->is_connected ? 'Replace saved token' : 'Paste token' }}"
                                    viewable
                                    class="flex-1"
                                />
                                <flux:button size="sm" wire:click="saveCredentials({{ $server->id }})">Save</flux:button>
                            </div>
                        @elseif ($server->auth_type === 'oauth')
                            <div class="grid gap-3 sm:grid-cols-2">
                                <flux:input
                                    wire:model="clientIds.{{ $server->id }}"
                                    size="sm"
                                    label="Client ID"
                                    placeholder="{{ filled($server->oauth_client_id) ? 'Saved' : 'Optional with dynamic registration' }}"
                                />
                                <flux:input
                                    wire:model="clientSecrets.{{ $server->id }}"
                                    size="sm"
                                    type="password"
                                    label="Client secret"
                                    placeholder="{{ filled($server->oauth_client_secret) ? 'Saved' : 'Optional' }}"
                                    viewable
                                />
                            </div>
                            <flux:input
                                wire:model="scopes.{{ $server->id }}"
                                size="sm"
                                label="Scopes"
                                placeholder="{{ $server->oauth_scope ?: 'Provider defaults' }}"
                            />
                            <flux:input
                                size="sm"
                                label="Callback URL"
                                value="{{ route('connections.oauth.callback', $server->slug) }}"
                                readonly
                                copyable
                            />
                            <div class="flex gap-2">
                                <flux:button size="sm" wire:click="saveCredentials({{ $server->id }})">Save settings</flux:button>
                                <flux:button
                                    size="sm"
                                    href="{{ route('connections.oauth.connect', $server->slug) }}"
                                    variant="primary"
                                    icon="arrow-top-right-on-square"
                                >
                                    {{ $server->is_connected ? 'Reconnect' : 'Authorize' }}
                                </flux:button>
                            </div>
                        @endif

                        <div>
                            <flux:text class="mb-2 text-xs">Approval policy</flux:text>
                            <flux:button.group>
                                @foreach (['writes' => 'Writes', 'always' => 'Always', 'never' => 'Never'] as $mode => $label)
                                    <flux:button
                                        size="sm"
                                        variant="{{ $server->approval_mode === $mode ? 'primary' : 'outline' }}"
                                        wire:click="setApprovalMode({{ $server->id }}, '{{ $mode }}')"
                                    >
                                        {{ $label }}
                                    </flux:button>
                                @endforeach
                            </flux:button.group>
                        </div>

                        <div class="flex justify-between">
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
                    </div>
                </flux:accordion.item>
            @endforeach
        </flux:accordion>
    @endif
</main>
