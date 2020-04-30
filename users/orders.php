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


include ("../config.php");
require_once '../include/session.php';
$db_sessions = new DBSessionHandler();
include ("login_functions.php");

process_login();


require ("header.php");

if ( isset( $_REQUEST['cancel'] ) && $_REQUEST['cancel'] == 'yes' && isset( $_REQUEST['order_id'] ) ) {
	if ( $_REQUEST['order_id'] == "temp" ) {

		$sql = "SELECT * FROM temp_orders WHERE session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], session_id() ) . "'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
		if ( mysqli_num_rows( $result ) > 0 ) {
			$row = mysqli_fetch_assoc( $result );

			// delete associated ad
			$sql = "DELETE FROM ads where ad_id=" . intval( $row['ad_id'] );
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			// delete associated temp order
			$sql = "DELETE FROM temp_orders WHERE session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], session_id() ) . "'";
			mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

			// delete associated uploaded image
			$imagefile = get_tmp_img_name();
			if(file_exists($imagefile)) {
				unlink($imagefile);
			}

			// if deleted order is the current order unset current order id
			if ( $_REQUEST['order_id'] == $_SESSION['MDS_order_id'] ) {
				unset( $_SESSION['MDS_order_id'] );
			}
		}

	} else {

	$sql = "SELECT * FROM orders WHERE user_id='" . intval( $_SESSION['MDS_ID'] ) . "' AND order_id='" . intval( $_REQUEST['order_id'] ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
	if ( mysqli_num_rows( $result ) > 0 ) {
		delete_order( intval( $_REQUEST['order_id'] ) );

		// if deleted order is the current order unset current order id
		if($_REQUEST['order_id'] == $_SESSION['MDS_order_id']) {
			unset($_SESSION['MDS_order_id']);
		}
	}
}
}

?>
<div class="container">

<script language="JavaScript" type="text/javascript">

function confirmLink(theLink, theConfirmMsg)
   {
      
       if (theConfirmMsg == '') {
           return true;
       }

       var is_confirmed = confirm(theConfirmMsg + '\n');
       if (is_confirmed) {
           theLink.href += '&is_js_confirmed=1';
       }

       return is_confirmed;
   } // end of the 'confirmLink()' function

</script>

<h3><?php echo $label['advertiser_ord_history']; ?></h3>

<p>
<?php echo $label['advertiser_ord_explain']; ?>
</p>

<h4><?php echo $label['advertiser_ord_hist_list']; ?></h4>

<?php

$orders = array();

$sql = "SELECT * FROM orders AS t1, users AS t2 WHERE t1.user_id=t2.ID AND t1.user_id='" . intval( $_SESSION['MDS_ID'] ) . "' ORDER BY t1.order_date DESC ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );
while ( $row = mysqli_fetch_array( $result ) ) {
	$orders[] = $row;
}

$sql = "SELECT * FROM temp_orders WHERE session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], session_id() ) . "' ORDER BY order_date DESC ";
$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));
while ( $row = mysqli_fetch_array( $result ) ) {
	$orders[] = $row;
}

// Sort by order_date from both orders and temp_orders
function date_sort( $a, $b ) {
	return strtotime( $a['order_date'] ) < strtotime( $b['order_date'] );
}

usort( $orders, "date_sort" );

?>

    <table class="table mt-4">
        <thead>
        <tr>
            <th scope="col"><?php echo $label['advertiser_ord_prderdate']; ?></th>
            <th scope="col"><?php echo $label['advertiser_ord_custname']; ?></th>
            <th scope="col"><?php echo $label['advertiser_ord_usernid'];?></th>
            <th scope="col"><?php echo $label['advertiser_ord_orderid']; ?></th>
            <th scope="col"><?php echo $label['advertiser_ord_quantity']; ?></th>
            <th scope="col"><?php echo $label['advertiser_ord_image']; ?></th>
            <th scope="col"><?php echo $label['advertiser_ord_amount'];?></th>
            <th scope="col"><?php echo $label['advertiser_status']; ?></th>
        </tr>
        </thead>
        <tbody>
