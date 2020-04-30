<?php
/**
 * @package        mds
 * @copyright    (C) Copyright 2020 Ryan Rhode, All rights reserved.
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

use Imagine\Filter\Basic\Autorotate;

require_once( 'area_map_functions.php' );
require_once( 'package_functions.php' );
require_once( 'banner_functions.php' );
require_once( 'image_functions.php' );
require_once( __DIR__ . '/../vendor/autoload.php' );

$banner_data = load_banner_constants( $BID );

if ( ! defined( 'UPLOAD_PATH' ) ) {
	$dir   = dirname( __FILE__ );
	$dir   = preg_split( '%[/\\\]%', $dir );
	$blank = array_pop( $dir );
	$dir   = implode( '/', $dir );
	define( 'UPLOAD_PATH', $dir . '/upload_files/' );

}

if ( ! defined( 'UPLOAD_HTTP_PATH' ) ) {

	$host     = $_SERVER['SERVER_NAME']; // hostname
	$http_url = $_SERVER['PHP_SELF']; // eg /ojo/admin/edit_config.php
	$http_url = explode( "/", $http_url );
	array_pop( $http_url ); // get rid of filename
	array_pop( $http_url ); // get rid of /admin
	$http_url = implode( "/", $http_url );

	define( 'UPLOAD_HTTP_PATH', "http://" . $host . $http_url . "/upload_files/" );
}

#---------------------------------------------------------------------
# Written for having magic quotes enabled
#---------------------------------------------------------------------
#####################################################
function unfck( $v ) {
	return is_array( $v ) ? array_map( 'unfck', $v ) : addslashes( $v );
}

######################################################
function unfck_gpc() {

	foreach ( array( 'POST', 'GET', 'REQUEST', 'COOKIE' ) as $gpc ) {
		$GLOBALS["_$gpc"] = array_map( 'unfck', $GLOBALS["_$gpc"] );

	}

}

##################################################

if ( isset( $_REQUEST['BID'] ) && ! empty( $_REQUEST['BID'] ) ) {
	if ( ! defined( 'NO_HOUSE_KEEP' ) || NO_HOUSE_KEEP != 'YES' ) {
		expire_orders();
	}
}

function expire_orders() {

	$now       = ( gmdate( "Y-m-d H:i:s" ) );
	$unix_time = time();

	// get the time of last run
	$sql = "SELECT * FROM `config` where `key` = 'LAST_EXPIRE_RUN' ";
	$result = @mysqli_query( $GLOBALS['connection'], $sql ) or $DB_ERROR = mysqli_error( $GLOBALS['connection'] );
	$t_row = @mysqli_fetch_array( $result );

	if ( isset( $DB_ERROR ) && $DB_ERROR != '' ) {
		return $DB_ERROR;
	}

	// Poor man's lock
	$sql = "UPDATE `config` SET `val`='YES' WHERE `key`='EXPIRE_RUNNING' AND `val`='NO' ";
	$result = @mysqli_query( $GLOBALS['connection'], $sql ) or $DB_ERROR = mysqli_error( $GLOBALS['connection'] );
	if ( @mysqli_affected_rows( $GLOBALS['connection'] ) == 0 ) {

		// make sure it cannot be locked for more than 30 secs
		// This is in case the proccess fails inside the lock
		// and does not release it.

		if ( $unix_time > $t_row['val'] + 30 ) {
			// release the lock

			$sql = "UPDATE `config` SET `val`='NO' WHERE `key`='EXPIRE_RUNNING' ";
			$result = @mysqli_query( $GLOBALS['connection'], $sql ) or $DB_ERROR = mysqli_error( $GLOBALS['connection'] );

			// update timestamp
			$sql = "REPLACE INTO config (`key`, `val`) VALUES ('LAST_EXPIRE_RUN', '$unix_time')  ";
			$result = @mysqli_query( $GLOBALS['connection'], $sql ) or $DB_ERROR = mysqli_error( $GLOBALS['connection'] );
		}

		// this function is already executing in another process.
		return;
	}

	// did 1 minute elapse since last run?
	if ( $unix_time > $t_row['val'] + 60 ) {

		// Delete Temp Orders

		$session_duration = 3600;

		$sql = "SELECT session_id, order_date FROM `temp_orders` WHERE  DATE_SUB('$now', INTERVAL $session_duration SECOND) >= temp_orders.order_date AND session_id <> '" . mysqli_real_escape_string( $GLOBALS['connection'], session_id() ) . "' ";

		$result = mysqli_query( $GLOBALS['connection'], $sql );

		while ( $row = @mysqli_fetch_array( $result ) ) {

			delete_temp_order( $row['session_id'] );

		}

		// COMPLTED Orders

		$sql = "SELECT *, banners.banner_id as BID from orders, banners where status='completed' and orders.banner_id=banners.banner_id AND orders.days_expire <> 0 AND DATE_SUB('$now', INTERVAL orders.days_expire DAY) >= orders.date_published AND orders.date_published IS NOT NULL";

		//echo $sql;

		$result = mysqli_query( $GLOBALS['connection'], $sql );

		$affected_BIDs = array();

		while ( $row = @mysqli_fetch_array( $result ) ) {
			$affected_BIDs[] = $row['BID'];
			expire_order( $row['order_id'] );

		}
		if ( sizeof( $affected_BIDs ) > 0 ) {
			foreach ( $affected_BIDs as $myBID ) {
				$b_row = load_banner_row( $myBID );
				if ( $b_row['auto_publish'] == 'Y' ) {
					process_image( $myBID );
					publish_image( $myBID );
					process_map( $myBID );
				}

			}
		}
		process_paid_renew_orders();
		unset( $affected_BIDs );

		// unconfirmed Orders

		if ( HOURS_UNCONFIRMED != 0 ) {

			$sql = "SELECT * from orders where (status='new') AND DATE_SUB('$now',INTERVAL " . intval( HOURS_UNCONFIRMED ) . " HOUR) >= date_stamp AND date_stamp IS NOT NULL ";

			$result = @mysqli_query( $GLOBALS['connection'], $sql );

			while ( $row = @mysqli_fetch_array( $result ) ) {
				delete_order( $row['order_id'] );

				// Now really delete the order.

				$sql = "delete from orders where order_id='" . intval( $row['order_id'] ) . "'";
				@mysqli_query( $GLOBALS['connection'], $sql );
				global $f2;
				$f2->debug( "Deleted unconfirmed order - " . $sql );

			}

		}

		// unpaid Orders
		if ( DAYS_CONFIRMED != 0 ) {
			$sql = "SELECT * from orders where (status='new' OR status='confirmed') AND DATE_SUB('$now',INTERVAL " . intval( DAYS_CONFIRMED ) . " DAY) >= date_stamp AND date_stamp IS NOT NULL ";

			$result = @mysqli_query( $GLOBALS['connection'], $sql );

			while ( $row = @mysqli_fetch_array( $result ) ) {
				expire_order( $row['order_id'] );

			}

		}

		// EXPIRED Orders -> Cancel

		if ( DAYS_RENEW != 0 ) {

			$sql = "SELECT * from orders where status='expired' AND DATE_SUB('$now',INTERVAL " . intval( DAYS_RENEW ) . " DAY) >= date_stamp AND date_stamp IS NOT NULL";

			$result = @mysqli_query( $GLOBALS['connection'], $sql );

			while ( $row = @mysqli_fetch_array( $result ) ) {
				cancel_order( $row['order_id'] );

			}

		}

		// Cancelled Orders -> Delete

		if ( DAYS_CANCEL != 0 ) {

			$sql = "SELECT * from orders where status='cancelled' AND DATE_SUB('$now',INTERVAL " . intval( DAYS_CANCEL ) . " DAY) >= date_stamp AND date_stamp IS NOT NULL ";

			$result = @mysqli_query( $GLOBALS['connection'], $sql );

			while ( $row = @mysqli_fetch_array( $result ) ) {
				delete_order( $row['order_id'] );
			}

		}

		// update last run time stamp

		// update timestamp
		$sql = "REPLACE INTO config (`key`, `val`) VALUES ('LAST_EXPIRE_RUN', '$unix_time')  ";
		$result = @mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	}

	// release the poor man's lock
	$sql = "UPDATE `config` SET `val`='NO' WHERE `key`='EXPIRE_RUNNING' ";
	@mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

}

#################################################

function delete_temp_order( $sid, $delete_ad = true ) {

	$sid = mysqli_real_escape_string( $GLOBALS['connection'], $sid );

	$sql = "select * from temp_orders where session_id='" . $sid . "' ";
	$order_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$order_row = mysqli_fetch_array( $order_result );

	//$sql = "DELETE FROM blocks WHERE session_id='".$sid."' ";
	//mysqli_query($GLOBALS['connection'], $sql) ;

	$sql = "DELETE FROM temp_orders WHERE session_id='" . $sid . "' ";
	mysqli_query( $GLOBALS['connection'], $sql );

	if ( $delete_ad ) {
		$sql = "DELETE FROM ads WHERE ad_id='" . intval( $order_row['ad_id'] ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql );
	}

	// delete the temp order image... and block info...

	$f = get_tmp_img_name( $sid );
	if ( file_exists( $f ) ) {
		unlink( $f );
	}
}

#################################################
/*

Type:  CREDIT (subtract)

$txn_id = transaction id from 3rd party payment system

$reson = any reason such as chargeback, refund etc..

$origin = paypal, stormpay, admin, etc

$order_id = the corresponding order id.

*/

function credit_transaction( $order_id, $amount, $currency, $txn_id, $reason, $origin ) {

	$type = "CREDIT";

	$date = ( gmdate( "Y-m-d H:i:s" ) );

	$sql = "SELECT * FROM transactions where txn_id='" . intval( $txn_id ) . "' and `type`='CREDIT' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $sql ) );
	if ( mysqli_num_rows( $result ) != 0 ) {
		return; // there already is a credit for this txn_id
	}

// check to make sure that there is a debit for this transaction

	$sql = "SELECT * FROM transactions where txn_id='" . intval( $txn_id ) . "' and `type`='DEBIT' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $sql ) );
	if ( mysqli_num_rows( $result ) > 0 ) {

		$sql = "INSERT INTO transactions (`txn_id`, `date`, `order_id`, `type`, `amount`, `currency`, `reason`, `origin`) VALUES('" . intval( $txn_id ) . "', '$date', '" . intval( $order_id ) . "', '$type', '" . floatval( $amount ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $currency ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $reason ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $origin ) . "')";

		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	}

}

#################################################
/*

Type: DEBIT (add)

$txn_id = transaction id from 3rd party payment system

$reson = any reason such as chargeback, refund etc..

$origin = paypal, stormpay, admin, etc

$order_id = the corresponding order id.

*/

function debit_transaction( $order_id, $amount, $currency, $txn_id, $reason, $origin ) {

	$type = "DEBIT";
	$date = ( gmdate( "Y-m-d H:i:s" ) );
// check to make sure that there is no debit for this transaction already

	$sql = "SELECT * FROM transactions where txn_id='" . intval( $txn_id ) . "' and `type`='DEBIT' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
	if ( mysqli_fetch_array( $result ) == 0 ) {
		$sql = "INSERT INTO transactions (`txn_id`, `date`, `order_id`, `type`, `amount`, `currency`, `reason`, `origin`) VALUES('" . intval( $txn_id ) . "', '$date', '" . intval( $order_id ) . "', '$type', '" . floatval( $amount ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $currency ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $reason ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $origin ) . "')";

		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	}

}

##################################################

