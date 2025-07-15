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
 * This service provides functions to deal with VR Payment tokens.
 */
class VRPaymentServiceToken extends VRPaymentServiceAbstract
{

    /**
     * The token API service.
     *
     * @var \VRPayment\Sdk\Service\TokenService
     */
    private $tokenService;

    /**
     * The token version API service.
     *
     * @var \VRPayment\Sdk\Service\TokenVersionService
     */
    private $tokenVersionService;

    public function updateTokenVersion($spaceId, $tokenVersionId)
    {
        $tokenVersion = $this->getTokenVersionService()->read($spaceId, $tokenVersionId);
        $this->updateInfo($spaceId, $tokenVersion);
    }

    public function updateToken($spaceId, $tokenId)
    {
        $query = new \VRPayment\Sdk\Model\EntityQuery();
        $filter = new \VRPayment\Sdk\Model\EntityQueryFilter();
        $filter->setType(\VRPayment\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('token.id', $tokenId),
                $this->createEntityFilter('state', \VRPayment\Sdk\Model\CreationEntityState::ACTIVE)
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $tokenVersions = $this->getTokenVersionService()->search($spaceId, $query);
        if (! empty($tokenVersions)) {
            $this->updateInfo($spaceId, current($tokenVersions));
        } else {
            $info = VRPaymentModelTokeninfo::loadByToken($spaceId, $tokenId);
            if ($info->getId()) {
                $info->delete();
            }
        }
    }

    protected function updateInfo($spaceId, \VRPayment\Sdk\Model\TokenVersion $tokenVersion)
    {
        $info = VRPaymentModelTokeninfo::loadByToken($spaceId, $tokenVersion->getToken()->getId());
        if (! in_array(
            $tokenVersion->getToken()->getState(),
            array(
                \VRPayment\Sdk\Model\CreationEntityState::ACTIVE,
                \VRPayment\Sdk\Model\CreationEntityState::INACTIVE
            )
        )) {
            if ($info->getId()) {
                $info->delete();
            }
            return;
        }

        $info->setCustomerId($tokenVersion->getToken()
            ->getCustomerId());
        $info->setName($tokenVersion->getName());

        $info->setPaymentMethodId(
            $tokenVersion->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration()
                ->getId()
        );
        $info->setConnectorId($tokenVersion->getPaymentConnectorConfiguration()
            ->getConnector());

        $info->setSpaceId($spaceId);
        $info->setState($tokenVersion->getToken()
            ->getState());
        $info->setTokenId($tokenVersion->getToken()
            ->getId());
        $info->save();
    }

    public function deleteToken($spaceId, $tokenId)
    {
        $this->getTokenService()->delete($spaceId, $tokenId);
    }

    /**
     * Returns the token API service.
     *
     * @return \VRPayment\Sdk\Service\TokenService
     */
    protected function getTokenService()
    {
        if ($this->tokenService == null) {
            $this->tokenService = new \VRPayment\Sdk\Service\TokenService(
                VRPaymentHelper::getApiClient()
            );
        }

        return $this->tokenService;
    }

    /**
     * Returns the token version API service.
     *
     * @return \VRPayment\Sdk\Service\TokenVersionService
     */
    protected function getTokenVersionService()
    {
        if ($this->tokenVersionService == null) {
            $this->tokenVersionService = new \VRPayment\Sdk\Service\TokenVersionService(
                VRPaymentHelper::getApiClient()
            );
        }

        return $this->tokenVersionService;
    }
}
