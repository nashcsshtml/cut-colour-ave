<?php

namespace Divi5Amelia;

use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use WP_Block;

/**
 * Shared helpers for rendering Divi-trigger buttons used by Amelia modules.
 */
trait AmeliaBookingButtonRendererTrait
{
    /**
     * Normalize button innerContent.desktop.value to object-like array.
     * Supports both persisted string and structured array formats.
     */
    private static function normalizeButtonDesktopValue(array &$button_attr): array
    {
        $button_attr['innerContent'] = $button_attr['innerContent'] ?? [];
        $button_attr['innerContent']['desktop'] = $button_attr['innerContent']['desktop'] ?? [];

        $raw = $button_attr['innerContent']['desktop']['value'] ?? null;

        if (is_string($raw)) {
            $button_attr['innerContent']['desktop']['value'] = [
                'text' => $raw,
            ];
        } elseif (!is_array($raw)) {
            $button_attr['innerContent']['desktop']['value'] = [];
        }

        return $button_attr['innerContent']['desktop']['value'];
    }

    /**
     * Merge CSS class names while preserving uniqueness.
     */
    private static function mergeClassNames(string ...$class_names): string
    {
        $all = implode(' ', $class_names);
        $parts = preg_split('/\s+/', trim($all)) ?: [];
        $parts = array_values(array_unique(array_filter($parts, static function ($part) {
            return $part !== '';
        })));

        return implode(' ', $parts);
    }

    /**
     * Ensure the first rendered button/anchor has display:inline-block in inline style.
     */
    private static function ensureInlineBlockDisplayInHtml(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        // Case 1: Existing style="..." on first clickable tag -> normalize display to inline-block.
        $updated = preg_replace_callback(
            '/<(a|button)\b([^>]*?)\sstyle\s*=\s*(["\'])(.*?)\3([^>]*)>/i',
            static function (array $matches): string {
                $style_value = trim((string) $matches[4]);
                if (preg_match('/(?:^|;)\s*display\s*:[^;]*/i', $style_value)) {
                    $style_value = preg_replace(
                        '/(^|;)\s*display\s*:[^;]*(?:;|$)/i',
                        '$1 display: inline-block;',
                        $style_value,
                        1
                    ) ?? $style_value;
                } else {
                    if ($style_value !== '' && substr($style_value, -1) !== ';') {
                        $style_value .= ';';
                    }
                    $style_value .= ' display: inline-block;';
                }

                if ($style_value !== '' && substr($style_value, -1) !== ';') {
                    $style_value .= ';';
                }

                return '<' . $matches[1] . $matches[2] . ' style=' . $matches[3] . trim($style_value) . $matches[3] . $matches[5] . '>';
            },
            $html,
            1
        );

        if ($updated !== null && $updated !== $html) {
            return $updated;
        }

        // Case 2: Bare style attribute token (style without value) on first clickable tag.
        $updated = preg_replace(
            '/(<(?:a|button)\b[^>]*?)\sstyle(?=[\s>])/i',
            '$1 style="display: inline-block;"',
            $html,
            1
        );

        if ($updated !== null && $updated !== $html) {
            return $updated;
        }

        // Case 3: No style attribute on first clickable tag -> inject one.
        $updated = preg_replace(
            '/(<(?:a|button)\b)([\s>])/i',
            '$1 style="display: inline-block;"$2',
            $html,
            1
        );

        return $updated ?? $html;
    }

    /**
     * Build and render trigger button HTML while keeping Divi button markup/styles.
     */
    private static function renderTriggerButtonHtml(
        array $attrs,
        WP_Block $block,
        ModuleElements $elements,
        string $auto_trigger,
        string $button_base_class,
        string $fallback_button_text
    ): string {
        $button_attr = $attrs['button'] ?? [];
        $desktop_value = self::normalizeButtonDesktopValue($button_attr);
        $button_text = trim((string) ($desktop_value['text'] ?? ''));
        if ($button_text === '') {
            $button_text = $fallback_button_text;
        }

        $module_id = isset($block->parsed_block['id']) ? (string) $block->parsed_block['id'] : '';
        $divi_button_instance_class = 'et_pb_button_' . sanitize_html_class($module_id !== '' ? $module_id : wp_unique_id());

        $button_attr['attributes'] = $button_attr['attributes'] ?? [];
        $existing_button_class = isset($button_attr['attributes']['class']) ? trim((string) $button_attr['attributes']['class']) : '';
        $button_attr['attributes']['class'] = self::mergeClassNames(
            $button_base_class,
            'et_block_module',
            $divi_button_instance_class,
            $existing_button_class
        );

        $button_attr['attributes']['id'] = trim($auto_trigger);
        if ($module_id !== '' && !isset($button_attr['attributes']['data-id'])) {
            $button_attr['attributes']['data-id'] = $module_id;
        }

        $button_attr['innerContent']['desktop']['value']['text'] = $button_text;
        if (!isset($button_attr['innerContent']['desktop']['value']['linkUrl'])) {
            $button_attr['innerContent']['desktop']['value']['linkUrl'] = '';
        }

        $button_html = $elements->render([
            'attrName'    => 'button',
            'elementAttr' => $button_attr,
            'elementProps' => [
                'hasWrapper'    => false,
                'allowEmptyUrl' => true,
            ],
        ]);

        $button_html = self::ensureInlineBlockDisplayInHtml((string) $button_html);

        if (!empty($button_html) && !empty($auto_trigger)) {
            $button_html = preg_replace(
                '/(<(?:a|button)\b)([\s>])/i',
                '$1 id="' . esc_attr($auto_trigger) . '"$2',
                $button_html,
                1
            );
        }

        return (string) $button_html;
    }

    /**
     * Render the common module shell around the booking trigger button.
     */
    private static function renderBookingButtonModuleShell(
        array $attrs,
        WP_Block $block,
        ModuleElements $elements,
        string $button_html,
        string $wrapper_class,
        string $shortcode_hidden_content
    ): string {
        $parent = BlockParserStore::get_parent(
            $block->parsed_block['id'],
            $block->parsed_block['storeInstance']
        );

        $inner_content =
            $elements->style_components(['attrName' => 'module'])
            . $elements->style_components(['attrName' => 'button'])
            . '<div class="' . esc_attr($wrapper_class) . '">'
            . $button_html
            . '</div>'
            . '<div class="amelia-shortcode" style="display:none">' . $shortcode_hidden_content . '</div>';

        return Module::render([
            // FE only.
            'orderIndex'          => $block->parsed_block['orderIndex'],
            'storeInstance'       => $block->parsed_block['storeInstance'],

            // VB equivalent.
            'attrs'               => $attrs,
            'elements'            => $elements,
            'id'                  => $block->parsed_block['id'],
            'name'                => $block->block_type->name,
            'classnamesFunction'  => [self::class, 'module_classnames'],
            'moduleCategory'      => $block->block_type->category,
            'stylesComponent'     => [self::class, 'module_styles'],
            'scriptDataComponent' => [self::class, 'module_script_data'],
            'parentAttrs'         => $parent->attrs ?? [],
            'parentId'            => $parent->id ?? '',
            'parentName'          => $parent->blockName ?? '',
            'children'            => $inner_content,
        ]);
    }
}

