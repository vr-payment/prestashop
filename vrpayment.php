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

if (!defined('_PS_VERSION_')) {
    exit();
}

use PrestaShop\PrestaShop\Core\Domain\Order\CancellationActionType;

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'vrpayment_autoloader.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'vrpayment-sdk' . DIRECTORY_SEPARATOR .
    'autoload.php');
class VRPayment extends PaymentModule
{

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name = 'vrpayment';
        $this->tab = 'payments_gateways';
        $this->author = 'wallee AG';
        $this->bootstrap = true;
        $this->need_instance = 0;
        $this->version = '1.0.11';
        $this->displayName = 'VR Payment';
        $this->description = $this->l('This PrestaShop module enables to process payments with %s.');
        $this->description = sprintf($this->description, 'VR Payment');
        $this->module_key = 'PrestaShop_ProductKey_V8';
        $this->ps_versions_compliancy = array(
            'min' => '8',
            'max' => _PS_VERSION_
        );
        parent::__construct();
        $this->confirmUninstall = sprintf(
            $this->l('Are you sure you want to uninstall the %s module?', 'abstractmodule'),
            'VR Payment'
        );

        // Remove Fee Item
        if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
            VRPaymentFeehelper::removeFeeSurchargeProductsFromCart($this->context->cart);
        }
        if (!empty($this->context->cookie->vrp_error)) {
            $errors = $this->context->cookie->vrp_error;
            if (is_string($errors)) {
                $this->context->controller->errors[] = $errors;
            } elseif (is_array($errors)) {
                foreach ($errors as $error) {
                    $this->context->controller->errors[] = $error;
                }
            }
            unset($_SERVER['HTTP_REFERER']); // To disable the back button in the error message
            $this->context->cookie->vrp_error = null;
        }
    }

    public function addError($error)
    {
        $this->_errors[] = $error;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function install()
    {
        if (!VRPaymentBasemodule::checkRequirements($this)) {
            return false;
        }
        if (!parent::install()) {
            return false;
        }
        return VRPaymentBasemodule::install($this);
    }

    public function uninstall()
    {
        return parent::uninstall() && VRPaymentBasemodule::uninstall($this);
    }

    public function installHooks()
    {
        return VRPaymentBasemodule::installHooks($this) && $this->registerHook('paymentOptions') &&
            $this->registerHook('actionFrontControllerSetMedia');
    }

    public function getBackendControllers()
    {
        return array(
            'AdminVRPaymentMethodSettings' => array(
                'parentId' => Tab::getIdFromClassName('AdminParentPayment'),
                'name' => 'VR Payment ' . $this->l('Payment Methods')
            ),
            'AdminVRPaymentDocuments' => array(
                'parentId' => -1, // No Tab in navigation
                'name' => 'VR Payment ' . $this->l('Documents')
            ),
            'AdminVRPaymentOrder' => array(
                'parentId' => -1, // No Tab in navigation
                'name' => 'VR Payment ' . $this->l('Order Management')
            ),
            'AdminVRPaymentCronJobs' => array(
                'parentId' => Tab::getIdFromClassName('AdminTools'),
                'name' => 'VR Payment ' . $this->l('CronJobs')
            )
        );
    }

    public function installConfigurationValues()
    {
        return VRPaymentBasemodule::installConfigurationValues();
    }

    public function uninstallConfigurationValues()
    {
        return VRPaymentBasemodule::uninstallConfigurationValues();
    }

    public function getContent()
    {
        $output = VRPaymentBasemodule::handleSaveAll($this);
        $output .= VRPaymentBasemodule::handleSaveApplication($this);
        $output .= VRPaymentBasemodule::handleSaveEmail($this);
        $output .= VRPaymentBasemodule::handleSaveIntegration($this);
        $output .= VRPaymentBasemodule::handleSaveCartRecreation($this);
        $output .= VRPaymentBasemodule::handleSaveFeeItem($this);
        $output .= VRPaymentBasemodule::handleSaveDownload($this);
        $output .= VRPaymentBasemodule::handleSaveSpaceViewId($this);
        $output .= VRPaymentBasemodule::handleSaveOrderStatus($this);
        $output .= VRPaymentBasemodule::handleSaveCronSettings($this);
        $output .= VRPaymentBasemodule::displayHelpButtons($this);
        return $output . VRPaymentBasemodule::displayForm($this);
    }

    public function getConfigurationForms()
    {
        return array(
            VRPaymentBasemodule::getEmailForm($this),
            VRPaymentBasemodule::getIntegrationForm($this),
            VRPaymentBasemodule::getCartRecreationForm($this),
            VRPaymentBasemodule::getFeeForm($this),
            VRPaymentBasemodule::getDocumentForm($this),
            VRPaymentBasemodule::getSpaceViewIdForm($this),
            VRPaymentBasemodule::getOrderStatusForm($this),
            VRPaymentBasemodule::getCronSettingsForm($this),
        );
    }

    public function getConfigurationValues()
    {
        return array_merge(
            VRPaymentBasemodule::getApplicationConfigValues($this),
            VRPaymentBasemodule::getEmailConfigValues($this),
            VRPaymentBasemodule::getIntegrationConfigValues($this),
            VRPaymentBasemodule::getCartRecreationConfigValues($this),
            VRPaymentBasemodule::getFeeItemConfigValues($this),
            VRPaymentBasemodule::getDownloadConfigValues($this),
            VRPaymentBasemodule::getSpaceViewIdConfigValues($this),
            VRPaymentBasemodule::getOrderStatusConfigValues($this),
            VRPaymentBasemodule::getCronSettingsConfigValues($this)
        );
    }

    public function getConfigurationKeys()
    {
        return VRPaymentBasemodule::getConfigurationKeys();
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!isset($params['cart']) || !($params['cart'] instanceof Cart)) {
            return;
        }
        $cart = $params['cart'];
        try {
            $possiblePaymentMethods = VRPaymentServiceTransaction::instance()->getPossiblePaymentMethods(
                $cart
            );
        } catch (VRPaymentExceptionInvalidtransactionamount $e) {
            PrestaShopLogger::addLog($e->getMessage() . " CartId: " . $cart->id, 2, null, 'VRPayment');
            $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $paymentOption->setCallToActionText(
                $this->l('There is an issue with your cart, some payment methods are not available.')
            );
            $paymentOption->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:vrpayment/views/templates/front/hook/amount_error.tpl'
                )
            );
            $paymentOption->setForm(
                $this->context->smarty->fetch(
                    'module:vrpayment/views/templates/front/hook/amount_error_form.tpl'
                )
            );
            $paymentOption->setModuleName($this->name . "-error");
            return array(
                $paymentOption
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage() . " CartId: " . $cart->id, 1, null, 'VRPayment');
            return array();
        }
        $shopId = $cart->id_shop;
        $language = Context::getContext()->language->language_code;
        $methods = array();
        foreach ($possiblePaymentMethods as $possible) {
            $methodConfiguration = VRPaymentModelMethodconfiguration::loadByConfigurationAndShop(
                $possible->getSpaceId(),
                $possible->getId(),
                $shopId
            );
            if (!$methodConfiguration->isActive()) {
                continue;
            }
            $methods[] = $methodConfiguration;
        }
        $result = array();

        $this->context->smarty->registerPlugin(
            'function',
            'vrpayment_clean_html',
            array(
                'VRPaymentSmartyfunctions',
                'cleanHtml'
            )
        );

        foreach (VRPaymentHelper::sortMethodConfiguration($methods) as $methodConfiguration) {
            $parameters = VRPaymentBasemodule::getParametersFromMethodConfiguration($this, $methodConfiguration, $cart, $shopId, $language);
            $parameters['priceDisplayTax'] = Group::getPriceDisplayMethod(Group::getCurrent()->id);
            $parameters['iframe'] = $cart->iframe;
            $parameters['orderUrl'] = $this->context->link->getModuleLink(
                'vrpayment',
                'order',
                array(),
                true
            );
            $this->context->smarty->assign($parameters);
            $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $paymentOption->setCallToActionText($parameters['name']);
            $paymentOption->setLogo($parameters['image']);
            $paymentOption->setAction($parameters['link']);
            $paymentOption->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:vrpayment/views/templates/front/hook/payment_additional.tpl'
                )
            );
            $paymentOption->setForm(
                $this->context->smarty->fetch(
                    'module:vrpayment/views/templates/front/hook/payment_form.tpl'
                )
            );
            $paymentOption->setModuleName($this->name);
            $result[] = $paymentOption;
        }
        return $result;
    }

    public function hookActionFrontControllerSetMedia($arr)
    {
        if ($this->context->controller->php_self == 'order' || $this->context->controller->php_self == 'cart') {
            $uniqueId = $this->context->cookie->vrp_device_id;
            if ($uniqueId == false) {
                $uniqueId = VRPaymentHelper::generateUUID();
                $this->context->cookie->vrp_device_id = $uniqueId;
            }
            $scriptUrl = VRPaymentHelper::getBaseGatewayUrl() . '/s/' . Configuration::get(
                VRPaymentBasemodule::CK_SPACE_ID
            ) . '/payment/device.js?sessionIdentifier=' . $uniqueId;
            $this->context->controller->registerJavascript(
                'vrpayment-device-identifier',
                $scriptUrl,
                array(
                    'server' => 'remote',
                    'attributes' => 'async="async"'
                )
            );
        }
        if ($this->context->controller->php_self == 'order') {
            $this->context->controller->registerStylesheet(
                'vrpayment-checkut-css',
                'modules/' . $this->name . '/views/css/frontend/checkout.css'
            );
            $this->context->controller->registerJavascript(
                'vrpayment-checkout-js',
                'modules/' . $this->name . '/views/js/frontend/checkout.js'
            );
            Media::addJsDef(
                array(
                    'vRPaymentCheckoutUrl' => $this->context->link->getModuleLink(
                        'vrpayment',
                        'checkout',
                        array(),
                        true
                    ),
                    'vrpaymentMsgJsonError' => $this->l(
                        'The server experienced an unexpected error, you may try again or try to use a different payment method.'
                    )
                )
            );
            if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
                try {
                    $jsUrl = VRPaymentServiceTransaction::instance()->getJavascriptUrl($this->context->cart);
                    $this->context->controller->registerJavascript(
                        'vrpayment-iframe-handler',
                        $jsUrl,
                        array(
                            'server' => 'remote',
                            'priority' => 45,
                            'attributes' => 'id="vrpayment-iframe-handler"'
                        )
                    );
                } catch (Exception $e) {
                }
            }
        }
        if ($this->context->controller->php_self == 'order-detail') {
            $this->context->controller->registerJavascript(
                'vrpayment-checkout-js',
                'modules/' . $this->name . '/views/js/frontend/orderdetail.js'
            );
        }
    }

    public function hookDisplayTop($params)
    {
        return  VRPaymentBasemodule::hookDisplayTop($this, $params);
    }

    public function hookActionAdminControllerSetMedia($arr)
    {
        VRPaymentBasemodule::hookActionAdminControllerSetMedia($this, $arr);
        $this->context->controller->addCSS(__PS_BASE_URI__ . 'modules/' . $this->name . '/views/css/admin/general.css');
    }

    public function hasBackendControllerDeleteAccess(AdminController $backendController)
    {
        return $backendController->access('delete');
    }

    public function hasBackendControllerEditAccess(AdminController $backendController)
    {
        return $backendController->access('edit');
    }

    public function hookVRPaymentCron($params)
    {
        return VRPaymentBasemodule::hookVRPaymentCron($params);
    }
    /**
     * Show the manual task in the admin bar.
     * The output is moved with javascript to the correct place as better hook is missing.
     *
     * @return string
     */
    public function hookDisplayAdminAfterHeader()
    {
        $result = VRPaymentBasemodule::hookDisplayAdminAfterHeader($this);
        $result .= VRPaymentBasemodule::getCronJobItem($this);
        return $result;
    }

    public function hookVRPaymentSettingsChanged($params)
    {
        return VRPaymentBasemodule::hookVRPaymentSettingsChanged($this, $params);
    }

    public function hookActionMailSend($data)
    {
        return VRPaymentBasemodule::hookActionMailSend($this, $data);
    }

    public function validateOrder(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = array(),
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        Shop $shop = null,
        $order_reference = null
    ) {
        VRPaymentBasemodule::validateOrder($this, $id_cart, $id_order_state, $amount_paid, $payment_method, $message, $extra_vars, $currency_special, $dont_touch_amount, $secure_key, $shop, $order_reference);
    }

    public function validateOrderParent(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = array(),
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        Shop $shop = null,
        $order_reference = null
    ) {
        parent::validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method, $message, $extra_vars, $currency_special, $dont_touch_amount, $secure_key, $shop, $order_reference);
    }

    public function hookDisplayOrderDetail($params)
    {
        return VRPaymentBasemodule::hookDisplayOrderDetail($this, $params);
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        VRPaymentBasemodule::hookDisplayBackOfficeHeader($this, $params);
    }

    public function hookDisplayAdminOrderLeft($params)
    {
        return VRPaymentBasemodule::hookDisplayAdminOrderLeft($this, $params);
    }

    public function hookDisplayAdminOrderTabOrder($params)
    {
        return VRPaymentBasemodule::hookDisplayAdminOrderTabOrder($this, $params);
    }

    public function hookDisplayAdminOrderMain($params)
    {
        return VRPaymentBasemodule::hookDisplayAdminOrderMain($this, $params);
    }

    public function hookActionOrderSlipAdd($params)
    {
        $refundParameters = Tools::getAllValues();

        $order = $params['order'];

        if (!Validate::isLoadedObject($order) || $order->module != $this->name) {
            $idOrder = Tools::getValue('id_order');
            if (!$idOrder) {
                $order = $params['order'];
                $idOrder = (int)$order->id;
            }
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $module->name) {
                return;
            }
        }

        $strategy = VRPaymentBackendStrategyprovider::getStrategy();

        if ($strategy->isVoucherOnlyVRPayment($order, $refundParameters)) {
            return;
        }

        // need to manually set this here as it's expected downstream
        $refundParameters['partialRefund'] = true;

        $backendController = Context::getContext()->controller;
        $editAccess = 0;

        $access = Profile::getProfileAccess(
            Context::getContext()->employee->id_profile,
            (int) Tab::getIdFromClassName('AdminOrders')
        );
        $editAccess = isset($access['edit']) && $access['edit'] == 1;

        if ($editAccess) {
            try {
                $parsedData = $strategy->simplifiedRefund($refundParameters);
                VRPaymentServiceRefund::instance()->executeRefund($order, $parsedData);
            } catch (Exception $e) {
                $backendController->errors[] = VRPaymentHelper::cleanExceptionMessage($e->getMessage());
            }
        } else {
            $backendController->errors[] = Tools::displayError('You do not have permission to delete this.');
        }
    }

    public function hookDisplayAdminOrderTabLink($params)
    {
        return VRPaymentBasemodule::hookDisplayAdminOrderTabLink($this, $params);
    }

    public function hookDisplayAdminOrderContentOrder($params)
    {
        return VRPaymentBasemodule::hookDisplayAdminOrderContentOrder($this, $params);
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        return VRPaymentBasemodule::hookDisplayAdminOrderTabContent($this, $params);
    }

    public function hookDisplayAdminOrder($params)
    {
        return VRPaymentBasemodule::hookDisplayAdminOrder($this, $params);
    }

    public function hookActionAdminOrdersControllerBefore($params)
    {
        return VRPaymentBasemodule::hookActionAdminOrdersControllerBefore($this, $params);
    }

    public function hookActionObjectOrderPaymentAddBefore($params)
    {
        VRPaymentBasemodule::hookActionObjectOrderPaymentAddBefore($this, $params);
    }

    public function hookActionOrderEdited($params)
    {
        VRPaymentBasemodule::hookActionOrderEdited($this, $params);
    }

    public function hookActionOrderGridDefinitionModifier($params)
    {
        VRPaymentBasemodule::hookActionOrderGridDefinitionModifier($this, $params);
    }

    public function hookActionOrderGridQueryBuilderModifier($params)
    {
        VRPaymentBasemodule::hookActionOrderGridQueryBuilderModifier($this, $params);
    }

    public function hookActionProductCancel($params)
    {
        if ($params['action'] === CancellationActionType::PARTIAL_REFUND) {
            $idOrder = Tools::getValue('id_order');
            $refundParameters = Tools::getAllValues();

            $order = $params['order'];

            if (!Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }

            $strategy = VRPaymentBackendStrategyprovider::getStrategy();
            if ($strategy->isVoucherOnlyVRPayment($order, $refundParameters)) {
                return;
            }

            // need to manually set this here as it's expected downstream
            $refundParameters['partialRefund'] = true;

            $backendController = Context::getContext()->controller;
            $editAccess = 0;

            $access = Profile::getProfileAccess(
                Context::getContext()->employee->id_profile,
                (int) Tab::getIdFromClassName('AdminOrders')
            );
            $editAccess = isset($access['edit']) && $access['edit'] == 1;

            if ($editAccess) {
                try {
                    $parsedData = $strategy->simplifiedRefund($refundParameters);
                    VRPaymentServiceRefund::instance()->executeRefund($order, $parsedData);
                } catch (Exception $e) {
                    $backendController->errors[] = VRPaymentHelper::cleanExceptionMessage($e->getMessage());
                }
            } else {
                $backendController->errors[] = Tools::displayError('You do not have permission to delete this.');
            }
        }
    }
}
