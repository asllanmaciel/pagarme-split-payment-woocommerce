<?php

namespace PagarmeSplitPayment\Pagarme;

use WC_Payment_Gateways;

class ClientSingleton {
	private static $client;
	private $api_url = 'https://api.pagar.me/1/';
	//private $api_url = 'https://api.pagar.me/core/v5/';
	private $api_key;

	private function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	public static function get_instance() {
		if ( self::$client === null ) {
			$gateways = WC_Payment_Gateways::instance()->payment_gateways();

			if ( ! isset( $gateways['pagarme-credit-card'] ) ) { //pagarme-credit-card
				throw new \Exception( __( 'Configure Pagar.me API key' ) );
			}

			//$api_key      = $gateways['pagarme-credit-card']->get_option( 'api_key' );
			$api_key      = $gateways['pagarme-credit-card']->get_option( 'api_key' );
			self::$client = new self( $api_key );
		}

		//echo $api_key;
		//echo '<br>--------------->>>>>>>>>>>>><br>';
		//json_encode($gateways['pagarme-credit-card'],JSON_PRETTY_PRINT);


		return self::$client;
	}

	public function create_recipient( array $recipient_data ) {
		/*
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
		*/

		$dados['anticipatable_volume_percentage'] = '85';
		$dados['api_key'] = $this->api_key;
		$dados['automatic_anticipation_enabled'] = 'true';

		$bank_account['bank_code'] = $recipient_data['bank_code'];
		$bank_account['agencia'] = $recipient_data['agency'];
		$bank_account['agencia_dv'] = $recipient_data['agency_digit'];
		$bank_account['conta'] = $recipient_data['account'];
		$bank_account['type'] = $recipient_data['account_type'];
		$bank_account['conta_dv'] = $recipient_data['account_digit'];
		$bank_account['document_number'] = $recipient_data['document'];
		$bank_account['legal_name'] = $recipient_data['legal_name'];

		$dados['bank_account'] = $bank_account;

		$dados['transfer_day'] = '1';
		$dados['transfer_enabled'] = false;
		$dados['transfer_interval'] = 'monthly'; //daily, weekly, monthly

		$dados['postback_url'] = '';
		
		

		$response = $this->do_request(
			'recipients',
			'POST',
			$dados
		);

		/*
		echo json_encode($recipient_data,JSON_PRETTY_PRINT);

		echo '<br>--------------->>>>>>>>>>>>><br>'; 

		echo json_encode($dados,JSON_PRETTY_PRINT);

		echo '<br>--------------->>>>>>>>>>>>><br>';
		*/

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$response_data = json_decode( $response['body'], true );

		//var_dump($response_data);

		if ( ! empty( $response_data['errors'] ) ) {
			error_log( print_r( $response_data['errors'], true ) );
			throw new \Exception( 'Não foi possível criar recebedor.' );

			//print_r( $response_data['errors']);
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

	public function update_bank_data( array $recipient_data ) {
		$response = $this->do_request(
			'recipients/' . $recipient_data['recipient_id'] . '/default-bank-account',
			'PATCH',
			array(
				'bank_account' => $this->bank_account_data( $recipient_data ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$response_data = json_decode( $response['body'], true );

		if ( ! empty( $response_data['errors'] ) ) {
			error_log( print_r( $response_data['errors'], true ) );
			throw new \Exception( 'Não foi possível atualizar dados bancários do recebedor.' );
		}
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
			'legal_name'         	=> $recipient_data['legal_name'],
			'holder_type'         	=> $recipient_data['recipient_type'],
			'bank_code'             => $recipient_data['bank_code'],
			'agencia'       		=> $recipient_data['agency'],
			'conta'      			=> $recipient_data['account'],
			'conta_dv' 				=> $recipient_data['account_digit'],
			'document_number'     	=> $recipient_data['document'],
			'type'                	=> $recipient_data['account_type'],
		);
		


		if ( ! empty( $recipient_data['agency_digit'] ) ) {
			$bank_account_data['agencia_dv'] = $recipient_data['agency_digit'];
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
			//'User-Agent'           => $x_pagarme_useragent,
			//'X-PagarMe-User-Agent' => $x_pagarme_useragent,
			//'Authorization'        => 'Basic ' . base64_encode( $this->api_key . ':' ),
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
