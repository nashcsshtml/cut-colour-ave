<?php

namespace AmeliaBooking\Infrastructure\WP\Integrations;

use Plugin_Upgrader;
use WP_Ajax_Upgrader_Skin;
use WP_Error;

/**
 * Plugin Installer Service
 *
 * Handles the installation and activation of WordPress plugins
 */
class PluginInstaller
{
    /**
     * Map of allowed plugins to install
     *
     * @var array<string, string>
     */
    private static array $allowedPlugins = [
        'ivyforms' => 'ivyforms/ivyforms.php',
    ];

    /**
     * Check if plugin is allowed to be installed
     *
     * @param string $pluginSlug
     *
     * @return bool
     */
    public static function isPluginAllowed(string $pluginSlug): bool
    {
        return isset(self::$allowedPlugins[$pluginSlug]);
    }

    /**
     * Get plugin file path
     *
     * @param string $pluginSlug
     *
     * @return string|null
     */
    public static function getPluginFile(string $pluginSlug): ?string
    {
        return self::$allowedPlugins[$pluginSlug] ?? null;
    }

    /**
     * Check if plugin is already installed
     *
     * @param string $pluginSlug
     *
     * @return bool
     */
    public static function isPluginInstalled(string $pluginSlug): bool
    {
        $pluginFile = self::getPluginFile($pluginSlug);

        return $pluginFile !== null && file_exists(WP_PLUGIN_DIR . '/' . $pluginFile);
    }

    /**
     * Check if plugin is active
     *
     * @param string $pluginSlug
     *
     * @return bool
     */
    public static function isPluginActive(string $pluginSlug): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginFile = self::getPluginFile($pluginSlug);

        return $pluginFile !== null && is_plugin_active($pluginFile);
    }

    /**
     * Activate a plugin
     *
     * @param string $pluginSlug
     *
     * @return array
     */
    public static function activatePlugin(string $pluginSlug)
    {
        $activated = activate_plugin(self::getPluginFile($pluginSlug) ?? '');

        if (is_wp_error($activated)) {
            return [
                'success' => false,
                'message' => 'Plugin failed to activate: ' . $activated->get_error_message(),
            ];
        }

        return [
            'success' => true,
            'message' => 'Plugin activated successfully',
        ];
    }

    /**
     * Get plugin information from WordPress.org
     *
     * @param string $pluginSlug
     *
     * @return object|WP_Error
     */
    public static function getPluginInfo(string $pluginSlug)
    {
        if (!function_exists('plugins_api')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        return plugins_api('plugin_information', [
            'slug' => $pluginSlug,
            'fields' => [
                'short_description' => false,
                'sections'          => false,
                'requires'          => false,
                'rating'            => false,
                'ratings'           => false,
                'downloaded'        => false,
                'download_link'     => true,
                'last_updated'      => false,
                'added'             => false,
                'tags'              => false,
                'compatibility'     => false,
                'homepage'          => false,
                'donate_link'       => false,
            ],
        ]);
    }

    /**
     * Install plugin from download URL
     *
     * @param string $downloadUrl
     *
     * @return bool|WP_Error
     */
    public static function installPlugin(string $downloadUrl)
    {
        self::loadRequiredFiles();

        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());

        return $upgrader->install($downloadUrl);
    }

    /**
     * Install and activate plugin
     *
     * @param string $pluginSlug
     *
     * @return array
     */
    public static function installAndActivatePlugin(string $pluginSlug): array
    {
        if (!self::isPluginAllowed($pluginSlug)) {
            return [
                'success' => false,
                'message' => 'Plugin is not allowed',
            ];
        }

        if (!current_user_can('install_plugins')) {
            return [
                'success' => false,
                'message' => 'Insufficient permissions to install plugins',
            ];
        }

        // Get plugin info from WordPress.org
        $api = self::getPluginInfo($pluginSlug);

        if (is_wp_error($api)) {
            return [
                'success' => false,
                'message' => 'Failed to get plugin information: ' . $api->get_error_message(),
            ];
        }

        // Install the plugin
        $installed = self::installPlugin($api->download_link);

        if (is_wp_error($installed)) {
            return [
                'success' => false,
                'message' => 'Failed to install plugin: ' . $installed->get_error_message(),
            ];
        }

        if (!$installed) {
            return [
                'success' => false,
                'message' => 'Failed to install plugin',
            ];
        }

        // Activate the plugin
        $activated = self::activatePlugin($pluginSlug);

        if (!$activated['success']) {
            return [
                'success' => false,
                'message' => 'Plugin installed but failed to activate: ' . $activated['message'],
            ];
        }

        return [
            'success' => true,
            'message' => 'Plugin installed and activated successfully',
        ];
    }

    /**
     * Load required WordPress files for plugin installation
     *
     * @return void
     */
    private static function loadRequiredFiles(): void
    {
        // phpcs:disable
        if (file_exists(ABSPATH . 'wp-admin/includes/file.php')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (file_exists(ABSPATH . 'wp-admin/includes/misc.php')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }

        if (file_exists(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        if (file_exists(ABSPATH . 'wp-admin/includes/plugin-install.php')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }
        // phpcs:enable
    }
}
