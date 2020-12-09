<?php
require_once modification(DIR_SYSTEM . 'library/trustpayments/helper.php');
use TrustPayments\Model\AbstractModel;
use TrustPayments\Entity\TransactionInfo;
use TrustPayments\Provider\PaymentMethod;

class ModelExtensionTrustPaymentsTransaction extends AbstractModel {
	const DATE_FORMAT = 'Y-m-d H:i:s';

	public function loadList(array $filters){
		$transactionInfoList = TransactionInfo::loadByFilters($this->registry, $filters);
		/* @var $transactionInfoList TransactionInfo[] */
		$transactions = array();
		foreach ($transactionInfoList as $transactionInfo) {
			$paymentMethod = PaymentMethod::instance($this->registry)->find($transactionInfo->getPaymentMethodId());
			if ($paymentMethod) {
				$paymentMethodName = TrustPaymentsHelper::instance($this->registry)->translate($paymentMethod->getName()) . " (" . $transactionInfo->getPaymentMethodId() . ")";
			}
			else {
				$paymentMethodName = $transactionInfo->getPaymentMethodId();
			}
			$transactions[] = array(
				'id' => $transactionInfo->getId(),
				'order_id' => $transactionInfo->getOrderId(),
				'transaction_id' => $transactionInfo->getTransactionId(),
				'space_id' => $transactionInfo->getSpaceId(),
				'space_view_id' => $transactionInfo->getSpaceViewId(),
				'state' => $transactionInfo->getState(),
				'authorization_amount' => $transactionInfo->getAuthorizationAmount(),
				'created_at' => $transactionInfo->getCreatedAt()->format(self::DATE_FORMAT),
				'updated_at' => $transactionInfo->getUpdatedAt()->format(self::DATE_FORMAT),
				'payment_method' => $paymentMethodName,
				'view' => TrustPaymentsVersionHelper::createUrl($this->url, 'sale/order/info',
						array(
							'token' => $this->session->data['token'],
							'order_id' => $transactionInfo->getOrderId() 
						), true) 
			);
		}
		return $transactions;
	}
	
	public function getOrderStatuses() {
		return array(
			'',
			TrustPayments\Sdk\Model\TransactionState::AUTHORIZED,
			TrustPayments\Sdk\Model\TransactionState::COMPLETED,
			TrustPayments\Sdk\Model\TransactionState::CONFIRMED,
			TrustPayments\Sdk\Model\TransactionState::CREATE,
			TrustPayments\Sdk\Model\TransactionState::DECLINE,
			TrustPayments\Sdk\Model\TransactionState::FULFILL,
			TrustPayments\Sdk\Model\TransactionState::FAILED,
			TrustPayments\Sdk\Model\TransactionState::PENDING,
			TrustPayments\Sdk\Model\TransactionState::PROCESSING,
			TrustPayments\Sdk\Model\TransactionState::AUTHORIZED,
			TrustPayments\Sdk\Model\TransactionState::VOIDED,
		);
	}
	
	public function countRows() {
		return TransactionInfo::countRows($this->registry);
	}
}