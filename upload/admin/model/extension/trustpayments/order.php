<?php
require_once modification(DIR_SYSTEM . 'library/trustpayments/helper.php');
use TrustPayments\Model\AbstractModel;

/**
 * Handles the button on the order info page.
 */
class ModelExtensionTrustPaymentsOrder extends AbstractModel {

	/**
	 * Returns all jobs with status FAILED_CHECK, and moves these into state FAILED_DONE.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function getFailedJobs($order_id){
		$this->language->load('payment/trustpayments');
		$jobs = array_merge($this->getJobMessages(\TrustPayments\Entity\VoidJob::loadFailedCheckedForOrder($this->registry, $order_id)),
				$this->getJobMessages(\TrustPayments\Entity\CompletionJob::loadFailedCheckedForOrder($this->registry, $order_id)),
				$this->getJobMessages(\TrustPayments\Entity\RefundJob::loadFailedCheckedForOrder($this->registry, $order_id)));
		\TrustPayments\Entity\VoidJob::markFailedAsDone($this->registry, $order_id);
		\TrustPayments\Entity\CompletionJob::markFailedAsDone($this->registry, $order_id);
		\TrustPayments\Entity\RefundJob::markFailedAsDone($this->registry, $order_id);
		return $jobs;
	}

	public function getButtons($order_id){
		$this->language->load('payment/trustpayments');
		if (!isset($this->request->get['order_id'])) {
			return array();
		}
		$transaction_info = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $order_id);
		if ($transaction_info->getId() == null) {
			return array();
		}
		
		$buttons = array();
		
		if (\TrustPaymentsHelper::instance($this->registry)->isCompletionPossible($transaction_info)) {
			$buttons[] = $this->getCompletionButton();
			$buttons[] = $this->getVoidButton();
		}
		
		if (\TrustPaymentsHelper::instance($this->registry)->isRefundPossible($transaction_info)) {
			$buttons[] = $this->getRefundButton();
		}
		
		if (\TrustPaymentsHelper::instance($this->registry)->hasRunningJobs($transaction_info)) {
			$buttons[] = $this->getUpdateButton();
		}
		
		return $buttons;
	}

	/**
	 *
	 * @param \TrustPayments\Entity\AbstractJob[] $jobs
	 */
	private function getJobMessages($jobs){
		$job_messages = array();
		foreach ($jobs as $job) {
			$format = $this->language->get('trustpayments_failed_job_message');
			
			if ($job instanceof \TrustPayments\Entity\CompletionJob) {
				$type = $this->language->get('completion_job');
			}
			else if ($job instanceof \TrustPayments\Entity\RefundJob) {
				$type = $this->language->get('refund_job');
			}
			else if ($job instanceof \TrustPayments\Entity\VoidJob) {
				$type = $this->language->get('void_job');
			}
			else {
				$type = get_class($job);
			}
			
			$format = '%s %s: %s';
			$job_messages[] = sprintf($format, $type, $job->getJobId(), $job->getFailureReason());
		}
		return $job_messages;
	}

	private function getVoidButton(){
		return array(
			'text' => $this->language->get('button_void'),
			'icon' => 'ban',
			'route' => 'extension/trustpayments/void' 
		);
	}

	private function getCompletionButton(){
		return array(
			'text' => $this->language->get('button_complete'),
			'icon' => 'check',
			'route' => 'extension/trustpayments/completion' 
		);
	}

	private function getRefundButton(){
		return array(
			'text' => $this->language->get('button_refund'),
			'icon' => 'reply',
			'route' => 'extension/trustpayments/refund/page' 
		);
	}

	private function getUpdateButton(){
		return array(
			'text' => $this->language->get('button_update'),
			'icon' => 'refresh',
			'route' => 'extension/trustpayments/update' 
		);
	}
}