<?php

namespace TrustPayments\Webhook;

/**
 * Webhook processor to handle manual task state transitions.
 */
class ManualTask extends AbstractWebhook {

	/**
	 * Updates the number of open manual tasks.
	 *
	 * @param \TrustPayments\Webhook\Request $request
	 */
	public function process(Request $request){
		$manual_task_service = \TrustPayments\service\ManualTask::instance($this->registry);
		$manual_task_service->update();
	}
}