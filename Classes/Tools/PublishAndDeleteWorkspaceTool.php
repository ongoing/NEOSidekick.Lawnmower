<?php

namespace NEOSidekick\Lawnmower\Tools;

use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Flow\Annotations as Flow;
use NEOSidekick\Lawnmower\Utility\Tools;

class PublishAndDeleteWorkspaceTool implements ToolInterface
{
    /**
     * @Flow\Inject
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

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

        $workspace->publish($workspace->getBaseWorkspace());
        $this->workspaceRepository->remove($workspace);

        return ['workspaceName' => $workspaceName];
    }

    public static function getDefinition(): array
    {
        return [
            'name' => Tools::convertClassNameToToolName(self::class),
            'description' => 'A tool to publish and delete in the content repository',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'workspaceName' => [
                        'type' => 'string',
                        'description' => 'The name of the workspace to be published and deleted'
                    ]
                ],
                'required' => ['workspaceName']
            ],
            'annotations' => [
                'title' => 'Publish and Delete Workspace Tool',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => false,
                'openWorldHint' => false
            ]
        ];
    }
}
