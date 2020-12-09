<?php
require_once modification(DIR_SYSTEM . 'library/trustpayments/helper.php');

class ControllerExtensionTrustPaymentsRefund extends \TrustPayments\Controller\AbstractController {

	public function page(){
		$this->language->load('payment/trustpayments');
		$this->response->addHeader('Content-Type: application/json');
		try {
			$this->validate();
			
			$this->response->setOutput(
					json_encode(
							array(
								'redirect' => $this->createUrl('extension/trustpayments/refund',
										array(
											\TrustPaymentsVersionHelper::TOKEN => $this->request->get[\TrustPaymentsVersionHelper::TOKEN],
											'order_id' => $this->request->get['order_id'] 
										)) 
							)));
		}
		catch (Exception $e) {
			$this->response->setOutput(json_encode(array(
				'error' => $e->getMessage() 
			)));
		}
	}

	public function index(){
		try {
			$this->validate();
		}
		catch (Exception $e) {
			$this->displayError($e->getMessage());
			return;
		}
		
		$variables = array();
		$variables['error_warning'] = '';
		$variables['success'] = '';
		
		$this->load->model('sale/order');
		$order_info = $this->model_sale_order->getOrder($this->request->get['order_id']);
		$transaction_info = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $this->request->get['order_id']);
		
		$line_items = \TrustPayments\Service\LineItem::instance($this->registry)->getReducedItemsFromOrder($order_info, $transaction_info->getTransactionId(),
				$transaction_info->getSpaceId());
		$this->document->setTitle($this->language->get('heading_refund'));
		$this->document->addScript('view/javascript/trustpayments/refund.js');
		
		$variables += $this->loadLanguageVariables();
		$variables += $this->getAdminSurroundingTemplates();
		$variables += $this->getBreadcrumbs();
		
		$variables['line_items'] = $line_items;
		$variables['fixed_tax'] = false;
		foreach ($line_items as $line_item) {
			if (strpos($line_item->getUniqueId(), 'fixed_tax_') === 0) {
				$variables['fixed_tax'] = $this->language->get('description_fixed_tax');
				break;
			}
		}
		
		$currency_info = \TrustPayments\Provider\Currency::instance($this->registry)->find($order_info['currency_code']);
		if(!$currency_info) {
			$this->displayError($this->language->get('error_currency'));
			return;
		}
		
		$variables['currency_step'] = pow(10, -$currency_info->getFractionDigits());
		$variables['currency_decimals'] = $currency_info->getFractionDigits();
		$variables['cancel'] = $this->createUrl('sale/order/info',
				array(
					\TrustPaymentsVersionHelper::TOKEN => $this->session->data[\TrustPaymentsVersionHelper::TOKEN],
					'order_id' => $this->request->get['order_id'] 
				));
		$variables['refund_action'] = $this->createUrl('extension/trustpayments/refund/process',
				array(
					\TrustPaymentsVersionHelper::TOKEN => $this->session->data[\TrustPaymentsVersionHelper::TOKEN],
					'order_id' => $this->request->get['order_id'] 
				));
		
		$this->response->setOutput($this->loadView("extension/trustpayments/refund", $variables));
	}

	public function process(){
		try {
			$this->validate();
			
			$transaction_info = \TrustPayments\Entity\TransactionInfo::loadByOrderId($this->registry, $this->request->get['order_id']);
			
			$running = \TrustPayments\Entity\RefundJob::loadRunningForOrder($this->registry, $transaction_info->getOrderId());
			if ($running->getId()) {
				throw new \Exception($this->language->get('error_already_running'));
			}
			
			if (!\TrustPaymentsHelper::instance($this->registry)->isRefundPossible($transaction_info)) {
				throw new \Exception($this->language->get('error_cannot_create_job'));
			}
			
			$job = \TrustPayments\Service\Refund::instance($this->registry)->create($transaction_info, $this->request->post['item'],
					isset($this->request->post['restock']));
			\TrustPayments\Service\Refund::instance($this->registry)->send($job);
			
			$this->response->redirect(
					$this->createUrl('sale/order/info',
							array(
								\TrustPaymentsVersionHelper::TOKEN => $this->request->get[\TrustPaymentsVersionHelper::TOKEN],
								'order_id' => $this->request->get['order_id'] 
							)));
		}
		catch (Exception $e) {
			$this->displayError($e->getMessage());
		}
	}

	private function getBreadcrumbs(){
		return array(
			'breadcrumbs' => array(
				array(
					'href' => $this->createUrl('common/dashboard',
							array(
								\TrustPaymentsVersionHelper::TOKEN => $this->session->data[\TrustPaymentsVersionHelper::TOKEN] 
							)),
					'text' => $this->language->get('text_home'),
					'separator' => false 
				),
				array(
					'href' => $this->createUrl('sale/order/info',
							array(
								\TrustPaymentsVersionHelper::TOKEN => $this->session->data[\TrustPaymentsVersionHelper::TOKEN],
								'order_id' => $this->request->get['order_id'] 
							)),
					'text' => $this->language->get('entry_order'),
					'separator' => false 
				),
				array(
					'href' => '#',
					'text' => $this->language->get('entry_refund'),
					'separator' => false 
				) 
			) 
		);
	}

	private function loadLanguageVariables(){
		$this->load->language('payment/trustpayments');
		$variables = array(
			'heading_refund',
			'entry_refund',
			'description_refund',
			'entry_name',
			'entry_sku',
			'entry_type',
			'entry_tax',
			'entry_quantity',
			'entry_amount',
			'entry_total',
			'entry_item',
			'entry_id',
			'entry_unit_amount',
			'button_refund',
			'button_reset',
			'button_full',
			'button_cancel',
			'type_fee',
			'type_product',
			'type_discount',
			'entry_order',
			'entry_restock',
			'error_empty_refund',
			'type_shipping' 
		);
		$data = array();
		foreach ($variables as $key) {
			$data[$key] = $this->language->get($key);
		}
		return $data;
	}

	protected function getRequiredPermission(){
		return 'extension/trustpayments/refund';
	}
}