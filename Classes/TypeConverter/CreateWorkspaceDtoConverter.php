<?php

namespace NEOSidekick\Lawnmower\TypeConverter;

use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\Exception\TypeConverterException;
use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;
use NEOSidekick\Lawnmower\Dto\CreateWorkspaceDto;

class CreateWorkspaceDtoConverter extends AbstractTypeConverter
{
    protected $priority = 100;

    protected $sourceTypes = [
        'array',
    ];

    protected $targetType = CreateWorkspaceDto::class;

    /**
     * @Flow\Inject()
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @inheritDoc
     */
    public function convertFrom(
        $source,
        $targetType,
        array $convertedChildProperties = [],
        ?PropertyMappingConfigurationInterface $configuration = null
    ): CreateWorkspaceDto {
        $liveWorkspace = $this->workspaceRepository->findByIdentifier('live');

        if ($liveWorkspace === null) {
            throw new TypeConverterException('Live workspace not found', 1747565389499);
        }

        ['workspaceName' => $workspaceName] = $source;

        if (empty($workspaceName)) {
            throw new TypeConverterException('Workspace name is required', 1747565387209);
        }

        $existingWorkspace = $this->workspaceRepository->findByIdentifier($workspaceName);
        if ($existingWorkspace !== null) {
            throw new TypeConverterException('Workspace with the same name already exists', 1747565387209);
        }

        return new CreateWorkspaceDto(
            $workspaceName,
            $liveWorkspace
        );
    }
}
