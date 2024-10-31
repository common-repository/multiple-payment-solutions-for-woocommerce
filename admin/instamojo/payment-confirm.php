<?php

defined('ABSPATH') or die();
$payment_id         = sanitize_text_field($_GET['payment_id']);
$payment_request_id = sanitize_text_field($_GET['payment_request_id']);

wl_mpsw_insta_log(esc_html__("Callback Called with payment ID: $payment_id and payment req id: $payment_request_id", 'multiple-payment-solutions-for-woocommerce'));

if (!isset($payment_id) or !isset($payment_request_id)) {
    wl_mpsw_insta_log(esc_html__("Callback Called without  payment ID or payment req id exittng..", 'multiple-payment-solutions-for-woocommerce'));
    wp_redirect(get_site_url());
}

$stored_payment_req_id = WC()->session->get('payment_request_id');
if ($stored_payment_req_id != $payment_request_id) {
    wl_mpsw_insta_log(esc_html__("Given Payment request id not matched with stored payment request id: $stored_payment_req_id ", 'multiple-payment-solutions-for-woocommerce'));
    wp_redirect(get_site_url());
}

try {
    $Instamojo_object = new WC_Gateway_Instamojo_MPSW();
    $testmode         = 'yes' === $Instamojo_object->get_option('testmode', 'no');
    $testurl          = 'https://test.instamojo.com/api/1.1/';
    $liveurl          = 'https://www.instamojo.com/api/1.1/';
    $client_id        = $Instamojo_object->private_key;
    $client_secret    = $Instamojo_object->publishable_key;
    $xclient_id       = $Instamojo_object->truncate_secret($client_id);
    $xclient_secret   = $Instamojo_object->truncate_secret($client_secret);
    wl_mpsw_insta_log(esc_html__("Client ID: $xclient_id | Client Secret: $xclient_secret | Testmode: $testmode ", 'multiple-payment-solutions-for-woocommerce'));

    if ($testmode) {
        $requested_url = $testurl;
    } else {
        $requested_url = $liveurl;
    }

    $header = array(
        'X-Api-key'    => $client_id,
        'X-Auth-Token' => $client_secret,
        'Content-Type' => 'application/x-www-form-urlencoded',
    );

    $args = array(
        'method'      => 'GET',
        'body'        => array(),
        'timeout'     => '5',
        'redirection' => '5',
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => $header,
        'cookies'     => array(),
        'cainfo'      => WL_MPSW_PLUGIN_DIR_PATH . 'admin/instamojo/lib/cacert.pem'
    );

    $response = wp_remote_request($requested_url . 'payment-requests/' . $payment_request_id, $args);
    $response = wp_remote_retrieve_body($response);
    $response = json_decode($response, true);

    wl_mpsw_insta_log(esc_html__("Response from server: ", 'multiple-payment-solutions-for-woocommerce') . '' . print_r($response, true));

    if ($response['success'] == true) {
        $payment_status1 = $response['payment_request']['status'];
        $order_id        = $response['payment_request']['purpose'];
        wl_mpsw_insta_log(esc_html__("Payment Request status for $payment_request_id is $payment_status1", 'multiple-payment-solutions-for-woocommerce'));

        $payment_status2 = wp_remote_request($requested_url . 'payments/' . $payment_id, $args);
        $payment_status2 = wp_remote_retrieve_body($payment_status2);
        $payment_status2 = json_decode($payment_status2, true);
        $payment_status2 = $payment_status2['payment']['status'];
        wl_mpsw_insta_log(esc_html__("Payment status for $payment_id is $payment_status2", 'multiple-payment-solutions-for-woocommerce'));

        if ($payment_status1 == 'Completed') {
            $order_id = explode("-", $order_id);
            $order_id = $order_id[1];
            wl_mpsw_insta_log(esc_html__("Extracted order id from trasaction_id: ", 'multiple-payment-solutions-for-woocommerce') . '' . $order_id);
            $order = new WC_Order($order_id);

            if ($order) {
                if ($payment_status2 == "Credit") {
                    wl_mpsw_insta_log(esc_html__("Payment for $payment_id was credited.", 'multiple-payment-solutions-for-woocommerce'));
                    $order->payment_complete($payment_request_id);
                    update_post_meta($order_id, '_insta_paymrnt_id', $payment_id);
                    wp_safe_redirect($Instamojo_object->get_return_url($order));
                } else {
                    wl_mpsw_insta_log(esc_html__("Payment for $payment_id failed.", 'multiple-payment-solutions-for-woocommerce'));
                    $order->cancel_order(esc_html__('Unpaid order cancelled - Instamojo Returned Failed Status for payment Id ' . $payment_id, 'multiple-payment-solutions-for-woocommerce'));
                    global $woocommerce;
                    wp_safe_redirect($woocommerce->cart->get_cart_url());
                }
            } else {
                wl_mpsw_insta_log(esc_html__("Order not found with order id $order_id", 'multiple-payment-solutions-for-woocommerce'));
            }
        } elseif ($payment_status1 == 'Pending') {
            wl_mpsw_insta_log(esc_html__("Order payment is still pending $order_id", 'multiple-payment-solutions-for-woocommerce'));
        }
    } else {
        $payment_status = $response['status'];
        $error_message  = $response['message'];
        wl_mpsw_insta_log(esc_html__("Payment status for $payment_id is $payment_status and error message: $error_message", 'multiple-payment-solutions-for-woocommerce'));
    }
} catch (Exception $e) {
    wl_mpsw_insta_log($e->getMessage());
}
