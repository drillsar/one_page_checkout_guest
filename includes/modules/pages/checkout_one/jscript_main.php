<?php
// -----
// Part of the One-Page Checkout plugin, provided under GPL 2.0 license by lat9 (cindy@vinosdefrutastropicales.com).
// Copyright (C) 2013-2017, Vinos de Frutas Tropicales.  All rights reserved.
//
?>
<script type="text/javascript"><!--
<?php
// -----
// The "confirmation_required" array contains a list of payment modules for which, er, confirmation
// is required.  This is used to determine whether the "confirm-order" or "review-order" button is displayed.
// The $required_list value is created by the page's header_php.php processing.
//
?>
var confirmation_required = [<?php echo $required_list; ?>];

var virtual_order = <?php echo ($is_virtual_order) ? 'true' : 'false'; ?>;

var timeoutUrl = '<?php echo zen_href_link (FILENAME_LOGIN, '', 'SSL'); ?>';
var sessionTimeoutErrorMessage = '<?php echo JS_ERROR_SESSION_TIMED_OUT; ?>';
var ajaxTimeoutErrorMessage = '<?php echo JS_ERROR_AJAX_TIMEOUT; ?>';

var noShippingSelectedError = '<?php echo ERROR_NO_SHIPPING_SELECTED; ?>';

var flagOnSubmit = <?php echo ($flagOnSubmit) ? 'true' : 'false'; ?>;

var shippingTimeout = <?php echo (int)((defined ('CHECKOUT_ONE_SHIPPING_TIMEOUT')) ? CHECKOUT_ONE_SHIPPING_TIMEOUT : 5000); ?>;
//--></script>

