<?php
/**
 * @version        2.1
 * @package        mds
 * @copyright    (C) Copyright 2010-2020 Ryan Rhode, All rights reserved.
 * @author        Ryan Rhode, ryan@milliondollarscript.com
 * @license        This program is free software; you can redistribute it and/or modify
 *        it under the terms of the GNU General Public License as published by
 *        the Free Software Foundation; either version 3 of the License, or
 *        (at your option) any later version.
 *
 *        This program is distributed in the hope that it will be useful,
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *        GNU General Public License for more details.
 *
 *        You should have received a copy of the GNU General Public License along
 *        with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *        Million Dollar Script
 *        A pixel script for selling pixels on your website.
 *
 *        For instructions see README.txt
 *
 *        Visit our website for FAQs, documentation, a list team members,
 *        to post any bugs or feature requests, and a community forum:
 *        https://milliondollarscript.com/
 *
 */
require_once __DIR__ . "/../config.php";

$_PAYMENT_OBJECTS['voucher'] = new voucher;

function voucher_mail_error( $msg ) {

	$date = date( "D, j M Y H:i:s O" );

	$headers = "From: " . SITE_CONTACT_EMAIL . "\r\n";
	$headers .= "Reply-To: " . SITE_CONTACT_EMAIL . "\r\n";
	$headers .= "Return-Path: " . SITE_CONTACT_EMAIL . "\r\n";
	$headers .= "X-Mailer: PHP" . "\r\n";
	$headers .= "Date: $date" . "\r\n";
	$headers .= "X-Sender-IP: " . $_SERVER['REMOTE_ADDR'] . "\r\n";

	@mail( SITE_CONTACT_EMAIL, "Error message from " . SITE_NAME . " voucher payment module. ", $msg, $headers );

}

function voucher_log_entry( $entry_line ) {
	$entry_line = "Voucher: $entry_line\r\n ";
	$log_fp     = fopen( "logs.txt", "a" );
	fputs( $log_fp, $entry_line );
	fclose( $log_fp );
}

###########################################################################
# Payment Object

class voucher {

	var $name = "Voucher";
	var $description = "Pay for your order using a voucher.";
	var $className = "voucher";

	function __construct() {
		if ( $this->is_installed() ) {

			$sql = "SELECT * FROM config WHERE 
                           `key`='VOUCHER_ENABLED'
                           ";
			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			while ( $row = mysqli_fetch_array( $result ) ) {
			    $val = isset($row['val']) ? $row['val'] : "";
				define( $row['key'], $val );
			}
		}
	}

	function install() {
		$sql = "REPLACE INTO config (`key`, val) VALUES ('VOUCHER_ENABLED', '')";
		mysqli_query( $GLOBALS['connection'], $sql );
	}

	function uninstall() {
		$sql = "DELETE FROM config WHERE `key`='VOUCHER_ENABLED'";
		mysqli_query( $GLOBALS['connection'], $sql );
	}

	function payment_button( $order_id ) {
		global $label;

		$sql = "SELECT * FROM orders WHERE order_id=" . intval( $order_id );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$order_row = mysqli_fetch_array( $result );

		?>
        <div style="text-align: center;">
          <form id="voucher" action="<?php echo htmlspecialchars( BASE_HTTP_PATH . "users/thanks.php", ENT_QUOTES ); ?>" method="get">
            <input type="hidden" name="m" value="<?php echo $this->className; ?>" />
            <input type="hidden" name="order_id" value="<?php echo $order_row['order_id']; ?>" />
            <label for="voucher_code">Voucher code:</label>
            <input type="input" name="voucher_code" id="voucher_code" required />
            <input type="submit" value="Submit" />
          </form>
        </div>
		<?php
	}

	function config_form() {

		if ( $_REQUEST['action'] == 'save' ) {
			$voucher_enabled      = $_REQUEST['voucher_enabled'];
		} else {
			$voucher_enabled      = VOUCHER_ENABLED;
		}

		?>
        <p>No settings.</p>

		<?php

	}

	function save_config() {
		
	}