function complete_order( $user_id, $order_id ) {
	global $label;

	$sql = "SELECT * from orders where order_id='" . intval( $order_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$order_row = mysqli_fetch_array( $result );

	if ( $order_row['status'] != 'completed' ) {

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE orders set status='completed', date_published=NULL, date_stamp='$now' WHERE order_id='" . intval( $order_id ) . "'";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		// insert a transaction

		// mark pixels as sold.

		$sql = "SELECT * from orders where order_id='" . intval( $order_id ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$order_row = mysqli_fetch_array( $result );

		if ( strpos( $order_row['blocks'], "," ) !== false ) {
			$blocks = explode( ",", $order_row['blocks'] );
		} else {
			$blocks = array( 0 => $order_row['blocks'] );
		}
		foreach ( $blocks as $key => $val ) {
			$sql = "UPDATE blocks set status='sold' where block_id='" . intval( $val ) . "' and banner_id=" . intval( $order_row['banner_id'] );
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		}

		$sql = "SELECT * from users where ID='" . intval( $user_id ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$user_row = mysqli_fetch_array( $result );

		if ( $order_row['days_expire'] == 0 ) {
			$order_row['days_expire'] = $label['advertiser_ord_never'];
		}

		$price = convert_to_default_currency_formatted( $order_row['currency'], $order_row['price'] );

		$message = $label["order_completed_email_template"];
		$message = str_replace( "%SITE_NAME%", SITE_NAME, $message );
		$message = str_replace( "%FNAME%", $user_row['FirstName'], $message );
		$message = str_replace( "%LNAME%", $user_row['LastName'], $message );
		$message = str_replace( "%ORDER_ID%", $order_row['order_id'], $message );
		$message = str_replace( "%PIXEL_COUNT%", $order_row['quantity'], $message );
		$message = str_replace( "%PIXEL_DAYS%", $order_row['days_expire'], $message );
		$message = str_replace( "%PRICE%", $price, $message );
		$message = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $message );
		$message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $message );

		$html_message = $label["order_completed_email_template_html"];
		$html_message = str_replace( "%SITE_NAME%", SITE_NAME, $html_message );
		$html_message = str_replace( "%FNAME%", $user_row['FirstName'], $html_message );
		$html_message = str_replace( "%LNAME%", $user_row['LastName'], $html_message );
		$html_message = str_replace( "%ORDER_ID%", $order_row['order_id'], $html_message );
		$html_message = str_replace( "%PIXEL_COUNT%", $order_row['quantity'], $html_message );
		$html_message = str_replace( "%PIXEL_DAYS%", $order_row['days_expire'], $html_message );
		$html_message = str_replace( "%PRICE%", $price, $html_message );
		$html_message = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $html_message );
		$html_message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $html_message );

		$to      = trim( $user_row['Email'] );
		$subject = $label['order_completed_email_subject'];

		if ( EMAIL_USER_ORDER_COMPLETED == 'YES' ) {

			if ( USE_SMTP == 'YES' ) {
				$mail_id = queue_mail( $to, $user_row['FirstName'] . " " . $user_row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 1 );
				process_mail_queue( 2, $mail_id );
			} else {
				send_email( $to, $user_row['FirstName'] . " " . $user_row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 1 );
			}

		}

		// send a copy to admin

		if ( EMAIL_ADMIN_ORDER_COMPLETED == 'YES' ) {

			if ( USE_SMTP == 'YES' ) {
				$mail_id = queue_mail( SITE_CONTACT_EMAIL, $user_row['FirstName'] . " " . $user_row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 1 );
				process_mail_queue( 2, $mail_id );
			} else {
				send_email( SITE_CONTACT_EMAIL, $user_row['FirstName'] . " " . $user_row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 1 );
			}

		}

		// process the grid, if auto_publish is on

		$b_row = load_banner_row( $order_row['banner_id'] );

		if ( $b_row['auto_publish'] == 'Y' ) {
			process_image( $order_row['banner_id'] );
			publish_image( $order_row['banner_id'] );
			process_map( $order_row['banner_id'] );
		}

	}

}

##########################################

function confirm_order( $user_id, $order_id ) {
	global $label;

	$sql = "SELECT *, t1.blocks as BLK FROM orders as t1, users as t2 where t1.user_id=t2.ID AND order_id='" . intval( $order_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$row = mysqli_fetch_array( $result );

	if ( $row['status'] != 'confirmed' ) {

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE orders set status='confirmed', date_stamp='$now' WHERE order_id='" . intval( $order_id ) . "' ";
		//echo $sql."<br>";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		//echo "User id: ".$_SESSION['MDS_ID'];

		$_SESSION['MDS_order_id'] = ''; // destroy order id

		$sql = "UPDATE blocks set status='ordered' WHERE order_id='" . intval( $order_id ) . "' and banner_id='" . intval( $row['banner_id'] ) . "'";

		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		unset( $_SESSION['MDS_order_id'] );

		/*

		$blocks = explode (',', $row['BLK']);
		//echo $order_row['blocks'];
		foreach ($blocks as $key => $val) {

			$sql = "UPDATE blocks set status='ordered' WHERE block_id='".$val."' and banner_id='".$row['banner_id']."'";

			//echo $sql."<br>";

			mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);
		}

		*/

		if ( $row['days_expire'] == 0 ) {
			$row['days_expire'] = $label['advertiser_ord_never'];
		}

		$price = convert_to_default_currency_formatted( $row['currency'], $row['price'] );

		$message = $label["order_confirmed_email_template"];
		$message = str_replace( "%SITE_NAME%", SITE_NAME, $message );
		$message = str_replace( "%FNAME%", $row['FirstName'], $message );
		$message = str_replace( "%LNAME%", $row['LastName'], $message );
		$message = str_replace( "%ORDER_ID%", $row['order_id'], $message );
		$message = str_replace( "%PIXEL_COUNT%", $row['quantity'], $message );
		$message = str_replace( "%PIXEL_DAYS%", $row['days_expire'], $message );
		$message = str_replace( "%DEADLINE%", intval( DAYS_CONFIRMED ), $message );
		$message = str_replace( "%PRICE%", $price, $message );
		$message = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $message );
		$message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $message );

		$html_message = $label["order_confirmed_email_template_html"];
		$html_message = str_replace( "%SITE_NAME%", SITE_NAME, $html_message );
		$html_message = str_replace( "%FNAME%", $row['FirstName'], $html_message );
		$html_message = str_replace( "%LNAME%", $row['LastName'], $html_message );
		$html_message = str_replace( "%ORDER_ID%", $row['order_id'], $html_message );
		$html_message = str_replace( "%PIXEL_COUNT%", $row['quantity'], $html_message );
		$html_message = str_replace( "%PIXEL_DAYS%", $row['days_expire'], $html_message );
		$html_message = str_replace( "%DEADLINE%", intval( DAYS_CONFIRMED ), $html_message );
		$html_message = str_replace( "%PRICE%", $price, $html_message );
		$html_message = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $html_message );
		$html_message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $html_message );

		$to      = trim( $row['Email'] );
		$subject = $label['order_confirmed_email_subject'];

		if ( EMAIL_USER_ORDER_CONFIRMED == 'YES' ) {

			if ( USE_SMTP == 'YES' ) {
				$mail_id = queue_mail( $to, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 2 );
				process_mail_queue( 2, $mail_id );
			} else {
				send_email( $to, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 2 );
			}

			//@mail($to,$subject,$message,$headers);
		}

		// send a copy to admin
		if ( EMAIL_ADMIN_ORDER_CONFIRMED == 'YES' ) {

			if ( USE_SMTP == 'YES' ) {
				$mail_id = queue_mail( SITE_CONTACT_EMAIL, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 2 );
				process_mail_queue( 2, $mail_id );
			} else {
				send_email( SITE_CONTACT_EMAIL, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 2 );
			}
			//@mail(trim(SITE_CONTACT_EMAIL),$subject,$message,$headers);
		}

	}

}

##########################################

function pend_order( $user_id, $order_id ) {
	global $label;
	$sql = "SELECT * FROM orders as t1, users as t2 where t1.user_id=t2.ID AND t1.user_id='" . intval( $user_id ) . "' AND order_id='" . intval( $order_id ) . "' ";

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$row = mysqli_fetch_array( $result );

	if ( $row['status'] != 'pending' ) {

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE orders set status='pending', date_stamp='$now' WHERE order_id='" . intval( $order_id ) . "' ";
		//echo $sql;
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		$blocks = explode( ',', $row['blocks'] );
		//echo $order_row['blocks'];
		foreach ( $blocks as $key => $val ) {

			$sql = "UPDATE blocks set status='ordered' WHERE block_id='" . intval( $val ) . "' and banner_id='" . intval( $row['banner_id'] ) . "'";
			//echo $sql;
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		}

		if ( $row['days_expire'] == 0 ) {
			$row['days_expire'] = $label['advertiser_ord_never'];
		}

		$price = convert_to_default_currency_formatted( $row['currency'], $row['price'] );

		$message = $label["order_pending_email_template"];
		$message = str_replace( "%SITE_NAME%", SITE_NAME, $message );
		$message = str_replace( "%FNAME%", $row['FirstName'], $message );
		$message = str_replace( "%LNAME%", $row['LastName'], $message );
		$message = str_replace( "%ORDER_ID%", $row['order_id'], $message );
		$message = str_replace( "%PIXEL_COUNT%", $row['quantity'], $message );
		$message = str_replace( "%PIXEL_DAYS%", $row['days_expire'], $message );
		$message = str_replace( "%PRICE%", $price, $message );
		$message = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $message );
		$message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $message );

		$html_message = $label["order_pending_email_template_html"];
		$html_message = str_replace( "%SITE_NAME%", SITE_NAME, $html_message );
		$html_message = str_replace( "%FNAME%", $row['FirstName'], $html_message );
		$html_message = str_replace( "%LNAME%", $row['LastName'], $html_message );
		$html_message = str_replace( "%ORDER_ID%", $row['order_id'], $html_message );
		$html_message = str_replace( "%PIXEL_COUNT%", $row['quantity'], $html_message );
		$html_message = str_replace( "%PIXEL_DAYS%", $row['days_expire'], $html_message );
		$html_message = str_replace( "%PRICE%", $price, $html_message );
		$html_message = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $html_message );
		$html_message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $html_message );

		$to      = trim( $row['Email'] );
		$subject = $label['order_pending_email_subject'];

		if ( EMAIL_USER_ORDER_PENDED == 'YES' ) {
			if ( USE_SMTP == 'YES' ) {
				queue_mail( $to, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 3 );
			} else {
				send_email( $to, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 3 );
			}
			//@mail($to,$subject,$message,$headers);
		}

		// send a copy to admin
		if ( EMAIL_ADMIN_ORDER_PENDED == 'YES' ) {
			if ( USE_SMTP == 'YES' ) {
				$mail_id = queue_mail( SITE_CONTACT_EMAIL, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 3 );
				process_mail_queue( 2, $mail_id );
			} else {
				send_email( SITE_CONTACT_EMAIL, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 3 );
			}
			//@mail(trim(SITE_CONTACT_EMAIL),$subject,$message,$headers);
		}

	}

}

########################################################

function expire_order( $order_id ) {
	global $label;
	$sql = "SELECT *, t1.banner_id as BID, t1.user_id as UID FROM orders as t1, users as t2 where t1.user_id=t2.ID AND  order_id='" . intval( $order_id ) . "' ";
	//echo "$sql<br>";
	//days_expire

	//func_mail_error($sql." expire order");
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );

	if ( ( $row['status'] != 'expired' ) || ( $row['status'] != 'pending' ) ) {

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE orders set status='expired', date_stamp='$now' WHERE order_id='" . intval( $order_id ) . "' ";
		//echo "$sql<br>";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		$sql = "UPDATE blocks set status='ordered', `approved`='N' WHERE order_id='" . intval( $order_id ) . "' and banner_id='" . intval( $row['BID'] ) . "'";
		//echo "$sql<br>";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql . " (expire order)" );

		/*

		$blocks = explode (',', $row['blocks']);
		//echo $order_row['blocks'];
		foreach ($blocks as $key => $val) {

			$sql = "UPDATE blocks set status='ordered', `approved`='N' WHERE block_id='".$val."' and banner_id='".$row['BID']."'";
			//echo "$sql<br>";
			mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql." (expire order)");


		}

		*/

		// update approve status on orders.

		$sql = "UPDATE orders SET `approved`='N' WHERE order_id='" . intval( $order_id ) . "'";
		//echo "$sql<br>";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql . " (expire order)" );

		if ( $row['status'] == 'new' ) {
			return;// do not send email
		}

		if ( $row['days_expire'] == 0 ) {
			$row['days_expire'] = $label['advertiser_ord_never'];
		}

		$price = convert_to_default_currency_formatted( $row['currency'], $row['price'] );

		$message = $label["order_expired_email_template"];
		$message = str_replace( "%SITE_NAME%", SITE_NAME, $message );
		$message = str_replace( "%FNAME%", $row['FirstName'], $message );
		$message = str_replace( "%LNAME%", $row['LastName'], $message );
		$message = str_replace( "%ORDER_ID%", $row['order_id'], $message );
		$message = str_replace( "%PIXEL_COUNT%", $row['quantity'], $message );
		$message = str_replace( "%PIXEL_DAYS%", $row['days_expire'], $message );
		$message = str_replace( "%PRICE%", $price, $message );
		$message = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $message );
		$message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $message );

		$html_message = $label["order_expired_email_template_html"];
		$html_message = str_replace( "%SITE_NAME%", SITE_NAME, $html_message );
		$html_message = str_replace( "%FNAME%", $row['FirstName'], $html_message );
		$html_message = str_replace( "%LNAME%", $row['LastName'], $html_message );
		$html_message = str_replace( "%ORDER_ID%", $row['order_id'], $html_message );
		$html_message = str_replace( "%PIXEL_COUNT%", $row['quantity'], $html_message );
		$html_message = str_replace( "%PIXEL_DAYS%", $row['days_expire'], $html_message );
		$html_message = str_replace( "%PRICE%", $price, $html_message );
		$html_message = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $html_message );
		$html_message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $html_message );

		$to      = trim( $row['Email'] );
		$subject = $label['order_expired_email_subject'];

		if ( EMAIL_USER_ORDER_EXPIRED == 'YES' ) {
			if ( USE_SMTP == 'YES' ) {
				$mail_id = queue_mail( $to, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 4 );
				process_mail_queue( 2, $mail_id );
			} else {
				send_email( $to, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 4 );
			}
			//@mail($to,$subject,$message,$headers);
		}

		// send a copy to admin
		if ( EMAIL_ADMIN_ORDER_EXPIRED == 'YES' ) {
			if ( USE_SMTP == 'YES' ) {
				$mail_id = queue_mail( SITE_CONTACT_EMAIL, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 4 );
				process_mail_queue( 2, $mail_id );
			} else {
				send_email( SITE_CONTACT_EMAIL, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 4 );
			}
			//@mail(trim(EMAIL_ADMIN_ORDER_EXPIRED),$subject,$message,$headers);
		}

	}

}

########################################################

