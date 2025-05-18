<?php

namespace NEOSidekick\ContentRepositoryWebhooks\Dto;

use Neos\ContentRepository\Domain\Model\Workspace;

class CreateWorkspaceDto
{
    protected string $workspaceName;
    protected Workspace $baseWorkspace;

    public function __construct(string $workspaceName, Workspace $baseWorkspace)
    {
        $this->workspaceName = $workspaceName;
        $this->baseWorkspace = $baseWorkspace;
    }

    public function getWorkspaceName(): string
    {
        return $this->workspaceName;
    }

    public function getBaseWorkspace(): Workspace
    {
        return $this->baseWorkspace;
    }
}
