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
 * This service provides functions to deal with VR Payment refunds.
 */
class VRPaymentServiceRefund extends VRPaymentServiceAbstract
{
    private static $refundableStates = array(
        \VRPayment\Sdk\Model\TransactionState::COMPLETED,
        \VRPayment\Sdk\Model\TransactionState::DECLINE,
        \VRPayment\Sdk\Model\TransactionState::FULFILL
    );

    /**
     * The refund API service.
     *
     * @var \VRPayment\Sdk\Service\RefundService
     */
    private $refundService;

    /**
     * Returns the refund by the given external id.
     *
     * @param int $spaceId
     * @param string $externalId
     * @return \VRPayment\Sdk\Model\Refund
     */
    public function getRefundByExternalId($spaceId, $externalId)
    {
        $query = new \VRPayment\Sdk\Model\EntityQuery();
        $query->setFilter($this->createEntityFilter('externalId', $externalId));
        $query->setNumberOfEntities(1);
        $result = $this->getRefundService()->search($spaceId, $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            throw new Exception('The refund could not be found.');
        }
    }

    public function executeRefund(Order $order, array $parsedParameters)
    {
        $currentRefundJob = null;
        try {
            VRPaymentHelper::startDBTransaction();
            $transactionInfo = VRPaymentHelper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Could not load corresponding transaction',
                        'refund'
                    )
                );
            }

            VRPaymentHelper::lockByTransactionId(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            );
            // Reload after locking
            $transactionInfo = VRPaymentModelTransactioninfo::loadByTransaction(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            );
            $spaceId = $transactionInfo->getSpaceId();
            $transactionId = $transactionInfo->getTransactionId();

            if (! in_array($transactionInfo->getState(), self::$refundableStates)) {
                throw new Exception(
                    VRPaymentHelper::getModuleInstance()->l(
                        'The transaction is not in a state to be refunded.',
                        'refund'
                    )
                );
            }

            if (VRPaymentModelRefundjob::isRefundRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Please wait until the existing refund is processed.',
                        'refund'
                    )
                );
            }

            $refundJob = new VRPaymentModelRefundjob();
            $refundJob->setState(VRPaymentModelRefundjob::STATE_CREATED);
            $refundJob->setOrderId($order->id);
            $refundJob->setSpaceId($transactionInfo->getSpaceId());
            $refundJob->setTransactionId($transactionInfo->getTransactionId());
            $refundJob->setExternalId(uniqid($order->id . '-'));
            $refundJob->setRefundParameters($parsedParameters);
            $refundJob->save();
            // validate Refund Job
            $this->createRefundObject($refundJob);
            $currentRefundJob = $refundJob->getId();
            VRPaymentHelper::commitDBTransaction();
        } catch (Exception $e) {
            VRPaymentHelper::rollbackDBTransaction();
            throw $e;
        }
        $this->sendRefund($currentRefundJob);
    }

    protected function sendRefund($refundJobId)
    {
        $refundJob = new VRPaymentModelRefundjob($refundJobId);
        VRPaymentHelper::startDBTransaction();
        VRPaymentHelper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
        // Reload refund job;
        $refundJob = new VRPaymentModelRefundjob($refundJobId);
        if ($refundJob->getState() != VRPaymentModelRefundjob::STATE_CREATED) {
            // Already sent in the meantime
            VRPaymentHelper::rollbackDBTransaction();
            return;
        }
        try {
            $executedRefund = $this->refund($refundJob->getSpaceId(), $this->createRefundObject($refundJob));
            $refundJob->setState(VRPaymentModelRefundjob::STATE_SENT);
            $refundJob->setRefundId($executedRefund->getId());

            if ($executedRefund->getState() == \VRPayment\Sdk\Model\RefundState::PENDING) {
                $refundJob->setState(VRPaymentModelRefundjob::STATE_PENDING);
            }
            $refundJob->save();
            VRPaymentHelper::commitDBTransaction();
        } catch (\VRPayment\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \VRPayment\Sdk\Model\ClientError) {
                $refundJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            VRPaymentHelper::getModuleInstance()->l(
                                'Could not send the refund to %s. Error: %s',
                                'refund'
                            ),
                            'VR Payment',
                            VRPaymentHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $refundJob->setState(VRPaymentModelRefundjob::STATE_FAILURE);
                $refundJob->save();
                VRPaymentHelper::commitDBTransaction();
            } else {
                $refundJob->save();
                VRPaymentHelper::commitDBTransaction();
                $message = sprintf(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Error sending refund job with id %d: %s',
                        'refund'
                    ),
                    $refundJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'VRPaymentModelRefundjob');
                throw $e;
            }
        } catch (Exception $e) {
            $refundJob->save();
            VRPaymentHelper::commitDBTransaction();
            $message = sprintf(
                VRPaymentHelper::getModuleInstance()->l('Error sending refund job with id %d: %s', 'refund'),
                $refundJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'VRPaymentModelRefundjob');
            throw $e;
        }
    }

    public function applyRefundToShop($refundJobId)
    {
        $refundJob = new VRPaymentModelRefundjob($refundJobId);
        VRPaymentHelper::startDBTransaction();
        VRPaymentHelper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
        // Reload refund job;
        $refundJob = new VRPaymentModelRefundjob($refundJobId);
        if ($refundJob->getState() != VRPaymentModelRefundjob::STATE_APPLY) {
            // Already processed in the meantime
            VRPaymentHelper::rollbackDBTransaction();
            return;
        }
        try {
            $order = new Order($refundJob->getOrderId());
            $strategy = VRPaymentBackendStrategyprovider::getStrategy();
            $appliedData = $strategy->applyRefund($order, $refundJob->getRefundParameters());
            $refundJob->setState(VRPaymentModelRefundjob::STATE_SUCCESS);
            $refundJob->save();
            VRPaymentHelper::commitDBTransaction();
            try {
                $strategy->afterApplyRefundActions($order, $refundJob->getRefundParameters(), $appliedData);
            } catch (Exception $e) {
                // We ignore errors in the after apply actions
            }
        } catch (Exception $e) {
            VRPaymentHelper::rollbackDBTransaction();
            VRPaymentHelper::startDBTransaction();
            VRPaymentHelper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
            $refundJob = new VRPaymentModelRefundjob($refundJobId);
            $refundJob->increaseApplyTries();
            if ($refundJob->getApplyTries() > 3) {
                $refundJob->setState(VRPaymentModelRefundjob::STATE_FAILURE);
                $refundJob->setFailureReason(array(
                    'en-US' => sprintf($e->getMessage())
                ));
            }
            $refundJob->save();
            VRPaymentHelper::commitDBTransaction();
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = VRPaymentHelper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $refundJob = VRPaymentModelRefundjob::loadRunningRefundForTransaction($spaceId, $transactionId);
        if ($refundJob->getState() == VRPaymentModelRefundjob::STATE_CREATED) {
            $this->sendRefund($refundJob->getId());
        } elseif ($refundJob->getState() == VRPaymentModelRefundjob::STATE_APPLY) {
            $this->applyRefundToShop($refundJob->getId());
        }
    }

    public function updateRefunds($endTime = null)
    {
        $toSend = VRPaymentModelRefundjob::loadNotSentJobIds();
        foreach ($toSend as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->sendRefund($id);
            } catch (Exception $e) {
                $message = sprintf(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Error updating refund job with id %d: %s',
                        'refund'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'VRPaymentModelRefundjob');
            }
        }
        $toApply = VRPaymentModelRefundjob::loadNotAppliedJobIds();
        foreach ($toApply as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->applyRefundToShop($id);
            } catch (Exception $e) {
                $message = sprintf(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Error applying refund job with id %d: %s',
                        'refund'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'VRPaymentModelRefundjob');
            }
        }
    }

    public function hasPendingRefunds()
    {
        $toSend = VRPaymentModelRefundjob::loadNotSentJobIds();
        $toApply = VRPaymentModelRefundjob::loadNotAppliedJobIds();
        return ! empty($toSend) || ! empty($toApply);
    }

    /**
     * Creates a refund request model for the given parameters.
     *
     * @param Order $order
     * @param array $refund
     *            Refund data to be determined
     * @return \VRPayment\Sdk\Model\RefundCreate
     */
    protected function createRefundObject(VRPaymentModelRefundjob $refundJob)
    {
        $order = new Order($refundJob->getOrderId());

        $strategy = VRPaymentBackendStrategyprovider::getStrategy();

        $spaceId = $refundJob->getSpaceId();
        $transactionId = $refundJob->getTransactionId();
        $externalRefundId = $refundJob->getExternalId();
        $parsedData = $refundJob->getRefundParameters();
        $amount = $strategy->getRefundTotal($parsedData);
        $type = $strategy->getVRPaymentRefundType($parsedData);

        $reductions = $strategy->createReductions($order, $parsedData);
        $reductions = $this->fixReductions($amount, $spaceId, $transactionId, $reductions);

        $remoteRefund = new \VRPayment\Sdk\Model\RefundCreate();
        $remoteRefund->setExternalId($externalRefundId);
        $remoteRefund->setReductions($reductions);
        $remoteRefund->setTransaction($transactionId);
        $remoteRefund->setType($type);

        return $remoteRefund;
    }

    /**
     * Returns the fixed line item reductions for the refund.
     *
     * If the amount of the given reductions does not match the refund's grand total, the amount to refund is
     * distributed equally to the line items.
     *
     * @param float $refundTotal
     * @param int $spaceId
     * @param int $transactionId
     * @param \VRPayment\Sdk\Model\LineItemReductionCreate[] $reductions
     * @return \VRPayment\Sdk\Model\LineItemReductionCreate[]
     */
    protected function fixReductions($refundTotal, $spaceId, $transactionId, array $reductions)
    {
        $baseLineItems = $this->getBaseLineItems($spaceId, $transactionId);
        $reductionAmount = VRPaymentHelper::getReductionAmount($baseLineItems, $reductions);

        $configuration = VRPaymentVersionadapter::getConfigurationInterface();
        $computePrecision = $configuration->get('_PS_PRICE_COMPUTE_PRECISION_');

        if (Tools::ps_round($refundTotal, $computePrecision) != Tools::ps_round($reductionAmount, $computePrecision)) {
            $fixedReductions = array();
            $baseAmount = VRPaymentHelper::getTotalAmountIncludingTax($baseLineItems);
            $rate = $refundTotal / $baseAmount;
            foreach ($baseLineItems as $lineItem) {
                $reduction = new \VRPayment\Sdk\Model\LineItemReductionCreate();
                $reduction->setLineItemUniqueId($lineItem->getUniqueId());
                $reduction->setQuantityReduction(0);
                $reduction->setUnitPriceReduction(
                    round($lineItem->getAmountIncludingTax() * $rate / $lineItem->getQuantity(), 8)
                );
                $fixedReductions[] = $reduction;
            }

            return $fixedReductions;
        } else {
            return $reductions;
        }
    }

    /**
     * Sends the refund to the gateway.
     *
     * @param int $spaceId
     * @param \VRPayment\Sdk\Model\RefundCreate $refund
     * @return \VRPayment\Sdk\Model\Refund
     */
    public function refund($spaceId, \VRPayment\Sdk\Model\RefundCreate $refund)
    {
        return $this->getRefundService()->refund($spaceId, $refund);
    }

    /**
     * Returns the line items that are to be used to calculate the refund.
     *
     * This returns the line items of the latest refund if there is one or else of the completed transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \VRPayment\Sdk\Model\Refund $refund
     * @return \VRPayment\Sdk\Model\LineItem[]
     */
    protected function getBaseLineItems($spaceId, $transactionId, \VRPayment\Sdk\Model\Refund $refund = null)
    {
        $lastSuccessfulRefund = $this->getLastSuccessfulRefund($spaceId, $transactionId, $refund);
        if ($lastSuccessfulRefund) {
            return $lastSuccessfulRefund->getReducedLineItems();
        } else {
            return $this->getTransactionInvoice($spaceId, $transactionId)->getLineItems();
        }
    }

    /**
     * Returns the transaction invoice for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @throws Exception
     * @return \VRPayment\Sdk\Model\TransactionInvoice
     */
    protected function getTransactionInvoice($spaceId, $transactionId)
    {
        $query = new \VRPayment\Sdk\Model\EntityQuery();

        $filter = new \VRPayment\Sdk\Model\EntityQueryFilter();
        $filter->setType(\VRPayment\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter(
                    'state',
                    \VRPayment\Sdk\Model\TransactionInvoiceState::CANCELED,
                    \VRPayment\Sdk\Model\CriteriaOperator::NOT_EQUALS
                ),
                $this->createEntityFilter('completion.lineItemVersion.transaction.id', $transactionId)
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $invoiceService = new \VRPayment\Sdk\Service\TransactionInvoiceService(
            VRPaymentHelper::getApiClient()
        );
        $result = $invoiceService->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            throw new Exception('The transaction invoice could not be found.');
        }
    }

    /**
     * Returns the last successful refund of the given transaction, excluding the given refund.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \VRPayment\Sdk\Model\Refund $refund
     * @return \VRPayment\Sdk\Model\Refund
     */
    protected function getLastSuccessfulRefund(
        $spaceId,
        $transactionId,
        \VRPayment\Sdk\Model\Refund $refund = null
    ) {
        $query = new \VRPayment\Sdk\Model\EntityQuery();

        $filter = new \VRPayment\Sdk\Model\EntityQueryFilter();
        $filter->setType(\VRPayment\Sdk\Model\EntityQueryFilterType::_AND);
        $filters = array(
            $this->createEntityFilter('state', \VRPayment\Sdk\Model\RefundState::SUCCESSFUL),
            $this->createEntityFilter('transaction.id', $transactionId)
        );
        if ($refund != null) {
            $filters[] = $this->createEntityFilter(
                'id',
                $refund->getId(),
                \VRPayment\Sdk\Model\CriteriaOperator::NOT_EQUALS
            );
        }

        $filter->setChildren($filters);
        $query->setFilter($filter);

        $query->setOrderBys(
            array(
                $this->createEntityOrderBy('createdOn', \VRPayment\Sdk\Model\EntityQueryOrderByType::DESC)
            )
        );
        $query->setNumberOfEntities(1);

        $result = $this->getRefundService()->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * Returns the refund API service.
     *
     * @return \VRPayment\Sdk\Service\RefundService
     */
    protected function getRefundService()
    {
        if ($this->refundService == null) {
            $this->refundService = new \VRPayment\Sdk\Service\RefundService(
                VRPaymentHelper::getApiClient()
            );
        }

        return $this->refundService;
    }
}
