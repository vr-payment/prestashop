{*
 * VR Payment Prestashop
 *
 * This Prestashop module enables to process payments with VR Payment (https://www.vr-payment.de/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div id="vrpayment_documents" style="display:none">
{if !empty($vRPaymentInvoice)}
	<a target="_blank" href="{$vRPaymentInvoice|escape:'html':'UTF-8'}">{l s='Download your %name% invoice as a PDF file.' sprintf=['%name%' => 'VR Payment'] mod='vrpayment'}</a>
{/if}
{if !empty($vRPaymentPackingSlip)}
	<a target="_blank" href="{$vRPaymentPackingSlip|escape:'html':'UTF-8'}">{l s='Download your %name% packing slip as a PDF file.' sprintf=['%name%' => 'VR Payment'] mod='vrpayment'}</a>
{/if}
</div>
