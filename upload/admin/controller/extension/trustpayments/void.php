<?php
require_once modification(DIR_SYSTEM . 'library/trustpayments/helper.php');

class ControllerExtensionTrustPaymentsVoid extends \TrustPayments\Controller\AbstractController {

	public function index(){
		$this->response->addHeader('Content-Type: application/json');
		try {
			$this->validate();
			
			$transaction_info = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $this->request->get['order_id']);
			
			$running = \TrustPayments\Entity\VoidJob::loadRunningForOrder($this->registry, $transaction_info->getOrderId());
			if ($running->getId()) {
				throw new \Exception($this->language->get('error_already_running'));
			}
			
			if (!\TrustPaymentsHelper::instance($this->registry)->isCompletionPossible($transaction_info)) {
				throw new \Exception($this->language->get('error_cannot_create_job'));
			}
			
			$job = \TrustPayments\Service\VoidJob::instance($this->registry)->create($transaction_info);
			\TrustPayments\Service\VoidJob::instance($this->registry)->send($job);
			
			$this->load->model('extension/trustpayments/order');
			$new_buttons = $this->model_extension_trustpayments_order->getButtons($this->request->get['order_id']);
			
			$this->response->setOutput(
					json_encode(
							array(
								'success' => sprintf($this->language->get('message_void_success'), $transaction_info->getTransactionId()),
								'buttons' => $new_buttons 
							)));
		}
		catch (Exception $e) {
			$this->response->setOutput(json_encode(array(
				'error' => $e->getMessage() 
			)));
		}
	}

	protected function getRequiredPermission(){
		return 'extension/trustpayments/void';
	}
}