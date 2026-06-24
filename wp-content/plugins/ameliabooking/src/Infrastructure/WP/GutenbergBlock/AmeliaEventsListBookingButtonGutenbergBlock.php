<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\GutenbergBlock;

use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;
use AmeliaBooking\Infrastructure\Licence;

/**
 * Class AmeliaEventsListBookingButtonGutenbergBlock
 *
 * @package AmeliaBooking\Infrastructure\WP\GutenbergBlock
 */
class AmeliaEventsListBookingButtonGutenbergBlock extends GutenbergBlock
{
    /**
     * Register Amelia Events List Booking Button block for Gutenberg
     */
    public static function registerBlockType()
    {
        parent::enqueueSharedIcon();

        wp_enqueue_script(
            'amelia_events_list_booking_button_gutenberg_block',
            AMELIA_URL . 'public/js/gutenberg/amelia-events-list-booking-button/amelia-events-list-booking-button-gutenberg.js',
            array(
                'wp-blocks',
                'wp-components',
                'wp-element',
                'wp-block-editor',
                'wp-editor',
                'wp-data',
                'wp-compose',
                'amelia_block_icon',
            ),
            AMELIA_VERSION
        );

        wp_localize_script(
            'amelia_events_list_booking_button_gutenberg_block',
            'wpAmeliaLabels',
            array_merge(
                BackendStrings::getAllStrings(),
                self::getEntitiesData(),
                array('isLite' => !Licence\Licence::isPremium())
            )
        );

        wp_enqueue_style(
            'amelia_events_list_booking_button_gutenberg_styles',
            AMELIA_URL . 'public/js/gutenberg/amelia-events-list-booking-button/amelia-gutenberg-styles.css',
            array(),
            AMELIA_VERSION
        );

        register_block_type(
            'amelia/events-list-booking-button-gutenberg-block',
            array('editor_script' => 'amelia_events_list_booking_button_gutenberg_block')
        );
    }
}
