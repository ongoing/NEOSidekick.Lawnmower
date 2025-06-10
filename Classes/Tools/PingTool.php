<?php

namespace NEOSidekick\ContentRepositoryWebhooks\Tools;

class PingTool implements ToolInterface
{

    public static function getDefinition(): array
    {
        // {
        //  name: string;          // Unique identifier for the tool
        //  description?: string;  // Human-readable description
        //  inputSchema: {         // JSON Schema for the tool's parameters
        //    type: "object",
        //    properties: { ... }  // Tool-specific parameters
        //  },
        //  annotations?: {        // Optional hints about tool behavior
        //    title?: string;      // Human-readable title for the tool
        //    readOnlyHint?: boolean;    // If true, the tool does not modify its environment
        //    destructiveHint?: boolean; // If true, the tool may perform destructive updates
        //    idempotentHint?: boolean;  // If true, repeated calls with same args have no additional effect
        //    openWorldHint?: boolean;   // If true, tool interacts with external entities
        //  }
        //}
        return [
            'name' => self::class,
            'description' => 'A tool that does something interesting',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'param1' => [
                        'type' => 'string',
                        'description' => 'A parameter for the PingTool'
                    ],
                    'param2' => [
                        'type' => 'integer',
                        'description' => 'Another parameter for the PingTool'
                    ]
                ],
                'required' => ['param1']
            ],
            'annotations' => [
                'title' => 'Ping Tool',
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => false
            ]
        ];
    }

    public function call(array $arguments): array
    {
        return [
            [
                'type' => 'text',
                'content' => 'Pong! You called the PingTool with arguments: ' . json_encode($arguments)
            ]
        ];
    }
}
