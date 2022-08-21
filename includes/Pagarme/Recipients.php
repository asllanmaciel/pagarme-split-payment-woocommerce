<?php

namespace PagarmeSplitPayment\Pagarme;

use PagarmeSplitPayment\Pagarme\ClientSingleton;

class Recipients {
	private $partnerData, $client;

	public function __construct() {
		$this->client = ClientSingleton::get_instance();
	}

	public function createOrUpdate( $partnerData ) {
		$this->partnerData = $partnerData;
		$response          = null;

		try {
			$response = ! empty( $this->partnerData['psp_recipient_id'] ) ? $this->update() : $this->create();

			if ( ! isset( $response['id'] ) ) {
				throw new \Exception( print_r( $response, true ) );
			}

			return $response;
		} catch ( \Exception $e ) {
			//TODO: Add a log or something like that.. one day.
			var_dump( $e->getMessage() );
			die;
		}
	}

	private function create() {
		return $this->client->create_recipient(
			$this->getPartnerDataFormatted()
		);
	}

	private function update() {
		try {
			return $this->client->update_recipient(
				$this->getPartnerDataFormatted( true )
			);
		} catch ( \Exception $e ) {
			// Update may fail if Pagar.me account change and recipient doesnt exists
			// In this case, create another one
			return $this->create();
		}
	}

	private function getPartnerDataFormatted( $update = false ) {
		$formattedPartner = array(
			'bank_code'      => $this->partnerData['psp_bank_code'],
			'agency'         => $this->partnerData['psp_agency'],
			'agency_digit'   => $this->partnerData['psp_agency_digit'],
			'account'        => $this->partnerData['psp_account'],
			'account_digit'  => $this->partnerData['psp_account_digit'],
			'account_type'   => $this->partnerData['psp_account_type'],
			'document'       => $this->partnerData['psp_document_number'],
			'legal_name'     => $this->partnerData['psp_legal_name'],
			'recipient_type' => $this->partnerData['psp_holder_type'],
		);

		if ( ! $this->partnerData['psp_agency_digit'] ) {
			unset( $formattedPartner['agency_digit'] );
		}

		if ( ! $update ) {
			return $formattedPartner;
		}

		$formattedPartner['recipient_id'] = $this->partnerData['psp_recipient_id'];

		return $formattedPartner;
	}
}
