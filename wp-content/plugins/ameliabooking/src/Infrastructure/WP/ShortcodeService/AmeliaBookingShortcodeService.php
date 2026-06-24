<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\ShortcodeService;

use AmeliaBooking\Application\Services\Cache\CacheApplicationService;
use AmeliaBooking\Application\Services\Stash\StashApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\CustomField\CustomFieldRepository;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;
use AmeliaBooking\Infrastructure\WP\Translations\FrontendStrings;
use AmeliaBooking\Infrastructure\WP\Integrations\IvyForms\IvyFormsService;
use AmeliaBooking\Infrastructure\WP\Integrations\PluginInstaller;

/**
 * Class AmeliaBookingShortcodeService
 *
 * @package AmeliaBooking\Infrastructure\WP\ShortcodeService
 */
class AmeliaBookingShortcodeService
{
    public static $counter = 1000;

    /** @var Container $container */
    protected static $container = null;

    /**
     * Prepare scripts and styles
     * @throws InvalidArgumentException
     */
    public static function prepareScriptsAndStyles()
    {
        self::$container = self::$container ?: require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';

        self::$counter++;

        if (self::$counter > 1001) {
            return;
        }

        /** @var SettingsService $settingsService */
        $settingsService = self::$container->get('domain.settings.service');

        self::enqueuePaypalScript($settingsService);

        if ($settingsService->getSetting('payments', 'stripe')['enabled'] === true) {
            wp_enqueue_script('amelia_stripe_script', 'https://js.stripe.com/v3/');
        }

        if ($settingsService->getSetting('payments', 'square')['enabled'] === true) {
            if ($settingsService->getSetting('payments', 'square')['testMode'] === true) {
                wp_enqueue_script('amelia_square_js', 'https://sandbox.web.squarecdn.com/v1/square.js');
            } else {
                wp_enqueue_script('amelia_square_js', 'https://web.squarecdn.com/v1/square.js');
            }
            wp_enqueue_style(
                'amelia_google_button_style',
                'https://developers.google.com/reference/sdks/web/static/styles/code-preview.css',
                [],
                null,
                'all'
            );
        }

        if ($settingsService->getSetting('payments', 'razorpay')['enabled'] === true) {
            wp_enqueue_script('amelia_razorpay_script', 'https://checkout.razorpay.com/v1/checkout.js');
        }

        $gmapApiKey = $settingsService->getSetting('general', 'gMapApiKey');

        if ($gmapApiKey) {
            /** @var CustomFieldRepository $customFieldRepository */
            $customFieldRepository = self::$container->get('domain.customField.repository');

            $addressCustomFields = $customFieldRepository->getByFieldValue('type', 'address');

            if (count($addressCustomFields->getItems())) {
                wp_enqueue_script(
                    'amelia_google_maps_api',
                    "https://maps.googleapis.com/maps/api/js?key={$gmapApiKey}&libraries=places&loading=async"
                );
            }
        }

        $scriptId = AMELIA_DEV ? 'amelia_booking_scripts_dev_vite' : 'amelia_booking_script_index';

        if (AMELIA_DEV) {
            wp_enqueue_script(
                $scriptId,
                'http://localhost:3000/@vite/client',
                [],
                null,
                false
            );

            wp_enqueue_script(
                'amelia_booking_scripts_dev_main',
                'http://localhost:3000/src/assets/js/public/public.js',
                [],
                null,
                true
            );
        } else {
            wp_enqueue_script(
                $scriptId,
                AMELIA_URL . 'v3/public/assets/public.js',
                [],
                AMELIA_VERSION,
                true
            );

            // Vite bundles all Vue/Element/Maz CSS into one file (cssCodeSplit: false).
            // Dynamic chunk imports do not inject this stylesheet in WordPress; enqueue explicitly.
            wp_enqueue_style(
                'amelia_booking_v3_style',
                AMELIA_URL . 'v3/public/assets/style.css',
                [],
                AMELIA_VERSION
            );
        }

        $ameliaLocale = apply_filters('amelia_modify_locale_filter', AMELIA_LOCALE) ?: AMELIA_LOCALE;

        wp_localize_script(
            $scriptId,
            'localeLanguage',
            [$ameliaLocale]
        );

        wp_localize_script(
            $scriptId,
            'wpAmeliaSettings',
            $settingsService->getFrontendSettings()
        );

        // Strings Localization
        wp_localize_script(
            $scriptId,
            'wpAmeliaLabels',
            FrontendStrings::getAllStrings()
        );

        wp_localize_script(
            $scriptId,
            'wpAmeliaTimeZone',
            [DateTimeService::getTimeZone()->getName()]
        );

        $ameliaUrl = AMELIA_URL;

        $ameliaActionUrl = AMELIA_ACTION_URL;

        if (strpos($ameliaUrl, 'http://') === 0) {
            $ameliaUrl = substr($ameliaUrl, strpos(substr($ameliaUrl, 7), '/') + 7);

            $ameliaActionUrl = substr($ameliaActionUrl, strpos(substr($ameliaActionUrl, 7), '/') + 7);
        } elseif (strpos($ameliaUrl, 'https://') === 0) {
            $ameliaUrl = substr($ameliaUrl, strpos(substr($ameliaUrl, 8), '/') + 8);

            $ameliaActionUrl = substr($ameliaActionUrl, strpos(substr($ameliaActionUrl, 8), '/') + 8);
        }

        wp_localize_script(
            $scriptId,
            'wpAmeliaUrls',
            [
                'wpAmeliaUseUploadsAmeliaPath' => AMELIA_UPLOADS_FILES_PATH_USE,
                'wpAmeliaPluginURL'            => $ameliaUrl,
                'wpAmeliaPluginAjaxURL'        => $ameliaActionUrl,
            ]
        );

        if (!empty($_GET['ameliaCache']) || !empty($_GET['ameliaWcCache'])) {
            /** @var CacheApplicationService $cacheAS */
            $cacheAS = self::$container->get('application.cache.service');

            try {
                $cacheData = !empty($_GET['ameliaCache']) ?
                    $cacheAS->getCacheByName($_GET['ameliaCache']) : $cacheAS->getWcCacheByName($_GET['ameliaWcCache']);

                wp_localize_script(
                    $scriptId,
                    'ameliaCache',
                    [$cacheData ? str_replace('&quot;', '\\"', json_encode($cacheData)) : '']
                );
            } catch (QueryExecutionException $e) {
            }
        }

        if ($settingsService->getSetting('activation', 'stash')) {
            /** @var StashApplicationService $stashAS */
            $stashAS = self::$container->get('application.stash.service');

            wp_localize_script(
                $scriptId,
                'ameliaEntities',
                $stashAS->getStash()
            );
        }

        do_action('amelia_scripts_loaded');
    }

