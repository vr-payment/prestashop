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

class AdminVRPaymentDocumentsController extends ModuleAdminController
{
    public function postProcess()
    {
        parent::postProcess();
        // We want to be sure that displaying PDF is the last thing this controller will do
        exit();
    }

    public function initProcess()
    {
        parent::initProcess();
        $access = Profile::getProfileAccess(
            $this->context->employee->id_profile,
            (int) Tab::getIdFromClassName('AdminOrders')
        );
        if ($access['view'] === '1' && ($action = Tools::getValue('action'))) {
            $this->action = $action;
        } else {
            die(
                Tools::displayError(
                    $this->module->l(
                        'You do not have permission to view this.',
                        'adminvrpaymentdocumentscontroller'
                    )
                )
            );
        }
    }

    public function processVRPaymentInvoice()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                VRPaymentDownloadhelper::downloadInvoice($order);
            } catch (Exception $e) {
                die(
                    Tools::displayError(
                        $this->module->l(
                            'Could not fetch the document.',
                            'adminvrpaymentdocumentscontroller'
                        )
                    )
                );
            }
        } else {
            die(
                Tools::displayError(
                    $this->module->l('The order Id is missing.', 'adminvrpaymentdocumentscontroller')
                )
            );
        }
    }

    public function processVRPaymentPackingSlip()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                VRPaymentDownloadhelper::downloadPackingSlip($order);
            } catch (Exception $e) {
                die(
                    Tools::displayError(
                        $this->module->l(
                            'Could not fetch the document.',
                            'adminvrpaymentdocumentscontroller'
                        )
                    )
                );
            }
        } else {
            die(
                Tools::displayError(
                    $this->module->l('The order Id is missing.', 'adminvrpaymentdocumentscontroller')
                )
            );
        }
    }
}
