<?php
defined('ABSPATH') or die();

include('lib/encdec_payu.php');

class WC_Gateway_PayUmoney_MPSW extends WC_Payment_Gateway
{
    /**
    * construct function for this plugin __construct()
    *
    */
    public function __construct()
    {
        global $woocommerce;
        $this->id				  = 'wc_gateway_payu_mpsw';
        $this->method_title       = esc_html__('IS PayUmoney Gateway', 'multiple-payment-solutions-for-woocommerce');
        $this->method_description = esc_html__('PayUmoney is a trusted way to payment in India (INR)', 'multiple-payment-solutions-for-woocommerce').' '.wp_kses_post('<a href="#" target="_blank">See how to configure.</a>');
        $this->icon 			  = WL_MPSW_PLUGIN_URL.'assets/images/icon.png';
        $this->has_fields 		  = true;
        $this->supports           = array('refunds');
        $this->liveurl			  = 'https://secure.payu.in/_payment';
        $this->testurl			  = 'https://test.payu.in/_payment';
        $this->init_form_fields();
        $this->init_settings();
        $this->responseVal		  = '';

        /* Create IPN Log files for backup transaction details */
        $uploads                  = wp_upload_dir();
        $this->txn_log            = $uploads['basedir']."/wl_mpsw_log/payu";
        wp_mkdir_p($this->txn_log);

        /* Check selected currency */
        if (get_option('woocommerce_currency') == 'INR') {
            $wl_mpsw_payu_enabled = $this->settings['enabled'];
        } else {
            $wl_mpsw_payu_enabled = 'no';
        }

        $this->enabled	= $wl_mpsw_payu_enabled;
        $this->testmode	= $this->settings['testmode'];

        if (isset($this->settings['thank_you_message'])) {
            $this->thank_you_message = $this->settings['thank_you_message'];
        }

        if ('yes'==$this->testmode) {
            $this->title 	   = esc_html__('Sandbox PayUmoney', 'multiple-payment-solutions-for-woocommerce');
            $this->description = wp_kses_post('card number: <strong>4012001037141112</strong><br>'."\n"
            .'card CVV: <strong>123</strong><br>'."\n"
            .'expiry Date: <strong>05/20</strong><br>'."\n"
            .'<a href="https://www.payumoney.com/dev-guide/development/testmode.html" target="_blank">Development Guide</a><br>'."\n");
            if ($this->settings['merchantid']!='' ||  $this->settings['salt']!='') {
                $this->merchantid = $this->settings['merchantid'];
                $this->salt   	  = $this->settings['salt'];
            }
        } else {
            $this->title 	   = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->merchantid  = $this->settings['merchantid'];
            $this->salt   	   = $this->settings['salt'];
        }

        if (isset($_GET['wl_mpsw_payu_callback']) && isset($_GET['results']) && isset($_GET['wl_mpsw_payu_callback'])==1 && isset($_GET['results']) != '') {
            $this->responseVal = $_GET['results'];
            add_filter('woocommerce_thankyou_order_received_text', array($this, 'wl_mpsw_payu_thankyou'));
        }
        add_action('init', array(&$this, 'wl_mpsw_payu_transaction'));
        add_action('woocommerce_api_'.strtolower(get_class($this)), array( $this, 'wl_mpsw_payu_transaction' ));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
        add_action('woocommerce_receipt_' . $this->id, array( $this, 'wl_mpsw_payu_receipt_page' ));
    } // End Constructor

