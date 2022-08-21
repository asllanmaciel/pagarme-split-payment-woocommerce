<?php

namespace PagarmeSplitPayment\Pagarme;

use WC_Payment_Gateways;

class ClientSingleton {
	private static $client;
	private $api_url = 'https://api.pagar.me/core/v5/';
	private $api_key;

	private function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	public static function get_instance() {
		if ( self::$client === null ) {
			$gateways = WC_Payment_Gateways::instance()->payment_gateways();

			if ( ! isset( $gateways['woo-pagarme-payments'] ) ) {
				throw new \Exception( __( 'Configure Pagar.me API key' ) );
			}

			$api_key      = $gateways['woo-pagarme-payments']->get_option( 'api_sk' );
			self::$client = new self( $api_key );
		}

		return self::$client;
	}

	public function create_recipient( array $recipient_data ) {
		$response = $this->do_request(
			'recipients',
			'POST',
			array(
				'name'                 => $recipient_data['legal_name'],
				'document'             => $recipient_data['document'],
				'type'                 => $recipient_data['recipient_type'],
				'default_bank_account' => $this->bank_account_data( $recipient_data ),
				'transfer_settings'    => array(
					'transfer_enabled'  => false,
					'transfer_interval' => 'Monthly',
					'transfer_day'      => 1,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$response_data = json_decode( $response['body'], true );

		if ( ! empty( $response_data['errors'] ) ) {
			error_log( print_r( $response_data['errors'], true ) );
			throw new \Exception( 'Não foi possível criar recebedor.' );
		}

		return $response_data;
	}

	public function update_recipient( array $recipient_data ) {
		$this->update_bank_data( $recipient_data );

		$response = $this->do_request(
			'recipients/' . $recipient_data['recipient_id'],
			'PUT',
			array(
				'name' => $recipient_data['legal_name'],
				'type' => $recipient_data['recipient_type'],
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$response_data = json_decode( $response['body'], true );

		if ( ! empty( $response_data['errors'] ) ) {
			error_log( print_r( $response_data['errors'], true ) );
			throw new \Exception( 'Não foi possível atualizar recebedor.' );
		}

		return $response_data;
	}

	protected function get_error_message( array $errors, $operation = '' ): string {
		$errors_w_message = array_filter( $errors, fn( $error ) => isset( $error['message'] ) );

		if ( empty( $errors_w_message ) ) {
			return 'Não foi possível realizar ' . $operation;
		}

		$messages = array_map( fn( $error) => $error['message'], $errors_w_message );

		return implode( '<br />', $messages );
	}

	protected function bank_account_data( array $recipient_data ): array {
		$bank_account_data = array(
			'holder_name'         => $recipient_data['legal_name'],
			'holder_type'         => $recipient_data['recipient_type'],
			'bank'                => $recipient_data['bank_code'],
			'branch_number'       => $recipient_data['agency'],
			'account_number'      => $recipient_data['account'],
			'account_check_digit' => $recipient_data['account_digit'],
			'holder_document'     => $recipient_data['document'],
			'type'                => $recipient_data['account_type'],
		);

		if ( ! empty( $recipient_data['agency_digit'] ) ) {
			$bank_account_data['branch_check_digit'] = $recipient_data['agency_digit'];
		}

		return $bank_account_data;
	}

	protected function do_request( $endpoint, $method = 'POST', $data = array(), $headers = array() ) {
		$params = array(
			'method'  => $method,
			'timeout' => 60,
		);

		if ( ! empty( $data ) ) {
			$params['body'] = wp_json_encode( $data );
		}

		// Pagar.me user-agent and api version.
		$x_pagarme_useragent = 'pagarme-split-woocommerce/1';

		if ( defined( 'WC_VERSION' ) ) {
			$x_pagarme_useragent .= ' woocommerce/' . WC_VERSION;
		}

		$x_pagarme_useragent .= ' wordpress/' . get_bloginfo( 'version' );
		$x_pagarme_useragent .= ' php/' . phpversion();

		$params['headers'] = array(
			'User-Agent'           => $x_pagarme_useragent,
			'X-PagarMe-User-Agent' => $x_pagarme_useragent,
			'Authorization'        => 'Basic ' . base64_encode( $this->api_key . ':' ),
			'Content-Type'         => 'application/json',
		);

		if ( ! empty( $headers ) ) {
			$params['headers'] = array_merge( $params['headers'], $headers );
		}

		return wp_safe_remote_post( $this->get_api_url() . $endpoint, $params );
	}

	protected function get_api_url() {
		return $this->api_url;
	}
}
