<?php
/**
 * @package		mds
 * @copyright	(C) Copyright 2020 Ryan Rhode, All rights reserved.
 * @author		Ryan Rhode, ryan@milliondollarscript.com
 * @license		This program is free software; you can redistribute it and/or modify
 *		it under the terms of the GNU General Public License as published by
 *		the Free Software Foundation; either version 3 of the License, or
 *		(at your option) any later version.
 *
 *		This program is distributed in the hope that it will be useful,
 *		but WITHOUT ANY WARRANTY; without even the implied warranty of
 *		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *		GNU General Public License for more details.
 *
 *		You should have received a copy of the GNU General Public License along
 *		with this program;  If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 *		Million Dollar Script
 *		A pixel script for selling pixels on your website.
 *
 *		For instructions see README.txt
 *
 *		Visit our website for FAQs, documentation, a list team members,
 *		to post any bugs or feature requests, and a community forum:
 * 		https://milliondollarscript.com/
 *
 */

@set_time_limit ( 260 );
session_save_path('/app/files/sessions/');
session_start();
if (isset($_REQUEST['order_id'])) {
	$_SESSION['MDS_order_id'] = $_REQUEST['order_id'];
}
include ("../config.php");
include ("login_functions.php");
//$BID = 1; # Banner ID. Change this later & allow users to select multiple banners
//$sql = "select * from banners where banner_id='$BID'";
//$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);
//$b_row = mysqli_fetch_array($result);


process_login();

?>

<?php





########################################################
# MAIN
########################################################

$sql = "select * from temp_orders where session_id='".mysqli_real_escape_string( $GLOBALS['connection'], session_id())."' ";
$order_result = mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));


if (mysqli_num_rows($order_result)==0) { // no order id found...
require ("header.php");
	?>
    <div class="container">
<h1><?php echo $label['no_order_in_progress']; ?></h1>
<p><?php $label['no_order_in_progress_go_here'] = str_replace ('%ORDER_PAGE%', $order_page ,  $label['no_order_in_progress_go_here']); echo $label['no_order_in_progress_go_here']; ?></p>
    </div>
	<?php
	require ("footer.php");
	die();

} else {
	$order_row = mysqli_fetch_array($order_result);
}


#################################
require ("header.php");
?>
<div class="container">
<?php show_nav_status (4); ?>
<h3><?php echo $label['advertiser_pay_sel_method']; ?></h3>
<?php
if (($_REQUEST['action']=='confirm') || (($_REQUEST['action']=='complete'))){

	// move temp order to confirmed order

	if ($order_id = reserve_pixels_for_temp_order($order_row)) {

//echo "the order id is: $order_id<br>";

		// check the user's rank
		$sql = "select * from users where ID='".intval($_SESSION['MDS_ID'])."'";
		$u_result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);
		$u_row = mysqli_fetch_array($u_result);


		if (($order_row['price']==0) || ($u_row['Rank']==2)) {
			complete_order ($_SESSION['MDS_ID'], $order_id);
		} else {
			confirm_order ($_SESSION['MDS_ID'], $order_id);
		}
	} else { // we have a problem...

			?>
			<h1><?php echo $label['sorry_head']; ?></h1>
			<p><?php
			if (USE_AJAX=='SIMPLE') {
				$order_page = 'order_pixels.php';
			} else {
				$order_page = 'select.php';
			}
			$label['sorry_head2'] = str_replace ('%ORDER_PAGE%', $order_page , $label['sorry_head2']);
			echo $label['sorry_head2'];?></p>
			<?php
			require ("footer.php");
			die();

	}

	$_REQUEST['order_id'] = $order_id;

} else {
	$order_id = $_REQUEST['order_id'];
}
##########################
if ($_REQUEST['action']=='confirm') {
	$sql = "SELECT * from orders where order_id='".intval($order_id)."'";
	$result = mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']).$sql);
	$order_row = mysqli_fetch_array($result);

	$dir = dirname(__FILE__);
	$dir = preg_split ('%[/\\\]%', $dir);
	$blank = array_pop($dir);
	$dir = implode('/', $dir);

	include $dir.'/payment/payment_manager.php';

	payment_option_list($order_id);

}
?>
</div>
<?php
require ("footer.php");