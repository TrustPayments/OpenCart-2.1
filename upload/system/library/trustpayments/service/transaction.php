<?php

namespace TrustPayments\Service;

use TrustPayments\Sdk\Service\ChargeAttemptService;
use TrustPayments\Sdk\Service\TransactionService;
use TrustPayments\Sdk\Service\TransactionIframeService;
use TrustPayments\Sdk\Service\TransactionPaymentPageService;

/**
 * This service provides functions to deal with TrustPayments transactions.
 *
 * It generally provides three ways of creating & updating transactions.
 * 1) With total & address, for filtering active payment methods.
 * 2) With session data, before the order has been persisted in the database. For getting javascript url & confirming the order.
 * 3) With the order information, after the order has been completed. For backend operations.
 */
class Transaction extends AbstractService {

	public function getPaymentMethods(array $order_info){
		$sessionId = \TrustPaymentsHelper::instance($this->registry)->getCustomerSessionIdentifier();
		if (!$sessionId || !array_key_exists($sessionId, self::$possible_payment_method_cache)) {
			$transaction = $this->update($order_info, false);
			try {
				$payment_methods = $this->getTransactionService()->fetchPaymentMethods(
					$transaction->getLinkedSpaceId(),
					$transaction->getId(),
					'iframe'
				);
				foreach ($payment_methods as $payment_method) {
					MethodConfiguration::instance($this->registry)->updateData($payment_method);
				}
				self::$possible_payment_method_cache[$sessionId] = $payment_methods;
			}
			catch (\Exception $e) {
				self::$possible_payment_method_cache[$sessionId] = array();
				throw $e;
			}
		}
		return self::$possible_payment_method_cache[$sessionId];
	}

	public function getJavascriptUrl(){
		$transaction = $this->getTransaction(array(), false, array(
			\TrustPayments\Sdk\Model\TransactionState::PENDING 
		));
		$this->persist($transaction, array());
		return $this->getIframeService()->javascriptUrl($transaction->getLinkedSpaceId(), $transaction->getId());
	}

	public function getPaymentPageUrl(\TrustPayments\Sdk\Model\Transaction $transaction, $paymentCode){
		$paymentMethodId = \TrustPaymentsHelper::extractPaymentMethodId($paymentCode);
		return $this->getPaymentPageService()->paymentPageUrl($transaction->getLinkedSpaceId(), $transaction->getId()) .
				 '&paymentMethodConfigurationId=' . $paymentMethodId;
	}
	
	protected function getAllowedPaymentMethodConfigurations(array $order_info) {
		if(isset($order_info['payment_method']) && isset($order_info['payment_method']['code'])){
			return array(\TrustPaymentsHelper::extractPaymentMethodId($order_info['payment_method']['code']));
		}
		return null;
	}

	public function update(array $order_info, $confirm = false){
		$last = null;
		try {
			for ($i = 0; $i < 5; $i++) {
				$transaction = $this->getTransaction($order_info, false);
				if ($transaction->getState() !== \TrustPayments\Sdk\Model\TransactionState::PENDING) {
					if ($confirm) {
						throw new \Exception('No pending transaction available to be confirmed.');
					}
					return $this->create($order_info);
				}
				
				$pending_transaction = new \TrustPayments\Sdk\Model\TransactionPending();
				$pending_transaction->setId($transaction->getId());
				$pending_transaction->setVersion($transaction->getVersion());
				$this->assembleTransaction($pending_transaction, $order_info);
				
				if ($confirm) {
					$pending_transaction->setAllowedPaymentMethodConfigurations($this->getAllowedPaymentMethodConfigurations($order_info));
					$transaction = $this->getTransactionService()->confirm($transaction->getLinkedSpaceId(), $pending_transaction);
					$this->clearTransactionInSession();
				}
				else {
					$transaction = $this->getTransactionService()->update($transaction->getLinkedSpaceId(), $pending_transaction);
				}
				
				$this->persist($transaction, $order_info);
				
				return $transaction;
			}
		}
		catch (\TrustPayments\Sdk\ApiException $e) {
			$last = $e;
			if ($e->getCode() != 409) {
				throw $e;
			}
		}
		
		throw $last;
	}

