<?php
/**
 * Percentage-Based Processing Fee for WooCommerce Orders
 *
 * @package Turri
 * @subpackage WooCommerce
 * @version 2.3.0
 * @author Orlando Bruno
 * @created 2025-11-25
 * @updated 2025-11-25
 *
 * Description:
 * This snippet adds a percentage-based processing fee to WooCommerce orders.
 * The fee is calculated as a percentage of (subtotal - discounts + shipping).
 * The fee appears in the order totals with the percentage shown in the label.
 * Example: "Fee de Procesamiento (8.02%): ₡28,097"
 * HPOS (High-Performance Order Storage) compatible.
 *
 * Installation:
 * 1. Copy this code into a WPCodeBox snippet
 * 2. Set Type: PHP Snippet
 * 3. Set Location: Everywhere (or Admin)
 * 4. Activate the snippet
 *
 * Usage:
 * 1. Edit any WooCommerce order in admin
 * 2. Find the "Tarifa de Procesamiento" metabox on the sidebar
 * 3. Enter the fee percentage (e.g., 5 for 5%)
 * 4. Save the order - the fee will be automatically calculated and applied
 *
 * Configuration:
 * - Line 194: Set tax status ('taxable' or 'none')
 * - Line 191: Change fee name/label (maintains percentage in the name)
 */

// Add metabox to order edit screen (supports both classic and HPOS)
add_action('add_meta_boxes', 'turri_add_processing_fee_metabox');

/**
 * Register the Processing Fee metabox
 *
 * Adds a metabox to the order edit screen sidebar where admins
 * can manually input a processing fee amount.
 * Compatible with both classic orders and HPOS.
 */
function turri_add_processing_fee_metabox() {
    $screen = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
        ? wc_get_page_screen_id('shop-order')
        : 'shop_order';

    add_meta_box(
        'turri_processing_fee',                     // Unique ID
        'Tarifa de Procesamiento',                  // Box title
        'turri_processing_fee_metabox_content',     // Content callback
        $screen,                                    // Screen/Post type (dynamic for HPOS)
        'side',                                     // Context (side/normal/advanced)
        'default'                                   // Priority
    );
}

/**
 * Render the Processing Fee metabox content
 *
 * @param WP_Post|WC_Order $post_or_order The current post object (order) or WC_Order object (HPOS)
 */
function turri_processing_fee_metabox_content($post_or_order) {
    // Handle both classic (WP_Post) and HPOS (WC_Order) contexts
    $order = $post_or_order instanceof WP_Post ? wc_get_order($post_or_order->ID) : $post_or_order;

    if (!$order) {
        return;
    }

    $processing_fee_percentage = $order->get_meta('_processing_fee_percentage', true);

    // Calculate base amount (subtotal - discounts + shipping)
    $subtotal = $order->get_subtotal();
    $discount = $order->get_total_discount();
    $shipping = $order->get_shipping_total();
    $subtotal_after_discount = $subtotal - $discount;
    $base_amount = $subtotal_after_discount + $shipping;

    // Calculate fee amount if percentage exists
    $calculated_fee = 0;
    if ($processing_fee_percentage > 0) {
        $calculated_fee = ($base_amount * $processing_fee_percentage) / 100;
    }

    // Add nonce for security
    wp_nonce_field('turri_processing_fee_nonce', 'turri_processing_fee_nonce_field');
    ?>
    <p>
        <label for="processing_fee_percentage">Porcentaje de Tarifa:</label>
        <input type="number"
               id="processing_fee_percentage"
               name="processing_fee_percentage"
               value="<?php echo esc_attr($processing_fee_percentage); ?>"
               step="0.01"
               min="0"
               max="100"
               style="width: calc(100% - 30px); display: inline-block;"
               placeholder="0.00" />
        <span style="display: inline-block; width: 25px; text-align: center;">%</span>
    </p>
    <p class="description">
        Ingrese el porcentaje de tarifa sobre (subtotal - descuentos + envío). Deje vacío o 0 para no aplicar tarifa.
    </p>
    <?php if ($processing_fee_percentage > 0): ?>
        <p style="margin-top: 10px; padding: 8px; background: #f0f0f1; border-radius: 4px;">
            <strong>Base de cálculo:</strong><br>
            Subtotal: <?php echo wc_price($subtotal, array('currency' => $order->get_currency())); ?><br>
            <?php if ($discount > 0): ?>
                Descuentos: -<?php echo wc_price($discount, array('currency' => $order->get_currency())); ?><br>
                <strong>Subtotal después de descuentos: <?php echo wc_price($subtotal_after_discount, array('currency' => $order->get_currency())); ?></strong><br>
            <?php endif; ?>
            Envío: <?php echo wc_price($shipping, array('currency' => $order->get_currency())); ?><br>
            <hr style="margin: 8px 0; border: none; border-top: 1px solid #ddd;">
            <strong>Total base: <?php echo wc_price($base_amount, array('currency' => $order->get_currency())); ?></strong><br>
            <strong style="color: #2271b1;">Tarifa calculada (<?php echo esc_html($processing_fee_percentage); ?>%):
            <?php echo wc_price($calculated_fee, array('currency' => $order->get_currency())); ?></strong>
        </p>
    <?php endif; ?>
    <?php
}

