<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#07110e">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main
        class="shell"
        data-voice-agent
        data-turn-url="{{ route('voice.turn') }}"
        data-conversation-id="{{ $conversation?->id }}"
    >
        <header class="topbar">
            <div class="brand-mark" aria-hidden="true"><span></span></div>
            <div>
                <p class="eyebrow">Personal OS</p>
                <h1>Voice</h1>
            </div>
            <div class="privacy"><span></span> Private</div>
        </header>

        <section class="conversation" id="conversation" aria-live="polite" aria-label="Conversation">
            @forelse ($messages as $message)
                @if (in_array($message->role, ['user', 'assistant'], true))
                    <article class="message message--{{ $message->role }}">
                        <span>{{ $message->role === 'user' ? 'You' : 'Assistant' }}</span>
                        <p>{{ $message->content }}</p>
                    </article>
                @endif
            @empty
                <div class="empty-state" id="empty-state">
                    <div class="orb orb--idle" aria-hidden="true">
                        <i></i><i></i><i></i><i></i><i></i>
                    </div>
                    <p>Ready when you are.</p>
                    <span>Tap once to speak. Tap again when you’re done.</span>
                </div>
            @endforelse
        </section>

        <section class="composer" aria-label="Voice controls">
            <p class="status" id="voice-status">Tap to speak</p>
            <button class="talk-button" id="talk-button" type="button" aria-label="Start recording">
                <span class="talk-rings" aria-hidden="true"></span>
                <svg viewBox="0 0 24 24" role="img" aria-hidden="true">
                    <path d="M12 15.25a3.5 3.5 0 0 0 3.5-3.5V6.5a3.5 3.5 0 1 0-7 0v5.25a3.5 3.5 0 0 0 3.5 3.5Z"/>
                    <path d="M5.75 11.25a6.25 6.25 0 0 0 12.5 0M12 17.5v3M9 20.5h6"/>
                </svg>
            </button>

            <details class="text-fallback">
                <summary>Type instead</summary>
                <form id="text-form">
                    <textarea id="message-input" rows="2" maxlength="12000" placeholder="What’s on your mind?"></textarea>
                    <button type="submit">Send</button>
                </form>
                <label class="upload-fallback">
                    Test an audio file
                    <input id="audio-input" type="file" accept="audio/*">
                </label>
            </details>
        </section>
    </main>

    <audio id="assistant-audio" preload="auto"></audio>
</body>
</html>
