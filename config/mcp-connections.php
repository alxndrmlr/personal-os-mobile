<?php

return [
    'presets' => [
        'linear' => [
            'name' => 'Linear',
            'url' => 'https://mcp.linear.app/mcp',
            'auth_type' => 'oauth',
            'scope' => '',
            'description' => 'Search projects and issues, then create or update work with approval.',
        ],
        'backbone' => [
            'name' => 'Backbone',
            'url' => 'https://backbone.govai.com/mcp',
            'auth_type' => 'bearer',
            'scope' => '',
            'description' => 'Connect to the GovAI Backbone MCP gateway.',
        ],
        'slack' => [
            'name' => 'Slack',
            'url' => 'https://mcp.slack.com/mcp',
            'auth_type' => 'oauth',
            'scope' => implode(' ', [
                'search:read.public',
                'search:read.private',
                'search:read.mpim',
                'search:read.im',
                'search:read.files',
                'files:read',
                'emoji:read',
                'search:read.users',
                'channels:history',
                'groups:history',
                'mpim:history',
                'im:history',
                'channels:read',
                'groups:read',
                'im:read',
                'mpim:read',
                'users:read',
                'users:read.email',
                'chat:write',
            ]),
            'description' => 'Search and read Slack; posting messages always pauses for approval.',
        ],
        'notion' => [
            'name' => 'Notion',
            'url' => 'https://mcp.notion.com/mcp',
            'auth_type' => 'oauth',
            'scope' => '',
            'description' => 'Search your workspace and manage pages through Notion’s hosted MCP.',
        ],
    ],
];