function delete_order( $order_id ) {

	global $label;
	$sql = "SELECT * FROM orders where order_id='" . intval( $order_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$order_row = mysqli_fetch_array( $result );

	if ( $order_row['status'] != 'deleted' ) {

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE orders set status='deleted', date_stamp='$now' WHERE order_id='" . intval( $order_id ) . "'";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		// DELETE BLOCKS

		if ( $order_row['blocks'] != '' ) {

			$sql = "DELETE FROM blocks where order_id='" . intval( $order_id ) . "' and banner_id=" . intval( $order_row['banner_id'] );
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			/*
			$blocks = explode (",", $order_row['blocks']);
			foreach ($blocks as $key => $val) {
				if ($val!='') {
					$sql = "DELETE FROM blocks where block_id='$val' and banner_id=".$order_row['banner_id'];
					mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);
				}

			}
			*/

		}

		// DELETE ADS
		if ( ! function_exists( 'delete_ads_files' ) ) {
			require_once( "ads.inc.php" );
		}
		delete_ads_files( $order_row['ad_id'] );
		$sql = "DELETE from ads where ad_id='" . intval( $order_row['ad_id'] ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

	}

}

########################################################

function cancel_order( $order_id ) {

	global $label;
	$sql = "SELECT * FROM orders where order_id='" . intval( $order_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );
	//echo $sql."<br>";
	if ( $row['status'] != 'cancelled' ) {

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE orders set status='cancelled', date_stamp='$now', approved='N' WHERE order_id='" . intval( $order_id ) . "'";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		//echo $sql."<br>";
		$sql = "UPDATE blocks set status='ordered', `approved`='N' WHERE order_id='" . intval( $order_id ) . "' and banner_id='" . intval( $row['banner_id'] ) . "'";
		//echo $sql."<br>";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql . " (cancel order) " );

		/*
		$blocks = explode (',', $row['blocks']);
		//echo $order_row['blocks'];


		foreach ($blocks as $key => $val) {
			$sql = "UPDATE blocks set status='ordered', `approved`='N' WHERE block_id='".$val."' and banner_id='".$row['banner_id']."'";
			//echo $sql."<br>";
			mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql. " (cancel order) ");
		}
		*/

	}

	// process the grid, if auto_publish is on

	$b_row = load_banner_row( $row['banner_id'] );

	if ( $b_row['auto_publish'] == 'Y' ) {

		process_image( $row['banner_id'] );
		publish_image( $row['banner_id'] );
		process_map( $row['banner_id'] );
	}

}

########################################################
# is the renewal order already paid?
# (Orders can be paid and cont be completed until the previous order expires)
function is_renew_order_paid( $original_order_id ) {

	$sql = "SELECT * from orders WHERE original_order_id='" . intval( $original_order_id ) . "' AND status='renew_paid' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	if ( mysqli_num_rows( $result ) > 0 ) {
		return true;
	} else {
		return false;
	}

}

###########################################
# returns $order_id of the 'renew_wait' order
# only one 'renew_wait' wait order allowed for each $original_order_id
# and there must be no 'renew_paid' orders
function allocate_renew_order( $original_order_id ) {

	# if no waiting renew order, insert a new one
	$now = ( gmdate( "Y-m-d H:i:s" ) );

	if ( is_renew_order_paid( $original_order_id ) ) { // cannot allocate a renew_wait, this order was already paid and waiting to be completed.
		return false;
	}
	// are there any
	// renew_wait orders?
	$sql = "SELECT * FROM orders WHERE original_order_id='" . intval( $original_order_id ) . "' and status='renew_wait' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	if ( ( $row = mysqli_fetch_array( $result ) ) == false ) {
		// copy the original order to create a new renew_wait order
		$sql = "SELECT * FROM orders WHERE order_id='" . intval( $original_order_id ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );

		$sql = "INSERT INTO orders (user_id, order_id, blocks, status, order_date, price, quantity, banner_id, currency, days_expire, date_stamp, approved, original_order_id) VALUES ('" . intval( $row['user_id'] ) . "', '', '" . intval( $row['blocks'] ) . "', 'renew_wait', NOW(), '" . floatval( $row['price'] ) . "', '" . intval( $row['quantity'] ) . "', '" . intval( $row['banner_id'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $row['currency'] ) . "', " . intval( $row['days_expire'] ) . ", '$now', '" . mysqli_real_escape_string( $GLOBALS['connection'], $row['approved'] ) . "', '" . intval( $original_order_id ) . "') ";

		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$order_id = mysqli_insert_id( $GLOBALS['connection'] );

		return $order_id;

	} else {
		return $row['order_id'];

	}

}

##########################################
# payment had been completed.
# allocate renew_wait, set it to renew_paid

function pay_renew_order( $original_order_id ) {

	$wait_order_id = allocate_renew_order( $original_order_id );
	if ( $wait_order_id !== false ) {
		$sql = "UPDATE orders set status='renew_paid' WHERE order_id='" . intval( $wait_order_id ) . "' and status='renew_wait' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	}

	if ( mysqli_affected_rows( $GLOBALS['connection'] ) > 0 ) {
		return true;
		# this order will now wait until the old one expires so it can be completed
	} else {
		return false;
	}

}

#################################

function process_paid_renew_orders() {

	/*

	Complete: Only expired orders that have status as 'renew_paid'


	*/

	$sql = "SELECT * FROM orders WHERE status='renew_paid' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	while ( $row = mysqli_fetch_array( $result ) ) {
		// if expired
		complete_renew_order( $row['order_id'] );
	}
}

########################################################

function complete_renew_order( $order_id ) {
	global $label;

	$sql = "SELECT * from orders where order_id='" . intval( $order_id ) . "' and status='renew_paid' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$order_row = mysqli_fetch_array( $result );

	if ( $order_row['status'] != 'completed' ) {

		$now = ( gmdate( "Y-m-d H:i:s" ) );

		$sql = "UPDATE orders set status='completed', date_published=NULL, date_stamp='$now' WHERE order_id=" . intval( $order_id );
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		// update pixel's order_id

		$sql = "UPDATE blocks SET order_id='" . intval( $order_row['order_id'] ) . "' WHERE order_id='" . intval( $order_row['original_order_id'] ) . "' AND banner_id='" . intval( $order_row['banner_id'] ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		// update ads' order id

		$sql = "UPDATE ads SET order_id='" . intval( $order_row['order_id'] ) . "' WHERE order_id='" . intval( $order_row['original_order_id'] ) . "' AND banner_id='" . intval( $order_row['banner_id'] ) . "' ";
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

		// mark pixels as sold.

		$sql = "SELECT * from orders where order_id='" . intval( $order_id ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$order_row = mysqli_fetch_array( $result );

		if ( strpos( $order_row['blocks'], "," ) !== false ) {
			$blocks = explode( ",", $order_row['blocks'] );
		} else {
			$blocks = array( 0 => $order_row['blocks'] );
		}
		foreach ( $blocks as $key => $val ) {
			$sql = "UPDATE blocks set status='sold' where block_id='" . intval( $val ) . "' and banner_id=" . intval( $order_row['banner_id'] );
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		}

		$sql = "SELECT * from users where ID='" . intval( $order_row['user_id'] . "'" );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$user_row = mysqli_fetch_array( $result );

		if ( $order_row['days_expire'] == 0 ) {
			$order_row['days_expire'] = $label['advertiser_ord_never'];
		}

		$price = convert_to_default_currency_formatted( $order_row['currency'], $order_row['price'] );

		$message = $label["order_completed_renewal_email_template"];
		$message = str_replace( "%SITE_NAME%", SITE_NAME, $message );
		$message = str_replace( "%FNAME%", $user_row['FirstName'], $message );
		$message = str_replace( "%LNAME%", $user_row['LastName'], $message );
		$message = str_replace( "%ORDER_ID%", $order_row['order_id'], $message );
		$message = str_replace( "%ORIGINAL_ORDER_ID%", $order_row['original_order_id'], $message );
		$message = str_replace( "%PIXEL_COUNT%", $order_row['quantity'], $message );
		$message = str_replace( "%PIXEL_DAYS%", $order_row['days_expire'], $message );
		$message = str_replace( "%PRICE%", $price, $message );
		$message = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $message );
		$message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $message );

		$html_message = $label["order_completed_renewal_email_template_html"];
		$html_message = str_replace( "%SITE_NAME%", SITE_NAME, $html_message );
		$html_message = str_replace( "%FNAME%", $user_row['FirstName'], $html_message );
		$html_message = str_replace( "%LNAME%", $user_row['LastName'], $html_message );
		$html_message = str_replace( "%ORDER_ID%", $order_row['order_id'], $html_message );
		$html_message = str_replace( "%ORIGINAL_ORDER_ID%", $order_row['original_order_id'], $html_message );
		$html_message = str_replace( "%PIXEL_COUNT%", $order_row['quantity'], $html_message );
		$html_message = str_replace( "%PIXEL_DAYS%", $order_row['days_expire'], $html_message );
		$html_message = str_replace( "%PRICE%", $price, $html_message );
		$html_message = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $html_message );
		$html_message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $html_message );

		$to      = trim( $user_row['Email'] );
		$subject = $label['order_completed_email_subject'];

		if ( EMAIL_USER_ORDER_COMPLETED == 'YES' ) {

			if ( USE_SMTP == 'YES' ) {
				$mail_id = queue_mail( $to, $user_row['FirstName'] . " " . $user_row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 8 );
				process_mail_queue( 2, $mail_id );
			} else {
				send_email( $to, $user_row['FirstName'] . " " . $user_row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 1 );
			}

		}

		// send a copy to admin

		if ( EMAIL_ADMIN_ORDER_COMPLETED == 'YES' ) {

			if ( USE_SMTP == 'YES' ) {
				$mail_id = queue_mail( SITE_CONTACT_EMAIL, $user_row['FirstName'] . " " . $user_row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 8 );
				process_mail_queue( 2, $mail_id );
			} else {
				send_email( SITE_CONTACT_EMAIL, $user_row['FirstName'] . " " . $user_row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_message, 1 );
			}

		}

		// process the grid, if auto_publish is on

		$b_row = load_banner_row( $order_row['banner_id'] );

		if ( $b_row['auto_publish'] == 'Y' ) {
			process_image( $order_row['banner_id'] );
			publish_image( $order_row['banner_id'] );
			process_map( $order_row['banner_id'] );
		}

	}

}

#####################################################

function send_confirmation_email( $email ) {

	global $label;

	$sql    = "SELECT * FROM users where Email='" . mysqli_real_escape_string( $GLOBALS['connection'], $email ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql );
	$row    = mysqli_fetch_array( $result );

	$code = substr( md5( $row['Email'] . $row['Password'] ), 0, 8 );

	$verify_url = BASE_HTTP_PATH . "users/validate.php?lang=" . get_lang() . "&email=" . $row['Email'] . "&code=$code";

	$message = $label["confirmation_email_templaltev2"];
	$message = str_replace( "%FNAME%", $row['FirstName'], $message );
	$message = str_replace( "%LNAME%", $row['LastName'], $message );
	$message = str_replace( "%USERNAME%", $row['Username'], $message );
	$message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $message );
	$message = str_replace( "%SITE_NAME%", SITE_NAME, $message );
	$message = str_replace( "%VERIFY_URL%", $verify_url, $message );
	$message = str_replace( "%VALIDATION_CODE%", $code, $message );

	$html_msg = $label["confirmation_email_templaltev2_html"];
	$html_msg = str_replace( "%FNAME%", $row['FirstName'], $html_msg );
	$html_msg = str_replace( "%LNAME%", $row['LastName'], $html_msg );
	$html_msg = str_replace( "%USERNAME%", $row['Username'], $html_msg );
	$html_msg = str_replace( "%SITE_URL%", BASE_HTTP_PATH . "users/", $html_msg );
	$html_msg = str_replace( "%SITE_NAME%", SITE_NAME, $html_msg );
	$html_msg = str_replace( "%VERIFY_URL%", $verify_url, $html_msg );
	$html_msg = str_replace( "%VALIDATION_CODE%", $code, $html_msg );

	$to = trim( $row['Email'] );

	$subject = $label['confirmation_email_subject'];

	if ( USE_SMTP == 'YES' ) {
		$mail_id = queue_mail( $to, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_msg, 5 );
		process_mail_queue( 2, $mail_id );
	} else {
		send_email( $to, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_msg, 5 );
	}

	if ( EMAIL_ADMIN_ACTIVATION == 'YES' ) {

		if ( USE_SMTP == 'YES' ) {
			$mail_id = queue_mail( SITE_CONTACT_EMAIL, SITE_NAME, SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_msg, 5 );
			process_mail_queue( 2, $mail_id );
		} else {
			send_email( SITE_CONTACT_EMAIL, SITE_NAME, SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_msg, 5 );
		}

	}

}

########################################################

