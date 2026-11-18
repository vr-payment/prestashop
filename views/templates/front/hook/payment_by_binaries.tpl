{*
 * VR Payment Prestashop
 *
 * This Prestashop module enables to process payments with VR Payment (https://www.vr-payment.de).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
{foreach from=$vrpaymentBinaryButtons item="button"}
  <section class="js-payment-binary js-payment-{$button.moduleName|escape:'html':'UTF-8'} disabled">
    <button
      type="button"
      class="btn btn-primary vrpayment-pay-button"
      data-method-id="{$button.methodId|escape:'html':'UTF-8'}"
      disabled="disabled"
    >
      {l s='Pay with %s' sprintf=[$button.name] mod='vrpayment'}
    </button>
  </section>
{/foreach}
