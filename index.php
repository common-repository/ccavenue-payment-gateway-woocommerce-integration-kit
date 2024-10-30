<?php
/*
Plugin Name: CCAvenue Payment Gateway for WooCommerce
Plugin URI: http://www.travelldone.com
Description: CCAvenue Payment gateway for woocommerce based on standards
Version: 0.1
Author: Saifee Ratlamwala
Author URI: http://www.saifee.net
    Copyright: © 2009-2016 Saifee.
    License: GNU General Public License v3.0
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('plugins_loaded', 'wc_gateway_ccavenue_init', 0);

function wc_gateway_ccavenue_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
	/**
	 * CCAvenue Standard Payment Gateway
	 *
	 * Provides a CCAvenue Standard Payment Gateway.
	 *
	 * @class 		WC_Gateway_CCAvenue
	 * @extends		WC_Payment_Gateway
	 * @version		2.3.0
	 * @package		WooCommerce/Classes/Payment
	 * @author 		WooThemes
	 */
	class WC_Gateway_CCAvenue extends WC_Payment_Gateway {
	
		/** @var boolean Whether or not logging is enabled */
		public static $log_enabled = false;
	
		/** @var WC_Logger Logger instance */
		public static $log = false;
	
		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			$this->id                 = 'ccavenue';
			$this->has_fields         = false;
			$this->order_button_text  = __( 'Proceed to Pay', 'ccave' );
			$this->method_title       = __( 'CCAvenue', 'ccave' );
			$this->icon         	  = plugins_url( 'images/logo.gif' , __FILE__ );
			$this->method_description = sprintf( __( 'CCAvenue standard sends customers to CCAvenue PG to enter their payment information.', 'ccave' ), '<a href="' . admin_url( 'admin.php?page=wc-status' ) . '">', '</a>' );
			$this->supports           = array(
				'products'
			);
	
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
	
			// Define user set variables
			$this->title          = $this->get_option( 'title' );
			$this->description    = $this->get_option( 'description' );
			$this->testmode       = 'yes' === $this->get_option( 'testmode', 'no' );
			$this->debug          = 'yes' === $this->get_option( 'debug', 'no' );
			//$this->email          = $this->get_option( 'email' );
			//$this->receiver_email = $this->get_option( 'receiver_email', $this->email );
			$this->merchant_id    = $this->get_option( 'merchant_id');
	            	$this->working_key    = $this->get_option('working_key');
	            	$this->access_code    = $this->get_option('access_code');
	            	$this->notify_url     = WC()->api_request_url( 'WC_Gateway_CCAvenue' );
	
			self::$log_enabled    = $this->debug;
			
			add_action('valid-ccavenue-request', array($this, 'successful_request'));
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
	
			include_once( 'includes/class-wc-gateway-ccavenue-ipn-handler.php' );
			new WC_Gateway_CCAvenue_IPN_Handler( $this->testmode, $this->working_key, $this );
			WC_Gateway_CCAvenue::log('Start Logging');
		}
	
		/**
		 * Logging method
		 * @param  string $message
		 */
		public static function log( $message ) {
			if ( self::$log_enabled ) {
				if ( empty( self::$log ) ) {
					self::$log = new WC_Logger();
				}
				self::$log->add( 'ccavenue', $message );
			}
		}
	
	
		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @since 1.0.0
		 */
		public function admin_options() {
			echo '<h3>'.__('CCAvenue Payment Gateway', 'ccave').'</h3>';
			echo '<p>'.__('CCAvenue is most popular payment gateway for online shopping in India').'</p>';
			echo '<table class="form-table">';
			$this -> generate_settings_html();
			echo '</table>';
		}
	
		/**
		 * Initialise Gateway Settings Form Fields
		 */
		public function init_form_fields() {
			$this->form_fields = include( 'includes/settings-ccavenue.php' );
		}
		
			
		/**
		 * Get the CCAvenue request URL for an order
		 * @param  WC_Order  $order
		 * @param  boolean $sandbox
		 * @return string
		 */
		public function get_request_url( $order, $sandbox = false ) {
			if ( $sandbox ) {
				return 'https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction';
			} else {
				return 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction';
			}
		}
		
		/**
         	*  There are no payment fields for CCAvenue, but we want to show the description if set.
         	**/
	        function payment_fields(){
        		if($this -> description) echo wpautop(wptexturize($this -> description));
        	}
		
	        /**
	         * Receipt Page
	         **/
	        public function receipt_page( $order_id ) {
			//$order = wc_get_order( $order_id );
	        	WC_Gateway_CCAvenue::log('Inside Receipt Page');
			echo '<p>'.__('Thank you for your order, we are now directing you to CCAvenue page where you can complete your payment.', 'ccave').'</p>';
			echo $this -> generate_ccavenue_form($order_id);
	        }
	        
	        /**
	         * Generate CCAvenue button link
	         **/
	        public function generate_ccavenue_form($order_id){
	        	WC_Gateway_CCAvenue::log('Generating Form');
			global $woocommerce;
			include_once( 'includes/crypto-ccavenue.php' );
			$order = new WC_Order($order_id);
			$order_id = $order_id.'_'.date("ymds");
			$ccavenue_args = array(
			        'merchant_id'      => $this -> merchant_id,
			        'amount'           => $order -> order_total,
			        'order_id'         => $order_id,
			        'redirect_url'     => $this->notify_url,
			        'cancel_url'       => $this->notify_url,
			        'billing_name'     => $order -> billing_first_name .' '. $order -> billing_last_name,
			        'billing_address'  => trim($order -> billing_address_1, ','),
			        'billing_country'  => wc()->countries -> countries [$order -> billing_country],
			        'billing_state'    => $order -> billing_state,
			        'billing_city'     => $order -> billing_city,
			        'billing_zip'      => $order -> billing_postcode,
			        'billing_tel'      => $order->billing_phone,
			        'billing_email'    => $order -> billing_email,
			        'delivery_name'    => $order -> shipping_first_name .' '. $order -> shipping_last_name,
			        'delivery_address' => $order -> shipping_address_1,
			        'delivery_country' => $order -> shipping_country,
			        'delivery_state'   => $order -> shipping_state,
			        'delivery_tel'     => '',
			        'delivery_city'    => $order -> shipping_city,
			        'delivery_zip'     => $order -> shipping_postcode,
			        'language'         => 'EN',
			        'currency'         => get_woocommerce_currency()
	                );
	
			foreach($ccavenue_args as $param => $value) {
				$paramsJoined[] = "$param=$value";
			}
			
			$merchant_data   = implode('&', $paramsJoined);
			$encrypted_data = encrypt($merchant_data, $this -> working_key);
			$ccavenue_args_array   = array();
			$ccavenue_args_array[] = "<input type='hidden' name='encRequest' value='$encrypted_data'/>";
			$ccavenue_args_array[] = "<input type='hidden' name='access_code' value='{$this->access_code}'/>";
			
			wc_enqueue_js( '$.blockUI({
				message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to CcAvenue to make payment.', 'ccave' ) ) . '",
				baseZ: 99999,
				overlayCSS:
				{
					background: "#fff",
					opacity: 0.6
				},
				css: {
					padding:        "20px",
					zindex:         "9999999",
					textAlign:      "center",
					color:          "#555",
					border:         "3px solid #aaa",
					backgroundColor:"#fff",
					cursor:         "wait",
					lineHeight:     "24px",
			        }
				});
			jQuery("#submit_ccavenue_payment_form").click();' );
	
			$form = '<form action="' . esc_url( $this->get_request_url( $order, $this->testmode ) ) . '" method="post" id="ccavenue_payment_form" target="_top">
				' . implode( '', $ccavenue_args_array ) . '
				<!-- Button Fallback -->
				<div class="payment_buttons">
				<input type="submit" class="button alt" id="submit_ccavenue_payment_form" value="' . __( 'Pay via CCAvenue', 'ccave' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'ccave' ) . '</a>
				</div>
				<script type="text/javascript">
				jQuery(".payment_buttons").hide();
				</script>
				</form>';
			return $form;
		}
	
		/**
		 * Get the transaction URL.
		 *
		 * @param  WC_Order $order
		 *
		 * @return string
		 */
		/*public function get_transaction_url( $order ) {
			if ( $this->testmode ) {
				$this->view_transaction_url = 'https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction';
			} else {
				$this->view_transaction_url = 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction';
			}
			return parent::get_transaction_url( $order );
		}*/
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {	
			$order          = wc_get_order( $order_id );
	
			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			);
		}
		
		// get all pages
		function get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
					$has_parent = $page->post_parent;
					while($has_parent) {
						$prefix .=  ' - ';
						$next_page = get_page($has_parent);
						$has_parent = $next_page->post_parent;
					}
				}
				// add to page list array array
				$page_list[$page->ID] = $prefix . $page->post_title;
			}
			return $page_list;
		}
	}
		
	/**
	* Add the Gateway to WooCommerce
	**/
	function woocommerce_add_wc_gateway_ccavenue($methods) {
		$methods[] = 'WC_Gateway_CCAvenue';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_wc_gateway_ccavenue' );
}
?>