<?php

namespace Divi5Amelia;

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;

/**
 * Class that handle shared booking module output in frontend.
 */
abstract class SharedShortcodeModule implements DependencyInterface
{
    /**
     * Get shared shortcode string from attributes.
     *
     * @param array $attrs Attributes array.
     * @return string Shared shortcode string.
     */
    public static function getSharedShortcodeString($attrs)
    {
        $shortcode = '';

        // Trigger
        $trigger = $attrs['trigger']['innerContent']['desktop']['value'] ?? null;
        if ($trigger !== null && $trigger !== '') {
            $shortcode .= ' trigger=' . esc_attr($trigger);
        }

        // Trigger Type
        $trigger_type = $attrs['trigger_type']['innerContent']['desktop']['value'] ?? null;
        if ($trigger && $trigger_type !== null && $trigger_type !== '') {
            $shortcode .= ' trigger_type=' . esc_attr($trigger_type);
        }

        // In Dialog
        $in_dialog = $attrs['in_dialog']['innerContent']['desktop']['value'] ?? false;
        if ($in_dialog === 'on') {
            $shortcode .= ' in_dialog=1';
        }

        // Ivy
        $ivy = $attrs['ivy']['innerContent']['desktop']['value'] ?? null;
        if ($ivy !== null && $ivy !== '' && !$trigger) {
            $shortcode .= ' ivy=' . esc_attr($ivy);
        }

        return $shortcode;
    }
}
