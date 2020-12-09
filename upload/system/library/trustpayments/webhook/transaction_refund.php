<?php

namespace TrustPayments\Webhook;

/**
 * Webhook processor to handle refund state transitions.
 */
class TransactionRefund extends AbstractOrderRelated {

	/**
	 *
	 * @see AbstractOrderRelated::load_entity()
	 * @return \TrustPayments\Sdk\Model\Refund
	 */
	protected function loadEntity(Request $request){
		$refund_service = new \TrustPayments\Sdk\Service\RefundService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		return $refund_service->read($request->getSpaceId(), $request->getEntityId());
	}

	protected function getOrderId($refund){
		/* @var \TrustPayments\Sdk\Model\Refund $refund */
		return $refund->getTransaction()->getMerchantReference();
	}
	
	protected function getTransactionId($entity){
		/* @var $entity \TrustPayments\Sdk\Model\Refund */
		return $entity->getTransaction()->getId();
	}

	protected function processOrderRelatedInner(array $order_info, $refund){
		/* @var \TrustPayments\Sdk\Model\Refund $refund */
		switch ($refund->getState()) {
			case \TrustPayments\Sdk\Model\RefundState::FAILED:
				$this->failed($refund, $order_info);
				break;
			case \TrustPayments\Sdk\Model\RefundState::SUCCESSFUL:
				$this->refunded($refund, $order_info);
			default:
				// Nothing to do.
				break;
		}
	}

	protected function failed(\TrustPayments\Sdk\Model\Refund $refund, array $order_info){
		$refund_job = \TrustPayments\Entity\RefundJob::loadByExternalId($this->registry, $refund->getLinkedSpaceId(), $refund->getExternalId());
		
		if ($refund_job->getId()) {
			if ($refund->getFailureReason() != null) {
				$refund_job->setFailureReason($refund->getFailureReason()->getDescription());
			}
			
			$refund_job->setState(\TrustPayments\Entity\RefundJob::STATE_FAILED_CHECK);
			\TrustPayments\Entity\Alert::loadFailedJobs($this->registry)->modifyCount(1);
			
			$refund_job->save();
		}
	}

	protected function refunded(\TrustPayments\Sdk\Model\Refund $refund, array $order_info){
		$refund_job = \TrustPayments\Entity\RefundJob::loadByExternalId($this->registry, $refund->getLinkedSpaceId(), $refund->getExternalId());
		if ($refund_job->getId()) {
			$refund_job->setState(\TrustPayments\Entity\RefundJob::STATE_SUCCESS);
			$already_refunded = \TrustPayments\Entity\RefundJob::sumRefundedAmount($this->registry, $order_info['order_id']);
			
			if (\TrustPaymentsHelper::instance($this->registry)->areAmountsEqual($already_refunded + $refund->getAmount(), $order_info['total'],
					$order_info['currency_code'])) {
				$status = 'trustpayments_refund_status_id';
			}
			else {
				$status = $order_info['order_status_id'];
			}
			
			\TrustPaymentsHelper::instance($this->registry)->addOrderHistory($order_info['order_id'], $status,
					sprintf(\TrustPaymentsHelper::instance($this->registry)->getTranslation('message_refund_successful'), $refund->getId(),
							$refund->getAmount()), true);
			
			if ($refund_job->getRestock()) {
				$this->restock($refund);
			}
			
			$refund_job->save();
		}
	}

	protected function restock(\TrustPayments\Sdk\Model\Refund $refund){
		$db = $this->registry->get('db');
		$table = DB_PREFIX . 'product';
		foreach ($refund->getLineItems() as $line_item) {
			if ($line_item->getType() == \TrustPayments\Sdk\Model\LineItemType::PRODUCT) {
				$quantity = $db->escape($line_item->getQuantity());
				$id = $db->escape($line_item->getUniqueId());
				$query = "UPDATE $table SET quantity=quantity+$quantity WHERE product_id='$id';";
				$db->query($query);
			}
		}
	}
}