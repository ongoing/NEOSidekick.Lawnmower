<?php

namespace NEOSidekick\ContentRepositoryWebhooks\Utility;

use Neos\ContentRepository\Domain\Model\NodeInterface;

final class ArrayConverter
{
    public static function node(NodeInterface $node): array
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
}
