<?php
/*
Plugin Name: Another Payment Gateway
Author: Phaedra Solutions
*/

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'init_custom_gateway_class');
function init_custom_gateway_class(){

  class WC_Gateway_Custom extends WC_Payment_Gateway {

    public $domain;

    /**
     * Constructor for the gateway.
     */
    public function __construct() {

      $plugin_dir = plugin_dir_url(__FILE__);
      $this->domain = 'custom_payment';

      $this->id                 = 'custom';
      $this->icon               = apply_filters('woocommerce_custom_gateway_icon', '');
      $this->has_fields         = false;
      $this->method_title       = __( 'Custom', $this->domain );
      $this->method_description = __( 'Allows payments with AnotherPay gateway.', $this->domain );

      // Load the settings.
      $this->init_form_fields();
      $this->init_settings();

      // Define user set variables
      $this->title        = $this->get_option( 'title' );
      $this->description  = $this->get_option( 'description' );
      $this->instructions = $this->get_option( 'instructions', $this->description );
      $this->order_status = $this->get_option( 'order_status', 'processing' );
      $this->icon = apply_filters( 'woocommerce_gateway_icon', $plugin_dir.'\assets\another.png' );
      // Actions
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      // Customer Emails
      add_action( 'woocommerce_api_anotherpay_hook', array( $this, 'webhook' ) );



    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {

      $this->form_fields = array(
        'enabled' => array(
          'title'   => __( 'Enable/Disable', $this->domain ),
          'type'    => 'checkbox',
          'label'   => __( 'Enable AnotherPay', $this->domain ),
          'default' => 'yes'
        ),
        'title' => array(
          'title'       => __( 'Title', $this->domain ),
          'type'        => 'text',
          'description' => __( 'This controls the title which the user sees during checkout.', $this->domain ),
          'default'     => __( 'Custom Payment', $this->domain ),
          'desc_tip'    => true,
        ),
        'order_status' => array(
          'title'       => __( 'Order Status', $this->domain ),
          'type'        => 'select',
          'class'       => 'wc-enhanced-select',
          'description' => __( 'Choose whether status you wish after checkout.', $this->domain ),
          'default'     => 'wc-processing',
          'desc_tip'    => true,
          'options'     => wc_get_order_statuses()
        ),
        'description' => array(
          'title'       => __( 'Description', $this->domain ),
          'type'        => 'textarea',
          'description' => __( 'Payment method description that the customer will see on your checkout.', $this->domain ),
          'default'     => __('AnotherPay Checkout', $this->domain),
          'desc_tip'    => true,
        ),
        'is_live' => array(
          'title'   => __( 'Make Gateway Live', $this->domain ),
          'type'    => 'checkbox',
          'label'   => __( 'Set live', $this->domain ),
          'default' => 'yes'
        ),
        'sandbox_gateway_url' => array(
          'title'       => __( 'Sandbox Gateway URL', $this->domain ),
          'type'        => 'text',
          'description' => __( 'Post checkout details to this URL.', $this->domain ),
          'default'     => __('http://evoucher.mashup.li/outside_payments', $this->domain),
          'desc_tip'    => true,
        ),
        'live_gateway_url' => array(
          'title'       => __( 'Live Gateway URL', $this->domain ),
          'type'        => 'text',
          'description' => __( 'Post checkout details to this URL.', $this->domain ),
          'default'     => __('https://anotherpay.an-other.co.uk/outside_payments', $this->domain),
          'desc_tip'    => true,
        ),
        'unique_merchant_reference' => array(
          'title'       => __( 'Merchant Reference number', $this->domain ),
          'type'        => 'text',
          'description' => __( 'Unique Merchant Reference Number', $this->domain ),
          'default'     => __('', $this->domain),
          'desc_tip'    => true,
        ),
        'instructions' => array(
          'title'       => __( 'Instructions', $this->domain ),
          'type'        => 'textarea',
          'description' => __( 'Thankyou for paying with AnotherPay.', $this->domain ),
          'default'     => '',
          'desc_tip'    => true,
        ),
      );
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page() {
      if ( $this->instructions )
        echo wpautop( wptexturize( $this->instructions ) );
    }

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
      if ( $this->instructions && ! $sent_to_admin && 'custom' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
          echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
      }
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id ) {

      // global $woocommerce;
      $order = wc_get_order( $order_id );
      // checking for transiction
      $merchant_reference = $this->get_option( 'unique_merchant_reference');
      if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>=' ) ) {
        $order_data = $order->get_data();  
        $currency = $order_data["currency"];
      }
      else{
        $order_meta = get_post_meta($order_id);
        $currency = $order_meta["_order_currency"][0];
      }

      if ($this->get_option( 'is_live' )){
        $gateway_url = $this->get_option( 'sandbox_gateway_url');
      } else {
        $gateway_url = $this->get_option( 'live_gateway_url');
      }

      $query_string  = '?mainamount=' . $order->order_total;
      $query_string .= '&currencyiso3a=' . $currency;
      $query_string .= '&orderreference=' . $merchant_reference . "-" . $order_id;
      $query_string .= '&api=true';
      $query_string .= '&billingfirstname=' . $order->billing_first_name;
      $query_string .= '&billinglastname=' . $order->billing_last_name;
      $query_string .= '&billingstreet=' . $order->billing_address_1;
      $query_string .= '&billingtown=' . $order->billing_city;
      $query_string .= '&billingpostcode=' . $order->billing_postcode;
      $query_string .= '&billingcountryiso2a=' . $order->billing_country;
      $query_string .= '&billingtelephone=' . $order->billing_phone;
      $query_string .= '&billingemail=' . $order->billing_email;
      $query_string .= '&payment_notification_url=' . get_site_url() . "/wc-api/anotherpay_hook";
      $query_string .= '&payment_redirect_url=' . get_site_url();

      return array(
        'result' => 'success',
        'redirect' => $gateway_url . $query_string
      );

    }

    public function webhook() {
      global $woocommerce;
      $data = json_decode(file_get_contents('php://input'), true);
      $order = wc_get_order( $data['id'] );
      if($data['availed'])
      {           
        $order->reduce_order_stock();
        $order->payment_complete();
        $order->update_status('completed');
        $woocommerce->cart->empty_cart();
      }elseif(!$data['availed']){
        $order->update_status('failed');
        $order->needs_payment();
      }
    }
  }
}

add_filter( 'woocommerce_payment_gateways', 'add_custom_gateway_class' );
function add_custom_gateway_class( $methods ) {
  $methods[] = 'WC_Gateway_Custom'; 
  return $methods;
}
