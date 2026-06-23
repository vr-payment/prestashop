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

use VRPayment\Sdk\Model\TransactionLineItemVersionCreate;

/**
 * This service provides functions to deal with VR Payment transaction completions.
 */
class VRPaymentServiceTransactioncompletion extends VRPaymentServiceAbstract
{

    /**
     * The transaction completion API service.
     *
     * @var \VRPayment\Sdk\Service\TransactionCompletionService
     */
    private $completionService;

    public function executeCompletion($order)
    {
        $currentCompletionJob = null;
        try {
            VRPaymentHelper::startDBTransaction();
            $transactionInfo = VRPaymentHelper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Could not load corresponding transaction.',
                        'transactioncompletion'
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

            if ($transactionInfo->getState() != \VRPayment\Sdk\Model\TransactionState::AUTHORIZED) {
                throw new Exception(
                    VRPaymentHelper::getModuleInstance()->l(
                        'The transaction is not in a state to be completed.',
                        'transactioncompletion'
                    )
                );
            }

            if (VRPaymentModelCompletionjob::isCompletionRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Please wait until the existing completion is processed.',
                        'transactioncompletion'
                    )
                );
            }

            if (VRPaymentModelVoidjob::isVoidRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    VRPaymentHelper::getModuleInstance()->l(
                        'There is a void in process. The order can not be completed.',
                        'transactioncompletion'
                    )
                );
            }

            $completionJob = new VRPaymentModelCompletionjob();
            $completionJob->setSpaceId($spaceId);
            $completionJob->setTransactionId($transactionId);
            $completionJob->setState(VRPaymentModelCompletionjob::STATE_CREATED);
            $completionJob->setOrderId(
                VRPaymentHelper::getOrderMeta($order, 'vRPaymentMainOrderId')
            );
            $completionJob->save();
            $currentCompletionJob = $completionJob->getId();
            VRPaymentHelper::commitDBTransaction();
        } catch (Exception $e) {
            VRPaymentHelper::rollbackDBTransaction();
            throw $e;
        }

        try {
            $this->updateLineItems($currentCompletionJob);
            $this->sendCompletion($currentCompletionJob);
        } catch (Exception $e) {
            throw $e;
        }
    }

    protected function updateLineItems($completionJobId)
    {
        $completionJob = new VRPaymentModelCompletionjob($completionJobId);
        VRPaymentHelper::startDBTransaction();
        VRPaymentHelper::lockByTransactionId(
            $completionJob->getSpaceId(),
            $completionJob->getTransactionId()
        );
        // Reload completion job;
        $completionJob = new VRPaymentModelCompletionjob($completionJobId);

        if ($completionJob->getState() != VRPaymentModelCompletionjob::STATE_CREATED) {
            // Already updated in the meantime
            VRPaymentHelper::rollbackDBTransaction();
            return;
        }
        try {
            $baseOrder = new Order($completionJob->getOrderId());
            $collected = $baseOrder->getBrother()->getResults();
            $collected[] = $baseOrder;

            $lineItems = VRPaymentServiceLineitem::instance()->getItemsFromOrders($collected);

	        $lineItemVersion = (new TransactionLineItemVersionCreate())
			  ->setTransaction((int)$completionJob->getTransactionId())
			  ->setLineItems($lineItems)
			  ->setExternalId(uniqid());
			
            VRPaymentServiceTransaction::instance()->updateLineItems(
                $completionJob->getSpaceId(),
                $lineItemVersion
            );
            $completionJob->setState(VRPaymentModelCompletionjob::STATE_ITEMS_UPDATED);
            $completionJob->save();
            VRPaymentHelper::commitDBTransaction();
        } catch (\VRPayment\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \VRPayment\Sdk\Model\ClientError) {
                $completionJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            VRPaymentHelper::getModuleInstance()->l(
                                'Could not update the line items. Error: %s',
                                'transactioncompletion'
                            ),
                            VRPaymentHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $completionJob->setState(VRPaymentModelCompletionjob::STATE_FAILURE);
                $completionJob->save();
                VRPaymentHelper::commitDBTransaction();
            } else {
                $completionJob->save();
                VRPaymentHelper::commitDBTransaction();
                $message = sprintf(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Error updating line items for completion job with id %d: %s',
                        'transactioncompletion'
                    ),
                    $completionJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'VRPaymentModelCompletionjob');
                throw $e;
            }
        } catch (Exception $e) {
            $completionJob->save();
            VRPaymentHelper::commitDBTransaction();
            $message = sprintf(
                VRPaymentHelper::getModuleInstance()->l(
                    'Error updating line items for completion job with id %d: %s',
                    'transactioncompletion'
                ),
                $completionJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'VRPaymentModelCompletionjob');
            throw $e;
        }
    }

    protected function sendCompletion($completionJobId)
    {
        $completionJob = new VRPaymentModelCompletionjob($completionJobId);
        VRPaymentHelper::startDBTransaction();
        VRPaymentHelper::lockByTransactionId(
            $completionJob->getSpaceId(),
            $completionJob->getTransactionId()
        );
        // Reload completion job;
        $completionJob = new VRPaymentModelCompletionjob($completionJobId);

        if ($completionJob->getState() != VRPaymentModelCompletionjob::STATE_ITEMS_UPDATED) {
            // Already sent in the meantime
            VRPaymentHelper::rollbackDBTransaction();
            return;
        }
        try {
            $completion = $this->getCompletionService()->completeOnline(
                $completionJob->getSpaceId(),
                $completionJob->getTransactionId()
            );
            $completionJob->setCompletionId($completion->getId());
            $completionJob->setState(VRPaymentModelCompletionjob::STATE_SENT);
            $completionJob->save();
            VRPaymentHelper::commitDBTransaction();
        } catch (\VRPayment\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \VRPayment\Sdk\Model\ClientError) {
                $completionJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            VRPaymentHelper::getModuleInstance()->l(
                                'Could not send the completion to %s. Error: %s',
                                'transactioncompletion'
                            ),
                            'VR Payment',
                            VRPaymentHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $completionJob->setState(VRPaymentModelCompletionjob::STATE_FAILURE);
                $completionJob->save();
                VRPaymentHelper::commitDBTransaction();
            } else {
                $completionJob->save();
                VRPaymentHelper::commitDBTransaction();
                $message = sprintf(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Error sending completion job with id %d: %s',
                        'transactioncompletion'
                    ),
                    $completionJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'VRPaymentModelCompletionjob');
                throw $e;
            }
        } catch (Exception $e) {
            $completionJob->save();
            VRPaymentHelper::commitDBTransaction();
            $message = sprintf(
                VRPaymentHelper::getModuleInstance()->l(
                    'Error sending completion job with id %d: %s',
                    'transactioncompletion'
                ),
                $completionJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'VRPaymentModelCompletionjob');
            throw $e;
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = VRPaymentHelper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $completionJob = VRPaymentModelCompletionjob::loadRunningCompletionForTransaction(
            $spaceId,
            $transactionId
        );
        $this->updateLineItems($completionJob->getId());
        $this->sendCompletion($completionJob->getId());
    }

    public function updateCompletions($endTime = null)
    {
        $toProcess = VRPaymentModelCompletionjob::loadNotSentJobIds();
        foreach ($toProcess as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->updateLineItems($id);
                $this->sendCompletion($id);
            } catch (Exception $e) {
                $message = sprintf(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Error updating completion job with id %d: %s',
                        'transactioncompletion'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'VRPaymentModelCompletionjob');
            }
        }
    }

    public function hasPendingCompletions()
    {
        $toProcess = VRPaymentModelCompletionjob::loadNotSentJobIds();
        return ! empty($toProcess);
    }

    /**
     * Returns the transaction completion API service.
     *
     * @return \VRPayment\Sdk\Service\TransactionCompletionService
     */
    protected function getCompletionService()
    {
        if ($this->completionService == null) {
            $this->completionService = new \VRPayment\Sdk\Service\TransactionCompletionService(
                VRPaymentHelper::getApiClient()
            );
        }
        return $this->completionService;
    }
}
