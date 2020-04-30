<?php
/**
 * @package        mds
 * @copyright      (C) Copyright 2020 Ryan Rhode, All rights reserved.
 * @author         Ryan Rhode, ryan@milliondollarscript.com
 * @license        This program is free software; you can redistribute it and/or modify
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

use Imagine\Filter\Basic\Autorotate;

session_save_path('/app/files/sessions/');
session_start([
	'name' => 'MDSADMIN_PHPSESSID',
]);
require("../config.php");

require('admin_common.php');
require_once ("../include/ads.inc.php");

function disapprove_modified_order($order_id, $BID) {
/*
	$sql = "UPDATE orders SET approved='N' WHERE order_id='".$order_id."' AND banner_id='".$BID."' ";
	//echo $sql;
	mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));
	$sql = "UPDATE blocks SET approved='N' WHERE order_id='".$order_id."' AND banner_id='".$BID."' ";
	///echo $sql;
	mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));

	// send pixel change notification

	if (EMAIL_ADMIN_PUBLISH_NOTIFY=='YES') {
		send_published_pixels_notification($_SESSION['MDS_ID'], $BID);
	}
*/

}
?>
<?php echo $f2->get_doc(); ?>

<link rel='StyleSheet' type="text/css" href="../users/style.css" >
<style type="text/css">


</style>

<title><?php echo SITE_NAME; ?></title>

</head>

<body>

<?php

$BID = ( isset( $_REQUEST['BID'] ) && $f2->bid( $_REQUEST['BID'] ) != '' ) ? $f2->bid( $_REQUEST['BID'] ) : 1;

$banner_data = load_banner_constants($BID);

