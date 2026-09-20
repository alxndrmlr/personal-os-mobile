document.addEventListener('alpine:init', () => {
    Alpine.data('voiceSurface', () => ({
        recorder: null,
        chunks: [],
        stream: null,

        async startBrowserRecording() {
            try {
                if (! navigator.mediaDevices?.getUserMedia) {
                    throw new Error('Microphone recording is unavailable. Use “Type instead”.');
                }

                this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.chunks = [];
                this.recorder = new MediaRecorder(this.stream);
                this.recorder.addEventListener('dataavailable', (event) => this.chunks.push(event.data));
                this.recorder.addEventListener('stop', () => this.finishBrowserRecording(), { once: true });
                this.recorder.start();
                this.$wire.listen();
            } catch (error) {
                this.$wire.recordingFailed(error.message || 'Microphone access failed.');
            }
        },

        stopBrowserRecording() {
            if (this.recorder?.state === 'recording') {
                this.recorder.stop();
            }
        },

        finishBrowserRecording() {
            this.stream?.getTracks().forEach((track) => track.stop());
            const blob = new Blob(this.chunks, { type: this.recorder?.mimeType || 'audio/webm' });
            const file = new File([blob], 'recording.webm', { type: blob.type || 'audio/webm' });

            this.$wire.upload('recording', file, () => {
                this.$wire.processRecording();
            }, () => {
                this.$wire.recordingFailed('The recording could not be uploaded.');
            });
        },

        async speak(url, text) {
            if (url) {
                this.$refs.player.src = url;

                try {
                    await this.$refs.player.play();
                    return;
                } catch {
                    // Fall back to the platform speech synthesizer.
                }
            }

            if ('speechSynthesis' in window && text) {
                speechSynthesis.cancel();
                speechSynthesis.speak(new SpeechSynthesisUtterance(text));
            }
        },
    }));
});
