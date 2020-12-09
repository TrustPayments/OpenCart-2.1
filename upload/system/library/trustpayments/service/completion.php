<?php

namespace TrustPayments\Service;

/**
 * This service provides functions to deal with TrustPayments completions.
 */
class Completion extends AbstractJob {

	public function create(\TrustPayments\Entity\TransactionInfo $transaction_info){
		try {
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionStart();
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionLock($transaction_info->getSpaceId(), $transaction_info->getTransactionId());
			
			$job = \TrustPayments\Entity\CompletionJob::loadNotSentForOrder($this->registry, $transaction_info->getOrderId());
			if (!$job->getId()) {
				$job = $this->createBase($transaction_info, $job);
				$job->save();
			}
			
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionCommit();
		}
		catch (\Exception $e) {
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionRollback();
			throw $e;
		}
		
		return $job;
	}

	public function send(\TrustPayments\Entity\CompletionJob $job){
		try {
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionStart();
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionLock($job->getSpaceId(), $job->getTransactionId());
			
			$service = new \TrustPayments\Sdk\Service\TransactionCompletionService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
			$operation = $service->completeOnline($job->getSpaceId(), $job->getTransactionId());
			
			if ($operation->getFailureReason() != null) {
				$job->setFailureReason($operation->getFailureReason()->getDescription());
			}
			
			$labels = array();
			foreach ($operation->getLabels() as $label) {
				$labels[$label->getDescriptor()->getId()] = $label->getContentAsString();
			}
			$job->setLabels($labels);
			
			$job->setJobId($operation->getId());
			$job->setState(\TrustPayments\Entity\AbstractJob::STATE_SENT);
			$job->save();
			
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionCommit();
			return $job;
		}
		catch (\TrustPayments\Sdk\ApiException $api_exception) {
			return $this->handleApiException($job, $api_exception);
		}
		catch (\Exception $e) {
			\TrustPaymentsHelper::instance($this->registry)->dbTransactionRollback();
			throw $e;
		}
	}
}