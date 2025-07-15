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
 * Provider of label descriptor information from the gateway.
 */
class VRPaymentProviderLabeldescription extends VRPaymentProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('vrpayment_label_description');
    }

    /**
     * Returns the label descriptor by the given code.
     *
     * @param int $id
     * @return \VRPayment\Sdk\Model\LabelDescriptor
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of label descriptors.
     *
     * @return \VRPayment\Sdk\Model\LabelDescriptor[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $labelDescriptorService = new \VRPayment\Sdk\Service\LabelDescriptionService(
            VRPaymentHelper::getApiClient()
        );
        return $labelDescriptorService->all();
    }

    protected function getId($entry)
    {
        /* @var \VRPayment\Sdk\Model\LabelDescriptor $entry */
        return $entry->getId();
    }
}
