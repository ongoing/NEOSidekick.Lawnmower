<?php

namespace NEOSidekick\Lawnmower\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use NEOSidekick\Lawnmower\Dto\UpdateNodeDto;

class NodeController extends ActionController
{
    protected $defaultViewObjectName = JsonView::class;

    protected $supportedMediaTypes = ['application/json'];

    public function initializeUpdateAction(): void
    {
        $propertyMappingConfiguration = $this->arguments->getArgument('updateNodeDto')->getPropertyMappingConfiguration();
        $propertyMappingConfiguration->allowAllProperties();
    }

    /**
     * @param UpdateNodeDto $updateNodeDto
     * @Flow\MapRequestBody("updateNodeDto")
     * @return void
     * @Flow\SkipCsrfProtection
     */
    public function updateAction(UpdateNodeDto $updateNodeDto): void
    {
        $node = $updateNodeDto->getNode();
        $updatedProperties = $updateNodeDto->getUpdatedProperties();
        foreach ($updatedProperties as $propertyName => $propertyValue) {
            $node->setProperty($propertyName, $propertyValue);
        }
        $this->view->assign('value', ['success' => true]);
    }
}
