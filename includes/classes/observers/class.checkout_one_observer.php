<?php
// -----
// Part of the One-Page Checkout plugin, provided under GPL 2.0 license by lat9 (cindy@vinosdefrutastropicales.com).
// Copyright (C) 2013-2017, Vinos de Frutas Tropicales.  All rights reserved.
//
if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
}

class checkout_one_observer extends base 
{
    private $enabled = false;
            
    public function __construct() 
    {
        // -----
        // Determine if the current session browser is an Internet Explorer version less than 9 (that don't properly support
        // jQuery).
        //
        require DIR_WS_CLASSES . 'Vinos_Browser.php';
        $browser = new Vinos_Browser ();
        $unsupported_browser = ($browser->getBrowser () == Vinos_Browser::BROWSER_IE && $browser->getVersion () < 9);
        $this->browser = $browser->getBrowser() . '::' . $browser->getVersion ();
        
        // -----
        // The 'opctype' variable is applied to the checkout_shipping page's link by the checkout_one page's alternate link
        // (available if there's a jQuery error affecting that page's ability to perform a 1-page checkout).
        //
        // If that's set, set a session variable to override the OPC processing, allowing the customer to check out!  As a
        // developer "assist", that value can be reset by supplying &opctype=retry to any link to try again.
        //
        if (isset($_GET['opctype'])) {
            if ($_GET['opctype'] == 'jserr') {
                $_SESSION['opc_error'] = true;
            }
            if ($_GET['opctype'] == 'retry') {
                unset($_SESSION['opc_error']);
            }
        }
        
        // -----
        // Determine whether the OPC should be enabled.  It's enabled if:
        //
        // - No previous jQuery error on the checkout_one page has been detected.
        // - The plugin's database configuration is available and set for either
        //   - Full enablement
        //   - Conditional enablement and the current customer is in the conditional-customers list
        //
        $plugin_enabled = false;
        if (defined('CHECKOUT_ONE_ENABLED') && !isset($_SESSION['opc_error'])) {
            if (CHECKOUT_ONE_ENABLED == 'true') {
                $plugin_enabled = true;
            } elseif (CHECKOUT_ONE_ENABLED == 'conditional' && isset ($_SESSION['customer_id'])) {
                if (in_array ($_SESSION['customer_id'], explode (',', str_replace (' ', '', CHECKOUT_ONE_ENABLE_CUSTOMERS_LIST)))) {
                    $plugin_enabled = true;
                }
            }
        }
        
        // -----
        // If the current browser is supported and the plugin's environment is supportable, then the processing for the
        // OPC is enabled.  We'll attach notifiers to the various elements of the 3-page checkout to consolidate that
        // processing into a single page.
        //
        if (!$unsupported_browser && $plugin_enabled) {
            $this->enabled = true;
            $this->debug = (CHECKOUT_ONE_DEBUG == 'true' || CHECKOUT_ONE_DEBUG == 'full');
            if ($this->debug && CHECKOUT_ONE_DEBUG_EXTRA != '' && CHECKOUT_ONE_DEBUG_EXTRA != '*') {
                $debug_customers = explode (',', CHECKOUT_ONE_DEBUG_EXTRA);
                if (!in_array ($_SESSION['customer_id'], $debug_customers)) {
                    $this->debug = false;
                }
            }
            $this->debug_logfile = DIR_FS_LOGS . '/myDEBUG-one_page_checkout-' . $_SESSION['customer_id'] . '.log';
            $this->current_page_base = $GLOBALS['current_page_base'];
                    
            $this->attach(
                $this, 
                array(
                    'NOTIFY_HEADER_START_CHECKOUT_SHIPPING', 
                    'NOTIFY_HEADER_START_CHECKOUT_PAYMENT', 
                    'NOTIFY_HEADER_START_CHECKOUT_SHIPPING_ADDRESS', 
                    'NOTIFY_HEADER_START_CHECKOUT_CONFIRMATION', 
                    'NOTIFY_HEADER_END_CHECKOUT_SUCCESS',
                    'NOTIFY_HEADER_START_ADDRESS_BOOK_PROCESS',
                )
            );
            
            // -----
            // If the OPC's guest-checkout is enabled, watch for guest-related events, too.
            //
            if ($_SESSION['opc']->setGuestCheckoutEnabled()) {    
                $this->attach(
                    $this, 
                    array(
                        'NOTIFY_HEADER_START_CREATE_ACCOUNT',
                        'NOTIFY_PROCESS_3RD_PARTY_LOGINS',
                    )
                );
            }
        }
    }
  
