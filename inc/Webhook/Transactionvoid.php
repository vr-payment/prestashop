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
 * Webhook processor to handle transaction void state transitions.
 */
class VRPaymentWebhookTransactionvoid extends VRPaymentWebhookOrderrelatedabstract
{

    /**
     *
     * @see VRPaymentWebhookOrderrelatedabstract::loadEntity()
     * @return \VRPayment\Sdk\Model\TransactionVoid
     */
    protected function loadEntity(VRPaymentWebhookRequest $request)
    {
        $voidService = new \VRPayment\Sdk\Service\TransactionVoidService(
            VRPaymentHelper::getApiClient()
        );
        return $voidService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($void)
    {
        /* @var \VRPayment\Sdk\Model\TransactionVoid $void */
        return $void->getTransaction()->getMerchantReference();
    }

    protected function getTransactionId($void)
    {
        /* @var \VRPayment\Sdk\Model\TransactionVoid $void */
        return $void->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Order $order, $void)
    {
        /* @var \VRPayment\Sdk\Model\TransactionVoid $void */
        switch ($void->getState()) {
            case \VRPayment\Sdk\Model\TransactionVoidState::FAILED:
                $this->update($void, $order, false);
                break;
            case \VRPayment\Sdk\Model\TransactionVoidState::SUCCESSFUL:
                $this->update($void, $order, true);
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    protected function update(\VRPayment\Sdk\Model\TransactionVoid $void, Order $order, $success)
    {
        $voidJob = VRPaymentModelVoidjob::loadByVoidId($void->getLinkedSpaceId(), $void->getId());
        if (! $voidJob->getId()) {
            // We have no void job with this id -> the server could not store the id of the void after sending the
            // request. (e.g. connection issue or crash)
            // We only have on running void which was not yet processed successfully and use it as it should be the one
            // the webhook is for.
            $voidJob = VRPaymentModelVoidjob::loadRunningVoidForTransaction(
                $void->getLinkedSpaceId(),
                $void->getLinkedTransaction()
            );
            if (! $voidJob->getId()) {
                // void not initated in shop backend ignore
                return;
            }
            $voidJob->setVoidId($void->getId());
        }
        if ($success) {
            $voidJob->setState(VRPaymentModelVoidjob::STATE_SUCCESS);
        } else {
            if ($voidJob->getFailureReason() != null) {
                $voidJob->setFailureReason($void->getFailureReason()
                    ->getDescription());
            }
            $voidJob->setState(VRPaymentModelVoidjob::STATE_FAILURE);
        }
        $voidJob->save();
    }
}
