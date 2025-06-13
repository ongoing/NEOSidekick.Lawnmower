<?php

namespace NEOSidekick\ContentRepositoryWebhooks;

use GuzzleHttp\Client;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Controller\CreateContentContextTrait;
use Psr\Log\LoggerInterface;
use NEOSidekick\ContentRepositoryWebhooks\Dto\NodeChangeDto;
use NEOSidekick\ContentRepositoryWebhooks\Dto\WorkspacePublishedDto;

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

    /**
     * Collects every single incoming signal (e.g. Node added, property changed).
     * We send these events to the webhook immediately (existing functionality).
     */
    protected array $collectedSignals = [];

    /**
     * @var array
     * @Flow\InjectConfiguration(path="endpoints")
     */
    protected array $endpoints = [];

    /**
     * Array to store "before" and "after" states during publishing signals.
     * Example:
     * [
     *   'some-node-identifier' => [
     *     'before' => [
     *       'identifier' => '...',
     *       'name' => '...',
     *       'properties' => [ ... ]
     *     ],
     *     'after' => [
     *       'identifier' => '...',
     *       'name' => '...',
     *       'properties' => [ ... ]
     *     ]
     *   ],
     *   ...
     * ]
     */
    protected array $nodePublishingData = [];
    protected ?string $nodePublishingWorkSpaceName = null;

    /**
     * Registers various signals. Sends immediate webhooks for node add/remove/etc.
     */
    public function registerSignal(mixed ...$args): void
    {
        if (empty($this->endpoints)) {
            $this->systemLogger->warning(
                'No webhook endpoints configured. Skipping signal handling.',
                ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']
            );
            return;
        }

        // The last array element is always the signal class + method string:
        $signalClassAndMethod = array_pop($args);
        $eventName = $this->getEventNameFromSignal($signalClassAndMethod);

        switch ($signalClassAndMethod) {
            // ----------------------------------------------------------------
            // Neos CMS default immediate signals
            // ----------------------------------------------------------------
            case 'Neos\ContentRepository\Domain\Model\Node::nodeUpdated':
            case 'Neos\ContentRepository\Domain\Model\Node::nodeAdded':
            case 'Neos\ContentRepository\Domain\Model\Node::nodeRemoved':
                $node = $args[0];
                $this->systemLogger->debug($signalClassAndMethod . ': ' . $node->getIdentifier(), [
                    'packageKey' => 'NEOSidekick.ContentRepositoryWebhooks'
                ]);
                $this->collectedSignals[] = [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node)
                ];
                $this->sendWebhookRequests($eventName, [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node)
                ]);
                break;

            case 'Neos\ContentRepository\Domain\Model\Node::nodePropertyChanged':
                [$node, $propertyName, $oldValue, $newValue] = $args;
                $this->systemLogger->debug(
                    $signalClassAndMethod . ': ' . $node->getIdentifier()
                    . ' Property: ' . $propertyName
                    . ' Old Value: ' . $oldValue
                    . ' New Value: ' . $newValue,
                    ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']
                );
                $this->collectedSignals[] = [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node),
                    'propertyChange' => $this->renderNodePropertyChangeArray($node, $propertyName, $oldValue, $newValue)
                ];
                $this->sendWebhookRequests($eventName, [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node),
                    'propertyChange' => $this->renderNodePropertyChangeArray($node, $propertyName, $oldValue, $newValue)
                ]);
                break;

            case 'Neos\ContentRepository\Domain\Service\PublishingService::nodePublished':
                [$node, $workspace] = $args;
                $this->systemLogger->debug(
                    'Node published: ' . $node->getIdentifier()
                    . ' to workspace: ' . $workspace->getName(),
                    ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']
                );
                $this->collectedSignals[] = [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node),
                    'workspace' => $this->renderWorkspaceArray($workspace)
                ];
                $this->sendWebhookRequests($eventName, [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node),
                    'workspace' => $this->renderWorkspaceArray($workspace)
                ]);
                break;

            case 'Neos\ContentRepository\Domain\Service\PublishingService::nodeDiscarded':
                [$node, $workspace] = $args;
                $this->systemLogger->debug(
                    $signalClassAndMethod . ': ' . $node->getIdentifier()
                    . ' from workspace: ' . $workspace->getName(),
                    ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']
                );
                $this->collectedSignals[] = [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node),
                    'workspace' => $this->renderWorkspaceArray($workspace)
                ];
                $this->sendWebhookRequests($eventName, [
                    'event' => $signalClassAndMethod,
                    'node' => $this->renderNodeArray($node),
                    'workspace' => $this->renderWorkspaceArray($workspace)
                ]);
                break;

            // ----------------------------------------------------------------
            // Accumulate before/after states for final "WorkspacePublished" event
            // ----------------------------------------------------------------
            case 'Neos\ContentRepository\Domain\Model\Workspace::beforeNodePublishing':
                [$node, $targetWorkspace] = $args;
                $this->systemLogger->debug(
                    'beforeNodePublishing: ' . $node->getIdentifier()
                    . ' => ' . $targetWorkspace->getName(),
                    ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']
                );
                // Store the "before" data
                $this->nodePublishingWorkSpaceName = $targetWorkspace->getName();
                $this->nodePublishingData[$node->getIdentifier()]['before'] =
                    $this->renderNodeArray($node, includeProperties: true);
                break;

            case 'Neos\ContentRepository\Domain\Model\Workspace::afterNodePublishing':
                [$node, $workspace] = $args;
                $this->systemLogger->debug(
                    'afterNodePublishing: ' . $node->getIdentifier()
                    . ' => ' . $workspace->getName(),
                    ['packageKey' => 'NEOSidekick.ContentRepositoryWebhooks']
                );
                // Store the "after" data
                $this->nodePublishingData[$node->getIdentifier()]['after'] =
                    $this->renderNodeArray($node, includeProperties: true);
                break;

            default:
                // You could handle or ignore other signals
                break;
        }
    }

    /**
     * Called at the end of this object's lifecycle.
     * We'll send a single "WorkspacePublished" event for each workspace that had publishing.
     * This includes all nodes that were created, updated, or removed.
     */
    public function shutdownObject(): void
    {
        if (empty($this->nodePublishingData)) {
            return;
        }

        $eventName = 'workspacePublished';

        $this->systemLogger->debug('Publishing Data (before sending):', $this->nodePublishingData);
        $changes = [];
        foreach ($this->nodePublishingData as $nodeIdentifier => $states) {
            $before = $states['before'] ?? null;
            $after  = $states['after'] ?? null;

            // If there's no "before" => created
            // If there's no "after" => removed
            // Otherwise => updated
            if ($before === null && $after !== null) {
                $changeType = 'created';
            } elseif ($before !== null && $after === null) {
                $changeType = 'removed';
            } else {
                $changeType = 'updated';
            }

            // "identifier" and "name" come from the AFTER node if possible; Fallback to BEFORE if node was removed
            $identifier = $after['identifier'] ?? $before['identifier'] ?? 'unknown';
            $name       = $after['name']       ?? $before['name']       ?? 'unknown';

            // propertiesBefore/propertiesAfter can be null
            $propertiesBefore = $before['properties'] ?? null;
            $propertiesAfter  = $after['properties']  ?? null;

            $nodeChange = new NodeChangeDto($identifier, $name, $changeType, $propertiesBefore, $propertiesAfter);

            $changes[] = $nodeChange->toArray();
        }

        $workspacePublishedDto = new WorkspacePublishedDto('WorkspacePublished', $this->nodePublishingWorkSpaceName, $changes);

        $this->sendWebhookRequests(
            $eventName,
            $workspacePublishedDto->toArray()
        );

        $this->systemLogger->debug('Publishing Data (before cleanup):', $this->nodePublishingData);
        $this->nodePublishingData = [];
    }

    /**
     * @param NodeInterface $node
     * @param bool $includeProperties
     *
     * @return array
     *
     * @deprecated moved to NEOSidekick\ContentRepositoryWebhooks\Utility\ArrayConverter
     */
    private function renderNodeArray(
        Node $node,
        bool $includeProperties = false
    ): array {
        $nodeArray = [
            'identifier' => $node->getIdentifier(),
            'name' => $node->getName(),
            'path' => $node->getPath(),
            'type' => $node->getNodeType()->getName(),
            'workspace' => $node->getWorkspace()->getName(),
            'dimensions' => $node->getDimensions()
        ];

        if ($includeProperties) {
            $nodeArray['properties'] = (array) $node->getProperties();
        }

        return $nodeArray;
    }

    private function renderWorkspaceArray(Workspace $workspace): array
    {
        return [
            'name' => $workspace->getName(),
            'title' => $workspace->getTitle(),
            'description' => $workspace->getDescription()
        ];
    }

    private function renderNodePropertyChangeArray(
        Node $node,
        string $propertyName,
        mixed $oldValue,
        mixed $newValue
    ): array {
        return [
            'identifier' => $node->getIdentifier(),
            'propertyName' => $propertyName,
            'oldValue' => $oldValue,
            'newValue' => $newValue
        ];
    }

    private function sendWebhookRequests(string $eventName, array $payload): void
    {
        if (empty($this->endpoints[$eventName])) {
            return;
        }
        $endpointUrls = $this->endpoints[$eventName];

        foreach ($endpointUrls as $endpointUrl) {
            $this->sendWebhookRequest($endpointUrl, $payload);
        }
    }

    private function sendWebhookRequest(string $url, array $payload): void
    {
        try {
            $client = new Client();
            $client->post($url, [
                'json' => $payload
            ]);
        } catch (\Exception $e) {
            $this->systemLogger->error('Webhook request failed: ' . $e->getMessage(), [
                'packageKey' => 'NEOSidekick.ContentRepositoryWebhooks',
                'exception' => $e
            ]);
        }
    }

    private function getEventNameFromSignal(string $signalClassAndMethod): string
    {
        if (str_contains($signalClassAndMethod, '::')) {
            return substr($signalClassAndMethod, strrpos($signalClassAndMethod, '::') + 2);
        }
        return $signalClassAndMethod;
    }
}
