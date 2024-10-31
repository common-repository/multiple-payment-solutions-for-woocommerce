<?php
defined('ABSPATH') or die();

class PayUmoneyPayment
{
    /**
    * Payment transaction verification function execution
    */
    public static function wl_mpsw_payu_transaction_verification($txnid)
    {
        $this->verification_liveurl	= 'https://info.payu.in/merchant/postservice';
        $this->verification_testurl	= 'https://test.payu.in/merchant/postservice';

        $host = $this->verification_liveurl;
        if ($this->testmode == 'yes') {
            $host = $this->verification_testurl;
        }

        $hash_data['key']     = $this->merchantid;
        $hash_data['command'] = 'verify_payment';
        $hash_data['var1']    =  $txnid;
        $hash_data['hash']    = $this->wl_mpsw_calculate_hash_before_verification($hash_data);
        $response             = $this->wl_mpsw_send_request($host, $hash_data);
        $response             = unserialize($response);
        return $response['transaction_details'][$txnid]['status'];
    }

    /**
    * Calculate hash value before transaction
    */
    public static function wl_mpsw_calculate_hash_before_transaction($salt, $hash_data)
    {
        $hash_sequence = "key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10";
        $hash_vars_seq = explode('|', $hash_sequence);
        $hash_string   = '';

        foreach ($hash_vars_seq as $hash_var) {
            $hash_string .= isset($hash_data[$hash_var]) ? $hash_data[$hash_var] : '';
            $hash_string .= '|';
        }

        $hash_string .= $salt;
        $hash_data['hash'] = strtolower(hash('sha512', $hash_string));

        return $hash_data['hash'];
    } // function wl_mpsw_calculate_hash_before_transaction() end

    /**
    * calculate hash value after transaction
    */
    public static function wl_mpsw_check_hash_after_transaction($salt, $txnRs)
    {
        $hash_sequence    = "key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10";
        $hash_vars_seq    = explode('|', $hash_sequence);
        $hash_vars_seq    = array_reverse($hash_vars_seq);
        $merc_hash_string = $salt . '|' . $txnRs['status'];
        foreach ($hash_vars_seq as $merc_hash_var) {
            $merc_hash_string .= '|';
            $merc_hash_string .= isset($txnRs[$merc_hash_var]) ? $txnRs[$merc_hash_var] : '';
        }
        $merc_hash = strtolower(hash('sha512', $merc_hash_string));
        if ($merc_hash == $txnRs['hash']) {
            return true;
        } else {
            return false;
        }
    } // function wl_mpsw_check_hash_after_transaction() end

    /**
    * calculate hash value before verification
    */
    public static function wl_mpsw_calculate_hash_before_verification($hash_data)
    {
        $hash_sequence = "key|command|var1";
        $hash_vars_seq = explode('|', $hash_sequence);
        $hash_string   = '';
        foreach ($hash_vars_seq as $hash_var) {
            $hash_string .= isset($hash_data[$hash_var]) ? $hash_data[$hash_var] : '';
            $hash_string .= '|';
        }
        $hash_string .= $this->salt;
        $hash_data['hash'] = strtolower(hash('sha512', $hash_string));
        return $hash_data['hash'];
    } // function wl_mpsw_calculate_hash_before_verification() end

    /**
    * send request and get Transaction verification
    */
    public static function wl_mpsw_send_request($host, $data)
    {
        $response = wp_remote_post($host, array(
                        'headers' => array(),
                        'body' => $data,
                    ));
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        return $response_body;
    } // function wl_mpsw_send_request() end
}
