/**
 * VR Payment Prestashop
 *
 * This Prestashop module enables to process payments with VR Payment (https://www.vr-payment.de/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */
jQuery(function ($) {

    function getOrderIdFromUrl(string)
    {
        let urlSegment = string.split('vrpayment')[1];
        return urlSegment.split('/')[1]
    }
    
    function initialiseDocumentButtons()
    {
        if ($('[data-original-title="Download VRPayment Invoice"]').length) {
            $('[data-original-title="Download Packing Slip"]').click(function (e) {
                e.preventDefault();
                let id_order = getOrderIdFromUrl($(this).attr('href'));
                window.open(vrpayment_admin_token + "&action=vRPaymentPackingSlip&id_order=" + id_order, "_blank");
            });
        
            $('[data-original-title="Download VRPayment Invoice"]').click(function (e) {
                e.preventDefault();
                let id_order = getOrderIdFromUrl($(this).attr('href'));
                window.open(vrpayment_admin_token + "&action=vRPaymentInvoice&id_order=" + id_order, "_blank");
            });
        
            $('#order_grid_table tr').each(function () {
                let $this = $(this);
                let $row = $this.closest('tr');
                let isWPayment = "0";
                let $paymentStatusCol = $row.find('.column-osname');
                let isWPaymentCol = $row.find('.column-is_w_payment').html();
                if (isWPaymentCol) {
                    isWPayment = isWPaymentCol.trim();
                }
                let paymentStatusText = $paymentStatusCol.find('.btn').text();
                if (!paymentStatusText.includes("Payment accepted") || isWPayment.includes("0")) {
                    $row.find('[data-original-title="Download VRPayment Invoice"]').hide();
                    $row.find('[data-original-title="Download Packing Slip"]').hide();
                }
            });
        }
    }

    function hideIsWPaymentColumn()
    {
        $('th').each(function () {
            let $this = $(this);
            if ($this.html().includes("is_w_payment")) {
                $('table tr').find('td:eq(' + $this.index() + '),th:eq(' + $this.index() + ')').remove();
                return false;
            }
        });
    }
    
    function moveVRPaymentDocuments()
    {
        var documentsTab = $('#vrpayment_documents_tab');
        documentsTab.children('a').addClass('nav-link');
    }
    
    function moveVRPaymentActionsAndInfo()
    {
        var managementBtn = $('a.vrpayment-management-btn');
        var managementInfo = $('span.vrpayment-management-info');
        var orderActions = $('div.order-actions');
        var panel = $('div.panel');
        
        managementBtn.each(function (key, element) {
            $(element).detach();
            orderActions.find('.order-navigation').before(element);
        });
        managementInfo.each(function (key, element) {
            $(element).detach();
            orderActions.find('.order-navigation').before(element);
        });
        //to get the styling of prestashop we have to add this
        managementBtn.after("&nbsp;\n");
        managementInfo.after("&nbsp;\n");
    }
    
    function registerVRPaymentActions()
    {
        $('#vrpayment_update').off('click.vrpayment').on(
            'click.vrpayment',
            updateVRPayment
        );
        $('#vrpayment_void').off('click.vrpayment').on(
            'click.vrpayment',
            showVRPaymentVoid
        );
        $("#vrpayment_completion").off('click.vrpayment').on(
            'click.vrpayment',
            showVRPaymentCompletion
        );
        $('#vrpayment_completion_submit').off('click.vrpayment').on(
            'click.vrpayment',
            executeVRPaymentCompletion
        );
    }
    
    function showVRPaymentInformationSuccess(msg)
    {
        showVRPaymentInformation(msg, vrpayment_msg_general_title_succes, vrpayment_btn_info_confirm_txt, 'dark_green', function () {
            window.location.replace(window.location.href);});
    }
    
    function showVRPaymentInformationFailures(msg)
    {
        showVRPaymentInformation(msg, vrpayment_msg_general_title_error, vrpayment_btn_info_confirm_txt, 'dark_red', function () {
            window.location.replace(window.location.href);});
    }
    
    function showVRPaymentInformation(msg, title, btnText, theme, callback)
    {
        $.jAlert({
            'type': 'modal',
            'title': title,
            'content': msg,
            'theme': theme,
            'replaceOtherAlerts': true,
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'btns': [
            {
                'text': btnText,
                'closeAlert': true,
                'theme': 'blue',
                'onClick': callback
            }
            ],
            'onClose': callback
        });
    }
    
    function updateVRPayment()
    {
        $.ajax({
            type:   'POST',
            dataType:   'json',
            url:    vRPaymentUpdateUrl,
            success:    function (response, textStatus, jqXHR) {
                if ( response.success === 'true' ) {
                    location.reload();
                    return;
                } else if ( response.success === 'false' ) {
                    if (response.message) {
                        showVRPaymentInformation(response.message, msg_vrpayment_confirm_txt);
                    }
                    return;
                }
                showVRPaymentInformation(vrpayment_msg_general_error, msg_vrpayment_confirm_txt);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showVRPaymentInformation(vrpayment_msg_general_error, msg_vrpayment_confirm_txt);
            }
        });
    }
    
        
    function showVRPaymentVoid(e)
    {
        e.preventDefault();
        $.jAlert({
            'type': 'modal',
            'title': vrpayment_void_title,
            'content': $('#vrpayment_void_msg').text(),
            'class': 'multiple_buttons',
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'btns': [
            {
                'text': vrpayment_void_btn_deny_txt,
                'closeAlert': true,
                'theme': 'black'
            },
            {
                'text': vrpayment_void_btn_confirm_txt,
                'closeAlert': true,
                'theme': 'blue',
                'onClick':  executeVRPaymentVoid

            }
            ],
            'theme':'blue'
        });
        return false;
    }

    function executeVRPaymentVoid()
    {
        showVRPaymentSpinner();
        $.ajax({
            type:   'POST',
            dataType:   'json',
            url:    vRPaymentVoidUrl,
            success:    function (response, textStatus, jqXHR) {
                if ( response.success === 'true' ) {
                    showVRPaymentInformationSuccess(response.message);
                    return;
                } else if ( response.success === 'false' ) {
                    if (response.message) {
                        showVRPaymentInformationFailures(response.message);
                        return;
                    }
                }
                showVRPaymentInformationFailures(vrpayment_msg_general_error);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showVRPaymentInformationFailures(vrpayment_msg_general_error);
            }
        });
        return false;
    }
    
    
    function showVRPaymentSpinner()
    {
        $.jAlert({
            'type': 'modal',
            'title': false,
            'content': '<div class="vrpayment-loader"></div>',
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'class': 'unnoticeable',
            'replaceOtherAlerts': true
        });
    
    }
    
    function showVRPaymentCompletion(e)
    {
        e.preventDefault();
        $.jAlert({
            'type': 'modal',
            'title': vrpayment_completion_title,
            'content': $('#vrpayment_completion_msg').text(),
            'class': 'multiple_buttons',
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'btns': [
            {
                'text': vrpayment_completion_btn_deny_txt,
                'closeAlert': true,
                'theme': 'black'
            },
            {
                'text': vrpayment_completion_btn_confirm_txt,
                'closeAlert': true,
                'theme': 'blue',
                'onClick': executeVRPaymentCompletion
            }
            ],
            'theme':'blue'
        });

        return false;
    }
    
    
    function executeVRPaymentCompletion()
    {
        showVRPaymentSpinner();
        $.ajax({
            type:   'POST',
            dataType:   'json',
            url:    vRPaymentCompletionUrl,
            success:    function (response, textStatus, jqXHR) {
                if ( response.success === 'true' ) {
                    showVRPaymentInformationSuccess(response.message);
                    return;
                } else if ( response.success === 'false' ) {
                    if (response.message) {
                        showVRPaymentInformationFailures(response.message);
                        return;
                    }
                }
                showVRPaymentInformationFailures(vrpayment_msg_general_error);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showVRPaymentInformationFailures(vrpayment_msg_general_error);
            }
        });
        return false;
    }
    
    function vRPaymentTotalRefundChanges()
    {
        var generateDiscount =  $('.standard_refund_fields').find('#generateDiscount').attr("checked") === 'checked';
        var sendOffline = $('#vrpayment_refund_offline_cb_total').attr("checked") === 'checked';
        vRPaymentRefundChanges('total', generateDiscount, sendOffline);
    }
    
    function vRPaymentPartialRefundChanges()
    {
    
        var generateDiscount = $('.partial_refund_fields').find('#generateDiscountRefund').attr("checked") === 'checked';
        var sendOffline = $('#vrpayment_refund_offline_cb_partial').attr("checked")  === 'checked';
        vRPaymentRefundChanges('partial', generateDiscount, sendOffline);
    }
    
    function vRPaymentRefundChanges(type, generateDiscount, sendOffline)
    {
        if (generateDiscount) {
            $('#vrpayment_refund_online_text_'+type).css('display','none');
            $('#vrpayment_refund_offline_span_'+type).css('display','block');
            if (sendOffline) {
                $('#vrpayment_refund_offline_text_'+type).css('display','block');
                $('#vrpayment_refund_no_text_'+type).css('display','none');
            } else {
                $('#vrpayment_refund_no_text_'+type).css('display','block');
                $('#vrpayment_refund_offline_text_'+type).css('display','none');
            }
        } else {
            $('#vrpayment_refund_online_text_'+type).css('display','block');
            $('#vrpayment_refund_no_text_'+type).css('display','none');
            $('#vrpayment_refund_offline_text_'+type).css('display','none');
            $('#vrpayment_refund_offline_span_'+type).css('display','none');
            $('#vrpayment_refund_offline_cb_'+type).attr('checked', false);
        }
    }
    
    function handleVRPaymentLayoutChanges()
    {
        var addVoucher = $('#add_voucher');
        var addProduct = $('#add_product');
        var editProductChangeLink = $('.edit_product_change_link');
        var descOrderStandardRefund = $('#desc-order-standard_refund');
        var standardRefundFields = $('.standard_refund_fields');
        var partialRefundFields = $('.partial_refund_fields');
        var descOrderPartialRefund = $('#desc-order-partial_refund');

        if ($('#vrpayment_is_transaction').length > 0) {
            addVoucher.remove();
        }
        if ($('#vrpayment_remove_edit').length > 0) {
            addProduct.remove();
            addVoucher.remove();
            editProductChangeLink.closest('div').remove();
            $('.panel-vouchers').find('i.icon-minus-sign').closest('a').remove();
        }
        if ($('#vrpayment_remove_cancel').length > 0) {
            descOrderStandardRefund.remove();
        }
        if ($('#vrpayment_changes_refund').length > 0) {
            $('#refund_total_3').closest('div').remove();
            standardRefundFields.find('div.form-group').after($('#vrpayment_refund_online_text_total'));
            standardRefundFields.find('div.form-group').after($('#vrpayment_refund_offline_text_total'));
            standardRefundFields.find('div.form-group').after($('#vrpayment_refund_no_text_total'));
            standardRefundFields.find('#spanShippingBack').after($('#vrpayment_refund_offline_span_total'));
            standardRefundFields.find('#generateDiscount').off('click.vrpayment').on('click.vrpayment', vRPaymentTotalRefundChanges);
            $('#vrpayment_refund_offline_cb_total').on('click.vrpayment', vRPaymentTotalRefundChanges);
        
            $('#refund_3').closest('div').remove();
            partialRefundFields.find('button').before($('#vrpayment_refund_online_text_partial'));
            partialRefundFields.find('button').before($('#vrpayment_refund_offline_text_partial'));
            partialRefundFields.find('button').before($('#vrpayment_refund_no_text_partial'));
            partialRefundFields.find('#generateDiscountRefund').closest('p').after($('#vrpayment_refund_offline_span_partial'));
            partialRefundFields.find('#generateDiscountRefund').off('click.vrpayment').on('click.vrpayment', vRPaymentPartialRefundChanges);
            $('#vrpayment_refund_offline_cb_partial').on('click.vrpayment', vRPaymentPartialRefundChanges);
        }
        if ($('#vrpayment_completion_pending').length > 0) {
            addProduct.remove();
            addVoucher.remove();
            editProductChangeLink.closest('div').remove();
            descOrderPartialRefund.remove();
            descOrderStandardRefund.remove();
        }
        if ($('#vrpayment_void_pending').length > 0) {
            addProduct.remove();
            addVoucher.remove();
            editProductChangeLink.closest('div').remove();
            descOrderPartialRefund.remove();
            descOrderStandardRefund.remove();
        }
        if ($('#vrpayment_refund_pending').length > 0) {
            descOrderStandardRefund.remove();
            descOrderPartialRefund.remove();
        }
        moveVRPaymentDocuments();
        moveVRPaymentActionsAndInfo();
    }
    
    function init()
    {
        handleVRPaymentLayoutChanges();
        registerVRPaymentActions();
        initialiseDocumentButtons();
        hideIsWPaymentColumn();
    }
    
    init();
});
