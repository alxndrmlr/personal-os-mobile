<?php

namespace Tests\Feature;

use App\Ai\Agents\PersonalAssistant;
use App\Ai\Tools\ApprovableMcpTool;
use App\Models\AgentActivity;
use App\Models\McpServer;
use App\NativeComponents\ConnectionDetail;
use App\NativeComponents\Connections;
use App\NativeComponents\Voice;
use App\Services\ConversationTurn;
use App\Services\PersonalUser;
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Tools\Request;
use Laravel\Mcp\Client\Primitives\Tool as McpTool;
use Mockery;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;
use Tests\TestCase;

class McpConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        AsyncTask::clearFake();

        parent::tearDown();
    }

    public function test_a_server_can_be_added_from_the_connections_screen(): void
    {
        Native::test(Connections::class)
            ->set('name', 'Work')
            ->set('url', 'https://mcp.example.com/mcp')
            ->set('authType', 'Bearer token')
            ->set('token', 'secret-token')
            ->call('addServer')
            ->assertSee('Work')
            ->assertSee('Enabled')
            ->assertAccessible();

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

    public function test_a_server_opens_in_the_native_connection_detail_screen(): void
    {
        $server = McpServer::query()->create([
            'user_id' => app(PersonalUser::class)->get()->getKey(),
            'name' => 'Work',
            'slug' => 'work',
            'url' => 'https://mcp.example.com/mcp',
            'auth_type' => 'none',
            'approval_mode' => 'writes',
            'enabled' => true,
        ]);

        Native::visit("/connections/{$server->id}")
            ->assertScreen(ConnectionDetail::class)
            ->assertSee('Work')
            ->assertSee('Test connection')
            ->assertAccessible();
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
        AsyncTask::fake();
        LocalNotifications::shouldReceive('schedule')->once()->andReturn([]);

        Native::test(Voice::class)
            ->set('draft', 'Create the issue')
            ->call('sendText')
            ->assertSet('state', 'awaiting_approval')
            ->assertSet('pendingApprovals.0.id', 'call_123')
            ->assertSee('Review requested actions')
            ->assertSee('Ship the mobile app');

        $this->assertDatabaseHas('agent_activities', [
            'title' => 'Create the issue',
            'status' => AgentActivity::STATUS_NEEDS_INPUT,
        ]);
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
                'audio_path' => null,
                'approvals' => [],
            ]);
        $this->app->instance(ConversationTurn::class, $turn);
        AsyncTask::fake();

        Native::test(Voice::class)
            ->set('conversationId', 'conversation-123')
            ->set('pendingApprovals', [[
                'id' => 'call_123',
                'tool' => 'mcp_work_create_issue',
                'arguments' => ['title' => 'Ship it'],
                'reason' => 'This may change data in Work.',
            ]])
            ->call('chooseApproval', 0, true)
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
