import { Events, Microphone, Off, On } from '#nativephp';

const root = document.querySelector('[data-voice-agent]');

if (root) {
    const button = document.querySelector('#talk-button');
    const status = document.querySelector('#voice-status');
    const conversation = document.querySelector('#conversation');
    const emptyState = document.querySelector('#empty-state');
    const audio = document.querySelector('#assistant-audio');
    const textForm = document.querySelector('#text-form');
    const messageInput = document.querySelector('#message-input');
    const audioInput = document.querySelector('#audio-input');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    let conversationId = root.dataset.conversationId || null;
    let state = 'idle';
    let usingNativeRecorder = false;
    let mediaRecorder = null;
    let chunks = [];

    const setState = (next, label) => {
        state = next;
        root.dataset.state = next;
        status.textContent = label;
        button.disabled = next === 'thinking';
        button.setAttribute('aria-label', next === 'recording' ? 'Stop recording' : 'Start recording');
    };

    const addMessage = (role, text) => {
        emptyState?.remove();
        const article = document.createElement('article');
        article.className = `message message--${role}`;

        const speaker = document.createElement('span');
        speaker.textContent = role === 'user' ? 'You' : 'Assistant';
        const content = document.createElement('p');
        content.textContent = text;

        article.append(speaker, content);
        conversation.append(article);
        article.scrollIntoView({ behavior: 'smooth', block: 'end' });
    };

    const speak = async (url, text) => {
        if (url) {
            audio.src = url;
            try {
                await audio.play();
                return;
            } catch {
                // Fall back to the platform speech synthesizer.
            }
        }

        if ('speechSynthesis' in window) {
            speechSynthesis.cancel();
            speechSynthesis.speak(new SpeechSynthesisUtterance(text));
        }
    };

    const sendTurn = async ({ blob, path, mimeType, message } = {}) => {
        const formData = new FormData();

        if (blob) formData.append('audio', blob, 'recording.webm');
        if (path) formData.append('audio_path', path);
        if (mimeType) formData.append('mime_type', mimeType);
        if (message) formData.append('message', message);
        if (conversationId) formData.append('conversation_id', conversationId);
        formData.append('speak', '1');

        setState('thinking', 'Thinking…');

        try {
            const response = await fetch(root.dataset.turnUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: formData,
            });
            const payload = await response.json();

            if (!response.ok) {
                const validationMessage = Object.values(payload.errors || {})[0]?.[0];
                throw new Error(validationMessage || payload.message || 'The assistant could not respond.');
            }

            conversationId = payload.conversation_id;
            root.dataset.conversationId = conversationId;
            addMessage('user', payload.transcript);
            addMessage('assistant', payload.response);
            setState('idle', 'Tap to speak');
            await speak(payload.audio_url, payload.response);
        } catch (error) {
            setState('error', error.message || 'Something went wrong. Tap to retry.');
        }
    };

    const stopRecording = async () => {
        if (usingNativeRecorder) {
            await Microphone.stop();
        } else if (mediaRecorder?.state === 'recording') {
            mediaRecorder.stop();
        }

        setState('thinking', 'Finishing recording…');
    };

    const startBrowserRecording = async () => {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        chunks = [];
        mediaRecorder = new MediaRecorder(stream);
        mediaRecorder.addEventListener('dataavailable', (event) => chunks.push(event.data));
        mediaRecorder.addEventListener('stop', () => {
            stream.getTracks().forEach((track) => track.stop());
            const blob = new Blob(chunks, { type: mediaRecorder.mimeType || 'audio/webm' });
            sendTurn({ blob });
        }, { once: true });
        mediaRecorder.start();
        usingNativeRecorder = false;
    };

    const startRecording = async () => {
        try {
            await Microphone.record();
            usingNativeRecorder = true;
        } catch {
            if (!navigator.mediaDevices?.getUserMedia) {
                throw new Error('Microphone recording is unavailable. Use “Type instead”.');
            }

            await startBrowserRecording();
        }

        setState('recording', 'Listening… tap when finished');
    };

    const handleNativeRecording = (payload) => {
        if (!payload?.path) {
            setState('error', 'The recording did not include a readable file.');
            return;
        }

        sendTurn({ path: payload.path, mimeType: payload.mimeType });
    };

    const handleNativeCancellation = () => setState('idle', 'Recording cancelled');

    On(Events.Microphone.Recorded, handleNativeRecording);
    On(Events.Microphone.Cancelled, handleNativeCancellation);

    button.addEventListener('click', async () => {
        try {
            if (state === 'recording') {
                await stopRecording();
            } else if (state !== 'thinking') {
                await startRecording();
            }
        } catch (error) {
            setState('error', error.message || 'Microphone access failed.');
        }
    });

    textForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const message = messageInput.value.trim();
        if (!message) return;
        messageInput.value = '';
        sendTurn({ message });
    });

    audioInput.addEventListener('change', () => {
        const [file] = audioInput.files;
        if (file) sendTurn({ blob: file });
        audioInput.value = '';
    });

    window.addEventListener('beforeunload', () => {
        Off(Events.Microphone.Recorded, handleNativeRecording);
        Off(Events.Microphone.Cancelled, handleNativeCancellation);
    });
}
