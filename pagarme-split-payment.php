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
		add_action( 'upgrader_process_complete', array( self::class, 'override_api_service_on_plugin_update' ), 10, 2 );
		add_action( 'activate_pagarme-payments-for-woocommerce/woo-pagarme-payments.php', function() {
			self::override_api_service_ecommerce_module_core('pagarme-payments-for-woocommerce/woo-pagarme-payments.php');
		} );
	}

	public static function override_api_service_ecommerce_module_core( string $pagarme_plugin_id ) {
		$plugin_foder = explode( '/', $pagarme_plugin_id )[0];

		if ( empty( $plugin_foder ) ) {
			return;
		}

		$creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array() );
		WP_Filesystem( $creds );

		global $wp_filesystem;
		$api_service_path = WP_PLUGIN_DIR . '/' . $plugin_foder . '/vendor/pagarme/ecommerce-module-core/src/Kernel/Services/APIService.php';
		if ( $wp_filesystem->exists( $api_service_path ) ) {
			$wp_filesystem->delete( $api_service_path );
		}
		$wp_filesystem->copy( plugin_dir_path( __FILE__ ) . '/APIService.php', $api_service_path );
	}

	public static function override_api_service_on_plugin_update( $upgrader_object, $options ) {
		if ( $options['action'] == 'update' && $options['type'] == 'plugin' ) {
			foreach ( $options['plugins'] as $each_plugin ) {
				if ( preg_match_all( '/woo-pagarme-payments/', $each_plugin ) )
					self::override_api_service_ecommerce_module_core( 'pagarme-payments-for-woocommerce/woo-pagarme-payments.php' );
			}
		}
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

register_activation_hook(
	__FILE__,
	function() {
		$plugins = get_plugins();
		foreach ( $plugins as $key => $plugin ) {
			if ( $plugin['TextDomain'] == 'woo-pagarme-payments' ) {
				PagarmeSplitWooCommerce::override_api_service_ecommerce_module_core( $key );
				return;
			}
		}
	}
);
