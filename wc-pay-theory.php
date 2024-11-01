<?php

/**
 * Plugin Name: WC Pay Theory
 * Plugin URI: https://www.paytheory.com/
 * Description: A plugin enabling Pay Theory as a payment gateway for WooCommerce.
 * Version: 1.0.4
 *
 * WC requires at least: 6.0
 * WC tested up to: 6.2
 *
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/*
Pay Theory Payment Gateway is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Pay Theory Payment Gateway is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Pay Theory Payment Gateway. If not, see https://www.gnu.org/licenses/gpl-3.0.html.
*/

// Try to prevent direct access data leaks. Add this line of code after the opening PHP tag in each PHP file:
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

// This action hook registers our PHP class as a WooCommerce payment gateway
add_action( 'plugins_loaded', 'paytheory_init_gateway_class' );

// Add WC_Paytheory_Gateway as WC gateway
add_filter( 'woocommerce_payment_gateways', 'add_payment_gateway');

/**
 * Register PHP class as a WooCommerce payment gateway
 */
function paytheory_init_gateway_class() {
    if( class_exists( 'WC_Payment_Gateway' ) ) {
        require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-pay-theory-payment-gateway.php';
    }
}

/**
 * Add WC_Paytheory_Gateway as a WooCommerce payment gateway
 */
function add_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Paytheory_Gateway';
    return $gateways;
}