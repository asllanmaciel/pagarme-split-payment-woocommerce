<?php

namespace PagarmeSplitPayment\Pagarme;

use MundiAPILib\Models\CreateOrderRequest;
use MundiAPILib\Models\CreateSplitOptionsRequest;
use MundiAPILib\Models\CreateSplitRequest;
use PagarmeSplitPayment\Entities\Partner;
use PagarmeSplitPayment\Helper;

class SplitRules {
	protected static $order_id;

	public function split( CreateOrderRequest $order_request ) {

		$order             = wc_get_order( self::$order_id );
		$partners          = $this->partnersAmountOverOrder( $order );
		$mainRecipientData = carbon_get_theme_option( 'psp_partner' );

		if (
			empty( $partners ) ||
			empty( $mainRecipientData[0] ) ||
			empty( $mainRecipientData[0]['psp_recipient_id'] )
		) {
			return;
		}

		$partnersAmount = 0;

		foreach ( $partners as $id => $partner ) {
			$partnerData = carbon_get_user_meta( $id, 'psp_partner' )[0];

			if ( empty( $partnerData['psp_recipient_id'] ) ) {
				continue;
			}

			$partnersAmount += Helper::priceInCents( $partner['value'] );
			$this->create_partners_split( $order_request, $partnerData, $partner );
		}

		// If there is no percentage to split stop function
		if ( ! $partnersAmount ) {
			return;
		}

		$this->create_main_recipient_split( $mainRecipientData, $partnersAmount, $order, $order_request );

		$this->log( $order );
	}

	protected function create_partners_split( CreateOrderRequest $order_request, $partnerData, $partner ) {
		foreach ( $order_request->payments as $payment_request ) {
			if ( ! is_array( $payment_request->split ) ) {
				$payment_request->split = array();
			}
			$split_request              = new CreateSplitRequest();
			
			/*
			$split_request->recipientId = $partnerData['psp_recipient_id'];
			$split_request->amount      = round(Helper::priceInCents( $partner['value'] ));
			$split_request->type        = 'flat';

			$split_request->options                      = new CreateSplitOptionsRequest();
			$split_request->options->liable              = true;
			$split_request->options->chargeProcessingFee = true;
			*/
			
			$split_request->recipient_id = $partnerData['psp_recipient_id'];
			$split_request->amount      = round(Helper::priceInCents( $partner['value'] ));
			$split_request->type        = 'flat';

			$split_request->options                      = new CreateSplitOptionsRequest();
			$split_request->options->liable              = true;
			$split_request->options->charge_processing_fee = true;

			$payment_request->split[] = $split_request;
		}
	}

	protected function create_main_recipient_split( $mainRecipientData, $partnersAmount, $order, $order_request ) {
		foreach ( $order_request->payments as $payment_request ) {
			$split_request              = new CreateSplitRequest();
			
			/*
			$split_request->recipientId = $mainRecipientData[0]['psp_recipient_id'];
			$split_request->amount      = round(Helper::priceInCents( $order->get_total() ) - $partnersAmount);
			$split_request->type        = 'flat';

			$split_request->options                      = new CreateSplitOptionsRequest();
			$split_request->options->liable              = true;
			$split_request->options->chargeProcessingFee = true;
			$split_request->options->chargeRemainderFee  = true;
			*/

			$split_request->recipient_id = $mainRecipientData[0]['psp_recipient_id'];
			$split_request->amount      = round(Helper::priceInCents( $order->get_total() ) - $partnersAmount);
			$split_request->type        = 'flat';

			$split_request->options                      = new CreateSplitOptionsRequest();
			$split_request->options->liable              = true;
			$split_request->options->charge_processing_fee = true;
			$split_request->options->charge_remainder  = true;

			if ( ! is_array( $payment_request->split ) ) {
				$payment_request->split = array();
			}
			$payment_request->split[] = $split_request;
		}
	}

	/**
	 * Calculate the amount that each partner should receive over the order
	 *
	 * @param mixed $order WooCommerce Order object.
	 * @return array
	 */
	//
	private function partnersAmountOverOrder( \WC_Order $order ) {
		$items    = $order->get_items();
		$partners = array();

		if ( ! $items ) {
			return $partners;
		}

		foreach ( $items as $item ) {
			foreach ( $this->getPartnersFromProduct( $item->get_product_id() ) as $partner ) {
				$userId                        = (int) $partner['psp_partner'][0]['id'];
				$partner                       = new Partner( $userId );
				$partners[ $userId ]['value'] += $partner->calculateComission( $item )->getComission();
			}
		}

		return $partners;
	}

	private function getPartnersFromProduct( int $productId ): array {
		$partners = array(
			'percentage'   => carbon_get_post_meta( $productId, 'psp_percentage_partners' ),
			'fixed_amount' => array(
				array(
					'psp_partner'         => carbon_get_post_meta( $productId, 'psp_fixed_partner' ),
					'psp_comission_value' => carbon_get_post_meta( $productId, 'psp_comission_value' ),
				),
			),
		);

		$comissionType = carbon_get_post_meta( $productId, 'psp_comission_type' );

		return $partners[ $comissionType ];
	}

	private function log( $order ) {
		// Remove all partners related to order to be sure this info will be updated
		delete_post_meta( $order->get_ID(), 'psp_order_partner' );

		$items    = $order->get_items();
		$partners = array();

		$partners_ids = array();

		if ( $items ) {
			foreach ( $items as $item ) {
				$productId       = $item->get_product_id();
				$productPartners = carbon_get_post_meta(
					$productId,
					'psp_partners'
				);

				// Get data for all partners related to this order
				foreach ( $productPartners as $partner ) {
					$partners[] = array(
						'user_id'    => $partner['psp_partner_user'][0]['id'],
						'product_id' => $productId,
						'quantity'   => $item->get_quantity(),
						'amount'     => $item->get_data()['total'] * ( $partner['psp_percentage'] / 100 ),
						'percentage' => $partner['psp_percentage'],
					);

					// Register the different partners at this order
					if ( ! in_array( $partner['psp_partner_user'][0]['id'], $partners_ids ) ) {
						$partners_ids[] = $partner['psp_partner_user'][0]['id'];
					}
				}
			}
		}

		// Turn order queriable by partner id
		foreach ( $partners_ids as $partner_id ) {
			add_post_meta( $order->get_ID(), 'psp_partner_id', $partner_id );
		}

		update_post_meta( $order->get_ID(), 'psp_order_split', $partners );
	}

	public static function set_order_id( int $order_id ) {
		self::$order_id = $order_id;
	}

	public function addSplit() {
		add_action( 'after_covert_order_request', array( $this, 'split' ), 10 );
		add_action( 'woocommerce_checkout_order_processed', array( self::class, 'set_order_id' ) );
	}
}