function send_published_pixels_notification( $user_id, $BID ) {

	global $label;

	$subject = $label['publish_pixels_email_subject'];
	$subject = str_replace( "%SITE_NAME%", SITE_NAME, $subject );

	$sql = "SELECT * from banners where banner_id='" . intval( $BID ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$b_row = mysqli_fetch_array( $result );

	$sql = "SELECT * from users where ID='" . intval( $user_id ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$u_row = mysqli_fetch_array( $result );

	$sql = "SELECT  url, alt_text FROM blocks where user_id='" . intval( $user_id ) . "' AND banner_id='" . intval( $BID ) . "' GROUP by url, alt_text ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$url_list = '';
	while ( $row = mysqli_fetch_array( $result ) ) {
		$url_list .= $row['url'] . " - " . $row['alt_text'] . "\n";
	}

	$arr          = explode( "/", SERVER_PATH_TO_ADMIN );
	$admin_folder = array_pop( $arr );

	$view_url = BASE_HTTP_PATH . $admin_folder . "/remote_admin.php?key=" . substr( md5( ADMIN_PASSWORD ), 1, 15 ) . "&user_id=$user_id&BID=$BID";

	$msg = $label['publish_pixels_email_template'];
	$msg = str_replace( "%SITE_NAME%", SITE_NAME, $msg );
	$msg = str_replace( "%GRID_NAME%", $b_row['name'], $msg );
	$msg = str_replace( "%MEMBERID%", $u_row['Username'], $msg );
	$msg = str_replace( "%URL_LIST%", $url_list, $msg );
	$msg = str_replace( "%VIEW_URL%", $view_url, $msg );

	$html_msg = $label['publish_pixels_email_template_html'];
	$html_msg = str_replace( "%SITE_NAME%", SITE_NAME, $html_msg );
	$html_msg = str_replace( "%GRID_NAME%", $b_row['name'], $html_msg );
	$html_msg = str_replace( "%MEMBERID%", $u_row['Username'], $html_msg );
	$html_msg = str_replace( "%URL_LIST%", $url_list, $html_msg );
	$html_msg = str_replace( "%VIEW_URL%", $view_url, $html_msg );

	if ( USE_SMTP == 'YES' ) {
		$mail_id = queue_mail( SITE_CONTACT_EMAIL, 'Admin', SITE_CONTACT_EMAIL, SITE_NAME, $subject, $msg, $html_msg, 7 );
		process_mail_queue( 2, $mail_id );
	} else {
		send_email( SITE_CONTACT_EMAIL, 'Admin', SITE_CONTACT_EMAIL, SITE_NAME, $subject, $msg, $html_msg, 7 );
	}

}

#########################################################

function send_expiry_reminder( $order_id ) {

}

#########################
function display_order( $order_id, $BID ) {
	global $label;
	$BID = intval( $BID );
	$sql = "select * from banners where banner_id='$BID'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$b_row = mysqli_fetch_array( $result );

	if ( is_numeric( $order_id ) ) {
		$sql = "SELECT * from orders where order_id='" . intval( $order_id ) . "' and banner_id='$BID'";
	} else {
		$sql = "SELECT * from temp_orders where session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], $order_id ) . "' and banner_id='$BID'";
	}

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$order_row = mysqli_fetch_array( $result );

	?>

    <table class="table" width="300">
		<?php if ( isset( $order_row['order_id'] ) && $order_row['order_id'] != '' ) { ?>
            <tr>
                <td><b><?php echo $label['advertiser_ord_order_id']; ?></b></td>
                <td><?php echo $order_row['order_id']; ?></td>
            </tr>
		<?php } ?>
        <tr>
            <td><b><?php echo $label['advertiser_ord_date']; ?></b></td>
            <td><?php echo $order_row['order_date']; ?></td>
        </tr>
        <tr>
            <td><b><?php echo $label['advertiser_ord_quantity']; ?></b></td>
            <td><?php echo $order_row['quantity']; ?> <?php echo $label['advertiser_ord_pix']; ?></td>
        </tr>
        </tr>
        <tr>
            <td><b><?php echo $label['advertiser_ord_price']; ?></b></td>
            <td><?php echo convert_to_default_currency_formatted( $order_row['currency'], $order_row['price'] ) ?></td>
        </tr>
		<?php if ( isset( $order_row['order_id'] ) && $order_row['order_id'] != '' ) { ?>
            <tr>
                <td><b><?php echo $label['advertiser_ord_status']; ?></b></td>
                <td><?php echo $order_row['status']; ?></td>
            </tr>
		<?php } ?>
    </table>
	<?php
}

############################
# Contributed by viday
function display_packages( $order_id, $BID ) {

	global $f2, $label;

	$order_id = intval( $order_id );
	$BID      = intval( $BID );

	$sql = "select * from banners where banner_id='$BID'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$b_row = mysqli_fetch_array( $result );

	$sql = "SELECT * from orders where order_id='" . intval( $_SESSION['MDS_order_id'] ) . "' and banner_id='$BID'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$order_row = mysqli_fetch_array( $result );

	?>

    Please choose the duration of the campaign you desire:<p>
    <table border="1" width="300">
        <tr>
            <td><b><?php echo $label['advertiser_ord_order_id']; ?></b></td>
            <td><?php echo $order_row['order_id']; ?></td>
        </tr>
        <tr>
            <td><b><?php echo $label['advertiser_ord_date']; ?></b></td>
            <td><?php echo $order_row['order_date']; ?></td>
        </tr>
        <tr>
            <td><b><?php echo $label['advertiser_ord_name']; ?></b></td>
            <td><?php echo $b_row['name']; ?></td>
        </tr>
        <tr>
            <td><b><?php echo $label['advertiser_ord_quantity']; ?></b></td>
            <td><?php echo $order_row['quantity']; ?><?php echo $label['advertiser_ord_pix']; ?></td>
        </tr>
        <tr>
            <td><b>Duration/Price</b></td>
            <td><?php if ( $b_row['days_expire'] == 0 ) {
					echo $label['advertiser_ord_never'];
				} else {
                    // viday pricing dropdown
					?> <select name="packages"> <?php
					$sql = "SELECT * from packages where banner_id='" . intval( $BID ) . "' order by price asc";
					$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
					while ( $packages_row = mysqli_fetch_array( $result ) ) {
						echo "<option value=\"" . $packages_row['days_expire'] . "-" . $packages_row['price'] . "\">" . $packages_row['days_expire'] . " days - $" . $packages_row['price'] . "</option>";
					}
					echo "</select>";
					$get_blocks = explode( ",", $order_row['blocks'] );
					$num_blocks = count( $get_blocks );
					echo "<br>Prices are per block, you have chosen " . $num_blocks;
					if ( $num_blocks == "1" ) {
						echo " block.";
					} else {
						echo " blocks. ";
					}
					echo " Your total price will be calculated on the next screen.";
					echo "<input type=\"hidden\" name=\"num_blocks\" value=\"" . $num_blocks . "\">";
				} ?></td>
        </tr>
        <tr>
            <td><b><?php echo $label['advertiser_ord_status']; ?></b></td>
            <td><?php echo $order_row['status']; ?></td>
        </tr>
    </table>
	<?php
}

####################################################

function display_banner_selecton_form( $BID, $order_id, $res ) {

	$action = $_SERVER['PHP_SELF'];

	// strip parameters
	$a      = explode( '?', $action );
	$action = array_pop( $a );

	?>
    <form name="bidselect" method="post" action="<?php echo htmlentities( $action ); ?>">
        <input type="hidden" name="old_order_id" value="<?php echo $order_id; ?>">
        <input type="hidden" name="banner_change" value="1">
        <select name="BID" onchange="document.bidselect.submit()" style="font-size: 14px;">
			<?php
			while ( $row = mysqli_fetch_array( $res ) ) {
				if ( $row['banner_id'] == $BID ) {
					$sel = 'selected';
				} else {
					$sel = '';

				}
				echo '<option ' . $sel . ' value=' . $row['banner_id'] . '>' . $row['name'] . '</option>';
			}
			?>
        </select>
    </form>
	<?php
}

#######################################################

function escape_html( $val ) {

	$val = str_replace( '>', '&gt;', $val );
	$val = str_replace( '<', '&lt;', $val );
	$val = str_replace( '"', '&quot;', $val );
	//$val = str_replace("'", '&#39;',$val);

// echo "$val<br>";
	return $val;

}

####################################################
function send_email( $to_address, $to_name, $from_address, $from_name, $subject, $message, $html_message = '', $template_id = 0 ) {

	if ( strpos( strtolower( $to_address ), strtolower( 'Content-type' ) ) > 0 ) { // detect mail() injection
		return false;
	}

	if ( strpos( strtolower( $to_name ), strtolower( 'Content-type' ) ) > 0 ) { // detect mail injection
		return false;
	}

	if ( strpos( strtolower( $from_address ), strtolower( 'Content-type' ) ) > 0 ) { // detect mail injection
		return false;
	}

	if ( strpos( strtolower( $from_name ), strtolower( 'Content-type' ) ) > 0 ) { // detect mail injection
		return false;
	}

	if ( strpos( strtolower( $subject ), strtolower( 'Content-type' ) ) > 0 ) { // detect mail injection
		return false;
	}

	if ( strpos( strtolower( $message ), strtolower( 'Content-type' ) ) > 0 ) { // detect mail injection
		return false;
	}

	// save to the database...
	$attachments = 'N';
	$now         = ( gmdate( "Y-m-d H:i:s" ) );
	$sql         = "INSERT INTO mail_queue (mail_date, to_address, to_name, from_address, from_name, subject, message, html_message, attachments, status, error_msg, retry_count, template_id, date_stamp) VALUES('$now', '" . mysqli_real_escape_string( $GLOBALS['connection'], $to_address ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $to_name ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $from_address ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $from_name ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $subject ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $message ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $html_message ) . "', '$attachments', 'sent', '', 0, '" . intval( $template_id ) . "', '$now')";
	mysqli_query( $GLOBALS['connection'], $sql ) or q_mail_error( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$mail_id = mysqli_insert_id( $GLOBALS['connection'] );

	$error = send_phpmail( array(
		'from_address' => $from_address,
		'from_name'    => $from_name,
		'to_address'   => $to_address,
		'to_name'      => $to_name,
		'subject'      => $subject,
		'html_message' => $html_message,
		'message'      => $message,
		'mail_id'      => $mail_id,

	) );

	return empty( $error );
}

##################################################

function move_uploaded_image( $img_key ) {

	$img_name = $_FILES[ $img_key ]['name'];

	$temp  = explode( '.', $img_name );
	$ext   = array_pop( $temp );
	$fname = array_pop( $temp );

	$img_name = preg_replace( '/[^\w]+/', "", $fname );
	$img_name = $img_name . "." . $ext;

	$img_tmp = $_FILES[ $img_key ]['tmp_name'];

	$t = time();

	$new_name = TEMP_PATH . $t . "$img_name";

	move_uploaded_file( $img_tmp, $new_name );
	chmod( $new_name, 0666 );

	return $new_name;
}

function nav_pages_struct( $q_string, $count, $REC_PER_PAGE ) {

	global $label, $list_mode;

	$nav = array(
		'prev'         => '',
		'cur_page'     => 1,
		'pages_after'  => array(),
		'pages_before' => array(),
		'next'         => '',
	);

	if ( $list_mode == 'PREMIUM' ) {
		$page = 'hot.php';
	} else {
		$page = $_SERVER['PHP_SELF'];
	}

	$offset   = intval( $_REQUEST["offset"] );
	$show_emp = $_REQUEST["show_emp"];

	if ( $show_emp != '' ) {
		$show_emp = "&show_emp=" . urlencode( $show_emp );
	}

	$cat = $_REQUEST["cat"];
	if ( $cat != '' ) {
		$cat = ( "&cat=$cat" );
	}

	$order_by = $_REQUEST["order_by"];
	if ( $order_by != '' ) {
		$order_by = "&order_by=" . urlencode( $order_by );
	}

	$cur_page = $offset / $REC_PER_PAGE;
	$cur_page ++;

	// estimate number of pages.
	$pages = ceil( $count / $REC_PER_PAGE );
	if ( $pages == 1 ) {
		return $nav;
	}

	$off  = 0;
	$p    = 1;
	$prev = $offset - $REC_PER_PAGE;
	$next = $offset + $REC_PER_PAGE;

	if ( $prev === 0 ) {
		$prev = '';
	}

	if ( $prev > - 1 ) {
		$nav['prev'] = "<a href='" . htmlspecialchars( $page . "?offset=" . $prev . $q_string . $show_emp . $cat . $order_by ) . "'>" . $label["navigation_prev"] . "</a> ";
	}

	for ( $i = 0; $i < $count; $i = $i + $REC_PER_PAGE ) {
		if ( $p == $cur_page ) {
			$nav['cur_page'] = $p;

		} else {
			if ( $off === 0 ) {
				$off = '';
			}

			if ( $nav['cur_page'] != '' ) {
				$nav['pages_after'][ $p ] = $off;
			} else {
				$nav['pages_before'][ $p ] = $off;
			}
		}

		$p ++;

		$off = intval( $off ) + $REC_PER_PAGE;
	}

	if ( $next < $count ) {
		$nav['next'] = " | <a  href='" . htmlspecialchars( $page . "?offset=" . $next . $q_string . $show_emp . $cat . $order_by ) . "'> " . $label["navigation_next"] . "</a>";
	}

	return $nav;
}

#####################################################

function render_nav_pages( &$nav_pages_struct, $LINKS, $q_string = '' ) {

	global $f2, $list_mode, $label;

	if ( $list_mode == 'PREMIUM' ) {
		$page = 'hot.php';
		echo $label['post_list_more_sponsored'] . " ";
	} else {
		$page = $_SERVER['PHP_SELF'];
	}

	$offset   = $_REQUEST["offset"];
	$show_emp = $_REQUEST["show_emp"];

	if ( $show_emp != '' ) {
		$show_emp = "&show_emp=" . urlencode( $show_emp );
	}
	$cat = $_REQUEST["cat"];
	if ( $cat != '' ) {
		$cat = ( "&cat=$cat" );
	}
	$order_by = $_REQUEST["order_by"];
	if ( $order_by != '' ) {
		$order_by = "&order_by=" . urlencode( $order_by );
	}

	if ( $nav_pages_struct['cur_page'] > $LINKS - 1 ) {
		$LINKS  = round( $LINKS / 2 ) * 2;
		$NLINKS = $LINKS;
	} else {
		$NLINKS = $LINKS - $nav_pages_struct['cur_page'];
	}
	echo $nav_pages_struct['prev'];
	$b_count = isset( $nav_pages_struct['pages_before'] ) ? count( $nav_pages_struct['pages_before'] ) : 0;
	$pipe    = "";
	for ( $i = $b_count - $LINKS; $i <= $b_count; $i ++ ) {
		if ( $i > 0 ) {
			//echo " <a href='?offset=".$nav['pages_before'][$i]."'>".$i."</a></b>";
			echo " | <a  href='" . htmlspecialchars( $page . "?offset=" . $nav_pages_struct['pages_before'][ $i ] . $q_string . $show_emp . $cat . $order_by ) . "'>" . $i . "</a>";
			$pipe = "|";
		}
	}
	echo " $pipe <b>" . $nav_pages_struct['cur_page'] . " </b>  ";
	$a_count = isset( $nav_pages_struct['pages_after'] ) ? count( $nav_pages_struct['pages_after'] ) : 0;
	if ( $a_count > 0 ) {
		$i = 0;
		foreach ( $nav_pages_struct['pages_after'] as $key => $pa ) {
			$i ++;
			if ( $i > $NLINKS ) {
				break;
			}
			//echo " <a href='?offset=".$pa."'>".$key."</a>";
			echo " | <a  href='" . htmlspecialchars( $page . "?offset=" . $pa . $q_string . $show_emp . $cat . $order_by ) . "'>" . $key . "</a>  ";
		}
	}

	echo $nav_pages_struct['next'];
}

function do_log_entry( $entry_line ) {

	$entry_line = "$entry_line\r\n ";
	$log_fp     = @fopen( "logs.txt", "a" );
	@fputs( $log_fp, $entry_line );
	@fclose( $log_fp );

}

function select_block( $map_x, $map_y ) {

	global $BID, $b_row, $label, $order_id, $banner_data;

	// calculate clicked block from coords.

	if ( func_num_args() > 2 ) {
		$clicked_block = func_get_arg( 2 );

	} else {

		$clicked_block = get_block_id_from_position( $map_x, $map_y, $BID );
	}

	//Check if max_orders < order count
	if ( ! can_user_order( $b_row, $_SESSION['MDS_ID'] ) ) {
		return $label['advertiser_max_order_html']; // order count > max orders
	}

	if ( ! function_exists( 'delete_ads_files' ) ) {
		require_once( "../include/ads.inc.php" );
	}

	$blocks         = array();
	$clicked_blocks = array();
	$return_val     = "";

	$sql = "SELECT status, user_id, ad_id FROM blocks where block_id=" . intval( $clicked_block ) . " AND banner_id=" . intval( $BID );
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );

	if ( ( $row['status'] == '' ) || ( ( $row['status'] == 'reserved' ) && ( $row['user_id'] == $_SESSION['MDS_ID'] ) ) ) {

		// put block on order
		$sql = "SELECT blocks,status,ad_id,order_id FROM orders where user_id=" . intval( $_SESSION['MDS_ID'] ) . " and order_id=" . intval( $_SESSION['MDS_order_id'] ) . " and banner_id=" . intval( $BID ) . " ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		$row = mysqli_fetch_array( $result );
		if ( $row['blocks'] != '' ) {
			$blocks = explode( ",", $row['blocks'] );
			array_walk( $blocks, 'intval' );
		}

		// $blocks2 will be modified based on deselections
		$blocks2 = $blocks;

		// take multi-selection blocks into account (1,4,6) and deselecting blocks
		if ( isset( $_REQUEST['sel_mode'] ) && ! empty( $_REQUEST['sel_mode'] ) ) {
			if ( $_REQUEST['sel_mode'] == "sel1" ) {
				// 1x1
				// [1]
				if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
					// deselect
					unset( $blocks2[ $block ] );
				} else {
					// select
					$clicked_blocks[] = $clicked_block;
				}

			} else if ( $_REQUEST['sel_mode'] == "sel4" ) {
				// 2x2
				// [1][2]
				// [3][4]

				$pos   = get_block_position( $clicked_block, $BID );
				$max_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
				$max_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

				// 1
				if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
					// deselect
					unset( $blocks2[ $block ] );
				} else {
					// select
					$clicked_blocks[] = $clicked_block;
				}

				// 2
				$x = $pos['x'] + $banner_data['BLK_WIDTH'];
				$y = $pos['y'];
				if ( $x <= $max_x ) {
					$clicked_block = get_block_id_from_position( $x, $y, $BID );
					if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
						// deselect
						unset( $blocks2[ $block ] );
					} else {
						// select
						$clicked_blocks[] = $clicked_block;
					}
				}

				// 3
				$x = $pos['x'];
				$y = $pos['y'] + $banner_data['BLK_HEIGHT'];
				if ( $y <= $max_y ) {
					$clicked_block = get_block_id_from_position( $x, $y, $BID );
					if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
						// deselect
						unset( $blocks2[ $block ] );
					} else {
						// select
						$clicked_blocks[] = $clicked_block;
					}
				}

				// 4
				$x = $pos['x'] + $banner_data['BLK_WIDTH'];
				$y = $pos['y'] + $banner_data['BLK_HEIGHT'];
				if ( $x <= $max_x && $y <= $max_y ) {
					$clicked_block = get_block_id_from_position( $x, $y, $BID );
					if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
						// deselect
						unset( $blocks2[ $block ] );
					} else {
						// select
						$clicked_blocks[] = $clicked_block;
					}
				}

			} else if ( $_REQUEST['sel_mode'] == "sel6" ) {
				// 3x2
				// [1][2][3]
				// [4][5][6]

				$pos   = get_block_position( $clicked_block, $BID );
				$max_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
				$max_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

				// 1
				if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
					unset( $blocks2[ $block ] );
				} else {
					$clicked_blocks[] = $clicked_block;
				}

				// 2
				$x = $pos['x'] + $banner_data['BLK_WIDTH'];
				$y = $pos['y'];
				if ( $x <= $max_x ) {
					$clicked_block = get_block_id_from_position( $x, $y, $BID );
					if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
						// deselect
						unset( $blocks2[ $block ] );
					} else {
						// select
						$clicked_blocks[] = $clicked_block;
					}
				}

				// 3
				$x = $pos['x'] + ( $banner_data['BLK_WIDTH'] * 2 );
				$y = $pos['y'];
				if ( $x <= $max_x && $y <= $max_y ) {
					$clicked_block = get_block_id_from_position( $x, $y, $BID );
					if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
						// deselect
						unset( $blocks2[ $block ] );
					} else {
						// select
						$clicked_blocks[] = $clicked_block;
					}
				}

				// 4
				$x = $pos['x'];
				$y = $pos['y'] + $banner_data['BLK_HEIGHT'];
				if ( $y <= $max_y ) {
					$clicked_block = get_block_id_from_position( $x, $y, $BID );
					if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
						// deselect
						unset( $blocks2[ $block ] );
					} else {
						// select
						$clicked_blocks[] = $clicked_block;
					}
				}

				// 5
				$x = $pos['x'] + $banner_data['BLK_WIDTH'];
				$y = $pos['y'] + $banner_data['BLK_HEIGHT'];
				if ( $x <= $max_x && $y <= $max_y ) {
					$clicked_block = get_block_id_from_position( $x, $y, $BID );
					if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
						// deselect
						unset( $blocks2[ $block ] );
					} else {
						// select
						$clicked_blocks[] = $clicked_block;
					}
				}

				// 6
				$x = $pos['x'] + ( $banner_data['BLK_WIDTH'] * 2 );
				$y = $pos['y'] + $banner_data['BLK_HEIGHT'];
				if ( $x <= $max_x && $y <= $max_y ) {
					$clicked_block = get_block_id_from_position( $x, $y, $BID );
					if ( ( $block = array_search( $clicked_block, $blocks2 ) ) !== false ) {
						// deselect
						unset( $blocks2[ $block ] );
					} else {
						// select
						$clicked_blocks[] = $clicked_block;
					}
				}
			}
		}

		// merge blocks
		$new_blocks = array_merge( $blocks2, $clicked_blocks );

		// check max blocks
		$max_selected = false;
		if ( USE_AJAX == 'NO' ) {
			if ( $banner_data['G_MAX_BLOCKS'] > 0 ) {
				if ( sizeof( $new_blocks ) > $banner_data['G_MAX_BLOCKS'] ) {
					$max_selected = true;
					$return_val   = str_replace( '%MAX_BLOCKS%', $banner_data['G_MAX_BLOCKS'], $label['max_blocks_selected'] );
				}
			}
		}

		if ( ! $max_selected ) {
			$return_val = intval( $_SESSION['MDS_order_id'] );

			$price        = $total = 0;
			$num_blocks   = sizeof( $new_blocks );
			$quantity     = ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] ) * $num_blocks;
			$order_blocks = implode( ",", $new_blocks );
			$now          = gmdate( "Y-m-d H:i:s" );

			$sql = "REPLACE INTO orders (user_id, order_id, blocks, status, order_date, price, quantity, banner_id, currency, days_expire, date_stamp, approved) VALUES (" . intval( $_SESSION['MDS_ID'] ) . ", " . intval( $row['order_id'] ) . ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $order_blocks ) . "', 'new', NOW(), " . floatval( $price ) . ", " . intval( $quantity ) . ", " . intval( $BID ) . ", '" . mysqli_real_escape_string( $GLOBALS['connection'], get_default_currency() ) . "', " . intval( $b_row['days_expire'] ) . ", '$now', '" . mysqli_real_escape_string( $GLOBALS['connection'], $banner_data['AUTO_APPROVE'] ) . "') ";

			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$_SESSION['MDS_order_id'] = mysqli_insert_id( $GLOBALS['connection'] );
			$order_id                 = $_SESSION['MDS_order_id'];

			$sql = "delete from blocks where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' AND status = 'reserved' AND banner_id='" . intval( $BID ) . "' ";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			$cell = 0;

			$blocks_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];
			$blocks_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];

			for ( $y = 0; $y < $blocks_y; $y += $banner_data['BLK_HEIGHT'] ) {
				for ( $x = 0; $x < $blocks_x; $x += $banner_data['BLK_WIDTH'] ) {

					if ( in_array( $cell, $new_blocks ) ) {

						$price = get_zone_price( $BID, $y, $x );

						// reserve block
						$sql = "REPLACE INTO `blocks` ( `block_id` , `user_id` , `status` , `x` , `y` , `image_data` , `url` , `alt_text`, `approved`, `banner_id`, `currency`, `price`, `order_id`, `click_count`) VALUES (" . intval( $cell ) . ",  " . intval( $_SESSION['MDS_ID'] ) . " , 'reserved' , " . intval( $x ) . " , " . intval( $y ) . " , '' , '' , '', '" . mysqli_real_escape_string( $GLOBALS['connection'], $banner_data['AUTO_APPROVE'] ) . "', " . intval( $BID ) . ", '" . mysqli_real_escape_string( $GLOBALS['connection'], get_default_currency() ) . "', " . floatval( $price ) . ", " . intval( $_SESSION['MDS_order_id'] ) . ", 0)";
						mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

						$total += $price;
					}
					$cell ++;
				}
			}

			// update price
			$sql = "UPDATE orders SET price='" . floatval( $total ) . "' WHERE order_id='" . intval( $_SESSION['MDS_order_id'] ) . "'";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			$sql = "UPDATE orders SET original_order_id='" . intval( $_SESSION['MDS_order_id'] ) . "' WHERE order_id='" . intval( $_SESSION['MDS_order_id'] ) . "'";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			// check that we have ad_id, if not then create an ad for this order.
			if ( ! $row['ad_id'] ) {

				$_REQUEST['order_id'] = $order_id;
				$_REQUEST['BID']      = $BID;
				$_REQUEST['user_id']  = $_SESSION['MDS_ID'];

				$ad_id = insert_ad_data();

				$sql = "UPDATE orders SET ad_id='" . intval( $ad_id ) . "' WHERE order_id='" . intval( $order_id ) . "' ";
				$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
				$sql = "UPDATE blocks SET ad_id='" . intval( $ad_id ) . "' WHERE order_id='" . intval( $order_id ) . "' ";
				$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

				$_REQUEST['ad_id'] = $ad_id;
			}
		}

	} else {

		if ( $row['status'] == 'nfs' ) {
			$return_val = $label['advertiser_sel_nfs_error'];

		} else {
			$label['advertiser_sel_sold_error'] = str_replace( "%BLOCK_ID%", $clicked_block, $label['advertiser_sel_sold_error'] );
			$return_val                         = $label['advertiser_sel_sold_error'];
		}
	}

	return $return_val;
}

