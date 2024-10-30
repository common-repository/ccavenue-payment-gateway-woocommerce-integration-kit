<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

include_once( 'class-wc-gateway-ccavenue-response.php' );

/**
 * Handles responses from CCAvenue IPN
 */
class WC_Gateway_CCAvenue_IPN_Handler extends WC_Gateway_CCAvenue_Response {

	/** @var string Sandbox */
	protected $sandbox;

	/** @var string Working Key */
	protected $working_key;

	/** @var string Gateway */
	protected $gateway;

	/**
	 * Constructor
	 */
	public function __construct( $sandbox = false, $working_key = '', $gateway ) {
		include_once( 'crypto-ccavenue.php' );
		add_action( 'woocommerce_api_wc_gateway_ccavenue', array( $this, 'check_response' ) );
		add_action( 'valid-ccavenue-standard-ipn-request', array( $this, 'valid_response' ) );

		$this->sandbox        = $sandbox;
		$this->working_key    = $working_key;
		$this->gateway        = $gateway;
	}

	/**
	 * Check for CCAvenue IPN Response
	 */
	public function check_response() {
		if ( ! empty( $_POST ) ) {
			$posted = wp_unslash( $_POST );

			do_action( "valid-ccavenue-standard-ipn-request", $posted );
			exit;
		}
		wp_die( "CCAvenue IPN Request Failure", "CCAvenue IPN", array( 'response' => 500 ) );
	}

	/**
	 * There was a valid response
	 * @param  array $posted Post data after wp_unslash
	 */
	public function valid_response( $posted ) {
		if(isset($posted['encResp']))
		{
			$encResponse = $posted["encResp"];         
			WC_Gateway_CCAvenue::log('encResponse'.$encResponse);
			$rcvdString  = decrypt($encResponse,$this -> working_key);
			WC_Gateway_CCAvenue::log('working_key'.$this->working_key);
			WC_Gateway_CCAvenue::log('rcvdString'.$rcvdString);
			$decryptValues = array();
			parse_str( $rcvdString, $decryptValues );
			WC_Gateway_CCAvenue::log('decryptValues'.print_r($decryptValues, true));  
			$order_id_time = $decryptValues['order_id'];
			$order_id = explode('_', $decryptValues['order_id']);
			$order_id = (int)$order_id[0];			
			if($order_id != ''){
				try{
					$order = new WC_Order($order_id);
					$order_status = $decryptValues['order_status'];
					$transauthorised = false;
					if($order -> status !=='completed'){
						// Lowercase returned variables
						$order_status = strtolower( $order_status );
						
						WC_Gateway_CCAvenue::log( 'Found order #' . $order->id );
						WC_Gateway_CCAvenue::log( 'Payment status: ' . $order_status );
			
						if ( method_exists( $this, 'payment_status_' . $order_status ) ) {
							call_user_func( array( $this, 'payment_status_' . $order_status ), $order, $decryptValues );
						}
						
					}					
				}catch(Exception $e){
					$order->update_status( 'failed', sprintf( __( 'Payment %s via IPN.' . $posted['failure_message'], 'ccave' ), wc_clean( strtolower( $posted['order_status'] ) ) ) );
				}				
			}				
		}
	}


	/**
	 * Check currency from IPN matches the order
	 * @param  WC_Order $order
	 * @param  string $currency
	 */
	protected function validate_currency( $order, $currency ) {
		// Validate currency
		if ( $order->get_order_currency() != $currency ) {
			WC_Gateway_CCAvenue::log( 'Payment error: Currencies do not match (sent "' . $order->get_order_currency() . '" | returned "' . $currency . '")' );

			// Put this order on-hold for manual checking
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: CCAvenue currencies do not match (code %s).', 'ccave' ), $currency ) );
			exit;
		}
	}

	/**
	 * Check payment amount from IPN matches the order
	 * @param  WC_Order $order
	 */
	protected function validate_amount( $order, $amount ) {
		if ( number_format( $order->get_total(), 2, '.', '' ) != number_format( $amount, 2, '.', '' ) ) {
			WC_Gateway_CCAvenue::log( 'Payment error: Amounts do not match (gross ' . $amount . ')' );

			// Put this order on-hold for manual checking
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: CCAvenue amounts do not match (gross %s).', 'ccave' ), $amount ) );
			exit;
		}
	}

