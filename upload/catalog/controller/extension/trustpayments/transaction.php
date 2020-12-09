<?php
require_once modification(DIR_SYSTEM . 'library/trustpayments/helper.php');
use TrustPayments\Controller\AbstractController;

class ControllerExtensionTrustPaymentsTransaction extends AbstractController {

	public function fail(){
		if (isset($this->request->get['order_id']) &&
				 \TrustPayments\Service\Transaction::instance($this->registry)->waitForStates($this->request->get['order_id'],
						array(
							\TrustPayments\Sdk\Model\TransactionState::FAILED 
						), 5)) {
			$transaction_info = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $this->request->get['order_id']);
			unset($this->registry->get('session')->data['order_id']);
			$this->session->data['error'] = $transaction_info->getFailureReason();
		}
		else {
			$this->session->data['error'] = $this->language->get('error'); //TODO error text
		}
		$this->response->redirect($this->createUrl('checkout/checkout', ''));
	}

	protected function getRequiredPermission(){
		return '';
	}
}