################

/*

The new 'Easy' pixel selection method (since 2.0)
- Reserve pixels
Takes the temp_order and converts it to an order.
Allocates pixels in the blocks tabe, returning order_id
shows an error if pixels were not reserved.

*/

function reserve_pixels_for_temp_order( $temp_order_row ) {

	global $f2;

	// check if the user can get the order
	if ( ! can_user_order( load_banner_row( $temp_order_row['banner_id'] ), $_SESSION['MDS_ID'], $temp_order_row['package_id'] ) ) {
		echo 'can\'t touch this<br>';

		return false;

	}

	require_once( '../include/ads.inc.php' );

	// Session may have expired if they waited too long so tell them to start over, even though we might still have the file it doesn't match the current session id anymore.
	// TODO: Implement our own cookies instead of PHP sessions to allow longer sessions. Maybe can recover the old session file automatically somehow or another.
	$block_info = array();
	$sql        = "SELECT block_info FROM temp_orders WHERE session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], session_id() ) . "' ";
	$result     = mysqli_query( $GLOBALS['connection'], $sql );
	$row        = mysqli_fetch_array( $result );

	if ( mysqli_num_rows( $result ) > 0 ) {
		$block_info = unserialize( $row['block_info'] );
	}

	$in_str = $temp_order_row['blocks'];

	$sql = "select block_id from blocks where banner_id='" . intval( $temp_order_row['banner_id'] ) . "' and block_id IN(" . mysqli_real_escape_string( $GLOBALS['connection'], $in_str ) . ") ";
//echo $sql."<br>";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( $sql . mysqli_error( $GLOBALS['connection'] ) );
	if ( mysqli_num_rows( $result ) > 0 ) {
		return false;  // the pixels are not available!
	}

	// approval status, default is N
	$banner_row = load_banner_row( $temp_order_row['banner_id'] );
	$approved   = $banner_row['auto_approve'];

	$now = ( gmdate( "Y-m-d H:i:s" ) );

	$sql = "REPLACE INTO orders (user_id, order_id, blocks, status, order_date, price, quantity, banner_id, currency, days_expire, date_stamp, package_id, ad_id, approved) VALUES ('" . intval( $_SESSION['MDS_ID'] ) . "', 0, '" . mysqli_real_escape_string( $GLOBALS['connection'], $in_str ) . "', 'new', '" . $now . "', '" . floatval( $temp_order_row['price'] ) . "', '" . intval( $temp_order_row['quantity'] ) . "', '" . intval( $temp_order_row['banner_id'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], get_default_currency() ) . "', " . intval( $temp_order_row['days_expire'] ) . ", '" . $now . "', " . intval( $temp_order_row['package_id'] ) . ", " . intval( $temp_order_row['ad_id'] ) . ", '" . mysqli_real_escape_string( $GLOBALS['connection'], $approved ) . "') ";

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$order_id = mysqli_insert_id( $GLOBALS['connection'] );

	global $f2;
	$f2->debug( "Changed temp order to a real order - " . $sql );

	$sql = "UPDATE ads SET user_id='" . intval( $_SESSION['MDS_ID'] ) . "', order_id='" . intval( $order_id ) . "' where ad_id='" . intval( $temp_order_row['ad_id'] ) . "' ";
	//echo $sql;
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

	$sql = "UPDATE orders SET original_order_id='" . intval( $order_id ) . "' where order_id='" . intval( $order_id ) . "' ";
	//echo $sql;
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

	global $prams;
	$prams    = load_ad_values( $temp_order_row['ad_id'] );
	$url      = get_template_value( 'URL', 1 );
	$alt_text = get_template_value( 'ALT_TEXT', 1 );

	foreach ( $block_info as $key => $block ) {
		$sql = "REPLACE INTO `blocks` ( `block_id` , `user_id` , `status` , `x` , `y` , `image_data` , `url` , `alt_text`, `approved`, `banner_id`, `currency`, `price`, `order_id`, `ad_id`, `click_count`) VALUES ('" . intval( $key ) . "',  '" . intval( $_SESSION['MDS_ID'] ) . "' , 'reserved' , '" . intval( $block['map_x'] ) . "' , '" . intval( $block['map_y'] ) . "' , '" . mysqli_real_escape_string( $GLOBALS['connection'], $block['image_data'] ) . "' , '" . mysqli_real_escape_string( $GLOBALS['connection'], $url ) . "' , '" . mysqli_real_escape_string( $GLOBALS['connection'], $alt_text ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $approved ) . "', '" . intval( $temp_order_row['banner_id'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], get_default_currency() ) . "', '" . floatval( $block['price'] ) . "', '" . intval( $order_id ) . "', '" . intval( $temp_order_row['ad_id'] ) . "', 0)";

		global $f2;
		$f2->debug( "Updated block - " . $sql );
		mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

	}

	delete_temp_order( session_id(), false ); // false = do not delete the ad...

	return $order_id;

}

