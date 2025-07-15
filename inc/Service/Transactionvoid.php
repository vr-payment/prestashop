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
 * This service provides functions to deal with VR Payment transaction voids.
 */
class VRPaymentServiceTransactionvoid extends VRPaymentServiceAbstract
{

    /**
     * The transaction void API service.
     *
     * @var \VRPayment\Sdk\Service\TransactionVoidService
     */
    private $voidService;

    public function executeVoid($order)
    {
        $currentVoidId = null;
        try {
            VRPaymentHelper::startDBTransaction();
            $transactionInfo = VRPaymentHelper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Could not load corresponding transaction.',
                        'transactionvoid'
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
                        'The transaction is not in a state to be voided.',
                        'transactionvoid'
                    )
                );
            }
            if (VRPaymentModelVoidjob::isVoidRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Please wait until the existing void is processed.',
                        'transactionvoid'
                    )
                );
            }
            if (VRPaymentModelCompletionjob::isCompletionRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    VRPaymentHelper::getModuleInstance()->l(
                        'There is a completion in process. The order can not be voided.',
                        'transactionvoid'
                    )
                );
            }

            $voidJob = new VRPaymentModelVoidjob();
            $voidJob->setSpaceId($spaceId);
            $voidJob->setTransactionId($transactionId);
            $voidJob->setState(VRPaymentModelVoidjob::STATE_CREATED);
            $voidJob->setOrderId(
                VRPaymentHelper::getOrderMeta($order, 'vRPaymentMainOrderId')
            );
            $voidJob->save();
            $currentVoidId = $voidJob->getId();
            VRPaymentHelper::commitDBTransaction();
        } catch (Exception $e) {
            VRPaymentHelper::rollbackDBTransaction();
            throw $e;
        }
        $this->sendVoid($currentVoidId);
    }

    protected function sendVoid($voidJobId)
    {
        $voidJob = new VRPaymentModelVoidjob($voidJobId);
        VRPaymentHelper::startDBTransaction();
        VRPaymentHelper::lockByTransactionId($voidJob->getSpaceId(), $voidJob->getTransactionId());
        // Reload void job;
        $voidJob = new VRPaymentModelVoidjob($voidJobId);
        if ($voidJob->getState() != VRPaymentModelVoidjob::STATE_CREATED) {
            // Already sent in the meantime
            VRPaymentHelper::rollbackDBTransaction();
            return;
        }
        try {
            $void = $this->getVoidService()->voidOnline($voidJob->getSpaceId(), $voidJob->getTransactionId());
            $voidJob->setVoidId($void->getId());
            $voidJob->setState(VRPaymentModelVoidjob::STATE_SENT);
            $voidJob->save();
            VRPaymentHelper::commitDBTransaction();
        } catch (\VRPayment\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \VRPayment\Sdk\Model\ClientError) {
                $voidJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            VRPaymentHelper::getModuleInstance()->l(
                                'Could not send the void to %s. Error: %s',
                                'transactionvoid'
                            ),
                            'VR Payment',
                            VRPaymentHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $voidJob->setState(VRPaymentModelVoidjob::STATE_FAILURE);
                $voidJob->save();
                VRPaymentHelper::commitDBTransaction();
            } else {
                $voidJob->save();
                VRPaymentHelper::commitDBTransaction();
                $message = sprintf(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Error sending void job with id %d: %s',
                        'transactionvoid'
                    ),
                    $voidJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'VRPaymentModelVoidjob');
                throw $e;
            }
        } catch (Exception $e) {
            $voidJob->save();
            VRPaymentHelper::commitDBTransaction();
            $message = sprintf(
                VRPaymentHelper::getModuleInstance()->l(
                    'Error sending void job with id %d: %s',
                    'transactionvoid'
                ),
                $voidJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'VRPaymentModelVoidjob');
            throw $e;
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = VRPaymentHelper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $voidJob = VRPaymentModelVoidjob::loadRunningVoidForTransaction($spaceId, $transactionId);
        if ($voidJob->getState() == VRPaymentModelVoidjob::STATE_CREATED) {
            $this->sendVoid($voidJob->getId());
        }
    }

    public function updateVoids($endTime = null)
    {
        $toProcess = VRPaymentModelVoidjob::loadNotSentJobIds();

        foreach ($toProcess as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->sendVoid($id);
            } catch (Exception $e) {
                $message = sprintf(
                    VRPaymentHelper::getModuleInstance()->l(
                        'Error updating void job with id %d: %s',
                        'transactionvoid'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'VRPaymentModelVoidjob');
            }
        }
    }

    public function hasPendingVoids()
    {
        $toProcess = VRPaymentModelVoidjob::loadNotSentJobIds();
        return ! empty($toProcess);
    }

    /**
     * Returns the transaction void API service.
     *
     * @return \VRPayment\Sdk\Service\TransactionVoidService
     */
    protected function getVoidService()
    {
        if ($this->voidService == null) {
            $this->voidService = new \VRPayment\Sdk\Service\TransactionVoidService(
                VRPaymentHelper::getApiClient()
            );
        }

        return $this->voidService;
    }
}
