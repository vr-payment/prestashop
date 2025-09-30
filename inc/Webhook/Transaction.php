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
 * Webhook processor to handle transaction state transitions.
 */
class VRPaymentWebhookTransaction extends VRPaymentWebhookOrderrelatedabstract
{

    /**
     *
     * @see VRPaymentWebhookOrderrelatedabstract::loadEntity()
     * @return \VRPayment\Sdk\Model\Transaction
     */
    protected function loadEntity(VRPaymentWebhookRequest $request)
    {
        $transactionService = new \VRPayment\Sdk\Service\TransactionService(
            VRPaymentHelper::getApiClient()
        );
        return $transactionService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($transaction)
    {
        /* @var \VRPayment\Sdk\Model\Transaction $transaction */
        return $transaction->getMerchantReference();
    }

    protected function getTransactionId($transaction)
    {
        /* @var \VRPayment\Sdk\Model\Transaction $transaction */
        return $transaction->getId();
    }

    protected function processOrderRelatedInner(Order $order, $transaction)
    {
        /* @var \VRPayment\Sdk\Model\Transaction $transaction */
        $transactionInfo = VRPaymentModelTransactioninfo::loadByOrderId($order->id);
        if ($transaction->getState() != $transactionInfo->getState()) {
            switch ($transaction->getState()) {
                case \VRPayment\Sdk\Model\TransactionState::AUTHORIZED:
                    $this->authorize($transaction, $order);
                    break;
                case \VRPayment\Sdk\Model\TransactionState::DECLINE:
                    $this->decline($transaction, $order);
                    break;
                case \VRPayment\Sdk\Model\TransactionState::FAILED:
                    $this->failed($transaction, $order);
                    break;
                case \VRPayment\Sdk\Model\TransactionState::FULFILL:
                    $this->authorize($transaction, $order);
                    $this->fulfill($transaction, $order);
                    break;
                case \VRPayment\Sdk\Model\TransactionState::VOIDED:
                    $this->voided($transaction, $order);
                    break;
                case \VRPayment\Sdk\Model\TransactionState::COMPLETED:
                    $this->waiting($transaction, $order);
                    break;
                default:
                    // Nothing to do.
                    break;
            }
        }
    }

    protected function authorize(\VRPayment\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (VRPaymentHelper::getOrderMeta($sourceOrder, 'authorized')) {
            return;
        }
        // Do not send emails for this status update
        VRPaymentBasemodule::startRecordingMailMessages();
        VRPaymentHelper::updateOrderMeta($sourceOrder, 'authorized', true);
        $authorizedStatusId = Configuration::get(VRPaymentBasemodule::CK_STATUS_AUTHORIZED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($authorizedStatusId);
            $order->save();
        }
        VRPaymentBasemodule::stopRecordingMailMessages();
        if (Configuration::get(VRPaymentBasemodule::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            // Send stored messages
            $messages = VRPaymentHelper::getOrderEmails($sourceOrder);
            if (count($messages) > 0) {
                if (method_exists('Mail', 'sendMailMessageWithoutHook')) {
                    foreach ($messages as $message) {
                        Mail::sendMailMessageWithoutHook($message, false);
                    }
                }
            }
        }
        VRPaymentHelper::deleteOrderEmails($order);
        // Cleanup carts
        $originalCartId = VRPaymentHelper::getOrderMeta($order, 'originalCart');
        if (! empty($originalCartId)) {
            $cart = new Cart($originalCartId);
            $cart->delete();
        }
        VRPaymentServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function waiting(\VRPayment\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        VRPaymentBasemodule::startRecordingMailMessages();
        $waitingStatusId = Configuration::get(VRPaymentBasemodule::CK_STATUS_COMPLETED);
        if (! VRPaymentHelper::getOrderMeta($sourceOrder, 'manual_check')) {
            $orders = $sourceOrder->getBrother();
            $orders[] = $sourceOrder;
            foreach ($orders as $order) {
                $order->setCurrentState($waitingStatusId);
                $order->save();
            }
        }
        VRPaymentBasemodule::stopRecordingMailMessages();
        VRPaymentServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function decline(\VRPayment\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (! Configuration::get(VRPaymentBasemodule::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            // Do not send email
            VRPaymentBasemodule::startRecordingMailMessages();
        }

        $canceledStatusId = Configuration::get(VRPaymentBasemodule::CK_STATUS_DECLINED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($canceledStatusId);
            $order->save();
        }
        VRPaymentBasemodule::stopRecordingMailMessages();
        VRPaymentServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function failed(\VRPayment\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        // Do not send email
        VRPaymentBasemodule::startRecordingMailMessages();
        $errorStatusId = Configuration::get(VRPaymentBasemodule::CK_STATUS_FAILED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($errorStatusId);
            $order->save();
        }
        VRPaymentBasemodule::stopRecordingMailMessages();
        VRPaymentHelper::deleteOrderEmails($sourceOrder);
        VRPaymentServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function fulfill(\VRPayment\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (! Configuration::get(VRPaymentBasemodule::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            // Do not send email
            VRPaymentBasemodule::startRecordingMailMessages();
        }
        $payedStatusId = Configuration::get(VRPaymentBasemodule::CK_STATUS_FULFILL);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($payedStatusId);
            if (empty($order->invoice_date) || $order->invoice_date == '0000-00-00 00:00:00') {
                // Make sure invoice date is set, otherwise prestashop ignores the order in the statistics
                $order->invoice_date = date('Y-m-d H:i:s');
            }
            $order->save();
        }
        VRPaymentBasemodule::stopRecordingMailMessages();
        VRPaymentServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function voided(\VRPayment\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (! Configuration::get(VRPaymentBasemodule::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            // Do not send email
            VRPaymentBasemodule::startRecordingMailMessages();
        }
        $canceledStatusId = Configuration::get(VRPaymentBasemodule::CK_STATUS_VOIDED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($canceledStatusId);
            $order->save();
        }
        VRPaymentBasemodule::stopRecordingMailMessages();
        VRPaymentServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }
}
