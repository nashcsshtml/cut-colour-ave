<?php

declare(strict_types=1);

namespace AmeliaVendor\Melograno\UsageTracker\Collectors\Plugin;

use AmeliaVendor\Melograno\UsageTracker\Collectors\BaseCollector;

class IvyFormsCollector extends BaseCollector
{
    public function getPluginSlug(): string
    {
        return 'ivyforms';
    }

    public function getConsentOptionName(): string
    {
        return 'ivyforms_usage_tracking_consent';
    }

    /**
     * @return array<string, mixed>
     */
    protected function pluginPayload(): array
    {
        $data = [
            'plugin_version' => defined('IVYFORMS_VERSION') ? IVYFORMS_VERSION : null,
        ];

        $data = array_filter($data, static function ($value) {
            return $value !== null;
        });

        return $data;
    }
}
