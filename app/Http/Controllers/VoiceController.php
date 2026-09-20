<?php

namespace App\Http\Controllers;

use App\Ai\Agents\PersonalAssistant;
use App\Services\PersonalUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Ai\Audio;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Transcription;
use Throwable;

class VoiceController extends Controller
{
    public function __construct(private readonly PersonalUser $personalUser) {}

    public function index(): View
    {
        $user = $this->personalUser->get();
        $conversation = Conversation::query()
            ->where('participant_type', Conversation::participantType($user))
            ->where('participant_id', $user->getKey())
            ->latest('updated_at')
            ->first();

        return view('voice', [
            'conversation' => $conversation,
            'messages' => $conversation?->messages()->oldest()->get() ?? collect(),
        ]);
    }

    public function turn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audio' => ['nullable', 'file', 'mimetypes:audio/m4a,audio/mp4,audio/mpeg,audio/wav,audio/x-wav,audio/webm', 'max:25600'],
            'audio_path' => ['nullable', 'string', 'max:4096'],
            'mime_type' => ['nullable', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:12000'],
            'conversation_id' => ['nullable', 'uuid'],
            'speak' => ['nullable', 'boolean'],
        ]);

        $transcript = trim((string) ($validated['message'] ?? ''));

        if ($transcript === '') {
            $transcript = $this->transcribe($request, $validated);
        }

        if ($transcript === '') {
            throw ValidationException::withMessages([
                'message' => 'Record audio or enter a message before sending.',
            ]);
        }

        $user = $this->personalUser->get();
        $assistant = new PersonalAssistant;

        if ($conversationId = $validated['conversation_id'] ?? null) {
            $belongsToUser = Conversation::query()
                ->whereKey($conversationId)
                ->where('participant_type', Conversation::participantType($user))
                ->where('participant_id', $user->getKey())
                ->exists();

            abort_unless($belongsToUser, 404);
            $assistant->continue($conversationId, $user);
        } else {
            $assistant->forUser($user);
        }

        $response = $assistant->prompt($transcript);
        $audioUrl = null;

        if ($request->boolean('speak', true)) {
            $audioUrl = $this->synthesize((string) $response);
        }

        return response()->json([
            'conversation_id' => $response->conversationId,
            'transcript' => $transcript,
            'response' => (string) $response,
            'audio_url' => $audioUrl,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function transcribe(Request $request, array $validated): string
    {
        if ($request->hasFile('audio')) {
            return trim((string) Transcription::fromUpload($request->file('audio'))->generate());
        }

        if ($path = $validated['audio_path'] ?? null) {
            if (! is_file($path) || ! is_readable($path)) {
                throw ValidationException::withMessages([
                    'audio_path' => 'The native recording could not be read.',
                ]);
            }

            return trim((string) Transcription::fromPath(
                $path,
                $validated['mime_type'] ?? null,
            )->generate());
        }

        return '';
    }

    private function synthesize(string $text): ?string
    {
        try {
            $audio = Audio::of($text)
                ->voice(config('personal.voice'))
                ->instructions('Warm, natural, concise personal assistant delivery.')
                ->generate();

            $path = $audio->store('assistant-audio', 'mobile_public');

            return $path ? Storage::disk('mobile_public')->url($path) : null;
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Voice synthesis failed; returning text response.', [
                'exception' => $exception::class,
            ]);

            return null;
        }
    }
}