	/**
	 * Handle a completed payment
	 * @param  WC_Order $order
	 */
	protected function payment_status_success( $order, $posted ) {
		if ( $order->has_status( 'completed' ) ) {
			WC_Gateway_CCAvenue::log( 'Aborting, Order #' . $order->id . ' is already complete.' );
			exit;
		}

		$this->validate_currency( $order, $posted['currency'] );
		$this->validate_amount( $order, $posted['amount'] );
		$this->save_ccavenue_meta_data( $order, $posted );

		if ( 'success' === strtolower( $posted['order_status'] ) ) {
			$this->payment_complete( $order, ( ! empty( $posted['tracking_id'] ) ? wc_clean( $posted['tracking_id'] ) : '' ), __( 'IPN payment completed', 'ccave' ) );

		} else {
			$this->payment_on_hold( $order, sprintf( __( 'Payment pending: %s', 'ccave' ), $posted['status_message'] ) );
		}
		wp_redirect( $this->gateway->get_return_url( $order ) );
		exit();
	}


	/**
	 * Handle a failed payment
	 * @param  WC_Order $order
	 */
	protected function payment_status_failure( $order, $posted ) {
		$order->update_status( 'failed', sprintf( __( 'Payment %s via CCAvenue. ' . $posted['failure_message'], 'ccave' ), wc_clean( strtolower( $posted['order_status'] ) ) ) );
		wp_redirect( $this->gateway->get_return_url( $order ) );
		exit();
	}

	/**
	 * Handle a denied payment
	 * @param  WC_Order $order
	 */
	protected function payment_status_aborted( $order, $posted ) {
		$order->update_status( 'cancelled', sprintf( __( 'Payment %s by user. ' . $posted['failure_message'], 'ccave' ), wc_clean( strtolower( $posted['order_status'] ) ) ) );
		wc_add_notice(__('You seem to have cancelled the transaction. Please proceed to pay again','ccave'),'notice');
		if (WC()->cart->get_cart_contents_count()===0) {
			wp_redirect( $this->gateway->get_return_url( $order ) );
		}
		else {
			wp_redirect( wc_get_page_permalink( 'cart' ) );
		}
		exit();
	}

	/**
	 * Handle an expired payment
	 * @param  WC_Order $order
	 */
	protected function payment_status_invalid( $order, $posted ) {
		$this->payment_status_failure( $order, $posted );
	}


	/**
	 * Save important data from the IPN to the order
	 * @param WC_Order $order
	 */
	protected function save_ccavenue_meta_data( $order, $posted ) {
		if ( ! empty( $posted['tracking_id'] ) ) {
			update_post_meta( $order->id, 'CCAvenue Tracking ID', wc_clean( $posted['tracking_id'] ) );
		}
		if ( ! empty( $posted['bank_ref_no'] ) ) {
			update_post_meta( $order->id, 'CCAvenue Bank Ref No', wc_clean( $posted['bank_ref_no'] ) );
		}
		if ( ! empty( $posted['first_name'] ) ) {
			update_post_meta( $order->id, 'Payer first name', wc_clean( $posted['first_name'] ) );
		}
		if ( ! empty( $posted['last_name'] ) ) {
			update_post_meta( $order->id, 'Payer last name', wc_clean( $posted['last_name'] ) );
		}
		if ( ! empty( $posted['payment_type'] ) ) {
			update_post_meta( $order->id, 'Payment type', wc_clean( $posted['payment_type'] ) );
		}
	}

	/**
	 * Send a notification to the user handling orders.
	 * @param  string $subject
	 * @param  string $message
	 */
	protected function send_ipn_email_notification( $subject, $message ) {
		$new_order_settings = get_option( 'woocommerce_new_order_settings', array() );
		$mailer             = WC()->mailer();
		$message            = $mailer->wrap_message( $subject, $message );

		$mailer->send( ! empty( $new_order_settings['recipient'] ) ? $new_order_settings['recipient'] : get_option( 'admin_email' ), $subject, $message );
	}
}