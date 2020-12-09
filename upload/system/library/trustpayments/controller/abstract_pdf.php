<?php

namespace TrustPayments\Controller;

abstract class AbstractPdf extends AbstractController {

	protected function downloadPackingSlip($order_id){
		$transaction_info = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $order_id);
		if ($transaction_info->getId() != null && $transaction_info->getState() == \TrustPayments\Sdk\Model\TransactionState::FULFILL) {
			$service = new \TrustPayments\Sdk\Service\TransactionService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
			$document = $service->getPackingSlip($transaction_info->getSpaceId(), $transaction_info->getTransactionId());
			$this->download($document);
		}
	}

	protected function downloadInvoice($order_id){
		$transaction_info = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $order_id);
		if ($transaction_info->getId() != null && in_array($transaction_info->getState(),
				array(
					\TrustPayments\Sdk\Model\TransactionState::COMPLETED,
					\TrustPayments\Sdk\Model\TransactionState::FULFILL,
					\TrustPayments\Sdk\Model\TransactionState::DECLINE 
				))) {
					$service = new \TrustPayments\Sdk\Service\TransactionService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
			$document = $service->getInvoiceDocument($transaction_info->getSpaceId(), $transaction_info->getTransactionId());
			$this->download($document);
		}
	}

	/**
	 * Sends the data received by calling the given path to the browser and ends the execution of the script
	 *
	 * @param string $path
	 */
	private function download(\TrustPayments\Sdk\Model\RenderedDocument $document){
		header('Pragma: public');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Content-type: application/pdf');
		header('Content-Disposition: attachment; filename="' . $document->getTitle() . '.pdf"');
		header('Content-Description: ' . $document->getTitle());
		echo base64_decode($document->getData());
		exit();
	}
}