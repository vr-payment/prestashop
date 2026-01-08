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
        $this->version = '2.0.4';
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

    public function upgrade($version)
    {
        return true;
    }

    public function installHooks()
    {
        return VRPaymentBasemodule::installHooks($this) && $this->registerHook('paymentOptions') &&
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->registerHook('actionValidateStepComplete') &&
            $this->registerHook('actionObjectAddressAddAfter');
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
            VRPaymentBasemodule::getOrderStatusForm($this)
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
            VRPaymentBasemodule::getOrderStatusConfigValues($this)
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
            $transactionService = VRPaymentServiceTransaction::instance();
            $transaction = $transactionService->getTransactionFromCart($cart);
            $possiblePaymentMethods = $transactionService->getPossiblePaymentMethods($cart, $transaction);
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
        $methods = $this->filterShopMethodConfigurations($shopId, $possiblePaymentMethods);
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

    /**
     * Filters configured method entities for the current shop and the available SDK payment methods.
     *
     * @param int $shopId
     * @param \VRPayment\Sdk\Model\PaymentMethodConfiguration[] $possiblePaymentMethods
     * @return VRPaymentModelMethodconfiguration[]
     */
    protected function filterShopMethodConfigurations($shopId, array $possiblePaymentMethods)
    {
        $configured = VRPaymentModelMethodconfiguration::loadValidForShop($shopId);
        if (empty($configured) || empty($possiblePaymentMethods)) {
            return array();
        }

        $bySpaceAndConfiguration = array();
        foreach ($configured as $methodConfiguration) {
            $spaceId = $methodConfiguration->getSpaceId();
            if (! isset($bySpaceAndConfiguration[$spaceId])) {
                $bySpaceAndConfiguration[$spaceId] = array();
            }
            $bySpaceAndConfiguration[$spaceId][$methodConfiguration->getConfigurationId()] = $methodConfiguration;
        }

        $result = array();
        foreach ($possiblePaymentMethods as $possible) {
            $spaceId = $possible->getSpaceId();
            $configurationId = $possible->getId();
            if (isset($bySpaceAndConfiguration[$spaceId][$configurationId])) {
                $methodConfiguration = $bySpaceAndConfiguration[$spaceId][$configurationId];
                if ($methodConfiguration->isActive()) {
                    $result[] = $methodConfiguration;
                }
            }
        }

        return $result;
    }

    public function hookActionFrontControllerSetMedia()
    {
        $controller = $this->context->controller;

        if (!$controller) {
            return;
        }

        $phpSelf = $controller->php_self;
        if ($phpSelf === 'order' || $phpSelf === 'cart') {

            // Ensure device ID exists
            if (empty($this->context->cookie->vrp_device_id)) {
                $this->context->cookie->vrp_device_id = VRPaymentHelper::generateUUID();
            }

            $deviceId = $this->context->cookie->vrp_device_id;

            $scriptUrl = VRPaymentHelper::getBaseGatewayUrl() .
                '/s/' . Configuration::get(VRPaymentBasemodule::CK_SPACE_ID) .
                '/payment/device.js?sessionIdentifier=' . $deviceId;

            $controller->registerJavascript(
                'vrpayment-device-identifier',
                $scriptUrl,
                [
                'server' => 'remote',
                'attributes' => 'async'
                ]
            );
        }

        /**
         * ORDER PAGE ONLY
         * Add checkout JS/CSS + iframe handler
         */
        if ($phpSelf === 'order') {

            // checkout styles
            $controller->registerStylesheet(
                'vrpayment-checkout-css',
                'modules/' . $this->name . '/views/css/frontend/checkout.css'
            );

            // checkout JS
            $controller->registerJavascript(
                'vrpayment-checkout-js',
                'modules/' . $this->name . '/views/js/frontend/checkout.js'
            );

            // define global JS variables
            Media::addJsDef([
                'vRPaymentCheckoutUrl' => $this->context->link->getModuleLink(
                'vrpayment',
                'checkout',
                [],
                true
                ),
                'vrpaymentMsgJsonError' => $this->l(
                'The server experienced an unexpected error, you may try again or try a different payment method.'
                )
            ]);

            // Iframe handler JS (only when integration = iframe)
            $cart = $this->context->cart;

            if ($cart && Validate::isLoadedObject($cart)) {
                try {
                    // Get integration type from configuration
                    // 0 = iframe
                    // 1 = payment page
                    $integrationType = (int) Configuration::get(VRPaymentBasemodule::CK_INTEGRATION);

                    // Only load JS when NOT payment page
                    if ($integrationType !== Configuration::get(VRPaymentBasemodule::CK_INTEGRATION_TYPE_PAYMENT_PAGE)) {

                        $jsUrl = VRPaymentServiceTransaction::instance()
                            ->getJavascriptUrl($cart);

                        $this->context->controller->registerJavascript(
                            'vrpayment-iframe-handler',
                            $jsUrl,
                            [
                            'server' => 'remote',
                            'priority' => 45,
                            'attributes' => 'id="vrpayment-iframe-handler"'
                            ]
                        );
                    }

                } catch (Exception $e) {
                    // same behavior: silently ignore
                }
            }
        }

        /**
         * ORDER-DETAIL PAGE
         */
        if ($phpSelf === 'order-detail') {
            $controller->registerJavascript(
                'vrpayment-orderdetail-js',
                'modules/' . $this->name . '/views/js/frontend/orderdetail.js'
            );
        }
    }

    public function hookActionObjectAddressAddAfter($params)
    {
        $this->processAddressChange(isset($params['object']) ? $params['object'] : null);
    }

    public function hookActionValidateStepComplete($params)
    {
        if (isset($params['step_name']) && $params['step_name'] === 'addresses') {
            $this->processAddressChange(null);
        }
    }

    /**
     * Refreshes the pending transaction when the checkout address is created/selected.
     *
     * @param Address|null $address
     */
    private function processAddressChange($address = null)
    {
        $cart = $this->context->cart;
        if (!$cart || !Validate::isLoadedObject($cart)) {
            return;
        }

        try {
            VRPaymentServiceTransaction::instance()->refreshTransactionFromCart($cart);
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'VRPayment address refresh failed: ' . $e->getMessage(),
                2,
                null,
                $this->name
            );
        }
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

    /**
     * Show the manual task in the admin bar.
     * The output is moved with javascript to the correct place as better hook is missing.
     *
     * @return string
     */
    public function hookDisplayAdminAfterHeader()
    {
        $result = VRPaymentBasemodule::hookDisplayAdminAfterHeader($this);
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
