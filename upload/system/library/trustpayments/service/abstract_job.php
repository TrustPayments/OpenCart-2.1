<?php

namespace TrustPayments\Service;

/**
 * This service provides functions to deal with jobs, including locking and setting states.
 */
abstract class AbstractJob extends AbstractService {

	/**
	 * Set the state of the given job to failed with the message of the api exception.
	 * Expects a database transaction to be running, and will commit / rollback depending on outcome.
	 * 
	 * @param \TrustPayments\Entity\AbstractJob $job
	 * @param \TrustPayments\Sdk\ApiException $api_exception
	 * @throws \Exception
	 * @return \TrustPayments\Service\AbstractJob
	 */
	protected function handleApiException(\TrustPayments\Entity\AbstractJob $job, \TrustPayments\Sdk\ApiException $api_exception){
		try {
			$job->setState(\TrustPayments\Entity\AbstractJob::STATE_FAILED_CHECK);
			$job->setFailureReason([
				\TrustPaymentsHelper::FALLBACK_LANGUAGE => $api_exception->getMessage() 
			]);
			$job->save();
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionCommit();
			return $job;
		}
		catch (\Exception $e) {
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionRollback();
			throw new \Exception($e->getMessage() . ' | ' . $api_exception->getMessage(), $e->getCode(), $api_exception);
		}
	}

	protected function createBase(\TrustPayments\Entity\TransactionInfo $transaction_info, \TrustPayments\Entity\AbstractJob $job){
		$job->setTransactionId($transaction_info->getTransactionId());
		$job->setOrderId($transaction_info->getOrderId());
		$job->setSpaceId($transaction_info->getSpaceId());
		$job->setState(\TrustPayments\Entity\AbstractJob::STATE_CREATED);
		
		return $job;
	}
}