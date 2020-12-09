<?php

namespace TrustPayments\Webhook;

/**
 * Webhook processor to handle transaction completion state transitions.
 */
class TransactionCompletion extends AbstractOrderRelated {

	/**
	 *
	 * @see AbstractOrderRelated::loadEntity()
	 * @return \TrustPayments\Sdk\Model\TransactionCompletion
	 */
	protected function loadEntity(Request $request){
		$completion_service = new \TrustPayments\Sdk\Service\TransactionCompletionService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		return $completion_service->read($request->getSpaceId(), $request->getEntityId());
	}

	protected function getOrderId($completion){
		/* @var \TrustPayments\Sdk\Model\TransactionCompletion $completion */
		return $completion->getLineItemVersion()->getTransaction()->getMerchantReference();
	}
	
	protected function getTransactionId($entity){
		/* @var $entity \TrustPayments\Sdk\Model\TransactionCompletion */
		return $entity->getLinkedTransaction();
	}
	
	protected function processOrderRelatedInner(array $order_info, $completion){
		/* @var \TrustPayments\Sdk\Model\TransactionCompletion $completion */
		switch ($completion->getState()) {
			case \TrustPayments\Sdk\Model\TransactionCompletionState::FAILED:
				$this->failed($completion, $order_info);
				break;
			case \TrustPayments\Sdk\Model\TransactionCompletionState::SUCCESSFUL:
				$this->success($completion, $order_info);
				break;
			default:
				// Ignore PENDING & CREATE
				// Nothing to do.
				break;
		}
	}

	protected function success(\TrustPayments\Sdk\Model\TransactionCompletion $completion, array $order_info){
		$completion_job = \TrustPayments\Entity\CompletionJob::loadByJob($this->registry, $completion->getLinkedSpaceId(), $completion->getId());
		if (!$completion_job->getId()) {
			//We have no completion job with this id -> the server could not store the id of the completion after sending the request. (e.g. connection issue or crash)
			//We only have on running completion which was not yet processed successfully and use it as it should be the one the webhook is for.
			$completion_job = \TrustPayments\Entity\CompletionJob::loadRunningForOrder($this->registry, $order_info['order_id']);
			if (!$completion_job->getId()) {
				//completion not initated in shop backend ignore
				return;
			}
			$completion_job->setJobId($completion->getId());
		}
		$completion_job->setAmount($completion->getPaymentInformation());
		$completion_job->setState(\TrustPayments\Entity\CompletionJob::STATE_SUCCESS);
		
		$completion_job->save();
	}

	protected function failed(\TrustPayments\Sdk\Model\TransactionCompletion $completion, array $order_info){
		$completion_job = \TrustPayments\Entity\CompletionJob::loadByJob($this->registry, $completion->getLinkedSpaceId(), $completion->getId());
		if (!$completion_job->getId()) {
			//We have no completion job with this id -> the server could not store the id of the completion after sending the request. (e.g. connection issue or crash)
			//We only have on running completion which was not yet processed successfully and use it as it should be the one the webhook is for.
			$completion_job = \TrustPayments\Entity\CompletionJob::loadRunningForOrder($this->registry, $order_info['order_id']);
			if (!$completion_job->getId()) {
				return;
			}
			$completion_job->setJobId($completion->getId());
		}
		if ($completion->getFailureReason() != null) {
			$completion_job->setFailureReason($completion->getFailureReason()->getDescription());
		}
		
		$completion_job->setAmount($completion->getPaymentInformation());
		$completion_job->setState(\TrustPayments\Entity\CompletionJob::STATE_FAILED_CHECK);
		\TrustPayments\Entity\Alert::loadFailedJobs($this->registry)->modifyCount(1);
		
		$completion_job->save();
	}
}