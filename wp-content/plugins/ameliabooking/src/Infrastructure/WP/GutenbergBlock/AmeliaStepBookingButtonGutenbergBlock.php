<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\GutenbergBlock;

use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;

/**
 * Class AmeliaStepBookingGutenbergBlock
 *
 * @package AmeliaBooking\Infrastructure\WP\GutenbergBlock
 */
class AmeliaStepBookingButtonGutenbergBlock extends GutenbergBlock
{
    /**
     * Register Amelia Booking block for Gutenberg
     */
    public static function registerBlockType()
    {
        // Enqueue shared icon
        parent::enqueueSharedIcon();

        wp_enqueue_script(
            'amelia_step_booking_button_gutenberg_block',
            AMELIA_URL . 'public/js/gutenberg/amelia-step-booking-button/amelia-step-booking-button-gutenberg.js',
            array('wp-blocks', 'wp-components', 'wp-element', 'wp-editor', 'wp-data', 'amelia_block_icon')
        );

        wp_localize_script(
            'amelia_step_booking_button_gutenberg_block',
            'wpAmeliaLabels',
            array_merge(
                BackendStrings::getAllStrings(),
                self::getEntitiesData()
            )
        );


        wp_enqueue_style(
            'amelia_step_booking_button_gutenberg_styles',
            AMELIA_URL . 'public/js/gutenberg/amelia-step-booking-button/amelia-gutenberg-styles.css',
            [],
            AMELIA_VERSION
        );

        register_block_type(
            'amelia/step-booking-button-gutenberg-block',
            array('editor_script' => 'amelia_step_booking_button_gutenberg_block')
        );
    }
}
