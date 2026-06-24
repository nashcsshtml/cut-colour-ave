<?php

namespace Divi5Amelia;

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WP_Block;

/**
 * Class that handles "Amelia Step Booking Button" module output in frontend.
 */
class AmeliaStepBookingButtonModule implements DependencyInterface
{
    use AmeliaBookingButtonRendererTrait;

    private const BUTTON_BASE_CLASS = 'amelia-step-booking-button';

    /**
     * Register module.
     */
    public function load()
    {
        add_action('init', [AmeliaStepBookingButtonModule::class, 'registerModule']);
    }

    public static function registerModule()
    {
        $module_json_folder_path = dirname(__DIR__, 1) . '/visual-builder/src/modules/StepBookingButton';

        ModuleRegistration::register_module(
            $module_json_folder_path,
            [
                'render_callback' => [AmeliaStepBookingButtonModule::class, 'renderCallback'],
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
     * Normalize toggle values coming from Divi attrs.
     */
    private static function isToggleEnabled($value): bool
    {
        if (true === $value || 1 === $value || '1' === $value || 'on' === $value || 'true' === $value) {
            return true;
        }

        if (is_array($value)) {
            if (isset($value['desktop']['value'])) {
                return self::isToggleEnabled($value['desktop']['value']);
            }

            if (isset($value['value'])) {
                return self::isToggleEnabled($value['value']);
            }
        }

        return false;
    }

    /**
     * Render module HTML output.
     */
    public static function renderCallback(array $attrs, string $content, WP_Block $block, ModuleElements $elements): string
    {
        $auto_trigger = wp_unique_id('amelia-step-booking-btn-');

        $shortcode  = '[ameliastepbooking';
        $shortcode .= ' trigger=' . sanitize_html_class($auto_trigger);
        $shortcode .= ' trigger_type=id';
        $shortcode .= ' in_dialog=1';

        $layout = $attrs['layout']['innerContent']['desktop']['value'] ?? '1';
        if ($layout !== null && $layout !== '') {
            $shortcode .= ' layout=' . absint($layout);
        }

        $to_csv_ids = static function ($value): string {
            $vals = is_array($value) ? $value : [$value];
            $vals = array_filter(array_map('absint', $vals));

            return implode(',', $vals);
        };

        $preselect = $attrs['parameters']['innerContent']['desktop']['value']
            ?? $attrs['parameters']['innerContent']['value']
            ?? $attrs['parameters']['innerContent']
            ?? false;

        if (self::isToggleEnabled($preselect)) {
            $show = $attrs['type']['innerContent']['desktop']['value'] ?? '';
            if ($show !== '' && $show !== '0') {
                $shortcode .= ' show=' . sanitize_key((string) $show);
            }

            $category = $attrs['categories']['innerContent']['desktop']['value'] ?? [];
            $service  = $attrs['services']['innerContent']['desktop']['value'] ?? [];
            $employee = $attrs['employees']['innerContent']['desktop']['value'] ?? [];
            $location = $attrs['locations']['innerContent']['desktop']['value'] ?? [];
            $package  = $attrs['packages']['innerContent']['desktop']['value'] ?? [];

            $service_csv  = $to_csv_ids($service);
            $category_csv = $to_csv_ids($category);
            $employee_csv = $to_csv_ids($employee);
            $location_csv = $to_csv_ids($location);
            $package_csv  = $to_csv_ids($package);

            if ($service_csv !== '') {
                $shortcode .= ' service=' . $service_csv;
            } elseif ($category_csv !== '') {
                $shortcode .= ' category=' . $category_csv;
            }

            if ($employee_csv !== '') {
                $shortcode .= ' employee=' . $employee_csv;
            }

            if ($location_csv !== '') {
                $shortcode .= ' location=' . $location_csv;
            }

            if ($package_csv !== '') {
                $shortcode .= ' package=' . $package_csv;
            }
        }

        $shortcode .= ']';

        $button_html = self::renderTriggerButtonHtml(
            $attrs,
            $block,
            $elements,
            $auto_trigger,
            self::BUTTON_BASE_CLASS,
            __('Book Appointment', 'wpamelia')
        );


        return self::renderBookingButtonModuleShell(
            $attrs,
            $block,
            $elements,
            $button_html,
            'amelia-step-booking-button_wrapper',
            esc_html($shortcode)
        );
    }
}
