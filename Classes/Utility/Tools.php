<?php

namespace NEOSidekick\Lawnmower\Utility;

class Tools
{
    public static function convertClassNameToToolName(string $className): string
    {
        // Remove the namespace part
        $classNameParts = explode('\\', $className);
        $toolName = end($classNameParts);

        // Remove '_Original' and 'Tool' suffixes
        return str_replace(array('_Original', 'Tool'), '', $toolName);
    }
}
