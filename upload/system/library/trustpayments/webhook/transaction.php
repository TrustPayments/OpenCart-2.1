<?php

namespace TrustPayments\Webhook;

/**
 * Webhook processor to handle transaction state transitions.
 */
class Transaction extends AbstractOrderRelated {

	/**
	 *
	 * @see AbstractOrderRelated::load_entity()
	 * @return \TrustPayments\Sdk\Model\Transaction
	 */
	protected function loadEntity(Request $request){
		$transaction_service = new \TrustPayments\Sdk\Service\TransactionService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		return $transaction_service->read($request->getSpaceId(), $request->getEntityId());
	}

	protected function getOrderId($transaction){
		/* @var \TrustPayments\Sdk\Model\Transaction $transaction */
		return $transaction->getMerchantReference();
	}

	protected function getTransactionId($transaction){
		/* @var \TrustPayments\Sdk\Model\Transaction $transaction */
		return $transaction->getId();
	}

	protected function processOrderRelatedInner(array $order_info, $transaction){
		/* @var \TrustPayments\Sdk\Model\Transaction $transaction */
		$transactionInfo = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $order_info['order_id']);

		$finalStates = [
			\TrustPayments\Sdk\Model\TransactionState::FAILED,
			\TrustPayments\Sdk\Model\TransactionState::VOIDED,
			\TrustPayments\Sdk\Model\TransactionState::DECLINE,
			\TrustPayments\Sdk\Model\TransactionState::FULFILL
		];

		\TrustPaymentsHelper::instance($this->registry)->ensurePaymentCode($order_info, $transaction);

		$transactionInfoState = strtoupper($transactionInfo->getState());
		if (!in_array($transactionInfoState, $finalStates)) {
			\TrustPayments\Service\Transaction::instance($this->registry)->updateTransactionInfo($transaction, $order_info['order_id']);

			switch ($transaction->getState()) {
				case \TrustPayments\Sdk\Model\TransactionState::CONFIRMED:
					$this->processing($transaction, $order_info);
					break;
				case \TrustPayments\Sdk\Model\TransactionState::PROCESSING:
					$this->confirm($transaction, $order_info);
					break;
				case \TrustPayments\Sdk\Model\TransactionState::AUTHORIZED:
					$this->authorize($transaction, $order_info);
					break;
				case \TrustPayments\Sdk\Model\TransactionState::DECLINE:
					$this->decline($transaction, $order_info);
					break;
				case \TrustPayments\Sdk\Model\TransactionState::FAILED:
					$this->failed($transaction, $order_info);
					break;
				case \TrustPayments\Sdk\Model\TransactionState::FULFILL:

					if (!in_array($transactionInfoState, ['AUTHORIZED', 'COMPLETED'])) {
						$this->authorize($transaction, $order_info);
					}
					$this->fulfill($transaction, $order_info);
					break;
				case \TrustPayments\Sdk\Model\TransactionState::VOIDED:
					$this->voided($transaction, $order_info);
					break;
				case \TrustPayments\Sdk\Model\TransactionState::COMPLETED:
					$this->waiting($transaction, $order_info);
					break;
				default:
					// Nothing to do.
					break;
			}
		}
	}

	protected function processing(\TrustPayments\Sdk\Model\Transaction $transaction, array $order_info){
		\TrustPaymentsHelper::instance($this->registry)->addOrderHistory($order_info['order_id'], 'trustpayments_processing_status_id',
				\TrustPaymentsHelper::instance($this->registry)->getTranslation('message_webhook_processing'));
	}

	protected function confirm(\TrustPayments\Sdk\Model\Transaction $transaction, array $order_info){
		\TrustPaymentsHelper::instance($this->registry)->addOrderHistory($order_info['order_id'], 'trustpayments_processing_status_id',
				\TrustPaymentsHelper::instance($this->registry)->getTranslation('message_webhook_confirm'));
	}

	protected function authorize(\TrustPayments\Sdk\Model\Transaction $transaction, array $order_info){
		\TrustPaymentsHelper::instance($this->registry)->addOrderHistory($order_info['order_id'], 'trustpayments_authorized_status_id',
				\TrustPaymentsHelper::instance($this->registry)->getTranslation('message_webhook_authorize'));
	}

	protected function waiting(\TrustPayments\Sdk\Model\Transaction $transaction, array $order_info){
		\TrustPaymentsHelper::instance($this->registry)->addOrderHistory($order_info['order_id'], 'trustpayments_completed_status_id',
				\TrustPaymentsHelper::instance($this->registry)->getTranslation('message_webhook_waiting'));
	}

	protected function decline(\TrustPayments\Sdk\Model\Transaction $transaction, array $order_info){
		\TrustPaymentsHelper::instance($this->registry)->addOrderHistory($order_info['order_id'], 'trustpayments_decline_status_id',
				\TrustPaymentsHelper::instance($this->registry)->getTranslation('message_webhook_decline'));
	}

	protected function failed(\TrustPayments\Sdk\Model\Transaction $transaction, array $order_info){
		\TrustPaymentsHelper::instance($this->registry)->addOrderHistory($order_info['order_id'], 'trustpayments_failed_status_id',
				\TrustPaymentsHelper::instance($this->registry)->getTranslation('message_webhook_failed'));
	}

	protected function fulfill(\TrustPayments\Sdk\Model\Transaction $transaction, array $order_info){
		\TrustPaymentsHelper::instance($this->registry)->addOrderHistory($order_info['order_id'], 'trustpayments_fulfill_status_id',
				\TrustPaymentsHelper::instance($this->registry)->getTranslation('message_webhook_fulfill'));
	}

	protected function voided(\TrustPayments\Sdk\Model\Transaction $transaction, array $order_info){
		\TrustPaymentsHelper::instance($this->registry)->addOrderHistory($order_info['order_id'], 'trustpayments_voided_status_id',
				\TrustPaymentsHelper::instance($this->registry)->getTranslation('message_webhook_voided'));
	}
}