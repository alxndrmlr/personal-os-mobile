<native:column fill class="bg-theme-background">
    <native:scroll-view fill scroll-anchor="bottom">
        <native:column fill class="w-full p-4 gap-4">
            @if ($messages === [])
                <native:column fill class="w-full items-center justify-center gap-3">
                    <native:icon name="waveform" :size="44" color="{{ theme('primary') }}" />
                    <native:text class="text-2xl font-semibold text-theme-on-background text-center">
                        What can I help with?
                    </native:text>
                    <native:text class="text-sm text-theme-on-surface-variant text-center">
                        Talk naturally or type a request below.
                    </native:text>
                </native:column>
            @else
                @foreach ($messages as $index => $message)
                    <native:column
                        ref="message-{{ $index }}"
                        class="max-w-[340] px-4 py-3 gap-1 rounded-2xl {{ $message['role'] === 'user'
                            ? 'self-end bg-theme-primary'
                            : 'self-start bg-theme-surface' }}"
                    >
                        <native:text class="text-xs font-semibold {{ $message['role'] === 'user'
                            ? 'text-theme-on-primary'
                            : 'text-theme-on-surface-variant' }}">
                            {{ $message['role'] === 'user' ? 'You' : 'Assistant' }}
                        </native:text>
                        <native:text class="text-base {{ $message['role'] === 'user'
                            ? 'text-theme-on-primary'
                            : 'text-theme-on-surface' }}">
                            {{ $message['content'] }}
                        </native:text>
                    </native:column>
                @endforeach
            @endif

            @if ($state === 'thinking')
                <native:row class="self-start items-center gap-3 px-4 py-3 bg-theme-surface rounded-2xl">
                    <native:activity-indicator size="sm" a11y-label="Agent is working" />
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $status }}</native:text>
                </native:row>
            @endif

            @if ($pendingApprovals !== [])
                <native:column class="w-full p-4 gap-4 bg-theme-surface rounded-2xl border border-theme-outline">
                    <native:row class="w-full items-center gap-2">
                        <native:icon name="shield" :size="20" color="{{ theme('accent') }}" />
                        <native:text class="text-lg font-semibold text-theme-on-surface">
                            Review requested actions
                        </native:text>
                    </native:row>

                    @foreach ($pendingApprovals as $approval)
                        <native:column ref="approval-{{ $approval['id'] }}" class="w-full gap-2">
                            <native:text class="text-base font-semibold text-theme-on-surface">
                                {{ str($approval['tool'])->headline() }}
                            </native:text>
                            @if ($approval['reason'])
                                <native:text class="text-sm text-theme-on-surface-variant">
                                    {{ $approval['reason'] }}
                                </native:text>
                            @endif
                            <native:text class="text-xs font-mono text-theme-on-surface-variant select-text">
                                {{ json_encode($approval['arguments'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
                            </native:text>
                            <native:row class="w-full gap-2">
                                <native:button
                                    label="{{ ($approvalChoices[$approval['id']] ?? null) === 'approve' ? 'Allowed' : 'Allow' }}"
                                    variant="{{ ($approvalChoices[$approval['id']] ?? null) === 'approve' ? 'success' : 'secondary' }}"
                                    @press="chooseApproval('{{ $approval['id'] }}', 'approve')"
                                />
                                <native:button
                                    label="{{ ($approvalChoices[$approval['id']] ?? null) === 'reject' ? 'Denied' : 'Deny' }}"
                                    variant="{{ ($approvalChoices[$approval['id']] ?? null) === 'reject' ? 'destructive' : 'ghost' }}"
                                    @press="chooseApproval('{{ $approval['id'] }}', 'reject')"
                                />
                            </native:row>
                        </native:column>
                    @endforeach

                    @if ($approvalError !== '')
                        <native:text class="text-sm text-theme-destructive">{{ $approvalError }}</native:text>
                    @endif

                    <native:button
                        ref="continue-approvals"
                        label="Continue"
                        icon="arrow.forward"
                        @press="submitApprovals"
                    />
                </native:column>
            @endif

            <native:column class="w-full p-4 gap-3 bg-theme-surface rounded-2xl">
                <native:row class="w-full items-center gap-2">
                    <native:icon name="bolt" :size="18" />
                    <native:text class="text-base font-semibold text-theme-on-surface">Agent activity</native:text>
                    @if ($activeActivityCount > 0)
                        <native:spacer />
                        <native:text class="text-sm font-medium text-theme-primary">
                            {{ $activeActivityCount }} active
                        </native:text>
                    @endif
                </native:row>

                @forelse ($activities as $activity)
                    <native:row class="w-full items-start gap-3">
                        <native:icon
                            name="{{ match ($activity['status']) {
                                'working' => 'clock',
                                'needs_input' => 'questionmark.circle',
                                'completed' => 'checkmark.circle',
                                default => 'exclamationmark.circle',
                            } }}"
                            :size="18"
                            color="{{ match ($activity['status']) {
                                'working' => theme('primary'),
                                'needs_input' => theme('accent'),
                                'completed' => theme('success'),
                                default => theme('destructive'),
                            } }}"
                        />
                        <native:column class="flex-1 gap-1">
                            <native:text class="text-sm font-medium text-theme-on-surface">
                                {{ $activity['title'] }}
                            </native:text>
                            <native:text class="text-xs text-theme-on-surface-variant">
                                {{ $activity['detail'] }}
                            </native:text>
                        </native:column>
                    </native:row>
                @empty
                    <native:text class="text-sm text-theme-on-surface-variant">
                        Agent runs will appear here.
                    </native:text>
                @endforelse

                <native:button
                    ref="notifications"
                    label="{{ $notificationPermissionRequested ? 'Permission requested' : 'Enable system notifications' }}"
                    variant="ghost"
                    size="sm"
                    icon="bell"
                    :disabled="$notificationPermissionRequested"
                    @press="enableNotifications"
                />
            </native:column>
        </native:column>
    </native:scroll-view>

    <native:column class="w-full p-4 gap-3 bg-theme-surface border-theme-outline">
        <native:outlined-text-input
            ref="message-input"
            native:model.blur="draft"
            placeholder="Message your agent"
            max-lines="4"
            multiline
            keep-focus-on-submit
            :disabled="in_array($state, ['thinking', 'awaiting_approval'], true)"
            @submit="sendText"
        />
        <native:row class="w-full gap-3">
            <native:button
                ref="microphone"
                label="{{ $state === 'recording' ? 'Stop' : 'Talk' }}"
                icon="{{ $state === 'recording' ? 'stop.fill' : 'microphone.fill' }}"
                variant="{{ $state === 'recording' ? 'destructive' : 'secondary' }}"
                class="flex-1"
                :disabled="in_array($state, ['thinking', 'awaiting_approval'], true)"
                @press="toggleRecording"
            />
            <native:button
                ref="send"
                label="Send"
                icon-trailing="arrow.up.circle.fill"
                class="flex-1"
                :loading="$state === 'thinking'"
                :disabled="$state === 'awaiting_approval'"
                @press="sendText"
            />
        </native:row>
        <native:text class="text-xs text-theme-on-surface-variant text-center">{{ $status }}</native:text>
    </native:column>
</native:column>
