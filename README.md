# Personal OS Mobile

A private, single-user voice assistant built with Laravel 13, Livewire, Flux UI,
the Laravel AI SDK, and NativePHP Mobile. Laravel and SQLite run on the phone;
recorded audio is sent to the configured AI provider for transcription, an
agent turn, and speech synthesis.

## What is included

- Livewire + Flux Pro UI, themed to the Personal OS dark mint palette
- Push-to-talk recording through NativePHP's microphone plugin
- Browser `MediaRecorder` fallback for local development
- Laravel AI transcription and text-to-speech
- Persistent `agent_conversations` and `agent_conversation_messages`
- A concise personal assistant with a current-time tool
- Streamed agent replies through Livewire
- Encrypted MCP connections for Linear, Backbone, Slack, Notion, and custom servers
- Human approval before mutating or unannotated MCP tool calls
- A responsive, safe-area-aware phone UI with a landscape/dual-pane layout
- [iPhone Duo layout research](docs/iphone-duo-research.md) for cover, inner,
  book, table, and Split View states

This first slice is turn-based voice, not a full-duplex realtime audio stream.
That keeps conversation persistence and tool execution provider-independent
while leaving room for a dedicated realtime transport later.

## Local setup

Requirements: PHP 8.4, Composer, Node.js 22+, and an OpenAI API key.

```bash
cp .env.example .env
composer config http-basic.composer.fluxui.dev "$FLUX_USERNAME" "$FLUX_LICENSE_KEY"
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan storage:link
npm install
npm run build
php artisan serve
```

Flux Pro is authenticated via a local `auth.json` file (already gitignored).
Use your Flux account email as `FLUX_USERNAME` and license key as
`FLUX_LICENSE_KEY`. Never commit those values.

Set `OPENAI_API_KEY` in `.env`. Open `http://localhost:8000`; the browser can
record audio after microphone permission is granted.

## MCP connections and approvals

Open **Connections** from the voice screen. Add one of the built-in presets or
register a custom Streamable HTTP MCP endpoint. Bearer tokens, OAuth tokens,
refresh tokens, and OAuth client credentials use Laravel's encrypted model
casts before they are written to SQLite.

- Linear uses `https://mcp.linear.app/mcp` and supports dynamic OAuth client
  registration.
- Notion uses `https://mcp.notion.com/mcp` and requires interactive OAuth.
- Slack uses `https://mcp.slack.com/mcp`. Create a Slack app, add this app's
  displayed OAuth callback URL to its redirect URLs, then save the Slack client
  ID and secret before authorizing.
- Backbone uses `https://backbone.govai.com/mcp`. Its public authentication
  contract is not discoverable, so the preset starts in bearer-token mode.

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

## Run on iPhone

iOS builds require an Apple silicon Mac with macOS 15.6+, Xcode 26+, CocoaPods, and a
physical device in Developer Mode (or an iOS Simulator). On that Mac:

```bash
composer install
npm install
npm run build
php artisan native:install ios
php artisan native:run ios
```

Choose your simulator or connected iPhone when prompted. NativePHP generates
the ephemeral `nativephp/ios` project during installation; do not hand-edit it.
The microphone purpose string is configured in `config/nativephp.php`.

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
