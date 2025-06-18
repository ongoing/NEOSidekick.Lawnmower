<?php

namespace NEOSidekick\Lawnmower\Tools;

use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Flow\Annotations as Flow;
use NEOSidekick\Lawnmower\Utility\Tools;

class CreateWorkspaceTool implements ToolInterface
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

        if ($this->workspaceRepository->findByIdentifier($workspaceName) !== null) {
            $randomSuffix = random_int(1000, 9999);
            $workspaceName .= '-' . $randomSuffix;
        }

        $liveWorkspace = $this->workspaceRepository->findByIdentifier('live');
        if ($liveWorkspace === null) {
            throw new \RuntimeException('Live workspace does not exist.', 1749588763035);
        }

        $workspace = new Workspace($workspaceName, $liveWorkspace);
        $this->workspaceRepository->add($workspace);

        return ['workspaceName' => $workspaceName];
    }

    public static function getDefinition(): array
    {
        return [
            'name' => Tools::convertClassNameToToolName(self::class),
            'description' => 'A tool to create a new workspace in the content repository',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'workspaceName' => [
                        'type' => 'string',
                        'description' => 'The name of the workspace to be created'
                    ]
                ],
                'required' => ['workspaceName']
            ],
            'annotations' => [
                'title' => 'Create Workspace Tool',
                'readOnlyHint' => false,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => false
            ]
        ];
    }
}