################

function get_block_position( $block_id, $banner_id ) {

	$cell     = "0";
	$ret['x'] = 0;
	$ret['y'] = 0;

	$banner_data = load_banner_constants( $banner_id );

	for ( $i = 0; $i < $banner_data['G_HEIGHT']; $i ++ ) {
		for ( $j = 0; $j < $banner_data['G_WIDTH']; $j ++ ) {
			if ( $block_id == $cell ) {
				$ret['x'] = $j * $banner_data['BLK_WIDTH'];
				$ret['y'] = $i * $banner_data['BLK_HEIGHT'];

				return $ret;

			}
			$cell ++;
		}

	}

	return $ret;
}

function get_block_id_from_position( $x, $y, $banner_id ) {
	$id          = 0;
	$banner_data = load_banner_constants( $banner_id );
	$max_y       = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];
	$max_x       = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];

	for ( $y2 = 0; $y2 < $max_y; $y2 += $banner_data['BLK_HEIGHT'] ) {
		for ( $x2 = 0; $x2 < $max_x; $x2 += $banner_data['BLK_WIDTH'] ) {
			if ( $x == $x2 && $y == $y2 ) {
				return $id;
			}
			$id ++;
		}
	}

	return $id;
}

########################

function is_block_free( $block_id, $banner_id ) {

	$sql = "SELECT * from blocks where block_id='" . intval( $block_id ) . "' AND banner_id='" . intval( $banner_id ) . "' ";
	//echo "$sql<br>";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	if ( mysqli_num_rows( $result ) == 0 ) {

		return true;

	} else {

		return false;

	}

}

######################################################
# Move 1 block
# - changes the x y of a block
# - updates the order's blocks column
# *** assuming that the grid constants were loaded!

