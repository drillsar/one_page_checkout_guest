// -----
// Part of the One-Page Checkout plugin, provided under GPL 2.0 license by lat9 (cindy@vinosdefrutastropicales.com).
// Copyright (C) 2013-2018, Vinos de Frutas Tropicales.  All rights reserved.
//
var selected;
var submitter = null;

// -----
// These functions are "legacy", carried over from the like-named module in /includes/modules/pages/checkout_payment
//
function concatExpiresFields(fields) 
{
    return jQuery(":input[name=" + fields[0] + "]").val() + jQuery(":input[name=" + fields[1] + "]").val();
}

function popupWindow(url) 
{
    window.open(url,'popupWindow','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,copyhistory=no,width=450,height=320,screenX=150,screenY=150,top=150,left=150')
}

function couponpopupWindow(url) 
{
    window.open(url,'couponpopupWindow','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,copyhistory=no,width=450,height=320,screenX=150,screenY=150,top=150,left=150')
}

// -----
// Used by various payment modules and checkout-confirmation pages; sets the (presumed) submit button with the
// "btn_submit" id disabled upon the form's submittal.
//
function submitonce()
{
    var button = document.getElementById("btn_submit");
    button.style.cursor="wait";
    button.disabled = true;
    setTimeout('button_timeout()', 4000);
    return false;
}
function button_timeout() 
{
    var button = document.getElementById("btn_submit");
    button.style.cursor="pointer";
    button.disabled = false;
}

// -----
// Local to the checkout_one page, provides a common function to log a javascript console
// message.  The checking is required for older (pre IE-9?) versions of Internet Explorer, which
// doesn't instantiate the window.console class unless the debug pane is open.
//
function zcLog2Console(message)
{
    if (window.console) {
        if (typeof(console.log) == 'function') {
            console.log (message);
        }
    }
}

// -----
// Used by the on-page processing and also by various "credit-class" order-totals (e.g. ot_coupon, ot_gv) to
// initialize the checkout_payment form's submittal.  The (global) "submitter" value is set on return to either
// null/0 (payment-handling required) or 1 (no payment-handling required) and is used by the Zen Cart payment class
// to determine whether to "invoke" the selected payment method.
// 
function submitFunction($gv,$total) 
{
    var arg_count = arguments.length;
    submitter = null;
    var arg_list = '';
    for (var i = 0; i < arg_count; i++) {
        arg_list += arguments[i] + ', ';
    }
    zcLog2Console( 'submitFunction, '+arg_count+' arguments: '+arg_list );
    if (arg_count == 2) {
        var ot_total = document.getElementById( 'ottotal' );
        var total = ot_total.children[0].textContent.substr (1);
        zcLog2Console( 'Current order total: '+total+', text: '+ot_total.children[0].textContent );
        document.getElementById( 'current-order-total' ).value = ot_total.children[0].textContent;
        if (total == 0) {
            zcLog2Console( 'Order total is 0, setting submitter' );
            submitter = 1;
        } else {
            var ot_codes = [].slice.call(document.querySelectorAll( '[id^=disc-]' ));
            for (var i = 0; i < ot_codes.length; i++) {
                if (ot_codes[i].value != '') {
                    submitter = 1;
                }
            }
            var ot_gv = document.getElementsByName( 'cot_gv' );
            if (ot_gv.length != 0) {
                zcLog2Console( 'Checking ot_gv value ('+ot_gv[0].value+') against order total ('+total+')' );
                if (ot_gv[0].value >= total) {
                    submitter = 1;
                }
            }
        }
    }
    zcLog2Console('submitFunction, on exit submitter='+submitter);
}

// -----
// Normally used in an onfocus attribute of a payment-module's selection.
//
function methodSelect(theMethod) 
{
    if (document.getElementById(theMethod)) {
        document.getElementById(theMethod).checked = 'checked';
    }
}

