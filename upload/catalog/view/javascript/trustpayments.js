(function($) {
	window.TrustPayments = {
		handler : null,
		methodConfigurationId : null,
		running : false,
		initCalls : 0,
		initMaxCalls : 10,
		confirmationButtonSources: ['#button-confirm', '#journal-checkout-confirm-button'],

		initialized : function() {
			$('#trustpayments-iframe-spinner').hide();
			$('#trustpayments-iframe-container').show();
			TrustPayments.enableConfirmButton();
			$('#button-confirm').click(function(event) {
				TrustPayments.handler.validate();
				TrustPayments.disableConfirmButton();
			});
		},

		fallback : function(methodConfigurationId) {
			TrustPayments.methodConfigurationId = methodConfigurationId;
			$('#button-confirm').click(TrustPayments.submit);
			$('#trustpayments-iframe-spinner').toggle();
			TrustPayments.enableConfirmButton();
		},
		
		reenable: function() {
			TrustPayments.enableConfirmButton();
			if($('html').hasClass('quick-checkout-page')) { // modifications do not work for js
				triggerLoadingOff();
			}
		},

		submit : function() {
			if (!TrustPayments.running) {
				TrustPayments.running = true;
				$.getJSON('index.php?route=payment/trustpayments_'
						+ TrustPayments.methodConfigurationId
						+ '/confirm', '', function(data, status, jqXHR) {
					if (data.status) {
						if(TrustPayments.handler) {
							TrustPayments.handler.submit();
						}
						else {
							window.location.assign(data.redirect);
						}
					}
					else {
						alert(data.message);
						TrustPayments.reenable();
					}
					TrustPayments.running = false;
				});
			}
		},

		validated : function(result) {
			if (result.success) {
				TrustPayments.submit();
			} else {
				TrustPayments.reenable();
				if(result.errors) {
					alert(result.errors.join(" "));
				}
			}
		},

		init : function(methodConfigurationId) {
			TrustPayments.initCalls++;
			TrustPayments.disableConfirmButton();
			if (typeof window.IframeCheckoutHandler === 'undefined') {
				if (TrustPayments.initCalls < TrustPayments.initMaxCalls) {
					setTimeout(function() {
						TrustPayments.init(methodConfigurationId);
					}, 500);
				} else {
					TrustPayments.fallback(methodConfigurationId);
				}
			} else {
				TrustPayments.methodConfigurationId = methodConfigurationId;
				TrustPayments.handler = window
						.IframeCheckoutHandler(methodConfigurationId);
				TrustPayments.handler
						.setInitializeCallback(this.initialized);
				TrustPayments.handler
					.setValidationCallback(this.validated);
				TrustPayments.handler
					.setEnableSubmitCallback(this.enableConfirmButton);
				TrustPayments.handler
					.setDisableSubmitCallback(this.disableConfirmButton);
				TrustPayments.handler
						.create('trustpayments-iframe-container');
			}
		},
		
		enableConfirmButton : function() {
			for(var i = 0; i < TrustPayments.confirmationButtonSources.length; i++) {
				var button = $(TrustPayments.confirmationButtonSources[i]);
				if(button.length) {
					button.removeAttr('disabled');
				}
			}
		},
		
		disableConfirmButton : function() {
			for(var i = 0; i < TrustPayments.confirmationButtonSources.length; i++) {
				var button = $(TrustPayments.confirmationButtonSources[i]);
				if(button.length) {
					button.attr('disabled', 'disabled');
				}
			}
		}
	}
})(jQuery);