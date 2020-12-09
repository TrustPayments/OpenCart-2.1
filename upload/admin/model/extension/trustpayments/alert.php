<?php
require_once modification(DIR_SYSTEM . 'library/trustpayments/helper.php');
use TrustPayments\Model\AbstractModel;

/**
 * Handles the display of alerts in the top right.
 * Is used in combination with
 * - controller/extension/trustpayments/alert.php
 * - system/library/trustpayments/modification/TrustPaymentsAlerts.ocmod.xml
 */
class ModelExtensionTrustPaymentsAlert extends AbstractModel {
	private $alerts;

	public function getAlertsTitle(){
		$this->load->language('payment/trustpayments');
		return $this->language->get('title_notifications');
	}

	public function getAlerts(){
		if ($this->alerts == null) {
			try {
				$this->load->language('payment/trustpayments');
				$this->alerts = array();
				$alert_entities = \TrustPayments\Entity\Alert::loadAll($this->registry);
			
				foreach ($alert_entities as $alert_entity) {
					$this->alerts[] = array(
						'url' => $this->createUrl($alert_entity->getRoute(),
								array(
									\TrustPaymentsVersionHelper::TOKEN => $this->session->data[\TrustPaymentsVersionHelper::TOKEN] 
								)),
						'text' => $this->language->get($alert_entity->getKey()),
						'level' => $alert_entity->getLevel(),
						'count' => $alert_entity->getCount() 
					);
				}
			}
			catch(\Exception $e) {
				// We ignore errors here otherwise we might not be albe to display the admin backend UI.
			}
		}
		return $this->alerts;
	}

	public function getAlertCount(){
		$count = 0;
		foreach ($this->getAlerts() as $alert) {
			$count += $alert['count'];
		}
		return $count;
	}
}
