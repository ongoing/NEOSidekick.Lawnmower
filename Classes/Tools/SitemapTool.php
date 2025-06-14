<?php

namespace NEOSidekick\ContentRepositoryWebhooks\Tools;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use NEOSidekick\ContentRepositoryWebhooks\Utility\Tools;


class SitemapTool implements ToolInterface
{
    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;


    public function call(array $arguments): array
    {
        $dimension = $arguments['dimension'] ?? null;
        $contextProperties = [
            'workspaceName' => 'live',
        ];

        if ($dimension) {
            $contextProperties['dimensions'] = $dimension;
        }

        $contentContext = $this->contextFactory->create($contextProperties);

        $rootNodeContextPath = $arguments['rootNodeContextPath'] ?? '/sites';
        $rootNode = $contentContext->getNode($rootNodeContextPath);

        if (!$rootNode) {
            return ['error' => 'Root node not found'];
        }

        $sitemap = $this->generateSitemapMarkdown($rootNode, 0);

        return ['sitemap' => $sitemap];
    }

    private function generateSitemapMarkdown(NodeInterface $node, int $level): string
    {
        $markdown = '';
        if ($this->isDocumentNodeVisible($node)) {
            $title = $node->getProperty('title');

            // Handle special case for nodes without title (like /sites container)
            if (empty($title)) {
                $nodePath = $node->getPath();
                if ($nodePath === '/sites') {
                    $title = 'Sites';
                }
            }

            $indentation = str_repeat('  ', $level);
            $contextPath = $node->getContextPath();

            $markdown .= $indentation . '- [' . $title . '](' . $contextPath . ')' . PHP_EOL;
        }

        foreach ($node->getChildNodes('Neos.Neos:Document') as $childDocumentNode) {
            $markdown .= $this->generateSitemapMarkdown($childDocumentNode, $level + 1);
        }

        return $markdown;
    }

    private function isDocumentNodeVisible(NodeInterface $node): bool
    {
        if ($node->isHiddenInIndex() || !$node->isAccessible() || !$node->isVisible()) {
            return false;
        }
        return true;
    }


    public static function getDefinition(): array
    {
        return [
            'name' => Tools::convertClassNameToToolName(self::class),
            'description' => 'A tool to generate a sitemap of the content repository as a markdown file tree.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'rootNodeContextPath' => [
                        'type' => 'string',
                        'description' => 'The context path of the root node for the sitemap (e.g. /sites/my-site).',
                        'default' => '/sites'
                    ],
                    'dimension' => [
                        'type' => 'string',
                        'description' => 'The content dimension to use for the sitemap (e.g. "de_DE"). Optional, defaults to default dimension.'
                    ]
                ],
            ],
            'annotations' => [
                'title' => 'Sitemap Generator Tool',
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => false
            ]
        ];
    }
}
