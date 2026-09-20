<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AgentActivity extends Model
{
    use HasUuids;

    public const STATUS_WORKING = 'working';

    public const STATUS_NEEDS_INPUT = 'needs_input';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_WORKING,
            self::STATUS_NEEDS_INPUT,
        ], true);
    }
}
