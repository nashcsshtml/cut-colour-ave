<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace Elementor;

use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;

/**
 * Class AmeliaEventsListBookingButtonElementorWidget
 *
 * Extends the native Elementor Button widget so all standard button controls
 * are inherited. Event list booking filters are added in a dedicated section.
 *
 * @package AmeliaBooking\Infrastructure\WP\Elementor
 */
class AmeliaEventsListBookingButtonElementorWidget extends Widget_Button
{
    public function get_name()
    {
        return 'ameliaeventslistbookingbutton';
    }

    public function get_title()
    {
        $widgetLabel = BackendStrings::get('events_list_booking_button_gutenberg_block');

        return is_array($widgetLabel) ? $widgetLabel['title'] : BackendStrings::get('events_list_booking');
    }

    public function get_icon()
    {
        return 'amelia-logo';
    }

    public function get_categories()
    {
        return ['amelia-elementor'];
    }

    protected function register_controls()
    {
        parent::register_controls();

        $this->update_control(
            'text',
            [
                'default' => BackendStrings::get('event_book_event'),
            ]
        );

        $this->remove_control('link');
        $this->remove_control('button_css_id');

        $this->start_controls_section(
            'amelia_events_list_booking_options',
            [
                'label' => esc_html__('Amelia Events List Options', 'wpamelia'),
            ]
        );

        $this->add_control(
            'preselect',
            [
                'label' => BackendStrings::get('filter'),
                'type' => Controls_Manager::SWITCHER,
                'default' => false,
                'label_on' => BackendStrings::get('yes'),
                'label_off' => BackendStrings::get('no'),
            ]
        );

        $this->add_control(
            'event_to_show',
            [
                'label'     => BackendStrings::get('event_time_scope'),
                'type'      => Controls_Manager::SELECT,
                'condition' => ['preselect' => 'yes'],
                'default'   => 'all',
                'options'   => [
                    'all'    => BackendStrings::get('all_events'),
                    'future' => BackendStrings::get('future_events'),
                    'past'   => BackendStrings::get('past_events'),
                    'custom' => BackendStrings::get('custom_range'),
                ],
            ]
        );

        $this->add_control(
            'start_date',
            [
                'label' => BackendStrings::get('red_start_date'),
                'type' => Controls_Manager::DATE_TIME,
                'picker_options' => [
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                ],
                'condition' => [
                    'preselect' => 'yes',
                    'event_to_show' => 'custom'
                ],
            ]
        );

        $this->add_control(
            'end_date',
            [
                'label' => BackendStrings::get('red_end_date'),
                'type' => Controls_Manager::DATE_TIME,
                'picker_options' => [
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                ],
                'condition' => [
                    'preselect' => 'yes',
                    'event_to_show' => 'custom'
                ],
            ]
        );

        $this->add_control(
            'select_event',
            [
                'label' => BackendStrings::get('select_event'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => AmeliaEventsListBookingElementorWidget::amelia_elementor_get_events(),
                'condition' => ['preselect' => 'yes'],
                'placeholder' => BackendStrings::get('show_all_events')
            ]
        );

        $this->add_control(
            'select_tag',
            [
                'label' => BackendStrings::get('select_tag'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => AmeliaEventsListBookingElementorWidget::amelia_elementor_get_tags(),
                'condition' => ['preselect' => 'yes'],
                'placeholder' => BackendStrings::get('show_all_tags')
            ]
        );

        $this->add_control(
            'select_location',
            [
                'label' => BackendStrings::get('select_location'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => AmeliaEventsListBookingElementorWidget::amelia_elementor_get_locations(),
                'condition' => ['preselect' => 'yes'],
                'placeholder' => BackendStrings::get('show_all_locations')
            ]
        );

        $this->add_control(
            'show_recurring',
            [
                'label' => BackendStrings::get('recurring_event'),
                'type' => Controls_Manager::SWITCHER,
                'condition' => ['preselect' => 'yes'],
                'default' => false,
                'label_on' => BackendStrings::get('yes'),
                'label_off' => BackendStrings::get('no'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        $autoTrigger = 'amelia-events-list-booking-btn-' . substr(md5($this->get_id()), 0, 8);

        $shortcode  = '[ameliaeventslistbooking';
        $shortcode .= ' trigger="' . esc_attr($autoTrigger) . '"';
        $shortcode .= ' trigger_type="id"';
        $shortcode .= ' in_dialog=1';

        $toCsvIds = static function ($value): string {
            $vals = is_array($value) ? $value : [$value];
            $vals = array_filter(array_map('absint', $vals));

            return implode(',', $vals);
        };

        if (!empty($settings['preselect'])) {
            if (!empty($settings['select_event'])) {
                $event = $toCsvIds($settings['select_event']);
                if ($event !== '') {
                    $shortcode .= ' event="' . esc_attr($event) . '"';
                }
            } elseif (!empty($settings['event_to_show']) && $settings['event_to_show'] !== 'all') {
                if ($settings['event_to_show'] === 'custom') {
                    if (empty($settings['start_date']) || empty($settings['end_date'])) {
                        echo BackendStrings::get('notice_for_missing_dates');

                        return;
                    }

                    $shortcode .= ' range="' . esc_attr($settings['start_date'] . ' - ' . $settings['end_date']) . '"';
                } else {
                    $shortcode .= ' range="' . esc_attr($settings['event_to_show']) . '"';
                }
            }

            if (!empty($settings['select_location'])) {
                $location = $toCsvIds($settings['select_location']);
                if ($location !== '') {
                    $shortcode .= ' location="' . esc_attr($location) . '"';
                }
            }

            if (!empty($settings['select_tag'])) {
                $tags = is_array($settings['select_tag']) ? $settings['select_tag'] : [$settings['select_tag']];
                $tags = array_values(array_filter($tags));

                if (!empty($tags)) {
                    $shortcode .= ' tag="';
                    foreach ($tags as $index => $tag) {
                        $shortcode .= ($index === 0 ? '' : ',') . '{' . esc_attr($tag) . '}';
                    }
                    $shortcode .= '"';
                }
            }

            if (!empty($settings['show_recurring']) && $settings['show_recurring'] === 'yes') {
                $shortcode .= ' recurring=1';
            }
        }

        $shortcode .= ']';

        echo '<div class="amelia-events-list-booking-button-trigger">';

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

        // Keep the legacy shortcode class so existing frontend scans also pick this up.
        echo '<div class="amelia-step-booking-shortcode amelia-events-list-booking-shortcode" style="display:none">'
            . $shortcode
            . '</div>';
    }
}
