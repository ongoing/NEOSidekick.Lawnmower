<?php

namespace NEOSidekick\ContentRepositoryWebhooks\Tools;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Controller\CreateContentContextTrait;
use Neos\Neos\Domain\Service\NodeSearchService;
use NEOSidekick\ContentRepositoryWebhooks\Utility\ArrayConverter;

class SearchInNodesTool implements ToolInterface
{
    use CreateContentContextTrait;

    /**
     * @var NodeSearchService
     * @Flow\Inject
     */
    protected $nodeSearchService;

    public function call(array $arguments): array
    {
        $term = $arguments['term'] ?? '';
        $searchNodeTypes = $arguments['searchNodeTypes'] ?? [];
        $workspaceName = $arguments['workspaceName'] ?? 'live';
        $dimensions = $arguments['dimensions'] ?? [];
        $startingPoint = $arguments['startingPoint'] ?? null;

        $context = $this->createContentContext($workspaceName, $dimensions);
        $result = $this->nodeSearchService->findByProperties(
            $term,
            $searchNodeTypes,
            $context,
            $startingPoint ? $context->getNode($startingPoint) : null
        );
        return array_map([ArrayConverter::class, 'node'], $result);
    }

    public static function getDefinition(): array
    {
        return [
            'name' => self::class,
            'description' => 'A tool to search for nodes in the content repository',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'term' => [
                        'type' => 'string',
                        'description' => 'The search term that should be looked for in the properties of a node.'
                    ],
                    'workspaceName' => [
                        'type' => 'string',
                        'description' => 'The name of the workspace to search in.',
                        'default' => 'live'
                    ],
                    'dimensions' => [
                        'type' => 'object',
                        'description' => 'The dimensions to search in.',
                        'properties' => [
                            'language' => [
                                'type' => 'string',
                                'description' => 'The language dimension value or values, comma separated.'
                            ]
                        ],
                        'default' => []
                    ],
                    'startingPoint' => [
                        'type' => 'string',
                        'description' => 'The path of the node to start the search from. If not provided, the search will start from the root node.'
                    ],
                    'searchNodeTypes' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'description' => 'The node types to search for. If not provided, all node types will be searched.'
                        ],
                        'description' => 'An array of node type names to filter the search results.'
                    ]
                ],
                'required' => ['term', 'workspaceName']
            ],
            'annotations' => [
                'title' => 'Search in Nodes',
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => false
            ]
        ];
    }
}
