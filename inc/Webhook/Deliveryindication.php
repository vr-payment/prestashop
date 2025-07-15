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
 * Webhook processor to handle delivery indication state transitions.
 */
class VRPaymentWebhookDeliveryindication extends VRPaymentWebhookOrderrelatedabstract
{

    /**
     *
     * @see VRPaymentWebhookOrderrelatedabstract::loadEntity()
     * @return \VRPayment\Sdk\Model\DeliveryIndication
     */
    protected function loadEntity(VRPaymentWebhookRequest $request)
    {
        $deliveryIndicationService = new \VRPayment\Sdk\Service\DeliveryIndicationService(
            VRPaymentHelper::getApiClient()
        );
        return $deliveryIndicationService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($deliveryIndication)
    {
        /* @var \VRPayment\Sdk\Model\DeliveryIndication $deliveryIndication */
        return $deliveryIndication->getTransaction()->getMerchantReference();
    }

    protected function getTransactionId($deliveryIndication)
    {
        /* @var \VRPayment\Sdk\Model\DeliveryIndication $delivery_indication */
        return $deliveryIndication->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Order $order, $deliveryIndication)
    {
        /* @var \VRPayment\Sdk\Model\DeliveryIndication $deliveryIndication */
        switch ($deliveryIndication->getState()) {
            case \VRPayment\Sdk\Model\DeliveryIndicationState::MANUAL_CHECK_REQUIRED:
                $this->review($order);
                break;
            default:
                break;
        }
    }

    protected function review(Order $sourceOrder)
    {
        VRPaymentBasemodule::startRecordingMailMessages();
        $manualStatusId = Configuration::get(VRPaymentBasemodule::CK_STATUS_MANUAL);
        VRPaymentHelper::updateOrderMeta($sourceOrder, 'manual_check', true);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($manualStatusId);
            $order->save();
        }
        VRPaymentBasemodule::stopRecordingMailMessages();
    }
}
