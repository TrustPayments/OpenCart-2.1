<?php

namespace TrustPayments\Provider;

/**
 * Provider of payment connector information from the gateway.
 */
class PaymentConnector extends AbstractProvider {

	protected function __construct(\Registry $registry){
		parent::__construct($registry, 'oc_trustpayments_payment_connectors');
	}

	/**
	 * Returns the payment connector by the given id.
	 *
	 * @param int $id
	 * @return \TrustPayments\Sdk\Model\PaymentConnector
	 */
	public function find($id){
		return parent::find($id);
	}

	/**
	 * Returns a list of payment connectors.
	 *
	 * @return \TrustPayments\Sdk\Model\PaymentConnector[]
	 */
	public function getAll(){
		return parent::getAll();
	}

	protected function fetchData(){
		$connector_service = new \TrustPayments\Sdk\Service\PaymentConnectorService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		return $connector_service->all();
	}

	protected function getId($entry){
		/* @var \TrustPayments\Sdk\Model\PaymentConnector $entry */
		return $entry->getId();
	}
}