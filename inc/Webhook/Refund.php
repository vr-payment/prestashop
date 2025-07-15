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
 * Webhook processor to handle refund state transitions.
 */
class VRPaymentWebhookRefund extends VRPaymentWebhookOrderrelatedabstract
{

    /**
     * Processes the received order related webhook request.
     *
     * @param VRPaymentWebhookRequest $request
     */
    public function process(VRPaymentWebhookRequest $request)
    {
        parent::process($request);
        $refund = $this->loadEntity($request);
        $refundJob = VRPaymentModelRefundjob::loadByExternalId(
            $refund->getLinkedSpaceId(),
            $refund->getExternalId()
        );
        if ($refundJob->getState() == VRPaymentModelRefundjob::STATE_APPLY) {
            VRPaymentServiceRefund::instance()->applyRefundToShop($refundJob->getId());
        }
    }

    /**
     *
     * @see VRPaymentWebhookOrderrelatedabstract::loadEntity()
     * @return \VRPayment\Sdk\Model\Refund
     */
    protected function loadEntity(VRPaymentWebhookRequest $request)
    {
        $refundService = new \VRPayment\Sdk\Service\RefundService(
            VRPaymentHelper::getApiClient()
        );
        return $refundService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($refund)
    {
        /* @var \VRPayment\Sdk\Model\Refund $refund */
        return $refund->getTransaction()->getMerchantReference();
    }

    protected function getTransactionId($refund)
    {
        /* @var \VRPayment\Sdk\Model\Refund $refund */
        return $refund->getTransaction()->getId();
    }

    protected function processOrderRelatedInner(Order $order, $refund)
    {
        /* @var \VRPayment\Sdk\Model\Refund $refund */
        switch ($refund->getState()) {
            case \VRPayment\Sdk\Model\RefundState::FAILED:
                $this->failed($refund, $order);
                break;
            case \VRPayment\Sdk\Model\RefundState::SUCCESSFUL:
                $this->refunded($refund, $order);
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    protected function failed(\VRPayment\Sdk\Model\Refund $refund, Order $order)
    {
        $refundJob = VRPaymentModelRefundjob::loadByExternalId(
            $refund->getLinkedSpaceId(),
            $refund->getExternalId()
        );
        if ($refundJob->getId()) {
            $refundJob->setState(VRPaymentModelRefundjob::STATE_FAILURE);
            $refundJob->setRefundId($refund->getId());
            if ($refund->getFailureReason() != null) {
                $refundJob->setFailureReason($refund->getFailureReason()
                    ->getDescription());
            }
            $refundJob->save();
        }
    }

    protected function refunded(\VRPayment\Sdk\Model\Refund $refund, Order $order)
    {
        $refundJob = VRPaymentModelRefundjob::loadByExternalId(
            $refund->getLinkedSpaceId(),
            $refund->getExternalId()
        );
        if ($refundJob->getId()) {
            $refundJob->setState(VRPaymentModelRefundjob::STATE_APPLY);
            $refundJob->setRefundId($refund->getId());
            $refundJob->save();
        }
    }
}
