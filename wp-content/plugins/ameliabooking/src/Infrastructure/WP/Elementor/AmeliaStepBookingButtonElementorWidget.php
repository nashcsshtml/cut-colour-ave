<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace Elementor;

use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;

/**
 * Class AmeliaStepBookingButtonElementorWidget
 *
 * Extends the native Elementor Button widget so all standard button controls
 * (Type, Text, Link, Size, Icon, Icon Position, Icon Spacing and the full
 * Style tab) are inherited for free.  The Amelia booking options (preselect
 * parameters, layout, manual trigger, in-dialog) are added in a dedicated
 * section after the parent controls.
 *
 * @package AmeliaBooking\Infrastructure\WP\Elementor
 */
class AmeliaStepBookingButtonElementorWidget extends Widget_Button
{
    // -------------------------------------------------------------------------
    // Identity
    // -------------------------------------------------------------------------

    public function get_name()
    {
        return 'stepbookingbutton';
    }

    public function get_title()
    {
        $widgetLabel = BackendStrings::get('step_booking_button_gutenberg_block');

        return is_array($widgetLabel) ? $widgetLabel['title'] : BackendStrings::get('step_booking');
    }

    public function get_icon()
    {
        return 'amelia-logo';
    }

    public function get_categories()
    {
        return ['amelia-elementor'];
    }

    // -------------------------------------------------------------------------
    // Controls
    // -------------------------------------------------------------------------