/**
 * Save the processing fee when order is updated
 *
 * This function:
 * 1. Validates the nonce and permissions
 * 2. Saves the fee percentage to order meta
 * 3. Calculates fee amount based on percentage (subtotal + shipping)
 * 4. Removes any existing processing fee items
 * 5. Adds the new fee to the order
 * 6. Recalculates order totals
 *
 * Compatible with both classic orders and HPOS.
 *
 * @param int $order_id The order ID being saved
 */
add_action('woocommerce_process_shop_order_meta', 'turri_save_processing_fee', 10, 1);
function turri_save_processing_fee($order_id) {
    // Security checks
    // 1. Verify nonce
    if (!isset($_POST['turri_processing_fee_nonce_field']) ||
        !wp_verify_nonce($_POST['turri_processing_fee_nonce_field'], 'turri_processing_fee_nonce')) {
        return;
    }

    // 2. Check if autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // 3. Check user permissions
    if (!current_user_can('edit_shop_orders')) {
        return;
    }

    // Get the order object
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Get and sanitize the fee percentage
    $fee_percentage = isset($_POST['processing_fee_percentage']) ? floatval($_POST['processing_fee_percentage']) : 0;

    // Remove any existing processing fee items first (prevents duplicates)
    foreach ($order->get_items('fee') as $item_id => $item) {
        // Check if fee name contains "Fee de Procesamiento" (matches any percentage)
        if (strpos($item->get_name(), 'Fee de Procesamiento') !== false) {
            $order->remove_item($item_id);
        }
    }

    if ($fee_percentage > 0) {
        // Save fee percentage to order meta
        $order->update_meta_data('_processing_fee_percentage', $fee_percentage);

        // Calculate base amount (subtotal - discounts + shipping)
        $subtotal = $order->get_subtotal();
        $discount = $order->get_total_discount();
        $shipping = $order->get_shipping_total();
        $subtotal_after_discount = $subtotal - $discount;
        $base_amount = $subtotal_after_discount + $shipping;

        // Calculate fee amount
        $fee_amount = ($base_amount * $fee_percentage) / 100;

        // Create and add new fee with percentage in the name
        $fee = new WC_Order_Item_Fee();
        $fee->set_name('Fee de Procesamiento (' . number_format($fee_percentage, 2, '.', '') . '%)');
        $fee->set_amount($fee_amount);
        $fee->set_total($fee_amount);
        $fee->set_tax_status('taxable');                   // 'taxable' or 'none' (change here)

        // Add fee to order
        $order->add_item($fee);

        // Recalculate totals
        $order->calculate_totals();
    } else {
        // Remove fee percentage if amount is 0 or empty
        $order->delete_meta_data('_processing_fee_percentage');

        // Recalculate totals
        $order->calculate_totals();
    }

    // Save order
    $order->save();
}

/**
 * Note: WooCommerce automatically displays all fees (WC_Order_Item_Fee) in the order totals.
 * The fee will appear as the name we set in line 182: "Tarifa de Procesamiento"
 * No additional display hook is needed.
 */
