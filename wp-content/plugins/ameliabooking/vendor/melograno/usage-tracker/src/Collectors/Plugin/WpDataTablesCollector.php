<?php

declare(strict_types=1);

namespace AmeliaVendor\Melograno\UsageTracker\Collectors\Plugin;

use AmeliaVendor\Melograno\UsageTracker\Collectors\BaseCollector;

class WpDataTablesCollector extends BaseCollector
{
    /** @var list<string> */
    private const ALLOWED_TABLES = [
        'wpdatatables',
        'wpdatacharts',
    ];

    /** @var array<string, string> */
    private const LICENSE_TIER_MAP = [
        'free' => 'free',
        'starter' => 'starter',
        'standard' => 'standard',
        'pro' => 'pro',
        'developer' => 'developer',
    ];

    /** @var array<string, string> */
    private const CONTENT_TYPE_TABLE_MAP = [
        'table' => 'wpdatatables',
        'chart' => 'wpdatacharts',
    ];

    public function getPluginSlug(): string
    {
        return 'wpdatatables';
    }

    public function getConsentOptionName(): string
    {
        return 'wpdatatables_usage_tracking_consent';
    }

    /**
     * Maps wpDataTables licence tier values to telemetry license slugs.
     */
    public static function normalizeLicenseTier(?string $licenseTier): ?string
    {
        if ($licenseTier === null) {
            return null;
        }

        $key = strtolower(trim($licenseTier));

        return self::LICENSE_TIER_MAP[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function pluginPayload(): array
    {
        $data = [
            'plugin_version' => defined('WDT_CURRENT_VERSION') ? WDT_CURRENT_VERSION : null,
        ];

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (function_exists('get_plugin_data') && defined('WDT_ROOT_PATH')) {
            $pluginData = get_plugin_data(WDT_ROOT_PATH . 'wpdatatables.php', false, false);
            if (!empty($pluginData['Version'])) {
                $data['plugin_version'] = $pluginData['Version'];
            }
        }

        $rawTier = $this->resolveLicenseTier();
        $data['license'] = self::normalizeLicenseTier($rawTier);

        $data['tables_count'] = $this->resolveContentCount('table');
        $data['charts_count'] = $this->resolveContentCount('chart');

        $data['first_content_created_at'] = $this->resolveFirstContentCreatedAt();

        $data = array_filter($data, static function ($value) {
            return $value !== null;
        });

        return $data;
    }

    protected function resolveContentCount(string $contentType): int
    {
        if (class_exists('WDTTools')) {
            return (int) \WDTTools::getTablesCount($contentType);
        }

        $table = self::CONTENT_TYPE_TABLE_MAP[$contentType] ?? '';

        return $this->countPluginRows($table);
    }

    protected function resolveFirstContentCreatedAt(): ?string
    {
        return null;
    }

    protected function resolveLicenseTier(): ?string
    {
        $this->ensureTierDetectionLoaded();

        if (!function_exists('wdtmcp_detect_integrations') || !function_exists('wdtmcp_detect_tier')) {
            return null;
        }

        return wdtmcp_detect_tier(wdtmcp_detect_integrations());
    }

    private function ensureTierDetectionLoaded(): void
    {
        if (function_exists('wdtmcp_detect_tier')) {
            return;
        }

        if (!defined('WDT_ROOT_PATH')) {
            return;
        }

        $path = WDT_ROOT_PATH . 'Infrastructure/WP/MCP/Abilities/get-system-info.php';

        if (is_file($path)) {
            require_once $path;
        }
    }

    private function countPluginRows(string $table): int
    {
        if (!in_array($table, self::ALLOWED_TABLES, true)) {
            return 0;
        }

        global $wpdb;

        if (!isset($wpdb)) {
            return 0;
        }

        $count = $wpdb->get_var('SELECT COUNT(*) FROM ' . $wpdb->prefix . $table);

        return $count === null ? 0 : (int) $count;
    }
}