function move_block( $block_from, $block_to, $banner_id ) {

	# reserve block_to
	if ( ! is_block_free( $block_to, $banner_id ) ) {
		echo "<font color='red'>Cannot move the block - the space chosen is not empty!</font><br>";

		return false;
	}

	#load block_from
	$sql = "SELECT * from blocks where block_id='" . intval( $block_from ) . "' AND banner_id='" . intval( $banner_id ) . "' ";
	//echo "$sql<br>";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$source_block = mysqli_fetch_array( $result );

	// get the position and check range, do not move if out of range

	$pos = get_block_position( $block_to, $banner_id );
	//echo "pos is ($block_to): ";print_r($pos); echo "<br>";

	$x = $pos['x'];
	$y = $pos['y'];

	$banner_data = load_banner_constants( $banner_id );

	if ( ( $x === '' ) || ( $x > ( $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'] ) ) || $x < 0 ) {
		echo "<b>x is $x</b><br>";

		return false;

	}

	if ( ( $y === '' ) || ( $y > ( $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'] ) ) || $y < 0 ) {
		echo "<b>y is $y</b><br>";

		return false;
	}

	$sql = "REPLACE INTO `blocks` ( `block_id` , `user_id` , `status` , `x` , `y` , `image_data` , `url` , `alt_text`, `file_name`, `mime_type`,  `approved`, `published`, `banner_id`, `currency`, `price`, `order_id`, `click_count`, `ad_id`) VALUES ('" . intval( $block_to ) . "',  '" . intval( $source_block['user_id'] ) . "' , '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['status'] ) . "' , '" . intval( $x ) . "' , '" . intval( $y ) . "' , '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['image_data'] ) . "' , '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['url'] ) . "' , '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['alt_text'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['file_name'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['mime_type'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['approved'] ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['published'] ) . "', '" . intval( $banner_id ) . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], $source_block['currency'] ) . "', '" . floatval( $source_block['price'] ) . "', '" . intval( $source_block['order_id'] ) . "', '" . intval( $source_block['click_count'] ) . "', '" . intval( $source_block['ad_id'] ) . "')";

	global $f2;
	$f2->debug( "Moved Block - " . $sql );

	mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	# delete 'from' block
	$sql = "DELETE from blocks WHERE block_id='" . intval( $block_from ) . "' AND banner_id='" . intval( $banner_id ) . "' ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	$f2->debug( "Deleted block_from - " . $sql );

	// Update the order record
	$sql = "SELECT * from orders WHERE order_id='" . intval( $source_block['order_id'] ) . "' AND banner_id='" . intval( $banner_id ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$order_row  = mysqli_fetch_array( $result );
	$blocks     = array();
	$new_blocks = array();
	$blocks     = explode( ',', $order_row['blocks'] );
	foreach ( $blocks as $item ) {
		if ( $block_from == $item ) {
			$item = $block_to;
		}
		$new_blocks[] = intval( $item );
	}

	$sql_blocks = implode( ',', $new_blocks );

	$sql = "UPDATE orders SET blocks='" . $sql_blocks . "' WHERE order_id='" . intval( $source_block['order_id'] ) . "' ";
	# update the customer's order
	mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	$f2->debug( "Updated order - " . $sql );

	return true;

}

###################################

######################################################

function move_order( $block_from, $block_to, $banner_id ) {

	//move_block($block_from, $block_to, $banner_id);

	// get the block_to x,y
	$pos  = get_block_position( $block_to, $banner_id );
	$to_x = $pos['x'];
	$to_y = $pos['y'];

// we need to work out block_from, get the block with the lowest x and y

	$min_max = get_blocks_min_max( $block_from, $banner_id );
	$from_x  = $min_max['low_x'];
	$from_y  = $min_max['low_y'];
	//echo "block_from: ($block_from) $from_x $from_y<br>";
	//echo "block_to: ($block_to) $to_x $to_y<br>";
	// get the position move's difference

	$dx = ( $to_x - $from_x ); //echo "$to_x - $from_x ($dx)<br>";
	$dy = ( $to_y - $from_y );

	// get the order

	$sql = "SELECT * from blocks where block_id='" . intval( $block_from ) . "' AND banner_id='" . intval( $banner_id ) . "' ";
	//echo "$sql<br>";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$source_block = mysqli_fetch_array( $result );

	$sql = "SELECT * from blocks WHERE order_id='" . intval( $source_block['order_id'] ) . "' AND banner_id='" . intval( $banner_id ) . "' ";
	//echo "$sql<br>";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	$banner_data = load_banner_constants( $banner_id );

	$grid_width = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];

	while ( $block_row = mysqli_fetch_array( $result ) ) { // check each block to make sure we can move it.

		//echo 'from: '.$block_row['x'].",".$block_row['y']." to ".($block_row['x']+$dx).",".($block_row['y']+$dy)." (to pos: $to_x, $to_y diff: $dx & $dy)<Br>";
		//$block_to = ((($block_row['y']+$dy)*$grid_width)+($block_row['x']+$dx))/10 ;

		$block_to = ( ( $block_row['x'] + $dx ) / $banner_data['BLK_WIDTH'] ) + ( ( ( $block_row['y'] + $dy ) / $banner_data['BLK_HEIGHT'] ) * ( $grid_width / $banner_data['BLK_WIDTH'] ) );

		if ( ! is_block_free( $block_to, $banner_id ) ) {
			echo "<font color='red'>Cannot move the order - the space chosen is not empty!</font><br>";

			return false;
		}

	}

	mysqli_data_seek( $result, 0 );

	while ( $block_row = mysqli_fetch_array( $result ) ) {

		$block_from = ( ( $block_row['x'] ) / $banner_data['BLK_WIDTH'] ) + ( ( $block_row['y'] / $banner_data['BLK_HEIGHT'] ) * ( $grid_width / $banner_data['BLK_WIDTH'] ) );
		$block_to   = ( ( $block_row['x'] + $dx ) / $banner_data['BLK_WIDTH'] ) + ( ( ( $block_row['y'] + $dy ) / $banner_data['BLK_HEIGHT'] ) * ( $grid_width / $banner_data['BLK_WIDTH'] ) );

		move_block( $block_from, $block_to, $banner_id );
	}

	return true;

}

######################################################
/*
function get_required_size($x, $y) - assuming the grid constants were initialized
$x and $y are the current size
*/
function get_required_size( $x, $y, $banner_data ) {

	$block_width  = $banner_data['BLK_WIDTH'];
	$block_height = $banner_data['BLK_HEIGHT'];

	$size[0] = $x;
	$size[1] = $y;

	$mod = ( $x % $block_width );

	if ( $mod > 0 ) { // width does not fit
		$size[0] = $x + ( $block_width - $mod );

	}

	$mod = ( $y % $block_height );

	if ( $mod > 0 ) { // height does not fit
		$size[1] = $y + ( $block_height - $mod );

	}

	return $size;

}

######################################################
# If $user_id is null then return for all banners
function get_clicks_for_today( $BID, $user_id = 0 ) {

	$date = gmDate( 'Y' ) . "-" . gmDate( 'm' ) . "-" . gmDate( 'd' );

	$sql = "SELECT *, SUM(clicks) AS clk FROM `clicks` where banner_id='" . intval( $BID ) . "' AND `date`='$date' GROUP BY banner_id, block_id, user_id, date";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );

	return $row['clk'];

}

#######################################################
# If $BID is null then return for all banners
function get_clicks_for_banner( $BID = '' ) {

	$sql = "SELECT *, SUM(clicks) AS clk FROM `clicks` where banner_id='" . intval( $BID ) . "'  GROUP BY banner_id, block_id, user_id, date";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );

	return $row['clk'];

}

#########################################################
/*

First check to see if the banner has packages. If it does
then check how many orders the user had.
*/

function can_user_order( $banner_data, $user_id, $package_id = 0 ) {
	// check rank

	$sql = "select Rank from users where ID='" . intval( $user_id ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$u_row = mysqli_fetch_array( $result );

	if ( $u_row['Rank'] == '2' ) {

		return true;

	}

	$BID = $banner_data['BANNER_ID'];

	if ( banner_get_packages( $BID ) ) { // if user has package, check if the user can order this package
		if ( $package_id == 0 ) { // don't know the package id, assume true.

			return true;
		} else {

			return can_user_get_package( $user_id, $package_id );
		}
	} else {

		// check againts the banner. (Banner has no packages)
		if ( ( $banner_data['G_MAX_ORDERS'] > 0 ) ) {

			$sql = "SELECT order_id FROM orders where `banner_id`='" . intval( $BID ) . "' and `status` <> 'deleted' and `status` <> 'new' AND user_id='" . intval( $user_id ) . "'";

			$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
			$count = mysqli_num_rows( $result );
			if ( $count >= $banner_data['G_MAX_ORDERS'] ) {
				return false;
			} else {
				return true;
			}
		} else {
			return true; // can make unlimited orders
		}

	}

}

//////

function get_blocks_min_max( $block_id, $banner_id ) {

	$sql = "SELECT * FROM blocks where block_id='" . intval( $block_id ) . "' and banner_id='" . intval( $banner_id ) . "' ";

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result );

	$sql = "select * from blocks where order_id='" . intval( $row['order_id'] ) . "' ";
	$result3 = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

	//echo $sql;

	// find high x, y & low x, y
	// low x,y is the top corner, high x,y is the bottom corner

	$high_x = $high_y = $low_x = $low_y = "";
	while ( $block_row = mysqli_fetch_array( $result3 ) ) {

		if ( $high_x == '' ) {
			$high_x = $block_row['x'];
			$high_y = $block_row['y'];
			$low_x  = $block_row['x'];
			$low_y  = $block_row['y'];

		}

		if ( $block_row['x'] > $high_x ) {
			$high_x = $block_row['x'];
		}

		if ( $block_row['y'] > $high_y ) {
			$high_y = $block_row['y'];
		}

		if ( $block_row['y'] < $low_y ) {
			$low_y = $block_row['y'];
		}

		if ( $block_row['x'] < $low_x ) {
			$low_x = $block_row['x'];
		}

	}

	$ret           = array();
	$ret['high_x'] = $high_x;
	$ret['high_y'] = $high_y;
	$ret['low_x']  = $low_x;
	$ret['low_y']  = $low_y;

	return $ret;

}

################################################
function get_definition( $field_type ) {

	switch ( $field_type ) {
		case "TEXT":
			return "VARCHAR( 255 ) NOT NULL ";
			break;
		case "SEPERATOR":
			break;
		case "EDITOR":
			return "TEXT NOT NULL ";
			break;
		case "CATEGORY":
			return "INT(11) NOT NULL ";
			break;
		case "DATE":
		case "DATE_CAL":
			return "DATETIME NOT NULL ";
			break;
		case "FILE":
			return "VARCHAR( 255 ) NOT NULL ";
			break;
		case "MIME":
			return "VARCHAR( 255 ) NOT NULL ";
			break;
		case "BLANK":
			break;
		case "NOTE":
			return "VARCHAR( 255 ) NOT NULL ";
			break;
		case "CHECK":
			return "VARCHAR( 255 ) NOT NULL ";
			break;
		case "IMAGE":
			return "VARCHAR( 255 ) NOT NULL ";
			break;
		case "RADIO":
			return "VARCHAR( 255 ) NOT NULL ";
			break;
		case "SELECT":
			return "VARCHAR( 255 ) NOT NULL ";
			break;
		case "MSELECT":
			return "VARCHAR( 255 ) NOT NULL ";
			break;
		case "TEXTAREA":
			return "TEXT NOT NULL ";
			break;
		default:
			return "VARCHAR( 255 ) NOT NULL ";
			break;

	}

}

##############################################

function saveImage( $field_id ) {

	global $f2;

	$imagine = new Imagine\Gd\Imagine();

	if ( ! defined( 'IMG_MAX_WIDTH' ) || IMG_MAX_WIDTH == 'IMG_MAX_WIDTH' ) {

		$max_width = '150';
	} else {
		$max_width = IMG_MAX_WIDTH;
	}

	$uploaddir = UPLOAD_PATH . "images/";
	$thumbdir  = UPLOAD_PATH . "images/";

	$a    = explode( ".", $_FILES[ $field_id ]['name'] );
	$ext  = strtolower( array_pop( $a ) );
	$name = strtolower( array_shift( $a ) );

	if ( $_SESSION['MDS_ID'] != '' ) {
		$name = $_SESSION['MDS_ID'] . "_" . $name;
	} else {
		//	$name = subssession_id().$name;

	}
	//echo "<b>NAMEis:[$name]</b>";

	//$name = ereg_replace("[ '\"]+", "_", $name);
	// strip quotes, spaces
	$name = preg_replace( '/[^0-9a-zа-яіїё\`\~\!\@\#\$\%\^\*\(\)\; \,\.\'\/\_\-]/i', ' ', $name );

	$new_name   = $name . time() . "." . $ext;
	$uploadfile = $uploaddir . $new_name; //$uploaddir . $file_name;
	$thumbfile  = $thumbdir . $new_name;

	//echo "te,p Image is:".$_FILES[$field_id]['tmp_name']." upload file:".$uploadfile;

	if ( move_uploaded_file( $_FILES[ $field_id ]['tmp_name'], $uploadfile ) ) {
		//echo "File is valid, and was successfully uploaded. ($uploadfile)\n";
	} else {
		switch ( $_FILES[ $field_id ]["error"] ) {
			case UPLOAD_ERR_OK:
				break;
			case UPLOAD_ERR_INI_SIZE:
				print( "The uploaded file exceeds the upload_max_filesize directive (" . ini_get( "upload_max_filesize" ) . ") in php.ini." );
				break;
			case UPLOAD_ERR_FORM_SIZE:
				print( "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form." );
				break;
			case UPLOAD_ERR_PARTIAL:
				print( "The uploaded file was only partially uploaded." );
				break;
			case UPLOAD_ERR_NO_FILE:
				print( "No file was uploaded." );
				break;
			case UPLOAD_ERR_NO_TMP_DIR:
				print( "Missing a temporary folder." );
				break;
			case UPLOAD_ERR_CANT_WRITE:
				print( "Failed to write file to disk" );
				break;
			default:
				print( "Unknown File Error" );
		}

		//echo "Possible file upload attack ($uploadfile)! $field_id<br>\n";
		//echo $_FILES[$field_id]['tmp_name']."<br>";
	}

	setMemoryLimit( $uploadfile );

	$image = $imagine->open( $uploadfile );

	// autorotate
	$imagine->setMetadataReader( new \Imagine\Image\Metadata\ExifMetadataReader() );
	$filter = new Imagine\Filter\Transformation();
	$filter->add( new AutoRotate() );
	$filter->apply( $image );

	$current_size = $image->getSize();
	$orig_width   = $current_size->getWidth();
	$orig_height  = $current_size->getHeight();

	// Set a maximum height and width
	$max_width  = 200;
	$max_height = 200;

	$new_width  = $final_width = min( $orig_width, $max_width );
	$new_height = $final_height = min( $orig_height, $max_height );

	if ( $orig_width > $max_width ) {

		if ( $orig_width > $orig_height ) {
			$final_width  = $new_width;
			$final_height = $orig_height * ( $new_height / $orig_width );
		} else if ( $orig_width < $orig_height ) {
			$final_width  = $orig_width * ( $new_width / $orig_height );
			$final_height = $new_height;
		} else if ( $orig_width == $orig_height ) {
			$final_width  = $new_width;
			$final_height = $new_height;
		}

		// Resize to max size
		$image->resize( new Imagine\Image\Box( $final_width, $final_height ) );
		$image->save( $uploadfile );

	} else {
		//echo 'No need to resize.<br>';

	}

	//@unlink($uploadfile); // delete the original file.
	return $new_name;
}

###########################################################

function deleteImage( $table_name, $object_name, $object_id, $field_id ) {

	$sql = "SELECT `" . mysqli_real_escape_string( $GLOBALS['connection'], $field_id ) . "` FROM `" . mysqli_real_escape_string( $GLOBALS['connection'], $table_name ) . "` WHERE `" . mysqli_real_escape_string( $GLOBALS['connection'], $object_name ) . "`='" . intval( $object_id ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
	$row = mysqli_fetch_array( $result, MYSQLI_ASSOC );
	if ( $row[ $field_id ] != '' ) {
		// delete the original
		@unlink( UPLOAD_PATH . "images/" . $row[ $field_id ] );
		// delete the thumb
		//@unlink (IMG_PATH."thumbs/".$row[$field_id]);
		//echo "<br><b>unlnkthis[".IMG_PATH."thumbs/$new_name]</b><br>";
	}

// yeo su 019 760 0030

}

##########################################################

function saveFile( $field_id ) {

	global $f2;

	$uploaddir = UPLOAD_PATH . 'docs/';
	$new_name  = "";
	foreach ( $_FILES[ $field_id ]["error"] as $key => $error ) {
		if ( $error == UPLOAD_ERR_OK ) {
			$tmp_name   = $_FILES[ $field_id ]["tmp_name"][ $key ];
			$tmp_name   = basename( $tmp_name );
			$path_parts = pathinfo( $tmp_name );
			$tmp_name   = preg_replace( '/[^a-z0-9]+/', '-', strtolower( $tmp_name ) );
			$new_name   = $tmp_name . time() . "." . $path_parts['extension'];
			move_uploaded_file( $tmp_name, "$uploaddir/$new_name" );
		}
	}

	return $new_name;

}

#####################################################################

function deleteFile( $table_name, $object_name, $object_id, $field_id ) {

	$sql = "SELECT `" . mysqli_real_escape_string( $GLOBALS['connection'], $field_id ) . "` FROM `" . mysqli_real_escape_string( $GLOBALS['connection'], $table_name ) . "` WHERE `" . mysqli_real_escape_string( $GLOBALS['connection'], $object_name ) . "`='" . intval( $object_id ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	$row = mysqli_fetch_array( $result, MYSQLI_ASSOC );
	if ( $row[ $field_id ] != '' ) {
		// delete the original
		@unlink( UPLOAD_PATH . "docs/" . $row[ $field_id ] );
		// delete the thumb
		// unlink (FILE_PATH."thumbs/".$row[$field_id]);
		//echo "<br><b>unlnkthis[".IMG_PATH."thumbs/$new_name]</b><br>";
	}

// yeo su 019 760 0030

}

/////////////////////////////////////
###########################################################

function is_filetype_allowed( $file_name ) {

	$a   = explode( ".", $file_name );
	$ext = strtolower( array_pop( $a ) );

	if ( ! defined( 'ALLOWED_EXT' ) || ALLOWED_EXT == 'ALLOWED_EXT' ) {
		$ALLOWED_EXT = 'jpg, jpeg, gif, png, doc, pdf, wps, hwp, txt, bmp, rtf, wri';
	} else {
		$ALLOWED_EXT = trim( strtolower( ALLOWED_EXT ) );
	}

	$ext_list = preg_split( "/[\s,]+/", ( $ALLOWED_EXT ) );

	return in_array( $ext, $ext_list );
}

###########################################################

function is_imagetype_allowed( $file_name ) {

	$a   = explode( ".", $file_name );
	$ext = strtolower( array_pop( $a ) );

	if ( ! defined( "ALLOWED_IMG" ) || ALLOWED_IMG == 'ALLOWED_IMG' ) {
		$ALLOWED_IMG = 'jpg, jpeg, gif, png, doc, pdf, wps, hwp, txt, bmp, rtf, wri';
	} else {
		$ALLOWED_IMG = trim( strtolower( ALLOWED_IMG ) );
	}

	//$ext_list = explode (',',$ALLOWED_EXT);
	$ext_list = preg_split( "/[\s,]+/", ( $ALLOWED_IMG ) );

	return in_array( $ext, $ext_list );

}

/////////////////////////////////////

function get_tmp_img_name( $session_id = '' ) {

	if ( $session_id == '' ) {
		$session_id = addslashes( session_id() );
	}
	$uploaddir = TEMP_PATH;
	$dh        = opendir( $uploaddir );
	while ( ( $file = readdir( $dh ) ) !== false ) {
		if ( strpos( $file, "tmp_" . md5( $session_id ) ) !== false ) {

			return $uploaddir . $file;
		}
	}

	return "";
}

////////////////////////////

function update_temp_order_timestamp() {

	$now = ( gmdate( "Y-m-d H:i:s" ) );
	$sql = "UPDATE temp_orders SET order_date='$now' WHERE session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], session_id() ) . "' ";
	mysqli_query( $GLOBALS['connection'], $sql );

}

////////////////

function show_nav_status( $page_id ) {

    ?>
<nav aria-label="breadcrumb">
    <ul class="form-pagelist">
<?php
	global $label;
	for ( $i = 1; $i <= 5; $i ++ ) {
        $active = "";
		if ( $i == $page_id ) {
			$active = "active";
		}
		echo "<li class='form-pagelist-item $active' aria-current='page'>".$label[ 'advertiser_nav_status' . $i ]."</li>";
	}
	?>
    </ul>
</nav>
    <?php

}

////////////////////////

/**
 * @param string
 *
 * @desc Strip forbidden tags and delegate tag-source check to removeEvilAttributes()
 * @return string
 */
function removeEvilAttributes( $tagSource ) {
	$stripAttrib = '/ (style|class|onclick|ondblclick|onmousedown|onmouseup|onmouseover|onmousemove|onmouseout|onkeypress|onkeydown|onkeyup|onload)=/'; // (\'|")[^$2]+/i
	//$tagSource = stripslashes($tagSource);
	$tagSource = preg_replace( $stripAttrib, '  ', $tagSource );
	// $tagSource = addslashes($tagSource);
	//echo htmlentities($tagSource);
	return $tagSource;
}

/**
 * @param string
 *
 * @desc Strip forbidden attributes from a tag
 * @return string
 */
function removeEvilTags( $source ) {
	$allowedTags = '<h1><b><br><br><i><a><ul><li><hr><blockquote><img><span><div><font><p><em><strong><center><div><table><td><tr>';
	$source      = strip_tags( $source, $allowedTags );

	return removeEvilAttributes( $source );
	//return preg_replace('/<(.*?)>/ie', "'<'.removeEvilAttributes('\\1').'>'", $source);
}

##############################################################

function remove_non_latin1_chars( $str ) {
	// strip out characters that aren't valid in ISO-8859-1 (Also known as 'Latin 1', used in HTML Documents)
	return preg_replace( '/[^\x09\x0A\x0D\x20-\x7F\xC0-\xFF]/', '', $str );

}

################################################

function trim_date( $gmdate ) {
	preg_match( "/(\d+-\d+-\d+).+/", $gmdate, $m );

	return $m[1];

}

###########################################

function get_formatted_date( $date ) {

	if ( ! defined( 'DATE_INPUT_SEQ' ) ) {
		define( 'DATE_INPUT_SEQ', 'YMD' );
	}

	$year = substr( $date, 0, 4 );
	$ret  = $s = "";

	if ( ( $year > 2038 ) || ( $year < 1970 ) ) {  //  out of range to format!
		$month    = substr( $date, 5, 2 );
		$day      = substr( $date, 8, 2 );
		$sequence = strtoupper( DATE_INPUT_SEQ );
		while ( $widget = substr( $sequence, 0, 1 ) ) {
			switch ( $widget ) {
				case 'Y':
					$ret .= $s . $year;
					break;
				case 'M':
					$ret .= $s . $month;
					break;
				case 'D':
					$ret .= $s . $day;
					break;
			}
			$s        = '-';
			$sequence = substr( $sequence, 1 );
		}

		return $ret;

	}

	// else:
	$time = strtotime( $date );

	return date( DATE_FORMAT, $time );

}

function get_local_time( $gmdate ) {

	if ( ( strpos( $gmdate, 'GMT' ) === false ) && ( ( strpos( $gmdate, 'UTC' ) === false ) ) && ( ( strpos( $gmdate, '+0000' ) === false ) ) ) { // gmt not found
		$gmdate = $gmdate . " GMT";

	}
	date_default_timezone_set( "GMT" );
	$gmtime = strtotime( $gmdate );

	if ( $gmtime == - 1 ) { // out of range
		preg_match( "/(\d+-\d+-\d+).+/", $gmdate, $m );

		return $m[1];

	} else {

		return gmdate( "Y-m-d H:i:s", $gmtime + ( 3600 * GMT_DIF ) );
	}

}

function break_long_words( $input, $with_tags ) {
	// new routine, deals with html tags...
	if ( defined( 'LNG_MAX' ) ) {
		$lng_max = LNG_MAX;
	} else {
		$lng_max = 100;
	}
	//echo $lng_max;

	$input = stripslashes( $input );

	while ( $trun_str = truncate_html_str( $input, $lng_max, $trunc_str_len, false, $with_tags ) ) {

		//echo "trun_str:".htmlentities($trun_str)."<br>";
		$new_str = "";
		if ( $trunc_str_len == $lng_max ) { // string was truncated

			//echo "truncate!";
			if ( strrpos( $trun_str, " " ) !== false ) { // if trun_str has a space?
				$new_str .= $trun_str;
				//echo " has space![".htmlentities($trun_str)."]<br>";

			} else {
				$new_str .= $trun_str . " ";
				//echo " no space[".htmlentities($trun_str)."]<br>";

			}

		} else {
			$new_str .= $trun_str;
		}
		$input = substr( $input, strlen( $trun_str ) );
	}

	$new_str = addslashes( $new_str );

	return $new_str;

}

#######################################
# function truncate_html_str
# truncate a string encoded with htmlentities eg &nbsp; is counted as 1 character
# Limitation: does not work with well if the string contains html tags, (but does it's best to deal with them).
function truncate_html_str( $s, $MAX_LENGTH, &$trunc_str_len ) {

	$trunc_str_len = 0;

	if ( func_num_args() > 3 ) {
		$add_ellipsis = func_get_arg( 3 );

	} else {
		$add_ellipsis = true;
	}

	if ( func_num_args() > 4 ) {
		$with_tags = func_get_arg( 4 );

	} else {
		$with_tags = false;
	}

	if ( $with_tags ) {
		$tag_expr = "|<[^>]+>";

	}

	$offset          = 0;
	$character_count = 0;
	# match a character, or characters encoded as html entity
	# treat each match as a single character
	#
	$str = "";
	while ( ( preg_match( '/(&#?[0-9A-z]+;' . $tag_expr . '|.|\n)/', $s, $maches, PREG_OFFSET_CAPTURE, $offset ) && ( $character_count < $MAX_LENGTH ) ) ) {
		$offset += strlen( $maches[0][0] );
		$character_count ++;
		$str .= $maches[0][0];

	}
	if ( ( $character_count == $MAX_LENGTH ) && ( $add_ellipsis ) ) {
		$str = $str . "...";
	}
	$trunc_str_len = $character_count;

	return $str;

}

/////////////////////////////////////////

// assumming that load_banner_constants($_REQUEST['BID']); was called...
function get_pixel_image_size( $order_id ) {

	$sql = "SELECT * FROM blocks WHERE order_id='" . intval( $order_id ) . "' ";

	$result3 = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

	// find high x, y & low x, y
	// low x,y is the top corner, high x,y is the bottom corner

	while ( $block_row = mysqli_fetch_array( $result3 ) ) {

		$high_x = ! isset( $high_x ) ? $block_row['x'] : $high_x;
		$high_y = ! isset( $high_y ) ? $block_row['y'] : $high_y;
		$low_x  = ! isset( $low_x ) ? $block_row['x'] : $low_x;
		$low_y  = ! isset( $low_y ) ? $block_row['y'] : $low_y;

		if ( $block_row['x'] > $high_x ) {
			$high_x = $block_row['x'];
		}

		if ( $block_row['y'] > $high_y ) {
			$high_y = $block_row['y'];
		}

		if ( $block_row['y'] < $low_y ) {
			$low_y = $block_row['y'];
		}

		if ( $block_row['x'] < $low_x ) {
			$low_x = $block_row['x'];
		}
	}

	$high_x = ! isset( $high_x ) ? 0 : $high_x;
	$high_y = ! isset( $high_y ) ? 0 : $high_y;
	$low_x  = ! isset( $low_x ) ? 0 : $low_x;
	$low_y  = ! isset( $low_y ) ? 0 : $low_y;

	$banner_data = load_banner_constants( $block_row['banner_id'] );

	$size['x'] = ( $high_x + $banner_data['BLK_WIDTH'] ) - $low_x;
	$size['y'] = ( $high_y + $banner_data['BLK_HEIGHT'] ) - $low_y;

	return $size;

}

////////////////

function bcmod_wrapper( $x, $y ) {
	if ( function_exists( 'bcmod' ) ) {
		return bcmod( $x, $y );
	}
	// how many numbers to take at once? carefull not to exceed (int)
	$take = 5;
	$mod  = '';

	do {
		$a   = (int) $mod . substr( $x, 0, $take );
		$x   = substr( $x, $take );
		$mod = $a % $y;
	} while ( strlen( $x ) );

	return (int) $mod;
}

////////////////////////////////

function elapsedtime( $sec ) {
	$days    = floor( $sec / 86400 );
	$hrs     = floor( bcmod_wrapper( $sec, 86400 ) / 3600 );
	$mins    = round( bcmod_wrapper( bcmod_wrapper( $sec, 86400 ), 3600 ) / 60 );
	$tstring = "";
	if ( $days > 0 ) {
		$tstring = $days . "d, ";
	}
	if ( $hrs > 0 ) {
		$tstring = $tstring . $hrs . "h, ";
	}
	$tstring = "" . $tstring . $mins . "m";

	return $tstring;
}

///////////////////////////////////////////

//////////////////
// convert decimal string to a hex string.
function decimal_to_hex( $decimal ) {
	return sprintf( '%X', $decimal );
}

function htmlent_to_hex( $str ) {
	// convert html Unicode entities to Javascript Unicode entities &#51060 to \u00ED
	return preg_replace_callback( "/&#([0-9A-z]+);/", function ( $m ) {
		decimal_to_hex( $m[1] );
	}, $str );
}

// Javascript string preparation.
function js_out_prep( $str ) {
	$str = addslashes( $str );
	$str = htmlent_to_hex( $str );

	return $str;
}

function echo_copyright() {
	?>
		Built with Love and Coffee by the Drupal Community. <br />
    Powered By <a target="_blank" href="https://milliondollarscript.com/">Million Dollar Script</a> Copyright &copy; 2010-<?php echo date( "Y" ); ?>
	<?php
}

// catch errors
function shutdown() {
	$isError = false;
	if ( $error = error_get_last() ) {
		switch ( $error['type'] ) {
			case E_ERROR:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				$isError = true;
				break;
		}
	}

	if ( $isError ) {
		echo "Script execution halted ({$error['message']})";

		// php memory error
		if ( substr_count( $error['message'], 'Allowed memory size' ) ) {
			echo "<br />Try increasing your PHP memory limit and restarting the web server.";
		}

	} else {
		echo "Script completed";
	}
}

// validate session id
// http://php.net/manual/en/function.session-id.php#116836
function session_valid_id( $session_id ) {
	return preg_match( '/^[-,a-zA-Z0-9]{1,128}$/', $session_id ) > 0;
}

/**
 * Get Max Allowed Upload Size
 *
 * @link https://stackoverflow.com/a/40484281/311458
 *
 * @return mixed
 */
function _GetMaxAllowedUploadSize() {
	$Sizes   = array();
	$Sizes[] = ini_get( 'upload_max_filesize' );
	$Sizes[] = ini_get( 'post_max_size' );
	$Sizes[] = ini_get( 'memory_limit' );

	$Sizes = convertMemoryToBytes( $Sizes );

	return min( $Sizes );
}

/**
 * Convert an array memory string values to integer values
 *
 * @param array $Sizes
 *
 * @return array
 */
function convertMemoryToBytes( $Sizes ) {
	for ( $x = 0; $x < count( $Sizes ); $x ++ ) {
		$Last = strtolower( $Sizes[ $x ][ strlen( $Sizes[ $x ] ) - 1 ] );
		if ( $Last == 'k' ) {
			$Sizes[ $x ] *= 1024;
		} elseif ( $Last == 'm' ) {
			$Sizes[ $x ] *= 1024;
			$Sizes[ $x ] *= 1024;
		} elseif ( $Last == 'g' ) {
			$Sizes[ $x ] *= 1024;
			$Sizes[ $x ] *= 1024;
			$Sizes[ $x ] *= 1024;
		} elseif ( $Last == 't' ) {
			$Sizes[ $x ] *= 1024;
			$Sizes[ $x ] *= 1024;
			$Sizes[ $x ] *= 1024;
			$Sizes[ $x ] *= 1024;
		}
	}

	return $Sizes;
}

/**
 * Attempt to increase the Memory Limit based on image size.
 *
 * @link https://alvarotrigo.com/blog/watch-beauty-and-the-beast-2017-full-movie-online-streaming-online-and-download/
 *
 * @param string $filename
 */
function setMemoryLimit( $filename ) {
	//this might take time so we limit the maximum execution time to 50 seconds
	set_time_limit( 50 );

	//initializing variables
	list( $maxMemoryUsage ) = convertMemoryToBytes( array( "512M" ) );
	list( $currentLimit ) = convertMemoryToBytes( array( ini_get( 'memory_limit' ) ) );
	$currentUsage = memory_get_usage();
	$width        = 0;
	$height       = 0;

	//getting the image width and height
	list( $width, $height ) = getimagesize( $filename );

	//calculating the needed memory
	$size = $currentUsage + $currentLimit + ( floor( $width * $height * 4 * 1.5 + 1048576 ) );

	// make sure memory limit is within range
	$size = min( max( $size, MEMORY_LIMIT ), $maxMemoryUsage );

	//updating the default value
	ini_set( 'memory_limit', $size );
}

/**
 * Function to validate email addresses
 *
 * @link https://stackoverflow.com/a/42969643/311458
 *
 * @param $email
 *
 * @return bool
 */
function validate_mail( $email ) {
	$emailB = filter_var( $email, FILTER_SANITIZE_EMAIL );

	if ( filter_var( $emailB, FILTER_VALIDATE_EMAIL ) === false || $emailB != $email ) {
		return false;
	}

	return true;
}
