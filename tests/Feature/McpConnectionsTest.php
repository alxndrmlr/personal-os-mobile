<?php

namespace Tests\Feature;

use App\Ai\Agents\PersonalAssistant;
use App\Ai\Tools\ApprovableMcpTool;
use App\Livewire\Connections;
use App\Livewire\Voice;
use App\Models\McpServer;
use App\Services\ConversationTurn;
use App\Services\PersonalUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Tools\Request;
use Laravel\Mcp\Client\Primitives\Tool as McpTool;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class McpConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_server_can_be_added_from_the_connections_screen(): void
    {
        $this->get('/connections')
            ->assertOk()
            ->assertSeeLivewire(Connections::class)
            ->assertSee('Add server')
            ->assertSee('Add an MCP URL above');

        Livewire::test(Connections::class)
            ->set('name', 'Work')
            ->set('url', 'https://mcp.example.com/mcp')
            ->set('authType', 'bearer')
            ->set('token', 'secret-token')
            ->call('addServer')
            ->assertSee('Work')
            ->assertSee('Connected');

        $this->assertDatabaseHas('mcp_servers', [
            'slug' => 'work',
            'url' => 'https://mcp.example.com/mcp',
            'auth_type' => 'bearer',
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
            'work',
            'Work',
            'writes',
        );
        $read = new ApprovableMcpTool(
            $this->tool('get_issue', ['readOnlyHint' => true]),
            'work',
            'Work',
            'writes',
        );

        $this->assertNotNull($write->shouldRequestApproval(new Request(['title' => 'Ship it'])));
        $this->assertNull($read->shouldRequestApproval(new Request(['id' => 'ENG-1'])));
        $this->assertStringStartsWith('mcp_work_', $write->name());
    }

    public function test_a_pending_tool_call_is_rendered_for_human_approval(): void
    {
        PersonalAssistant::fake([
            AgentResponse::fakeWithPendingApprovals([
                new PendingApproval(
                    id: 'call_123',
                    tool: 'mcp_work_create_issue',
                    arguments: ['title' => 'Ship the mobile app'],
                    reason: 'This may change data in Work.',
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

    public function test_a_human_decision_is_sent_back_to_the_paused_conversation(): void
    {
        $turn = Mockery::mock(ConversationTurn::class);
        $turn->shouldReceive('decide')
            ->once()
            ->withArgs(function (string $conversationId, Decisions $decisions): bool {
                return $conversationId === 'conversation-123'
                    && $decisions->get('call_123')->isApproved();
            })
            ->andReturn([
                'conversation_id' => 'conversation-123',
                'transcript' => '',
                'response' => 'The issue was created.',
                'audio_url' => null,
                'approvals' => [],
            ]);
        $this->app->instance(ConversationTurn::class, $turn);

        Livewire::test(Voice::class)
            ->set('conversationId', 'conversation-123')
            ->set('pendingApprovals', [[
                'id' => 'call_123',
                'tool' => 'mcp_work_create_issue',
                'arguments' => ['title' => 'Ship it'],
                'reason' => 'This may change data in Work.',
            ]])
            ->call('chooseApproval', 'call_123', 'approve')
            ->call('submitApprovals')
            ->assertSet('state', 'idle')
            ->assertSet('pendingApprovals', [])
            ->assertSee('The issue was created.');
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
