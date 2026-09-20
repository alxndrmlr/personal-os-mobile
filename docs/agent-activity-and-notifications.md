# Agent activity and notifications

## What is implemented

Every assistant turn now creates an `agent_activities` record with one of four
states:

- `working`
- `needs_input`
- `completed`
- `failed`

The voice screen polls the five most recent records and shows a compact activity
card. The same state survives app restarts and can also be updated by queued
jobs as longer-running agents are introduced.

NativePHP's local-notifications plugin requests system permission from the
activity card. A local iOS or Android notification is scheduled when an agent:

- pauses for tool approval;
- completes; or
- fails.

Notification previews intentionally contain no prompt or response text. Tapping
a notification opens the app, where the full activity and conversation remain
available.

## Background execution

`QUEUE_CONNECTION=database` is already configured. NativePHP Mobile runs that
queue on a separate on-device PHP thread, so future long-running agent jobs can
use `AgentActivityTracker` without blocking the voice UI. The current voice turn
still streams in the foreground because moving it to a queued job would remove
the token-by-token response unless a separate event transport is added.

Local notifications are the correct delivery mechanism for work performed on
this device. Agents running on a remote server will need APNs/FCM push
notifications instead, because local code cannot schedule a notification after
iOS has terminated the app.

## iOS Live Activities

NativePHP Mobile 4 does not currently expose ActivityKit. Apple's implementation
also requires a WidgetKit extension, shared `ActivityAttributes`, app-group
entitlements, and SwiftUI lock-screen/Dynamic Island layouts. Setting
`NSSupportsLiveActivities` alone is not sufficient.

The persisted activity model is the source of truth for that extension. A future
NativePHP plugin should expose these bridge operations:

```text
AgentActivity.startOrUpdate([
  { id, title, status, detail, started_at }
])

AgentActivity.end()
```

The bridge should maintain one Live Activity containing the active run list,
update only when a status changes, and end it when no `working` or
`needs_input` records remain. The widget should show only a count and generic
status on the lock screen unless the user explicitly opts into sensitive
previews.

This extension must be added through a reproducible NativePHP plugin/build hook,
not by editing the generated `nativephp/ios` Xcode project. It requires final
compilation and verification on a Mac with a physical iPhone.
