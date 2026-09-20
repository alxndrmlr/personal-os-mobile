<?php

namespace App\AsyncTasks;

use App\Services\ConversationTurn;
use Native\Mobile\AsyncTask;

class RunConversationTurn extends AsyncTask
{
    /**
     * @return array<string, mixed>
     */
    public function handle(
        ?string $message,
        ?string $audioPath,
        ?string $mimeType,
        ?string $conversationId,
    ): array {
        return app(ConversationTurn::class)->handle(
            message: $message,
            audioPath: $audioPath,
            mimeType: $mimeType,
            conversationId: $conversationId,
        );
    }
}
