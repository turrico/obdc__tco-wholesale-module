<?php
function add_b2b_user_role() {
    add_role('b2b_customer', 'B2B Customer', array('read' => true));

    $role = get_role('b2b_customer');
}

add_action('init', 'add_b2b_user_role');

function redirect_b2b_from_admin() {
    if (current_user_can('b2b_customer') && is_admin()) {
        wp_redirect(home_url());
        exit;
    }
}
add_action('admin_init', 'redirect_b2b_from_admin');