// -----
// Not currently used, but might be useful in the future!
//
function setJavaScriptEnabled ()
{
    var jsEnabled = document.getElementById( 'javascript-enabled' );
    if (jsEnabled) {
        jsEnabled.value = '1';
    }
}

// -----
// Called by the on-click event processor for the "Shipping Address, same as Billing?" checkbox, checks
// to see that the ship-to address section is present (it's not for virtual orders) and, if so, either
// hides or shows that address based on the checkbox status.
//
// Requires:
// - checkbox, id="shipping_billing"
// - CSS classes "hiddenField" and "visibleField".
//
function shippingIsBilling () 
{
    var shippingAddress = document.getElementById ('checkoutOneShipto');
    if (shippingAddress) {
        if (document.getElementById ('shipping_billing').checked) {
            shippingAddress.className = 'hiddenField';
            shippingAddress.setAttribute ('className', 'hiddenField'); 
        } else {
            shippingAddress.className = 'visibleField';
            shippingAddress.setAttribute ('className', 'visibleField');
        }
    }
}

// -----
// Called by various on-page event handlers, sets the flag that's passed to the checkout_one_confirmation page
// to indicate whether the transition was due to an order-confirmation vs. a credit-class order-total update.
//
var orderConfirmed = 0;
function setOrderConfirmed (value)
{
    orderConfirmed = value;
    jQuery('#confirm-the-order').val( value );
    zcLog2Console ('Setting orderConfirmed ('+value+'), submitter ('+submitter+')');
}

