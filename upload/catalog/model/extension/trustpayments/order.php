<?php
require_once modification(DIR_SYSTEM . 'library/trustpayments/helper.php');
use TrustPayments\Model\AbstractModel;

/**
 * Handles the customer order info.
 */
class ModelExtensionTrustPaymentsOrder extends AbstractModel {

	public function getButtons($order_id){
		if (!\TrustPaymentsHelper::instance($this->registry)->isValidOrder($order_id)) {
			return array();
		}
		
		$this->language->load('payment/trustpayments');
		$transaction_info = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $order_id);
		
		$buttons = array();
		
		if ($this->config->get('trustpayments_download_packaging') && $transaction_info->getState() == \TrustPayments\Sdk\Model\TransactionState::FULFILL) {
			$buttons[] = $this->getPackagingButton();
		}
		
		if ($this->config->get('trustpayments_download_invoice') && in_array($transaction_info->getState(),
				array(
					\TrustPayments\Sdk\Model\TransactionState::FULFILL,
					\TrustPayments\Sdk\Model\TransactionState::COMPLETED,
					\TrustPayments\Sdk\Model\TransactionState::DECLINE 
				))) {
			$buttons[] = $this->getInvoiceButton();
		}
		
		return $buttons;
	}

	private function getInvoiceButton(){
		return array(
			'text' => $this->language->get('button_invoice'),
			'icon' => 'download',
			'url' => $this->createUrl('extension/trustpayments/pdf/invoice', array(
				'order_id' => $this->request->get['order_id'] 
			)) 
		);
	}

	private function getPackagingButton(){
		return array(
			'text' => $this->language->get('button_packing_slip'),
			'icon' => 'download',
			'url' => $this->createUrl('extension/trustpayments/pdf/packingSlip', array(
				'order_id' => $this->request->get['order_id'] 
			)) 
		);
	}
}