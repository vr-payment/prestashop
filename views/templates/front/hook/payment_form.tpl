{*
 * VR Payment Prestashop
 *
 * This Prestashop module enables to process payments with VR Payment (https://www.vr-payment.de/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<form action="{$orderUrl|escape:'html':'UTF-8'}" class="vrpayment-payment-form" data-method-id="{$methodId|escape:'html':'UTF-8'}">
	<div id="vrpayment-{$methodId|escape:'html':'UTF-8'}">
		<input type="hidden" id="vrpayment-iframe-possible-{$methodId|escape:'html':'UTF-8'}" name="vrpayment-iframe-possible-{$methodId|escape:'html':'UTF-8'}" value="{$iframe|escape:'html':'UTF-8'}" />
		<div id="vrpayment-loader-{$methodId|escape:'html':'UTF-8'}" class="vrpayment-loader"></div>
	</div>
</form>
