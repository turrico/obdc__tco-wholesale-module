<?php
function get_producer_name_by_product($product_id, $relationship_id) {
    if (!class_exists("MB_Relationships_API")) {
        return "MB_Relationships_API class does not exist";
    }

    $connected = new WP_Query([
        "relationship" => [
            "id" => $relationship_id,
            "to" => $product_id,
        ],
        "nopaging" => true,
    ]);

    ob_start();

    if ($connected->have_posts()) {
        while ($connected->have_posts()) {
            $connected->the_post();
            echo esc_attr(get_the_title());
        }
    } else {
        echo "No producer found";
    }

    wp_reset_postdata();

    return ob_get_clean();
}

function enhanced_woocommerce_product_display_shortcode() {
    ob_start();

    // Enqueue scripts and styles ONLY when shortcode is used
    wp_enqueue_style("bulk-purchase-form-css");
    wp_enqueue_script("bulk-purchase-form");

    if (!function_exists("wc_get_products")) {
        echo "<p class='wc-no-products'>WooCommerce is not active!</p>";
        return ob_get_clean();
    }

    $args = [
        "status" => "publish",
        "limit" => -1,
        "return" => "objects",
    ];
    $products = wc_get_products($args);

    if (empty($products)) {
        echo "<p class='wc-no-products'>No products found.</p>";
        return ob_get_clean();
    }

    // --- Preload Cart Quantities ---
    $cart_quantities = [];
    if (WC()->cart) {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['data']->get_id();
            // Handle variations: get_id() returns variation ID for variations
            $cart_quantities[$product_id] = $cart_item['quantity'];
        }
    }
    // -------------------------------

    $total = 0;
    // Changed to Cart URL
    $cart_url = wc_get_cart_url();

    echo "<div class='bulk-purchase-form'>";
    echo "<form action='" . esc_url($cart_url) . "' method='post'>";
    echo "<div class='bulk-purchase-form__sticky-header sticky'>";
    echo "<span class='bulk-purchase-form__total text--primary-ultra-light text--l text--bold'>Total: <span id='orderTotal'>" . number_format($total, 2, ",", ".") . "</span></span>";
    // Updated button text and classes
    echo "<button type='submit' class='bulk-purchase-form__submit-order btn--secondary text--m text--bold'>Actualizar Carrito</button>";
    echo "</div>";
    echo "<table class='bulk-purchase-form__table'>";
    echo "<thead><tr class='bulk-purchase-form__table-header'><th>Producto</th><th>Características</th><th>Precio</th><th>Cantidad</th><th>Total</th></tr></thead>";

    foreach ($products as $product) {
        $producer_name = get_producer_name_by_product($product->get_id(), "producto-productor-relationship");
        $display_producer_name = !empty($producer_name) ? $producer_name : "No producer found";

        // Wrap each product in a tbody for grouping
        echo "<tbody class='product-group'>";

        // --- Mobile-Only Sticky Header Row ---
        echo "<tr class='mobile-product-header' style='display:none;'>";
        echo "<td colspan='5'>";
        echo "<span class='bulk-purchase-form__product-name'>" . esc_html($product->get_name()) . "</span><br>";
        echo "<span class='bulk-purchase-form__producer-name'>" . esc_html($display_producer_name) . "</span>";
        echo "</td>";
        echo "</tr>";
        // -------------------------------------

        if ($product->is_type("variable")) {
            $variations = $product->get_available_variations();
            $variation_count = count($variations);

            foreach ($variations as $index => $variation) {
                $variation_obj = new WC_Product_Variation($variation["variation_id"]);
                $attributes = $variation_obj->get_attributes();
                $attributes_text = implode(", ", array_map(function ($attr, $value) { return "$attr: $value"; }, array_keys($attributes), $attributes));

                $row_class = ($index === 0) ? 'bulk-purchase-form__product-row first-variation' : 'bulk-purchase-form__product-row subsequent-variation';

                // Get current quantity from cart if available
                $current_qty = isset($cart_quantities[$variation["variation_id"]]) ? $cart_quantities[$variation["variation_id"]] : 0;

                echo "<tr class='$row_class'>";
                
                // Only render product info for the first variation
                if ($index === 0) {
                    echo "<td class='bulk-purchase-form__product-info' data-column='Producto' rowspan='$variation_count'>";
                    echo "<span class='bulk-purchase-form__product-name'>" . esc_html($product->get_name()) . "</span><br>";
                    echo "<span class='bulk-purchase-form__producer-name'>" . esc_html($display_producer_name) . "</span>";
                    echo "</td>";
                }

                echo "<td class='bulk-purchase-form__product-details' data-column='Características'>" . esc_html($attributes_text) . "</td>";
                echo "<td class='bulk-purchase-form__product-price' data-column='Precio'>" . format_currency_colones($variation_obj->get_price()) . "</td>";
                echo "<td class='bulk-purchase-form__product-quantity' data-column='Cantidad'><input type='number' class='bulk-purchase-form__quantity-input' name='quantity[" . esc_attr($variation_obj->get_id()) . "]' value='" . esc_attr($current_qty) . "' min='0' step='1' inputmode='numeric' pattern='[0-9]*'></td>";
                echo "<td class='bulk-purchase-form__product-total' data-column='Total'>₡0.00</td>";
                echo "</tr>";
            }
        } else {
            $attributes = $product->get_attributes();
            $attributes_text = implode(", ", array_map(function ($attr) use ($product) { return $attr->get_name() . ": " . $product->get_attribute($attr->get_name()); }, $attributes));

            // Get current quantity from cart if available
            $current_qty = isset($cart_quantities[$product->get_id()]) ? $cart_quantities[$product->get_id()] : 0;

            echo "<tr class='bulk-purchase-form__product-row'>";
            echo "<td class='bulk-purchase-form__product-info' data-column='Producto'>";
            echo "<span class='bulk-purchase-form__product-name'>" . esc_html($product->get_name()) . "</span><br>";
            echo "<span class='bulk-purchase-form__producer-name'>" . esc_html($display_producer_name) . "</span>";
            echo "</td>";
            echo "<td class='bulk-purchase-form__product-details' data-column='Características'>" . esc_html($attributes_text ?: "N/A") . "</td>";
            echo "<td class='bulk-purchase-form__product-price' data-column='Precio'>" . format_currency_colones($product->get_price()) . "</td>";
            echo "<td class='bulk-purchase-form__product-quantity' data-column='Cantidad'><input type='number' class='bulk-purchase-form__quantity-input' name='quantity[" . esc_attr($product->get_id()) . "]' value='" . esc_attr($current_qty) . "' min='0' step='1' inputmode='numeric' pattern='[0-9]*'></td>";
            echo "<td class='bulk-purchase-form__product-total' data-column='Total'>₡0.00</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
    }

    echo "</table>";
    echo "</form>";
    echo "</div>";

    return ob_get_clean();
}

add_shortcode("enhanced_wc_products", "enhanced_woocommerce_product_display_shortcode");

function format_currency_colones($amount) {
    if (!is_numeric($amount)) {
        error_log("Non-numeric value passed to format_currency_colones: " . var_export($amount, true));
        return "₡0.00";
    }

    $amount = (float) $amount;

    return "₡" . number_format($amount, 2, ",", ".");
}

add_action("template_redirect", "handle_product_form_submission");
function handle_product_form_submission() {
    if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["quantity"])) {
        $quantities = $_POST["quantity"];
        if (is_array($quantities)) {
            foreach ($quantities as $product_id => $qty) {
                $qty = intval($qty);
                $cart_item_key = WC()->cart->find_product_in_cart($product_id);
                
                if ($cart_item_key) {
                    if ($qty > 0) {
                        WC()->cart->set_quantity($cart_item_key, $qty);
                    } else {
                        WC()->cart->remove_cart_item($cart_item_key);
                    }
                } elseif ($qty > 0) {
                    WC()->cart->add_to_cart($product_id, $qty);
                }
            }
            // Redirect to Cart URL
            wp_redirect(wc_get_cart_url());
            exit();
        }
    }
}
