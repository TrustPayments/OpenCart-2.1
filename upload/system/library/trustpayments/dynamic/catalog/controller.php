<?php
require_once (DIR_SYSTEM . "library/trustpayments/helper.php");
use \TrustPayments\Controller\AbstractController;

abstract class ControllerExtensionPaymentTrustPaymentsBase extends AbstractController {

	public function index(){
		if (!$this->config->get('trustpayments_status')) {
			return '';
		}
		$this->load->language('payment/trustpayments');
		$data = array();
		
		$data['configuration_id'] = \TrustPaymentsHelper::extractPaymentMethodId($this->getCode());
		
		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['text_loading'] = $this->language->get('text_loading');
		
		$this->load->model('payment/' . $this->getCode());
		$data['text_payment_title'] = $this->{"model_payment_{$this->getCode()}"}->getTitle();
		$data['text_further_details'] = $this->language->get('text_further_details');
		
		$data['opencart_js'] = 'catalog/view/javascript/trustpayments.js';
		$data['external_js'] = TrustPayments\Service\Transaction::instance($this->registry)->getJavascriptUrl();
		
		return $this->loadView('payment/trustpayments/iframe', $data);
	}

	public function confirm(){
		if (!$this->config->get('trustpayments_status')) {
			return '';
		}
		$result = array(
			'status' => false 
		);
		try {
			$transaction = $this->confirmTransaction();
			$result['status'] = true;
			$result['redirect'] = TrustPayments\Service\Transaction::instance($this->registry)->getPaymentPageUrl($transaction, $this->getCode());
		}
		catch (Exception $e) {
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionRollback();
			\TrustPaymentsHelper::instance($this->registry)->log($e->getMessage(), \TrustPaymentsHelper::LOG_ERROR);
			$this->load->language('payment/trustpayments');
			$result['message'] = $this->language->get('error_confirmation'); 
			unset($this->session->data['order_id']); // this order number cannot be used anymore
			TrustPayments\Service\Transaction::instance($this->registry)->clearTransactionInSession();
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($result));
	}

	private function confirmTransaction(){
		$transaction = TrustPayments\Service\Transaction::instance($this->registry)->getTransaction($this->getOrderInfo(), false,
				array(
					\TrustPayments\Sdk\Model\TransactionState::PENDING 
				));
		if ($transaction->getState() == \TrustPayments\Sdk\Model\TransactionState::PENDING) {
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionStart();
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionLock($transaction->getLinkedSpaceId(), $transaction->getId());
			TrustPayments\Service\Transaction::instance($this->registry)->update($this->session->data, true);
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionCommit();
			return $transaction;
		}
		
		throw new Exception('Transaction is not pending.');
	}
	
	private function getOrderInfo() {
		if(!isset($this->session->data['order_id'])) {
			throw new Exception("No order_id to confirm.");
		}
		$this->load->model('checkout/order');
		return $this->model_checkout_order->getOrder($this->session->data['order_id']);
	}

	protected function getRequiredPermission(){
		return '';
	}

	protected abstract function getCode();
}