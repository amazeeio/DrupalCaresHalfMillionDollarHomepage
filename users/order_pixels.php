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


include( "../config.php" );
require_once '../include/session.php';
$db_sessions = new DBSessionHandler();
include( "login_functions.php" );

process_login();

//echo "session id:".session_id();
//echo " ".strlen(session_id());

//print_r($_SESSION);
//print_r($_REQUEST);

$BID             = ( isset( $_REQUEST['BID'] ) && $f2->bid( $_REQUEST['BID'] ) != '' ) ? $f2->bid( $_REQUEST['BID'] ) : $BID = 1;
$_SESSION['BID'] = $BID;

###############################
if ( isset( $_REQUEST['order_id'] ) && $_REQUEST['order_id'] != '' ) {

	$_SESSION['MDS_order_id'] = $_REQUEST['order_id'];

	if ( ( ! is_numeric( $_REQUEST['order_id'] ) ) && ( $_REQUEST['order_id'] != 'temp' ) ) {
		die();
	}

}
################################
/*

Delete temporary order when the banner was changed.

*/

if ( ( isset( $_REQUEST['banner_change'] ) && $_REQUEST['banner_change'] != '' ) || ( isset( $_FILES['graphic'] ) && $_FILES['graphic']['tmp_name'] != '' ) ) {


	delete_temp_order( session_id() );

}

#################################

$tmp_image_file = get_tmp_img_name();

# load order from php
# only allowed 1 new order per banner

$sql = "SELECT * from orders where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' and status='new' and banner_id='$BID' ";
//$sql = "SELECT * from orders where order_id=".$_SESSION[MDS_order_id];
$order_result = mysqli_query( $GLOBALS['connection'], $sql );
$order_row    = mysqli_fetch_array( $order_result );

if ( ( $order_row['user_id'] != '' ) && $order_row['user_id'] != $_SESSION['MDS_ID'] ) { // do a test, just in case.

	die( 'you do not own this order!' );

}

if ( ( $_SESSION["MDS_order_id"] == '' ) || ( USE_AJAX == 'YES' ) ) { // guess the order id
	$_SESSION["MDS_order_id"] = $order_row["order_id"];
}

###############################

$banner_data = load_banner_constants( $BID );

// Update time stamp on temp order (if exists)

update_temp_order_timestamp();

###############################

$sql = "select block_id, status, user_id FROM blocks where banner_id='$BID' ";
$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
while ( $row = mysqli_fetch_array( $result ) ) {
	$blocks[ $row['block_id'] ] = $row['status'];
	//if (($row[user_id] == $_SESSION['MDS_ID']) && ($row['status']!='ordered') && ($row['status']!='sold')) {
	//	$blocks[$row[block_id]] = 'onorder';
	//	$order_exists = true;
	//}
	//echo $row[block_id]." ";
}

###############################

require( "header.php" );

