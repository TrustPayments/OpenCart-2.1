<?php
require_once modification(DIR_SYSTEM . 'library/trustpayments/helper.php');

class ControllerExtensionTrustPaymentsEvent extends TrustPayments\Controller\AbstractEvent {

	/**
	 * Re-Creates required files for display of payment methods.
	 */
	public function createMethodConfigurationFiles(){
		try {
			$this->validate();
			$this->load->model('extension/trustpayments/dynamic');
			$this->model_extension_trustpayments_dynamic->install();
		}
		catch (Exception $e) {
			// ensure that permissions etc. do not cause page loads to fail
			return;
		}
	}

	protected function getRequiredPermission(){
		return 'extension/trustpayments/event';
	}
}