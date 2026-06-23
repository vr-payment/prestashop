{*
 * VR Payment Prestashop
 *
 * This Prestashop module enables to process payments with VR Payment (https://www.vr-payment.de/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
*}
{if (isset($showAuthorizedActions) && $showAuthorizedActions)}
	<div style="display:none;" class="hidden-print">
		<a class="btn btn-action vrpayment-management-btn"  id="vrpayment_void">
			<i class="icon-remove"></i>
			{l s='Void' mod='vrpayment'}
		</a>
		<a class="btn btn-action vrpayment-management-btn"  id="vrpayment_completion">
			<i class="icon-check"></i>
			{l s='Completion' mod='vrpayment'}
		</a>
	</div>

	<script type="text/javascript">
		var vrpayment_void_title = "{l s='Are you sure?' mod='vrpayment' js=1}";
		var vrpayment_void_btn_confirm_txt = "{l s='Void Order' mod='vrpayment' js=1}";
		var vrpayment_void_btn_deny_txt = "{l s='No' mod='vrpayment' js=1}";

		var vrpayment_completion_title = "{l s='Are you sure?' mod='vrpayment' js=1}";
		var vrpayment_completion_btn_confirm_txt = "{l s='Complete Order'  mod='vrpayment' js=1}";
		var vrpayment_completion_btn_deny_txt = "{l s='No' mod='vrpayment' js=1}";

		var vrpayment_msg_general_error = "{l s='The server experienced an unexpected error, please try again.'  mod='vrpayment' js=1}";
		var vrpayment_msg_general_title_succes = "{l s='Success'  mod='vrpayment' js=1}";
		var vrpayment_msg_general_title_error = "{l s='Error'  mod='vrpayment' js=1}";
		var vrpayment_btn_info_confirm_txt = "{l s='OK'  mod='vrpayment' js=1}";
	</script>

	<div id="vrpayment_void_msg" class="hidden-print" style="display:none">
		{if !empty($affectedOrders)}
			{l s='This will also void the following orders:' mod='vrpayment' js=1}
			<ul>
				{foreach from=$affectedOrders item=other}
					<li>
						<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$other|intval}">
							{l s='Order %d' sprintf=$other mod='vrpayment' js=1}
						</a>
					</li>
				{/foreach}
			</ul>
			{l s='If you only want to void this order, we recommend to remove all products from this order.' mod='vrpayment' js=1}
		{else}
			{l s='This action cannot be undone.' mod='vrpayment' js=1}
		{/if}
	</div>

	<div id="vrpayment_completion_msg" class="hidden-print" style="display:none">
		{if !empty($affectedOrders)}
			{l s='This will also complete the following orders:' mod='vrpayment'}
			<ul>
				{foreach from=$affectedOrders item=other}
					<li>
						<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$other|intval}">
							{l s='Order %d' sprintf=$other mod='vrpayment'}
						</a>
					</li>
				{/foreach}
			</ul>
		{else}
			{l s='This finalizes the order, it no longer can be changed.' mod='vrpayment'}
		{/if}
	</div>
{/if}

{if (isset($showUpdateActions) && $showUpdateActions)}
	<div style="display:none;" class="hidden-print">
		<a class="btn btn-default vrpayment-management-btn" id="vrpayment_update">
			<i class="icon-refresh"></i>
			{l s='Update' mod='vrpayment'}
		</a>
	</div>
{/if}


{if isset($isVRPaymentTransaction)}
	<div style="display:none;" class="hidden-print" id="vrpayment_is_transaction"></div>
{/if}

{if isset($editButtons)}
	<div style="display:none;" class="hidden-print" id="vrpayment_remove_edit"></div>
{/if}

{if isset($cancelButtons)}
	<div style="display:none;" class="hidden-print" id="vrpayment_remove_cancel"></div>
{/if}

{if isset($refundChanges)}
	<div style="display:none;" class="hidden-print" id="vrpayment_changes_refund">
		<p id="vrpayment_refund_online_text_total">{l s='This refund is sent to %s and money is transfered back to the customer.' sprintf='VR Payment' mod='vrpayment'}</p>
		<p id="vrpayment_refund_offline_text_total" style="display:none;">{l s='This refund is sent to %s, but [1]no[/1] money is transfered back to the customer.' tags=['<b>'] sprintf='VR Payment' mod='vrpayment'}</p>
		<p id="vrpayment_refund_no_text_total" style="display:none;">{l s='This refund is [1]not[/1] sent to %s.' tags=['<b>'] sprintf='VR Payment' mod='vrpayment'}</p>
		<p id="vrpayment_refund_offline_span_total" class="checkbox" style="display: none;">
			<label for="vrpayment_refund_offline_cb_total">
				<input type="checkbox" id="vrpayment_refund_offline_cb_total" name="vrpayment_offline">
				{l s='Send as offline refund to %s.' sprintf='VR Payment' mod='vrpayment'}
			</label>
		</p>

		<p id="vrpayment_refund_online_text_partial">{l s='This refund is sent to %s and money is transfered back to the customer.' sprintf='VR Payment' mod='vrpayment'}</p>
		<p id="vrpayment_refund_offline_text_partial" style="display:none;">{l s='This refund is sent to %s, but [1]no[/1] money is transfered back to the customer.' tags=['<b>'] sprintf='VR Payment' mod='vrpayment'}</p>
		<p id="vrpayment_refund_no_text_partial" style="display:none;">{l s='This refund is [1]not[/1] sent to %s.' tags=['<b>'] sprintf='VR Payment' mod='vrpayment'}</p>
		<p id="vrpayment_refund_offline_span_partial" class="checkbox" style="display: none;">
			<label for="vrpayment_refund_offline_cb_partial">
				<input type="checkbox" id="vrpayment_refund_offline_cb_partial" name="vrpayment_offline">
				{l s='Send as offline refund to %s.' sprintf='VR Payment' mod='vrpayment'}
			</label>
		</p>
	</div>
{/if}

{if isset($completionPending)}
	<div style="display:none;" class="hidden-print" id="vrpayment_completion_pending">
	<span class="span label label-inactive vrpayment-management-info">
		<i class="icon-refresh"></i>
		{l s='Completion in Process' mod='vrpayment'}
	</span>
	</div>
{/if}

{if isset($voidPending)}
	<div style="display:none;" class="hidden-print" id="vrpayment_void_pending">
	<span class="span label label-inactive vrpayment-management-info">
		<i class="icon-refresh"></i>
		{l s='Void in Process' mod='vrpayment'}
	</span>

	</div>
{/if}

{if isset($refundPending)}
	<div style="display:none;" class="hidden-print" id="vrpayment_refund_pending">
	<span class="span label label-inactive vrpayment-management-info">
		<i class="icon-refresh"></i>
		{l s='Refund in Process' mod='vrpayment'}
	</span>
	</div>
{/if}


<script type="text/javascript">
	{if isset($voidUrl)}
	var vRPaymentVoidUrl = "{$voidUrl|escape:'javascript':'UTF-8'}";
	{/if}
	{if isset($completionUrl)}
	var vRPaymentCompletionUrl = "{$completionUrl|escape:'javascript':'UTF-8'}";
	{/if}
	{if isset($updateUrl)}
	var vRPaymentUpdateUrl = "{$updateUrl|escape:'javascript':'UTF-8'}";
	{/if}

</script>
