<?php
namespace TrustPayments\Webhook;

/**
 * Webhook processor to handle token version state transitions.
 */
class TokenVersion extends AbstractWebhook {

	public function process(Request $request){
		$token_service = \TrustPayments\Service\Token::instance($this->registry);
		$token_service->updateTokenVersion($request->getSpaceId(), $request->getEntityId());
	}
}