    /**
    * init Gateway Form Fields
    *
    * @since 1.0
    */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'			=> esc_html__('Enable/Disable:', 'multiple-payment-solutions-for-woocommerce'),
                'type'			=> 'checkbox',
                'label' 		=> esc_html__('Enable PayUmoney', 'multiple-payment-solutions-for-woocommerce'),
                'default'		=> 'yes'
            ),
            'title' => array(
                'title' 		=> esc_html__('Title:', 'multiple-payment-solutions-for-woocommerce'),
                'type' 			=> 'text',
                'description'	=> esc_html__('This controls the title which the user sees during checkout.', 'multiple-payment-solutions-for-woocommerce'),
                'default' 		=> esc_html__('PayUmoney', 'multiple-payment-solutions-for-woocommerce')
            ),
            'description' => array(
                'title' 		=> esc_html__('Description:', 'multiple-payment-solutions-for-woocommerce'),
                'type' 			=> 'textarea',
                'description' 	=> esc_html__('This controls the title which the user sees during checkout.', 'multiple-payment-solutions-for-woocommerce'),
                'default' 		=> esc_html__('Direct payment via PayUmoney. PayUmoney accepts VISA, MasterCard, Debit Cards and the Net Banking of all major banks.', 'multiple-payment-solutions-for-woocommerce'),
            ),
            'merchantid' => array(
                'title' 		=> esc_html__('Merchant Key:', 'multiple-payment-solutions-for-woocommerce'),
                'type' 			=> 'text',
                'custom_attributes' => array( 'required' => 'required' ),
                'description' 	=> esc_html__('This key is generated at the time of activation of your site and helps to uniquely identify you to PayUmoney', 'multiple-payment-solutions-for-woocommerce'),
                'default' 		=> ''
            ),
            'salt' => array(
                'title' 		=> esc_html__('SALT:', 'multiple-payment-solutions-for-woocommerce'),
                'type'	 		=> 'text',
                'custom_attributes' => array( 'required' => 'required' ),
                'description' 	=> esc_html__('String of characters provided by PayUmoney', 'multiple-payment-solutions-for-woocommerce'),
                'default' 		=> ''
            ),
            'testmode' => array(
                'title' 		=> esc_html__('Mode of transaction:', 'multiple-payment-solutions-for-woocommerce'),
                'type' 			=> 'select',
                'label' 		=> esc_html__('PayUindia Tranasction Mode.', 'multiple-payment-solutions-for-woocommerce'),
                'options' 		=> array('yes'=>'Test / Sandbox Mode','no'=>'Live Mode' ),
                'default' 		=> 'no',
                'description' 	=> esc_html__('Mode of PayUindia activities'),
                'desc_tip' 		=> true
                ),
            'thank_you_message' => array(
                'title' 		=> esc_html__('Thank you page message:', 'multiple-payment-solutions-for-woocommerce'),
                'type' 			=> 'textarea',
                'description' 	=> esc_html__('Thank you page order success message when order has been received', 'multiple-payment-solutions-for-woocommerce'),
                'default' 		=> esc_html__('Thank you. Your order has been received.', 'multiple-payment-solutions-for-woocommerce'),
                ),
            );
    } // function init_form_fields() end

    public function admin_options()
    {
        ?>
<h3><?php _e('IS PayUmoney Gateway', 'multiple-payment-solutions-for-woocommerce'); ?>
</h3>
<p><?php _e('PayUmoney works by sending the user to PayUmoney to enter their payment information. Note that PayUmoney will only take payments in Indian Rupee.', 'multiple-payment-solutions-for-woocommerce'); ?>
</p>
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
	<p><strong><?php _e('IS PayUmoney Gateway Disabled', 'multiple-payment-solutions-for-woocommerce'); ?></strong>
		<?php echo sprintf(__('Choose Indian Rupee (Rs.) as your store currency in <a href="%s">Pricing Options</a> to enable the PayUmoney WooCommerce payment gateway', 'multiple-payment-solutions-for-woocommerce'), admin_url('admin.php?page=wc-settings')); ?>
	</p>
</div>
<?php
            } // End check currency
    } // function admin_options() end

    /**
     * Build the form after click on PayUmoney button.
     *
     * @since 1.0
     */
    private function wl_mpsw_generate_payu_form($order_id)
    {
        global $woocommerce;
        $order       = new WC_Order($order_id);
        $txnid       = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
        $productinfo = sprintf(__('Order ID:'.$order_id.' from ', 'multiple-payment-solutions-for-woocommerce'), $order_id) . get_bloginfo('name');

        $hash_data['key']		  = $this->merchantid;
        $hash_data['txnid'] 	  = $txnid;
        $hash_data['amount'] 	  = $order->get_total();
        $hash_data['productinfo'] = $productinfo;
        $hash_data['firstname']	  = $order->get_billing_first_name();
        $hash_data['email'] 	  = $order->get_billing_email();
        $hash_data['phone'] 	  = $order->get_billing_phone();
        $hash_data['udf5'] 		  = "WooCommerce_v_3.x_BOLT";
        $hash_data['hash'] 		  = PayUmoneyPayment::wl_mpsw_calculate_hash_before_transaction($this->salt, array_filter($hash_data));

        update_post_meta($order_id, '_transaction_id', $txnid);
        $returnURL         = $woocommerce->api_request_url(strtolower(get_class($this)));
        $wl_mpsw_payu_args = array(
            'key'			   => $this->merchantid,
            'surl'			   => $returnURL,
            'furl'			   => esc_url_raw($order->get_checkout_payment_url(false)),
            'curl'			   => esc_url_raw($order->get_checkout_payment_url(false)),
            'firstname'		   => $order->get_billing_first_name(),
            'lastname'		   => $order->get_billing_last_name(),
            'email'			   => $order->get_billing_email(),
            'address1'		   => $order->get_billing_address_1(),
            'address2'		   => $order->get_billing_address_2(),
            'city'			   => $order->get_billing_city(),
            'state'			   => $order->get_billing_state(),
            'zipcode'		   => $order->get_billing_postcode(),
            'country'		   => $order->get_billing_country(),
            'phone' 	       => $order->get_billing_phone(),
            'service_provider' => 'payu_paisa',
            'productinfo'	   => $productinfo,
            'amount'		   => $order->get_total()
        );

        $wl_mpsw_payu_args = array_filter($wl_mpsw_payu_args);
        $payuform         = '';

        foreach ($wl_mpsw_payu_args as $key => $value) {
            if ($value) {
                $payuform .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
            }
        }
        $payuform .= '<input type="hidden" name="txnid" value="' . $txnid . '" />' . "\n";
        $payuform .= '<input type="hidden" name="udf5" value="' . $hash_data['udf5'] . '" />' . "\n";
        $payuform .= '<input type="hidden" name="hash" value="' . $hash_data['hash'] . '" />' . "\n";

        $posturl = $this->liveurl;
        if ($this->testmode == 'yes') {
            $posturl = $this->testurl;
        }

        return '<form action="' . $posturl . '" method="POST" name="wl_mpsw_payform" id="wl_mpsw_payform">
				' . $payuform . '
				<input type="submit" class="button" id="wl_mpsw_submit_payu_payment_form" value="' . esc_html__('Pay via PayUmoney', 'multiple-payment-solutions-for-woocommerce') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">'.__('Cancel order &amp; restore cart', 'multiple-payment-solutions-for-woocommerce') . '</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "'.__('Thank you for your order. We are now redirecting you to PayU to make payment.', 'multiple-payment-solutions-for-woocommerce').'",
								overlayCSS:
								{
									background: "#fff",
									opacity: 0.6
								},
								css: {
							        padding:        20,
							        textAlign:      "center",
							        color:          "#555",
							        border:         "3px solid #aaa",
							        backgroundColor:"#fff",
							        cursor:         "wait"
							    }
							});
							jQuery("#wl_mpsw_payform").attr("action","'.$posturl.'");
						jQuery("#wl_mpsw_submit_payu_payment_form").click();
					});
				</script>
			</form>';
    } // function wl_mpsw_generate_payu_form() end

    /**
     * Process the payment for checkout.
     */
    public function process_payment($order_id)
    {
        $this->wl_mpsw_payu_clear_cache();
        global $woocommerce;
        $order = new WC_Order($order_id);
        return array(
            'result' 	=> 'success',
            'redirect'	=> $order->get_checkout_payment_url(true)
        );
    } // function process_payment() end

    /**
     * Page after cheout button and redirect to PayU payment page.
     *
     * @since 1.0
     */
    public function wl_mpsw_payu_receipt_page($order_id)
    {
        $this->wl_mpsw_payu_clear_cache();
        global $woocommerce;
        $order = new WC_Order($order_id);
        echo '<p>' . esc_html__('Thank you for your order, please click the button below to pay with PayUmoney.', 'multiple-payment-solutions-for-woocommerce') . '</p>';
        echo $this->wl_mpsw_generate_payu_form($order_id);
    } // function wl_mpsw_payu_receipt_page() end

    /**
     * Clear the cache data for browser
     *
     * @since 1.0
     */
    private function wl_mpsw_payu_clear_cache()
    {
        header("Pragma: no-cache");
        header("Cache-Control: no-cache");
        header("Expires: 0");
    }// function wl_mpsw_payu_clear_cache() end

    /**
     * Check the status of current transaction and get response with $_POST
     *
     * @since 1.0
     */
    public function wl_mpsw_payu_transaction()
    {
        global $woocommerce;
        $order_id = absint(WC()->session->get('order_awaiting_payment'));
        $order    = new WC_Order($order_id);
        $salt     = $this->salt;
        if (! empty($_POST)) {
            $postData = sanitize_post($_POST);
            $ipn_txt  = json_encode(sanitize_text_field($_POST));
            $txn_file = $this->txn_log."/".sanitize_text_field($_POST['txnid']).".txt";
            $FILE     = fopen($txn_file, "w");
            fwrite($FILE, $ipn_txt);
            fclose($FILE);
        } else {
            die(__('No transaction data was passed!', 'multiple-payment-solutions-for-woocommerce'));
        }

        if (PayUmoneyPayment::wl_mpsw_check_hash_after_transaction($salt, $_POST)) {
            if (isset($this->postData['txnid']) && $this->postData['txnid']!='') {
                update_post_meta($order_id, '_payu_authorization_id', $this->postData['txnid']);
            }

            if ($postData['status'] == 'success') {
                $order->payment_complete();
                $order->add_order_note(wp_kses_post('PayUmoney payment successful.<br/>PayU Transaction id: '.$postData['txnid'].'<br/>Bank Ref: '.$postData['bank_ref_num'].'<br/>Transaction method: '.$postData['bankcode'].' ( '.$postData['mode'].' )'));
            } elseif (strtolower(PayUmoneyPayment::wl_mpsw_payu_transaction_verification($postData['txnid'])) == 'pending') {
                $order->update_status('on-hold');
                $order->payment_complete();
                $order->add_order_note(wp_kses_post('PayUmoney payment is pending.<br/>PayU Transaction id: '.$postData['txnid'].'<br/>Bank Ref: '.$postData['bank_ref_num'].'<br/>Transaction method:'.$postData['bankcode'].' ( '.$postData['mode'].' )'));
            } else {
                $order->update_status('failed');
                wc_add_notice(__('Error on payment: PayUmoney payment is failed', 'multiple-payment-solutions-for-woocommerce'), 'error');
                $order->add_order_note(wp_kses_post('PayUmoney payment is failed.<br/>PayU Transaction id: '.$postData['txnid']));
                wp_redirect($order->get_checkout_payment_url(false));
            }

            $results    = urlencode(base64_encode(json_encode($_POST)));
            $return_url = add_query_arg(array( 'wl_mpsw_payu_callback'=>1,'results'=>$results,'rul'=>urlencode_deep(get_home_url()) ), $this->get_return_url($order));
            wp_redirect($return_url);
        } else {
            wc_add_notice(__('Error on payment: Hash incorrect!', 'multiple-payment-solutions-for-woocommerce'), 'error');
            wp_redirect($order->get_checkout_payment_url(false));
            die('');
        }
    } // function wl_mpsw_payu_transaction() end

    /**
    * Thank you page success data
    * @since 1.0
    */
    public function wl_mpsw_payu_thankyou()
    {
        $wl_mpsw_thanku_response = json_decode(base64_decode(urldecode($this->responseVal)), true);

        if (strtolower($wl_mpsw_thanku_response['status']) == 'success') {
            $added_text = wp_kses_post('<section class="woocommerce-order-details">
								<h3>'.$this->thank_you_message.'</h3>
								<h2 class="woocommerce-order-details__title">Transaction details</h2>
								<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
									<thead>
										<tr>
											<th class="woocommerce-table__product-name product-name">PayUmoney Transaction id:</th>
											<th class="woocommerce-table__product-table product-total">'.$wl_mpsw_thanku_response['txnid'].'</th>
										</tr>
									</thead>
									<tbody>
										<tr class="woocommerce-table__line-item order_item">
											<td class="woocommerce-table__product-name product-name">Bank Ref:</td>
											<td class="woocommerce-table__product-total product-total">'.$wl_mpsw_thanku_response['bank_ref_num'].'</td>
										</tr>
									</tbody>
									<tfoot>
										<tr>
											<th scope="row">Transaction method:</th>
											<td>'.$wl_mpsw_thanku_response['bankcode'].' ( '.$wl_mpsw_thanku_response['mode'].' )</td>
										</tr>
									</tfoot>
								</table>
							</section>');
        } elseif (strtolower($wl_mpsw_thanku_response['status']) == 'pending') {
            $added_text = wp_kses_post('<section class="woocommerce-order-details">
									<h3>PayUmoney payment is pending</h3>
									<h2 class="woocommerce-order-details__title">Transaction details</h2>
									<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
										<thead>
											<tr>
												<th class="woocommerce-table__product-name product-name">PayUmoney Transaction id</th>
												<th class="woocommerce-table__product-table product-total">'.$wl_mpsw_thanku_response['txnid'].'</th>
											</tr>
										</thead>
									</table>
								</section>');
        } else {
            wc_add_notice(__('Error on payment: Hash incorrect!', 'multiple-payment-solutions-for-woocommerce'), 'error');
            wp_redirect($order->get_checkout_payment_url(false));
        }
        return $added_text ;
    }// function wl_mpsw_payu_thankyou() end

    /**
    * Process refund call process_refund()
    *
    */
    public function process_refund($order_id, $amount = null, $reason='')
    {
        global $woocommerce;
        $order            = new WC_Order($order_id);
        $authorization_id = get_post_meta($order_id, '_payu_authorization_id', true);
        $transaction_id   = get_post_meta($order_id, '_transaction_id', true);

        $refund_url = 'https://www.payumoney.com/treasury/merchant/refundPayment';
        if ($this->testmode == 'yes') {
            $refund_url = 'https://test.payumoney.com/treasury/merchant/refundPayment';
        }

        $PayuParams = array(
                        "merchantKey"  => $this->merchantid,
                        "paymentId"    => $authorization_id,
                        "refundAmount" => $amount,
                    );

        $post_data    = json_encode($PayuParams, JSON_UNESCAPED_SLASHES);
        $response     = $this->wl_mpsw_payu_apiCall($refund_url, $post_data, 'POST');
        $ref_response = json_decode($response, true);

        if (isset($ref_response['body']['status']) && ($ref_response['body']['status'] == 0)) {
            $refund_note =  sprintf(__('Refund: %1$s %2$s<br>PayUmoney Refund ID: %3$s<br>Order ID: %4$s', 'multiple-payment-solutions-for-woocommerce'), $amount, get_option('woocommerce_currency'), $ref_response['body']['result'], $transaction_id);
            $order->add_order_note($refund_note);
            return true;
        } else {
            return new WP_Error('error', esc_html__($ref_response));
        }
    }// Process refund call process_refund() end

    /**
    * Curl call wl_mpsw_paytm_apiCall()
    *
    */
    public function wl_mpsw_payu_apiCall($url, $post_data, $method)
    {
        $args = array(
            'method'      => $method,
            'body'        => $post_data,
            'timeout'     => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'cookies'     => array()
        );

        $response = wp_remote_request($url, $args);
        $response = wp_remote_retrieve_body($response);
        return $response;
    }// Curl call wl_mpsw_paytm_apiCall() end
}