// -----
// Main processing section, starts when the browser has finished and the page is "ready" ...
//
jQuery(document).ready(function(){
    // -----
    // There are a bunch of "required" elements for this submit-less form to be properly handled.  Check
    // to see that they're present, alerting the customer (hopefully the owner!) if any of those elements
    // are missing.
    //
    var elementsMissing = false;
    if (jQuery( 'form[name="checkout_payment"]' ).length == 0) {
        elementsMissing = true;
        zcLog2Console( 'Missing form[name="checkout_payment"]' );
    }
    if (jQuery( '#orderTotalDivs' ).length == 0) {
        elementsMissing = true;
        zcLog2Console( 'Missing #orderTotalDivs' );
    }
    if (jQuery( '#current-order-total' ).length == 0) {
        elementsMissing = true;
        zcLog2Console ( 'Missing #current-order-total' );
    }
    if (jQuery( '#opc-order-confirm' ).length == 0) {
        elementsMissing = true;
        zcLog2Console( 'Missing #opc-order-confirm' );
    }
    if (jQuery( '#opc-order-review' ).length == 0) {
        elementsMissing = true;
        zcLog2Console( 'Missing #opc-order-review' );
    }

    if (!virtual_order) {
    if (jQuery( '#otshipping' ).length == 0) {
        elementsMissing = true;
        zcLog2Console ( 'Missing #otshipping' );
    }
}

    if (elementsMissing) {
        alert( 'Please contact the store owner; some required elements of this page are missing.' );
    }
    
    // -----
    // Perform some page-load type operations, initializing the "environment".
    //
    shippingIsBilling();
    setJavaScriptEnabled();
  
    // -----
    // Disallow the Enter key (so that all form-submittal actions occur via "click"), except when that
    // key is pressed within a textarea section.
    //
    jQuery(document).on("keypress", ":input:not(textarea)", function(event) {
        return event.keyCode != 13;
    });

    // -----
    // This function displays either the "review-order" or "confirm-order", based
    // on the currently-selected payment method.  If no payment method is selected,
    // the "confirm-order" button is displayed and javascript "injected" by the Zen Cart
    // payment class will alert if no payment method is currently chosen.
    //
    function setFormSubmitButton()
    {
        var payment_module = null;
        if (document.checkout_payment.payment) {
            if (document.checkout_payment.payment.length) {
                for (var i=0; i<document.checkout_payment.payment.length; i++) {
                    if (document.checkout_payment.payment[i].checked) {
                        payment_module = document.checkout_payment.payment[i].value;
                    }
                }
            } else if (document.checkout_payment.payment.checked) {
                payment_module = document.checkout_payment.payment.value;
            } else if (document.checkout_payment.payment.value) {
                payment_module = document.checkout_payment.payment.value;
            }
        }
        zcLog2Console( 'setFormSubmitButton, payment-module: '+payment_module );
        jQuery( '#opc-order-review, #opc-order-confirm' ).hide();
        if (payment_module == null || confirmation_required.indexOf( payment_module ) == -1) {
            jQuery( '#opc-order-confirm' ).show();
            zcLog2Console( 'Showing "confirm"' );
        } else {
            jQuery( '#opc-order-review' ).show();
            zcLog2Console( 'Showing "review"' );
        }
    }
    setFormSubmitButton();
    
    setOrderConfirmed (0);
    jQuery( '#checkoutOneShippingFlag' ).show();
    
    zcLog2Console ( 'jQuery version: '+jQuery().jquery );
    
    
    function focusOnShipping ()
    {
        var scrollPos =  jQuery( "#checkoutShippingMethod" ).offset().top;
        jQuery(window).scrollTop( scrollPos );
    }

    // -----
    // The "collectsCartDataOnsite" interface built into Zen Cart magically transformed between
    // Zen Cart 1.5.4 and 1.5.5, so this module for the One-Page Checkout plugin includes both
    // forms.  That way, if a payment module was written for 1.5.4 it'll work, ditto for those
    // written for the 1.5.5 method.
    //
    // Zen Cart 1.5.4 uses the single-function approach (collectsCardDataOnsite) while the 1.5.5
    // approach splits the functions int "doesCollectsCardDataOnsite" and "doCollectsCardDataOnsite".
    // 
    collectsCardDataOnsite = function(paymentValue)
    {
        zcLog2Console( 'Checking collectsDardDataOnsite('+paymentValue+') ...' );
        zcJS.ajax({
            url: "ajax.php?act=ajaxPayment&method=doesCollectsCardDataOnsite",
            data: {paymentValue: paymentValue}
        }).done(function( response ) {
            if (response.data == true) {
                zcLog2Console( ' ... it does!' );
                var str = jQuery('form[name="checkout_payment"]').serializeArray();

                zcJS.ajax({
                    url: "ajax.php?act=ajaxPayment&method=prepareConfirmation",
                    data: str
                }).done(function( response ) {
                    jQuery('#checkoutPayment').hide();
                    jQuery('#navBreadCrumb').html(response.breadCrumbHtml);
                    jQuery('#checkoutPayment').before(response.confirmationHtml);
                    jQuery(document).attr('title', response.pageTitle);
                    jQuery(document).scrollTop( 0 );
                    if (confirmation_required.indexOf( paymentValue ) == -1) {
                        zcLog2Console( 'Preparing to submit form, since confirmation is not required for "'+paymentValue+'", per the required list: "'+confirmation_required );
                        jQuery('#checkoutOneLoading').show();
                        jQuery('form[name="checkout_confirmation"]')[0].submit();
                    } else {
                        zcLog2Console( 'Confirmation required, displaying for '+paymentValue+'.' );
                        jQuery('#checkoutConfirmDefault').show();
                    }
                });
            } else {
                zcLog2Console( ' ... it does not, submitting.' );
                jQuery('form[name="checkout_payment"]')[0].submit();
            }
        });
        return false;
    }

    var lastPaymentValue = null;

    doesCollectsCardDataOnsite = function(paymentValue)
    {
        zcLog2Console( 'Checking doesCollectsCardDataOnsite('+paymentValue+') ...' );
        if (jQuery('#'+paymentValue+'_collects_onsite').val()) {
            if (jQuery('#pmt-'+paymentValue).is(':checked')) {
                zcLog2Console( '... it does!' );
                lastPaymentValue = paymentValue;
                return true;
            }
        }
        zcLog2Console( '... it does not.' );
        lastPaymentValue = null;
        return false;
    }

    doCollectsCardDataOnsite = function()
    {
        var str = jQuery('form[name="checkout_payment"]').serializeArray();

        zcLog2Console( 'doCollectsCardDataOnsite for '+lastPaymentValue );
        zcJS.ajax({
            url: "ajax.php?act=ajaxPayment&method=prepareConfirmation",
            data: str
        }).done(function( response ) {
            jQuery('#checkoutPayment').hide();
            jQuery('#navBreadCrumb').html(response.breadCrumbHtml);
            jQuery('#checkoutPayment').before(response.confirmationHtml);
            jQuery(document).attr('title', response.pageTitle);
            jQuery(document).scrollTop( 0 );
            if (confirmation_required.indexOf( lastPaymentValue ) == -1) {
                zcLog2Console( 'Preparing to submit form, since confirmation is not required for "'+lastPaymentValue+'", per the required list: "'+confirmation_required );
                jQuery('#checkoutOneLoading').show();
                jQuery('#checkoutConfirmationDefault').hide();
                jQuery('form[name="checkout_confirmation"]')[0].submit();
            } else {
                zcLog2Console( 'Confirmation required, displaying for '+lastPaymentValue+'.' );
                jQuery('#checkoutConfirmDefault').show();
            }
        });
    }

    function changeShippingSubmitForm (type)
    {
        var shippingSelected = jQuery( 'input[name=shipping]' );
        if (shippingSelected.is( ':radio' )) {
            shippingSelected = jQuery( 'input[name=shipping]:checked' );
        }
        if (shippingSelected.length == 0 && type != 'shipping-billing') {
            alert( noShippingSelectedError );
            focusOnShipping();
        } else {
            shippingSelected = shippingSelected.val();
            var shippingIsBilling = jQuery( '#shipping_billing' ).is( ':checked' );
            
            var shippingData = {
                shipping: shippingSelected,
                shipping_is_billing: shippingIsBilling,
                shipping_request: type
            };
            
            if (additionalShippingInputs.length != 0) {
                jQuery.each(additionalShippingInputs, function(field_name, values) {
                    shippingInputs[field_name] = jQuery('input[name="'+values['input_name']+'"]'+values['parms']).val();
                });
                shippingData = jQuery.extend(shippingData, shippingInputs);
            }

            zcLog2Console( 'Updating shipping method to '+shippingSelected+', processing type: '+type );
            zcJS.ajax({
                url: "ajax.php?act=ajaxOnePageCheckout&method=updateShipping",
                data: shippingData,
                timeout: shippingTimeout,
                error: function (jqXHR, textStatus, errorThrown) {
                    zcLog2Console('error: status='+textStatus+', errorThrown = '+errorThrown+', override: '+jqXHR);
                    if (textStatus == 'timeout') {
                        alert( ajaxTimeoutErrorMessage );
                    }
                    shippingError = true;
                },
            }).done(function( response ) {
                jQuery( '#orderTotalDivs' ).html(response.orderTotalHtml);
                
                var shippingError = false;
                jQuery( '#otshipping, #otshipping+br' ).show ();
                if (response.status == 'ok') {
                    if (type == 'shipping-billing') {
                        jQuery( '#checkoutShippingChoices' ).html( response.shippingHtml );
                        jQuery( '#checkoutShippingContentChoose' ).html( response.shippingMessage );
                        jQuery( '#checkoutShippingChoices' ).on( 'click', 'input[name=shipping]', function( event ) {
                            changeShippingSubmitForm( 'shipping-only' );
                        });                        
                    }
                } else {
                    if (response.status == 'timeout') {
                        alert( sessionTimeoutErrorMessage );
                        jQuery(location).attr( 'href', timeoutUrl );
                    }
                    
                    shippingError = true;
                    if (response.status == 'invalid') {
                        jQuery( '#checkoutShippingMethod input[name=shipping]' ).prop( 'checked', false );
                        jQuery( '#checkoutShippingChoices' ).html( response.shippingHtml );
                        jQuery( '#checkoutShippingChoices' ).on( 'click', 'input[name=shipping]', function( event ) {
                            changeShippingSubmitForm( 'shipping-only' );
                        });
                        jQuery( '#otshipping, #otshipping+br' ).hide();
                        focusOnShipping();
                    }
                    if (response.errorMessage != '') {
                        if (type == 'submit' || type == 'shipping-billing' || type == 'submit-cc') {
                            alert( response.errorMessage );
                        }
                    }
                }  
                zcLog2Console( 'Shipping method updated, error: '+shippingError ); 
                
                if (type == 'submit' || type == 'submit-cc') {
                    if (shippingError == true) {
                        zcLog2Console( 'Shipping error, correct to proceed.' );
                    } else {
                        zcLog2Console ('Form submitted, type ('+type+'), orderConfirmed ('+orderConfirmed+')');
                        if (type == 'submit-cc') {
                            jQuery( 'form[name="checkout_payment"]' ).submit();
                        } else if (orderConfirmed) {
                            jQuery( '#confirm-order' ).attr( 'disabled', true );
                            
                            // -----
                            // If there is at least one payment method available, submit the form.
                            //   
                            if (flagOnSubmit) {
                            var formPassed = check_form();
                            zcLog2Console ('Form checked, passed ('+formPassed+')');
                            
                            if (formPassed) {
                                jQuery( '#confirm-order' ).attr('disabled', false);
                                jQuery( 'form[name="checkout_payment"]' ).submit();
                            }
} 
                        }
                    }
                }
            });
        }           
    }
    
    // -----
    // When a shipping-choice is clicked, make the AJAX call to recalculate the order-totals based
    // on that shipping selection.
    //
    jQuery( '#checkoutShippingMethod input[name=shipping]' ).on( 'click', function( event ) {
        changeShippingSubmitForm ('shipping-only', event);
    });
    
    // -----
    // When the billing=shipping box is clicked, record the current selection and make the AJAX call to
    // recalculate the order-totals, now that the shipping address might be different.
    //
    jQuery( '#shipping_billing' ).on( 'click', function( event ) {
        shippingIsBilling();
        changeShippingSubmitForm( 'shipping-billing' );
        
    });

    // -----
    // The tpl_checkout_one_default.php processing has appled 'class="opc-cc-submit"' to each credit-class
    // order-total's "Apply" button.  When one of those "Apply" buttons is clicked, note that the order has
    // **not** been confirmed, make the AJAX call to recalculate the order-totals and submit the form,
    // causing the transition to (and back from) the checkout_one_confirmation page where that credit-class
    // processing has recorded its changes.
    //
    jQuery( '.opc-cc-submit' ).on( 'click', function( event ) {
        zcLog2Console( 'Submitting credit-class request' );
        setOrderConfirmed(0);
        changeShippingSubmitForm( 'submit-cc' );
    });
    
    // -----
    // When a different payment method is chosen, determine whether the payment will require a confirmation-
    // page display, change the form's pseudo-submit button to reflect either "Review" or "Confirm".
    //
    jQuery( 'input[name=payment]' ).on( 'change', function() {
        setFormSubmitButton();
    });
    
    // -----
    // When the form's pseudo-submit button, either "Review" or "Confirm", is clicked, the user is ready
    // to submit their order.  Set up the various "hidden" fields to reflect the order's current state,
    // note that this is an order-confirmation request, and cause the order to be submitted.
    //
    jQuery( '#opc-order-review, #opc-order-confirm' ).on( 'click', function( event ) {
        submitFunction(0,0); 
        setOrderConfirmed (1);

        zcLog2Console( 'Submitting order-creating form' );
        changeShippingSubmitForm( 'submit' );
    });
    
    // -----
    // If we get here successfully, the jQuery processing for the page looks OK so we'll hide the
    // alternate-checkout link and display the "normal" one-page checkout form.
    //
    jQuery( '#checkoutPaymentNoJs' ).hide();
    jQuery( '#checkoutPayment' ).show();
});