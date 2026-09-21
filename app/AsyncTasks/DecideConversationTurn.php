<?php

namespace App\AsyncTasks;

use App\Services\ConversationTurn;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Native\Mobile\AsyncTask;

class DecideConversationTurn extends AsyncTask
{
    /**
     * @param  array<string, string>  $choices
     * @return array<string, mixed>
     */
    public function handle(string $conversationId, array $choices): array
    {
        $decisions = Decisions::from(
            collect($choices)->mapWithKeys(
                fn (string $choice, string $id): array => [
                    $id => $choice === 'approve'
                        ? Decision::approve()
                        : Decision::reject('The user did not approve this action.'),
                ],
            )->all(),
        );

        return app(ConversationTurn::class)->decide(
            conversationId: $conversationId,
            decisions: $decisions,
        );
    }
}
