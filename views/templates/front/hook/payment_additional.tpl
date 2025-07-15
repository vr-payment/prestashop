{*
 * VR Payment Prestashop
 *
 * This Prestashop module enables to process payments with VR Payment (https://www.vr-payment.de/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div sytle="display:none" class="vrpayment-method-data" data-method-id="{$methodId|escape:'html':'UTF-8'}" data-configuration-id="{$configurationId|escape:'html':'UTF-8'}"></div>
<section>
  {if !empty($description)}
    {* The description has to be unfiltered to dispaly html correcty. We strip unallowed html tags before we assign the variable to smarty *}
    <p>{vrpayment_clean_html text=$description}</p>
  {/if}
  {if !empty($surchargeValues)}
	<span class="vrpayment-surcharge vrpayment-additional-amount"><span class="vrpayment-surcharge-text vrpayment-additional-amount-test">{l s='Minimum Sales Surcharge:' mod='vrpayment'}</span>
		<span class="vrpayment-surcharge-value vrpayment-additional-amount-value">
			{if $priceDisplayTax}
				{Tools::displayPrice($surchargeValues.surcharge_total)|escape:'html':'UTF-8'} {l s='(tax excl.)' mod='vrpayment'}
	        {else}
	        	{Tools::displayPrice($surchargeValues.surcharge_total_wt)|escape:'html':'UTF-8'} {l s='(tax incl.)' mod='vrpayment'}
	        {/if}
       </span>
   </span>
  {/if}
  {if !empty($feeValues)}
	<span class="vrpayment-payment-fee vrpayment-additional-amount"><span class="vrpayment-payment-fee-text vrpayment-additional-amount-test">{l s='Payment Fee:' mod='vrpayment'}</span>
		<span class="vrpayment-payment-fee-value vrpayment-additional-amount-value">
			{if ($priceDisplayTax)}
	          	{Tools::displayPrice($feeValues.fee_total)|escape:'html':'UTF-8'} {l s='(tax excl.)' mod='vrpayment'}
	        {else}
	          	{Tools::displayPrice($feeValues.fee_total_wt)|escape:'html':'UTF-8'} {l s='(tax incl.)' mod='vrpayment'}
	        {/if}
       </span>
   </span>
  {/if}
  
</section>
