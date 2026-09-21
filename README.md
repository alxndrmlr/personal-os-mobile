# Personal OS Mobile

A private, single-user voice assistant built with Laravel 13, the Laravel AI
SDK, and NativePHP Mobile 4. The interface is SuperNative: EDGE Blade templates
render real SwiftUI on iOS and Jetpack Compose on Android. There is no
Livewire, Flux, JavaScript, Vite, or app web view.

## Included

- Native push-to-talk recording and synthesized-response playback
- Non-blocking AI turns through NativePHP async tasks
- Persistent Laravel AI conversations and messages
- Encrypted, app-managed MCP server connections
- Human approval for mutating or unannotated MCP tool calls
- Persistent agent activity and system completion/approval notifications
- Native tab/navigation chrome, forms, dark mode, and responsive EDGE layouts
- [iPhone Duo layout research](docs/iphone-duo-research.md)
- [Agent notification and Live Activity architecture](docs/agent-activity-and-notifications.md)

Voice interaction is turn-based rather than full-duplex realtime audio. Agent
responses currently appear when the complete async turn finishes; restoring
token-by-token streaming will require a native event transport.

## First local setup

Requirements:

- PHP 8.3+ with SQLite
- Composer
- an OpenAI API key

```bash
git clone https://github.com/alxndrmlr/personal-os-mobile.git
cd personal-os-mobile
./bin/setup-local
```

The script prompts securely for the OpenAI key, creates `.env`, installs PHP
dependencies, generates `APP_KEY`, prepares SQLite, runs migrations, links
storage, and validates the native components and plugins.

For non-interactive setup:

```bash
OPENAI_API_KEY="..." ./bin/setup-local
```

The key is written only to the gitignored `.env`.

## Run the app

### NativePHP Jump

```bash
./bin/dev jump
```

Install the free NativePHP Jump app and scan the QR code. Jump is useful for
iterating on the SuperNative screens without opening Xcode. A compiled app is
still required to verify every third-party plugin, including local
notifications and media playback.

### Compiled iOS app

An iOS build requires an Apple silicon Mac, Xcode, CocoaPods, and an iPhone in
Developer Mode or an iOS Simulator. Set a unique bundle ID first:

```dotenv
NATIVEPHP_APP_ID=com.yourname.personalos
NATIVEPHP_DEEPLINK_SCHEME=personalos
```

Then:

```bash
./bin/dev ios
```

The first run generates the ephemeral iOS project and then launches the app
with native hot reload. After changing a native plugin or its Swift/Kotlin
code, regenerate the project:

```bash
./bin/dev ios:install
./bin/dev ios
```

The registered native plugins are:

- `nativephp/mobile-ui`
- `nativephp/mobile-microphone`
- `nativephp/mobile-media-player`
- `ikromjon/nativephp-mobile-local-notifications`

## MCP connections and approvals

Open the native **Connections** tab and enter a Streamable HTTP endpoint,
authentication method, and approval policy. No providers are preconfigured.
Bearer tokens, OAuth tokens, refresh tokens, and OAuth client credentials use
Laravel encrypted casts before being written to SQLite.

The default **Writes and unknown** policy trusts only tools whose MCP
annotations explicitly set `readOnlyHint: true`. Write tools and unannotated
tools pause the AI conversation and show their arguments for approval.
**Every tool call** is safest for an untrusted server. **Never** should only be
used for a server you fully control.

OAuth still needs a callback URL reachable by the provider. For a purely
on-device install, use bearer tokens where supported until the callback is
hosted on a small companion backend or NativePHP exposes deep-link query
parameters directly to a native screen.

## Checks

```bash
composer test
php artisan native:validate
php artisan native:plugin:validate
vendor/bin/pint --test
```

The Linux cloud environment can run Laravel and native component tests, but
Apple only permits generating and compiling the iOS shell on macOS.

## Security

NativePHP bundles the application environment into the app. An OpenAI key in a
private, personally installed build can still be extracted by someone with
access to the app bundle. Route AI calls through a server-side proxy before
distributing the app.

No login flow is included by design. Anyone with access to the unlocked app has
access to its conversations, so the device passcode/Face ID is the current
security boundary.

## Configuration

| Variable | Purpose |
| --- | --- |
| `OPENAI_API_KEY` | Agent, transcription, and speech provider |
| `PERSONAL_USER_NAME` | On-device conversation participant |
| `PERSONAL_USER_EMAIL` | Stable identity for persisted conversations |
| `PERSONAL_ASSISTANT_VOICE` | Laravel AI speech voice |
| `NATIVEPHP_APP_ID` | Reverse-domain iOS bundle identifier |
| `NATIVEPHP_DEEPLINK_SCHEME` | Custom URL scheme for native callbacks |
