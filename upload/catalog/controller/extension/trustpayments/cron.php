<?php
require_once modification(DIR_SYSTEM . 'library/trustpayments/helper.php');

class ControllerExtensionTrustPaymentsCron extends Controller {

	public function index(){
		$this->endRequestPrematurely();
		
		if (isset($this->request->get['security_token'])) {
			$security_token = $this->request->get['security_token'];
		}
		else {
			\TrustPaymentsHelper::instance($this->registry)->log('Cron called without security token.', \TrustPaymentsHelper::LOG_ERROR);
			die();
		}
		
		\TrustPayments\Entity\Cron::cleanUpCronDB($this->registry);
		
		try {
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionStart();
			$result = \TrustPayments\Entity\Cron::setProcessing($this->registry, $security_token);
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionCommit();
			if (!$result) {
				die();
			}
		}
		catch (Exception $e) {
			// 1062 is mysql duplicate constraint error. This is expected and doesn't need to be logged.
			if (strpos('1062', $e->getMessage()) === false && strpos('constraint_key', $e->getMessage()) === false) {
				\TrustPaymentsHelper::instance($this->registry)->log('Updating cron failed: ' . $e->getMessage(), \TrustPaymentsHelper::LOG_ERROR);
			}
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionRollback();
			die();
		}
		
		$errors = $this->runTasks();
		
		try {
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionStart();
			$result = \TrustPayments\Entity\Cron::setComplete($this->registry, $security_token, implode('. ', $errors));
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionCommit();
			if (!$result) {
				\TrustPaymentsHelper::instance($this->registry)->log('Could not update finished cron job.', \TrustPaymentsHelper::LOG_ERROR);
				die();
			}
		}
		catch (Exception $e) {
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionRollback();
			\TrustPaymentsHelper::instance($this->registry)->log('Could not update finished cron job: ' . $e->getMessage(), \TrustPaymentsHelper::LOG_ERROR);
			die();
		}
		die();
	}

	private function runTasks(){
		$errors = array();
		foreach (\TrustPayments\Entity\AbstractJob::loadNotSent($this->registry) as $job) {
			try {
				switch (get_class($job)) {
					case \TrustPayments\Entity\CompletionJob::class:
						$transaction_info = \TrustPayments\Entity\TransactionInfo::loadByTransaction($this->registry, $job->getSpaceId(),
								$job->getTransactionId());
						\TrustPayments\Service\Transaction::instance($this->registry)->updateLineItemsFromOrder($transaction_info->getOrderId());
						\TrustPayments\Service\Completion::instance($this->registry)->send($job);
						break;
					case \TrustPayments\Entity\RefundJob::class:
						\TrustPayments\Service\Refund::instance($this->registry)->send($job);
						break;
					case \TrustPayments\Entity\VoidJob::class:
						\TrustPayments\Service\VoidJob::instance($this->registry)->send($job);
						break;
					default:
						break;
				}
			}
			catch (Exception $e) {
				\TrustPaymentsHelper::instance($this->registry)->log('Could not update job: ' . $e->getMessage(), \TrustPaymentsHelper::LOG_ERROR);
				$errors[] = $e->getMessage();
			}
		}
		return $errors;
	}

	private function endRequestPrematurely(){
		if(ob_get_length()){
			ob_end_clean();
		}
		// Return request but keep executing
		set_time_limit(0);
		ignore_user_abort(true);
		ob_start();
		if (session_id()) {
			session_write_close();
		}
		header("Content-Encoding: none");
		header("Connection: close");
		header('Content-Type: text/javascript');
		ob_end_flush();
		flush();
		if (is_callable('fastcgi_finish_request')) {
			fastcgi_finish_request();
		}
	}
}