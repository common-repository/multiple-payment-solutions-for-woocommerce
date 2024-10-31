<?php
defined('ABSPATH') or die();
include('lib/encdec_paytm.php');

class WC_Gateway_Paytm_MPSW extends WC_Payment_Gateway
{
    /**
    * construct function for this plugin __construct()
    *
    */
    public function __construct()
    {
        global $woocommerce;
        $this->id				  = 'wc_gateway_paytm_mpsw';
        $this->method_title       = esc_html__('IS Paytm Gateway', 'multiple-payment-solutions-for-woocommerce');
        $this->method_description = esc_html__('Direct payment via Paytm. Paytm accepts Credit / Debit Cards and Refund options', 'multiple-payment-solutions-for-woocommerce').' '.wp_kses_post('<a href="#" target="_blank">See how to configure.</a>');
        $this->icon 			  = WL_MPSW_PLUGIN_URL.'assets/images/paytm_icon.png';
        $this->has_fields 		  = true;
        $this->supports           = array('refunds');
        $this->liveurl			  = 'https://securegw.paytm.in/';
        $this->testurl			  = 'https://securegw-stage.paytm.in/';
        $this->init_form_fields();
        $this->init_settings();
        $this->responseVal		  = '';

        if (get_option('woocommerce_currency') == 'INR') {
            $wl_mpsw_paytm_enabled = $this->settings['enabled'];
        } else {
            $wl_mpsw_paytm_enabled = 'no';
        }

        $this->enabled	= $wl_mpsw_paytm_enabled;
        $this->testmode	= $this->settings['testmode'];

        if (isset($this->settings['industry_type_id']) && $this->settings['industry_type_id']!='') {
            $this->industry_type_id	= $this->settings['industry_type_id'];
        } else {
            $this->industry_type_id = 'Retail';
        }

        if (isset($this->settings['thank_you_message'])) {
            $this->thank_you_message = esc_html__($this->settings['thank_you_message'], 'multiple-payment-solutions-for-woocommerce');
        } else {
            $this->thank_you_message = esc_html__('Thank you! your order has been received.', 'multiple-payment-solutions-for-woocommerce');
        }

        if (isset($this->settings['redirect_message']) && $this->settings['redirect_message']!='') {
            $this->redirect_message = esc_html__($this->settings['redirect_message'], 'multiple-payment-solutions-for-woocommerce');
        } else {
            $this->redirect_message = esc_html__('Thank you for your order. We are now redirecting you to Pay with Paytm to make payment.', 'multiple-payment-solutions-for-woocommerce');
        }

        $this->merchantid   		= $this->settings['merchantid'];
        $this->merchant_website   	= $this->settings['merchant_website'];
        $this->mkey   				= $this->settings['mkey'];

        if ('yes'==$this->testmode) {
            $this->title 	   = esc_html__('Sandbox Paytm', 'multiple-payment-solutions-for-woocommerce');
            $this->description = wp_kses_post('<a href="https://developer.paytm.com/docs/testing-integration/" target="_blank">Development Guide and Test Account details</a>');
        } else {
            $this->title 	   = $this->settings['title'];
            $this->description = $this->settings['description'];
        }

        if (isset($_GET['wl_mpsw_paytm_callback']) && isset($_GET['results']) && isset($_GET['wl_mpsw_paytm_callback'])==1 && isset($_GET['results']) != '') {
            $this->responseVal = $_GET['results'];
            add_filter('woocommerce_thankyou_order_received_text', array($this, 'wl_mpsw_paytm_thankyou'));
        }

        add_action('init', array(&$this, 'wl_mpsw_paytm_transaction'));
        add_action('woocommerce_api_'.strtolower(get_class($this)), array( $this, 'wl_mpsw_paytm_transaction' ));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
        add_action('woocommerce_receipt_' . $this->id, array( $this, 'wl_mpsw_paytm_receipt_page' ));
    } // End Constructor

