<?php

namespace Divi5Amelia;

/**
 * Helpers for merging module.json with shared-module.json for Divi 5 registration.
 */
class ModuleMetadata
{
    /**
     * Merge shared attributes into module registration args.
     *
     * @param string $moduleFolder Absolute path to the module directory containing module.json.
     * @param array  $args         Extra arguments passed to ModuleRegistration::register_module().
     *
     * @return array Registration args with merged attributes.
     */
    public static function getRegistrationArgs(string $moduleFolder, array $args = []): array
    {
        $moduleFolder = untrailingslashit($moduleFolder);
        $moduleFile   = $moduleFolder . '/module.json';
        $sharedFile   = dirname($moduleFolder, 2) . '/shared-module.json';

        if (! is_readable($moduleFile)) {
            return $args;
        }

        $moduleMetadata = json_decode(file_get_contents($moduleFile), true);

        if (! is_array($moduleMetadata)) {
            return $args;
        }

        $sharedMetadata = [];

        if (is_readable($sharedFile)) {
            $decoded = json_decode(file_get_contents($sharedFile), true);
            if (is_array($decoded)) {
                $sharedMetadata = $decoded;
            }
        }

        $moduleAttributes  = $moduleMetadata['attributes'] ?? [];
        $sharedAttributes  = $sharedMetadata['attributes'] ?? [];
        $mergedAttributes  = array_merge($sharedAttributes, $moduleAttributes);

        return array_merge($args, ['attributes' => $mergedAttributes]);
    }
}
