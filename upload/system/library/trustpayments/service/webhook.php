<?php

namespace TrustPayments\Service;

use TrustPayments\Webhook\Entity;

/**
 * This service handles webhooks.
 */
class Webhook extends AbstractService {
	
	/**
	 * The webhook listener API service.
	 *
	 * @var \TrustPayments\Sdk\Service\WebhookListenerService
	 */
	private $webhook_listener_service;
	
	/**
	 * The webhook url API service.
	 *
	 * @var \TrustPayments\Sdk\Service\WebhookUrlService
	 */
	private $webhook_url_service;
	private $webhook_entities = array();

	/**
	 * Constructor to register the webhook entites.
	 */
	protected function __construct(\Registry $registry){
		parent::__construct($registry);
		$this->webhook_entities[1487165678181] = new Entity(1487165678181, 'Manual Task',
				array(
					\TrustPayments\Sdk\Model\ManualTaskState::DONE,
					\TrustPayments\Sdk\Model\ManualTaskState::EXPIRED,
					\TrustPayments\Sdk\Model\ManualTaskState::OPEN 
				), 'TrustPayments\Webhook\ManualTask');
		$this->webhook_entities[1472041857405] = new Entity(1472041857405, 'Payment Method Configuration',
				array(
					\TrustPayments\Sdk\Model\CreationEntityState::ACTIVE,
					\TrustPayments\Sdk\Model\CreationEntityState::DELETED,
					\TrustPayments\Sdk\Model\CreationEntityState::DELETING,
					\TrustPayments\Sdk\Model\CreationEntityState::INACTIVE 
				), 'TrustPayments\Webhook\MethodConfiguration', true);
		$this->webhook_entities[1472041829003] = new Entity(1472041829003, 'Transaction',
				array(
					\TrustPayments\Sdk\Model\TransactionState::CONFIRMED,
					\TrustPayments\Sdk\Model\TransactionState::AUTHORIZED,
					\TrustPayments\Sdk\Model\TransactionState::DECLINE,
					\TrustPayments\Sdk\Model\TransactionState::FAILED,
					\TrustPayments\Sdk\Model\TransactionState::FULFILL,
					\TrustPayments\Sdk\Model\TransactionState::VOIDED,
					\TrustPayments\Sdk\Model\TransactionState::COMPLETED,
					\TrustPayments\Sdk\Model\TransactionState::PROCESSING 
				), 'TrustPayments\Webhook\Transaction');
		$this->webhook_entities[1472041819799] = new Entity(1472041819799, 'Delivery Indication',
				array(
					\TrustPayments\Sdk\Model\DeliveryIndicationState::MANUAL_CHECK_REQUIRED 
				), 'TrustPayments\Webhook\DeliveryIndication');
		
		$this->webhook_entities[1472041831364] = new Entity(1472041831364, 'Transaction Completion',
				array(
					\TrustPayments\Sdk\Model\TransactionCompletionState::FAILED,
					\TrustPayments\Sdk\Model\TransactionCompletionState::SUCCESSFUL 
				), 'TrustPayments\Webhook\TransactionCompletion');
		
		$this->webhook_entities[1472041867364] = new Entity(1472041867364, 'Transaction Void',
				array(
					\TrustPayments\Sdk\Model\TransactionVoidState::FAILED,
					\TrustPayments\Sdk\Model\TransactionVoidState::SUCCESSFUL 
				), 'TrustPayments\Webhook\TransactionVoid');
		
		$this->webhook_entities[1472041839405] = new Entity(1472041839405, 'Refund',
				array(
					\TrustPayments\Sdk\Model\RefundState::FAILED,
					\TrustPayments\Sdk\Model\RefundState::SUCCESSFUL 
				), 'TrustPayments\Webhook\TransactionRefund');
		$this->webhook_entities[1472041806455] = new Entity(1472041806455, 'Token',
				array(
					\TrustPayments\Sdk\Model\CreationEntityState::ACTIVE,
					\TrustPayments\Sdk\Model\CreationEntityState::DELETED,
					\TrustPayments\Sdk\Model\CreationEntityState::DELETING,
					\TrustPayments\Sdk\Model\CreationEntityState::INACTIVE 
				), 'TrustPayments\Webhook\Token');
		$this->webhook_entities[1472041811051] = new Entity(1472041811051, 'Token Version',
				array(
					\TrustPayments\Sdk\Model\TokenVersionState::ACTIVE,
					\TrustPayments\Sdk\Model\TokenVersionState::OBSOLETE 
				), 'TrustPayments\Webhook\TokenVersion');
	}

	/**
	 * Installs the necessary webhooks in TrustPayments.
	 */
	public function install($space_id, $url){
		if ($space_id !== null && !empty($url)) {
			$webhook_url = $this->getWebhookUrl($space_id, $url);
			if ($webhook_url == null) {
				$webhook_url = $this->createWebhookUrl($space_id, $url);
			}
			$existing_listeners = $this->getWebhookListeners($space_id, $webhook_url);
			foreach ($this->webhook_entities as $webhook_entity) {
				/* @var WC_TrustPayments_Webhook_Entity $webhook_entity */
				$exists = false;
				foreach ($existing_listeners as $existing_listener) {
					if ($existing_listener->getEntity() == $webhook_entity->getId()) {
						$exists = true;
					}
				}
				if (!$exists) {
					$this->createWebhookListener($webhook_entity, $space_id, $webhook_url);
				}
			}
		}
	}
	
