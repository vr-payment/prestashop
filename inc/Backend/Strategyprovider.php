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
 * This provider allows to create a VRPayment_ShopRefund_IStrategy.
 * The implementation of
 * the strategy depends on the actual prestashop version.
 */
class VRPaymentBackendStrategyprovider
{
    /**
     * Returns the refund strategy to use
     *
     * @return VRPaymentBackendIstrategy
     */
    public static function getStrategy()
    {
        return new VRPaymentBackendDefaultstrategy();
    }
}