    public function update(&$class, $eventID, $p1, &$p2, &$p3, &$p4, &$p5, &$p6, &$p7) 
    {
        switch ($eventID) {     
            case 'NOTIFY_HEADER_START_CHECKOUT_SHIPPING':
            case 'NOTIFY_HEADER_START_CHECKOUT_PAYMENT':
            case 'NOTIFY_HEADER_START_CHECKOUT_CONFIRMATION':
                $this->debug_message('checkout_one redirect: ', true, 'checkout_one_observer');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_ONE, zen_get_all_get_params(), 'SSL'));
                break;
                
            case 'NOTIFY_HEADER_START_CHECKOUT_SHIPPING_ADDRESS':
                $_SESSION['shipping_billing'] = false;
                break;
      
            case 'NOTIFY_HEADER_END_CHECKOUT_SUCCESS':
                unset($_SESSION['shipping_billing'], $_SESSION['opc_sendto_saved']);
                break;
                
            case 'NOTIFY_HEADER_START_CREATE_ACCOUNT':
                zen_redirect(zen_href_link(FILENAME_REGISTER, '', 'SSL'));
                break;
                
            // -----
            // If the customer has just added an address, force that address to be the
            // primary if the customer currently has no permanent addresses.
            //
            case 'NOTIFY_HEADER_START_ADDRESS_BOOK_PROCESS':
                if (isset($_SESSION['customer_id']) && $_SESSION['customer_id'] != 0) {
                    if (isset($_POST['action']) && $_POST['action'] == 'process') {
                        $check = $GLOBALS['db']->Execute(
                            "SELECT address_book_id
                               FROM " . TABLE_ADDRESS_BOOK . "
                              WHERE customers_id = " . (int)$_SESSION['customer_id'] . "
                                AND address_type = " . OnePageCheckout::ADDRESS_TYPE_REGULAR . "
                              LIMIT 1"
                        );
                        if ($check->EOF) {
                            $_POST['primary'] = 'on';
                        }
                    }
                }
                break;
                
            // -----
            // Parameters:
            //
            // $p1 ... Supplied email address
            // $p2 ... Supplied password
            // $p3 ... Boolean flag, set to true if the login is to be authorized.
            //
            case 'NOTIFY_PROCESS_3RD_PARTY_LOGINS':
                if ($p3 === true) {
                    $_SESSION['opc']->setAccountTypeByEmail($p1);
                }
                break;
 
