<style>
.panel-heading {
	background-color: initial;
}

.route-checkout-checkout .panel-heading:hover {
	background-color: initial;
}

.trustpayments-container {
	padding: 15px;
}

#trustpayments-iframe-spinner {
	display: none;
	margin: auto;
}
</style>
<div class="panel panel-default">
	<div class="panel-heading">
		<h4><?php echo $text_payment_title; ?></h4>
		<span><?php echo $text_further_details; ?></span>
	</div>
	<div class="trustpayments-container">
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
    $('#journal-checkout-confirm-button').attr('disabled', 'disabled');
    if(typeof Window.trustpaymentsTimeout !== 'undefined') {
        clearTimeout(Window.trustpaymentsTimeout);
    }
	function initAddressUpdates() {
		if(typeof window.addressUpdateEventsSet === 'undefined') {
			window.addressUpdateEventsSet = true;
			function registerAddressUpdates(selector) {
				let fullSelector = 'input[name^="' + selector + '_"]';
				var addressUpdateTimerId;
				$(fullSelector).on('change', function() {
					if(addressUpdateTimerId) {
						clearTimeout(addressUpdateTimerId);
					}
					addressUpdateTimerId = setTimeout(updateAddress, 1000, selector);
				});
				$(fullSelector).on('input', function() {
					if(addressUpdateTimerId) {
						clearTimeout(addressUpdateTimerId);
						addressUpdateTimerId = null;
					}
				});
			}
			
			function updateAddress(selector) {
				let fullSelector = 'input[name^="' + selector + '_"]';
			      $.ajax({
			        cache: false,
			        url: 'index.php?route=extension/trustpayments/address/update&' + $(fullSelector).serialize(),
			        type: 'get',
			        dataType: 'html'
			      }).always(
			      	function() {$(document).trigger('journal_checkout_reload_' + selector); }
			      );
			}
			
			registerAddressUpdates('payment');
			registerAddressUpdates('shipping');
		}
	}
    function initTrustPaymentsIframe(){
        jQuery('#trustpayments-iframe-spinner').css("display", "block");
        if(typeof jQuery === 'undefined' || typeof TrustPayments === 'undefined') {
            Window.trustpaymentsTimeout = setTimeout(initTrustPaymentsIframe, 500);
        } else {
            TrustPayments.init('<?php echo $configuration_id; ?>');
            initAddressUpdates();
        }
    }
    var checked = $('[data-trustpayments-original-checked=true]').val();
    if(checked && checked.startsWith('trustpayments_')) {
        if (typeof Window.loadCounter === 'undefined') {
            Window.loadCounter = 1;
        } else if (Window.loadCounter == 1) {
            Window.loadCounter++;
        } else if (Window.loadCounter == 2) {
            jQuery().ready(initTrustPaymentsIframe);
        }
    } else {
        jQuery().ready(initTrustPaymentsIframe);
    }
    </script>
</div>
