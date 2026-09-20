<?php

namespace App\Ai\Tools;

use Illuminate\Support\Str;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\McpTool;
use Laravel\Mcp\Client\Primitives\Tool;

class ApprovableMcpTool extends McpTool implements Approvable
{
    use InteractsWithApprovals;

    public function __construct(
        Tool $tool,
        private readonly string $serverSlug,
        string $serverName,
        string $approvalMode,
    ) {
        parent::__construct($tool);

        $readOnly = ($tool->annotations['readOnlyHint'] ?? false) === true;

        match ($approvalMode) {
            'never' => $this->withoutApproval(),
            'always' => $this->requireApproval("Allow {$serverName} to run {$tool->name}?"),
            default => $readOnly
                ? $this->withoutApproval()
                : $this->requireApproval("This may change data in {$serverName}."),
        };
    }

    public function name(): string
    {
        $server = Str::of($this->serverSlug)->snake()->replaceMatches('/[^a-z0-9_]/', '')->limit(16, '');
        $tool = Str::of($this->tool->name)->snake()->replaceMatches('/[^a-z0-9_]/', '');
        $name = "mcp_{$server}_{$tool}";

        return strlen($name) <= 64
            ? $name
            : substr($name, 0, 55).'_'.substr(hash('xxh3', $name), 0, 8);
    }
}