    /**
    * init Gateway Form Fields init_form_fields()
    *
    */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'			=> esc_html__('Enable/Disable:', 'multiple-payment-solutions-for-woocommerce'),
                'type'			=> 'checkbox',
                'label' 		=> esc_html__('Enable Paytm', 'multiple-payment-solutions-for-woocommerce'),
                'default'		=> 'yes'
            ),
            'title' => array(
                'title' 		=> esc_html__('Title:', 'multiple-payment-solutions-for-woocommerce'),
                'type' 			=> 'text',
                'description'	=> esc_html__('This controls the title which the user sees during checkout.', 'multiple-payment-solutions-for-woocommerce'),
                'custom_attributes' => array( 'required' => 'required' ),
                'default' 		=> esc_html__('Paytm', 'multiple-payment-solutions-for-woocommerce')
            ),
            'description' => array(
                'title' 		=> esc_html__('Description:', 'multiple-payment-solutions-for-woocommerce'),
                'type' 			=> 'textarea',
                'description' 	=> esc_html__('This controls the description which the user sees during checkout.', 'multiple-payment-solutions-for-woocommerce'),
                'default' 		=> esc_html__('Direct payment via Paytm. Paytm accepts VISA, MasterCard, Debit Cards and the Net Banking of all major banks.', 'multiple-payment-solutions-for-woocommerce'),
            ),
            'merchantid' => array(
                'title' 		=> esc_html__('Merchant ID:', 'multiple-payment-solutions-for-woocommerce'),
                'type' 			=> 'text',
                'custom_attributes' => array( 'required' => 'required' ),
                'description' 	=> esc_html__('This Merchant ID is generated at the time of activation of your site and helps to uniquely identify you to Paytm Merchant', 'multiple-payment-solutions-for-woocommerce'),
                'custom_attributes' => array( 'required' => 'required', 'autocomplete'=> 'off' ),
                'default' 		=> ''
            ),
            'mkey' => array(
                'title' 		=> esc_html__('Merchant Key:', 'multiple-payment-solutions-for-woocommerce'),
                'type'	 		=> 'text',
                'custom_attributes' => array( 'required' => 'required' ),
                'description' 	=> esc_html__('String of Key characters provided by Paytm', 'multiple-payment-solutions-for-woocommerce'),
                'custom_attributes' => array( 'required' => 'required', 'autocomplete'=> 'off' ),
                'default' 		=> ''
            ),
            'industry_type_id' => array(
                'title' 		=> esc_html__('Industry Type:', 'multiple-payment-solutions-for-woocommerce'),
                'type'	 		=> 'text',
                'custom_attributes' => array( 'required' => 'required' ),
                'description' 	=> esc_html__('INDUSTRY TYPE ID provided by Paytm use <b>Retail</b> for sandbox/test mode', 'multiple-payment-solutions-for-woocommerce'),
                'custom_attributes' => array( 'required' => 'required', 'autocomplete'=> 'off' ),
                'default' 		=> ''
            ),
            'merchant_website' => array(
                'title' 		=> esc_html__('Website:', 'multiple-payment-solutions-for-woocommerce'),
                'type'	 		=> 'text',
                'custom_attributes' => array( 'required' => 'required' ),
                'description' 	=> esc_html__('Website url provided by Paytm use <b>WEBSTAGING</b> for sandbox/test mode', 'multiple-payment-solutions-for-woocommerce'),
                'custom_attributes' => array( 'required' => 'required', 'autocomplete'=> 'off' ),
                'default' 		=> ''
            ),
            'testmode' => array(
                'title' 		=> esc_html__('Mode of transaction:', 'multiple-payment-solutions-for-woocommerce'),
                'type' 			=> 'select',
                'label' 		=> esc_html__('Paytm Tranasction Mode.', 'multiple-payment-solutions-for-woocommerce'),
                'options' 		=> array('yes'=>'Test / Sandbox Mode','no'=>'Live Mode'),
                'default' 		=> 'no',
                'description' 	=> esc_html__('Mode of Paytm activities'),
                'desc_tip' 		=> true
                ),
            'thank_you_message' => array(
                'title' 		=> esc_html__('Thank you page message:', 'multiple-payment-solutions-for-woocommerce'),
                'type' 			=> 'textarea',
                'description' 	=> esc_html__('Thank you page order success message when order has been received', 'multiple-payment-solutions-for-woocommerce'),
                'default' 		=> esc_html__('Thank you! your order has been received.', 'multiple-payment-solutions-for-woocommerce'),
                ),
            'redirect_message' => array(
                'title' 		=> esc_html__('Redirecting you to Pay with Paytm:', 'multiple-payment-solutions-for-woocommerce'),
                'type' 			=> 'textarea',
                'description' 	=> esc_html__('We are now redirecting you to Paytm to make payment', 'multiple-payment-solutions-for-woocommerce'),
                'default' 		=> esc_html__('Thank you for your order. We are now redirecting you to Pay with Paytm to make payment.', 'multiple-payment-solutions-for-woocommerce'),
                ),
            );
    } // End init Gateway Form Fields init_form_fields()

    /**
    * WP Admin Options admin_options()
    *
    */
    public function admin_options()
    {
        ?>
    	<h3><?php _e('Paytm ', 'multiple-payment-solutions-for-woocommerce'); ?></h3>
    	<p><?php _e('Paytm works by sending the user to Paytm to enter their payment information to complete their payment process. Note that Paytm will only take payments in Indian Rupee(INR).', 'multiple-payment-solutions-for-woocommerce'); ?></p>
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
					<p><strong><?php _e('Paytm  Gateway Disabled', 'multiple-payment-solutions-for-woocommerce'); ?></strong>
						<?php echo sprintf(esc_html__('Choose Indian Rupee (Rs.) as your store currency in
						<a href="%s">Pricing Options</a> to enable the Paytm WooCommerce payment gateway.', 'multiple-payment-solutions-for-woocommerce'), admin_url('admin.php?page=wc-settings')); ?>
					</p>
				</div>
				<?php
            } // End check currency
    } // End WP Admin Options admin_options()

    /**
    * Build the form after click on Paytm Payment button wl_mpsw_generate_paytm_form()
    *
    */
    private function wl_mpsw_generate_paytm_form($order_id)
    {
        $this->wl_mpsw_paytm_clear_cache();
        global $wp;
        global $woocommerce;
        $order     = new WC_Order($order_id);
        $txnid     = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
        update_post_meta($order_id, '_transaction_id', $txnid);
        $returnURL = $woocommerce->api_request_url(strtolower(get_class($this)));

        $wl_mpsw_paytm_args_data = array(
            'MID'					=> $this->merchantid,
            'ORDER_ID'				=> $txnid,
            'CUST_ID'				=> $order->get_billing_email(),
            'INDUSTRY_TYPE_ID'		=> $this->industry_type_id,
            'CHANNEL_ID'			=> 'WEB',
            'TXN_AMOUNT'			=> $order->get_total(),
            'WEBSITE'				=> $this->merchant_website,
            'CALLBACK_URL'			=> $returnURL,
            'MOBILE_NO'				=> $order->get_billing_phone(),
            'EMAIL'					=> $order->get_billing_email(),
        );

        $this->wl_mpsw_paytm_log(esc_html__('Paytm Arrgument passed for creating payments.', 'multiple-payment-solutions-for-woocommerce').' '.$wl_mpsw_paytm_args_data);

        $wl_mpsw_paytm_args                 = array_filter($wl_mpsw_paytm_args_data);
        $wl_mpsw_paytm_args["CHECKSUMHASH"] = PaytmPayment::getChecksumFromArray($wl_mpsw_paytm_args, $this->mkey);

        $checkoutform = '';
        foreach ($wl_mpsw_paytm_args as $name => $value) {
            if ($value) {
                $checkoutform .='<input type="hidden" name="' . $name .'" value="' . $value . '">';
            }
        }
        $posturl = $this->liveurl;
        if ($this->testmode == 'yes') {
            $posturl = $this->testurl;
        }
        return '<form action="'.$posturl.'order/process" method="POST" name="paytmform" id="paytmform">
				' . $checkoutform . '
				<input type="submit" class="button" id="submit_mpsw_paytm_payment_form" value="' . esc_html__('Pay with Paytm', 'multiple-payment-solutions-for-woocommerce') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">'.__('Cancel order &amp; restore cart', 'multiple-payment-solutions-for-woocommerce') . '</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "'.__($this->redirect_message, 'multiple-payment-solutions-for-woocommerce').'",
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
							jQuery("#paytmform").submit();
							jQuery("#submit_mpsw_paytm_payment_form").click();
					});
				</script>
			</form>';
    } // End Paytm Payment button wl_mpsw_generate_paytm_form()

    /**
    * Process the payment for checkout process_payment()
    *
    */
    public function process_payment($order_id)
    {
        $this->wl_mpsw_paytm_clear_cache();
        global $woocommerce;
        $order = new WC_Order($order_id);
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    } // checkout process_payment()  end

    /**
     * Page after cheout button and redirect to Paytm payment page wl_mpsw_paytm_receipt_page()
     *
     */
    public function wl_mpsw_paytm_receipt_page($order_id)
    {
        $this->wl_mpsw_paytm_clear_cache();
        global $woocommerce;
        $order = new WC_Order($order_id);
        printf('<h3>%1$s</h3>', __('Thank you for your order, please click the button below to Pay with Paytm.', 'multiple-payment-solutions-for-woocommerce'));
        _e($this->wl_mpsw_generate_paytm_form($order_id));
    } // Cheout button and redirect wl_mpsw_paytm_receipt_page() end

    /**
    * Check the status of current transaction and get response with $_POST wl_mpsw_paytm_transaction()
    *
    */
    public function wl_mpsw_paytm_transaction()
    {
        global $woocommerce;
        global $wpdb;

        if (null !==(sanitize_text_field($_POST['ORDERID'])) && sanitize_text_field($_POST['ORDERID']) != '') {
            $trnid = sanitize_text_field($_POST['ORDERID']);
        }
        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => array('wc'),
            'numberposts' => 1,
            'meta_query'  => array(
                   array(
                       'key'     => '_transaction_id',
                       'value'   => $trnid,
                       'compare' => '=',
                   )
               )
            );
        $post_id_arr = get_posts($args);
        if (isset($post_id_arr[0]->ID) && $post_id_arr[0]->ID !='') {
            $order_id = $post_id_arr[0]->ID;
        }
        $order = new WC_Order($order_id);
        $mkey  = $this->mkey;
        if (! empty(sanitize_post($_POST))) {
            foreach (sanitize_post($_POST) as $key => $value) {
                $this->responseVal[$key] = htmlentities($value, ENT_QUOTES);
            }
        } else {
            wc_add_notice(esc_html__('Error on payment: Paytm payment failed!', 'multiple-payment-solutions-for-woocommerce'), 'error');
            wp_redirect($order->get_cancel_order_url());
        }

        $postResp         = sanitize_post($_POST);
        $postRespChecksum = null !==(sanitize_text_field($_POST["CHECKSUMHASH"])) ? sanitize_text_field($_POST["CHECKSUMHASH"]) : "";

        /* Checking hash for the transaction wheather this response is true or false */
        if ($this->wl_mpsw_check_paytm_hash_after_transaction($postResp, $mkey, $postRespChecksum)) {
            if (isset($this->responseVal['TXNID']) && $this->responseVal['TXNID']!='') {
                update_post_meta($order_id, '_ptm_authorization_id', $this->responseVal['TXNID']);
            }

            if ($postResp['STATUS'] == 'TXN_SUCCESS') {
                $order_note = sprintf(esc_html__('Reference Order ID: %1$s<br>Paytm Transaction ID: %2$s<br>Bank Ref: %3$s<br>Transaction method: %4$s', 'multiple-payment-solutions-for-woocommerce'), $this->responseVal['ORDERID'], $this->responseVal['TXNID'], $this->responseVal['BANKTXNID'], $this->responseVal['GATEWAYNAME'].' ( '.$this->responseVal['PAYMENTMODE'].' )') ;
                $this->wl_mpsw_paytm_log($order_note);
                $order->add_order_note($order_note);
                $order->payment_complete();
            } elseif ($postResp['STATUS'] == 'PENDING') {
                $order_note = sprintf(esc_html__('Reference Order ID: %1$s<br>Paytm Transaction ID: %2$s<br>Bank Ref: %3$s<br>Transaction method: %4$s', 'multiple-payment-solutions-for-woocommerce'), $this->responseVal['ORDERID'], $this->responseVal['TXNID'], $this->responseVal['BANKTXNID'], $this->responseVal['GATEWAYNAME'].' ( '.$this->responseVal['PAYMENTMODE'].' )') ;
                $this->wl_mpsw_paytm_log($order_note);
                $order->add_order_note($order_note);
                $order->update_status('on-hold');
            } else {
                $order_note = sprintf(esc_html__('Paytm payment is failed.<br>Reference Order ID: %1$s<br>Error: %2$s'), $this->responseVal['ORDERID'], $this->responseVal['RESPMSG']) ;
                $order->add_order_note($order_note);
                $this->wl_mpsw_paytm_log($order_note);
                wc_add_notice(esc_html__('Error on payment: Paytm payment failed! Reference Order ID: '.$this->responseVal['ORDERID'].' ('.$this->responseVal['RESPMSG']. ' )', 'multiple-payment-solutions-for-woocommerce'), 'error');
                wp_redirect($order->get_cancel_order_url_raw());
                die();
            }

            $results    = urlencode(base64_encode(json_encode($_POST)));
            $return_url = add_query_arg(array( 'wl_mpsw_paytm_callback'=>1,'results'=>$results ), $this->get_return_url($order));
            wp_redirect($return_url);
        }
    } // get response wl_mpsw_paytm_transaction() end

    /**
    * Clear cache for the previous value wl_mpsw_paytm_clear_cache()
    *
    */
    private function wl_mpsw_paytm_clear_cache()
    {
        header("Pragma: no-cache");
        header("Cache-Control: no-cache");
        header("Expires: 0");
    } // Clear cache for the previous value wl_mpsw_paytm_clear_cache() end

    /**
    * calculate hash value before transaction wpl_paytm_calculate_hash_before_transaction()
    *
    */
    private function wpl_paytm_calculate_hash_before_transaction($arrayList, $key, $sort=1)
    {
        if ($sort != 0) {
            ksort($arrayList);
        }
        $str         = PaytmPayment::getArray2Str($arrayList);
        $salt        = PaytmPayment::generateSalt_e(4);
        $finalString = $str . "|" . $salt;
        $hash        = hash("sha256", $finalString);
        $hashString  = $hash . $salt;
        $checksum    = PaytmPayment::encrypt_e($hashString, $key);
        return $checksum;
    } // function wpl_calculate_hash_before_transaction() end

    /**
    * calculate hash value after transaction wl_mpsw_check_paytm_hash_after_transaction()
    *
    */
    private function wl_mpsw_check_paytm_hash_after_transaction($arrayList, $key, $checksumvalue)
    {
        $arrayList     = PaytmPayment::removeCheckSumParam($arrayList);
        ksort($arrayList);
        $str           = PaytmPayment::getArray2StrForVerify($arrayList);
        $paytm_hash    = PaytmPayment::decrypt_e($checksumvalue, $key);
        $salt          = substr($paytm_hash, -4);
        $finalString   = $str . "|" . $salt;
        $website_hash  = hash("sha256", $finalString);
        $website_hash .= $salt;

        $validFlag = "FALSE";
        if ($website_hash == $paytm_hash) {
            $validFlag = "TRUE";
        } else {
            $validFlag = "FALSE";
        }
        return $validFlag;
    } // function wl_mpsw_check_paytm_hash_after_transaction() end

    /**
    * get Checksum From String wl_mpsw_paytm_getChecksumFromString()
    *
    */
    private function wl_mpsw_paytm_getChecksumFromString($str, $key)
    {
        $salt        = PaytmPayment::generateSalt_e(4);
        $finalString = $str . "|" . $salt;
        $hash        = hash("sha256", $finalString);
        $hashString  = $hash . $salt;
        $checksum    = PaytmPayment::encrypt_e($hashString, $key);
        return $checksum;
    }// function wl_mpsw_paytm_getChecksumFromString() end

    /**
    * Thank you page success data wl_mpsw_paytm_thankyou()
    *
    */
    public function wl_mpsw_paytm_thankyou()
    {
        $wl_mpsw_paytm_response = json_decode(base64_decode(urldecode($this->responseVal)), true);
        global $woocommerce;
        global $wpdb;

        if (isset($wl_mpsw_paytm_response['ORDERID']) && $wl_mpsw_paytm_response['ORDERID'] != '') {
            $trnid = $wl_mpsw_paytm_response['ORDERID'];
        }
        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => array('wc'),
            'numberposts' => 1,
            'meta_query'  => array(
                   array(
                       'key'     => '_transaction_id',
                       'value'   => $trnid,
                       'compare' => '=',
                   )
               )
            );
        $post_id_arr = get_posts($args);
        if (isset($post_id_arr[0]->ID) && $post_id_arr[0]->ID !='') {
            $order_id = $post_id_arr[0]->ID;
        }
        $order = new WC_Order($order_id);

        $added_text = '';
        if (strtolower($wl_mpsw_paytm_response['STATUS']) == 'txn_success') {
            echo $added_text .= wp_kses_post('<section class="woocommerce-order-details">
										<h3>'.$this->thank_you_message.'</h3>
										<h2 class="woocommerce-order-details__title">Transaction details</h2>
										<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
											<thead>
												<tr>
													<th class="woocommerce-table__product-name product-name">Reference Order ID:</th>
													<th class="woocommerce-table__product-table product-total">'.$wl_mpsw_paytm_response['ORDERID'].'</th>
												</tr>
											</thead>
											<tbody>
												<tr class="woocommerce-table__line-item order_item">
													<td class="woocommerce-table__product-name product-name">Paytm Transaction ID:</td>
													<td class="woocommerce-table__product-total product-total">'.$wl_mpsw_paytm_response['TXNID'].'</td>
												</tr>
											</tbody>
											<tfoot>
												<tr class="woocommerce-table__line-item order_item">
													<td class="woocommerce-table__product-name product-name">Bank Ref:</td>
													<td class="woocommerce-table__product-total product-total">'.$wl_mpsw_paytm_response['BANKTXNID'].'</td>
												</tr>
												<tr>
													<th scope="row">Transaction method:</th>
													<td>'.$wl_mpsw_paytm_response['GATEWAYNAME'].' ( '.$wl_mpsw_paytm_response['PAYMENTMODE'].' )</td>
												</tr>
											</tfoot>
										</table>
									</section>');
        } elseif (strtolower($wl_mpsw_paytm_response['STATUS']) == 'pending') {
            echo $added_text .= wp_kses_post('<section class="woocommerce-order-details">
										<h3>Paytm payment is pending</h3>
										<h2 class="woocommerce-order-details__title">Transaction details</h2>
										<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
											<thead>
												<tr>
													<th class="woocommerce-table__product-name product-name">Reference Order ID</th>
													<th class="woocommerce-table__product-table product-total">'.$wl_mpsw_paytm_response['ORDERID'].'</th>
												</tr>
											</thead>
											<tbody>
												<tr class="woocommerce-table__line-item order_item">
													<td class="woocommerce-table__product-name product-name">Paytm Transaction ID:</td>
													<td class="woocommerce-table__product-total product-total">'.$wl_mpsw_paytm_response['TXNID'].'</td>
												</tr>
											</tbody>
										</table>
									</section>');
        } else {
            wp_redirect($order->get_checkout_payment_url(false));
        }
    }// function wl_mpsw_paytm_thankyou() end

    /**
    * Process refund call process_refund()
    */
    public function process_refund($order_id, $amount = null, $reason='')
    {
        global $woocommerce;
        $order               = new WC_Order($order_id);
        $authorization_id    = get_post_meta($order_id, '_ptm_authorization_id', true);
        $transaction_id      = get_post_meta($order_id, '_transaction_id', true);
        $reference_id        = 'REF-'.substr(hash('sha256', mt_rand() . microtime()), 0, 10);
        $paytmParams         = array();
        $paytmParams["body"] = array(
                                    "mid"          => $this->merchantid,
                                    "txnType"      => "REFUND",
                                    "orderId"      => $transaction_id,
                                    "txnId"        => $authorization_id,
                                    "refId"        => $reference_id,
                                    "refundAmount" => $amount,
                                    "comments"	   => $reason
                                );

        $checksum = $this->wl_mpsw_paytm_getChecksumFromString(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), $this->mkey);
        $paytmParams["head"] = array(
                                    "clientId"	=> "C11",
                                    "signature"	=> $checksum
                                );
        $post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
        $posturl   = $this->liveurl;
        if ($this->testmode == 'yes') {
            $posturl = $this->testurl;
        }
        $refund_url   = $posturl."refund/apply";
        $response     = $this->wl_mpsw_paytm_apiCall($refund_url, $post_data, 'POST');
        $ref_response = json_decode($response, true);

        $log = esc_html__('Refund Response:- ', 'multiple-payment-solutions-for-woocommerce').' '.print_r($ref_response);
        $this->wl_mpsw_paytm_log($log);

        if (isset($ref_response['body']['resultInfo']['resultStatus']) && ($ref_response['body']['resultInfo']['resultStatus']=='TXN_SUCCESS' || $ref_response['body']['resultInfo']['resultStatus']=='PENDING')) {
            $refund_note =  sprintf(esc_html__('Refund: %1$s %2$s<br>Paytm Refund ID: %3$s<br>Reference ID: %4$s', 'multiple-payment-solutions-for-woocommerce'), $amount, get_option('woocommerce_currency'), $ref_response['body']['refundId'], $ref_response['body']['refId']);
            $order->add_order_note($refund_note);
            return true;
        } elseif (isset($ref_response['body']['resultInfo']['resultStatus']) && $ref_response['body']['resultInfo']['resultStatus']=='TXN_FAILURE') {
            return new WP_Error('error', esc_html__($ref_response['body']['resultInfo']['resultMsg']));
        }
    }// Process refund call process_refund() end

    /**
    * Paytm API call wl_mpsw_paytm_apiCall()
    *
    */
    public function wl_mpsw_paytm_apiCall($url, $post_data, $method)
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
    }// Paytm API call wl_mpsw_paytm_apiCall() end

    public function wl_mpsw_paytm_log($message)
    {
        $log = new WC_Logger();
        $log->add(esc_html__('Wblizar Paytm Log', 'multiple-payment-solutions-for-woocommerce'), $message);
    }
} //  End WC_Gateway_Paytm_MPSW Class
