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

class AdminVRPaymentMethodSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->context->smarty->addTemplateDir($this->getTemplatePath());
        $this->tpl_folder = 'method_settings/';
        $this->bootstrap = true;
    }

    public function postProcess()
    {
        if (Tools::isSubmit('save_style') || Tools::isSubmit('save_fee') || Tools::isSubmit('save_all')) {
            $shopContext = (! Shop::isFeatureActive() || Shop::getContext() == Shop::CONTEXT_SHOP);
            if (! $shopContext) {
                $this->displayWarning(
                    $this->module->l(
                        'You can only save the settings in a shop context.',
                        'adminvrpaymentmethodsettingscontroller'
                    )
                );
                return;
            }
            $spaceId = Configuration::get(VRPaymentBasemodule::CK_SPACE_ID);
            if ($spaceId === false) {
                $this->displayWarning(
                    $this->module->l(
                        'You have to configure a Space Id for the current shop.',
                        'adminvrpaymentmethodsettingscontroller'
                    )
                );
                return;
            }
            $methodId = Tools::getValue('method_id', null);
            if ($methodId === null || ! ctype_digit($methodId)) {
                $this->displayWarning(
                    $this->module->l('No valid method provided.', 'adminvrpaymentmethodsettingscontroller')
                );
                return;
            }
            $method = VRPaymentModelMethodconfiguration::loadByIdWithChecks(
                $methodId,
                Context::getContext()->shop->id
            );
            if ($method === false) {
                $this->displayWarning(
                    $this->module->l(
                        'This method is not configurable in this shop context.',
                        'adminvrpaymentmethodsettingscontroller'
                    )
                );
                return;
            }
            if (Tools::isSubmit('save_style') || Tools::isSubmit('save_all')) {
                $method->setActive((int) Tools::getValue('active'));
                $method->setShowDescription((int) Tools::getValue('show_description'));
                $method->setShowImage((int) Tools::getValue('show_image'));
            }
            if (Tools::isSubmit('save_fee') || Tools::isSubmit('save_all')) {
                $method->setFeeFixed((float) Tools::getValue('fee_fixed'));
                $method->setFeeRate((float) Tools::getValue('fee_rate'));
                $method->setFeeBase((int) Tools::getValue('fee_base'));
                $method->setFeeAddTax((int) Tools::getValue('fee_add_tax'));
            }
            $method->update();
            $this->setRedirectAfter(
                self::$currentIndex . '&token=' . $this->token . '&method_id=' . (int) Tools::getValue('method_id')
            );
        }
    }

    public function initContent()
    {
        $methodId = Tools::getValue('method_id', null);
        if ($methodId !== null && ctype_digit($methodId)) {
            $this->handleView($methodId);
        } else {
            $this->handleList();
        }
        parent::initContent();
    }

    private function handleList()
    {
        $this->display = 'list';
        $this->context->smarty->assign(
            'title',
            'VR Payment ' .
            $this->module->l('Payment Methods', 'adminvrpaymentmethodsettingscontroller')
        );

        $shopContext = (! Shop::isFeatureActive() || Shop::getContext() == Shop::CONTEXT_SHOP);
        if (! $shopContext) {
            $this->displayWarning(
                $this->module->l(
                    'You have more than one shop and must select one to configure the payment methods.',
                    'adminvrpaymentmethodsettingscontroller'
                )
            );
            return;
        }
        $spaceId = Configuration::get(VRPaymentBasemodule::CK_SPACE_ID);
        if ($spaceId === false) {
            $this->displayWarning(
                $this->module->l(
                    'You have to configure a Space Id for the current shop.',
                    'adminvrpaymentmethodsettingscontroller'
                )
            );
            return;
        }
        $methodConfigurations = array();
        $methods = VRPaymentModelMethodconfiguration::loadValidForShop(Context::getContext()->shop->id);
        $spaceViewId = Configuration::get(VRPaymentBasemodule::CK_SPACE_VIEW_ID);
        foreach ($methods as $method) {
            $methodConfigurations[] = array(
                'id' => $method->getId(),
                'configurationName' => $method->getConfigurationName(),
                'imageUrl' => VRPaymentHelper::getResourceUrl(
                    $method->getImageBase(),
                    $method->getImage(),
                    VRPaymentHelper::convertLanguageIdToIETF($this->context->language->id),
                    $spaceId,
                    $spaceViewId
                )
            );
        }

        $this->context->smarty->assign('methodConfigurations', $methodConfigurations);
    }

    private function handleView($methodId)
    {
        $shopContext = (! Shop::isFeatureActive() || Shop::getContext() == Shop::CONTEXT_SHOP);
        if (! $shopContext) {
            $this->displayWarning(
                $this->module->l(
                    'You can only edit the settings in a shop context.',
                    'adminvrpaymentmethodsettingscontroller'
                )
            );
            return;
        }
        $spaceId = Configuration::get(VRPaymentBasemodule::CK_SPACE_ID);
        if ($spaceId === false) {
            $this->displayWarning(
                $this->module->l(
                    'You have to configure a Space Id for the current shop.',
                    'adminvrpaymentmethodsettingscontroller'
                )
            );
            return;
        }
        $method = VRPaymentModelMethodconfiguration::loadByIdWithChecks(
            $methodId,
            Context::getContext()->shop->id
        );
        if ($method === false) {
            $this->displayWarning(
                $this->module->l(
                    'This method is not available in this shop context.',
                    'adminvrpaymentmethodsettingscontroller'
                )
            );
            return;
        }
        $this->display = 'edit';

        $form = $this->displayConfig($method);
        $this->toolbar_title = $method->getConfigurationName();
        $this->context->smarty->registerPlugin(
            'function',
            'vrpayment_output_method_form',
            array(
                'VRPaymentSmartyfunctions',
                'outputMethodForm'
            )
        );
        $this->context->smarty->assign('formHtml', $form);
    }

    private function getFormHelper()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get(
            'PS_BO_ALLOW_EMPLOYEE_FORM_LANG'
        ) : 0;

        $helper->identifier = $this->identifier;
        $helper->title = 'VR Payment ' .
            $this->module->l('Payment Methods', 'adminvrpaymentmethodsettingscontroller');

        $helper->module = $this->module;
        $helper->name_controller = 'AdminVRPaymentMethodSettings';
        $helper->token = Tools::getAdminTokenLite('AdminVRPaymentMethodSettings');
        $helper->tpl_vars = array(
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );
        return $helper;
    }

    private function displayConfig(VRPaymentModelMethodconfiguration $method)
    {
        $configuration = array(
            array(
                'type' => 'switch',
                'label' => $this->module->l('Active', 'adminvrpaymentmethodsettingscontroller'),
                'name' => 'active',
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Active', 'adminvrpaymentmethodsettingscontroller')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->module->l('Disabled', 'adminvrpaymentmethodsettingscontroller')
                    )
                ),
                'lang' => false
            ),
            array(
                'type' => 'text',
                'label' => $this->module->l('Title', 'adminvrpaymentmethodsettingscontroller'),
                'name' => 'title',
                'disabled' => true,
                'lang' => true,
                'col' => 6
            ),
            array(
                'type' => 'text',
                'label' => $this->module->l('Description', 'adminvrpaymentmethodsettingscontroller'),
                'name' => 'description',
                'disabled' => true,
                'lang' => true,
                'col' => 6
            ),
            array(
                'type' => 'switch',
                'label' => $this->module->l(
                    'Display method description',
                    'adminvrpaymentmethodsettingscontroller'
                ),
                'name' => 'show_description',
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->module->l('Show', 'adminvrpaymentmethodsettingscontroller')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->module->l('Hide', 'adminvrpaymentmethodsettingscontroller')
                    )
                ),
                'lang' => false
            ),
            array(
                'type' => 'switch',
                'label' => $this->module->l(
                    'Display method image',
                    'adminvrpaymentmethodsettingscontroller'
                ),
                'name' => 'show_image',
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->module->l('Show', 'adminvrpaymentmethodsettingscontroller')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->module->l('Hide', 'adminvrpaymentmethodsettingscontroller')
                    )
                ),
                'lang' => false
            )
        );

        $defaultCurrency = Currency::getCurrency(Configuration::get('PS_CURRENCY_DEFAULT'));
        $fees = array(
            array(
                'type' => 'switch',
                'label' => $this->module->l('Add tax', 'adminvrpaymentmethodsettingscontroller'),
                'name' => 'fee_add_tax',
                'desc' => $this->module->l(
                    'Should the tax amount be added after the computation or should the tax be included in the computed fee.',
                    'adminvrpaymentmethodsettingscontroller'
                ),
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->module->l('Add', 'adminvrpaymentmethodsettingscontroller')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->module->l('Inlcuded', 'adminvrpaymentmethodsettingscontroller')
                    )
                ),
                'lang' => false
            ),
            array(
                'type' => 'text',
                'label' => $this->module->l('Fee Fixed', 'adminvrpaymentmethodsettingscontroller'),
                'desc' => sprintf(
                    $this->module->l(
                        'The fee has to be entered in the shops default currency. Current default currency: %s',
                        'adminvrpaymentmethodsettingscontroller'
                    ),
                    $defaultCurrency['iso_code']
                ),
                'name' => 'fee_fixed',
                'col' => 3
            ),
            array(
                'type' => 'text',
                'label' => $this->module->l('Fee Rate', 'adminvrpaymentmethodsettingscontroller'),
                'desc' => $this->module->l(
                    'The rate in percent.',
                    'adminvrpaymentmethodsettingscontroller'
                ),
                'name' => 'fee_rate',
                'col' => 3
            ),
            array(
                'type' => 'select',
                'label' => $this->module->l(
                    'Fee is calculated based on:',
                    'adminvrpaymentmethodsettingscontroller'
                ),
                'name' => 'fee_base',
                'options' => array(
                    'query' => array(
                        array(
                            'name' => $this->module->l(
                                'Total (inc Tax)',
                                'adminvrpaymentmethodsettingscontroller'
                            ),
                            'type' => VRPaymentBasemodule::TOTAL_MODE_BOTH_INC
                        ),
                        array(
                            'name' => $this->module->l(
                                'Total (exc Tax)',
                                'adminvrpaymentmethodsettingscontroller'
                            ),
                            'type' => VRPaymentBasemodule::TOTAL_MODE_BOTH_EXC
                        ),
                        array(
                            'name' => $this->module->l(
                                'Total without shipping (inc Tax)',
                                'adminvrpaymentmethodsettingscontroller'
                            ),
                            'type' => VRPaymentBasemodule::TOTAL_MODE_WITHOUT_SHIPPING_INC
                        ),
                        array(
                            'name' => $this->module->l(
                                'Total without shipping (exc Tax)',
                                'adminvrpaymentmethodsettingscontroller'
                            ),
                            'type' => VRPaymentBasemodule::TOTAL_MODE_WITHOUT_SHIPPING_EXC
                        ),
                        array(
                            'name' => $this->module->l(
                                'Products only (inc Tax)',
                                'adminvrpaymentmethodsettingscontroller'
                            ),
                            'type' => VRPaymentBasemodule::TOTAL_MODE_PRODUCTS_INC
                        ),
                        array(
                            'name' => $this->module->l(
                                'Products only (exc Tax)',
                                'adminvrpaymentmethodsettingscontroller'
                            ),
                            'type' => VRPaymentBasemodule::TOTAL_MODE_PRODUCTS_EXC
                        )
                    ),
                    'id' => 'type',
                    'name' => 'name'
                )
            )
        );

        $fieldsForm = array();
        $fieldsForm[]['form'] = array(
            'legend' => array(
                'title' => $this->module->l('General Settings', 'adminvrpaymentmethodsettingscontroller')
            ),
            'input' => $configuration,
            'buttons' => array(
                array(
                    'title' => $this->module->l('Save All', 'adminvrpaymentmethodsettingscontroller'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'save_all'
                ),
                array(
                    'title' => $this->module->l('Save', 'adminvrpaymentmethodsettingscontroller'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'save_style'
                )
            )
        );
        $fieldsForm[]['form'] = array(
            'legend' => array(
                'title' => $this->module->l('Payment Fee', 'adminvrpaymentmethodsettingscontroller')
            ),
            'input' => $fees,
            'buttons' => array(
                array(
                    'title' => $this->module->l('Save All', 'adminvrpaymentmethodsettingscontroller'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'save_all'
                ),
                array(
                    'title' => $this->module->l('Save', 'adminvrpaymentmethodsettingscontroller'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'save_fee'
                )
            )
        );
        $helper = $this->getFormHelper();
        $helper->tpl_vars['fields_value'] = array_merge($this->getBaseValues($method), $this->getFeeValues($method));
        return $helper->generateForm($fieldsForm);
    }

    private function getBaseValues(VRPaymentModelMethodconfiguration $method)
    {
        $result = array();
        $result['active'] = $method->isActive();
        $result['show_description'] = $method->isShowDescription();
        $result['show_image'] = $method->isShowImage();
        $title = array();
        $description = array();
        foreach ($this->context->controller->getLanguages() as $language) {
            $title[$language['id_lang']] = VRPaymentHelper::translate(
                $method->getTitle(),
                $language['id_lang']
            );
            $description[$language['id_lang']] = VRPaymentHelper::translate(
                $method->getDescription(),
                $language['id_lang']
            );
        }
        $result['title'] = $title;
        $result['description'] = $description;
        return $result;
    }

    private function displayFeeConfig(VRPaymentModelMethodconfiguration $method)
    {
        $submit = array(
            'title' => $this->module->l('Save', 'adminvrpaymentmethodsettingscontroller'),
            'class' => 'btn btn-default pull-right'
        );
        $defaultCurrency = Currency::getCurrency(Configuration::get('PS_CURRENCY_DEFAULT'));
        $fieldsForm = array();
        $fees = array(
            array(
                'type' => 'switch',
                'label' => $this->module->l('Add tax', 'adminvrpaymentmethodsettingscontroller'),
                'name' => 'fee_add_tax',
                'desc' => $this->module->l(
                    'Should the tax amount be added after the computation or should the tax be included in the computed fee.',
                    'adminvrpaymentmethodsettingscontroller'
                ),
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->module->l('Add', 'adminvrpaymentmethodsettingscontroller')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->module->l('Inlcuded', 'adminvrpaymentmethodsettingscontroller')
                    )
                ),
                'lang' => false
            ),
            array(
                'type' => 'text',
                'label' => $this->module->l('Fee Fixed', 'adminvrpaymentmethodsettingscontroller'),
                'desc' => sprintf(
                    $this->module->l(
                        'The fee has to be entered in the shops default currency. Current default currency: %s',
                        'adminvrpaymentmethodsettingscontroller'
                    ),
                    $defaultCurrency['iso_code']
                ),
                'name' => 'fee_fixed',
                'col' => 3
            ),
            array(
                'type' => 'text',
                'label' => $this->module->l('Fee Rate', 'adminvrpaymentmethodsettingscontroller'),
                'desc' => $this->module->l(
                    'The rate in percent.',
                    'adminvrpaymentmethodsettingscontroller'
                ),
                'name' => 'fee_rate',
                'col' => 3
            ),
            array(
                'type' => 'select',
                'label' => $this->module->l(
                    'Fee is calculated based on:',
                    'adminvrpaymentmethodsettingscontroller'
                ),
                'name' => 'fee_base',
                'options' => array(
                    'query' => array(
                        array(
                            'name' => $this->module->l(
                                'Total (inc Tax)',
                                'adminvrpaymentmethodsettingscontroller'
                            ),
                            'type' => VRPaymentBasemodule::TOTAL_MODE_BOTH_INC
                        ),
                        array(
                            'name' => $this->module->l(
                                'Total (exc Tax)',
                                'adminvrpaymentmethodsettingscontroller'
                            ),
                            'type' => VRPaymentBasemodule::TOTAL_MODE_BOTH_EXC
                        ),
                        array(
                            'name' => $this->module->l(
                                'Total without shipping (inc Tax)',
                                'adminvrpaymentmethodsettingscontroller'
                            ),
                            'type' => VRPaymentBasemodule::TOTAL_MODE_WITHOUT_SHIPPING_INC
                        ),
                        array(
                            'name' => $this->module->l(
                                'Total without shipping (exc Tax)',
                                'adminvrpaymentmethodsettingscontroller'
                            ),
                            'type' => VRPaymentBasemodule::TOTAL_MODE_WITHOUT_SHIPPING_EXC
                        ),
                        array(
                            'name' => $this->module->l(
                                'Products only (inc Tax)',
                                'adminvrpaymentmethodsettingscontroller'
                            ),
                            'type' => VRPaymentBasemodule::TOTAL_MODE_PRODUCTS_INC
                        ),
                        array(
                            'name' => $this->module->l(
                                'Products only (exc Tax)',
                                'adminvrpaymentmethodsettingscontroller'
                            ),
                            'type' => VRPaymentBasemodule::TOTAL_MODE_PRODUCTS_EXC
                        )
                    ),
                    'id' => 'type',
                    'name' => 'name'
                )
            )
        );

        $fieldsForm[]['form'] = array(
            'legend' => array(
                'title' => $this->module->l('Payment Fee', 'adminvrpaymentmethodsettingscontroller')
            ),
            'input' => $fees,
            'submit' => $submit
        );
        $helper = $this->getFormHelper();
        $helper->submit_action = 'save_fee';
        $helper->tpl_vars['fields_value'] = $this->getFeeValues($method);
        return $helper->generateForm($fieldsForm);
    }

    private function getFeeValues(VRPaymentModelMethodconfiguration $method)
    {
        $result = array();
        $result['fee_rate'] = $method->getFeeRate();
        $result['fee_fixed'] = $method->getFeeFixed();
        $result['fee_base'] = $method->getFeeBase();
        $result['fee_add_tax'] = $method->isFeeAddTax();
        return $result;
    }
}
