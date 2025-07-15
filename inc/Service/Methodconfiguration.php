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
 * VRPayment_Service_Method_Configuration Class.
 */
class VRPaymentServiceMethodconfiguration extends VRPaymentServiceAbstract
{

    /**
     * Updates the data of the payment method configuration.
     *
     * @param \VRPayment\Sdk\Model\PaymentMethodConfiguration $configuration
     */
    public function updateData(\VRPayment\Sdk\Model\PaymentMethodConfiguration $configuration)
    {
        $entities = VRPaymentModelMethodconfiguration::loadByConfiguration(
            $configuration->getLinkedSpaceId(),
            $configuration->getId()
        );
        foreach ($entities as $entity) {
            if ($this->hasChanged($configuration, $entity)) {
                $entity->setConfigurationName($configuration->getName());
                $entity->setState($this->getConfigurationState($configuration));
                $entity->setTitle($configuration->getResolvedTitle());
                $entity->setDescription($configuration->getResolvedDescription());
                $entity->setImage($this->getResourcePath($configuration->getResolvedImageUrl()));
                $entity->setImageBase($this->getResourceBase($configuration->getResolvedImageUrl()));
                $entity->setSortOrder($configuration->getSortOrder());
                $entity->save();
            }
        }
    }

    private function hasChanged(
        \VRPayment\Sdk\Model\PaymentMethodConfiguration $configuration,
        VRPaymentModelMethodconfiguration $entity
    ) {
        if ($configuration->getName() != $entity->getConfigurationName()) {
            return true;
        }

        if ($this->getConfigurationState($configuration) != $entity->getState()) {
            return true;
        }

        if ($configuration->getSortOrder() != $entity->getSortOrder()) {
            return true;
        }

        if ($configuration->getResolvedTitle() != $entity->getTitle()) {
            return true;
        }

        if ($configuration->getResolvedDescription() != $entity->getDescription()) {
            return true;
        }

        $image = $this->getResourcePath($configuration->getResolvedImageUrl());
        if ($image != $entity->getImage()) {
            return true;
        }

        $imageBase = $this->getResourceBase($configuration->getResolvedImageUrl());
        if ($imageBase != $entity->getImageBase()) {
            return true;
        }

        return false;
    }

    /**
     * Synchronizes the payment method configurations from VR Payment.
     */
    public function synchronize()
    {
        $existingFound = array();

        $existingConfigurations = VRPaymentModelMethodconfiguration::loadAll();

        $spaceIdCache = array();

        $paymentMethodConfigurationService = new \VRPayment\Sdk\Service\PaymentMethodConfigurationService(
            VRPaymentHelper::getApiClient()
        );

        foreach (Shop::getShops(true, null, true) as $shopId) {
            $spaceId = Configuration::get(VRPaymentBasemodule::CK_SPACE_ID, null, null, $shopId);

            if ($spaceId) {
                if (! array_key_exists($spaceId, $spaceIdCache)) {
                    $spaceIdCache[$spaceId] = $paymentMethodConfigurationService->search(
                        $spaceId,
                        new \VRPayment\Sdk\Model\EntityQuery()
                    );
                }
                $configurations = $spaceIdCache[$spaceId];
                foreach ($configurations as $configuration) {
                    $method = VRPaymentModelMethodconfiguration::loadByConfigurationAndShop(
                        $spaceId,
                        $configuration->getId(),
                        $shopId
                    );
                    if ($method->getId() !== null) {
                        $existingFound[] = $method->getId();
                    }
                    $method->setShopId($shopId);
                    $method->setSpaceId($spaceId);
                    $method->setConfigurationId($configuration->getId());
                    $method->setConfigurationName($configuration->getName());
                    $method->setState($this->getConfigurationState($configuration));
                    $method->setTitle($configuration->getResolvedTitle());
                    $method->setDescription($configuration->getResolvedDescription());
                    $method->setImage($this->getResourcePath($configuration->getResolvedImageUrl()));
                    $method->setImageBase($this->getResourceBase($configuration->getResolvedImageUrl()));
                    $method->setSortOrder($configuration->getSortOrder());
                    $method->save();
                }
            }
        }
        foreach ($existingConfigurations as $existingConfiguration) {
            if (! in_array($existingConfiguration->getId(), $existingFound)) {
                $existingConfiguration->setState(VRPaymentModelMethodconfiguration::STATE_HIDDEN);
                $existingConfiguration->save();
            }
        }
        Cache::clean('vrpayment_methods');
    }

    /**
     * Returns the payment method for the given id.
     *
     * @param int $id
     * @return \VRPayment\Sdk\Model\PaymentMethod
     */
    protected function getPaymentMethod($id)
    {
        /* @var VRPayment_Provider_Payment_Method */
        $methodProvider = VRPaymentProviderPaymentmethod::instance();
        return $methodProvider->find($id);
    }

    /**
     * Returns the state for the payment method configuration.
     *
     * @param \VRPayment\Sdk\Model\PaymentMethodConfiguration $configuration
     * @return string
     */
    protected function getConfigurationState(\VRPayment\Sdk\Model\PaymentMethodConfiguration $configuration)
    {
        switch ($configuration->getState()) {
            case \VRPayment\Sdk\Model\CreationEntityState::ACTIVE:
                return VRPaymentModelMethodconfiguration::STATE_ACTIVE;
            case \VRPayment\Sdk\Model\CreationEntityState::INACTIVE:
                return VRPaymentModelMethodconfiguration::STATE_INACTIVE;
            default:
                return VRPaymentModelMethodconfiguration::STATE_HIDDEN;
        }
    }
}