	// true or false
	function is_enabled() {

		$sql = "SELECT val FROM `config` WHERE `key`='VOUCHER_ENABLED' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$row = mysqli_fetch_array( $result );
		if ( $row['val'] == 'Y' ) {
			return true;

		} else {
			return false;

		}

	}

	function is_installed() {

		$sql = "SELECT val FROM config WHERE `key`='VOUCHER_ENABLED' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
		//$row = mysqli_fetch_array($result);

		if ( mysqli_num_rows( $result ) > 0 ) {
			return true;

		} else {
			return false;

		}

	}

	function enable() {

		$sql = "UPDATE config SET val='Y' WHERE `key`='VOUCHER_ENABLED' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );

	}

	function disable() {

		$sql = "UPDATE config SET val='N' WHERE `key`='VOUCHER_ENABLED' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );

	}

	function process_payment_return() {
    global $f2;
    
		if ( ( $_REQUEST['order_id'] != '' ) ) {

			if ( $_SESSION['MDS_ID'] == '' ) {

				echo "Error: You must be logged in to view this page";

			} else {

        $order_id = intval( $_REQUEST['order_id'] );
        $voucher_id = $f2->filter($_REQUEST['voucher_code']);

        $sql = "SELECT * FROM vouchers WHERE code='" . $voucher_id . "'";
				$result = mysqli_query( $GLOBALS['connection'], $sql ) or voucher_mail_error( mysqli_error( $GLOBALS['connection'] ) . $sql );
        $voucher = mysqli_fetch_array( $result );
        
        if (empty($voucher)) {
          echo '<p>Invalid voucher code. <a href="' . BASE_HTTP_PATH . 'users/payment.php?order_id=' . $order_id . '&BID=1">Enter a different code</a>.</p>';
          exit;
        }

        if (!is_null($voucher['order_id'])) {
          echo '<p>Voucher already claimed. <a href="' . BASE_HTTP_PATH . 'users/payment.php?order_id=' . $order_id . '&BID=1">Enter a different code</a>.</p>';
          exit;
        }

				$sql = "SELECT * FROM orders WHERE order_id='" . $order_id . "'";
				$result = mysqli_query( $GLOBALS['connection'], $sql ) or voucher_mail_error( mysqli_error( $GLOBALS['connection'] ) . $sql );
        $order = mysqli_fetch_array( $result );

        if ($voucher['banner_id'] != $order['banner_id']) {
          echo '<p>Voucher not valid for this banner. <a href="' . BASE_HTTP_PATH . 'users/payment.php?order_id=' . $order_id . '&BID=1">Enter a different code</a>.</p>';
          exit;
        }

        if ($voucher['price_discount']) {
          if ($voucher['price_discount'] < $order['price']) {
            echo '<p>Voucher price is less than order total. <a href="' . BASE_HTTP_PATH . 'users/payment.php?order_id=' . $order_id . '&BID=1">Enter a different code</a>.</p>';
            exit;  
          }
        } else if ($voucher['blocks_discount']) {
          $blocks = explode(',', $order['blocks']);
          if ($voucher['blocks_discount'] < count($blocks)) {
            echo '<p>Voucher blocks are less than order total. <a href="' . BASE_HTTP_PATH . 'users/payment.php?order_id=' . $order_id . '&BID=1">Enter a different code</a>.</p>';
            exit;  
          }
        } else {
          echo '<p>Order exceeds voucher. <a href="' . BASE_HTTP_PATH . 'users/payment.php?order_id=' . $order_id . '&BID=1">Enter a different code</a>.</p>';
          exit;
        }

        $sql = "UPDATE vouchers SET order_id=" . mysqli_real_escape_string( $GLOBALS['connection'], $order['order_id']) . " WHERE `voucher_id`=" . mysqli_real_escape_string( $GLOBALS['connection'], $voucher['voucher_id']);
		    $result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );

        complete_order( $order['user_id'], $order_id );
        debit_transaction( $order_id, $order['price'], $order['currency'], 'Voucher', $voucher['code'], 'Voucher' );

				echo "Your order has been completed!";
				exit;
			}

		}

	}

}

?>