            default:
                break;
        }
    }
    
    public function debug_message($message, $include_request = false, $other_caller = '')
    {
        if ($this->debug) {
            $extra_info = '';
            if ($include_request) {
                $the_request = $_REQUEST;
                foreach ($the_request as $name => $value) {
                    if (strpos($name, 'cc_number') !== false || strpos($name, 'cc_cvv') !== false || strpos($name, 'card-number') !== false || strpos($name, 'cv2-number') !== false) {
                        unset($the_request[$name]);
                    }
                }
                $extra_info = print_r($the_request, true);
            }
            
            // -----
            // Change any occurrences of [code] to ["code"] in the logs so that they can be properly posted between [CODE} tags on the Zen Cart forums.
            //
            $message = str_replace('[code]', '["code"]', $message);
            error_log(date('Y-m-d H:i:s') . ' ' . (($other_caller != '') ? $other_caller : $this->current_page_base) . ": $message$extra_info" . PHP_EOL, 3, $this->debug_logfile);
            $this->notify($message);
        }
    }
    
    public function hashSession($current_order_total)
    {
        $session_data = $_SESSION;
        if (isset($session_data['shipping'])) {
           unset($session_data['shipping']['extras']);
        }
        unset($session_data['shipping_billing'], $session_data['comments'], $session_data['navigation']);
        
        // -----
        // The ot_gv order-total in Zen Cart 1.5.4 sets its session-variable to either 0 or '0.00', which results in
        // false change-detection by this function.  As such, if the order-total's variable is present in the session
        // and is 0, set the variable to a "known" representation of 0!
        //
        if (isset($session_data['cot_gv']) && $session_data['cot_gv'] == 0) {
            $session_data['cot_gv'] = '0.00';
        }
        
        // -----
        // Some of the payment methods (e.g. ceon_manual_card) and possibly shipping/order_totals update
        // information into the session upon their processing ... and ultimately cause the hash on entry
        // to be different from the hash on exit.  Simply update the following list with the variables that
        // can be safely ignored in the hash.
        //
        unset (
            $session_data['ceon_manual_card_card_holder'],
            $session_data['ceon_manual_card_card_type'],
            $session_data['ceon_manual_card_card_expiry_month'],
            $session_data['ceon_manual_card_card_expiry_year'],
            $session_data['ceon_manual_card_card_cv2_number_not_present'],
            $session_data['ceon_manual_card_card_start_month'],
            $session_data['ceon_manual_card_card_start_year'],
            $session_data['ceon_manual_card_card_issue_number'],
            $session_data['ceon_manual_card_data_entered']
        );
        
        // -----
        // Add the order's current total to the blob that's being hashed, so that changes in the total based on
        // payment-module selection can be properly detected (e.g. COD fee).
        //
        // Some currenciues use a non-ASCII symbol for its symbol, e.g. £.  To ensure that we don't get into
        // a checkout-loop, make sure that the order's current total is scrubbed to convert any "HTML entities"
        // into their character representation.
        //
        // This is needed since the order's current total, as passed into the confirmation page, is created by
        // javascript that captures the character representation of any symbols.
        //
        $session_data['order_current_total'] = html_entity_decode($current_order_total, ENT_COMPAT, CHARSET);
        
        // -----
        // If the order's current total is 0 (which it will be after a 100% coupon), don't include the session's
        // defined payment method, as that might change.
        //
        if (preg_replace('/\D+/', '', $current_order_total) == 0) {
            unset($session_data['payment']);
        }
        
        $hash_values = var_export($session_data, true);
        $this->debug_message("hashSession returning an md5 of $hash_values", false, 'checkout_one_observer');
        return md5($hash_values);
    }
    
    public function isOrderFreeShipping($country_override = false)
    {
        global $order, $db;
        
        $free_shipping = false;
        $pass = false;
        if (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true') {
            if ($country_override === false) {
                $order_country = $order->delivery['country_id'];
            } else {
                $country_check = $db->Execute(
                    "SELECT entry_country_id 
                       FROM " . TABLE_ADDRESS_BOOK . " 
                      WHERE address_book_id = " . (int)$_SESSION['sendto'] . " 
                      LIMIT 1"
                );
                $order_country = ($country_check->EOF) ? 0 : $country_check->fields['entry_country_id'];
            }
            switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
                case 'national':
                    if ($order_country == STORE_COUNTRY) {
                        $pass = true;
                    }
                    break;

                case 'international':
                    if ($order_country != STORE_COUNTRY) {
                        $pass = true;
                    }
                    break;

                case 'both':
                    $pass = true;
                    break;

            }

            if ($pass && $_SESSION['cart']->show_total() >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER) {
                $free_shipping = true;
            }
        }
        return $free_shipping;
    }
    
    public function isEnabled()
    {
        return $this->enabled;
    }

}