# iPhone Duo: developer states, SDK, and Personal OS design notes

Researched 20 September 2026 for this private Livewire / Flux / NativePHP
voice app. Apple announced iPhone Duo on 9 September 2026. Device
availability is 23 October 2026 on iOS 27.1. The written SDK and Xcode 27.1
beta were still rolling out through late September; treat API spellings as
coming from Apple Tech Talks, the Human Interface Guidelines page *Designing
for iPhone Duo*, and the *Preparing your app for iPhone Duo* overview, not
from a frozen public header dump.

This is a design and architecture note, not a promise that NativePHP already
exposes every SwiftUI / UIKit symbol below.

## What the device actually is

iPhone Duo is a book-style foldable with two displays of the same aspect
ratio:

| Surface | Size (Apple Newsroom) | Role |
| --- | --- | --- |
| Outer cover | 5.4 in | Compact-width phone while closed |
| Inner canvas | 7.6 in | Regular-width canvas while open |

There is a hinge, an outer front camera (always occluding a region of the
cover display), and an inner under-display front camera (occluding only while
that camera is active). System chrome on the shorter, wider cover display
moves to the **side**: status bar, Dynamic Island / Live Activities,
navigation, toolbars, and tab bars share a vertical strip so content keeps
vertical room.

Build target for native Duo APIs is the **iOS 27.1 SDK in Xcode 27.1**, with
an iPhone Duo simulator in Device Hub (open / close / rotate / fold
controls). Apps still run if you do not rebuild; they just get letterboxed
or inset depending on SDK vintage (see [SDK compatibility tiers](#sdk-compatibility-tiers)).

Official starting points:

- [Get ready for iPhone Duo](https://developer.apple.com/iphone-duo/)
- [Get ready for iPhone Duo (news)](https://developer.apple.com/news/?id=vn8abkxx)
- Tech Talks: *Design for iPhone Duo*, *Prepare your app for iPhone Duo*,
  *Raise the bar with iPhone Duo*, *Strike a pose with adaptive layouts on
  iPhone Duo*, *Leverage multiple displays and scenes on iPhone Duo*,
  *Build a great camera experience for iPhone Duo*

## The rule Apple repeats

Do **not** design a unique layout per pose.

Design two size-class layouts:

1. **Compact width** — outer display, Split View slices, any narrow scene
2. **Regular width** — inner display when the app has the canvas

Avoid fixed widths, device-name breakpoints, `UIScreen.main`, and layout
math based on interface orientation. iPhone Mirroring, Split View, Picture
in Picture, and the fold all resize the scene. If the UI is freely
resizable and inset to the safe area, Apple’s claim is that most of Duo
falls out of existing adaptive work.

Hinge **angle** is for effects (Apple’s example is a pitch-bend control),
not for choosing a layout. Layout should follow arrangement containers,
reserved regions, size classes, and safe-area insets.

## Device states we should design for

Marketing names five poses (“anything’s posable”: StandBy, landscape,
portrait, seated, standing). Developer talks actually specify a smaller,
more useful set. Map them to **app states**, not product-page labels.

### 1. Closed — outer display

The cover is a short, wide compact-width phone.

- System bars go **vertical on the outer edge** (typically trailing / right
  in LTR, still hardware-aligned in RTL).
- Offset content into the horizontal safe area so it is not hidden behind
  that strip. Fully centered, full-bleed layouts are only for immersive
  non-scrolling surfaces whose controls will not land under the bar or
  camera.
- Sheets on the outer display get vertical controls.
- Continuity: alerts and trailing-side chrome on the inner display are
  placed so they remain near where they will appear after the device
  closes.

**Personal OS:** this is one-handed push-to-talk. Keep the talk button in
easy thumb reach, conversation as a single scrolling column, “Type instead”
collapsed. Do not put the only send control under the outer camera / Dynamic
Island.

### 2. Fully open, portrait — inner display

The inner canvas has enough vertical space that **bars stay horizontal**.
This is the one pose Apple keeps classic top/bottom chrome.

- Prefer a two-column or list-plus-detail layout rather than a stretched
  compact phone UI.
- Hierarchy must match the outer display. People open and close mid-task;
  Mail’s model is “message or list when closed, both when open,” not a
  different information architecture.

**Personal OS:** conversation as the primary column; composer / talk control
as secondary. Same actions as closed: talk, type, attach audio.

### 3. Fully open, landscape — inner display

Bars move back to the **side**. Extra width is for a durable two-pane
layout (inbox + message, transcript + player, health sidebar + chart).

**Personal OS:** this is the layout we already sketched: talk control on
one pane, live transcript on the other. Keep the talk button large; do not
let the dual-pane collapse the mic into a tiny icon-only control unless the
scene is extremely narrow.

### 4. Partially folded, book (hinge vertical)

The fold is an active **division region** down the center. The curve is
hard to read and hard to tap.

- Interactive elements **displace** off the fold (system does this for
  alerts, sheets, menus, popovers, toolbar buttons).
- **Scrolling** articles, feeds, lists, and transcripts do **not** displace;
  people already scroll through the curve.
- Alerts move to the **trailing** side so they stay close to the outer
  display after close.
- Split-style UIs go ~50/50 around the hinge.
- Grids should prefer an **even** column count whenever a division region
  exists (even if currently inactive), so a cell is not centered on the
  hinge.

**Personal OS:** put conversation on one leaf and the talk button / status
on the other. Never place the mic, Send, or a transcript bubble on the
hinge. The fold is a natural “you / assistant” divider.

### 5. Partially folded, table or tent (hinge horizontal)

One half is a distant viewing surface; the other is a stable touch surface.

- **Top / far region:** content you glance at (video, clock, transcript,
  waveform).
- **Bottom / near region:** tappable controls (transport, talk, send).
- Same hierarchy as other poses — do not hide features that exist when
  fully open.

**Personal OS:** orb + last assistant reply on the upper leaf; giant talk
button, status text, and type-instead on the lower leaf. This is the
hands-free / kitchen-counter voice pose.

### 6. Split View and other apps sharing the inner display

The inner display can run two apps. Your scene can be a narrow regular or
even compact slice. Controls move to the **outer** edge of whichever side
your app occupies (left app → left edge, right app → right edge). Safe area
matters on **both** edges because the neighbor’s chrome sits on the other
side.

**Personal OS:** the compact closed layout must remain usable at Split View
widths. Dual-pane is optional; the talk button must never require the full
7.6-inch canvas.

### 7. StandBy / poster-like rest

Named in marketing, thinly documented for third-party apps as of this
research. Treat as: glanceable, low interaction, possibly outer-display
facing in a tent. Do not block a future “listening / last reply” lock-style
surface, but do not invent a third information architecture for it yet.

### 8. Camera and capture accessory

Not the first Personal OS milestone, but the SDK has a dual-display capture
story: `CameraCaptureAccessory` / `UISceneAccessory.cameraCapture` can put
content on the **outer** display while the inner display runs the session.
Front cameras are **occlusion reserved regions**. The inner camera’s region
is active only while that camera is on.

## SDK compatibility tiers

| Built with | Closed (outer) | Open (inner) |
| --- | --- | --- |
| Pre–iOS 27 SDK | Uses space to the left of status bar and camera | Familiar letterboxed size |
| iOS 27 SDK | (cover behavior as documented for that SDK) | Extends to the left of the status-bar area |
| iOS 27.1 SDK | Edge-to-edge; standard nav/toolbar items can lay out vertically under the status bar | Full edge-to-edge; arrangement, reserved-region, and hinge APIs |

Notes:

- Scene lifecycle (`UIScene`) is required for iOS 27 SDKs.
- `UIRequiresFullScreen` still resizes when the phone opens or closes.
- NativePHP currently ships its own embedded PHP runtime and UI shell.
  Apple’s 27.1 layout APIs apply to the **native** chrome NativePHP
  generates (WKWebView / SuperNative), not automatically to our Blade.
  We still have to express compact vs regular in CSS.

## Developer APIs (iOS 27.1)

Symbols as named in Apple talks and follow-on writeups of the 27.1 docs.
Confirm against Xcode 27.1 headers before shipping.

| Job | SwiftUI | UIKit |
| --- | --- | --- |
| Two-pane layout around the fold | `ArrangementView`, `arrangementViewStyle(.split \| .overlay)` | `UIArrangementViewController` |
| Split sizing | `splitArrangementLayoutRatio`, `splitArrangementFixedLayoutSize` | `UISplitArrangement` |
| Hinge / camera geometry | `GeometryProxy.reservedRegions(kind:options:)` | `UIView.reservedRegions(kind:options:)` |
| Hinge angle / status | `onHingeChange` → `DeviceHinge` (`.closed`, `.partiallyOpen`, `.fullyOpen`) | `UIHingeInteraction` / `UIHinge` (adds `.unknown`) |
| Vertical bar opt-out | `toolbarVerticalBehavior(.disabled)` | `preferredVerticalBarBehavior` |
| Which edge the bar is on | `toolbarVerticalEdge` | `verticalBarEdge` trait |
| Item allowed on vertical bar | `axisBehavior` | `UIBarButtonItem.axisBehavior` |
| New window | existing window APIs | `UIWindowScene.ActivationAction` |
| Capture on the other display | `CameraCaptureAccessory` + `sceneAccessory` | `UISceneAccessory.cameraCapture` |

**Split vs overlay**

- Split = two views that must both stay visible (list + detail, transcript +
  player). Stacks (`HStack` / `VStack`) become splits.
- Overlay = foreground / background (controls over content). `ZStack` becomes
  overlay. When folded, overlay **separates** across the fold; primary goes
  trailing or bottom.

Do not nest an arrangement inside `NavigationSplitView`, `List`, or
`ScrollView`. Keep navigation containers **around** the arrangement.

**Reserved regions**

- Kind `.division` — the fold. Active when partially open; inactive and
  zero-width when flat.
- Kind `.occlusion` — cameras. Outer always; inner only while capturing.
- Query with `.includeInactive` when a high-level choice (even column count)
  should not wait for the hinge to move.
- Do not cache frames; re-read on geometry change.

**Vertical bars**

Only system bars from `NavigationStack` / `NavigationSplitView` / `TabView`
or `UINavigationController` / `UITabBarController` participate. Hand-built
`UIToolbar` / `UITabBar` instances do not. Icon-only items go vertical;
text-only items stay horizontal and overflow. Top of the vertical strip:
Back / Close, then the prominent action (Done). Frequent actions and badged
items overflow last.

A voice player or calculator-like screen may disable vertical bars.

## What this means for Personal OS

We are not a UIKit app. NativePHP hosts Laravel / Livewire / Flux. Apple
will not automatically turn Flux toolbars into Duo vertical bars. We should
still **behave** as if we were following HIG, using CSS as the arrangement
layer.

### App states to encode in the product

| State | Scene | Voice UI |
| --- | --- | --- |
| `cover` | compact width, short viewport | Single column; talk button dominant; accordion collapsed |
| `inner-portrait` | regular width, tall | Optional two-row: conversation then composer |
| `inner-landscape` | regular width, wide | Two pane: composer \| conversation (current desktop CSS) |
| `book` | two side-by-side regions, fold gutter | Conversation \| talk; gutter empty |
| `table` | two stacked regions, fold gutter | Transcript above, controls below |
| `split-narrow` | compact again | Same as `cover` |
| `listening` / `thinking` / `speaking` | orthogonal to pose | Status + talk-button variant only; do not change architecture |

Pose and conversation phase are independent. Folding mid-turn must not
reset the agent conversation.

### CSS / Livewire tactics (do this without the 27.1 SDK)

1. Keep using `env(safe-area-inset-*)` on all four edges. Duo Split View
   and vertical bars make **left and right** as important as top/bottom.
2. Drive layout with **container queries** on the voice shell (`@container`
   min-width / min-aspect-ratio), not `window.innerWidth` and not a
   hardcoded “iPhone Duo” breakpoint.
3. Treat a center band as reserved when the viewport is inner-sized and
   the aspect ratio is roughly square-ish (partial fold). CSS cannot see
   hinge angle; a conservative gutter (`flex` / `grid` with a middle track
   that stays empty) is safer than guessing degrees.
4. Keep the talk control as a large circular Flux primary button. In `table`
   it belongs in the near region; in `book` it belongs on one leaf, not
   across the fold.
5. Conversation transcript is scrolling content — it may cross the fold.
   Bubbles and the Send control may not.
6. Do not build a second “open phone” information architecture. Opening the
   device should reveal **more of the same conversation**, not a different
   app.
7. When NativePHP grows hinge or display APIs, map them onto the same state
   names above. Until then, size-class CSS is the contract.

### NativePHP caveats

- iOS builds still require a Mac, Xcode, and `native:install` /
  `native:run`. Duo simulator support depends on Apple’s 27.1 toolchain
  **and** NativePHP picking it up.
- SuperNative / webview will apply Apple safe areas; it will not implement
  `ArrangementView` for Blade. We own the two-pane and gutter behavior.
- Microphone, speech, and camera permissions remain Info.plist concerns
  (`NSMicrophoneUsageDescription` is already set). Inner-camera occlusion
  only matters if we add video later.
- Test closed, open, Split View widths, and a squarish inner aspect in the
  browser before hardware exists. The Flux dual-pane we already have is the
  inner-landscape sketch; the compact column is the cover sketch.

## Practical audit for this repo

When we next touch `resources/views/livewire/voice.blade.php` and
`resources/css/app.css`:

- [ ] Compact column remains usable at ~320–400px wide (cover + Split View)
- [ ] Regular + landscape uses two panes without stretching a phone column
- [ ] Talk button stays ≥ ~44pt / ~80px visual target in every state
- [ ] No interactive control is centered on a 50% vertical or horizontal
      split (hinge gutter)
- [ ] Transcript can scroll through the middle; composer cannot
- [ ] Safe-area padding on trailing **and** leading edges
- [ ] Opening/closing does not require a new conversation
- [ ] Type-instead accordion does not cover the talk button in table pose
- [ ] Re-check NativePHP + Xcode 27.1 simulator when that beta is installed

## Sources

Primary (Apple):

- [developer.apple.com/iphone-duo](https://developer.apple.com/iphone-duo/)
- Apple Developer News, 9 September 2026, *Get ready for iPhone Duo*
- Tech Talks listed above (English subtitle tracks quoted widely in
  contemporaneous notes)
- Human Interface Guidelines: *Designing for iPhone Duo* (published 9
  September 2026)
- *Preparing your app for iPhone Duo* (Apple technology overview; written
  form was still landing with Xcode 27.1)
- WWDC25 *Make your UIKit app more flexible* and WWDC26 *Modernize your
  UIKit app* (resizability / scenes; not Duo-specific)

Secondary notes used to cross-check API names and timelines (not a
substitute for headers):

- [sarunw.com — iPhone Duo design principles](https://sarunw.com/posts/design-for-iphone-duo/)
- [Blake Crosley — Designing for iPhone Duo](https://blakecrosley.com/blog/designing-for-iphone-duo)
- [Blake Crosley — iPhone Duo for developers](https://blakecrosley.com/blog/iphone-duo-for-developers)
- [MacObserver — six new APIs](https://www.macobserver.com/news/iphone-duo-new-apis-developers-have-not-used-before/)
- [MacObserver — outer display, hinge, poses](https://www.macobserver.com/news/iphone-duo-design-rules-outer-display-hinge-five-poses/)
