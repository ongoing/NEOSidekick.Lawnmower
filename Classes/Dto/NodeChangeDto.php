<?php

namespace NEOSidekick\ContentRepositoryWebhooks\Dto;

final class NodeChangeDto
{
    private string $identifier;
    private string $name;
    /** @var string created|updated|removed */
    private string $changeType;
    private ?array $propertiesBefore;
    private ?array $propertiesAfter;

    /**
     * @param string $identifier
     * @param string $name
     * @param string $changeType       "created", "updated", or "removed"
     * @param array|null $propertiesBefore
     * @param array|null $propertiesAfter
     */
    public function __construct(
        string $identifier,
        string $name,
        string $changeType,
        ?array $propertiesBefore,
        ?array $propertiesAfter
    ) {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->changeType = $changeType;
        $this->propertiesBefore = $propertiesBefore;
        $this->propertiesAfter = $propertiesAfter;
    }

    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'name' => $this->name,
            'changeType' => $this->changeType,
            'propertiesBefore' => $this->propertiesBefore,
            'propertiesAfter' => $this->propertiesAfter
        ];
    }
}