?>
<div class="container">

    <script type="text/javascript">

		var browser_compatible = false;
		var browser_checked = false;
		var selectedBlocks = new Array();
		var selBlocksIndex = 0;

		function refreshSelectedLayers() {
			var pointer = document.getElementById('block_pointer');

		} //End testing()
		//End -J- Edit: Custom functions for resize bug

		//Begin -J- Edit: Custom functions for resize bug
		//Taken from http://www.quirksmode.org/js/findpos.html; but modified
		function findPosX(obj) {
			var curleft = 0;
			if (obj.offsetParent) {
				while (obj.offsetParent) {
					curleft += obj.offsetLeft;
					obj = obj.offsetParent;
				}
			} else if (obj.x)
				curleft += obj.x;
			return curleft;
		}

		//Taken from http://www.quirksmode.org/js/findpos.html; but modified
		function findPosY(obj) {
			var curtop = 0;
			if (obj.offsetParent) {
				while (obj.offsetParent) {
					curtop += obj.offsetTop;
					obj = obj.offsetParent;
				}
			} else if (obj.y)
				curtop += obj.y;
			return curtop;
		}


		function is_browser_compatible() {

			/*
		userAgent should not be used, but since there is a bug in Opera, and there is
		no way to detect this bug unless userAgent is used...
			*/

			if ((navigator.userAgent.indexOf("Opera") !== -1)) {
				// does not work in Opera
				// cannot work out why?
				return false;
			} else {

				if (navigator.userAgent.indexOf("Gecko") !== -1) {
					// gecko based browsers should be ok
					// this includes safari?
					// continue to other tests..

				} else {
					if (navigator.userAgent.indexOf("MSIE") === -1) {
						return false; // unknown..
					}
				}

				//return false; // mozilla incompatible

			}

			// check if we can get by element id

			if (!document.getElementById) {

				return false;
			}

			// check if we can XMLHttpRequest

			return typeof XMLHttpRequest !== 'undefined';

		}

		///////////////////////////////////////////////
		var trip_count = 0;

		function check_selection(OffsetX, OffsetY) {

			var grid_width =<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>;
			var grid_height =<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>;

			var blk_width = <?php echo $banner_data['BLK_WIDTH']; ?>;
			var blk_height = <?php echo $banner_data['BLK_HEIGHT']; ?>;

			window.map_x = OffsetX;
			window.map_y = OffsetY;

			window.clicked_block = ((window.map_x) / blk_width) + ((window.map_y / blk_height) * (grid_width / blk_width));

			if (window.clicked_block === 0) {
				// convert to string
				window.clicked_block = "0";

			}

			//////////////////////////////////////////////////
			// Trip to the database.
			//////////////////////////////////////////////////

			var xmlhttp;

			if (typeof XMLHttpRequest !== "undefined") {
				xmlhttp = new XMLHttpRequest();
			}

			// Note: do not use &amp; for & here
			xmlhttp.open("GET", "check_selection.php?user_id=<?php echo $_SESSION['MDS_ID'];?>&map_x=" + OffsetX + "&map_y=" + OffsetY + "&block_id=" + window.clicked_block + "&BID=<?php echo $BID . "&t=" . time(); ?>", true);

			if (trip_count !== 0) { // trip_count: global variable counts how many times it goes to the server
				document.getElementById('submit_button1').disabled = true;
				document.getElementById('submit_button2').disabled = true;
				var pointer = document.getElementById('block_pointer');
				pointer.style.cursor = 'wait';
				var pixelimg = document.getElementById('pixelimg');
				pixelimg.style.cursor = 'wait';

			}

			xmlhttp.onreadystatechange = function () {
				if (xmlhttp.readyState === 4) {

					// bad selection - not available
					if (xmlhttp.responseText.indexOf('E432') > -1) {
						alert(xmlhttp.responseText);
						is_moving = true;

					}

					document.getElementById('submit_button1').disabled = false;
					document.getElementById('submit_button2').disabled = false;

					var pointer = document.getElementById('block_pointer');
					pointer.style.cursor = 'pointer';
					var pixelimg = document.getElementById('pixelimg');
					pixelimg.style.cursor = 'pointer';

				}

			};

			xmlhttp.send(null);

		}

		function make_selection(event) {

			event.stopPropagation();
			event.preventDefault();

			window.reserving = true;

			var xmlhttp;

			if (typeof XMLHttpRequest !== "undefined") {
				xmlhttp = new XMLHttpRequest();
			}

			// Note: do not use &amp; for & here
			xmlhttp.open("GET", "make_selection.php?user_id=<?php echo $_SESSION['MDS_ID'];?>&map_x=" + window.map_x + "&map_y=" + window.map_y + "&block_id=" + window.clicked_block + "&BID=<?php echo $BID . "&t=" . time(); ?>", true);

			var pointer = document.getElementById('block_pointer');
			pointer.style.cursor = 'wait';
			var pixelimg = document.getElementById('pixelimg');
			pixelimg.style.cursor = 'wait';
			document.body.style.cursor = 'wait';
			var submit1 = document.getElementById('submit_button1');
			var submit2 = document.getElementById('submit_button2');
			submit1.disabled = true;
			submit2.disabled = true;
			submit1.value = "<?php echo $f2->nl2html( $label['reserving_pixels'] ); ?>";
			submit2.value = "<?php echo $f2->nl2html( $label['reserving_pixels'] ); ?>";
			submit1.style.cursor = 'wait';
			submit2.style.cursor = 'wait';

			xmlhttp.onreadystatechange = function () {
				if (xmlhttp.readyState === 4) {
					document.form1.submit();
				}
			};

			xmlhttp.send(null);
		}

		//////////////////////////////////////////
		// Initialize
		var block_str = "<?php echo $order_row["blocks"]; ?>";
		trip_count = 0;

		//////////////////////////////////

		var pos;

		function getObjCoords(obj) {
			var pos = {x: 0, y: 0};
			var curtop = 0;
			var curleft = 0;
			if (obj.offsetParent) {
				while (obj.offsetParent) {
					curtop += obj.offsetTop;
					curleft += obj.offsetLeft;
					obj = obj.offsetParent;
				}
			} else if (obj.y) {
				curtop += obj.y;
				curleft += obj.x;
			}
			pos.x = curleft;
			pos.y = curtop;
			return pos;
		}

		///////////////////////////////////////////////////

		function show_pointer(e) {
			var button = document.getElementById('submit_button1');

			//return;
			if (!browser_checked) {
				browser_compatible = is_browser_compatible();
			}

			if (!browser_compatible) {
				return false;
			}

			browser_checked = true;

			var pixelimg = document.getElementById('pixelimg');
			var pointer = document.getElementById('block_pointer');

			if (!is_moving) return;

			var pos = getObjCoords(pixelimg);

			if (e.offsetX != undefined) {
				var OffsetX = e.offsetX;
				var OffsetY = e.offsetY;
			} else {
				var OffsetX = e.pageX - pos.x;
				var OffsetY = e.pageY - pos.y;
			}

			OffsetX = Math.floor(OffsetX / <?php echo $banner_data['BLK_WIDTH']; ?>) *<?php echo $banner_data['BLK_WIDTH']; ?>;
			OffsetY = Math.floor(OffsetY / <?php echo $banner_data['BLK_HEIGHT']; ?>) *<?php echo $banner_data['BLK_HEIGHT']; ?>;

			if (isNaN(OffsetX) || isNaN(OffsetY)) {
				return
			}

			if (pointer_height + OffsetY > <?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];?>) {

			} else {
				pointer.style.top = pos.y + OffsetY + 'px';
				pointer.map_y = OffsetY;
			}

			if (pointer_width + OffsetX > <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];?>) {

			} else {
				pointer.map_x = pos.x + OffsetX;

				pointer.style.left = pos.x + OffsetX + 'px';
			}

			return true;
		}

		var i_count = 0;

		///////////////////////

		function show_pointer2(e) {
			//function called when mouse is over the actual pointing image

			if (!is_moving) return;

			var pixelimg = document.getElementById('pixelimg');
			var pointer = document.getElementById('block_pointer');

			var pos = getObjCoords(pixelimg);
			var p_pos = getObjCoords(pointer);

			if (e.offsetX != undefined) {
				var OffsetX = e.offsetX;
				var OffsetY = e.offsetY;
				var ie = true;
			} else {
				var OffsetX = e.pageX - pos.x;
				var OffsetY = e.pageY - pos.y;
				var ie = false;
			}

			if (ie) { // special routine for internet explorer...

				var rel_posx = p_pos.x - pos.x;
				var rel_posy = p_pos.y - pos.y;

				pointer.map_x = rel_posx;
				pointer.map_y = rel_posy;

				if (isNaN(OffsetX) || isNaN(OffsetY)) {
					return
				}

				if (OffsetX >=<?php echo $banner_data['BLK_WIDTH']; ?>) { // move the pointer right
					if (rel_posx + pointer_width >= <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>) {
					} else {
						pointer.map_x = p_pos.x +<?php echo $banner_data['BLK_WIDTH']; ?>;
						pointer.style.left = pointer.map_x + 'px';
					}

				}

				if (OffsetY ><?php echo $banner_data['BLK_HEIGHT']; ?>) { // move the pointer down

					if (rel_posy + pointer_height >= <?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>) {

						//return
					} else {

						pointer.map_y = p_pos.y +<?php echo $banner_data['BLK_HEIGHT']; ?>;
						pointer.style.top = pointer.map_y + 'px';
					}
				}

			} else {

				var tOffsetX = Math.floor(OffsetX / <?php echo $banner_data['BLK_WIDTH']; ?>) *<?php echo $banner_data['BLK_WIDTH']; ?>;
				var tOffsetY = Math.floor(OffsetY / <?php echo $banner_data['BLK_HEIGHT']; ?>) *<?php echo $banner_data['BLK_HEIGHT']; ?>;


				if (isNaN(OffsetX) || isNaN(OffsetY)) {
					//alert ('naan');
					return

				}
				if (OffsetX > tOffsetX) {

					if (pointer_width + tOffsetX > <?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];?>) {
						// dont move left
					} else {
						pointer.map_x = tOffsetX;
						pointer.style.left = pos.x + tOffsetX + 'px';
					}

				}

				if (OffsetY > tOffsetY) {

					if (pointer_height + tOffsetY > <?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];?>) { // dont move down

					} else {

						pointer.style.top = pos.y + tOffsetY + 'px';
						pointer.map_y = tOffsetY;
					}

				}

			}

		}

		//////
		function get_clicked_block() {

			var pointer = document.getElementById('block_pointer');

			var grid_width =<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];?>;
			var grid_height =<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];?>;

			var blk_width = <?php echo $banner_data['BLK_WIDTH']; ?>;
			var blk_height = <?php echo $banner_data['BLK_HEIGHT']; ?>;

			var clicked_block = ((pointer.map_x) / blk_width) + ((pointer.map_y / blk_height) * (grid_width / blk_width));

			if (clicked_block === 0) {
				clicked_block = "0";// convert to string

			}
			return clicked_block;
		}

		////////////////////

		function do_block_click() {

			if (window.reserving) {
				return;
			}

			if (is_moving) {
				var cb = get_clicked_block();
				var pointer = document.getElementById('block_pointer');
				trip_count = 1;
				check_selection(pointer.map_x, pointer.map_y);
				low_x = pointer.map_x;
				low_y = pointer.map_y;

				is_moving = false;
			} else {
				is_moving = true;
			}
		}

		var low_x = 0;
		var low_y = 0;

		<?php

		// get the top-most, left-most block
		$low_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
		$low_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

		$sql = "SELECT block_info FROM temp_orders WHERE session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], session_id() ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
		$row        = mysqli_fetch_array( $result );

		if ( mysqli_num_rows( $result ) > 0 ) {
			$block_info = unserialize( $row['block_info'] );
		}

		$init = false;
		if ( isset( $block_info ) && is_array( $block_info ) ) {

			foreach ( $block_info as $block ) {

				if ( $low_x >= $block['map_x'] ) {
					$low_x = $block['map_x'];
					$init  = true;
				}

				if ( $low_y >= $block['map_y'] ) {
					$low_y = $block['map_y'];
					$init  = true;
				}

			}

		}

		//		if (($low_x == ($banner_data['G_WIDTH']*$banner_data['BLK_WIDTH'])) && ($low_y == ($banner_data['G_HEIGHT']*$banner_data['BLK_HEIGHT']))) {
		//
		//		}

		if ( ! $init ) {
			$low_x     = 0;
			$low_y     = 0;
			$is_moving = " is_moving=true ";
		} else {
			$is_moving = " is_moving=false ";
		}

		echo "low_x = $low_x;";
		echo "low_y = $low_y; $is_moving";

		?>

		function move_image_to_selection() {


			var pointer = document.getElementById('block_pointer');
			var pixelimg = document.getElementById('pixelimg');
			var pos = getObjCoords(pixelimg);

			pointer.style.top = pos.y + low_y + 'px';
			pointer.map_y = low_y;

			pointer.style.left = pos.x + low_x + 'px';
			pointer.map_x = low_x;

			pointer.style.visibility = 'visible';
			//show_pointer ();

		}

    </script>
    <style>
        #block_pointer {
            height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
            width: <?php echo $banner_data['BLK_WIDTH']; ?>px;
            padding: 0;
            margin: 0;
            line-height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
            font-size: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
        }
    </style>
