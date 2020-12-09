<?php
require_once modification(DIR_SYSTEM . 'library/trustpayments/helper.php');

class ControllerExtensionTrustPaymentsCompletion extends \TrustPayments\Controller\AbstractController {

	public function index(){
		$this->response->addHeader('Content-Type: application/json');
		try {
			$this->validate();
			
			$completion_job = \TrustPayments\Entity\CompletionJob::loadRunningForOrder($this->registry, $this->request->get['order_id']);
			
			if ($completion_job->getId() !== null) {
				throw new Exception($this->language->get('error_already_running'));
			}
			
			$transaction_info = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $this->request->get['order_id']);
			
			if (!\TrustPaymentsHelper::instance($this->registry)->isCompletionPossible($transaction_info)) {
				throw new \Exception($this->language->get('error_cannot_create_job'));
			}
			
			// ensure line items are current (e.g. events were skipped when order is edited)
			\TrustPayments\Service\Transaction::instance($this->registry)->updateLineItemsFromOrder($this->request->get['order_id']);
			
			$job = \TrustPayments\Service\Completion::instance($this->registry)->create($transaction_info);
			\TrustPayments\Service\Completion::instance($this->registry)->send($job);
			
			$this->load->model('extension/trustpayments/order');
			$new_buttons = $this->model_extension_trustpayments_order->getButtons($this->request->get['order_id']);
			
			$this->response->setOutput(
					json_encode(
							array(
								'success' => sprintf($this->language->get('message_completion_success'), $transaction_info->getTransactionId()),
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
		return 'extension/trustpayments/completion';
	}
}