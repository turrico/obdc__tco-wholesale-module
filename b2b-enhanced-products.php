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
function b2b_enqueue_scripts() {
    global $post;

    // Load scripts if:
    // 1. We're on the specific wholesale page
    // 2. The shortcode is present in the content
    // 3. We're on the product archive (for tabs implementation)
    $specific_page_slug = 'formulario-de-compra';
    $has_shortcode = is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'enhanced_wc_products');
    $is_product_archive = is_post_type_archive('product');

    if (is_specific_page($specific_page_slug) || $has_shortcode || $is_product_archive) {
        wp_enqueue_script('bulk-purchase-form', plugin_dir_url(__FILE__) . 'js/bulk-purchase-form.js', array('jquery'), '1.0', true);
        wp_script_add_data('bulk-purchase-form', array('defer', 'async'), true);

        wp_enqueue_script('sticky-header', plugin_dir_url(__FILE__) . 'js/sticky-header.js', array('jquery'), '1.0', true);
        wp_script_add_data('sticky-header', array('defer', 'async'), true);
    }
}
add_action('wp_enqueue_scripts', 'b2b_enqueue_scripts');

// Add 'defer' and 'async' attributes to scripts
function wp_script_add_data($handle, $attributes, $value) {
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