    protected function register_controls()
    {
        // ── All standard Elementor Button widget controls ──────────────────────
        // Type, Text, Size, Selected Icon, Icon Position, Icon Spacing
        // + full Style tab (Typography, colours, Border, Radius, Shadow, Padding)
        parent::register_controls();

        // Set default button text to "Book Appointment"
        $this->update_control(
            'text',
            [
                'default' => BackendStrings::get('book_appointment'),
            ]
        );

        // Remove controls that are irrelevant for the Amelia booking button:
        // the booking is opened via an Amelia shortcode trigger, not a URL link,
        // and the trigger ID is managed internally so a manual Button ID is
        // confusing and unused.
        $this->remove_control('link');
        $this->remove_control('button_css_id');

        // ── Amelia booking options ─────────────────────────────────────────────
        $controls_data = AmeliaStepBookingElementorWidget::amelia_elementor_get_data();

        $this->start_controls_section(
            'amelia_booking_options',
            [
                'label' => esc_html__('Amelia Booking Options', 'wpamelia'),
            ]
        );

        // Preselect toggle
        $this->add_control(
            'preselect',
            [
                'label'     => BackendStrings::get('filter'),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => false,
                'label_on'  => BackendStrings::get('yes'),
                'label_off' => BackendStrings::get('no'),
            ]
        );

        // Category
        if ($controls_data['categories'] && sizeof($controls_data['categories']) > 1) {
            $this->add_control(
                'select_category',
                [
                    'label'       => BackendStrings::get('select_category'),
                    'type'        => Controls_Manager::SELECT2,
                    'multiple'    => true,
                    'options'     => $controls_data['categories'],
                    'condition'   => ['preselect' => 'yes'],
                    'placeholder' => BackendStrings::get('show_all_categories'),
                ]
            );
        }

        // Service
        if ($controls_data['services'] && sizeof($controls_data['services']) > 1) {
            $this->add_control(
                'select_service',
                [
                    'label'       => BackendStrings::get('select_service'),
                    'type'        => Controls_Manager::SELECT2,
                    'multiple'    => true,
                    'options'     => $controls_data['services'],
                    'condition'   => ['preselect' => 'yes'],
                    'placeholder' => BackendStrings::get('show_all_services'),
                ]
            );
        }

        // Employee
        if ($controls_data['employees'] && sizeof($controls_data['employees']) > 1) {
            $this->add_control(
                'select_employee',
                [
                    'label'       => BackendStrings::get('select_employee'),
                    'type'        => Controls_Manager::SELECT2,
                    'multiple'    => true,
                    'options'     => $controls_data['employees'],
                    'condition'   => ['preselect' => 'yes'],
                    'placeholder' => BackendStrings::get('show_all_employees'),
                ]
            );
        }

        // Location
        if ($controls_data['locations'] && sizeof($controls_data['locations']) > 1) {
            $this->add_control(
                'select_location',
                [
                    'label'       => BackendStrings::get('select_location'),
                    'type'        => Controls_Manager::SELECT2,
                    'multiple'    => true,
                    'options'     => $controls_data['locations'],
                    'condition'   => ['preselect' => 'yes'],
                    'placeholder' => BackendStrings::get('show_all_locations'),
                ]
            );
        }

        // Package
        if (!empty($controls_data['packages'])) {
            $this->add_control(
                'select_package',
                [
                    'label'       => BackendStrings::get('select_package'),
                    'type'        => Controls_Manager::SELECT2,
                    'multiple'    => true,
                    'options'     => $controls_data['packages'],
                    'condition'   => ['preselect' => 'yes'],
                    'placeholder' => BackendStrings::get('show_all_packages'),
                ]
            );
        }

        // Show
        if (!empty($controls_data['show'])) {
            $this->add_control(
                'select_show',
                [
                    'label'     => BackendStrings::get('show_all'),
                    'type'      => Controls_Manager::SELECT,
                    'options'   => $controls_data['show'],
                    'condition' => ['preselect' => 'yes'],
                    'default'   => '',
                ]
            );
        }

        // Layout
        $this->add_control(
            'layout',
            [
                'label'       => BackendStrings::get('layout_select_label'),
                'type'        => Controls_Manager::SELECT,
                'description' => BackendStrings::get('layout_description'),
                'options'     => $controls_data['layout_options'],
                'default'     => '1',
            ]
        );

        $this->end_controls_section();
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        // ── Build trigger value ────────────────────────────────────────────────
        $autoTrigger   = 'amelia-step-booking-btn-' . substr(md5($this->get_id()), 0, 8);

        // ── Build shortcode ────────────────────────────────────────────────────
        $shortcode  = '[ameliastepbooking';
        $shortcode .= ' trigger=' . $autoTrigger;
        $shortcode .= ' trigger_type=id';
        $shortcode .= ' in_dialog=1';

        $toCsvIds = static function ($value): string {
            $vals = is_array($value) ? $value : [$value];
            $vals = array_filter(array_map('absint', $vals));
            return implode(',', $vals);
        };

        if (!empty($settings['preselect'])) {
            if (!empty($settings['select_show'])) {
                $shortcode .= ' show=' . sanitize_key((string) $settings['select_show']);
            }

            if (!empty($settings['select_service'])) {
                $service = $toCsvIds($settings['select_service']);
                if ($service !== '') {
                    $shortcode .= ' service=' . $service;
                }
            } elseif (!empty($settings['select_category'])) {
                $category = $toCsvIds($settings['select_category']);
                if ($category !== '') {
                    $shortcode .= ' category=' . $category;
                }
            }

            if (!empty($settings['select_employee'])) {
                $employee = $toCsvIds($settings['select_employee']);
                if ($employee !== '') {
                    $shortcode .= ' employee=' . $employee;
                }
            }

            if (!empty($settings['select_location'])) {
                $location = $toCsvIds($settings['select_location']);
                if ($location !== '') {
                    $shortcode .= ' location=' . $location;
                }
            }

            if (!empty($settings['select_package'])) {
                $package = $toCsvIds($settings['select_package']);
                if ($package !== '') {
                    $shortcode .= ' package=' . $package;
                }
            }
        }

        if (!empty($settings['layout'])) {
            $shortcode .= ' layout=' . absint($settings['layout']);
        }

        $shortcode .= ']';

        // ── Trigger wrapper ────────────────────────────────────────────────────
        echo '<div class="amelia-step-booking-button-trigger">';

        // ── Capture parent button output and inject the ID ────────────────────
        ob_start();
        parent::render();
        $button_html = ob_get_clean();

        // Remove any existing id attribute before injecting the trigger ID
        $button_html = preg_replace(
            '/<a\s+([^>]*?)\s*id="[^"]*"\s*([^>]*?)class="([^"]*elementor-button[^"]*)"/',
            '<a $1$2class="$3"',
            $button_html
        );

        // Inject trigger ID and enforce cursor pointer style on the button link.
        $button_html = preg_replace_callback(
            '/<a\s+([^>]*?)class="([^"]*elementor-button[^"]*)"([^>]*)>/',
            static function ($matches) use ($autoTrigger) {
                $beforeClass = $matches[1];
                $afterClass  = $matches[3];

                $appendCursor = static function ($styleValue) {
                    if (preg_match('/(^|;)\s*cursor\s*:/i', $styleValue)) {
                        return $styleValue;
                    }

                    $styleValue = rtrim($styleValue);
                    if ($styleValue !== '' && substr($styleValue, -1) !== ';') {
                        $styleValue .= ';';
                    }

                    return $styleValue . ' cursor: pointer;';
                };

                $styleUpdated = false;

                $beforeClass = preg_replace_callback(
                    '/\sstyle="([^"]*)"/i',
                    static function ($styleMatch) use (&$styleUpdated, $appendCursor) {
                        $styleUpdated = true;
                        return ' style="' . esc_attr($appendCursor($styleMatch[1])) . '"';
                    },
                    $beforeClass,
                    1
                );

                if (!$styleUpdated) {
                    $afterClass = preg_replace_callback(
                        '/\sstyle="([^"]*)"/i',
                        static function ($styleMatch) use (&$styleUpdated, $appendCursor) {
                            $styleUpdated = true;
                            return ' style="' . esc_attr($appendCursor($styleMatch[1])) . '"';
                        },
                        $afterClass,
                        1
                    );
                }

                if (!$styleUpdated) {
                    $afterClass .= ' style="cursor: pointer;"';
                }

                return '<a ' . $beforeClass
                    . 'class="' . esc_attr($matches[2]) . '"'
                    . ' id="' . esc_attr($autoTrigger) . '"'
                    . $afterClass
                    . '>';
            },
            $button_html,
            1
        );

        echo $button_html;

        echo '</div>';

        // ── Hidden shortcode consumed by Amelia frontend JS ───────────────────
        echo '<div class="amelia-step-booking-shortcode" style="display:none">'
            . esc_html($shortcode)
            . '</div>';
    }
}
