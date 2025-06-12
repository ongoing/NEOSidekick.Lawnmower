<?php

namespace NEOSidekick\ContentRepositoryWebhooks\TypeConverter;

use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Flow\Property\Exception\TypeConverterException;
use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;
use Neos\Neos\Controller\CreateContentContextTrait;
use NEOSidekick\ContentRepositoryWebhooks\Dto\UpdateNodeDto;

class UpdateNodeDtoConverter extends AbstractTypeConverter
{
    use CreateContentContextTrait;

    protected $priority = 100;

    protected $sourceTypes = [
        'array',
    ];

    protected $targetType = UpdateNodeDto::class;

    /**
     * @inheritDoc
     */
    public function convertFrom(
        $source,
        $targetType,
        array $convertedChildProperties = [],
        ?PropertyMappingConfigurationInterface $configuration = null
    ): UpdateNodeDto {
        ['nodeContextPath' => $nodeContextPath, 'updatedProperties' => $updatedProperties] = $source;
        if (is_string($nodeContextPath)) {
            $nodeContextPath = NodePaths::explodeContextPath($nodeContextPath);
        }
        ['nodePath' => $nodePath, 'workspaceName' => $workspaceName, 'dimensions' => $dimensions] = $nodeContextPath;
        $context = $this->createContentContext(
            $workspaceName,
            $dimensions
        );

        $node = $context->getNode($nodePath);

        if ($node === null) {
            throw new TypeConverterException('Node not found: ' . $nodePath, 1747565389499);
        }

        // Check if properties are defined in the node type
        $nodeType = $node->getNodeType();
        foreach ($updatedProperties as $propertyName => $propertyValue) {
            if (!array_key_exists($propertyName, $nodeType->getProperties())) {
                throw new TypeConverterException('Property "' . $propertyName . '" is not valid for node type "' . $nodeType->getName() . '"', 1747565387209);
            }
        }

        return new UpdateNodeDto($node, $updatedProperties);
    }
}
