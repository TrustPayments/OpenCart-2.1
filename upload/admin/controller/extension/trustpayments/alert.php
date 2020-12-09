<?php
require_once modification(DIR_SYSTEM . 'library/trustpayments/helper.php');

/**
 * Handles the display of alerts in the top right.
 * Is used in combination with
 * - model/extension/trustpayments/alert.php
 * - system/library/trustpayments/modification/TrustPaymentsAlerts.ocmod.xml
 */
class ControllerExtensionTrustPaymentsAlert extends TrustPayments\Controller\AbstractEvent {

	/**
	 * Redirects the user to the manual task overview in the trustpayments backend.
	 */
	public function manual(){
		try {
			$this->validate();
			$this->response->redirect(\TrustPaymentsHelper::getBaseUrl() . '/s/' . $this->config->get('trustpayments_space_id') . '/manual-task/list');
		}
		catch (Exception $e) {
			$this->displayError($e->getMessage());
		}
	}

	/**
	 * Redirect the user to the order with the oldest checkable failed job.
	 */
	public function failed(){
		try {
			$oldest_failed = \TrustPayments\Entity\RefundJob::loadOldestCheckable($this->registry);
			if (!$oldest_failed->getId()) {
				$oldest_failed = \TrustPayments\Entity\CompletionJob::loadOldestCheckable($this->registry);
			}
			if (!$oldest_failed->getId()) {
				$oldest_failed = \TrustPayments\Entity\VoidJob::loadOldestCheckable($this->registry);
			}
			$this->response->redirect(
					$this->createUrl('sale/order/info',
							array(
								\TrustPaymentsVersionHelper::TOKEN => $this->session->data[\TrustPaymentsVersionHelper::TOKEN],
								'order_id' => $oldest_failed->getOrderId() 
							)));
		}
		catch (Exception $e) {
			$this->displayError($e->getMessage());
		}
	}

	protected function getRequiredPermission(){
		return 'extension/trustpayments/alert';
	}
}