<?php
/**
* Plugin Name: 			Custom Login Page Customizer
* Plugin URI: 			https://loginpress.pro/?utm_source=login-customizer-lite&utm_medium=plugin-url-link
* Description: 			Custom Login Customizer plugin allows you to easily customize your login page straight from your WordPress Customizer! Awesome, right?
* Version: 				2.5.4
* Requires at least: 	4.0
* Tested up to:         6.9
* Requires PHP:      	5.6
* Author: 				Hardeep Asrani
* Author URI: 			https://loginpress.pro/?utm_source=login-customizer-lite&utm_medium=author-url-link
* WordPress Available:  yes
* Requires License:     no
* License: 				GPLv2+
* Text Domain: 			login-customizer
* Domain Path: 			/resources/languages
* GitHub Plugin URI:    https://github.com/WPBrigade/login-customizer
*
* @package 			LoginCustomizer
* @author 			WPBrigade
* @copyright 		Copyright (c) 2025, WPBrigade
* @link 			https://loginpress.pro/
* @license			https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/
namespace LoginCustomizer;

if ( ! function_exists( 'lc_fs2324234' ) ) {
    // Create a helper function for easy SDK access.
    function lc_fs2324234() {
        global $lc_fs2324234;

        if ( ! isset( $lc_fs2324234 ) ) {
            // Include Freemius SDK.
            require_once dirname(__FILE__) . '/freemius/start.php';

            $lc_fs2324234 = fs_dynamic_init( array(
                'id'                  => '8656',
                'slug'                => 'login-customizer',
                'type'                => 'plugin',
                'public_key'          => 'pk_685db264a64a07d94c59266a47a2a',
                'is_premium'          => false,
                'has_addons'          => false,
                'has_paid_plans'      => false,
                'menu'                => array(
                    'slug'           => 'login-customizer',
                    'account'        => false,
                    'support'        => false,
                ),
            ) );
        }

        return $lc_fs2324234;
    }

    // Init Freemius.
    lc_fs2324234();
    // Signal that SDK was initiated.
    do_action( 'lc_fs2324234_loaded' );
}

defined( 'ABSPATH' ) || exit;

include_once 'autoload.php';

use LoginCustomizer\Plugin;

/**
 * Wrapper for the plugin instance.
 *
 * @since  2.2.0
 * @access public
 * @return void
 */
function plugin() {
	
	static $instance = null;

	if ( is_null( $instance ) ) {
		$instance = new Plugin( __DIR__, plugin_dir_url( __FILE__ ) );
	}

	return $instance;
}

# Boot the plugin.
plugin();
