<?php

namespace Divi5Amelia;

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use AmeliaBooking\Infrastructure\WP\Translations\LiteBackendStrings;
use WP_Block;

/**
 * Class that handles "Amelia Events List Booking Button" module output in frontend.
 */
class AmeliaEventsListBookingButtonModule implements DependencyInterface
{
    use AmeliaBookingButtonRendererTrait;

    private const BUTTON_BASE_CLASS = 'amelia-eventslist-booking-button';

    /**
     * Register module.
     */
    public function load()
    {
        add_action('init', [AmeliaEventsListBookingButtonModule::class, 'registerModule']);
    }

    public static function registerModule()
    {
        $module_json_folder_path = dirname(__DIR__, 1) . '/visual-builder/src/modules/EventsListBookingButton';

        ModuleRegistration::register_module(
            $module_json_folder_path,
            [
                'render_callback' => [AmeliaEventsListBookingButtonModule::class, 'renderCallback'],
            ]
        );
    }

    /**
     * Generate classnames for the module.
     */
    public static function module_classnames(array $args): void
    {
        $classnames_instance = $args['classnamesInstance'];
        $attrs               = $args['attrs'];

        $classnames_instance->add(
            ElementClassnames::classnames([
                'attrs' => $attrs['module']['decoration'] ?? [],
            ])
        );
    }

    /**
     * Module script data.
     */
    public static function module_script_data(array $args): void
    {
        $elements = $args['elements'];

        $elements->script_data([
            'attrName' => 'module',
        ]);
    }

    /**
     * Module styles.
     */
    public static function module_styles(array $args): void
    {
        $attrs    = $args['attrs'] ?? [];
        $elements = $args['elements'];
        $settings = $args['settings'] ?? [];

        Style::add([
            'id'            => $args['id'],
            'name'          => $args['name'],
            'orderIndex'    => $args['orderIndex'],
            'storeInstance' => $args['storeInstance'],
            'styles'        => [
                // Module styles.
                $elements->style([
                    'attrName'   => 'module',
                    'styleProps' => [
                        'disabledOn' => [
                            'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
                        ],
                    ],
                ]),
                // Button styles.
                $elements->style([
                    'attrName' => 'button',
                ]),
            ],
        ]);
    }

    /**
     * Render module HTML output.
     */
    public static function renderCallback(array $attrs, string $content, WP_Block $block, ModuleElements $elements): string
    {
        $auto_trigger = wp_unique_id('amelia-eventslist-booking-btn-');

        $shortcode  = '[ameliaeventslistbooking';
        $shortcode .= ' trigger=' . sanitize_html_class($auto_trigger);
        $shortcode .= ' trigger_type=id';
        $shortcode .= ' in_dialog=1';

        // Preselect/filter parameters
        $booking_params = $attrs['booking_params']['innerContent']['desktop']['value'] ?? false;
        if ($booking_params === 'on') {
            $event = $attrs['events']['innerContent']['desktop']['value'] ?? [];
            if ($event && count($event) > 0) {
                $shortcode .= ' event=' . implode(',', array_filter(array_map('absint', $event)));
            }

            $event_to_show = $attrs['event_to_show']['innerContent']['desktop']['value'] ?? 'all';
            if (count($event) === 0 && $event_to_show !== 'all') {
                if ($event_to_show === 'custom') {
                    $start_date = $attrs['start_date']['innerContent']['desktop']['value'] ?? '';
                    $end_date = $attrs['end_date']['innerContent']['desktop']['value'] ?? '';

                    $has_valid_start = $start_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date);
                    $has_valid_end = $end_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date);

                    if (!$has_valid_start || !$has_valid_end) {
                        // Fallback: render without date range filter, or log warning
                        // For now, skip the range parameter rather than hiding the button
                        $event_to_show = 'all';
                    } else {
                        $shortcode .= ' range="' . esc_attr($start_date) . ' - ' . esc_attr($end_date) . '"';
                    }
                } else {
                    $shortcode .= ' range="' . esc_attr($event_to_show) . '"';
                }
            }

            $tag = $attrs['tags']['innerContent']['desktop']['value'] ?? [];
            if ($tag && count($tag) > 0) {
                $shortcode .= ' tag="' . implode(',', array_map(function ($t) {
                        return '{' . sanitize_text_field($t) . '}';
                    }, $tag)) . '"';
            }

            $recurring = $attrs['recurring']['innerContent']['desktop']['value'] ?? false;
            if ($recurring === 'on') {
                $shortcode .= ' recurring=1';
            }

            $locations = $attrs['locations']['innerContent']['desktop']['value'] ?? [];
            if ($locations && count($locations) > 0) {
                $shortcode .= ' location=' . implode(',', array_filter(array_map('absint', $locations)));
            }
        }

        $shortcode .= ']';

        // Render shortcode now to preserve quoted attributes (e.g., custom range) reliably.
        $shortcode_output = do_shortcode($shortcode);

        $button_html = self::renderTriggerButtonHtml(
            $attrs,
            $block,
            $elements,
            $auto_trigger,
            self::BUTTON_BASE_CLASS,
            LiteBackendStrings::get('event_book_event')
        );

        return self::renderBookingButtonModuleShell(
            $attrs,
            $block,
            $elements,
            $button_html,
            'amelia-eventslist-booking-button_wrapper',
            $shortcode_output
        );
    }
}

