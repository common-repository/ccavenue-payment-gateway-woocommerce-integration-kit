<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for CCAvenue Gateway
 */
return array(
	'enabled' => array(
		'title' => __('Enable/Disable', 'ccave'),
		'type' => 'checkbox',
		'label' => __('Enable CCAvenue Payment Module.', 'ccave'),
		'default' => 'no'
	),
	'title' => array(
		'title' => __('Title:', 'ccave'),
		'type'=> 'text',
		'description' => __('This controls the title which the user sees during checkout.', 'ccave'),
		'default' => __('CCAvenue', 'ccave')
	),
	'description' => array(
		'title' => __('Description:', 'ccave'),
		'type' => 'textarea',
		'description' => __('This controls the description which the user sees during checkout.', 'ccave'),
		'default' => __('Pay securely by Credit or Debit card or internet banking through CCAvenue Secure Servers.', 'ccave')
	),
	'merchant_id' => array(
		'title' => __('Merchant ID', 'ccave'),
		'type' => 'text',
		'description' => __('This id(USER ID) available at "Generate Working Key" of "Settings and Options at CCAvenue."','ccave')
	),
	'working_key' => array(
		'title' => __('Working Key', 'ccave'),
		'type' => 'text',
		'description' =>  __('Given to Merchant by CCAvenue', 'ccave'),
	),
	'access_code' => array(
		'title' => __('Access Code', 'ccave'),
		'type' => 'text',
		'description' =>  __('Given to Merchant by CCAvenue', 'ccave'),
	),
	'testmode' => array(
		'title'       => __( 'CCAvenue Sandbox', 'ccave' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable CCAvenue sandbox', 'ccave' ),
		'default'     => 'no',
		'description' => __( 'CCAvenue sandbox can be used to test payments.', 'ccave' ),
	),
	'debug' => array(
		'title'       => __( 'Debug Log', 'ccave' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable logging', 'ccave' ),
		'default'     => 'no',
		'description' => sprintf( __( 'Log CCAvenue events, such as requests, inside <code>%s</code>', 'ccave' ), wc_get_log_file_path( 'ccavenue' ) )
	),
);
