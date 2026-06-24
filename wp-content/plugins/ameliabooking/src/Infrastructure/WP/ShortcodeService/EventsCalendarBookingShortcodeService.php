<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\ShortcodeService;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\WP\Integrations\PluginInstaller;

/**
 * Class EventsCalendarBookingShortcodeService
 *
 * @package AmeliaBooking\Infrastructure\WP\ShortcodeService
 */
class EventsCalendarBookingShortcodeService extends AmeliaBookingShortcodeService
{
    /**
     * @param array $params
     * @return string
     * @throws InvalidArgumentException
     */
    public static function shortcodeHandler($params)
    {
        self::$container = self::$container ?: require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';

        /** @var SettingsService $settingsService */
        $settingsService = self::$container->get('domain.settings.service');

        if (!empty($params['ivy']) && (!$settingsService->isFeatureEnabled('ivy') || !PluginInstaller::isPluginActive('ivyforms'))) {
            $params['ivy'] = '';
        }

        $params = shortcode_atts(
            [
                'ivy'           => '',
                'trigger'       => '',
                'trigger_type'  => '',
                'in_dialog'     => '',
                'counter'       => self::$counter,
                'event'         => null,
                'recurring'     => null,
                'employee'      => null,
                'tag'           => null,
                'today'         => null,
                'location'      => null,
            ],
            $params
        );

        if (!empty($params['tag'])) {
            $params['tag'] = htmlspecialchars_decode($params['tag']);
        }

        return self::renderView('events-calendar-booking.inc.php', $params);
    }
}
