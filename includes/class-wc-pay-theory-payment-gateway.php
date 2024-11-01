<?php
/**
 * Pay Theory's WooCommerce payment gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_Paytheory_Gateway extends WC_Payment_Gateway {
    private string $fee_mode;
    private string $test_mode;
    private string $test_api_key;
    private string $production_api_key;
    private string $payment_field_styles;
    private string $partner;
    private string $stage;
    private ?string $api_key;

    /**
     * Class constructor, set up gateway settings
     */
    public function __construct() {

        $this->id = 'paytheory'; // payment gateway plugin ID
        $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
        $this->has_fields = true; // custom fields
        $this->method_title = 'Pay Theory';
        $this->method_description = 'Accept Payments with Pay Theory'; // will be displayed on the options page
        $this->api_key = null;

        // This gateway supports payments for products
        $this->supports = array('products');

        // Payment Gateway options
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();
        $this->enabled = $this->get_option( 'enabled' );
        $this->fee_mode = $this->get_option( 'fee_mode' );
        $this->email_receipt = $this->get_option( 'email_receipt' );
        $this->title = "Pay With Pay Theory";
        $this->payment_field_styles = $this->get_option('payment_field_styles');
        $this->test_mode = $this->get_option('test_mode');
        $this->test_api_key = $this->get_option('test_api_key');
        $this->production_api_key = $this->get_option('production_api_key');
        $this->payment_field_styles = $this->get_option('payment_field_styles');

        if ($this->test_mode === 'yes') {
            if (
                !empty($this->test_api_key) &&
                (strpos($this->test_api_key, 'paytheorylab') !== false || strpos($this->test_api_key, 'paytheorystudy') !== false)
            ) {
                $this->api_key = $this->test_api_key;
                $parts = explode('-', $this->api_key);
                $this->partner = $parts[0];
                $this->stage = $parts[1];
            }
        }
        elseif ($this->test_mode === 'no') {
            if (!empty($this->production_api_key) && strpos($this->production_api_key, 'paytheory') !== false) {
                $this->api_key = $this->production_api_key;
                $parts = explode('-', $this->api_key);
                $this->partner = $parts[0];
                $this->stage = $parts[1];
            }
        }

        // Sets pt config settings
        $this->expose_settings();

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Enqueue custom JavaScript
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        // Action hook for successful sdk response
        add_action('woocommerce_api_' . 'success_callback', array($this, 'success_callback_handler'));


    }

    /**
     * Plugin options
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
                'enabled' => array(
                        'title' => 'Enable Payment Gateway',
                        'type' => 'checkbox',
                        'label' => 'Enable Payments',
                        'default' => 'no',
                ),
                'test_api_key' => array(
                        'title' => 'Test API Key',
                        'type' => 'text',
                        'placeholder' => 'Please enter your Test/Sandbox API KEY',
                ),
                'production_api_key' => array(
                        'title' => 'Production/Live API Key',
                        'type'  => 'password',
                        'placeholder' => 'Please enter your Production/Live API KEY',
                ),
                'email_receipt' => array(
                        'title' => 'Email Receipts',
                        'type' => 'checkbox',
                        'label' => 'Enable Email Receipts',
                        'default' => 'yes'
                ),
                'test_mode' => array(
                        'title'       => 'Test Mode',
                        'label'       => 'Enable Test Mode',
                        'type'        => 'checkbox',
                        'description' => 'Place the payment gateway in test mode using test API keys.',
                        'desc_tip'    => true,
                ),
//                'fee_mode' => array(
//                        'title' => 'Fee Mode',
//                        'type' => 'select',
//                        'description' => 'Please enter your Pay Theory Fee Mode',
//                        'default' => 'MERCHANT_FEE',
//                        'desc_tip' => true,
//                        'options' => array('MERCHANT_FEE' => 'Merchant Fee', 'SERVICE_FEE' => 'Service Fee'),
//                ),
//                'payment_field_styles' => array(
//                        'title' => 'Payment Field Style',
//                        'type' => 'select',
//                        'description' => 'Please enter your payment field styles',
//                        'default' => '{"default":{"color":"black","fontSize":"14px"},"success":{"color":"#5cb85c","fontSize":"14px"},"error":{"color":"#d9534f","fontSize":"14px"},"radio":{"width":20,"fill":"blue","stroke":"grey","text":{"fontSize":"18px","color":"grey"}},"hidePlaceholder":false}',
//                        'desc_tip' => true,
//                        'options' => array('{"default":{"color":"black","fontSize":"14px"},"success":{"color":"#5cb85c","fontSize":"14px"},"error":{"color":"#d9534f","fontSize":"14px"},"radio":{"width":20,"fill":"blue","stroke":"grey","text":{"fontSize":"18px","color":"grey"}},"hidePlaceholder":false}' => 'Default Style', '{"default":{"color":"black","fontSize":"14px"},"success":{"color":"#5cb85c","fontSize":"14px"},"error":{"color":"#d9534f","fontSize":"14px"},"radio":{"width":20,"fill":"blue","stroke":"grey","text":{"fontSize":"18px","color":"grey"}},"hidePlaceholder":true}' => 'No Placeholders Style'),
//                )
        );
    }
    /**
     * Set Payment Gateway Configurations
     */
    public function expose_settings()
    {
        global $pay_theory_plugin;

        // Only load if its cart or checkout page
        if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
            return;
        }

        // If payment gateway is disabled, do not continue
        if ($this->enabled ==='no') {
            return;
        }

        // Ensure the api_key is not null before proceeding
        if ($this->api_key === null) {
            return;
        }

        // ensure ssl is enabled in production
        if ($this->test_mode === 'no' && !is_ssl()) {
            return;
        }

        //Check that woo exists to prevent fatal errors
        if (function_exists('is_woocommerce')) {
            $pay_theory_plugin = [
                'API_KEY' => $this->api_key,
                'FEE_MODE' => $this->fee_mode,
                'EMAIL_RECEIPT' => $this->email_receipt,
                'STYLES' => $this->payment_field_styles,
                'PARTNER' => $this->partner,
                'STAGE' => $this->stage

            ];
        }
    }

    /**
     * Creates the custom payment fields that loads on checkout page
     */
    public function payment_fields()
    {
        // if test mode enabled display the description with test mode
        if ( $this->test_mode === "yes" ) {
            $this->description .= '*** TEST MODE ENABLED ***';
            $this->description  = trim( $this->description );
        }
        // display the description with <p> tags etc.
        echo wpautop( wp_kses_post( $this->description ) );

        // if test mode enabled display the description with test mode
        if ( $this->api_key === null ) {
            echo '<p> Error: Invalid Api Key </p>';
        } else {
            echo '<div id="pay-theory-woo-form">
            <style>
              #pay-theory-credit-card-cvv,
              #pay-theory-credit-card-exp,
              #pay-theory-credit-card-number {
                max-height: 52px !important;
                height: 52px !important;
                border: 1px solid #dddddd;
                margin-bottom: 22px;
                background: white;
              }
              
              .pay-theory-field {
                max-height: 52px;
              }
            </style>
            <label for="card_number" class="">
                Card Number&nbsp;<abbr class="required" title="required">*</abbr>
            </label>
            <div id="pay-theory-credit-card-number" style="width: 279px !important;"></div>
            <label for="card_expiration" class="">
                Card Exp&nbsp;<abbr class="required" title="required">*</abbr>
            </label>
            <div id="pay-theory-credit-card-exp"></div>
            <label for="card_cvv" class="">
                CVV&nbsp;<abbr class="required" title="required">*</abbr>
            </label>
            <div id="pay-theory-credit-card-cvv"></div>
            <div id="pt-woo-error-message" style="padding: 8px; border: 1px solid red; background: #ffe0e0; width: auto; border-radius: 4px; color: red; display: none;"></div>
          </div>';
        }

    }

    /**
     * Register our custom js files, register sdk and pass variables to sdk to initialize
     */
    public function payment_scripts()
    {
        global $pay_theory_plugin;

        // Ensure we are on cart or checkout page
        if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
            return;
        }
        // Ensure we are not on receipt page
        if ( is_wc_endpoint_url( 'order-received' ) ) {
            return;
        }

        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ( 'no' === $this->enabled ) {
            return;
        }

        // Check for prod api keys
        if ( $this->test_mode === 'no' && empty( $this->production_api_key )) {
            return;
        }

        // Check for test api key
        if ( $this->test_mode === 'yes' && empty( $this->test_api_key )) {
            return;
        }

        // ensure ssl is enabled in production
        if ($this->test_mode === 'no' && !is_ssl()) {
            return;
        }

        if ($this->api_key === null){
            return;
        }

        // register our sdk
        wp_register_script( 'pay_theory_sdk', "https://$this->partner.sdk.$this->stage.com/index.js" );

        // Localize the script with $pay_theory_plugin
        wp_localize_script( 'pay_theory_sdk', 'pay_theory_plugin', $pay_theory_plugin);

        wp_enqueue_script( 'pay_theory_sdk' );

        // Register custom js file with jquery and init_sdk as dependencies
        wp_register_script( 'pay_theory_woo_support', plugins_url( '../public/paytheory.js', __FILE__ ), array( 'jquery', 'pay_theory_sdk' ) );

        // Localize the script with $pay_theory_plugin
        wp_localize_script( 'pay_theory_woo_support', 'pay_theory_plugin', $pay_theory_plugin);

        // Enqueue the script
        wp_enqueue_script( 'pay_theory_woo_support' );
    }

    /**
     * Validate payment fields
     * @return boolean
     */
    public function validate_fields(): bool
    {
        if( empty( $_POST[ 'billing_first_name' ]) ) {
            wc_add_notice(  'First name is required!', 'error' );
            return false;
        }

        if( empty( $_POST[ 'billing_last_name' ]) ) {
            wc_add_notice(  'First name is required!', 'error' );
            return false;
        }

        if( empty( $_POST[ 'billing_postcode' ]) ) {
            wc_add_notice(  'First name is required!', 'error' );
            return false;
        }

        return true;
    }

    /**
     * Callback function to handle payment responses
     */
    public function success_callback_handler()
    {
        header( 'HTTP/1.1 200 OK' );

        $request_body = file_get_contents('php://input');
        $data = json_decode($request_body, true)['body'];

        $receipt_number = $data['receipt_number'];
        $last_four = $data['last_four'];
        $brand = $data['brand'];
        $created_at = $data['created_at'];
        $amount = $data['amount'];
        $service_fee = $data['service_fee'];
        $state = $data['state'];
        $tags = $data['tags'];
        $metadata = $data['metadata'];
        $payor_id = $data['payor_id'];
        $payment_method_id = $data['payment_method_id'];

        WC()->session->set('my-receipt_number', $receipt_number);
        WC()->session->set('last_four', $last_four);
        WC()->session->set('brand', $brand);
        WC()->session->set('created_at', $created_at);
        WC()->session->set('amount', $amount);
        WC()->session->set('service_fee', $service_fee);
        WC()->session->set('state', $state);
        WC()->session->set('tags', $tags);
        WC()->session->set('metadata', $metadata);
        WC()->session->set('payor_idr', $payor_id);
        WC()->session->set('payment_method_id', $payment_method_id);
    }

    /**
     * Processing the woocommerce order after the payment has been completed successfully
     * @param $order_id
     * @return array
     */
    public function process_payment( $order_id ): array
    {
        global $woocommerce;

        $order = wc_get_order( $order_id );

        $receipt_number = WC()->session->get('my-receipt_number');
        $created_at = WC()->session->get('created_at');
        $amount = WC()->session->get('amount');
        $state = WC()->session->get('state');

        $graphqlEndpoint = "https://internal.$this->partner.$this->stage.com/graphql";

        $accountCode = $order_id;
        $transactionId = $receipt_number;

        $graphqlQuery = 'mutation updateTransactionAccountCode($var1: String!, $var2: String!) { updateTransactionAccountCode(account_code: $var1, transaction_id: $var2) }';

        $body = array(
            'query' => $graphqlQuery,
            'variables' => array(
                'var1' => $accountCode,
                'var2' => $transactionId
            ),
            'operationName' => 'updateTransactionAccountCode'
        );

        $args = array(
            'headers' => array(
                'Authorization' => $this->api_key
            ),
            'body'    => json_encode($body),
            'timeout' => 60, // here, the timeout is set to 60 seconds
        );

        wp_remote_post($graphqlEndpoint, $args);

        // Mark payment complete and reduce stock
        $order->payment_complete();

        // some notes to customer (replace true with false to make it private)
        $order->add_order_note( "Pay Theory Receipt Number: $receipt_number", true );
        $order->add_order_note( "Created At: $created_at", true );
        $order->add_order_note( "Amount Charged: $amount", true );
        $order->add_order_note( "Payment Status: $state", true );

        // Empty WC cart
        $woocommerce->cart->empty_cart();

        // Redirect to the thank you page
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $order )
        );
    }
}