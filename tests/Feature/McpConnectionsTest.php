<?php

namespace Tests\Feature;

use App\Ai\Agents\PersonalAssistant;
use App\Ai\Tools\ApprovableMcpTool;
use App\Livewire\Connections;
use App\Livewire\Voice;
use App\Models\McpServer;
use App\Services\PersonalUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Tools\Request;
use Laravel\Mcp\Client\Primitives\Tool as McpTool;
use Livewire\Livewire;
use Tests\TestCase;

class McpConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_preset_can_be_installed_from_the_connections_screen(): void
    {
        $this->get('/connections')
            ->assertOk()
            ->assertSeeLivewire(Connections::class)
            ->assertSee('Linear')
            ->assertSee('Backbone')
            ->assertSee('Slack')
            ->assertSee('Notion');

        Livewire::test(Connections::class)
            ->call('installPreset', 'linear')
            ->assertSee('Needs credentials');

        $this->assertDatabaseHas('mcp_servers', [
            'slug' => 'linear',
            'url' => 'https://mcp.linear.app/mcp',
            'auth_type' => 'oauth',
            'approval_mode' => 'writes',
        ]);
    }

    public function test_connection_secrets_are_encrypted_at_rest(): void
    {
        $user = app(PersonalUser::class)->get();
        $server = McpServer::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'Private',
            'slug' => 'private',
            'url' => 'https://mcp.example.com/mcp',
            'auth_type' => 'bearer',
            'bearer_token' => 'plain-secret-token',
        ]);

        $raw = $server->getConnection()->table('mcp_servers')->where('id', $server->id)->value('bearer_token');

        $this->assertNotSame('plain-secret-token', $raw);
        $this->assertSame('plain-secret-token', $server->fresh()->bearer_token);
    }

    public function test_write_and_unannotated_tools_require_approval_by_default(): void
    {
        $write = new ApprovableMcpTool(
            $this->tool('create_issue'),
            'linear',
            'Linear',
            'writes',
        );
        $read = new ApprovableMcpTool(
            $this->tool('get_issue', ['readOnlyHint' => true]),
            'linear',
            'Linear',
            'writes',
        );

        $this->assertNotNull($write->shouldRequestApproval(new Request(['title' => 'Ship it'])));
        $this->assertNull($read->shouldRequestApproval(new Request(['id' => 'ENG-1'])));
        $this->assertStringStartsWith('mcp_linear_', $write->name());
    }

    public function test_a_pending_tool_call_is_rendered_for_human_approval(): void
    {
        PersonalAssistant::fake([
            AgentResponse::fakeWithPendingApprovals([
                new PendingApproval(
                    id: 'call_123',
                    tool: 'mcp_linear_create_issue',
                    arguments: ['title' => 'Ship the mobile app'],
                    reason: 'This may change data in Linear.',
                ),
            ]),
        ]);

        Livewire::test(Voice::class)
            ->set('draft', 'Create the issue')
            ->call('sendText')
            ->assertSet('state', 'awaiting_approval')
            ->assertSet('pendingApprovals.0.id', 'call_123')
            ->assertSee('Review requested actions')
            ->assertSee('Ship the mobile app');
    }

    private function tool(string $name, array $annotations = []): McpTool
    {
        return new McpTool(
            client: null,
            name: $name,
            title: null,
            description: null,
            inputSchema: ['type' => 'object', 'properties' => []],
            outputSchema: null,
            annotations: $annotations,
            meta: null,
        );
    }
}
