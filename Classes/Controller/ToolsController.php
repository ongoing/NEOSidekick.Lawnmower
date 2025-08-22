<?php

namespace NEOSidekick\Lawnmower\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use Neos\Flow\Reflection\ReflectionService;
use NEOSidekick\Lawnmower\Tools\ToolInterface;

class ToolsController extends ActionController
{
    protected $defaultViewObjectName = JsonView::class;

    protected $supportedMediaTypes = ['application/json'];

    /**
     * @Flow\Inject
     * @var ReflectionService
     */
    protected $reflectionService;

    public function listAction(): void
    {
        $toolsImplementations = $this->reflectionService->getAllImplementationClassNamesForInterface(ToolInterface::class);
        $toolDefinitions = [];
        foreach ($toolsImplementations as $toolImplementation) {
            if (is_subclass_of($toolImplementation, ToolInterface::class)) {
                $toolDefinitions[] = $toolImplementation::getDefinition();
            }
        }
        $this->view->assign('value', $toolDefinitions);
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return void
     *
     * @Flow\SkipCsrfProtection
     */
    public function callAction(string $name, array $arguments): void
    {
        $toolsImplementations = $this->reflectionService->getAllImplementationClassNamesForInterface(ToolInterface::class);
        foreach ($toolsImplementations as $toolImplementation) {
            $fqdnParts = explode('\\', $toolImplementation);
            $className = array_pop($fqdnParts);
            $classNameWithoutToolPostfix = str_replace('Tool', '', $className);
            if ($classNameWithoutToolPostfix === $name) {
                $tool = new $toolImplementation();
                try {
                    $content = $tool->call($arguments);
                    $this->view->assign('value', ['isError' => false, 'content' => $content]);
                } catch (\Exception $e) {
                    $this->view->assign('value', ['isError' => true, 'content' => [
                        [
                            'type' => 'text',
                            'content' => sprintf('Error while calling tool "%s": %s', $name, $e->getMessage())
                        ]
                    ]]);
                }
                return;
            }
        }
        throw new \RuntimeException(sprintf('Tool "%s" not found.', $name), 1749589070307);
    }
}
