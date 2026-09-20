# Personal OS Mobile

A private, single-user voice assistant built with Laravel 13, Livewire, Flux UI,
the Laravel AI SDK, and NativePHP Mobile. Laravel and SQLite run on the phone;
recorded audio is sent to the configured AI provider for transcription, an
agent turn, and speech synthesis.

## What is included

- Livewire + Flux Pro UI using the default Flux design system
- Push-to-talk recording through NativePHP's microphone plugin
- Browser `MediaRecorder` fallback for local development
- Laravel AI transcription and text-to-speech
- Persistent `agent_conversations` and `agent_conversation_messages`
- A concise personal assistant with a current-time tool
- Streamed agent replies through Livewire
- An encrypted MCP server registry managed entirely through the app
- Human approval before mutating or unannotated MCP tool calls
- Persistent agent activity with on-device completion and approval notifications
- A responsive, safe-area-aware phone UI with a landscape/dual-pane layout
- [iPhone Duo layout research](docs/iphone-duo-research.md) for cover, inner,
  book, table, and Split View states
- [Agent notifications and Live Activity architecture](docs/agent-activity-and-notifications.md)

This first slice is turn-based voice, not a full-duplex realtime audio stream.
That keeps conversation persistence and tool execution provider-independent
while leaving room for a dedicated realtime transport later.

## First local setup

Requirements:

- PHP 8.3+ with SQLite
- Composer
- Node.js 22+ and npm
- your Flux account email and Flux Pro license key
- an OpenAI API key

Clone the repository and run the setup script:

```bash
git clone https://github.com/alxndrmlr/personal-os-mobile.git
cd personal-os-mobile
./bin/setup-local
```

The script securely prompts for the Flux and OpenAI credentials, creates
`.env`, installs PHP and JavaScript dependencies, generates `APP_KEY`, prepares
SQLite, runs migrations, builds assets, links storage, and validates the
NativePHP plugins.

For a non-interactive setup, pass credentials as environment variables:

```bash
FLUX_USERNAME="you@example.com" \
FLUX_LICENSE_KEY="..." \
OPENAI_API_KEY="..." \
./bin/setup-local
```

Flux credentials are written to the gitignored Composer `auth.json`; the OpenAI
key is written to the gitignored `.env`. Do not put either value in a committed
file or a shell script.

## Start development

### Browser

```bash
./bin/dev web
```

This runs Laravel, the on-device-style database queue, logs, and Vite together.
Open `http://localhost:8000`. Browser recording uses `MediaRecorder`, so this is
the quickest way to inspect the Livewire and Flux UI.

### NativePHP Jump

```bash
./bin/dev jump
```

Install the free NativePHP Jump app on the phone and scan the displayed QR code.
Jump is useful for a quick device preview without Xcode. It includes first-party
plugins such as the microphone, but not this project's third-party local
notifications plugin. Use a compiled iOS build to test system notifications.

### Compiled iOS app

This requires an Apple silicon Mac, Xcode, CocoaPods, and either an iPhone in
Developer Mode or an iOS Simulator. Set a unique bundle ID in `.env` first:

```dotenv
NATIVEPHP_APP_ID=com.yourname.personalos
```

Then run:

```bash
./bin/dev ios
```

On its first run this generates the ephemeral iOS project, then opens the
device/simulator selection and starts NativePHP with hot reload and Vite. The
microphone and local-notification plugins are already registered in
`app/Providers/NativeServiceProvider.php`; no additional registration command
is needed.

If a native plugin or its Swift/Kotlin code changes, regenerate the native
project before running again:

```bash
./bin/dev ios:install
./bin/dev ios
```

## MCP connections and approvals

Open **Connections** from the voice screen and enter a name, Streamable HTTP
MCP URL, authentication method, and approval policy. There is no built-in
provider catalog. Bearer tokens, OAuth tokens, refresh tokens, and OAuth client
credentials use Laravel's encrypted model casts before they are written to
SQLite.

OAuth servers that support dynamic client registration need only their URL.
Other servers may require a client ID, secret, or scopes after the connection
is added. The connection detail view shows the exact callback URL to register
with that provider.

OAuth callbacks are generated from `APP_URL`. It must be the URL that the
provider can redirect back to. Local web development can use a trusted HTTPS
tunnel; the NativePHP build must preserve a callback URL that returns to its
embedded Laravel server or a future native deep-link bridge.

The default **Writes** policy trusts only tools whose MCP annotations explicitly
set `readOnlyHint: true`; write tools and tools without that annotation pause
the Laravel AI conversation and show their exact arguments for approval. Use
**Always** for an untrusted server. **Never** should only be used for a server
you fully control.

Run the checks with:

```bash
composer test
npm run build
vendor/bin/pint --test
```

## iPhone notes

Choose your simulator or connected iPhone when prompted. NativePHP generates
the ephemeral `nativephp/ios` project during installation; do not hand-edit it.
The microphone purpose string is configured in `config/nativephp.php`. Tap
**Enable system notifications** in the agent activity card once after
installing.

The Linux development environment can build and test Laravel and the web UI,
but Apple does not permit generating or compiling the iOS shell outside macOS.

NativePHP bundles the application's environment into the IPA. Using
`OPENAI_API_KEY` directly is reasonable for a private, personally installed
build, but the key can be extracted from the app. Route AI calls through a
server-side proxy before distributing the app to anyone else.

## Configuration

| Variable | Purpose |
| --- | --- |
| `OPENAI_API_KEY` | Default agent, transcription, and speech provider |
| `PERSONAL_USER_NAME` | Name for the on-device conversation participant |
| `PERSONAL_USER_EMAIL` | Stable identity for persisted conversations |
| `PERSONAL_ASSISTANT_VOICE` | Laravel AI speech voice |
| `NATIVEPHP_APP_ID` | Reverse-domain iOS bundle identifier |

No login flow is included by design. Anyone with access to the unlocked app has
access to its conversations, so device passcode/Face ID remains the security
boundary until app-level biometric locking is added.
