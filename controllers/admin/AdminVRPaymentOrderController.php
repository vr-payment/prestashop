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

class AdminVRPaymentOrderController extends ModuleAdminController
{
    public function postProcess()
    {
        parent::postProcess();
        exit();
    }

    public function initProcess()
    {
        parent::initProcess();
        $access = Profile::getProfileAccess(
            $this->context->employee->id_profile,
            (int) Tab::getIdFromClassName('AdminOrders')
        );
        if ($access['edit'] === '1' && ($action = Tools::getValue('action'))) {
            $this->action = $action;
        } else {
            echo json_encode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l(
                        'You do not have permission to edit the order.',
                        'adminvrpaymentordercontroller'
                    )
                )
            );
            die();
        }
    }

    public function ajaxProcessUpdateOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                VRPaymentServiceTransactioncompletion::instance()->updateForOrder($order);
                VRPaymentServiceTransactioncompletion::instance()->updateForOrder($order);
                echo json_encode(array(
                    'success' => 'true'
                ));
                die();
            } catch (Exception $e) {
                echo json_encode(array(
                    'success' => 'false',
                    'message' => $e->getMessage()
                ));
                die();
            }
        } else {
            echo json_encode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.', 'adminvrpaymentordercontroller')
                )
            );
            die();
        }
    }

    public function ajaxProcessVoidOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                VRPaymentServiceTransactionvoid::instance()->executeVoid($order);
                echo json_encode(
                    array(
                        'success' => 'true',
                        'message' => $this->module->l(
                            'The order is updated automatically once the void is processed.',
                            'adminvrpaymentordercontroller'
                        )
                    )
                );
                die();
            } catch (Exception $e) {
                echo json_encode(
                    array(
                        'success' => 'false',
                        'message' => VRPaymentHelper::cleanExceptionMessage($e->getMessage())
                    )
                );
                die();
            }
        } else {
            echo json_encode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.', 'adminvrpaymentordercontroller')
                )
            );
            die();
        }
    }

    public function ajaxProcessCompleteOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                VRPaymentServiceTransactioncompletion::instance()->executeCompletion($order);
                echo json_encode(
                    array(
                        'success' => 'true',
                        'message' => $this->module->l(
                            'The order is updated automatically once the completion is processed.',
                            'adminvrpaymentordercontroller'
                        )
                    )
                );
                die();
            } catch (Exception $e) {
                echo json_encode(
                    array(
                        'success' => 'false',
                        'message' => VRPaymentHelper::cleanExceptionMessage($e->getMessage())
                    )
                );
                die();
            }
        } else {
            echo json_encode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.', 'adminvrpaymentordercontroller')
                )
            );
            die();
        }
    }
}
