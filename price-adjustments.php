<?php
add_filter('woocommerce_get_price', 'adjust_b2b_prices_for_customers', 10, 2);
add_filter('woocommerce_get_variation_price', 'adjust_b2b_prices_for_customers', 10, 2);

function adjust_b2b_prices_for_customers($price, $product) {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $allowed_roles = ['b2b_customer', 'administrator'];
        if (array_intersect($allowed_roles, (array)$user->roles)) {
            if ($product->is_type('variable')) {
                $variations = $product->get_available_variations();
                foreach ($variations as $variation) {
                    $variation_id = $variation['variation_id'];
                    $b2b_price_variation = get_post_meta($variation_id, '_B2B_price_variation', true);
                    if ($b2b_price_variation !== '' && floatval($b2b_price_variation) > 0) {
                        return $b2b_price_variation;
                    }
                }
            } else {
                $b2b_price = get_post_meta($product->get_id(), '_B2B_price', true);
                if ($b2b_price !== '' && floatval($b2b_price) > 0) {
                    return $b2b_price;
                }
            }
        }
    }

    return $price;
}
