<?php

namespace NEOSidekick\ContentRepositoryWebhooks\Dto;

use Neos\ContentRepository\Domain\Model\NodeInterface;

final class UpdateNodeDto
{
    protected NodeInterface $node;
    protected array $updatedProperties;

    public function __construct(NodeInterface $node, array $updatedProperties)
    {
        $this->node = $node;
        $this->updatedProperties = $updatedProperties;
    }

    public function getNode(): NodeInterface
    {
        return $this->node;
    }

    public function getUpdatedProperties(): array
    {
        return $this->updatedProperties;
    }
}
