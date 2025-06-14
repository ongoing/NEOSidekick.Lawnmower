<?php

namespace NEOSidekick\ContentRepositoryWebhooks\Tools;

use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\PublishingService;
use Neos\Neos\Ui\ContentRepository\Service\WorkspaceService;
use NEOSidekick\ContentRepositoryWebhooks\Utility\Tools;

class DiscardAndDeleteWorkspaceTool implements ToolInterface
{
    /**
     * @Flow\Inject
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var PublishingService
     */
    protected $publishingService;

    public function call(array $arguments): array
    {
        [
            'workspaceName' => $workspaceName
        ] = $arguments;

        $workspace = $this->workspaceRepository->findByIdentifier($workspaceName);
        if ($workspace === null) {
            throw new \RuntimeException('Workspace with the given name does not exist.', 1749901919965);
        }

        if ($workspace->getBaseWorkspace() === null) {
            throw new \RuntimeException('Workspace does not have a base workspace.', 1749901965537);
        }

        $unpublishedNodes = $this->publishingService->getUnpublishedNodes($workspace);
        $this->publishingService->discardNodes($unpublishedNodes);
        $this->workspaceRepository->remove($workspace);

        return ['workspaceName' => $workspaceName];
    }

    public static function getDefinition(): array
    {
        return [
            'name' => Tools::convertClassNameToToolName(self::class),
            'description' => 'A tool to discard and delete in the content repository',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'workspaceName' => [
                        'type' => 'string',
                        'description' => 'The name of the workspace to be discard and deleted'
                    ]
                ],
                'required' => ['workspaceName']
            ],
            'annotations' => [
                'title' => 'Discard and Delete Workspace Tool',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => false,
                'openWorldHint' => false
            ]
        ];
    }
}
