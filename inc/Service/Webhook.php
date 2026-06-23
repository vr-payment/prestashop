<?php
/**
 * VR Payment Prestashop
 *
 * This Prestashop module enables to process payments with VR Payment (https://www.vr-payment.de/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * This service handles webhooks.
 */
class VRPaymentServiceWebhook extends VRPaymentServiceAbstract
{

    /**
     * The webhook listener API service.
     *
     * @var \VRPayment\Sdk\Service\WebhookListenerService
     */
    private $webhookListenerService;

    /**
     * The webhook url API service.
     *
     * @var \VRPayment\Sdk\Service\WebhookUrlService
     */
    private $webhookUrlService;

    private $webhookEntities = array();

    /**
     * Constructor to register the webhook entites.
     */
    public function __construct()
    {
        $this->webhookEntities[1487165678181] = new VRPaymentWebhookEntity(
            1487165678181,
            'Manual Task',
            array(
                \VRPayment\Sdk\Model\ManualTaskState::DONE,
                \VRPayment\Sdk\Model\ManualTaskState::EXPIRED,
                \VRPayment\Sdk\Model\ManualTaskState::OPEN
            ),
            'VRPaymentWebhookManualtask'
        );
        $this->webhookEntities[1472041857405] = new VRPaymentWebhookEntity(
            1472041857405,
            'Payment Method Configuration',
            array(
                \VRPayment\Sdk\Model\CreationEntityState::ACTIVE,
                \VRPayment\Sdk\Model\CreationEntityState::DELETED,
                \VRPayment\Sdk\Model\CreationEntityState::DELETING,
                \VRPayment\Sdk\Model\CreationEntityState::INACTIVE
            ),
            'VRPaymentWebhookMethodconfiguration',
            true
        );
        $this->webhookEntities[1472041829003] = new VRPaymentWebhookEntity(
            1472041829003,
            'Transaction',
            array(
                \VRPayment\Sdk\Model\TransactionState::AUTHORIZED,
                \VRPayment\Sdk\Model\TransactionState::DECLINE,
                \VRPayment\Sdk\Model\TransactionState::FAILED,
                \VRPayment\Sdk\Model\TransactionState::FULFILL,
                \VRPayment\Sdk\Model\TransactionState::VOIDED,
                \VRPayment\Sdk\Model\TransactionState::COMPLETED
            ),
            'VRPaymentWebhookTransaction'
        );
        $this->webhookEntities[1472041819799] = new VRPaymentWebhookEntity(
            1472041819799,
            'Delivery Indication',
            array(
                \VRPayment\Sdk\Model\DeliveryIndicationState::MANUAL_CHECK_REQUIRED
            ),
            'VRPaymentWebhookDeliveryindication'
        );

        $this->webhookEntities[1472041831364] = new VRPaymentWebhookEntity(
            1472041831364,
            'Transaction Completion',
            array(
                \VRPayment\Sdk\Model\TransactionCompletionState::FAILED,
                \VRPayment\Sdk\Model\TransactionCompletionState::SUCCESSFUL
            ),
            'VRPaymentWebhookTransactioncompletion'
        );

        $this->webhookEntities[1472041867364] = new VRPaymentWebhookEntity(
            1472041867364,
            'Transaction Void',
            array(
                \VRPayment\Sdk\Model\TransactionVoidState::FAILED,
                \VRPayment\Sdk\Model\TransactionVoidState::SUCCESSFUL
            ),
            'VRPaymentWebhookTransactionvoid'
        );

        $this->webhookEntities[1472041839405] = new VRPaymentWebhookEntity(
            1472041839405,
            'Refund',
            array(
                \VRPayment\Sdk\Model\RefundState::FAILED,
                \VRPayment\Sdk\Model\RefundState::SUCCESSFUL
            ),
            'VRPaymentWebhookRefund'
        );
        $this->webhookEntities[1472041806455] = new VRPaymentWebhookEntity(
            1472041806455,
            'Token',
            array(
                \VRPayment\Sdk\Model\CreationEntityState::ACTIVE,
                \VRPayment\Sdk\Model\CreationEntityState::DELETED,
                \VRPayment\Sdk\Model\CreationEntityState::DELETING,
                \VRPayment\Sdk\Model\CreationEntityState::INACTIVE
            ),
            'VRPaymentWebhookToken'
        );
        $this->webhookEntities[1472041811051] = new VRPaymentWebhookEntity(
            1472041811051,
            'Token Version',
            array(
                \VRPayment\Sdk\Model\TokenVersionState::ACTIVE,
                \VRPayment\Sdk\Model\TokenVersionState::OBSOLETE
            ),
            'VRPaymentWebhookTokenversion'
        );
    }

