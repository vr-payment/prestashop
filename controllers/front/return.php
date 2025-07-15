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

class VRPaymentReturnModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     *
     * @see FrontController::initContent()
     */
    public function postProcess()
    {
        $orderId = Tools::getValue('order_id', null);
        $orderKey = Tools::getValue('secret', null);
        $action = Tools::getValue('action', null);

        if ($orderId != null) {
            $order = new Order($orderId);
            if ($orderKey == null || $orderKey != VRPaymentHelper::computeOrderSecret($order)) {
                $error = Tools::displayError('Invalid Secret.');
                die($error);
            }
            switch ($action) {
                case 'success':
                    $this->processSuccess($order);

                    return;
                case 'failure':
                    self::processFailure($order);

                    return;
                default:
            }
        }
        $error = Tools::displayError('Invalid Request.');
        die($error);
    }

    private function processSuccess(Order $order)
    {
        $transactionService = VRPaymentServiceTransaction::instance();
        $transactionService->waitForTransactionState(
            $order,
            array(
                \VRPayment\Sdk\Model\TransactionState::CONFIRMED,
                \VRPayment\Sdk\Model\TransactionState::PENDING,
                \VRPayment\Sdk\Model\TransactionState::PROCESSING
            ),
            5
        );
        $cartId = $order->id_cart;
        $customer = new Customer($order->id_customer);

        $this->redirect_after = $this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            array(
                'id_cart' => $cartId,
                'id_module' => $this->module->id,
                'id_order' => $order->id,
                'key' => $customer->secure_key
            )
        );
    }

    private function processFailure(Order $order)
    {
        $transactionService = VRPaymentServiceTransaction::instance();
        $transactionService->waitForTransactionState(
            $order,
            array(
                \VRPayment\Sdk\Model\TransactionState::FAILED
            ),
            5
        );
        $transaction = VRPaymentModelTransactioninfo::loadByOrderId($order->id);

        $userFailureMessage = $transaction->getUserFailureMessage();

        if (empty($userFailureMessage)) {
            $failureReason = $transaction->getFailureReason();

            if ($failureReason !== null) {
                $userFailureMessage = VRPaymentHelper::translate($failureReason);
            }
        }
        if (! empty($userFailureMessage)) {
            $this->context->cookie->vrp_error = $userFailureMessage;
        }
        
        $this->setCurrentState($order);

        //the new state should be saved here to override the stock issues of incrementing or decrementing items when going through the webhooks
        //by calling the setCurrentState function more than once without saving.
        $order->save();

        // Set cart to cookie
        $originalCartId = VRPaymentHelper::getOrderMeta($order, 'originalCart');
        if (! empty($originalCartId)) {
            $this->context->cookie->id_cart = $originalCartId;
        }

        $this->redirect_after = $this->context->link->getPageLink('order', true, null, "step=3");
    }

    public function setMedia()
    {
        // We do not need styling here
    }
    
    /**
     * @param Order $order
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function setCurrentState(Order $order): void
    {
        $newState = Configuration::get(VRPaymentBasemodule::CK_STATUS_FAILED);
        $history = new OrderHistory();
        $history->id_order = $order->id;
        $history->id_order_state = $newState;
        $history->id_employee = (int)Context::getContext()->employee->id;
        $history->date_add = date('Y-m-d H:i:s');
        $history->add();
        
        $order->current_state = $newState;
    }

    protected function displayMaintenancePage()
    {
        // We never display the maintenance page.
    }

    protected function displayRestrictedCountryPage()
    {
        // We do not want to restrict the content by any country.
    }

    protected function canonicalRedirection($canonical_url = '')
    {
        // We do not need any canonical redirect
    }
}
