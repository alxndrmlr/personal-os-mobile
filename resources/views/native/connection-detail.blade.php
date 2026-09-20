<native:scroll-view fill class="bg-theme-background">
    <native:column class="w-full p-4 gap-4">
        <native:column class="w-full p-4 gap-3 bg-theme-surface rounded-2xl">
            <native:row class="w-full items-center gap-3">
                <native:icon
                    name="{{ $connected ? 'checkmark.circle.fill' : 'link.circle' }}"
                    :size="30"
                    color="{{ $connected ? theme('success') : theme('primary') }}"
                />
                <native:column class="flex-1 gap-1">
                    <native:text class="text-xl font-semibold text-theme-on-surface">{{ $name }}</native:text>
                    <native:text class="text-xs text-theme-on-surface-variant select-text">{{ $url }}</native:text>
                </native:column>
                <native:toggle
                    ref="server-enabled"
                    a11y-label="Enable {{ $name }}"
                    native:model="enabled"
                />
            </native:row>
        </native:column>

        @if ($authType === 'bearer')
            <native:column class="w-full p-4 gap-3 bg-theme-surface rounded-2xl">
                <native:text class="text-base font-semibold text-theme-on-surface">Bearer credentials</native:text>
                <native:outlined-text-input
                    ref="bearer-token"
                    label="New bearer token"
                    keyboard="password"
                    secure
                    autocapitalize="none"
                    native:model.blur="token"
                />
                <native:button label="Save token" icon="lock" @press="saveCredentials" />
            </native:column>
        @elseif ($authType === 'oauth')
            <native:column class="w-full p-4 gap-3 bg-theme-surface rounded-2xl">
                <native:text class="text-base font-semibold text-theme-on-surface">OAuth 2.1</native:text>
                <native:text class="text-sm text-theme-on-surface-variant">
                    Dynamic client registration is used when client credentials are left empty.
                </native:text>
                <native:outlined-text-input
                    label="Client ID (optional)"
                    autocapitalize="none"
                    native:model.blur="clientId"
                />
                <native:outlined-text-input
                    label="Client secret (optional)"
                    keyboard="password"
                    secure
                    autocapitalize="none"
                    native:model.blur="clientSecret"
                />
                <native:outlined-text-input
                    label="Scopes (optional)"
                    autocapitalize="none"
                    native:model.blur="scope"
                />
                <native:row class="w-full gap-2">
                    <native:button
                        label="Save settings"
                        variant="secondary"
                        class="flex-1"
                        @press="saveCredentials"
                    />
                    <native:button
                        ref="oauth-authorize"
                        label="{{ $connected ? 'Reconnect' : 'Authorize' }}"
                        class="flex-1"
                        @press="authorize"
                    />
                </native:row>
            </native:column>
        @endif

        <native:column class="w-full p-4 gap-3 bg-theme-surface rounded-2xl">
            <native:text class="text-base font-semibold text-theme-on-surface">Tool approval</native:text>
            <native:select
                ref="server-approval"
                label="Policy"
                :options="['Writes and unknown', 'Every tool call', 'Never']"
                native:model="approvalMode"
            />
            <native:text class="text-xs text-theme-on-surface-variant">
                “Never” should only be used for an MCP server you fully trust.
            </native:text>
        </native:column>

        @if ($status !== '')
            <native:column class="w-full p-3 bg-theme-primary/15 rounded-xl">
                <native:text class="text-sm text-theme-primary">{{ $status }}</native:text>
            </native:column>
        @endif

        @if ($error !== '')
            <native:column class="w-full p-3 bg-theme-destructive/15 rounded-xl">
                <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
            </native:column>
        @endif

        <native:button
            ref="test-connection"
            label="Test connection"
            icon="bolt"
            variant="secondary"
            :loading="$testing"
            @press="testConnection"
        />
        <native:button
            ref="remove-server"
            label="Remove server"
            icon="trash"
            variant="destructive"
            @press="remove"
        />
    </native:column>
</native:scroll-view>
