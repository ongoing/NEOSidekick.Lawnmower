<?php

namespace NEOSidekick\ContentRepositoryWebhooks\Tools;

use Neos\Flow\Property\PropertyMappingConfiguration;
use Neos\Flow\Property\TypeConverter\ObjectConverter;
use Neos\Neos\TypeConverter\NodeConverter;
use NEOSidekick\ContentRepositoryWebhooks\Dto\UpdateNodeDto;
use NEOSidekick\ContentRepositoryWebhooks\TypeConverter\UpdateNodeDtoConverter;
use NEOSidekick\ContentRepositoryWebhooks\Utility\Tools;

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
            'name' => Tools::convertClassNameToToolName(self::class),
            'description' => 'A tool to update properties of a node in the content repository',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'nodeContextPath' => [
                        'type' => 'object',
                        'description' => 'The identifier of the node to update',
                        'properties' => [
                            'nodePath' => [
                                'type' => 'string',
                                'description' => 'The path of the node in the content repository'
                            ],
                            'workspaceName' => [
                                'type' => 'string',
                                'description' => 'The name of the workspace where the node resides'
                            ],
                            'dimensions' => [
                                'type' => 'object',
                                'description' => 'The dimensions of the node, e.g., language, country, etc.'
                            ]
                        ],
                        'required' => ['nodePath', 'workspaceName', 'dimensions']
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
