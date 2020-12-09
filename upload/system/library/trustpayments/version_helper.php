<?php
require_once (DIR_SYSTEM . 'library/trustpayments/autoload.php');

/**
 * Versioning helper which offers implementations depending on opencart version.
 *
 * @author sebastian
 *
 */
class TrustPaymentsVersionHelper {
	const TOKEN = 'token';
	
	public static function getModifications(){
		return array(
			'TrustPaymentsCore' => array(
				'file' => 'TrustPaymentsCore.ocmod.xml',
				'default_status' => 1
			),
			'TrustPaymentsAlerts' => array(
				'file' => 'TrustPaymentsAlerts.ocmod.xml',
				'default_status' => 1
			),
			'TrustPaymentsAdministration' => array(
				'file' => 'TrustPaymentsAdministration.ocmod.xml',
				'default_status' => 1
			),
			'TrustPaymentsQuickCheckoutCompatibility' => array(
				'file' => 'TrustPaymentsQuickCheckoutCompatibility.ocmod.xml',
				'default_status' => 0
			),
			'TrustPaymentsXFeeProCompatibility' => array(
				'file' => 'TrustPaymentsXFeeProCompatibility.ocmod.xml',
				'default_status' => 0
			),
			'TrustPaymentsPreventConfirmationEmail' => array(
				'file' => 'TrustPaymentsPreventConfirmationEmail.ocmod.xml',
				'default_status' => 0
			),
			'TrustPaymentsEvents' => array(
				'file' => 'TrustPaymentsEvents.ocmod.xml',
				'default_status' => 1
			),
			'TrustPaymentsFrontendPdf' => array(
				'file' => 'TrustPaymentsFrontendPdf.ocmod.xml',
				'default_status' => 1
			),
			'TrustPaymentsTransactionView' => array(
				'file' => 'TrustPaymentsTransactionView.ocmod.xml',
				'default_status' => 1
			)
		);
	}
	public static function wrapJobLabels(\Registry $registry, $content){
		return $content;
	}

	public static function getPersistableSetting($value, $default){
		if ($value) {
			$value = $value['value'];
		}
		else {
			$value = $default;
		}
		return $value;
	}

	public static function getTemplate($theme, $template){
	    if (file_exists(DIR_TEMPLATE . $theme . '/template/' . $template . ".tpl")) {
	        return $theme . '/template/' . $template . ".tpl";
	    }
	    else if (file_exists(DIR_TEMPLATE . $template . ".tpl")) {
	    	return $template . ".tpl";
	    }
	    else {
	        return 'default/template/' . $template . ".tpl";
	    }
	}
	
	public static function newTax(\Registry $registry) {
		return new \Tax($registry);
	}
	
	public static function getSessionTotals(\Registry $registry){
		// Totals
		$registry->get('load')->model('extension/extension');
		
		$totals = array();
		$taxes = $registry->get('cart')->getTaxes();
		$total = 0;
		
		$sort_order = array();
		
		$results = $registry->get('model_extension_extension')->getExtensions('total');
		
		foreach ($results as $key => $value) {
			$sort_order[$key] = $registry->get('config')->get($value['code'] . '_sort_order');
		}
		
		array_multisort($sort_order, SORT_ASC, $results);
		
		foreach ($results as $result) {
			if ($registry->get('config')->get($result['code'] . '_status')) {
				$registry->get('load')->model('total/' . $result['code']);
				
				// We have to put the totals in an array so that they pass by reference.
				$registry->get('model_total_' . $result['code'])->getTotal($totals, $total, $taxes);
			}
			
			$sort_order = array();
			
			foreach ($totals as $key => $value) {
				$sort_order[$key] = $value['sort_order'];
			}
			
			array_multisort($sort_order, SORT_ASC, $totals);
		}
		
		return $totals;
	}
	
	public static function persistPluginStatus(\Registry $registry, array $post) {
	}
	
	public static function extractPaymentSettingCode($code) {
		return $code;
	}
	
	public static function extractLanguageDirectory($language){
		return $language['directory'];
	}
	
	public static function createUrl(Url $url_provider, $route, $query, $ssl){
		if (is_array($query)) {
			$query = http_build_query($query);
		}
		else if (!is_string($query)) {
			throw new Exception("Query must be of type string or array, " . get_class($query) . " given.");
		}
		return $url_provider->link($route, $query, $ssl);
	}
}