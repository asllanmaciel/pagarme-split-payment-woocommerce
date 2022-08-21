<?php
/**
 * Plugin Name: Pagar.me Split Payment for WooCommerce
 * Description: Allow you to define partners to split payment with using Pagar.me gateway.
 * Version: 1.2.1
 * Author: Insus Tecnologia
 * Author URI: https://insus.com.br
 * Text Domain: pagarme-split-payment
 * Domain Path: /i18n/languages/
 *
 * @package PagarmeSplitPayment
 */

defined( 'ABSPATH' ) || exit;
define( 'PLUGIN_NAME', 'Pagar.me Split Payment' );

require_once( __DIR__ . '/vendor/autoload.php' );

class PagarmeSplitWooCommerce {
	public static function run() {
		\Carbon_Fields\Carbon_Fields::boot();

		// CPTs
		( new \PagarmeSplitPayment\Cpts\ProductCustomPostType() )->create();
		( new \PagarmeSplitPayment\Cpts\ShopOrderCustomPostType() )->create();

		// Business rules
		( new \PagarmeSplitPayment\Pagarme\SplitRules() )->addSplit();

		// Admin
		( new \PagarmeSplitPayment\Admin\Actions() )
			->createRecipients()
			->createAdminNotices();
		( new \PagarmeSplitPayment\Admin\PluginOptions() )->create();

		// Roles
		( new \PagarmeSplitPayment\Roles\PartnerRole() )->create();

		add_filter( 'woocommerce_settings_api_form_fields_woo-pagarme-payments', array( self::class, 'add_secret_key_field' ) );
	}

	public static function add_secret_key_field( array $fields ) {
		$start_fields_key = array_slice( array_keys( $fields ), 0, 2 );
		$end_fields_key   = array_slice( array_keys( $fields ), 2 );
		$new_fields       = array();
		foreach ( $start_fields_key as $field_key ) {
			$new_fields[ $field_key ] = $fields[ $field_key ];
		}

		$new_fields['api_sk'] = array(
			'title' => 'Secret Key',
			'type'  => 'text',
		);

		foreach ( $end_fields_key as $field_key ) {
			$new_fields[ $field_key ] = $fields[ $field_key ];
		}

		return $new_fields;
	}
}

add_action(
	'after_setup_theme',
	function() {
		PagarmeSplitWooCommerce::run();
	}
);
