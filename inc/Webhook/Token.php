<?php
/**
 * VR Payment Prestashop
 *
 * This Prestashop module enables to process payments with VR Payment (https://www.vr-payment.de/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Webhook processor to handle token state transitions.
 */
class VRPaymentWebhookToken extends VRPaymentWebhookAbstract
{
    public function process(VRPaymentWebhookRequest $request)
    {
        $tokenService = VRPaymentServiceToken::instance();
        $tokenService->updateToken($request->getSpaceId(), $request->getEntityId());
    }
}
