<?php
require_once modification(DIR_SYSTEM . 'library/trustpayments/helper.php');

class ControllerExtensionTrustPaymentsPdf extends TrustPayments\Controller\AbstractPdf {

	public function packingSlip(){
		$this->validate();
		$this->downloadPackingSlip($this->request->get['order_id']);
	}

	public function invoice(){
		$this->validate();
		$this->downloadInvoice($this->request->get['order_id']);
	}

	
	protected function getRequiredPermission(){
		return '';
	}
}