<?php

function cca_enqueue_assets() {

    wp_enqueue_style(
        'cca-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        [],
        time()
    );

    wp_enqueue_script(
        'cca-child-script',
        get_stylesheet_directory_uri() . '/js/custom.js',
        ['jquery'],
        time(),
        true
    );
}
add_action('wp_enqueue_scripts', 'cca_enqueue_assets');

/**
 * Add endpoint
 */
function cca_add_appointments_endpoint() {
    add_rewrite_endpoint( 'appointments', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'cca_add_appointments_endpoint' );

/**
 * Add menu item
 */
function cca_add_appointments_menu( $items ) {

    $logout = $items['customer-logout'];
    unset($items['customer-logout']);

    $items['appointments'] = 'My Appointments';

    $items['customer-logout'] = $logout;

    return $items;
}
add_filter( 'woocommerce_account_menu_items', 'cca_add_appointments_menu' );

/**
 * Endpoint content
 */
function cca_appointments_content() {
    echo do_shortcode('[ameliacustomerpanel]');
}
add_action( 'woocommerce_account_appointments_endpoint', 'cca_appointments_content' );
