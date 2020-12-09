<?php

namespace TrustPayments\Provider;

/**
 * Provider of label descriptor group information from the gateway.
 */
class LabelDescriptionGroup extends AbstractProvider {

	protected function __construct(\Registry $registry){
		parent::__construct($registry, 'oc_trustpayments_label_descriptor_group');
	}

	/**
	 * Returns the label descriptor group by the given code.
	 *
	 * @param int $id
	 * @return \TrustPayments\Sdk\Model\LabelDescriptorGroup
	 */
	public function find($id){
		return parent::find($id);
	}

	/**
	 * Returns a list of label descriptor groups.
	 *
	 * @return \TrustPayments\Sdk\Model\LabelDescriptorGroup[]
	 */
	public function getAll(){
		return parent::getAll();
	}

	protected function fetchData(){
		$label_descriptor_group_service = new \TrustPayments\Sdk\Service\LabelDescriptionGroupService(
				\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		return $label_descriptor_group_service->all();
	}

	protected function getId($entry){
		/* @var \TrustPayments\Sdk\Model\LabelDescriptorGroup $entry */
		return $entry->getId();
	}
}