if ( isset($_REQUEST['ad_id']) && is_numeric( $_REQUEST['ad_id'] ) ) {

	$imagine = new Imagine\Gd\Imagine();

	$gd_info = @gd_info();
	if ( $gd_info['GIF Read Support'] ) {
		$gif_support = "GIF";
	};
	if ( $gd_info['JPG Support'] ) {
		$jpeg_support = "JPG";
	};
	if ( $gd_info['PNG Support'] ) {
		$png_support = "PNG";
	};

	$prams = load_ad_values( $_REQUEST['ad_id'] );

	// pre-check for failure
	if ( $prams['user_id'] == "" ) {
		die( "Either the user id for this ad doesn't exist or this ad doesn't exist." );
	}
	//echo "load const ";
	$banner_data = load_banner_constants( $prams['banner_id'] );

	$sql = "SELECT * from ads as t1, orders as t2 where t1.ad_id=t2.ad_id AND t1.user_id=" . intval($prams['user_id']) . " and t1.banner_id='" . intval($prams['banner_id']) . "' and t1.ad_id='" . intval($prams['ad_id']) . "' AND t1.order_id=t2.order_id ";
	//echo $sql."<br>";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	$row      = mysqli_fetch_array( $result );
	$order_id = $row['order_id'];
	$blocks   = explode( ',', $row['blocks'] );

	$size   = get_pixel_image_size( $row['order_id'] );
	$pixels = $size['x'] * $size['y'];
	//print_r($size);
	//echo "order id:".$row['order_id']."<br>";
	//echo "$sql<br>";

	$sql = "SELECT * from blocks WHERE order_id='" . intval($order_id) . "'";
	$blocks_result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

	if ( $_REQUEST['change_pixels'] ) {

		// a new image was uploaded...

		// move the file

		$uploaddir = TEMP_PATH;

		$parts = $file_parts = pathinfo( $_FILES['graphic']['name'] );
		$ext   = $f2->filter( strtolower( $file_parts['extension'] ) );

		// CHECK THE EXTENSION TO MAKE SURE IT IS ALLOWED
		$ALLOWED_EXT = array( 'jpg', 'jpeg', 'gif', 'png' );

		if ( ! in_array( $ext, $ALLOWED_EXT ) && file_exists( $label['advertiser_file_type_not_supp'] . $ext ) ) {
			$error              .= "<strong><font color='red'>" . $label['advertiser_file_type_not_supp'] . " ($ext)</font></strong><br />";
			$image_changed_flag = false;

		}
		if ( isset( $error ) ) {
			echo $error;

		} else {
			// clean up is handled by the delete_temp_order($sid) function...
			//delete_temp_order( session_id() );

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

			if ( move_uploaded_file( $_FILES['pixels']['tmp_name'], $uploadfile ) ) {
				//echo "File is valid, and was successfully uploaded.\n";
				$tmp_image_file = $uploadfile;

				setMemoryLimit($uploadfile);

				// check image size

				$img_size = getimagesize( $tmp_image_file );
				// check the size
				if ( ( MDS_RESIZE != 'YES' ) && ( ( $img_size[0] > $size['x'] ) || ( $img_size[1] > $size['y'] ) ) ) {
					$label['adv_pub_sizewrong'] = str_replace( '%SIZE_X%', $size['x'], $label['adv_pub_sizewrong'] );
					$label['adv_pub_sizewrong'] = str_replace( '%SIZE_Y%', $size['y'], $label['adv_pub_sizewrong'] );
					$error                      = $label['adv_pub_sizewrong'] . "<br>";

				} else { // size is ok. change the blocks.

					// create the new img...

					while ( $block_row = mysqli_fetch_array( $blocks_result ) ) {

						$high_x = ! isset( $high_x ) ? $block_row['x'] : $high_x;
						$high_y = ! isset( $high_y ) ? $block_row['y'] : $high_y;
						$low_x  = ! isset( $low_x ) ? $block_row['x'] : $low_x;
						$low_y  = ! isset( $low_y ) ? $block_row['y'] : $low_y;

						if ( $block_row['x'] > $high_x ) {
							$high_x = $block_row['x'];
						}

						if ( ! isset( $high_y ) || $block_row['y'] > $high_y ) {
							$high_y = $block_row['y'];
						}

						if ( ! isset( $low_y ) || $block_row['y'] < $low_y ) {
							$low_y = $block_row['y'];
						}

						if ( ! isset( $low_x ) || $block_row['x'] < $low_x ) {
							$low_x = $block_row['x'];
						}

					}

					$high_x = ! isset( $high_x ) ? 0 : $high_x;
					$high_y = ! isset( $high_y ) ? 0 : $high_y;
					$low_x  = ! isset( $low_x ) ? 0 : $low_x;
					$low_y  = ! isset( $low_y ) ? 0 : $low_y;

					$_REQUEST['map_x'] = $high_x;
					$_REQUEST['map_y'] = $high_y;

					$parts = explode( '.', $tmp_image_file );
					$ext   = strtolower( array_pop( $parts ) );

					// init new image with Imagine from uploaded file
					$image = $imagine->open( $tmp_image_file );

					// autorotate
					$imagine->setMetadataReader(new \Imagine\Image\Metadata\ExifMetadataReader());
					$filter = new Imagine\Filter\Transformation();
					$filter->add(new AutoRotate());
					$filter->apply($image);

					// resize uploaded image
					if ( MDS_RESIZE == 'YES' ) {
						$resize = new Imagine\Image\Box( $size['x'], $size['y'] );
						$image->resize( $resize );
					}

					// create a block size Box
					$block_size = new Imagine\Image\Box( $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );

					// Paste image into selected blocks (AJAX mode allows individual block selection)
					for ( $y = 0; $y < $size['y']; $y += $banner_data['BLK_HEIGHT'] ) {

						for ( $x = 0; $x < $size['x']; $x += $banner_data['BLK_WIDTH'] ) {

							// create new destination image
							$palette = new Imagine\Image\Palette\RGB();
							$color = $palette->color('#000', 0);
							$dest = $imagine->create($block_size, $color);

							// crop a part from the tiled image
							$block = $image->copy();
							$block->crop( new Imagine\Image\Point( $x, $y ), $block_size );

							// paste the block into the destination image
							$dest->paste( $block, new Imagine\Image\Point( 0, 0 ) );

							// save the image as a base64 encoded string
							$image_data = base64_encode( $dest->get( "png", array( 'png_compression_level' => 9 ) ) );

							// some variables
							$map_x     = $x + $low_x;
							$map_y     = $y + $low_y;
							$GRD_WIDTH = $banner_data['BLK_WIDTH'] * $banner_data['G_WIDTH'];
							//$cb        = ( ( $map_x ) / $banner_data['BLK_WIDTH'] ) + ( ( $map_y / $banner_data['BLK_HEIGHT'] ) * ( $GRD_WIDTH / $banner_data['BLK_WIDTH'] ) );
							//$block_id = get_block_id_from_position( $block_row['x'], $block_row['y'], $BID );

							// save to db
							$sql = "UPDATE blocks SET image_data='$image_data' where block_id='" . intval( $block_row['block_id'] ) . "' AND banner_id='" . intval( $BID ) . "' ";
							mysqli_query( $GLOBALS['connection'], $sql );
						}
					}
				}

				unlink( $tmp_image_file );
				unset( $tmp_image_file );

				if ( $banner_data['AUTO_APPROVE'] != 'Y' ) { // to be approved by the admin
					$sql = "UPDATE orders SET approved='N' WHERE order_id='" . intval( $order_id ) . "' AND banner_id='" . intval( $BID ) . "' ";
					mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
					$sql = "UPDATE blocks SET approved='N' WHERE order_id='" . intval( $order_id ) . "' AND banner_id='" . intval( $BID ) . "' ";
					mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
				}

				if ( $banner_data['AUTO_PUBLISH'] == 'Y' ) {
					process_image( $BID );
					publish_image( $BID );
					process_map( $BID );
				}

			} else {
				//echo "Possible file upload attack!\n";
				echo $label['pixel_upload_failed'];
			}

		}

	}

# Ad forms:
	?>
	<p>
<div class="fancy_heading" width="85%"><?php echo $label['adv_pub_editad_head']; ?></div>
<p><?php echo $label['adv_pub_editad_desc']; ?> </p>
<p><b><?php echo $label['adv_pub_yourpix'] ; ?></b></p>
<table border=0 bgcolor='#d9d9d9' cellspacing="1" cellpadding="5">
<tr bgcolor="#ffffff">
<td valign="top"><b><?php echo $label['adv_pub_piximg']; ?></b><br>
<center>
<?php
if ($_REQUEST['ad_id']!='') {
		//echo "ad is".$_REQUEST['ad_id'];
		?><img src="get_order_image.php?BID=<?php echo $BID; ?>&aid=<?php echo $_REQUEST['ad_id']; ?>" border=1><?php
	} else {
		?><img src="get_order_image.php?BID=<?php echo $BID; ?>&block_id=<?php echo $_REQUEST['block_id']; ?>" border=1><?php
	} ?>
</center>
</td>
<td valign="top"><b><?php echo $label['adv_pub_pixinfo']; ?></b><br><?php

		$label['adv_pub_pixcount'] = str_replace('%SIZE_X%',$size['x'],$label['adv_pub_pixcount']);
		$label['adv_pub_pixcount'] = str_replace('%SIZE_Y%', $size['y'],$label['adv_pub_pixcount']);
		$label['adv_pub_pixcount'] = str_replace('%PIXEL_COUNT%', $pixels,$label['adv_pub_pixcount']);
		echo $label['adv_pub_pixcount'];
		?><br></td>
<td valign="top"><b><?php echo $label['adv_pub_pixchng']; ?></b><br><?php
			$label['adv_pub_pixtochng'] = str_replace('%SIZE_X%',$size['x'],$label['adv_pub_pixtochng']);
			$label['adv_pub_pixtochng'] = str_replace('%SIZE_Y%',$size['y'],$label['adv_pub_pixtochng']);
			echo $label['adv_pub_pixtochng'];
			?><form name="change" enctype="multipart/form-data" method="post">
<input type="file" name='pixels'><br>
<input type="hidden" name="ad_id" value="<?php echo $_REQUEST['ad_id']; ?>">
<input type="submit" name="change_pixels" value="<?php echo $label['adv_pub_pixupload']; ?>"></form><?php if ($error) { echo "<font color='red'>".$error."</font>"; $error='';} ?>
<font size='1'><?php echo $label['advertiser_publish_supp_formats']; ?> <?php echo "$gif_support $jpeg_support $png_support"; ?></font>
</td>
</tr>
</table>

<p><b><?php echo $label['adv_pub_edityourad']; ?></b></p>
<?php



	if ($_REQUEST['save'] != "" ) { // saving

		$error = validate_ad_data(1);
		if ($error != '') { // we have an error
			display_ad_form (1, "user", '');
		} else {
			insert_ad_data(true); // admin mode
			$prams = load_ad_values ($_REQUEST['ad_id']);
			update_blocks_with_ad($_REQUEST['ad_id'], $prams['user_id']);
			display_ad_form (1, "user", $prams);
			// disapprove the pixels because the ad was modified..

			if ($banner_data['AUTO_APPROVE']!='Y') { // to be approved by the admin
				disapprove_modified_order($prams['order_id'], $BID);
			}

			if ($banner_data['AUTO_PUBLISH']=='Y') {
				process_image($BID);
				publish_image($BID);
				process_map($BID);
				//echo 'published.';
			}
			echo 'Ad Saved. <A href="ads.php?BID='.$prams['banner_id'].'">&lt;&lt; Go to the Ad List</a>';
			echo "<hr>";
		}
	} else {

		$prams = load_ad_values ($_REQUEST['ad_id']);
		display_ad_form (1, "user", $prams);

	}
	$prams = load_ad_values ($_REQUEST['ad_id']);
	$sql = "select * FROM users where ID='".intval($prams['user_id'])."' ";
	$result = mysqli_query($GLOBALS['connection'], $sql);
	$u_row = mysqli_fetch_array($result);


	$b_row = load_banner_row($prams['banner_id']);
	?>

	<h3>Additional Info</h3>
	<b>Customer:</b><?php echo $u_row['LastName'].', '.$u_row['FirstName'];  ?><BR>
	<b>Order #:</b><?php echo $prams['order_id'];?><br>
	<b>Grid:</b><a href='ordersmap.php?banner_id=<?php echo $prams['banner_id']; ?>'><?php echo $prams['banner_id']." - ".$b_row['name'];?></a>


	<?php
	echo '<hr>';

} else {

	// select banner id
	if (isset($_REQUEST['BID']) && $f2->bid($_REQUEST['BID'])!='') {
		$BID = $f2->bid($_REQUEST['BID']);
	} else {
		$BID = 1;

	}

	$sql = "Select * from banners ";
$res = mysqli_query($GLOBALS['connection'], $sql);
?>

<form name="bidselect" method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">

	Select grid: <select name="BID" onchange="document.bidselect.submit()">
		<option> </option>
		<?php
		while ($row=mysqli_fetch_array($res)) {

			if (($row['banner_id']==$BID) && ($BID!='all')) {
				$sel = 'selected';
			} else {
				$sel ='';

			}
			echo '<option '.$sel.' value='.$row['banner_id'].'>'.$row['name'].'</option>';
		}
		?>
	</select>
	</form>
	<hr>
	<?php
}

$count = list_ads ($admin=true,$order, $offset,  $list_mode='ALL');
?>
</body>
