<?php

namespace App\Services;

use App\Models\AgentActivity;
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AgentActivityTracker
{
    public function start(string $prompt, ?string $conversationId = null): AgentActivity
    {
        $title = Str::of($prompt)
            ->squish()
            ->limit(80)
            ->value();

        return AgentActivity::query()->create([
            'conversation_id' => $conversationId,
            'title' => $title ?: 'Voice request',
            'status' => AgentActivity::STATUS_WORKING,
            'detail' => 'Working…',
            'started_at' => now(),
        ]);
    }

    public function resume(string $conversationId): AgentActivity
    {
        $activity = AgentActivity::query()
            ->where('conversation_id', $conversationId)
            ->where('status', AgentActivity::STATUS_NEEDS_INPUT)
            ->latest('updated_at')
            ->first();

        if (! $activity) {
            return $this->start('Continue approved action', $conversationId);
        }

        $activity->forceFill([
            'status' => AgentActivity::STATUS_WORKING,
            'detail' => 'Continuing…',
            'finished_at' => null,
        ])->save();

        return $activity;
    }

    /**
     * @param  array{conversation_id: string|null, response: string, approvals: array<int, mixed>}  $result
     */
    public function finish(AgentActivity $activity, array $result): void
    {
        if (filled($result['conversation_id'])) {
            $activity->conversation_id = $result['conversation_id'];
        }

        if ($result['approvals'] !== []) {
            $activity->forceFill([
                'status' => AgentActivity::STATUS_NEEDS_INPUT,
                'detail' => count($result['approvals']) === 1
                    ? 'One action needs your approval.'
                    : count($result['approvals']).' actions need your approval.',
            ])->save();

            $this->notify(
                $activity,
                'Your agent needs input',
                'Open Personal OS to review a requested action.',
                1,
            );

            return;
        }

        $activity->forceFill([
            'status' => AgentActivity::STATUS_COMPLETED,
            'detail' => filled($result['response']) ? Str::limit($result['response'], 160) : 'Finished.',
            'finished_at' => now(),
        ])->save();

        $this->notify(
            $activity,
            'Your agent is done',
            'Open Personal OS to review the result.',
            0,
        );
    }

    public function fail(AgentActivity $activity): void
    {
        $activity->forceFill([
            'status' => AgentActivity::STATUS_FAILED,
            'detail' => 'The agent could not finish this request.',
            'finished_at' => now(),
        ])->save();

        $this->notify(
            $activity,
            'Your agent stopped',
            'Open Personal OS to review the error.',
            0,
        );
    }

    private function notify(AgentActivity $activity, string $title, string $body, int $badge): void
    {
        try {
            LocalNotifications::schedule([
                'id' => "agent-{$activity->id}-{$activity->status}",
                'title' => $title,
                'body' => $body,
                'delay' => 1,
                'badge' => $badge,
                'data' => [
                    'url' => '/',
                    'activity_id' => $activity->id,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::warning('An agent status notification could not be scheduled.', [
                'activity' => $activity->id,
                'exception' => $exception::class,
            ]);
        }
    }
}