    /**
     * Installs the necessary webhooks in VR Payment.
     */
    public function install()
    {
        $spaceIds = array();
        foreach (Shop::getShops(true, null, true) as $shopId) {
            $spaceId = Configuration::get(VRPaymentBasemodule::CK_SPACE_ID, null, null, $shopId);
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $webhookUrl = $this->getWebhookUrl($spaceId);
                if ($webhookUrl == null) {
                    $webhookUrl = $this->createWebhookUrl($spaceId);
                }
                $existingListeners = $this->getWebhookListeners($spaceId, $webhookUrl);
                foreach ($this->webhookEntities as $webhookEntity) {
                    /* @var VRPaymentWebhookEntity $webhookEntity */
                    $exists = false;
                    foreach ($existingListeners as $existingListener) {
                        if ($existingListener->getEntity() == $webhookEntity->getId()) {
                            $exists = true;
                        }
                    }
                    if (! $exists) {
                        $this->createWebhookListener($webhookEntity, $spaceId, $webhookUrl);
                    }
                }
                $spaceIds[] = $spaceId;
            }
        }
    }

    /**
     *
     * @param int|string $id
     * @return VRPaymentWebhookEntity
     */
    public function getWebhookEntityForId($id)
    {
        if (isset($this->webhookEntities[$id])) {
            return $this->webhookEntities[$id];
        }
        return null;
    }

    /**
     * Create a webhook listener.
     *
     * @param VRPaymentWebhookEntity $entity
     * @param int $spaceId
     * @param \VRPayment\Sdk\Model\WebhookUrl $webhookUrl
     * @return \VRPayment\Sdk\Model\WebhookListenerCreate
     */
    protected function createWebhookListener(
        VRPaymentWebhookEntity $entity,
        $spaceId,
        \VRPayment\Sdk\Model\WebhookUrl $webhookUrl
    ) {
        $webhookListener = new \VRPayment\Sdk\Model\WebhookListenerCreate();
        $webhookListener->setEntity($entity->getId());
        $webhookListener->setEntityStates($entity->getStates());
        $webhookListener->setName('Prestashop ' . $entity->getName());
        $webhookListener->setState(\VRPayment\Sdk\Model\CreationEntityState::ACTIVE);
        $webhookListener->setUrl($webhookUrl->getId());
        $webhookListener->setNotifyEveryChange($entity->isNotifyEveryChange());
        $webhookListener->setEnablePayloadSignatureAndState(true);

        return $this->getWebhookListenerService()->create($spaceId, $webhookListener);
    }

    /**
     * Returns the existing webhook listeners.
     *
     * @param int $spaceId
     * @param \VRPayment\Sdk\Model\WebhookUrl $webhookUrl
     * @return \VRPayment\Sdk\Model\WebhookListener[]
     */
    protected function getWebhookListeners($spaceId, \VRPayment\Sdk\Model\WebhookUrl $webhookUrl)
    {
        $query = new \VRPayment\Sdk\Model\EntityQuery();
        $filter = new \VRPayment\Sdk\Model\EntityQueryFilter();
        $filter->setType(\VRPayment\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('state', \VRPayment\Sdk\Model\CreationEntityState::ACTIVE),
                $this->createEntityFilter('url.id', $webhookUrl->getId())
            )
        );
        $query->setFilter($filter);
        return $this->getWebhookListenerService()->search($spaceId, $query);
    }

    /**
     * Creates a webhook url.
     *
     * @param int $spaceId
     * @return \VRPayment\Sdk\Model\WebhookUrlCreate
     */
    protected function createWebhookUrl($spaceId)
    {
        $webhookUrl = new \VRPayment\Sdk\Model\WebhookUrlCreate();
        $webhookUrl->setUrl($this->getUrl());
        $webhookUrl->setState(\VRPayment\Sdk\Model\CreationEntityState::ACTIVE);
        $webhookUrl->setName('Prestashop');
        return $this->getWebhookUrlService()->create($spaceId, $webhookUrl);
    }

    /**
     * Returns the existing webhook url if there is one.
     *
     * @param int $spaceId
     * @return \VRPayment\Sdk\Model\WebhookUrl
     */
    protected function getWebhookUrl($spaceId)
    {
        $query = new \VRPayment\Sdk\Model\EntityQuery();
        $filter = new \VRPayment\Sdk\Model\EntityQueryFilter();
        $filter->setType(\VRPayment\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('state', \VRPayment\Sdk\Model\CreationEntityState::ACTIVE),
                $this->createEntityFilter('url', $this->getUrl())
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $result = $this->getWebhookUrlService()->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            return null;
        }
    }

    /**
     * Returns the webhook endpoint URL.
     *
     * @return string
     */
    protected function getUrl()
    {
        $link = Context::getContext()->link;

        $shopIds = Shop::getShops(true, null, true);
        asort($shopIds);
        $shopId = reset($shopIds);

        $languageIds = Language::getLanguages(true, $shopId, true);
        asort($languageIds);
        $languageId = reset($languageIds);

        $url = $link->getModuleLink('vrpayment', 'webhook', array(), true, $languageId, $shopId);
        // We have to parse the link, because of issue http://forge.prestashop.com/browse/BOOM-5799
        $urlQuery = parse_url($url, PHP_URL_QUERY) ?? '';
        if (stripos($urlQuery, 'controller=module') !== false && stripos($urlQuery, 'controller=webhook') !== false) {
            $url = str_replace('controller=module', 'fc=module', $url);
        }
        return $url;
    }

    /**
     * Returns the webhook listener API service.
     *
     * @return \VRPayment\Sdk\Service\WebhookListenerService
     */
    protected function getWebhookListenerService()
    {
        if ($this->webhookListenerService == null) {
            $this->webhookListenerService = new \VRPayment\Sdk\Service\WebhookListenerService(
                VRPaymentHelper::getApiClient()
            );
        }
        return $this->webhookListenerService;
    }

    /**
     * Returns the webhook url API service.
     *
     * @return \VRPayment\Sdk\Service\WebhookUrlService
     */
    protected function getWebhookUrlService()
    {
        if ($this->webhookUrlService == null) {
            $this->webhookUrlService = new \VRPayment\Sdk\Service\WebhookUrlService(
                VRPaymentHelper::getApiClient()
            );
        }
        return $this->webhookUrlService;
    }
}
