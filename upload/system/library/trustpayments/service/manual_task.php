<?php

namespace TrustPayments\Service;

/**
 * This service provides methods to handle manual tasks.
 */
class ManualTask extends AbstractService {
	const CONFIG_KEY = 'trustpayments_manual_task';

	/**
	 * Returns the number of open manual tasks.
	 *
	 * @return int
	 */
	public function getNumberOfManualTasks(){
		$num = $this->registry->get('config')->get(self::CONFIG_KEY);
		return $num === null ? 0 : $num;
	}

	/**
	 * Updates the number of open manual tasks.
	 *
	 * @return int
	 */
	public function update(){
		$number_of_manual_tasks = 0;
		$manual_task_service = new \TrustPayments\Sdk\Service\ManualTaskService(\TrustPaymentsHelper::instance($this->registry)->getApiClient());
		
		$space_id = $this->registry->get('config')->get('trustpayments_space_id');
		if (!empty($space_id)) {
			$number_of_manual_tasks = $manual_task_service->count($space_id,
					$this->createEntityFilter('state', \TrustPayments\Sdk\Model\ManualTaskState::OPEN));
			
			$table = DB_PREFIX . 'setting';
			$key = self::CONFIG_KEY;
			$number_of_manual_tasks = (int) $number_of_manual_tasks;
			$store_id = $this->registry->get('config')->get('config_store_id');
			if($store_id === null){
				$store_id = 0;
			}
			
			\TrustPayments\Entity\Alert::loadManualTask($this->registry)->setCount($number_of_manual_tasks)->save();
			
			$this->registry->get('db')->query(
					"UPDATE $table SET `value`='$number_of_manual_tasks' WHERE `code`='trustpayments' AND `key`='$key' AND `store_id`='$store_id';");
		}
		
		return $number_of_manual_tasks;
	}
}