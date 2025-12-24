<?php
/**
 * Plugin Name: B2B Enhanced Products
 * Description: A plugin to display WooCommerce products with B2B enhancements, including custom user roles, price adjustments, and enhanced product displays.
 * Version: 1.0
 * Author: Your Name
 */

// Include other PHP files
include_once plugin_dir_path(__FILE__) . 'shortcodes.php';
include_once plugin_dir_path(__FILE__) . 'user-roles.php';
include_once plugin_dir_path(__FILE__) . 'price-adjustments.php';
include_once plugin_dir_path(__FILE__) . 'order-fees.php';

// Function to check if the current page is the specific page
function is_specific_page($page_slug) {
    global $post;
    return is_page() && $post->post_name === $page_slug;
}

// Enqueue JavaScript files
function b2b_register_scripts() {
    // Register styles and scripts so they can be enqueued on demand by the shortcode
    wp_register_style('bulk-purchase-form-css', plugin_dir_url(__FILE__) . 'css/bulk-purchase-form.css', array(), '1.0');

    wp_register_script('bulk-purchase-form', plugin_dir_url(__FILE__) . 'js/bulk-purchase-form.js', array('jquery'), '1.0', true);
    
    // Localize script to pass AJAX URL and Nonce
    wp_localize_script('bulk-purchase-form', 'obdc_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('obdc_cart_nonce')
    ));

    obdc_add_script_attributes('bulk-purchase-form', array('defer', 'async'), true);

    wp_register_script('sticky-header', plugin_dir_url(__FILE__) . 'js/sticky-header.js', array('jquery'), '1.0', true);
    obdc_add_script_attributes('sticky-header', array('defer', 'async'), true);
}
add_action('wp_enqueue_scripts', 'b2b_register_scripts');

// AJAX Handler to retrieve current cart quantities
function obdc_get_cart_quantities() {
    check_ajax_referer('obdc_cart_nonce', 'nonce');

    if (!WC()->cart) {
        wp_send_json_error('WooCommerce Cart not available');
    }

    $cart_quantities = [];
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['data']->get_id(); // Returns variation_id for variations
        $cart_quantities[$product_id] = $cart_item['quantity'];
    }

    wp_send_json_success($cart_quantities);
}
add_action('wp_ajax_obdc_get_cart_quantities', 'obdc_get_cart_quantities');
add_action('wp_ajax_nopriv_obdc_get_cart_quantities', 'obdc_get_cart_quantities');

// Add 'defer' and 'async' attributes to scripts
function obdc_add_script_attributes($handle, $attributes, $value) {
    if (!is_array($attributes)) {
        $attributes = array($attributes);
    }

    foreach ($attributes as $attribute) {
        add_filter('script_loader_tag', function ($tag, $handle_to_check) use ($handle, $attribute, $value) {
            if ($handle !== $handle_to_check) {
                return $tag;
            }

            if ($value) {
                return str_replace(' src', ' ' . $attribute . ' src', $tag);
            }

            return str_replace(' ' . $attribute, '', $tag);
        }, 10, 2);
    }
}
