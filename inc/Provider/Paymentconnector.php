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
 * Provider of payment connector information from the gateway.
 */
class VRPaymentProviderPaymentconnector extends VRPaymentProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('vrpayment_connectors');
    }

    /**
     * Returns the payment connector by the given id.
     *
     * @param int $id
     * @return \VRPayment\Sdk\Model\PaymentConnector
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of payment connectors.
     *
     * @return \VRPayment\Sdk\Model\PaymentConnector[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $connectorService = new \VRPayment\Sdk\Service\PaymentConnectorService(
            VRPaymentHelper::getApiClient()
        );
        return $connectorService->all();
    }

    protected function getId($entry)
    {
        /* @var \VRPayment\Sdk\Model\PaymentConnector $entry */
        return $entry->getId();
    }
}