<?php

function sum_transactions($total, $txn) {
  return [
    'price' => $total['price'] + $txn['amount'],
    'blocks' => $total['blocks'] + count(explode(',', $txn['blocks'])),
  ];
};

/**
 * Voucher upload validation.
 */
if (isset($_POST['voucher_code'])) {
    $code = (string) $_POST['voucher_code'];
    $code = preg_replace("/[^A-Za-z0-9]/", '', $code);
    $code = trim($code);
    if (empty($code)) {
      $error = '<div class="alert alert-danger" role="alert">Voucher code is invalid.</div>';
    }
    else {
      $result = mysqli_query($GLOBALS['connection'], "SELECT * FROM vouchers WHERE code = '" . mysqli_real_escape_string( $GLOBALS['connection'], $code) . "'") or die (mysqli_error($GLOBALS['connection']));
      $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
      if (empty($row)) {
        $error = '<div class="alert alert-danger" role="alert">Voucher code is invalid.</div>';
      }
      else {
        $sql = "SELECT t.amount, o.blocks FROM transactions t LEFT JOIN orders o on t.order_id = o.order_id where t.reason='" . mysqli_real_escape_string( $GLOBALS['connection'], $code) . "' and t.`type`='DEBIT' ";
        $result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
        $voucher_debits = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $total_debits = array_reduce($voucher_debits, "sum_transactions", ['price' => 0, 'blocks' => 0]);

        $sql = "SELECT t.amount, o.blocks FROM transactions t LEFT JOIN orders o on t.order_id = o.order_id where t.reason='" . mysqli_real_escape_string( $GLOBALS['connection'], $code) . "' and t.`type`='CREDIT' ";
        $result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
        $voucher_credits = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $total_credits = array_reduce($voucher_credits, "sum_transactions", ['price' => 0, 'blocks' => 0]);

        $total_used = [
          'price' => $total_debits['price'] - $total_credits['price'],
          'blocks' => $total_debits['blocks'] - $total_credits['blocks'],
        ];

        $voucher_info = '<div class="alert alert-success" role="alert">Voucher code is valid, and has a total discount of $' . $row['price_discount'] . '.';
        if ($total_used['price'] < $row['price_discount']) {
            $voucher_info .= ' A total of $' . ($row['price_discount'] - $total_used['price']) . ' remaining in credit.';
        }
        $voucher_info .= '</div>';
        $_SESSION['voucher_id'] = $row['voucher_id'];
        $_SESSION['voucher_code'] = $code;
        $_SESSION['voucher_price_discount'] = $row['price_discount'];
        $_SESSION['voucher_price_left'] = (int) $row['price_discount'] - $total_used['price'];
        $_SESSION['voucher_blocks_left'] = (int) $row['blocks_discount'] - $total_used['blocks'];
      }
    }
}

