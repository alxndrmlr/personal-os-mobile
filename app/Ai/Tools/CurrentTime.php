<?php

namespace App\Ai\Tools;

use DateTimeZone;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CurrentTime implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Return the current local date and time for a requested IANA timezone.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'timezone' => ['nullable', 'timezone:all'],
        ]);

        $timezone = new DateTimeZone($validated['timezone'] ?? config('app.timezone'));
        $now = now($timezone);

        return json_encode([
            'iso_8601' => $now->toIso8601String(),
            'human' => $now->format('l, F j, Y \a\t g:i A T'),
            'timezone' => $timezone->getName(),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'timezone' => $schema->string()
                ->description('Optional IANA timezone, such as America/Los_Angeles.'),
        ];
    }
}
