<?php

/**
 * Created by PhpStorm.
 * User: jorge
 * Date: 21/02/2016
 * Time: 2:41 PM
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

include_once( 'WC_gateway_comm_web_response.php' );


class WC_gateway_comm_web_response_handler extends WC_gateway_comm_web_response{

    protected $SECURE_SECRET;
    protected $gateway;

    protected $TAG = 'COMM_WEB: ';

    function __construct($SECURE_SECRET, $gateway) {
        $this->gateway    = $gateway;
        $this->SECURE_SECRET = $SECURE_SECRET;
        add_action( 'woocommerce_api_wc_gateway_comm_web', array( $this, 'check_response' ) );
        add_action( 'valid-comm-web-response', array( $this, 'valid_response' ) );
    }

    public function check_response() {
        // get and remove the vpc_TxnResponseCode code from the response fields as we
        // do not want to include this field in the hash calculation
        $vpc_Txn_Secure_Hash = $_GET["vpc_SecureHash"];
        unset($_GET["vpc_SecureHash"]);

        // set a flag to indicate if hash has been validated
        $errorExists = false;

        if (strlen($this->SECURE_SECRET) > 0 && $_GET["vpc_TxnResponseCode"] != "7"
            && $_GET["vpc_TxnResponseCode"] != "No Value Returned") {

            $md5HashData = $this->SECURE_SECRET;
            error_log("SECURE SECRET = " . $this->SECURE_SECRET);

            // sort all the incoming vpc response fields and leave out any with no value
            foreach($_GET as $key => $value) {
                if ($key != "vpc_Secure_Hash" or strlen($value) > 0) {
                    $md5HashData .= $value;
                }
            }
            // Validate the Secure Hash (remember MD5 hashes are not case sensitive)
            // This is just one way of displaying the result of checking the hash.
            // In production, you would work out your own way of presenting the result.
            // The hash check is all about detecting if the data has changed in transit.
            if (strtoupper($vpc_Txn_Secure_Hash) == strtoupper(md5($md5HashData))) {
                error_log($this->TAG . "VALID HASH");
                do_action( 'valid-comm-web-response', $_GET);
                exit;
            } else {
                error_log("$this->TAG .NOT VALID HASH");
            }
        } else {
            error_log($this->TAG . "HASH NOT CALCULATED (FIELD EMPTY?)");
        }
        wp_die( 'Payment Request Failure', 'Comm Web Request', array( 'response' => 500 ) );
    }
    public function valid_response($response) {
        $raw_order = $response['vpc_OrderInfo'];
        $order = $this->getOrder($raw_order);
        error_log($this->TAG . 'Order Found: ' . $order->id);

        $responseCode = $response['vpc_TxnResponseCode'];
        if ($responseCode == '0' && $response['vpc_Amount'] == $order->get_total() * 100) {
            $order->add_order_note( 'Completed' );
            $order->payment_complete();
            wp_redirect($this->gateway->get_return_url( $order ) );
            exit;
        }else{
            // Transaction was not succesful
            // Add notice to the cart
            // TODO: Add more details to error
            wc_add_notice( $this->getResponseDescription($responseCode), 'error' );
            // Add note to the order for your reference
            $order->add_order_note( 'Error: '. $this->getResponseDescription($responseCode) );
            wp_redirect(wc_get_checkout_url());
            exit;
        }
    }
    function getResponseDescription($responseCode) {

        switch ($responseCode) {
            case "0" : $result = "Transaction Successful"; break;
            case "?" : $result = "Transaction status is unknown"; break;
            case "1" : $result = "Unknown Error"; break;
            case "2" : $result = "Bank Declined Transaction"; break;
            case "3" : $result = "No Reply from Bank"; break;
            case "4" : $result = "Expired Card"; break;
            case "5" : $result = "Insufficient funds"; break;
            case "6" : $result = "Error Communicating with Bank"; break;
            case "7" : $result = "Payment Server System Error"; break;
            case "8" : $result = "Transaction Type Not Supported"; break;
            case "9" : $result = "Bank declined transaction (Do not contact Bank)"; break;
            case "A" : $result = "Transaction Aborted"; break;
            case "C" : $result = "Transaction Cancelled"; break;
            case "D" : $result = "Deferred transaction has been received and is awaiting processing"; break;
            case "F" : $result = "3D Secure Authentication failed"; break;
            case "I" : $result = "Card Security Code verification failed"; break;
            case "L" : $result = "Shopping Transaction Locked (Please try the transaction again later)"; break;
            case "N" : $result = "Cardholder is not enrolled in Authentication scheme"; break;
            case "P" : $result = "Transaction has been received by the Payment Adaptor and is being processed"; break;
            case "R" : $result = "Transaction was not processed - Reached limit of retry attempts allowed"; break;
            case "S" : $result = "Duplicate SessionID (OrderInfo)"; break;
            case "T" : $result = "Address Verification Failed"; break;
            case "U" : $result = "Card Security Code Failed"; break;
            case "V" : $result = "Address Verification and Card Security Code Failed"; break;
            default  : $result = "Unable to be determined";
        }
        return $result;
    }
}