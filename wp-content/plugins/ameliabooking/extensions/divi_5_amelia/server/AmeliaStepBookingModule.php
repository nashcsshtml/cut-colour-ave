<?php

namespace Divi5Amelia;

use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

/**
 * Class that handle "Amelia Step Booking" module output in frontend.
 */
class AmeliaStepBookingModule extends SharedShortcodeModule
{
    /**
     * Register module.
     * DependencyInterface interface ensures class method name `load()` is executed for initialization.
     */
    public function load()
    {
        // Register module.
        add_action('init', [AmeliaStepBookingModule::class, 'registerModule']);
    }

    public static function registerModule()
    {
        // Path to module metadata that is shared between Frontend and Visual Builder.
        $module_json_folder_path = dirname(__DIR__, 1) . '/visual-builder/src/modules/StepBooking';

        ModuleRegistration::register_module(
            $module_json_folder_path,
            ModuleMetadata::getRegistrationArgs(
                $module_json_folder_path,
                [
                    'render_callback' => [AmeliaStepBookingModule::class, 'renderCallback'],
                ]
            )
        );
    }

    /**
     * Render module HTML output.
     */
    public static function renderCallback($attrs, $content, $block, $elements)
    {
        $shortcode = '[ameliastepbooking';

        $show_all = $attrs['type']['innerContent']['desktop']['value'] ?? null;
        if ($show_all !== null && $show_all !== '0') {
            $shortcode .= ' show=' . $show_all;
        }

        // Layout
        $layout = $attrs['layout']['innerContent']['desktop']['value'] ?? null;
        if ($layout !== null && $layout !== '') {
            $shortcode .= ' layout=' . $layout;
        }

        $shortcode .= self::getSharedShortcodeString($attrs);

        $preselect = $attrs['parameters']['innerContent']['desktop']['value'] ?? false;
        if ($preselect === 'on') {
            $category = $attrs['categories']['innerContent']['desktop']['value'] ?? [];
            $service  = $attrs['services']['innerContent']['desktop']['value'] ?? [];
            $employee = $attrs['employees']['innerContent']['desktop']['value'] ?? [];
            $location = $attrs['locations']['innerContent']['desktop']['value'] ?? [];
            $package  = $attrs['packages']['innerContent']['desktop']['value'] ?? [];

            if ($service && count($service) > 0) {
                $shortcode .= ' service=' . implode(',', $service);
            } elseif ($category && count($category) > 0) {
                $shortcode .= ' category=' . implode(',', $category);
            }
            if ($employee && count($employee) > 0) {
                $shortcode .= ' employee=' . implode(',', $employee);
            }
            if ($location && count($location) > 0) {
                $shortcode .= ' location=' . implode(',', $location);
            }
            if ($package && count($package) > 0) {
                $shortcode .= ' package=' . implode(',', $package);
            }
        }

        $shortcode .= ']';

        return do_shortcode($shortcode);
    }
}
