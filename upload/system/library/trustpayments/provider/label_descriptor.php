<?php

namespace TrustPayments\Provider;

/**
 * Provider of label descriptor information from the gateway.
 */
class LabelDescriptor extends AbstractProvider {

	protected function __construct(\Registry $registry){
		parent::__construct($registry, 'oc_trustpayments_label_descriptor');
	}

	/**
	 * Returns the label descriptor by the given code.
	 *
	 * @param int $id
	 * @return \TrustPayments\Sdk\Model\LabelDescriptor
	 */
	public function find($id){
		return parent::find($id);
	}

	/**
	 * Returns a list of label descriptors.
	 *
	 * @return \TrustPayments\Sdk\Model\LabelDescriptor[]
	 */
	public function getAll(){
		return parent::getAll();
	}

	protected function fetchData(){
		$label_descriptor_service = new \TrustPayments\Sdk\Service\LabelDescriptionService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		return $label_descriptor_service->all();
	}

	protected function getId($entry){
		/* @var \TrustPayments\Sdk\Model\LabelDescriptor $entry */
		return $entry->getId();
	}
}