	public function uninstall($space_id, $url) {
		if($space_id !== null && !empty($url)) {
			$webhook_url = $this->getWebhookUrl($space_id, $url);
			if($webhook_url == null) {
				\TrustPaymentsHelper::instance($this->registry)->log("Attempted to uninstall webhooks with URL $url, but was not found");
				return;
			}
			foreach($this->getWebhookListeners($space_id, $webhook_url) as $listener) {
				$this->getWebhookListenerService()->delete($space_id, $listener->getId());
			}
			
			$this->getWebhookUrlService()->delete($space_id, $webhook_url->getId());
		}
	}

	/**
	 *
	 * @param int|string $id
	 * @return Entity
	 */
	public function getWebhookEntityForId($id){
		if (isset($this->webhook_entities[$id])) {
			return $this->webhook_entities[$id];
		}
		return null;
	}

	/**
	 * Create a webhook listener.
	 *
	 * @param Entity $entity
	 * @param int $space_id
	 * @param \TrustPayments\Sdk\Model\WebhookUrl $webhook_url
	 * @return \TrustPayments\Sdk\Model\WebhookListenerCreate
	 */
	protected function createWebhookListener(Entity $entity, $space_id, \TrustPayments\Sdk\Model\WebhookUrl $webhook_url){
		$webhook_listener = new \TrustPayments\Sdk\Model\WebhookListenerCreate();
		$webhook_listener->setEntity($entity->getId());
		$webhook_listener->setEntityStates($entity->getStates());
		$webhook_listener->setName('Opencart ' . $entity->getName());
		$webhook_listener->setState(\TrustPayments\Sdk\Model\CreationEntityState::ACTIVE);
		$webhook_listener->setUrl($webhook_url->getId());
		$webhook_listener->setNotifyEveryChange($entity->isNotifyEveryChange());
		return $this->getWebhookListenerService()->create($space_id, $webhook_listener);
	}

	/**
	 * Returns the existing webhook listeners.
	 *
	 * @param int $space_id
	 * @param \TrustPayments\Sdk\Model\WebhookUrl $webhook_url
	 * @return \TrustPayments\Sdk\Model\WebhookListener[]
	 */
	protected function getWebhookListeners($space_id, \TrustPayments\Sdk\Model\WebhookUrl $webhook_url){
		$query = new \TrustPayments\Sdk\Model\EntityQuery();
		$filter = new \TrustPayments\Sdk\Model\EntityQueryFilter();
		$filter->setType(\TrustPayments\Sdk\Model\EntityQueryFilterType::_AND);
		$filter->setChildren(
				array(
					$this->createEntityFilter('state', \TrustPayments\Sdk\Model\CreationEntityState::ACTIVE),
					$this->createEntityFilter('url.id', $webhook_url->getId()) 
				));
		$query->setFilter($filter);
		return $this->getWebhookListenerService()->search($space_id, $query);
	}

	/**
	 * Creates a webhook url.
	 *
	 * @param int $space_id
	 * @return \TrustPayments\Sdk\Model\WebhookUrlCreate
	 */
	protected function createWebhookUrl($space_id){
		$webhook_url = new \TrustPayments\Sdk\Model\WebhookUrlCreate();
		$webhook_url->setUrl($this->getUrl());
		$webhook_url->setState(\TrustPayments\Sdk\Model\CreationEntityState::ACTIVE);
		$webhook_url->setName('Opencart');
		return $this->getWebhookUrlService()->create($space_id, $webhook_url);
	}

	/**
	 * Returns the existing webhook url if there is one.
	 *
	 * @param int $space_id
	 * @return \TrustPayments\Sdk\Model\WebhookUrl
	 */
	protected function getWebhookUrl($space_id, $url){
		$query = new \TrustPayments\Sdk\Model\EntityQuery();
		$query->setNumberOfEntities(1);
		$filter = new \TrustPayments\Sdk\Model\EntityQueryFilter();
		$filter->setType(\TrustPayments\Sdk\Model\EntityQueryFilterType::_AND);
		$filter->setChildren(
				array(
					$this->createEntityFilter('state', \TrustPayments\Sdk\Model\CreationEntityState::ACTIVE),
					$this->createEntityFilter('url', $url)
				));
		$query->setFilter($filter);
		$result = $this->getWebhookUrlService()->search($space_id, $query);
		if (!empty($result)) {
			return $result[0];
		}
		else {
			return null;
		}
	}

	/**
	 * Returns the webhook endpoint URL.
	 *
	 * @return string
	 */
	protected function getUrl(){
		return \TrustPaymentsHelper::instance($this->registry)->getWebhookUrl();
	}

	/**
	 * Returns the webhook listener API service.
	 *
	 * @return \TrustPayments\Sdk\Service\WebhookListenerService
	 */
	protected function getWebhookListenerService(){
		if ($this->webhook_listener_service == null) {
			$this->webhook_listener_service = new \TrustPayments\Sdk\Service\WebhookListenerService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		}
		return $this->webhook_listener_service;
	}

	/**
	 * Returns the webhook url API service.
	 *
	 * @return \TrustPayments\Sdk\Service\WebhookUrlService
	 */
	protected function getWebhookUrlService(){
		if ($this->webhook_url_service == null) {
			$this->webhook_url_service = new \TrustPayments\Sdk\Service\WebhookUrlService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		}
		return $this->webhook_url_service;
	}
}