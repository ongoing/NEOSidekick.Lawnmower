<?php

namespace NEOSidekick\Lawnmower;

use GuzzleHttp\Client;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Controller\CreateContentContextTrait;
use Psr\Log\LoggerInterface;
use NEOSidekick\Lawnmower\Dto\NodeChangeDto;
use NEOSidekick\Lawnmower\Dto\WorkspacePublishedDto;

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
                ['packageKey' => 'NEOSidekick.Lawnmower']
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
                    'packageKey' => 'NEOSidekick.Lawnmower'
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
                    ['packageKey' => 'NEOSidekick.Lawnmower']
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
                    ['packageKey' => 'NEOSidekick.Lawnmower']
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
                    ['packageKey' => 'NEOSidekick.Lawnmower']
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
                    ['packageKey' => 'NEOSidekick.Lawnmower']
                );
                // Store the "before" data - get the original node from the target workspace
                $this->nodePublishingWorkSpaceName = $targetWorkspace->getName();

                // Get the original node from the target workspace (before changes)
                $originalNode = null;
                try {
                    $context = $this->createContentContext($targetWorkspace->getName(), $node->getDimensions());
                    $originalNode = $context->getNodeByIdentifier($node->getIdentifier());
                } catch (\Exception $e) {
                    $this->systemLogger->warning('Could not fetch original node from target workspace: ' . $e->getMessage(), [
                        'packageKey' => 'NEOSidekick.Lawnmower'
                    ]);
                }

                if ($originalNode) {
                    $this->nodePublishingData[$node->getIdentifier()]['before'] =
                        $this->renderNodeArray($originalNode, true);
                } else {
                    // If we can't get the original node, this might be a new node being created
                    $this->nodePublishingData[$node->getIdentifier()]['before'] = null;
                }
                break;

            case 'Neos\ContentRepository\Domain\Model\Workspace::afterNodePublishing':
                [$node, $workspace] = $args;
                $this->systemLogger->debug(
                    'afterNodePublishing: ' . $node->getIdentifier()
                    . ' => ' . $workspace->getName(),
                    ['packageKey' => 'NEOSidekick.Lawnmower']
                );
                // Store the "after" data - but only if the node actually exists in the target workspace
                // If the node was deleted, it won't exist in the target workspace, so we don't store "after" data
                $nodeExistsInTargetWorkspace = null;
                try {
                    $context = $this->createContentContext($workspace->getName(), $node->getDimensions());
                    $nodeExistsInTargetWorkspace = $context->getNodeByIdentifier($node->getIdentifier());
                } catch (\Exception $e) {
                    $this->systemLogger->warning('Could not verify node existence in target workspace: ' . $e->getMessage(), [
                        'packageKey' => 'NEOSidekick.Lawnmower'
                    ]);
                }

                if ($nodeExistsInTargetWorkspace) {
                    // Node exists in target workspace - it was created or updated
                    $this->nodePublishingData[$node->getIdentifier()]['after'] =
                        $this->renderNodeArray($nodeExistsInTargetWorkspace, true);
                } else {
                    // Node doesn't exist in target workspace yet - might be a newly created node
                    // or a deleted node. For new nodes, use the source node data as fallback
                    if (!isset($this->nodePublishingData[$node->getIdentifier()]['before'])) {
                        // If there's no 'before' data, this is likely a new node
                        $this->systemLogger->debug('Newly created node, using source node data: ' . $node->getIdentifier(), [
                            'packageKey' => 'NEOSidekick.Lawnmower'
                        ]);
                        $this->nodePublishingData[$node->getIdentifier()]['after'] =
                            $this->renderNodeArray($node, true);
                    } else {
                        // There is 'before' data but no node in target workspace - it was deleted
                        $this->systemLogger->debug('Node was deleted: ' . $node->getIdentifier(), [
                            'packageKey' => 'NEOSidekick.Lawnmower'
                        ]);
                    }
                }
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

            // Skip if both before and after are null (shouldn't happen but safety check)
            if ($before === null && $after === null) {
                $this->systemLogger->warning('Skipping node with no before/after data: ' . $nodeIdentifier, [
                    'packageKey' => 'NEOSidekick.Lawnmower'
                ]);
                continue;
            }

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

            // Create nodeContextPath object from the AFTER node if possible; Fallback to BEFORE if node was removed
            $nodeContextPath = [
                'identifier' => $after['identifier'] ?? $before['identifier'] ?? $nodeIdentifier,
                'path' => $after['path'] ?? $before['path'] ?? 'unknown',
                'workspace' => $after['workspace'] ?? $before['workspace'] ?? $this->nodePublishingWorkSpaceName ?? 'unknown',
                'dimensions' => $after['dimensions'] ?? $before['dimensions'] ?? []
            ];
            $name = $after['name'] ?? $before['name'] ?? 'unknown';

            // propertiesBefore/propertiesAfter can be null
            $propertiesBefore = $before['properties'] ?? null;
            $propertiesAfter  = $changeType === 'removed' ? null : ($after['properties'] ?? null);

            $nodeChange = new NodeChangeDto($nodeContextPath, $name, $changeType, $propertiesBefore, $propertiesAfter);

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
     * @deprecated moved to NEOSidekick\Lawnmower\Utility\ArrayConverter
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
                'packageKey' => 'NEOSidekick.Lawnmower',
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
