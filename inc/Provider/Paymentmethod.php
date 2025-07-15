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
 * Provider of payment method information from the gateway.
 */
class VRPaymentProviderPaymentmethod extends VRPaymentProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('vrpayment_methods');
    }

    /**
     * Returns the payment method by the given id.
     *
     * @param int $id
     * @return \VRPayment\Sdk\Model\PaymentMethod
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of payment methods.
     *
     * @return \VRPayment\Sdk\Model\PaymentMethod[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $methodService = new \VRPayment\Sdk\Service\PaymentMethodService(
            VRPaymentHelper::getApiClient()
        );
        return $methodService->all();
    }

    protected function getId($entry)
    {
        /* @var \VRPayment\Sdk\Model\PaymentMethod $entry */
        return $entry->getId();
    }
}
