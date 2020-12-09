<?php

namespace TrustPayments\Provider;

/**
 * Provider of currency information from the gateway.
 */
class Currency extends AbstractProvider {

	protected function __construct(\Registry $registry){
		parent::__construct($registry, 'oc_trustpayments_currencies');
	}

	/**
	 * Returns the currency by the given code.
	 *
	 * @param string $code
	 * @return \TrustPayments\Sdk\Model\RestCurrency
	 */
	public function find($code){
		return parent::find($code);
	}

	/**
	 * Returns a list of currencies.
	 *
	 * @return \TrustPayments\Sdk\Model\RestCurrency[]
	 */
	public function getAll(){
		return parent::getAll();
	}

	protected function fetchData(){
		$currency_service = new \TrustPayments\Sdk\Service\CurrencyService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		return $currency_service->all();
	}

	protected function getId($entry){
		/* @var \TrustPayments\Sdk\Model\RestCurrency $entry */
		return $entry->getCurrencyCode();
	}
}