<?php
defined('ABSPATH') or die();


class WC_Gateway_Instamojo_MPSW extends WC_Payment_Gateway
{

    /**
     * Class constructor
    */
    public function __construct()
    {
        global $woocommerce;
        $this->id                 = 'is-instamojo'; // payment gateway plugin ID
        $this->icon               = WL_MPSW_PLUGIN_URL.'assets/images/instamojo.png';
        $this->has_fields         = true; // in case you need a custom credit card form
        $this->method_title       = 'IS Instamojo Gateway';
        $this->method_description = esc_html__('Direct payment via Instamojo. Instamojo accepts Credit / Debit Cards, Netbanking.', 'multiple-payment-solutions-for-woocommerce').' '.wp_kses_post('<a href="#" target="_blank">See how to configure.</a>'); // will be displayed on the options page

        // gateways can support subscriptions, refunds, saved payment methods,
        // but in this tutorial we begin with simple payments
        $this->supports = array(
            'products',
            'refunds'
          );
        $this->liveurl  = 'https://www.instamojo.com/api/1.1/';
        $this->testurl  = 'https://test.instamojo.com/api/1.1/';

        // Method with all the options fields
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        if (get_option('woocommerce_currency') == 'INR') {
            $wl_mpsw_instamojo_enabled = $this->get_option('enabled');
        } else {
            $wl_mpsw_instamojo_enabled = 'no';
        }

        $this->enabled	       = $wl_mpsw_instamojo_enabled;
        $this->title           = $this->get_option('title') ? $this->get_option('title') : 'Instamojo Credit/Debit Card Payment';
        $this->description     = $this->get_option('description');
        $this->testmode        = 'yes' === $this->get_option('testmode');
        $this->private_key     = $this->testmode ? $this->get_option('test_private_key') : $this->get_option('live_private_key');
        $this->publishable_key = $this->testmode ? $this->get_option('test_auth_token') : $this->get_option('live_auth_token');

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));

        // We need custom JavaScript to obtain a token
        add_action('wp_enqueue_scripts', array( $this, 'payment_scripts' ));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
        add_action('woocommerce_receipt_' . $this->id, array( $this, 'wl_mpsw_payment_receipt_page' ));

        // You can also register a webhook here
        add_action('woocommerce_api_wl_instamojo', array( $this, 'webhook' ));
    }

    /**
     * Plugin options
        */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => esc_html__('Enable/Disable', 'multiple-payment-solutions-for-woocommerce'),
                'label'       => esc_html__('Enable Instamojo Gateway', 'multiple-payment-solutions-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => esc_html__('Title', 'multiple-payment-solutions-for-woocommerce'),
                'type'        => 'text',
                'description' => esc_html__('This controls the title which the user sees during checkout.', 'multiple-payment-solutions-for-woocommerce'),
                'default'     => 'Instamojo',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => esc_html__('Description', 'multiple-payment-solutions-for-woocommerce'),
                'type'        => 'textarea',
                'description' => esc_html__('This controls the description which the user sees during checkout.', 'multiple-payment-solutions-for-woocommerce'),
                'default'     => 'Pay with your credit card via our super-cool payment gateway.',
            ),
            'testmode' => array(
                'title'       => esc_html__('Test mode', 'multiple-payment-solutions-for-woocommerce'),
                'label'       => esc_html__('Enable Test Mode', 'multiple-payment-solutions-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => esc_html__('Place the payment gateway in test mode using test API keys.', 'multiple-payment-solutions-for-woocommerce'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'test_auth_token' => array(
                'title'       => esc_html__('Test Private Auth Token', 'multiple-payment-solutions-for-woocommerce'),
                'type'        => 'text'
            ),
            'test_private_key' => array(
                'title'        => esc_html__('Test Private API Key', 'multiple-payment-solutions-for-woocommerce'),
                'type'         => 'password',
            ),
            'live_auth_token'  => array(
                'title'        => esc_html__('Live Private Auth Token', 'multiple-payment-solutions-for-woocommerce'),
                'type'         => 'text'
            ),
            'live_private_key' => array(
                'title'        => esc_html__('Live Private API Key', 'multiple-payment-solutions-for-woocommerce'),
                'type'         => 'password'
            )
        );
    }

    /**
    * WP Admin Options admin_options()
    *
    */
    public function admin_options()
    {
        ?>
    	<h3><?php esc_html_e('Instamojo ', 'multiple-payment-solutions-for-woocommerce'); ?></h3>
    	<p><?php esc_html_e('Instamojo works by sending the user to Instamojo payment popup box to complete their payment process. Note that Instamojo will only take payments in Indian Rupee(INR).', 'multiple-payment-solutions-for-woocommerce'); ?></p>
		<?php
            if (get_option('woocommerce_currency') == 'INR') {
                ?>
				<table class="form-table">
					<?php $this->generate_settings_html(); ?>
				</table>
			<?php
            } else {
                ?>
				<div class="inline error">
					<p><strong><?php esc_html_e('Instamojo  Gateway Disabled', 'multiple-payment-solutions-for-woocommerce'); ?></strong>
						<?php echo sprintf(esc_html__('Choose Indian Rupee (Rs.) as your store currency in
						<a href="%s">Pricing Options</a> to enable the Instamojo WooCommerce payment gateway.', 'multiple-payment-solutions-for-woocommerce'), admin_url('admin.php?page=wc-settings')); ?>
					</p>
				</div>
				<?php
            } // End check currency
    } // End WP Admin Options admin_options()

    /**
    * Build the form after click on Instamojo Paynow button wl_mpsw_generate_instamojo_form()
    *
    */
    private function wl_mpsw_generate_instamojo_form($order_id)
    {
        $this->wl_mpsw_payment_clear_cache();
        global $wp;
        global $woocommerce;

        $this->log(esc_html__("Creating Instamojo Order for order id: $order_id", 'multiple-payment-solutions-for-woocommerce'));
        $xclient_id     = $this->truncate_secret($this->private_key);
        $xclient_secret = $this->truncate_secret($this->publishable_key);
        $this->log(esc_html__("Client ID: $xclient_id | Client Secret: $xclient_secret | Testmode: $this->testmode", 'multiple-payment-solutions-for-woocommerce'));

        $order = new WC_Order($order_id);
        $txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
        update_post_meta($order_id, '_transaction_id', $txnid);
        try {
            if ($this->testmode) {
                $requested_url = $this->testurl;
            } else {
                $requested_url = $this->liveurl;
            }

            $order_data = array(
                "purpose"      => time()."-". $order_id,
                "amount"       => $order->get_total(),
                'phone'        => $order->get_billing_phone(),
                'buyer_name'   => substr(trim((html_entity_decode($order->get_billing_first_name() ." ".$order->get_billing_last_name(), ENT_QUOTES, 'UTF-8'))), 0, 20),
                'redirect_url' => get_site_url(),
                "send_email"   => true,
                "email"        => $order->get_billing_email(),
            );
            $this->log(esc_html__("Data sent for creating order ", 'multiple-payment-solutions-for-woocommerce').''.print_r($order_data, true));

            $header = array(
                'X-Api-key'    => $this->private_key,
                'X-Auth-Token' => $this->publishable_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            );

            $args = array(
                'method'      => 'POST',
                'body'        => $order_data,
                'timeout'     => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => $header,
                'cookies'     => array(),
                'cainfo'      => WL_MPSW_PLUGIN_DIR_PATH . 'admin/instamojo/lib/cacert.pem'
            );

            $response = wp_remote_request($requested_url.'payment-requests/', $args);
            $response = wp_remote_retrieve_body($response);
            $response = json_decode($response, true);

            $this->log(esc_html__("Response from server on creating order", 'multiple-payment-solutions-for-woocommerce').' '.print_r($response, true));

            if ($response['success'] == true) {
                WC()->session->set('payment_request_id', $response['payment_request']['id']);
                return esc_html__('<button type="button" id="wl_sms_intamojo_pay_btn">Pay Now</button>
                <script>
                jQuery(document).ready(function () {
                    "use strict";
                    jQuery(document).on("click", "#wl_sms_intamojo_pay_btn", function (e) {
                        e.preventDefault();
                        Instamojo.open("'.$response['payment_request']['longurl'].'");
                    });
                });
                </script>');
            } else {
                return esc_html__('Response from server:'.$response['message'].'', 'multiple-payment-solutions-for-woocommerce');
                $this->log(esc_html__("An error occurred, Response from server:  " . $response['message'] . "", 'multiple-payment-solutions-for-woocommerce'));
            }
        } catch (Exception $e) {
            print('Error: ' . $e->getMessage());
        }
    }

    /**
     * You will need it if you want your custom credit card form, Step 4 is about it
     */
    public function payment_fields()
    {
        // ok, let's display some description before the payment form
        if ($this->description) {
            // you can instructions for test mode, I mean test card numbers etc.
            if ($this->testmode) {
                $this->description .= esc_html__('TEST MODE ENABLED. In test mode, you can use the card numbers listed in ', 'multiple-payment-solutions-for-woocommerce').''.wp_kses_post('<a href="https://support.instamojo.com/hc/en-us/articles/208485675-Test-or-Sandbox-Account" target="_blank" rel="noopener noreferrer">documentation</a>.');
                $this->description  = trim($this->description);
            }
            // display the description with <p> tags etc.
            echo wpautop(wp_kses_post($this->description));
        }
    }

    /*
        * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
        */
    public function payment_scripts()
    {
        // we need JavaScript to process a token only on cart/checkout pages, right?
        if (! is_cart() && ! is_checkout() && ! isset($_GET['pay_for_order'])) {
            return;
        }

        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ('no' === $this->enabled) {
            return;
        }

        // no reason to enqueue JavaScript if API keys are not set
        if (empty($this->private_key) || empty($this->publishable_key)) {
            return;
        }

        // do not work with card detailes without SSL unless your website is in a test mode
        if (! $this->testmode && ! is_ssl()) {
            return;
        }

        // let's suppose it is our payment processor JavaScript that allows to obtain a token
        wp_enqueue_script('instamojo-checkout', 'https://js.instamojo.com/v1/checkout.js', array( 'jquery' ));
    }

    /*
        * Fields validation, more in Step 5
        */
    public function validate_fields()
    {
        if (empty($_POST[ 'billing_first_name' ])) {
            wc_add_notice(esc_html__('First name is required!', 'multiple-payment-solutions-for-woocommerce'), 'error');
            return false;
        }
        if (empty($_POST[ 'billing_last_name' ])) {
            wc_add_notice(esc_html__('Last name is required!', 'multiple-payment-solutions-for-woocommerce'), 'error');
            return false;
        }
        if (empty($_POST[ 'billing_email' ])) {
            wc_add_notice(esc_html__('Email is required!', 'multiple-payment-solutions-for-woocommerce'), 'error');
            return false;
        }
        return true;
    }

    /*
        * We're processing the payments here, everything about it is in Step 5
        */
    public function process_payment($order_id)
    {
        $this->wl_mpsw_payment_clear_cache();
        global $woocommerce;
        $order = new WC_Order($order_id);
        return array(
            'result' 	=> 'success',
            'redirect'	=> $order->get_checkout_payment_url(true)
        );
    }

    /**
     * Page after cheout button and redirect to Instamojo payment page wl_mpsw_payment_receipt_page()
     *
     */
    public function wl_mpsw_payment_receipt_page($order_id)
    {
        $this->wl_mpsw_payment_clear_cache();
        global $woocommerce;
        $order = new WC_Order($order_id);
        printf('<h3>%1$s</h3>', __('Thank you for your order, please click the button below to Pay with Instamojo.', 'multiple-payment-solutions-for-woocommerce'));
        _e($this->wl_mpsw_generate_instamojo_form($order_id));
    } // Cheout button and redirect wl_mpsw_payment_receipt_page() end

    /**
    * Clear cache for the previous value wl_mpsw_payment_clear_cache()
    *
    */
    public function wl_mpsw_payment_clear_cache()
    {
        header("Pragma: no-cache");
        header("Cache-Control: no-cache");
        header("Expires: 0");
    } // Clear cache for the previous value wl_mpsw_payment_clear_cache() end

    /**
    * Process refund call process_refund()
    *
    */
    public function process_refund($order_id, $amount = null, $reason='')
    {
        $this->wl_mpsw_payment_clear_cache();
        global $woocommerce;
        $order = new WC_Order($order_id);

        if (empty($reason)) {
            $reason = esc_html__('Customer isn\'t satisfied with the quality', 'multiple-payment-solutions-for-woocommerce');
        }

        try {
            if ($this->testmode) {
                $requested_url = $this->testurl;
            } else {
                $requested_url = $this->liveurl;
            }

            $payment_id     = get_post_meta($order_id, '_insta_paymrnt_id', true);
            $transaction_id = get_post_meta($order_id, '_transaction_id', true);

            $order_data     = array(
                'transaction_id' => $order->get_transaction_id(),
                'payment_id'     => $payment_id,
                'type'           => 'QFL',
                'refund_amount'  => $amount,
                'body'           => $reason
            );

            $header = array(
                'X-Api-key'    => $this->private_key,
                'X-Auth-Token' => $this->publishable_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            );

            $args = array(
                'method'      => 'POST',
                'body'        => $order_data,
                'timeout'     => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => $header,
                'cookies'     => array(),
                'cainfo'      => WL_MPSW_PLUGIN_DIR_PATH . 'admin/instamojo/lib/cacert.pem'
            );

            $response = wp_remote_request($requested_url.'refunds/', $args);
            $response = wp_remote_retrieve_body($response);
            $response = json_decode($response, true);

            if (! empty($response) && $response['status'] == 'success') {
                if ($response['refunds']['status'] == 'Refunded' || $response['refunds']['status'] == 'Closed') {
                    $refund_note =  sprintf(__('Refund: %1$s %2$s<br>Paytm Refund ID: %3$s<br>Reference ID: %4$s', 'multiple-payment-solutions-for-woocommerce'), $amount, get_option('woocommerce_currency'), $response['refunds']['id']);
                    $order->add_order_note($refund_note);
                    return true;
                } else {
                    return new WP_Error('error', esc_html__('Not Refunded', 'multiple-payment-solutions-for-woocommerce'));
                }
            } else {
                return new WP_Error('error', esc_html__(isset($response['message']), 'multiple-payment-solutions-for-woocommerce'));
            }
        } catch (Exception $e) {
            return new WP_Error('error', $e->getMessage());
        }
    }// Process refund call process_refund() end

    /*
        * In case you need a webhook, like PayPal IPN etc
        */
    public function webhook()
    {
        $order = wc_get_order(sanitize_text_field($_GET['id']));
        $order->payment_complete();
        $order->reduce_order_stock();
        update_option('webhook_debug', sanitize_text_field($_GET));
    }

    public function log($message)
    {
        wl_mpsw_insta_log($message);
    }

    public function truncate_secret($secret)
    {
        return wl_mpsw_truncate_secret($secret);
    }
} // end \WC_Gateway_Instamojo_MPSW class
