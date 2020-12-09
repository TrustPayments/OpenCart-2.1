<?php

namespace TrustPayments\Controller;

abstract class AbstractEvent extends AbstractController {

	protected function validate(){
		$this->language->load('payment/trustpayments');
		$this->validatePermission();
		// skip valdiating order.
	}
}