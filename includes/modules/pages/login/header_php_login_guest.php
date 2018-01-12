<?php
// -----
// Part of the One-Page Checkout plugin, provided under GPL 2.0 license by lat9 (cindy@vinosdefrutastropicales.com).
// Copyright (C) 2013-2018, Vinos de Frutas Tropicales.  All rights reserved.
//
// If the One-Page Checkout's "Guest Checkout" or account-registration are enabled, instruct the template-formatting 
// to disable the right and left sideboxes.
//
$block_error = false;
if (isset($_SESSION['opc']) && $_SESSION['opc']->temporaryAddressesEnabled()) {
    $flag_disable_right = $flag_disable_left = true;
    
    // -----
    // This definition (to be moved to admin configuration) controls the formatting used in the display of the
    // guest-checkout enabled login screen.  The value is an encoded string, identifying which block should be
    // displayed in which column.  Columns are delimited by a semi-colon (;) and the top-to-bottom column
    // layout is in the order specified by the block-elements' left-to-right order.
    //
    // The block elements are:
    //
    // L ... (required) The email/password login block.
    // P ... (optional) The PayPal Express Checkout shortcut-button block.
    // G ... (required) The guest-checkout block.
    // C ... (required) The create-account block.
    // B ... (optional) The "Account Benefits" block.
    //
    if (!defined('CHECKOUT_ONE_LOGIN_LAYOUT')) {
        define('CHECKOUT_ONE_LOGIN_LAYOUT', 'L;P,G;C');
    }
    
    $required_blocks = array(
        'L' => true,
        'G' => true,
        'C' => true,
    );
    $column_blocks = array();
    $display_elements = explode(';', CHECKOUT_ONE_LOGIN_LAYOUT);
    $valid_blocks = explode(',', 'L,P,G,C,B');
    $num_columns = 0;
    foreach ($display_elements as $current_element) {
        $current_block = array();
        $column_elements = explode(',', $current_element);
        foreach ($column_elements as $block) {
            if (!in_array($block, $valid_blocks)) {
                $block_error = true;
            } else {
                switch ($block) {
                    case 'G':
                        if ($_SESSION['cart']->count_contents() > 0 && $_SESSION['opc']->guestCheckoutEnabled()) {
                            $current_block[] = $block;
                        }
                        break;
                    case 'P':
                        if ($ec_button_enabled) {
                            $current_block[] = $block;
                        }
                        break;
                    default:
                        $current_block[] = $block;
                        break;
                }
                unset($required_blocks[$block]);
            }
        }
        $column_blocks[] = $current_block;
        if (count($current_block) != 0) {
            $num_columns++;
        }
    }
    if ($block_error || $num_columns == 0 || count($required_blocks) != 0) {
        $block_error = true;
        trigger_error('Invalid value(s) found in CHECKOUT_ONE_LOGIN_LAYOUT (' . CHECKOUT_ONE_LOGIN_LAYOUT . ').  Guest-checkout is disabled.', E_USER_WARNING);
    }
    unset($display_elements, $valid_blocks, $current_element, $block, $current_block, $column_elements, $required_blocks);
}