<?php

		if (count($orders) == 0) {
	echo '<td colspan="7">'.$label['advertiser_ord_noordfound'].' </td>';
} else {

			foreach($orders as $order) {
	?>
<tr>
			<td><?php echo get_local_time($order['order_date']);?></td>
			<td><?php echo isset($order['FirstName']) ? $order['FirstName']." ".$order['LastName'] : "";?></td>
			<td><?php echo isset($order['Username']) ? $order['Username'] : "";?> (#<?php echo $order['ID'];?>)</td>
			<td>#<?php echo isset($order['order_id']) ? $order['order_id'] : "";?></td>
			<td><?php echo $order['quantity'];?></td>
	<td><?php

					$sql = "select * from banners where banner_id=".intval($order['banner_id']);
			$b_result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);
			$b_row = mysqli_fetch_array($b_result);
		
			echo $b_row['name'];
			
		?></td>
			<td><?php echo convert_to_default_currency_formatted($order['currency'], $order['price']); ?></td>
			<td><?php
			    if(isset($order['status'])) {

					echo $label[$order['status']];?><br><?php
	if (USE_AJAX=='SIMPLE') {
		$order_page = 'order_pixels.php';
		$temp_var = '&order_id=temp';
	} else {
		$order_page = 'select.php';
	}

                    switch ( $order['status'] ) {
		case "new":
			echo $label['adv_ord_inprogress'].'<br>';
                            echo "<a href='" . $order_page . "?BID=" . $order['banner_id'] . "$temp_var'>(" . $label['advertiser_ord_confnow'] . ")</a>";
                            echo "<br><input type='button' value='" . $label['advertiser_ord_cancel_button'] . "' onclick='if (!confirmLink(this, \"" . $label['advertiser_ord_cancel'] . "\")) return false; window.location=\"orders.php?cancel=yes&order_id=" . $order['order_id'] . "\"' >";
			break;
		case "confirmed":
                            echo "<a href='payment.php?order_id=" . $order['order_id'] . "&BID=" . $order['banner_id'] . "'>(" . $label['advertiser_ord_awaiting'] . ")</a>";
                            //echo "<br><input type='button' value='".$label['advertiser_ord_cancel_button']."' onclick='if (!confirmLink(this, \"".$label['advertiser_ord_cancel']."\")) return false; window.location=\"orders.php?cancel=yes&order_id=".$order['order_id']."\"' >";
			break;
		case "completed":
			echo "<a href='publish.php?order_id=".$order['order_id']."&BID=".$order['banner_id']."'>(".$label['advertiser_ord_manage_pix'].")</a>";

			if ($order['days_expire'] > 0) {

				if ($order['published']!='Y') {
						$time_start = strtotime(gmdate('r'));
				} else {
					$time_start = strtotime($order['date_published']." GMT");
				}

				$elapsed_time = strtotime(gmdate('r')) - $time_start;
				$elapsed_days = floor ($elapsed_time / 60 / 60 / 24);
				
				$exp_time =  ($order['days_expire']  * 24 * 60 * 60);

				$exp_time_to_go = $exp_time - $elapsed_time;
				$exp_days_to_go =  floor ($exp_time_to_go / 60 / 60 / 24);

				$to_go = elapsedtime($exp_time_to_go);

				$elapsed = elapsedtime($elapsed_time);

				if ($order['date_published']!='') {
					echo "<br>Expires in: ".$to_go;
				}

			}

			break;
		case "expired":

			$time_expired = strtotime($order['date_stamp']);

			$time_when_cancel = $time_expired + (DAYS_RENEW * 24 * 60 * 60);

			$days =floor (($time_when_cancel - time()) / 60 / 60 / 24);

			// check to see if there is a renew_wait or renew_paid order

			$sql = "select order_id from orders where (status = 'renew_paid' OR status = 'renew_wait') AND original_order_id='".intval($order['original_order_id'])."' ";
			$res_c = mysqli_query($GLOBALS['connection'], $sql);
			if (mysqli_num_rows($res_c)==0) {
 
				$label['advertiser_ord_renew'] = str_replace("%DAYS_TO_RENEW%", $days, $label['advertiser_ord_renew']);
				echo "<a href='payment.php?order_id=".$order['order_id']."&BID=".$order['banner_id']."'><span class='text-danger'><small>(".$label['advertiser_ord_renew'].")</small></span></a>";
			}
			break;
		case "cancelled":
			break;
		case "pending":
			break;

	}

/*
	if (($row['price']==0) && ($row['status']='deleted') && && ($row['status']!='cancelled')) {

		echo "<br><input type='button' value='".$label['advertiser_ord_cancel_button']."' onclick='if (!confirmLink(this, \"".$label['advertiser_ord_cancel']."\")) return false; window.location=\"orders.php?cancel=yes&order_id=".$row['order_id']."\"' >";


	}

*/
                } else {
                    $temp_var = '&order_id=temp';
                    echo $label['adv_ord_inprogress'] . '<br>';
                    echo "<a href='order_pixels.php?BID={$order['banner_id']}{$temp_var}'>({$label['advertiser_ord_confnow']})</a>";
                    echo "<br><input type='button' value='{$label['advertiser_ord_cancel_button']}' onclick='if (!confirmLink(this, \"{$label['advertiser_ord_cancel']}\")) return false; window.location=\"orders.php?cancel=yes{$temp_var}\"' >";
                }
			}
	?></td>
	</tr>

	<?php
	}
?>
        </tbody>
</table>
</div>
<?php

require ("footer.php");

?>
