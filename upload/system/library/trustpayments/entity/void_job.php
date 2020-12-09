<?php

namespace TrustPayments\Entity;

/**
 *
 */
class VoidJob extends AbstractJob {

	protected static function getTableName(){
		return 'trustpayments_void_job';
	}
}