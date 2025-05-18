<?php

namespace NEOSidekick\ContentRepositoryWebhooks;

use GuzzleHttp\Client;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Controller\CreateContentContextTrait;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class SignalCollectionService
{
    use CreateContentContextTrait;

    /**
     * @var LoggerInterface
     * @Flow\Inject
     */
    protected $systemLogger;

    protected array $collectedSignals = [];

    /**
     * @var string
     * @Flow\InjectConfiguration("webhookUrl")
     */
    protected string $webhookUrl;

    public function registerSignal(mixed ...$args): void
    {
        if (!$this->webhookUrl) {
            $this->systemLogger->warning('No webhook URL configured. Skipping signal handling.', ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']);
            return;
        }

        $signalClassAndMethod = array_pop($args);
        switch($signalClassAndMethod) {
            case 'Neos\ContentRepository\Domain\Model\Node::nodeUpdated':
            case 'Neos\ContentRepository\Domain\Model\Node::nodeAdded':
            case 'Neos\ContentRepository\Domain\Model\Node::nodeRemoved':
                $node = $args[0];
                $this->systemLogger->debug('Node updated or added: ' . $node->getIdentifier(), ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']);
                $this->collectedSignals[] = [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node)
                ];
                $this->sendWebhookRequest(
                    $this->webhookUrl,
                    [
                        'event' => $signalClassAndMethod,
                        'node' => $this->renderNodeArray($node)
                    ]
                );
                break;
            case 'Neos\ContentRepository\Domain\Model\Node::nodePropertyChanged':
                [$node, $propertyName, $oldValue, $newValue] = $args;
                $this->systemLogger->debug('Node property changed: ' . $node->getIdentifier() . ' Property: ' . $propertyName . ' Old Value: ' . $oldValue . ' New Value: ' . $newValue, ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']);
                $this->collectedSignals[] = [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node),
                    'propertyChange' => $this->renderNodePropertyChangeArray($node, $propertyName, $oldValue, $newValue)
                ];
                $this->sendWebhookRequest(
                    $this->webhookUrl,
                    [
                        'event' => $signalClassAndMethod,
                        'node' => $this->renderNodeArray($node),
                        'propertyChange' => $this->renderNodePropertyChangeArray($node, $propertyName, $oldValue, $newValue)
                    ]
                );
                break;
            case 'Neos\ContentRepository\Domain\Service\PublishingService::nodePublished':
                [$node, $workspace] = $args;
                $this->systemLogger->debug('Node published: ' . $node->getIdentifier() . ' to workspace: ' . $workspace->getName(), ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']);
                $this->collectedSignals[] = [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node),
                    'workspace' => $this->renderWorkspaceArray($workspace)
                ];
                $this->sendWebhookRequest(
                    $this->webhookUrl,
                    [
                        'event' => $signalClassAndMethod,
                        'node' => $this->renderNodeArray($node),
                        'workspace' => $this->renderWorkspaceArray($workspace)
                    ]
                );
                break;
            case 'Neos\ContentRepository\Domain\Service\PublishingService::nodeDiscarded':
                [$node, $workspace] = $args;
                $this->systemLogger->debug('Node discarded: ' . $node->getIdentifier() . ' from workspace: ' . $workspace->getName(), ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']);
                $this->collectedSignals[] = [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node),
                    'workspace' => $this->renderWorkspaceArray($workspace)
                ];
                $this->sendWebhookRequest(
                    $this->webhookUrl,
                    [
                        'event' => $signalClassAndMethod,
                        'node' => $this->renderNodeArray($node),
                        'workspace' => $this->renderWorkspaceArray($workspace)
                    ]
                );
                break;
            case 'Neos\ContentRepository\Domain\Model\Workspace::beforeNodePublishing':
                [$node, $targetWorkspace] = $args;
                $this->systemLogger->debug('Node before publishing: ' . $node->getIdentifier() . ' to workspace: ' . $targetWorkspace->getName(), ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']);
                $this->systemLogger->debug('Node property changes:', $this->renderNodePropertyChangesArray($node, $targetWorkspace));
                $this->collectedSignals[] = [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node),
                    'propertyChanges' => $this->renderNodePropertyChangesArray($node, $targetWorkspace)
                ];
                break;
            case 'Neos\ContentRepository\Domain\Model\Workspace::afterNodePublishing':
                [$node, $workspace] = $args;
                $this->systemLogger->debug('Node after publishing: ' . $node->getIdentifier() . ' in workspace: ' . $workspace->getName(), ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']);
                $this->collectedSignals[] = [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node),
                    'workspace' => $this->renderWorkspaceArray($workspace)
                ];
                $this->sendWebhookRequest(
                    $this->webhookUrl,
                    [
                        'event' => $signalClassAndMethod,
                        'node' => $this->renderNodeArray($node),
                        'workspace' => $this->renderWorkspaceArray($workspace)
                    ]
                );
                break;
            default:
                // Handle other signals or ignore
                break;
        }
    }

    private function renderNodeArray(NodeInterface $node): array
    {
        return [
            'identifier' => $node->getIdentifier(),
            'name' => $node->getName(),
            'path' => $node->getPath(),
            'properties' => $node->getProperties(),
            'type' => $node->getNodeType()->getName(),
            'workspace' => $node->getWorkspace()->getName(),
            'dimensions' => $node->getDimensions()
        ];
    }

    private function renderWorkspaceArray(Workspace $workspace): array
    {
        return [
            'name' => $workspace->getName(),
            'title' => $workspace->getTitle(),
            'description' => $workspace->getDescription()
        ];
    }

    private function renderNodePropertyChangeArray(NodeInterface $node, string $propertyName, mixed $oldValue, mixed $newValue): array
    {
        return [
            'identifier' => $node->getIdentifier(),
            'propertyName' => $propertyName,
            'oldValue' => $oldValue,
            'newValue' => $newValue
        ];
    }

    private function sendWebhookRequest(string $url, array $payload): void
    {
        $client = new Client();
        $response = $client->sendRequest(new WebhookRequest($url, $payload));
        $this->systemLogger->debug('Webhook response: ' . $response->getStatusCode() . $response->getBody(), ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']);
    }

    private function renderNodePropertyChangesArray(NodeInterface $node, Workspace $targetWorkspace): array
    {
        $targetContext = $this->createContentContext(
            $targetWorkspace->getName(),
            $node->getDimensions()
        );
        $nodeDataInSourceContext = $node->getNodeData();
        $nodeInTargetContext = $targetContext->getNode($node->getPath());

        if ($nodeInTargetContext === null) {
            return [];
        }

        $nodeDataInTargetContext = $nodeInTargetContext->getNodeData();
        $nodeType = $node->getNodeType();
        $changedProperties = [];
        foreach ($nodeType->getProperties() as $propertyName => $propertyType) {
            if ($propertyType['type'] !== 'string') {
                continue;
            }

            if ($nodeDataInSourceContext->getProperty($propertyName) !== $nodeDataInTargetContext->getProperty($propertyName)) {
                $changedProperties[$propertyName] = [
                    'newValue' => $nodeDataInSourceContext->getProperty($propertyName),
                    'oldValue' => $nodeDataInTargetContext->getProperty($propertyName)
                ];
            }
        }

        return [
            'identifier' => $node->getIdentifier(),
            'changedProperties' => $changedProperties
        ];
    }

    public function shutdownObject(): void
    {
        $this->systemLogger->debug('Collected signals:', $this->collectedSignals);
    }
}
