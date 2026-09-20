<native:scroll-view fill class="bg-theme-background">
    <native:column class="w-full p-4 gap-5">
        <native:column class="w-full p-4 gap-4 bg-theme-surface rounded-2xl">
            <native:column class="w-full gap-1">
                <native:text class="text-lg font-semibold text-theme-on-surface">Add MCP server</native:text>
                <native:text class="text-sm text-theme-on-surface-variant">
                    Add any Streamable HTTP endpoint. Credentials stay encrypted on this device.
                </native:text>
            </native:column>

            <outlined-text-input
                ref="server-name"
                label="Name"
                placeholder="Linear"
                native:model.blur="name"
                :is-error="isset($errors['name'])"
                supporting="{{ $errors['name'][0] ?? '' }}"
            />
            <outlined-text-input
                ref="server-url"
                label="Server URL"
                placeholder="https://example.com/mcp"
                keyboard="url"
                autocapitalize="none"
                native:model.blur="url"
                :is-error="isset($errors['url'])"
                supporting="{{ $errors['url'][0] ?? '' }}"
            />
            <native:select
                ref="auth-type"
                label="Authentication"
                :options="['OAuth 2.1', 'Bearer token', 'None']"
                native:model="authType"
            />

            @if ($authType === 'Bearer token')
                <outlined-text-input
                    ref="server-token"
                    label="Bearer token"
                    keyboard="password"
                    secure
                    autocapitalize="none"
                    native:model.blur="token"
                    :is-error="isset($errors['token'])"
                    supporting="{{ $errors['token'][0] ?? '' }}"
                />
            @endif

            <native:select
                ref="approval-mode"
                label="Tool approval"
                :options="['Writes and unknown', 'Every tool call', 'Never']"
                native:model="approvalMode"
            />
            <native:button ref="add-server" label="Add server" icon="plus" @press="addServer" />
        </native:column>

        <native:column class="w-full gap-3">
            <native:text class="text-lg font-semibold text-theme-on-background">Your servers</native:text>

            @forelse ($servers as $server)
                <pressable
                    ref="server-{{ $server['id'] }}"
                    class="w-full p-4 bg-theme-surface rounded-2xl"
                    press-scale="0.98"
                    @press="openServer({{ $server['id'] }})"
                >
                    <native:row class="w-full items-center gap-3">
                        <native:icon
                            name="{{ $server['connected'] ? 'checkmark.circle.fill' : 'link.circle' }}"
                            :size="24"
                            color="{{ $server['last_error'] ? theme('destructive') : theme('primary') }}"
                        />
                        <native:column class="flex-1 gap-1">
                            <native:text class="text-base font-semibold text-theme-on-surface">
                                {{ $server['name'] }}
                            </native:text>
                            <native:text class="text-xs text-theme-on-surface-variant">
                                {{ $server['url'] }}
                            </native:text>
                            <native:text class="text-xs text-theme-on-surface-variant">
                                {{ $server['enabled'] ? 'Enabled' : 'Disabled' }}
                                · {{ str($server['auth_type'])->headline() }}
                            </native:text>
                        </native:column>
                        <native:icon name="chevron.right" :size="18" />
                    </native:row>
                </pressable>
            @empty
                <native:column class="w-full p-5 gap-2 bg-theme-surface rounded-2xl items-center">
                    <native:icon name="link" :size="32" />
                    <native:text class="text-base font-medium text-theme-on-surface">No servers yet</native:text>
                    <native:text class="text-sm text-theme-on-surface-variant text-center">
                        Add a server above to make its tools available to your agent.
                    </native:text>
                </native:column>
            @endforelse
        </native:column>
    </native:column>
</native:scroll-view>
