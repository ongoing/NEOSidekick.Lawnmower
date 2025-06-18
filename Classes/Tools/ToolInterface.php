<?php

namespace NEOSidekick\Lawnmower\Tools;

interface ToolInterface
{
    public function call(array $arguments): array;
    public static function getDefinition(): array;
}
