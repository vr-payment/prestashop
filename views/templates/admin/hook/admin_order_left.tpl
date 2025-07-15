{*
 * VR Payment Prestashop
 *
 * This Prestashop module enables to process payments with VR Payment (https://www.vr-payment.de/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
*}
<div id="vRPaymentTransactionInfo" class="card">
	<div class="card-header">
		<i class="icon-rocket"></i>
		VR Payment {l s='Transaction Information' mod='vrpayment'}
	</div>
	<div class="card-body">
	<div class="vrpayment-transaction-data-column-container">
		<div class="vrpayment-transaction-column">
			<p>
				<strong>{l s='General Details' mod='vrpayment'}</strong>
			</p>
			<dl class="well list-detail">
				<dt>{l s='Payment Method' mod='vrpayment'}</dt>
				<dd>{$configurationName|escape:'html':'UTF-8'}
			{if !empty($methodImage)}
			 	<br /><img
						src="{$methodImage|escape:'html':'UTF-8'}"
						width="50" />
			{/if}
				</dd>
				<dt>{l s='Transaction State' mod='vrpayment'}</dt>
				<dd>{$transactionState|escape:'html':'UTF-8'}</dd>
			{if !empty($failureReason)}
            	<dt>{l s='Failure Reason' mod='vrpayment'}</dt>
				<dd>{$failureReason|escape:'html':'UTF-8'}</dd>
			{/if}
        		<dt>{l s='Authorization Amount' mod='vrpayment'}</dt>
				<dd>{displayPrice price=$authorizationAmount}</dd>
				<dt>{l s='Transaction' mod='vrpayment'}</dt>
				<dd>
					<a href="{$transactionUrl|escape:'html':'UTF-8'}" target="_blank">
						{l s='View' mod='vrpayment'}
					</a>
				</dd>
			</dl>
		</div>
		{if !empty($labelsByGroup)}
			{foreach from=$labelsByGroup item=group}
			<div class="vrpayment-transaction-column">
				<div class="vrpayment-payment-label-container" id="vrpayment-payment-label-container-{$group.id|escape:'html':'UTF-8'}">
					<p class="vrpayment-payment-label-group">
						<strong>
						{$group.translatedTitle|escape:'html':'UTF-8'}
						</strong>
					</p>
					<dl class="well list-detail">
						{foreach from=$group.labels item=label}
	                		<dt>{$label.translatedName|escape:'html':'UTF-8'}</dt>
							<dd>{$label.value|escape:'html':'UTF-8'}</dd>
						{/foreach}
					</dl>
				</div>
			</div>
			{/foreach}
		{/if}
	</div>
	{if !empty($completions)}
		<div class="vrpayment-transaction-data-column-container panel">
			<div class="panel-heading">
				<i class="icon-check"></i>
					VR Payment {l s='Completions' mod='vrpayment'}
			</div>
			<div class="table-responsive">
				<table class="table" id="vrpayment_completion_table">
					<thead>
						<tr>
							<th>
								<span class="title_box ">{l s='Job Id' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Completion Id' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Status' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Error Message' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Links' mod='vrpayment'}</span>
							</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$completions item=completion}
						<tr>
							<td>{$completion->getId()|escape:'html':'UTF-8'}</td>
							<td>{if ($completion->getCompletionId() != 0)}
									{$completion->getCompletionId()|escape:'html':'UTF-8'}
								{else}
									{l s='Not available' mod='vrpayment'}
								{/if}
							</td>
							<td>{$completion->getState()|escape:'html':'UTF-8'}</td>
							<td>{if !empty($completion->getFailureReason())}
									{assign var='failureReason' value="{vrpayment_translate text=$completion->getFailureReason()}"}
									{$failureReason|escape:'html':'UTF-8'}
								{else}
									{l s='(None)' mod='vrpayment'}
								{/if}
							</td>
							<td>
								{if ($completion->getCompletionId() != 0)}
									{assign var='completionUrl' value="{vrpayment_completion_url completion=$completion}"}
									<a href="{$completionUrl|escape:'html':'UTF-8'}" target="_blank">
										{l s='View' mod='vrpayment'}
									</a>
								{else}
									{l s='Not available' mod='vrpayment'}
								{/if}
							</td>
						</tr>
					{/foreach}
					</tbody>
				</table>
			</div>
		</div>
	{/if}
		{if !empty($void)}
		<div class="vrpayment-transaction-data-column-container panel">
			<div class="panel-heading">
				<i class="icon-remove"></i>
					VR Payment {l s='Voids' mod='vrpayment'}
			</div>
			<div class="table-responsive">
				<table class="table" id="vrpayment_void_table">
					<thead>
						<tr>
							<th>
								<span class="title_box ">{l s='Job Id' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Void Id' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Status' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Error Message' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Links' mod='vrpayment'}</span>
							</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$voids item=voidItem}
						<tr>
							<td>{$voidItem->getId()|escape:'html':'UTF-8'}</td>
							<td>{if ($voidItem->getVoidId() != 0)}
									{$voidItem->getVoidId()|escape:'html':'UTF-8'}
								{else}
									{l s='Not available' mod='vrpayment'}
								{/if}
							</td>
							<td>{$voidItem->getState()|escape:'html':'UTF-8'}</td>
							<td>{if !empty($voidItem->getFailureReason())}
									{assign var='failureReason' value="{vrpayment_translate text=$voidItem->getFailureReason()}"}
									{$failureReason|escape:'html':'UTF-8'}
								{else}
									{l s='(None)' mod='vrpayment'}
								{/if}
							</td>
							<td>
								{if ($voidItem->getVoidId() != 0)}
									{assign var='voidUrl' value="{vrpayment_void_url void=$voidItem}"}
									<a href="{$voidUrl|escape:'html':'UTF-8'}" target="_blank">
										{l s='View' mod='vrpayment'}
									</a>
								{else}
									{l s='Not available' mod='vrpayment'}
								{/if}
							</td>
						</tr>
					{/foreach}
					</tbody>
				</table>
			</div>
		</div>
	{/if}
		{if !empty($refunds)}
		<div class="vrpayment-transaction-data-column-container panel">
			<div class="panel-heading">
				<i class="icon-exchange"></i>
					VR Payment {l s='Refunds' mod='vrpayment'}
			</div>
			<div class="table-responsive">
				<table class="table" id="vrpayment_refund_table">
					<thead>
						<tr>
							<th>
								<span class="title_box ">{l s='Job Id' mod='vrpayment'}</span>
							</th>

							<th>
								<span class="title_box ">{l s='External Id' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Refund Id' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Amount' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Type' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Status' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Error Message' mod='vrpayment'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Links' mod='vrpayment'}</span>
							</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$refunds item=refund}
						<tr>
							<td>{$refund->getId()|escape:'html':'UTF-8'}</td>
							<td>{$refund->getExternalId()|escape:'html':'UTF-8'}</td>
							<td>
								{if ($refund->getRefundId() != 0)}
									{$refund->getRefundId()|escape:'html':'UTF-8'}
								{else}
									{l s='Not available' mod='vrpayment'}
								{/if}
							</td>
							<td>
								{assign var='refundAmount' value="{vrpayment_refund_amount refund=$refund}"}
								{displayPrice price=$refundAmount currency=$currency->id}
							</td>
							<td>
								{assign var='refundType' value="{vrpayment_refund_type refund=$refund}"}
								{$refundType|escape:'html':'UTF-8'}
							</td>
							<td>{$refund->getState()|escape:'html':'UTF-8'}</td>
							<td>{if !empty($refund->getFailureReason())}
									{assign var='failureReason' value="{vrpayment_translate text=$refund->getFailureReason()}"}
									{$failureReason|escape:'html':'UTF-8'}
								{else}
									{l s='(None)' mod='vrpayment'}
								{/if}
							</td>
							<td>
								{if ($refund->getRefundId() != 0)}
									{assign var='refundURl' value="{vrpayment_refund_url refund=$refund}"}
									<a href="{$refundURl|escape:'html':'UTF-8'}" target="_blank">
										{l s='View' mod='vrpayment'}
									</a>
								{else}
									{l s='Not available' mod='vrpayment'}
								{/if}
							</td>
						</tr>
					{/foreach}
					</tbody>
				</table>
			</div>
		</div>
	{/if}
	</div>

</div>
