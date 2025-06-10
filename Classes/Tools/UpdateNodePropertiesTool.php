<?php

namespace NEOSidekick\ContentRepositoryWebhooks\Tools;

use Neos\Flow\Property\PropertyMappingConfiguration;
use Neos\Flow\Property\TypeConverter\ObjectConverter;
use Neos\Neos\TypeConverter\NodeConverter;
use NEOSidekick\ContentRepositoryWebhooks\Dto\UpdateNodeDto;
use NEOSidekick\ContentRepositoryWebhooks\TypeConverter\UpdateNodeDtoConverter;

class UpdateNodePropertiesTool implements ToolInterface
{

    public function call(array $arguments): array
    {
        $objectConverter = new UpdateNodeDtoConverter();
        $propertyConversionConfiguration = new PropertyMappingConfiguration();
        $propertyConversionConfiguration->allowAllProperties();
        $updateNodeDto = $objectConverter->convertFrom($arguments, UpdateNodeDto::class, [], $propertyConversionConfiguration);

        $node = $updateNodeDto->getNode();
        $updatedProperties = $updateNodeDto->getUpdatedProperties();
        foreach ($updatedProperties as $propertyName => $propertyValue) {
            $node->setProperty($propertyName, $propertyValue);
        }

        return ['nodeContextPath' => $node->getContextPath(), 'updatedProperties' => $updatedProperties];
    }

    public static function getDefinition(): array
    {
        return [
            'name' => self::class,
            'description' => 'A tool to update properties of a node in the content repository',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'nodeContextPath' => [
                        'type' => 'string',
                        'description' => 'The identifier of the node to update'
                    ],
                    'properties' => [
                        'type' => 'object',
                        'description' => 'An object containing the properties to update'
                    ]
                ],
                'required' => ['nodeIdentifier', 'properties']
            ],
            'annotations' => [
                'title' => 'Update Node Properties Tool',
                'readOnlyHint' => false,
                'destructiveHint' => true,
                'idempotentHint' => true,
                'openWorldHint' => false
            ]
        ];
    }
}
