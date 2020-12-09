<?php

namespace TrustPayments\Service;

class MethodConfiguration extends AbstractService {

	/**
	 * Updates the data of the payment method configuration.
	 *
	 * @param \TrustPayments\Sdk\Model\PaymentMethodConfiguration $configuration
	 */
	public function updateData(\TrustPayments\Sdk\Model\PaymentMethodConfiguration $configuration){
		/* @var \TrustPayments\Entity\MethodConfiguration $entity */
		$entity = \TrustPayments\Entity\MethodConfiguration::loadByConfiguration($this->registry, $configuration->getLinkedSpaceId(), $configuration->getId());
		if ($entity->getId() !== null && $this->hasChanged($configuration, $entity)) {
			$entity->setConfigurationName($configuration->getName());
			$entity->setTitle($configuration->getResolvedTitle());
			$entity->setDescription($configuration->getResolvedDescription());
			$entity->setImage($configuration->getResolvedImageUrl());
			$entity->setSortOrder($configuration->getSortOrder());
			$entity->save();
		}
	}

	private function hasChanged(\TrustPayments\Sdk\Model\PaymentMethodConfiguration $configuration, \TrustPayments\Entity\MethodConfiguration $entity){
		if ($configuration->getName() != $entity->getConfigurationName()) {
			return true;
		}
		
		if ($configuration->getResolvedTitle() != $entity->getTitle()) {
			return true;
		}
		
		if ($configuration->getResolvedDescription() != $entity->getDescription()) {
			return true;
		}
		
		if ($configuration->getResolvedImageUrl() != $entity->getImage()) {
			return true;
		}
		
		if ($configuration->getSortOrder() != $entity->getSortOrder()) {
			return true;
		}
		
		return false;
	}

	/**
	 * Synchronizes the payment method configurations from TrustPayments.
	 */
	public function synchronize($space_id){
		$existing_found = array();
		$existing_configurations = \TrustPayments\Entity\MethodConfiguration::loadBySpaceId($this->registry, $space_id);
		
		$payment_method_configuration_service = new \TrustPayments\Sdk\Service\PaymentMethodConfigurationService(
				\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		$configurations = $payment_method_configuration_service->search($space_id, new \TrustPayments\Sdk\Model\EntityQuery());
		
		foreach ($configurations as $configuration) {
			$method = \TrustPayments\Entity\MethodConfiguration::loadByConfiguration($this->registry, $space_id, $configuration->getId());
			if ($method->getId() !== null) {
				$existing_found[] = $method->getId();
			}
			
			$method->setSpaceId($space_id);
			$method->setConfigurationId($configuration->getId());
			$method->setConfigurationName($configuration->getName());
			$method->setState($this->getConfigurationState($configuration));
			$method->setTitle($configuration->getResolvedTitle());
			$method->setDescription($configuration->getResolvedDescription());
			$method->setImage($configuration->getResolvedImageUrl());
			$method->setSortOrder($configuration->getSortOrder());
			$method->save();
		}
		
		foreach ($existing_configurations as $existing_configuration) {
			if (!in_array($existing_configuration->getId(), $existing_found)) {
				$existing_configuration->setState(\TrustPayments\Entity\MethodConfiguration::STATE_HIDDEN);
				$existing_configuration->save();
			}
		}
		
		\TrustPayments\Provider\PaymentMethod::instance($this->registry)->clearCache();
	}

	/**
	 * Returns the payment method for the given id.
	 *
	 * @param int $id
	 * @return \TrustPayments\Sdk\Model\PaymentMethod
	 */
	protected function getPaymentMethod($id){
		return \TrustPayments\Provider\PaymentMethod::instance($this->registry)->find($id);
	}

	/**
	 * Returns the state for the payment method configuration.
	 *
	 * @param \TrustPayments\Sdk\Model\PaymentMethodConfiguration $configuration
	 * @return string
	 */
	protected function getConfigurationState(\TrustPayments\Sdk\Model\PaymentMethodConfiguration $configuration){
		switch ($configuration->getState()) {
			case \TrustPayments\Sdk\Model\CreationEntityState::ACTIVE:
				return \TrustPayments\Entity\MethodConfiguration::STATE_ACTIVE;
			case \TrustPayments\Sdk\Model\CreationEntityState::INACTIVE:
				return \TrustPayments\Entity\MethodConfiguration::STATE_INACTIVE;
			default:
				return \TrustPayments\Entity\MethodConfiguration::STATE_HIDDEN;
		}
	}
}