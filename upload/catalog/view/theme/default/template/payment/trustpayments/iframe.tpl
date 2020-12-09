<div class="panel panel-default">
	<div class="panel-heading">
		<h4><?php echo $text_payment_title; ?></h4>
		<span><?php echo $text_further_details; ?></span>
	</div>
	<div style="padding: 15px;">
		<div id="trustpayments-iframe-spinner" class="text-center">
			<i style="font-size: 12em;" class='fa fa-spinner fa-spin '></i>
		</div>
		<div id="trustpayments-iframe-container" class="text-center"
			style="display: none;"></div>

		<div class="buttons" style="overflow: hidden;">
			<div class="pull-right">
				<input type="button" value="<?php echo $button_confirm; ?>"
					id="button-confirm" class="btn btn-primary"
					data-loading-text="<?php echo $text_loading; ?>" disabled />
			</div>
		</div>
	</div>
	<script type="text/javascript" src="<?php echo $external_js; ?>"></script>
	<script type="text/javascript" src="<?php echo $opencart_js; ?>"></script>
	<script type="text/javascript">
    function initTrustPaymentsIframe(){
    	if(typeof TrustPayments === 'undefined') {
    		Window.loadTrustPaymentsTimeout = setTimeout(initTrustPaymentsIframe, 500);
    	} else {
    		TrustPayments.init('<?php echo $configuration_id; ?>');
    	}
    }
    if(typeof Window.loadTrustPaymentsTimeout !== 'undefined') {
		clearTimeout(Window.loadTrustPaymentsTimeout);
    }
    jQuery().ready(initTrustPaymentsIframe);
    </script>
</div>