    /**
     * @param string $tag
     * @param string $handle
     * @param string $src
     *
     * @return string
     */
    public static function prepareScripts($tag, $handle, $src)
    {
        switch ($handle) {
            case ('amelia_booking_scripts_dev_vite'):
            case ('amelia_booking_scripts_dev_main'):
            case ('amelia_dev_vite_client'):
            case ('amelia_dev_main_script'):
            case ('amelia_prod_main_script'):
                return "<script type='module' src='{$src}'></script>";

            case ('amelia_booking_script_index'):
                $settingsService = new SettingsService(new SettingsStorage());

                if ($settingsService->getSetting('activation', 'v3RelativePath')) {
                    $customUrl = $settingsService->getSetting('activation', 'customUrl');

                    $position = strpos($src, $customUrl['pluginPath'] . 'v3/public/assets/public.');

                    if ($position !== false) {
                        $src = substr($src, $position);
                    }
                } elseif (strpos($src, 'http://') === 0) {
                    $src = substr($src, strpos(substr($src, 7), '/') + 7);
                } elseif (strpos($src, 'https://') === 0) {
                    $src = substr($src, strpos(substr($src, 8), '/') + 8);
                }

                $asyncLoading = $settingsService->getSetting('activation', 'v3AsyncLoading') ?
                    'async' : '';

                return "<script type='module' {$asyncLoading} crossorigin src='{$src}'></script>";

            case ('amelia_booking_script_vendor'):
                return "<link rel='modulepreload' href='{$src}'>";

            default:
                return $tag;
        }
    }

    /**
     * @param string $tag
     * @param string $handle
     * @param string $href
     *
     * @return string
     */
    public static function prepareStyles($tag, $handle, $href)
    {
        switch ($handle) {
            case ('amelia_booking_v3_style'):
            case ('amelia_booking_style_index'):
            case ('amelia_booking_style_vendor'):
                $settingsService = new SettingsService(new SettingsStorage());

                if ($settingsService->getSetting('activation', 'v3RelativePath')) {
                    $customUrl = $settingsService->getSetting('activation', 'customUrl');

                    $position = strpos($href, $customUrl['pluginPath'] . 'v3/public/assets/style.');

                    if ($position !== false) {
                        $href = substr($href, $position);
                    }
                }

                return "<link rel='stylesheet' href='{$href}'>";

            default:
                return $tag;
        }
    }

    /**
     * Enqueue PayPal script
     */
    protected static function enqueuePaypalScript(SettingsService $settingsService): void
    {
        if ($settingsService->getSetting('payments', 'payPal')['enabled'] === true) {
            $payPalSettings = $settingsService->getSetting('payments', 'payPal');
            $payPalClientId  = $payPalSettings['sandboxMode']
                ? ($payPalSettings['testApiClientId'] ?? '')
                : ($payPalSettings['liveApiClientId'] ?? '');
            $payPalCurrency  = $settingsService->getSetting('payments', 'currency') ?: 'USD';
            wp_enqueue_script(
                'amelia_paypal_script',
                'https://www.paypal.com/sdk/js?client-id=' . urlencode($payPalClientId)
                    . '&currency=' . urlencode($payPalCurrency)
                    . '&components=buttons'
                    . '&disable-funding=venmo,paylater,card,sepa,bancontact,blik,eps,giropay,ideal,mercadopago,mybank,p24,sofort',
                [],
                null,
                false
            );
        }
    }

    /**
     * @param string $viewPath
     * @param array $params
     * @return string
     */
    protected static function renderView(string $viewPath, array $params): string
    {
        if (!empty($_GET['ameliaWcCache']) || !empty($_GET['ameliaCache'])) {
            $params['ivy'] = '';
        }

        self::prepareScriptsAndStyles();

        /** @var SettingsService $settingsService */
        $settingsService = self::$container->get('domain.settings.service');

        $html = !empty($params['ivy']) && $settingsService->isFeatureEnabled('ivy') && PluginInstaller::isPluginActive('ivyforms')
            ? IvyFormsService::shortcode($params['ivy'])
            : '';

        if (empty($html)) {
            $params['ivy'] = '';
        }

        ob_start();

        include AMELIA_PATH . '/view/frontend/' . $viewPath;

        $html .= ob_get_contents();

        ob_end_clean();

        return $html;
    }
}
