<?php
/*
Plugin Name: Multiple Payment Solutions for WooCommerce
Plugin URI: https://wordpress.org/plugins/multiple-payment-solutions-for-woocommerce/
Description: Multiple Payment Solutions for WooCommerce plugin provide you multiple payment options, so you can accept payments using any payment method - Credit / Debit Cards payment options from WP admin developed by vibhorp.
Author: vibhorp
Author URI: https://www.infigosoftware.in/
Version: 1.04
Text Domain: multiple-payment-solutions-for-woocommerce
Domain Path: /lang/
*/

defined('ABSPATH') or die();

if (!defined('WL_MPSW_PLUGIN_URL')) {
    define('WL_MPSW_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('WL_MPSW_PLUGIN_DIR_PATH')) {
    define('WL_MPSW_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
}

if (!defined('WL_MPSW_PLUGIN_BASENAME')) {
    define('WL_MPSW_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

if (!defined('WL_MPSW_PLUGIN_FILE')) {
    define('WL_MPSW_PLUGIN_FILE', __FILE__);
}

if (!defined('WL_MPSW_STRIPE_VERSION')) {
    define('WL_MPSW_STRIPE_VERSION', '7.14.2');
}


/**
 * WooCommerce fallback notice.
 *
 */
function wl_mpsw_woocommerce_missing_wc_notice() {
    /* translators: 1. URL link. */
    echo '<div class="error"><p><strong>' . sprintf(esc_html__('Multiple Payment Solutions for WooCommerce to be installed and active. You can download %s here.', 'woocommerce-gateway-stripe'), '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
}

/**
 * initialize Instamojo Gateway Class
 */
function wl_mpsw_offline_gateway_init() {
    load_plugin_textdomain('multiple-payment-solutions-for-woocommerce', false, basename(WL_MPSW_PLUGIN_DIR_PATH) . '/lang');

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wl_mpsw_woocommerce_missing_wc_notice');
        return;
    }

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require('admin/instamojo/class-instamojo-payments.php');
    require('admin/paytm/class-paytm-payments.php');
    require('admin/payumoney/class-payu-payments.php');
    require('admin/cashfree/class-cashfree-payments.php');

    add_filter('woocommerce_currencies', 'wl_mpsw_paytm_add_indian_rupee');
    add_filter('woocommerce_currency_symbol', 'wl_mpsw_paytm_add_indian_rupee_currency_symbol', 10, 2);
    add_action('admin_post_nopriv_wl_mpsw_rzp_wc_webhook', 'wl_mpsw_razorpay_webhook_init', 10);
}
add_action('plugins_loaded', 'wl_mpsw_offline_gateway_init', 11);

/**
 * look for redirect from instamojo.
 */
function wl_mpsw_init_instamojo_payment_gateway_redirect() {
    if (isset($_REQUEST['payment_id']) && isset($_REQUEST['payment_request_id']) && isset($_REQUEST['payment_status'])) {
        include_once "admin/instamojo/payment-confirm.php";
    }
}
add_action('template_redirect', 'wl_mpsw_init_instamojo_payment_gateway_redirect');

/**
 * Add Gateway class to all payment gateway methods
 */
function wl_mpsw_add_gateway_class($methods) {
    $methods[] = 'WC_Gateway_Instamojo_MPSW';
    $methods[] = 'WC_Gateway_Paytm_MPSW';
    $methods[] = 'WC_Gateway_PayUmoney_MPSW';
    $methods[] = 'WC_Gateway_CashFree_MPSW';
    return $methods;
}
add_filter('woocommerce_payment_gateways', 'wl_mpsw_add_gateway_class');

/**
 * To generate log
 */
function wl_mpsw_insta_log($message) {
    $log = new WC_Logger();
    $log->add('IS Instamojo', $message);
}

/**
 * function for truncating client_id and client_secret
 */
function wl_mpsw_truncate_secret($secret) {
    return substr($secret, 0, 4) . str_repeat('x', 10);
}

/**
 * add Indian rupee wl_mpsw_paytm_add_indian_rupee()
 *
 */
function wl_mpsw_paytm_add_indian_rupee($currencies) {
    $currencies['INR'] = esc_html__('Indian Rupee', 'woocommerce');
    return $currencies;
} // add indian rupee wl_mpsw_paytm_add_indian_rupee() end

/**
 * Add Indian rupee currency symbol if not exists wl_mpsw_paytm_add_indian_rupee_currency_symbol()
 *
 */
function wl_mpsw_paytm_add_indian_rupee_currency_symbol($currency_symbol, $currency) {
    switch ($currency) {
        case 'INR':
            $currency_symbol = 'Rs.';
            break;
    }
    return $currency_symbol;
} // Add Indian rupee currency symbol if not exists wl_mpsw_paytm_add_indian_rupee_currency_symbol() end

// This is set to a priority of 10
function wl_mpsw_razorpay_webhook_init() {
    $rzpWebhook = new RZP_Webhook();

    $rzpWebhook->process();
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wl_mpsw_add_action_links');
function wl_mpsw_add_action_links($links) {
    $mylinks = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '"><b>Settings</b></a>',
    );
    return array_merge($mylinks, $links);
}
