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
 * Provider of label descriptor group information from the gateway.
 */
class VRPaymentProviderLabeldescriptiongroup extends VRPaymentProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('vrpayment_label_description_group');
    }

    /**
     * Returns the label descriptor group by the given code.
     *
     * @param int $id
     * @return \VRPayment\Sdk\Model\LabelDescriptorGroup
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of label descriptor groups.
     *
     * @return \VRPayment\Sdk\Model\LabelDescriptorGroup[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $labelDescriptorGroupService = new \VRPayment\Sdk\Service\LabelDescriptionGroupService(
            VRPaymentHelper::getApiClient()
        );
        return $labelDescriptorGroupService->all();
    }

    protected function getId($entry)
    {
        /* @var \VRPayment\Sdk\Model\LabelDescriptorGroup $entry */
        return $entry->getId();
    }
}