/**
 * Image upload validation.
 */
else if ( isset( $_FILES['graphic'] ) && $_FILES['graphic']['tmp_name'] != '' ) {

	global $f2;

	$uploaddir = TEMP_PATH;

	//$parts = split ('\.', $_FILES['graphic']['name']);
	$parts = $file_parts = pathinfo( $_FILES['graphic']['name'] );
	$ext   = $f2->filter( strtolower( $file_parts['extension'] ) );

	// CHECK THE EXTENSION TO MAKE SURE IT IS ALLOWED
	$ALLOWED_EXT = array( 'jpg', 'jpeg', 'gif', 'png' );

	if ( ! in_array( $ext, $ALLOWED_EXT ) && file_exists( $label['advertiser_file_type_not_supp'] . $ext ) ) {
		$error = '<div class="alert alert-danger" role="alert">' . $label['advertiser_file_type_not_supp'] . " ($ext)</div>";
		$image_changed_flag = false;
	}
	if ( isset( $error ) ) {
		//echo "<font color='red'>Error, image upload failed</font>";
		echo $error;

	} else {


		// clean up is handled by the delete_temp_order($sid) function...

		delete_temp_order( session_id() );

		// delete temp_* files older than 24 hours
		$dh = opendir( $uploaddir );
		while ( ( $file = readdir( $dh ) ) !== false ) {

			$elapsed_time = 60 * 60 * 24; // 24 hours

			// delete old files
			$stat = stat( $uploaddir . $file );
			if ( $stat[9] < ( time() - $elapsed_time ) ) {
				if ( strpos( $file, 'tmp_' . md5( session_id() ) ) !== false ) {
					unlink( $uploaddir . $file );
				}

			}

		}

		$uploadfile = $uploaddir . "tmp_" . md5( session_id() ) . ".$ext";

		if ( move_uploaded_file( $_FILES['graphic']['tmp_name'], $uploadfile ) ) {
			//echo "File is valid, and was successfully uploaded.\n";
			$tmp_image_file = $uploadfile;

			setMemoryLimit( $uploadfile );

			// check the file size for min an max blocks.

			$size        = getimagesize( $tmp_image_file );
			$size        = get_required_size( $size[0], $size[1], $banner_data );
			$pixel_count = $size[0] * $size[1];
			$block_size  = $pixel_count / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

			if ( ( $block_size > $banner_data['G_MAX_BLOCKS'] ) && ( $banner_data['G_MAX_BLOCKS'] > 0 ) ) {

				$limit = $banner_data['G_MAX_BLOCKS'] * $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'];

				$label['max_pixels_required'] = str_replace( '%MAX_PIXELS%', $limit, $label['max_pixels_required'] );
				$label['max_pixels_required'] = str_replace( '%COUNT%', $pixel_count, $label['max_pixels_required'] );
				echo '<div class="alert alert-danger" role="alert">';
				echo $label['max_pixels_required'];
				echo '</div>';
				unlink( $tmp_image_file );
				unset( $tmp_image_file );

			} elseif ( ( $block_size < $banner_data['G_MIN_BLOCKS'] ) && ( $banner_data['G_MIN_BLOCKS'] > 0 ) ) {

				$label['min_pixels_required'] = str_replace( '%COUNT%', $pixel_count, $label['min_pixels_required'] );
				$label['min_pixels_required'] = str_replace( '%MIN_PIXELS%', $banner_data['G_MIN_BLOCKS'] * $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'], $label['min_pixels_required'] );
				echo '<div class="alert alert-danger" role="alert">';
				echo $label['min_pixels_required'];
				echo '</div>';
				unlink( $tmp_image_file );
				unset( $tmp_image_file );

			}

		} else {
			//echo "Possible file upload attack!\n";
			echo $label['pixel_upload_failed'];
		}

	}

}

// pointer.png

?>

    <span id="block_pointer" onmousemove="show_pointer2(event)" onclick="do_block_click(event);" style='cursor: pointer;position:absolute;left:0px; top:0px;background:transparent; visibility:hidden '><img src="get_pointer_graphic.php?BID=<?php echo $BID; ?>" alt=""/></span>


    <p>
		<?php
		show_nav_status( 1 );
		?>
    </p>

    <?php
    // Not valid code.
    if (isset($error)) {
        echo $error;
        unset($error);
    }
    if(isset($voucher_info)){
        echo $voucher_info;
    }
    ?>


    <p id="select_status"><?php echo( isset( $cannot_sel ) ? $cannot_sel : "" ); ?></p>

<?php

$sql = "SELECT * FROM banners order by `name` ";
$res = mysqli_query( $GLOBALS['connection'], $sql );

if ( mysqli_num_rows( $res ) > 1 ) {
	?>
    <div class="fancy_heading" style="width:85%;"><?php echo $label['advertiser_sel_pixel_inv_head']; ?></div>
    <p>
		<?php

		$label['advertiser_sel_select_intro'] = str_replace( "%IMAGE_COUNT%", mysqli_num_rows( $res ), $label['advertiser_sel_select_intro'] );

		//echo $label['advertiser_sel_select_intro'];

		?>

    </p>
    <p>
		<?php display_banner_selecton_form( $BID, $_SESSION['MDS_order_id'], $res ); ?>
    </p>
	<?php
}

if ( isset( $order_exists ) && $order_exists ) {
	echo "<p>" . $label['advertiser_order_not_confirmed'] . "</p>";

}
?>

<?php

$has_packages = banner_get_packages( $BID );
if ( $has_packages ) {
	display_package_options_table( $BID, '', false );

} else {
	display_price_table( $BID );
}

?>
    <div>
        <h3>Voucher checker</h3>
        <p>Enter the voucher code you received. This will be used to ensure the image you upload is smaller than the voucher credit.</p>
        <form method='post' action="<?php echo htmlentities( $_SERVER['PHP_SELF'] ); ?>" enctype="multipart/form-data">
            <div class="form-group">
                <label for="voucher_code">Code:</label>
                <input type='text' class="form-control" name='voucher_code' id="voucher_code" size="20">
            </div>
            <input type='hidden' name='BID' value='<?php echo $BID; ?>'/>
            <button type='submit' class="btn btn-primary mb-4">Check remaining credit</button>
        </form>
    </div>

  <?php
   // Only show the upload form after the voucher is valid.
   if (isset($_SESSION['voucher_id']) && !empty($_SESSION['voucher_id'])) {
     ?>
       <div>
           <h3><?php echo $label['pixel_uploaded_head']; ?></h3>
           <p><?php echo $label['upload_pix_description']; ?></p>
           <form method='post'
                 action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>"
                 enctype="multipart/form-data">
               <h4><?php $label['upload_your_pix']; ?></h4>
               <div class="form-group">
                   <label for="graphic">Image</label>
                   <input type='file' name='graphic' class="form-control-file" id="graphic">
               </div>
               <input type='hidden' name='BID' value='<?php echo $BID; ?>'/>
               <button type='submit' class="btn btn-primary mb-4"><?php echo $f2->rmnl($label['pix_upload_button']); ?></button>
           </form>
       </div>
     <?php
   }
   ?>
<?php

if ( ! $tmp_image_file ) {

	?>

	<?php

} else {


	?>
    <hr/>
    <h3><?php echo $label['your_uploaded_pix']; ?></h3>
		<?php

		echo "<img class='img-thumbnail' src='get_pointer_graphic.php?BID=" . $BID . "' alt='Your selected pixes' /><br />";

		$size = getimagesize( $tmp_image_file );

		?><?php
		$label['upload_image_size'] = str_replace( "%WIDTH%", "<span class='badge badge-primary'>".$size[0]."</span>", $label['upload_image_size'] );
		$label['upload_image_size'] = str_replace( "%HEIGHT%", "<span class='badge badge-primary'>".$size[1]."</span>", $label['upload_image_size'] );
		echo "<p>".$label['upload_image_size']."</p>";
		?>
		<?php

		$size = get_required_size( $size[0], $size[1], $banner_data );



		$pixel_count                     = $size[0] * $size[1];
		$block_size                      = $pixel_count / ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );
		$block_size_5 = $block_size * 5;
        echo "<p>The uploaded image will require <span class='badge badge-secondary'>$pixel_count</span> 
            pixels from the map which is a donation amount of <span class='badge badge-secondary'>$block_size_5 $/€</span>.</p>";

        // Attempt to load current voucher amount.
        if (isset($_SESSION['voucher_blocks_left'])) {
          if ($_SESSION['voucher_blocks_left'] < $block_size) {
              echo '<div class="alert alert-danger" role="alert">The remaining credit on your voucher cannot cover this amount of blocks. Perhaps you should use a smaller image?</div>';
          }
          else {
              echo '<div class="alert alert-success" role="alert">Your voucher can cover this amount of blocks.</div>';
          }
        }
        else {
            echo "<div class='alert alert-warning' role='alert'>If you donated less than <span class='badge badge-secondary'>$block_size_5 $/€</span>,
            please upload a smaller image that fits the donation amount, as you will not be able to continue later!</div>";
        }

        echo "<div class='alert alert-info' role='alert'>Please wait to place your image until the grid is loaded, it takes a bit.</div>";

		?>
	<?php //echo $label['advertiser_select_instructions']; ?>


    <form method="post" action="order_pixels.php" name='pixel_form'>
        <input type="hidden" name="jEditOrder" value="true">

        <input type="hidden" value="1" name="select">
        <input type="hidden" value="<?php echo $BID; ?>" name="BID">
        <div class="text-right">
        <button type="submit" class='btn btn-primary mb-4 d-none' <?php if ($_REQUEST['order_id']!='temp') { echo 'disabled'; } ?> type="button" name='submit_button1' id='submit_button1' onclick='document.form1.submit()'><?php echo $label['advertiser_write_ad_button']; ?></button>
        </div>

        <div class="text-center">
            <img style="border-bottom: 1px solid #D4D4D4; border-right: 1px solid #D4D4D4;" style="cursor: pointer;" id="pixelimg" <?php if ( ( USE_AJAX == 'YES' ) || ( USE_AJAX == 'SIMPLE' ) ) { ?> onmousemove="show_pointer(event)"  <?php } ?> type="image" name="map" value='Select Pixels.' width="<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>" height="<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>" src="show_selection.php?BID=<?php echo $BID; ?>&amp;gud=<?php echo time(); ?>"/>
        </div>

        <input type="hidden" name="action" value="select">
    </form>
    <div class="mt-4 text-right">

        <form method="post" action="write_ad.php" name="form1">
            <input type="hidden" name="package" value="">
            <input type="hidden" name="selected_pixels" value=''>
            <input type="hidden" name="order_id" value="<?php echo $_SESSION['MDS_order_id']; ?>">
            <input type="hidden" value="<?php echo $BID; ?>" name="BID">
            <button type="submit" class='btn btn-primary' <?php if (isset($_REQUEST['order_id']) && $_REQUEST['order_id']!='temp') { echo 'disabled'; } ?> name='submit_button2' id='submit_button2' onclick="make_selection(event);"><?php echo $f2->rmnl($label['advertiser_write_ad_button']); ?></button>
        </form>

        <script type="text/javascript">

			document.form1.selected_pixels.value = block_str;

        </script>

    </div>
    <script type="text/javascript">

		var pointer_width = <?php echo $size[0]; ?>;
		var pointer_height =  <?php echo $size[1]; ?>;
		window.onresize = move_image_to_selection;
		window.onload = move_image_to_selection;
		move_image_to_selection();

    </script>

	<?php
}
?>
</div>
<?php

require "footer.php";

?>
