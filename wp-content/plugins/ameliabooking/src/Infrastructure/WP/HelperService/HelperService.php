<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\HelperService;

use AmeliaBooking\Infrastructure\WP\Integrations\WooCommerce\WooCommerceService;

/**
 * Class HelperService
 *
 * @package AmeliaBooking\Infrastructure\WP\HelperService
 */
class HelperService
{
    public static $jsVars = [];

    /**
     * Determine whether the site is using SSL.
     *
     * Supports setups behind reverse proxies where SSL is terminated
     * before traffic reaches WordPress/PHP.
     */
    public static function isSSL(): bool
    {
        if (function_exists('is_ssl') && is_ssl()) {
            return true;
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $forwardedProtos = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO']);
            if (in_array('https', $forwardedProtos)) {
                return true;
            }
        }

        if (
            function_exists('get_option') &&
            'https' === parse_url((string)get_option('siteurl'), PHP_URL_SCHEME)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Helper method to add PHP vars to JS vars
     *
     * @param $varName
     * @param $phpVar
     */
    public static function exportJSVar($varName, $phpVar)
    {
        self::$jsVars[$varName] = $phpVar;
    }

    /**
     * Helper method to print PHP vars to JS vars
     */
    public static function printJSVars()
    {
        if (!empty(self::$jsVars)) {
            $jsBlock = '<script type="text/javascript">';
            foreach (self::$jsVars as $varName => $jsVar) {
                $jsBlock .= "var {$varName} = " . json_encode($jsVar) . ';';
            }
            $jsBlock .= '</script>';
            echo $jsBlock;
        }
    }

    /**
     * @param int $orderId
     *
     * @return string|null
     */
    public static function getWooCommerceOrderUrl($orderId)
    {
        return get_edit_post_link($orderId, '');
    }

    /**
     * @param int $orderId
     *
     * @return array
     */
    public static function getWooCommerceOrderItemAmountValues($orderId)
    {
        $order = wc_get_order($orderId);

        $prices = [];

        if ($order) {
            foreach ($order->get_items() as $itemId => $orderItem) {
                $data = wc_get_order_item_meta($itemId, WooCommerceService::AMELIA);

                if ($data && is_array($data)) {
                    $prices[$itemId] = [
                        'coupon' => (float)round($orderItem->get_subtotal() - $orderItem->get_total(), 2),
                        'tax'    => !isset($data['taxIncluded']) || !$data['taxIncluded'] ?
                            (float)$orderItem->get_total_tax() : 0,
                    ];
                }
            }
        }

        return $prices;
    }
}