<script type="text/javascript"><!--
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
            console.log(message);
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
function setJavaScriptEnabled()
{
    document.getElementById( 'javascript-enabled' ).value = '1';
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
    var shippingAddress = document.getElementById('checkoutOneShipto');
    if (shippingAddress) {
        if (document.getElementById('shipping_billing').checked) {
            shippingAddress.className = 'hiddenField';
            shippingAddress.setAttribute('className', 'hiddenField'); 
        } else {
            shippingAddress.className = 'visibleField';
            shippingAddress.setAttribute('className', 'visibleField');
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
    zcLog2Console('Setting orderConfirmed ('+value+'), submitter ('+submitter+')');
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
    
    setOrderConfirmed(0);
    jQuery( '#checkoutOneShippingFlag' ).show();
    
    zcLog2Console( 'jQuery version: '+jQuery().jquery );
    
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
<?php
    // -----
    // If the current order has generated shipping quotes (i.e. it's got at least one physical product), check to see if a 
    // shipping-module has required inputs that should accompany the post, format the necessary jQuery to gather those inputs.
    //
    if (isset ($quotes) && is_array ($quotes)) {
        $additional_shipping_inputs = array ();
        foreach ($quotes as $current_quote) {
            if (isset ($current_quote['required_input_names']) && is_array ($current_quote['required_input_names'])) {
                foreach ($current_quote['required_input_names'] as $current_input_name => $selection_required) {
                    $variable_name = base::camelize ($current_input_name);
?>
            var <?php echo $variable_name; ?> = jQuery( "input[name=<?php echo $current_input_name; ?>]<?php echo ($selection_required) ? ':checked' : ''; ?>" ).val();
<?php
                    $additional_shipping_inputs[$current_input_name] = $variable_name;
                }
            }
        }
    }
?>
            zcLog2Console( 'Updating shipping method to '+shippingSelected+', processing type: '+type );
            zcJS.ajax({
                url: "ajax.php?act=ajaxOnePageCheckout&method=updateShipping",
                data: {
                    shipping: shippingSelected,
                    shipping_is_billing: shippingIsBilling,
                    shipping_request: type,
<?php
    if (count ($additional_shipping_inputs) != 0) {
        foreach ($additional_shipping_inputs as $current_input_name => $current_input_value) {
?>
                <?php echo $current_input_name;?>: <?php echo $current_input_value; ?>,
<?php
        }
    }
?>
                },
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
                jQuery( '#otshipping, #otshipping+br' ).show();
                if (response.status == 'ok') {
                    if (type == 'shipping-billing') {
                        jQuery( '#checkoutShippingChoices' ).html( response.shippingHtml );
                        jQuery( '#checkoutShippingContentChoose' ).html( response.shippingMessage );
                        jQuery( '#checkoutShippingChoices' ).on('click', 'input[name=shipping]', function( event ) {
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
        changeShippingSubmitForm('shipping-only', event);
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
    jQuery( 'input[name=payment]' ).on('change', function() {
        setFormSubmitButton();
    });
    
    // -----
    // When the form's pseudo-submit button, either "Review" or "Confirm", is clicked, the user is ready
    // to submit their order.  Set up the various "hidden" fields to reflect the order's current state,
    // note that this is an order-confirmation request, and cause the order to be submitted.
    //
    jQuery( '#opc-order-review, #opc-order-confirm' ).on('click', function( event ) {
        submitFunction(0,0); 
        setOrderConfirmed(1);

        zcLog2Console( 'Submitting order-creating form' );
        changeShippingSubmitForm( 'submit' );
    });
    
    // -----
    // Monitor the billing- and shipping-address blocks for changes.
    //
    jQuery(document).on('change', '#checkoutOneBillto input, #checkoutOneBillto select', function(event) {
        jQuery(this).addClass( 'opc-changed' );
        jQuery( '#opc-bill-cancel, #opc-bill-save' ).show();
    });
    jQuery(document).on('click', '#opc-bill-cancel', function(event) {
        restoreAddressValues('bill', '#checkoutOneBillto');
        jQuery('#opc-bill-cancel, #opc-bill-save').hide();
    });
    jQuery(document).on('click', '#opc-bill-save', function(event) {
        saveAddressValues('bill', '#checkoutOneBillto');
    });
    
    function restoreAddressValues(which, address_block)
    {
        zcLog2Console('restoreAddressValues('+which+', '+address_block+')');
        zcJS.ajax({
            url: "ajax.php?act=ajaxOnePageCheckout&method=restoreAddressValues",
            data: {
                which: which
            },
            timeout: shippingTimeout,
            error: function (jqXHR, textStatus, errorThrown) {
                zcLog2Console('error: status='+textStatus+', errorThrown = '+errorThrown+', override: '+jqXHR);
                if (textStatus == 'timeout') {
                    alert(ajaxTimeoutErrorMessage);
                }
            },
        }).done(function( response ) {
            jQuery(address_block).html(response.addressHtml);
        });
    }
    
    function saveAddressValues(which, address_block)
    {
        zcLog2Console('saveAddressValues('+which+', '+address_block+')');
        var gender = jQuery('input[name="gender['+which+']"]').val(),
            company = jQuery('input[name="company['+which+']"]').val(),
            firstname = jQuery('input[name="firstname['+which+']"]').val(),
            lastname = jQuery('input[name="lastname['+which+']"]').val(),
            street_address = jQuery('input[name="street_address['+which+']"]').val(),
            suburb = jQuery('input[name="suburb['+which+']"]').val(),
            city = jQuery('input[name="city['+which+']"]').val(),
            state = jQuery('input[name="state['+which+']"]').val(),
            zone_id = jQuery('input[name="zone_id['+which+']"]').val(),
            postcode = jQuery('input[name="postcode['+which+']"]').val(),
            zone_country_id = jQuery('select[name="zone_country_id['+which+']"] option:selected').val();

        zcJS.ajax({
            url: "ajax.php?act=ajaxOnePageCheckout&method=validateAddressValues",
            data: {
                which: which,
                gender: gender,
                company: company,
                firstname: firstname,
                lastname: lastname,
                street_address: street_address,
                suburb: suburb,
                city: city,
                state: state,
                zone_id: zone_id,
                postcode: postcode,
                zone_country_id: zone_country_id
            },
            timeout: shippingTimeout,
            error: function (jqXHR, textStatus, errorThrown) {
                zcLog2Console('error: status='+textStatus+', errorThrown = '+errorThrown+', override: '+jqXHR);
                if (textStatus == 'timeout') {
                    alert(ajaxTimeoutErrorMessage);
                }
            },
        }).done(function( response ) {
            var messageBlock = '#messages-'+which;
            if (response.messages.length != 0) {
                var focusSet = false;
                jQuery(messageBlock).html('<ul></ul>').addClass('opc-error');
                jQuery(address_block+' input, '+address_block+' select').removeClass('opc-error');
                jQuery.each(response.messages, function(field_name, message) {
                    jQuery(messageBlock+' ul').append('<li>'+message+'</li>');
                    if (jQuery('input[name="'+field_name+'['+which+']"]').length) {
                        jQuery('input[name="'+field_name+'['+which+']"]').addClass('opc-error').removeClass('opc-changed');
                        if (!focusSet) {
                            focusSet = true;
                            jQuery('input[name="'+field_name+'['+which+']"]').focus();
                        }
                    } else {
                        jQuery('select[name="'+field_name+'['+which+']"]').addClass('opc-error').removeClass('opc-changed');
                        if (!focusSet) {
                            focusSet = true;
                            jQuery('select[name="'+field_name+'['+which+']"]').focus();
                        }
                    }
                });
            } else {
                restoreAddressValues(which, address_block);
            }
        });
    }

    // -----
    // If we get here successfully, the jQuery processing for the page looks OK so we'll hide the
    // alternate-checkout link and display the "normal" one-page checkout form.
    //
    jQuery( '#checkoutPaymentNoJs' ).hide();
    jQuery( '#checkoutPayment' ).show();
});
//--></script>