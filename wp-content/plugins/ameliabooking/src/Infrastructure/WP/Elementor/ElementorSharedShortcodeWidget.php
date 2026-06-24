<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace Elementor;

use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;

/**
 * Class ElementorSharedShortcodeWidget
 *
 * @package AmeliaBooking\Infrastructure\WP\Elementor
 */
abstract class ElementorSharedShortcodeWidget extends Widget_Base
{
    protected function setSharedShortcodeElements($controls_data)
    {
        $this->add_control(
            'load_manually',
            [
                'label' => BackendStrings::get('manually_loading'),
                'label_block' => true,
                'type' => Controls_Manager::TEXT,
                'placeholder' => '',
                'description' => BackendStrings::get('manually_loading_description'),
            ]
        );

        $this->add_control(
            'trigger_type',
            [
                'label' => BackendStrings::get('trigger_type'),
                'type' => Controls_Manager::SELECT,
                'description' => BackendStrings::get('trigger_type_tooltip'),
                'options' => [
                    'id' => BackendStrings::get('trigger_type_id'),
                    'class' => BackendStrings::get('trigger_type_class')
                ],
                'condition' => [
                    'load_manually!' => '',
                ],
                'default' => 'id'
            ]
        );

        $this->add_control(
            'in_dialog',
            [
                'label' => BackendStrings::get('in_dialog'),
                'type' => Controls_Manager::SWITCHER,
                'default' => false,
                'label_on' => BackendStrings::get('yes'),
                'label_off' => BackendStrings::get('no'),
                'condition' => [
                    'load_manually!' => '',
                ],
            ]
        );

        if (!empty($controls_data['ivy'])) {
            $this->add_control(
                'ivy',
                [
                    'label' => BackendStrings::get('ivy'),
                    'type' => Controls_Manager::SELECT,
                    'description' => BackendStrings::get('ivy_tooltip'),
                    'options' => $controls_data['ivy'],
                    'default' => '',
                    'condition' => [
                        'load_manually' => '',
                    ],
                ]
            );
        }
    }

    protected function getSharedShortcodeString($settings)
    {
        $shortCode = '';

        $trigger      = $settings['load_manually'] !== '' ? ' trigger="' . esc_attr($settings['load_manually']) . '"' : '';
        $trigger_type = $settings['load_manually'] && $settings['trigger_type'] !== '' ? ' trigger_type="' . esc_attr($settings['trigger_type']) . '"' : '';
        $in_dialog    = $settings['load_manually'] && $settings['in_dialog'] === 'yes' ? ' in_dialog=1' : '';
        $ivy          = !$settings['load_manually'] && !empty($settings['ivy']) && $settings['ivy'] !== '0' ? ' ivy="' . esc_attr($settings['ivy']) . '"' : '';

        $shortCode .= $trigger . $trigger_type . $in_dialog . $ivy;

        return $shortCode;
    }

    public static function setSharedShortcodeData($data, &$elementorData)
    {
        $elementorData['ivy'] = [];

        if (empty($data['ivy'])) {
            return;
        }

        foreach ($data['ivy'] as $form) {
            $elementorData['ivy'][$form['value']] = $form['label'];
        }
    }
}
