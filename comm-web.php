<?php

/*
Plugin Name: CommWeb
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 1.0
Author: jorge
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function flyonet_comm_web() {

    //	TODO: Check if WC_Payment_Gateway is available

    class WC_Gateway_Comm_Web extends WC_Payment_Gateway {

        function __construct() {
            // The global ID for this Payment method
            $this->id = "comm_web";
            // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
            $this->method_title = __( "CommWeb Virtual Payment ", 'comm_web' );
            // The description for this Payment Gateway, shown on the actual Payment options page on the backend
            $this->method_description = __( "CommWeb Virtual Payment bla bla bla", 'comm_web' );
            // The title to be used for the vertical tabs that can be ordered top to bottom
            $this->title = __( 'CommWeb Virtual Payment', 'comm_web' );
            // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
            $this->icon = null;
            // Bool. Can be set to true if you want payment fields to show on the checkout
            // if doing a direct integration, which we are doing in this case
            $this->has_fields = false;
            // This basically defines your settings which are then loaded with init_settings()
            $this->init_form_fields();
            // After init_settings() is called, you can get the settings and load them into variables, e.g:
            // $this->title = $this->get_option( 'title' );
            $this->init_settings();

            // Turn these settings into variables we can use
            foreach ( $this->settings as $setting_key => $value ) {
                $this->$setting_key = $value;
            }

            // Save admin options
            add_action( 'woocommerce_update_options_payment_gateways_' .
                $this->id, array( $this, 'process_admin_options' ) );

            include_once( 'includes/class-wc-gateway-paypal-ipn-handler.php' );
            new WC_Gateway_Paypal_IPN_Handler( $this->testmode, $this->receiver_email );
        }
        public function init_form_fields() {
            $this->form_fields = include( 'includes/settings_comm_web.php' );
        }

        // Submit payment and handle response
        public function process_payment( $order_id ) {
            include_once('includes/WC_Gateway_CommWeb_Request.php');

            $comm_web_request = new WC_Gateway_CommWeb_Request($this);
            $order = new WC_Order( $order_id );
            $vpcURL = $comm_web_request->get_request_url($order);

            error_log("url: " . $vpcURL);

            // Redirect to commweb page
            return array(
                'result'   => 'success',
                'redirect' => "$vpcURL",
            );
        }
    }
}

add_action('plugins_loaded', 'flyonet_comm_web');

function flyonet_add_comm_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_Comm_Web';
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'flyonet_add_comm_gateway_class' );