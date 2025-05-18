<?php

namespace NEOSidekick\ContentRepositoryWebhooks\Controller;

use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use NEOSidekick\ContentRepositoryWebhooks\Dto\CreateWorkspaceDto;

class WorkspaceController extends ActionController
{
    protected $defaultViewObjectName = JsonView::class;

    protected $supportedMediaTypes = ['application/json'];

    /**
     * @Flow\Inject
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    public function initializeCreateAction(): void
    {
        $propertyMappingConfiguration = $this->arguments->getArgument('createWorkspaceDto')->getPropertyMappingConfiguration();
        $propertyMappingConfiguration->allowAllProperties();
    }

    /**
     * @param CreateWorkspaceDto $createWorkspaceDto
     * @Flow\MapRequestBody("createWorkspaceDto")
     * @return void
     * @Flow\SkipCsrfProtection
     */
    public function createAction(CreateWorkspaceDto $createWorkspaceDto): void
    {
        $workspace = new Workspace($createWorkspaceDto->getWorkspaceName(), $createWorkspaceDto->getBaseWorkspace());
        $this->workspaceRepository->add($workspace);
        $this->view->assign('value', ['success' => true]);
    }
}
