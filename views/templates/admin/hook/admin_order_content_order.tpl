{*
 * VR Payment Prestashop
 *
 * This Prestashop module enables to process payments with VR Payment (https://www.vr-payment.de/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
*}
<div class="tab-pane" id="vrpayment_documents">
<h4 class="visible-print">VR Payment {l s='Documents' mod='vrpayment'} <span class="badge">({$vRPaymentDocumentsCount|escape:'html':'UTF-8'})</span></h4>

	<div class="table-responsive">
		<table class="table" id="vrpayment_documents_table">
			<tbody>
				{foreach from=$vRPaymentDocuments item=document}
					<tr>
						<td><a class="_blank" href="{$document.url|escape:'html':'UTF-8'}"><i class="icon-{$document.icon} vrpayment-document"></i><span>{$document.name}<pan></a>
						</td>
					</tr>
				{foreachelse}
					<tr>
						<td colspan="1" class="list-empty">
							<div class="list-empty-msg">
								<i class="icon-warning-sign list-empty-icon"></i>
								{l s='There is no document availabe yet.' mod='vrpayment'}
							</div>
						</td>
					</tr>
				{/foreach}
			</tbody>
		</table>
	</div>

</div>