	/**
	 * Wait for the order to reach a given state.
	 *
	 * @param $order_id
	 * @param array $states
	 * @param int $maxWaitTime
	 * @return boolean
	 */
	public function waitForStates($order_id, array $states, $maxWaitTime = 10){
		$startTime = microtime(true);
		while (true) {
			$transactionInfo = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $order_id);
			if (in_array($transactionInfo->getState(), $states)) {
				return true;
			}
			
			if (microtime(true) - $startTime >= $maxWaitTime) {
				return false;
			}
			sleep(1);
		}
	}

	/**
	 * Reads or creates a new transaction.
	 *
	 * @param array $order_info
	 * @return \TrustPayments\Sdk\Model\Transaction
	 */
	public function getTransaction($order_info = array(), $cache = true, $allowed_states = array()){
		$sessionId = \TrustPaymentsHelper::instance($this->registry)->getCustomerSessionIdentifier();
		
		if ($sessionId && isset(self::$transaction_cache[$sessionId]) && $cache) {
			return self::$transaction_cache[$sessionId];
		}
		
		$create = true;
		
		// attempt to load via session variables
		if ($this->hasTransactionInSession()) {
			self::$transaction_cache[$sessionId] = $this->getTransactionService()->read($this->getSessionSpaceId(), $this->getSessionTransactionId());
			// check if the status is expected
			$create = empty($allowed_states) ? false : !in_array(self::$transaction_cache[$sessionId]->getState(), $allowed_states);
		}
		
		// attempt to load via order id (existing transaction_info
		if (isset($order_info['order_id']) && $create) {
			$transaction_info = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $order_info['order_id']);
			if ($transaction_info->getId() && $transaction_info->getState() === 'PENDING') {
				self::$transaction_cache[$sessionId] = $this->getTransactionService()->read($transaction_info->getSpaceId(),
						$transaction_info->getTransactionId());
				$create = empty($allowed_states) ? false : !in_array(self::$transaction_cache[$sessionId]->getState(), $allowed_states);
			}
			if ($create) {
				throw new Exception("Order ID was already used."); // Todo test
			}
		}
		
		// no applicable transaction found, create new one.
		if ($create) {
			self::$transaction_cache[$sessionId] = $this->create($order_info);
		}
		
		return self::$transaction_cache[$sessionId];
	}

	private function persist($transaction, array $order_info){
		if (isset($order_info['order_id'])) {
			$this->updateTransactionInfo($transaction, $order_info['order_id']);
		}
		$this->storeTransactionIdsInSession($transaction);
		$this->storeShipping($transaction);
	}

	private function create(array $order_info){
		$create_transaction = new \TrustPayments\Sdk\Model\TransactionCreate();
		
		$create_transaction->setCustomersPresence(\TrustPayments\Sdk\Model\CustomersPresence::VIRTUAL_PRESENT);
		if (isset($this->registry->get('request')->cookie['trustpayments_device_id'])) {
			$create_transaction->setDeviceSessionIdentifier($this->registry->get('request')->cookie['trustpayments_device_id']);
		}
		
		$create_transaction->setAutoConfirmationEnabled(false);
		$create_transaction->setChargeRetryEnabled(false);
		$this->assembleTransaction($create_transaction, $order_info);
		$transaction = $this->getTransactionService()->create($this->registry->get('config')->get('trustpayments_space_id'),
				$create_transaction);
		
		$this->persist($transaction, $order_info);
		
		return $transaction;
	}

	private function assembleTransaction(\TrustPayments\Sdk\Model\AbstractTransactionPending $transaction, array $order_info){
		$order_id = isset($order_info['order_id']) ? $order_info['order_id'] : null;
		$data = $this->registry->get('session')->data;
		
		if (isset($data['currency'])) {
			$transaction->setCurrency($data['currency']);
		}
		else {
			throw new \Exception('Session currency not set.');
		}
		
		$transaction->setBillingAddress(
				$this->assembleAddress(\TrustPaymentsHelper::instance($this->registry)->getAddress('payment', $order_info)));
		if ($this->registry->get('cart')->hasShipping()) {
			$transaction->setShippingAddress(
					$this->assembleAddress(\TrustPaymentsHelper::instance($this->registry)->getAddress('shipping', $order_info)));
		}
		
		$customer = \TrustPaymentsHelper::instance($this->registry)->getCustomer();
		if (isset($customer['customer_id'])) {
			$transaction->setCustomerId($customer['customer_id']);
		}
		if (isset($customer['customer_email'])) {
			$transaction->setCustomerEmailAddress($this->getFixedSource($customer, 'customer_email', 150));
		}
		else if (isset($customer['email'])) {
			$transaction->setCustomerEmailAddress($this->getFixedSource($customer, 'email', 150));
		}
		
		$transaction->setLanguage(\TrustPaymentsHelper::instance($this->registry)->getCleanLanguageCode());
		if (isset($data['shipping_method'])) {
			$transaction->setShippingMethod($this->fixLength($data['shipping_method']['title'], 200));
		}
		
		$transaction->setLineItems(LineItem::instance($this->registry)->getItemsFromSession());
		$transaction->setSuccessUrl(\TrustPaymentsHelper::instance($this->registry)->getSuccessUrl());

		if ($order_id) {
			$transaction->setMerchantReference($order_id);
			$transaction->setFailedUrl(\TrustPaymentsHelper::instance($this->registry)->getFailedUrl($order_id));
		}
	}
	
	/**
	 * Cache for cart transactions.
	 *
	 * @var \TrustPayments\Sdk\Model\Transaction[]
	 */
	private static $transaction_cache = array();
	
	/**
	 * Cache for possible payment methods by cart.
	 *
	 * @var \TrustPayments\Sdk\Model\PaymentMethodConfiguration[]
	 */
	private static $possible_payment_method_cache = array();
	
	/**
	 * The transaction API service.
	 *
	 * @var \TrustPayments\Sdk\Service\TransactionService
	 */
	private $transaction_service;
	
	/**
	 * The charge attempt API service.
	 *
	 * @var \TrustPayments\Sdk\Service\ChargeAttemptService
	 */
	private $charge_attempt_service;
	
	/**
	 * The iframe API service, to retrieve JS url
	 *
	 * @var \TrustPayments\Sdk\Service\TransactionIframeService
	 */
	private $transaction_iframe_service;
	
	/**
	 * The payment page API service, tro retrieve pp URL
	 * 
	 * @var \TrustPayments\Sdk\Service\TransactionPaymentPageService
	 */
	private $transaction_payment_page_service;

	/**
	 * Returns the transaction API service.
	 *
	 * @return \TrustPayments\Sdk\Service\TransactionService
	 */
	private function getTransactionService(){
		if ($this->transaction_service === null) {
			$this->transaction_service = new TransactionService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		}
		return $this->transaction_service;
	}

	/**
	 * Returns the charge attempt API service.
	 *
	 * @return \TrustPayments\Sdk\Service\ChargeAttemptService
	 */
	private function getChargeAttemptService(){
		if ($this->charge_attempt_service === null) {
			$this->charge_attempt_service = new ChargeAttemptService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		}
		return $this->charge_attempt_service;
	}
	
	/**
	 * Returns the transaction API service.
	 *
	 * @return \TrustPayments\Sdk\Service\TransactionIframeService
	 */
	private function getIframeService(){
		if ($this->transaction_iframe_service === null) {
			$this->transaction_iframe_service = new TransactionIframeService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		}
		return $this->transaction_iframe_service;
	}
	
	/**
	 * Returns the transaction API service.
	 *
	 * @return \TrustPayments\Sdk\Service\TransactionPaymentPageService
	 */
	private function getPaymentPageService(){
		if ($this->transaction_payment_page_service === null) {
			$this->transaction_payment_page_service = new TransactionPaymentPageService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		}
		return $this->transaction_payment_page_service;
	}
	
	/**
	 * Updates the line items to be in line with the current order.
	 *
	 * @param string $order_id
	 * @return \TrustPayments\Sdk\Model\TransactionLineItemVersion
	 */
	public function updateLineItemsFromOrder($order_id){
		$order_info = \TrustPaymentsHelper::instance($this->registry)->getOrder($order_id);
		$transaction_info = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $order_id);
		
		\TrustPaymentsHelper::instance($this->registry)->xfeeproDisableIncVat();
		$line_items = \TrustPayments\Service\LineItem::instance($this->registry)->getItemsFromOrder($order_info,
				$transaction_info->getTransactionId(), $transaction_info->getSpaceId());
		\TrustPaymentsHelper::instance($this->registry)->xfeeproRestoreIncVat();
		
		$update_request = new \TrustPayments\Sdk\Model\TransactionLineItemUpdateRequest();
		$update_request->setTransactionId($transaction_info->getTransactionId());
		$update_request->setNewLineItems($line_items);
		return $this->getTransactionService()->updateTransactionLineItems($transaction_info->getSpaceId(), $update_request);
	}

	/**
	 * Stores the transaction data in the database.
	 *
	 * @param \TrustPayments\Sdk\Model\Transaction $transaction
	 * @param array $order_info
	 * @return \TrustPayments\Entity\TransactionInfo
	 */
	public function updateTransactionInfo(\TrustPayments\Sdk\Model\Transaction $transaction, $order_id){
		$info = \TrustPayments\Entity\TransactionInfo::loadByTransaction($this->registry, $transaction->getLinkedSpaceId(),
				$transaction->getId());
		$info->setTransactionId($transaction->getId());
		$info->setAuthorizationAmount($transaction->getAuthorizationAmount());
		$info->setOrderId($order_id);
		$info->setState($transaction->getState());
		$info->setSpaceId($transaction->getLinkedSpaceId());
		$info->setSpaceViewId($transaction->getSpaceViewId());
		$info->setLanguage($transaction->getLanguage());
		$info->setCurrency($transaction->getCurrency());
		$info->setConnectorId(
				$transaction->getPaymentConnectorConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()->getConnector() : null);
		$info->setPaymentMethodId(
				$transaction->getPaymentConnectorConfiguration() != null && $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() !=
				null ? $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration()->getPaymentMethod() : null);
		$info->setImage($this->getPaymentMethodImage($transaction));
		$info->setLabels($this->getTransactionLabels($transaction));
		if ($transaction->getState() == \TrustPayments\Sdk\Model\TransactionState::FAILED ||
				 $transaction->getState() == \TrustPayments\Sdk\Model\TransactionState::DECLINE) {
			$failed_charge_attempt = $this->getFailedChargeAttempt($transaction->getLinkedSpaceId(), $transaction->getId());
			if ($failed_charge_attempt && $failed_charge_attempt->getFailureReason() != null) {
				$info->setFailureReason($failed_charge_attempt->getFailureReason()->getDescription());
			}
			else if ($transaction->getFailureReason()) {
				$info->setFailureReason($transaction->getFailureReason()->getDescription());
			}
		}
		// TODO into helper?
		if($this->hasSaveableCoupon()) {
			$info->setCouponCode($this->getCoupon());
		}
		$info->save();
		return $info;
	}

	/**
	 * Returns the last failed charge attempt of the transaction.
	 *
	 * @param int $space_id
	 * @param int $transaction_id
	 * @return \TrustPayments\Sdk\Model\ChargeAttempt
	 */
	private function getFailedChargeAttempt($space_id, $transaction_id){
		$charge_attempt_service = $this->getChargeAttemptService();
		$query = new \TrustPayments\Sdk\Model\EntityQuery();
		$filter = new \TrustPayments\Sdk\Model\EntityQueryFilter();
		$filter->setType(\TrustPayments\Sdk\Model\EntityQueryFilterType::_AND);
		$filter->setChildren(
				array(
					$this->createEntityFilter('charge.transaction.id', $transaction_id),
					$this->createEntityFilter('state', \TrustPayments\Sdk\Model\ChargeAttemptState::FAILED) 
				));
		$query->setFilter($filter);
		$query->setOrderBys(array(
			$this->createEntityOrderBy('failedOn') 
		));
		$query->setNumberOfEntities(1);
		$result = $charge_attempt_service->search($space_id, $query);
		if ($result != null && !empty($result)) {
			return current($result);
		}
		else {
			return null;
		}
	}

	/**
	 * Returns an array of the transaction's labels.
	 *
	 * @param \TrustPayments\Sdk\Model\Transaction $transaction
	 * @return string[]
	 */
	private function getTransactionLabels(\TrustPayments\Sdk\Model\Transaction $transaction){
		$charge_attempt = $this->getChargeAttempt($transaction);
		if ($charge_attempt != null) {
			$labels = array();
			foreach ($charge_attempt->getLabels() as $label) {
				$labels[$label->getDescriptor()->getId()] = $label->getContentAsString();
			}
			return $labels;
		}
		else {
			return array();
		}
	}

	/**
	 * Returns the successful charge attempt of the transaction.
	 *
	 * @param \TrustPayments\Sdk\Model\Transaction $transaction
	 * @return \TrustPayments\Sdk\Model\ChargeAttempt
	 */
	private function getChargeAttempt(\TrustPayments\Sdk\Model\Transaction $transaction){
		$charge_attempt_service = $this->getChargeAttemptService();
		$query = new \TrustPayments\Sdk\Model\EntityQuery();
		$filter = new \TrustPayments\Sdk\Model\EntityQueryFilter();
		$filter->setType(\TrustPayments\Sdk\Model\EntityQueryFilterType::_AND);
		$filter->setChildren(
				array(
					$this->createEntityFilter('charge.transaction.id', $transaction->getId()),
					$this->createEntityFilter('state', \TrustPayments\Sdk\Model\ChargeAttemptState::SUCCESSFUL) 
				));
		$query->setFilter($filter);
		$query->setNumberOfEntities(1);
		$result = $charge_attempt_service->search($transaction->getLinkedSpaceId(), $query);
		if ($result != null && !empty($result)) {
			return current($result);
		}
		else {
			return null;
		}
	}

	/**
	 * Returns the payment method's image.
	 *
	 * @param \TrustPayments\Sdk\Model\Transaction $transaction
	 * @return string
	 */
	private function getPaymentMethodImage(\TrustPayments\Sdk\Model\Transaction $transaction){
		if ($transaction->getPaymentConnectorConfiguration() == null ||
				 $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() == null) {
			return null;
		}
		return $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration()->getResolvedImageUrl();
	}

	private function assembleAddress($source, $prefix = ''){
		$address = new \TrustPayments\Sdk\Model\AddressCreate();
		
		if (isset($source[$prefix . 'city'])) {
			$address->setCity($this->getFixedSource($source, $prefix . 'city', 100, false));
		}
		if (isset($source[$prefix . 'iso_code_2'])) {
			$address->setCountry($source[$prefix . 'iso_code_2']);
		}
		if (isset($source[$prefix . 'lastname'])) {
			$address->setFamilyName($this->getFixedSource($source, $prefix . 'lastname', 100, false));
		}
		if (isset($source[$prefix . 'firstname'])) {
			$address->setGivenName($this->getFixedSource($source, $prefix . 'firstname', 100, false));
		}
		if (isset($source[$prefix . 'company'])) {
			$address->setOrganizationName($this->getFixedSource($source, $prefix . 'company', 100, false));
		}
		if (isset($source[$prefix . 'postcode'])) {
			$address->setPostCode($this->getFixedSource($source, $prefix . 'postcode', 40));
		}
		if (isset($source[$prefix . 'address_1'])) {
			$address->setStreet($this->fixLength(trim($source[$prefix . 'address_1'] . "\n" . $source[$prefix . 'address_2']), 300, false));
		}
		
		// state is 2-part
		if (isset($source[$prefix . 'zone_code']) && isset($source[$prefix . 'iso_code_2'])) {
			$address->setPostalState($source[$prefix . 'iso_code_2'] . '_' . $source[$prefix . 'zone_code']);
		}
		
		return $address;
	}

	private function getFixedSource(array $source_array, $key, $max_length = null, $is_ascii = true, $new_lines = false){
		$value = null;
		if (isset($source_array[$key])) {
			$value = $source_array[$key];
			if ($max_length) {
				$value = $this->fixLength($value, $max_length);
			}
			if ($is_ascii) {
				$value = $this->removeNonAscii($value);
			}
			if (!$new_lines) {
				$value = str_replace("\n", "", $value);
			}
		}
		return $value;
	}

	private function hasTransactionInSession(){
		$data = $this->registry->get('session')->data;
		return isset($data['trustpayments_transaction_id']) && isset($data['trustpayments_space_id']) &&
				 $data['trustpayments_space_id'] == $this->registry->get('config')->get('trustpayments_space_id') &&
				 \TrustPaymentsHelper::instance($this->registry)->compareStoredCustomerSessionIdentifier();
	}

	public function clearTransactionInSession(){
		if ($this->hasTransactionInSession()) {
			unset($this->registry->get('session')->data['trustpayments_transaction_id']);
			unset($this->registry->get('session')->data['trustpayments_customer']);
			unset($this->registry->get('session')->data['trustpayments_space_id']);
		}
	}

	private function getSessionTransactionId(){
		return $this->registry->get('session')->data['trustpayments_transaction_id'];
	}

	private function getSessionSpaceId(){
		return $this->registry->get('session')->data['trustpayments_space_id'];
	}

	private function storeTransactionIdsInSession(\TrustPayments\Sdk\Model\Transaction $transaction){
		$this->registry->get('session')->data['trustpayments_customer'] = \TrustPaymentsHelper::instance($this->registry)->getCustomerSessionIdentifier();
		$this->registry->get('session')->data['trustpayments_transaction_id'] = $transaction->getId();
		$this->registry->get('session')->data['trustpayments_space_id'] = $transaction->getLinkedSpaceId();
	}

	private function storeShipping(\TrustPayments\Sdk\Model\Transaction $transaction){
		$session = $this->registry->get('session')->data;
		if (isset($session['shipping_method']) && isset($session['shipping_method']['cost']) && !empty($session['shipping_method']['cost'])) {
			$shipping_info = \TrustPayments\Entity\ShippingInfo::loadByTransaction($this->registry, $transaction->getLinkedSpaceId(),
					$transaction->getId());
			$shipping_info->setTransactionId($transaction->getId());
			$shipping_info->setSpaceId($transaction->getLinkedSpaceId());
			$shipping_info->setCost($this->registry->get('session')->data['shipping_method']['cost']);
			$shipping_info->setTaxClassId($this->registry->get('session')->data['shipping_method']['tax_class_id']);
			$shipping_info->save();
		}
	}
	
	private function hasSaveableCoupon() {
		return isset($this->registry->get('session')->data['coupon']) && isset($this->registry->get('session')->data['order_id']);
	}
	
	private function getCoupon() {
		return $this->registry->get('session')->data['coupon'];
	}
}