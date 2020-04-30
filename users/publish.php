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

use Imagine\Filter\Basic\Autorotate;

@set_time_limit ( 260);

include ("../config.php");
require_once '../include/session.php';
$db_sessions = new DBSessionHandler();
include ("login_functions.php");
require_once ("../include/ads.inc.php");

process_login();

$gd_info = gd_info();
if (isset($gd_info['GIF Read Support']) && !empty($gd_info['GIF Read Support'])) {$gif_support="GIF";}
if (isset($gd_info['JPG Support']) && !empty($gd_info['JPG Support'])) {$jpeg_support="JPG";}
if (isset($gd_info['PNG Support']) && !empty($gd_info['PNG Support'])) {$png_support="PNG";}

require ("header.php");
echo "<div class='container'>";

// Work out the banner id...

if ($f2->bid($_REQUEST['BID'])!='') {
	$BID = $f2->bid($_REQUEST['BID']);
	
} elseif (isset($_REQUEST['ad_id']) && !empty($_REQUEST['ad_id'])) {
	$sql = "select banner_id from ads where ad_id='".intval($_REQUEST['ad_id'])."'";
	$res = mysqli_query($GLOBALS['connection'], $sql);
	$row = mysqli_fetch_array($res);
	$BID = $row['banner_id'];
} else {
	// get the banner_id of one if the blocks the customer owns
	//$sql = "SELECT DISTINCT(blocks.banner_id) as banner_id, name FROM blocks, banners where blocks.banner_id=banners.banner_id AND user_id='".$_SESSION['MDS_ID']."' and (status='sold' or status='expired') LIMIT 1";
	
	$sql = "select *, banners.banner_id AS BID FROM orders, banners where orders.banner_id=banners.banner_id  AND user_id=".intval($_SESSION['MDS_ID'])." and (orders.status='completed' or status='expired') group by orders.banner_id order by orders.banner_id ";

	$res = mysqli_query($GLOBALS['connection'], $sql);
	if ($row = mysqli_fetch_array($res)) {
		$BID = $row['BID'];
	} else {
		$BID = 1; # this should not happen unless the above queries failed.
	}
}

//$sql = "select * from banners where banner_id='".$BID."'";
//$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);
//$banner_row = mysqli_fetch_array($result);

$banner_data = load_banner_constants($BID);

$sql = "select * from users where ID='".intval($_SESSION['MDS_ID'])."'";
$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);
$user_row = mysqli_fetch_array($result);

##################################################
# Entry point for completion of orders which are made by super users or if the order was for free
if ($_REQUEST['action']=='complete') {

	// check if order is $0 & complete it

	if ($_REQUEST['order_id']=='temp') { // convert the temp order to an order.

		$sql = "select * from temp_orders where session_id='".mysqli_real_escape_string( $GLOBALS['connection'], session_id())."' ";
		$order_result = mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));

		if (mysqli_num_rows($order_result)==0) { // no order id found...

			if (USE_AJAX=='SIMPLE') {
				$order_page = 'order_pixels.php';
			} else {
				$order_page = 'select.php';
			}
			?>
		<h1><?php echo $label['no_order_in_progress']; ?></h1>
		<p><?php $label['no_order_in_progress_go_here'] = str_replace ('%ORDER_PAGE%', $order_page ,  $label['no_order_in_progress_go_here']); echo $label['no_order_in_progress_go_here']; ?></p>
			<?php
			require ("footer.php");
			die();

		} elseif($order_row = mysqli_fetch_array($order_result)) {

			$_REQUEST['order_id'] = reserve_pixels_for_temp_order($order_row);

		} else {

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

	}

	$sql="select * from orders where order_id='".intval($_REQUEST['order_id'])."' AND user_id='".intval($_SESSION['MDS_ID'])."' ";
	$result = mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));
	$row = mysqli_fetch_array($result);
	if (($row['price']==0)||($user_row['Rank']==2)) {
		complete_order ($row['user_id'], $row['order_id']);
		// no transaction for this order
		echo "<h3>".$label['advertiser_publish_free_order']."</h3>";
	}
	// publish

	if ($banner_data['AUTO_PUBLISH']=='Y') {
		process_image($BID);
		publish_image($BID);
		process_map($BID);
		
	}

}

###############################################################

# Banner Selection form
# Load this form only if more than 1 grid exists with pixels purchased.

$sql = "select DISTINCT banners.banner_id, banners.name FROM orders, banners where orders.banner_id=banners.banner_id  AND user_id=".intval($_SESSION['MDS_ID'])." and (orders.status='completed' or status='expired') group by orders.banner_id, orders.order_id, banners.banner_id order by `name`";

$res = mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']).$sql);

if (mysqli_num_rows($res)>1) {
	?>
	<p>
	<div class="fancy_heading" width="85%"><?php echo $label['advertiser_publish_pixinv_head']; ?></div>

	<?php
	$label['advertiser_publish_select_init2'] = str_replace("%GRID_COUNT%", mysqli_num_rows($res),  $label['advertiser_publish_select_init2']);
	echo $label['advertiser_publish_select_init2'];
	?>
	</p>
	<p>
	<?php display_banner_selecton_form($BID, $_SESSION['MDS_order_id'], $res); ?>
	</p>
	<?php
		
} 


#####################################################
# A block was clicked. Fetch the ad_id and initialize $_REQUEST['ad_id']
# If no ad exists for this block, create it. 

if (isset($_REQUEST['block_id']) && !empty($_REQUEST['block_id'])) {

    global $ad_tag_to_field_id;

	$sql = "SELECT user_id, ad_id, order_id FROM blocks where banner_id='$BID' AND block_id='".intval($_REQUEST['block_id'])."'";
	$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));
	$blk_row = mysqli_fetch_array($result);

	if (!isset($blk_row['ad_id']) || empty($blk_row['ad_id'])) { // no ad exists, create a new ad_id
		$_REQUEST[$ad_tag_to_field_id['URL']['field_id']]='';
		$_REQUEST[$ad_tag_to_field_id['ALT_TEXT']['field_id']] = 'ad text';
		$_REQUEST['order_id'] = $blk_row['order_id'];
		$_REQUEST['BID'] = $BID;
		$_REQUEST['user_id'] = $_SESSION['MDS_ID'];
		$_REQUEST['ad_id'] = "";
		$ad_id = insert_ad_data();

		$sql = "UPDATE orders SET ad_id='".intval($ad_id)."' WHERE order_id='".intval($blk_row['order_id'])."' ";
		$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));
		$sql = "UPDATE blocks SET ad_id='".intval($ad_id)."' WHERE order_id='".intval($blk_row['order_id'])."' ";
		$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));

		$_REQUEST['ad_id'] = $ad_id;

	} else { // initialize $_REQUEST['ad_id']

	// make sure the ad exists..

		$sql = "select * from ads where ad_id='".intval($blk_row['ad_id'])."' ";
		$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));
		//echo $sql;
		if (mysqli_num_rows($result)==0) {
			echo "No ad exists..";
			$_REQUEST[$ad_tag_to_field_id['URL']['field_id']]='';
			$_REQUEST[$ad_tag_to_field_id['ALT_TEXT']['field_id']] = 'ad text';
			$_REQUEST['order_id'] = $blk_row['order_id'];
			$_REQUEST['BID'] = $BID;
			$_REQUEST['user_id'] = $_SESSION['MDS_ID'];
			$ad_id = insert_ad_data();

			$sql = "UPDATE orders SET ad_id='".intval($ad_id)."' WHERE order_id='".intval($blk_row['order_id'])."' ";
			$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));
			$sql = "UPDATE blocks SET ad_id='".intval($ad_id)."' WHERE order_id='".intval($blk_row['order_id'])."' ";
			$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));

			$_REQUEST['ad_id'] = $ad_id;
		} else {
		
			$_REQUEST['ad_id'] = $blk_row['ad_id'];

		}
		// bug in previous versions resulted in saving the ad's user_id with a session_id
		// fix user_id here
		$sql = "UPDATE ads SET user_id='".intval($blk_row['user_id'])."' WHERE order_id='".intval($blk_row['order_id'])."' AND user_id <> '".intval($_SESSION['MDS_ID'])."' limit 1 ";
		mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));

		


	}
	

}
//////////////

function disapprove_modified_order($order_id, $BID) {

	$sql = "UPDATE orders SET approved='N' WHERE order_id='".intval($order_id)."' AND banner_id='".intval($BID)."' ";
	//echo $sql;
	mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));
	$sql = "UPDATE blocks SET approved='N' WHERE order_id='".intval($order_id)."' AND banner_id='".intval($BID)."' ";
	///echo $sql;
	mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));

}

/////////////////////////
# Display ad editing forms if the ad was clicked, or 'Edit' button was pressed.

if ( isset( $_REQUEST['ad_id'] ) && ! empty( $_REQUEST['ad_id'] ) ) {
	$imagine = new Imagine\Gd\Imagine();

	$sql = "SELECT * from ads as t1, orders as t2 where t1.ad_id=t2.ad_id AND t1.user_id=" . intval($_SESSION['MDS_ID']) . " and t1.banner_id='".intval($BID)."' and t1.ad_id='" . intval($_REQUEST['ad_id']) . "' AND t1.order_id=t2.order_id ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	$row      = mysqli_fetch_array( $result );
	$order_id = $row['order_id'];
	$blocks   = explode( ',', $row['blocks'] );

	$size   = get_pixel_image_size( $row['order_id'] );
	$pixels = $size['x'] * $size['y'];

	$sql = "SELECT * from blocks WHERE order_id='" . intval($order_id) . "'";
	$blocks_result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

	if ( isset( $_REQUEST['change_pixels'] ) && ! empty( $_REQUEST['change_pixels'] ) ) {

		// a new image was uploaded...

		// move the file

		$uploaddir = TEMP_PATH;

		$parts = explode( '.', $_FILES['pixels']['name'] );
		$ext   = strtolower( array_pop( $parts ) );

		// CHECK THE EXTENSION TO MAKE SURE IT IS ALLOWED
		$ALLOWED_EXT = 'jpg, jpeg, gif, png';
		$ext_list    = preg_split( "/[\s,]+/i", ( $ALLOWED_EXT ) );
		if ( ! in_array( $ext, $ext_list ) ) {

			$error              .= "<b>" . $label['advertiser_file_type_not_supp'] . "</b><br>";
			$image_changed_flag = false;

		} else {

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
							$data = base64_encode( $dest->get( "png", array( 'png_compression_level' => 9 ) ) );

							// some variables
							$map_x     = $x + $low_x;
							$map_y     = $y + $low_y;
							$GRD_WIDTH = $banner_data['BLK_WIDTH'] * $banner_data['G_WIDTH'];
							$cb        = ( ( $map_x ) / $banner_data['BLK_WIDTH'] ) + ( ( $map_y / $banner_data['BLK_HEIGHT'] ) * ( $GRD_WIDTH / $banner_data['BLK_WIDTH'] ) );

							// save to db
							$sql = "UPDATE blocks SET image_data='$data' where block_id='" . intval($cb) . "' AND banner_id='" . intval($BID) . "' ";
							mysqli_query( $GLOBALS['connection'], $sql );
						}
					}
				}

				unlink( $tmp_image_file );

				if ( $banner_data['AUTO_APPROVE'] != 'Y' ) { // to be approved by the admin
					disapprove_modified_order( $order_id, $BID );
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
<h2><?php echo $label['adv_pub_editad_head']; ?></h2>
<p><?php echo $label['adv_pub_editad_desc']; ?> </p>
<p><b><?php echo $label['adv_pub_yourpix'] ; ?></b></p>
    <table class="table">
        <thead>
            <tr>
                <th scope="col"><?php echo $label['adv_pub_piximg']; ?></th>
                <th scope="col"><?php echo $label['adv_pub_pixinfo']; ?></th>
                <th scope="col"><?php echo $label['adv_pub_pixchng']; ?></th>
<!--                <th scope="col">--><?php //echo $label['list_pixels']; ?><!--</th>-->
            </tr>
        </thead>
        <tbody>
<td valign="top">
    <div class="text-center">
<?php
if (isset($_REQUEST['ad_id']) && !empty($_REQUEST['ad_id'])) {
		//echo "ad is".$_REQUEST['ad_id'];
		?><img src="get_order_image.php?BID=<?php echo $BID; ?>&aid=<?php echo $_REQUEST['ad_id']; ?>" border=1><?php
	} else {
		?><img src="get_order_image.php?BID=<?php echo $BID; ?>&block_id=<?php echo $_REQUEST['block_id']; ?>" border=1><?php
	} ?>
    </div>
</td>
<td valign="top"><?php

		$label['adv_pub_pixcount'] = str_replace('%SIZE_X%',$size['x'],$label['adv_pub_pixcount']);
		$label['adv_pub_pixcount'] = str_replace('%SIZE_Y%', $size['y'],$label['adv_pub_pixcount']);
		$label['adv_pub_pixcount'] = str_replace('%PIXEL_COUNT%', $pixels,$label['adv_pub_pixcount']);
		echo $label['adv_pub_pixcount'];
		?><br></td>
<td valign="top"><?php
			$label['adv_pub_pixtochng'] = str_replace('%SIZE_X%',$size['x'],$label['adv_pub_pixtochng']);
			$label['adv_pub_pixtochng'] = str_replace('%SIZE_Y%',$size['y'],$label['adv_pub_pixtochng']);
			echo $label['adv_pub_pixtochng'];
			?><form name="change" enctype="multipart/form-data" method="post">
<input class="form-control-file mb-0 mt-2" type="file" name='pixels'>
<input type="hidden" name="ad_id" value="<?php echo $_REQUEST['ad_id']; ?>">
<input class="btn btn-primary btn-sm mt-2" type="submit" name="change_pixels" value="<?php echo $label['adv_pub_pixupload']; ?>"></form><?php if ($error) { echo "<span class='text-danger'>".$error."</span>"; $error='';} ?>
<span class='text-muted'><small><?php echo $label['advertiser_publish_supp_formats']; ?> <?php echo "$gif_support $jpeg_support $png_support"; ?></small></span>
</td>
        </tbody>
</table>

<p><b><?php echo $label['adv_pub_edityourad']; ?></b></p>
<?php

	if (isset($_REQUEST['save']) && !empty($_REQUEST['save'])) { // saving

		$error = validate_ad_data(1);
		if ($error != '') { // we have an error
			$mode = "user";
			//display_ad_intro();
			display_ad_form (1, $mode, '');
		} else {

			$ad_id = insert_ad_data();
			update_blocks_with_ad($ad_id, $_SESSION['MDS_ID']);
			
			global $prams;
			$prams = load_ad_values ($ad_id);
			//print_r($prams);

			?>
			<div class="text-center"><div class='text-success'><?php echo $label['adv_pub_adsaved']; ?></div></div>
			<p>&nbsp;</p>
			<?php

			$mode = "user";
		
			display_ad_form (1, $mode, $prams);

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

			// send pixel change notification
			if (EMAIL_ADMIN_PUBLISH_NOTIFY=='YES') {
				send_published_pixels_notification($_SESSION['MDS_ID'], $BID);
			}

		}

	} else {
			
			$prams = load_ad_values ($_REQUEST['ad_id']);
			display_ad_form (1, 'user', $prams);

	}

	

} # end of ad forms
?>&nbsp;</p><?php
#########################################

# List Ads

ob_start();
$count = list_ads ($admin=false,$order, $offset, 'USER');
$contents = ob_get_contents();
ob_end_clean();

if ($count > 0) {
?>
	<h3><?php echo $label['adv_pub_yourads']; ?></h3>
    <p>
	<?php
		echo $contents;
	?>
	</p>
	<?php

}
		
//}

?>
	<h3><?php echo $label['advertiser_publish_head']; ?></h3>
	<p>
	<?php echo $label['advertiser_publish_instructions2']; ?>
	<?php
	
	// infrom the user about the approval status of the iamges.

	$sql = "select * from orders where user_id='".intval($_SESSION['MDS_ID'])."' AND status='completed' and  approved='N' and banner_id='".intval($BID)."' ";
	$result4 = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection'])); 

	if (mysqli_num_rows($result4)>0) {	
		?>
		<p><div width='100%' style="border-color:#FF9797; border-style:solid;padding:5px;"><?php echo $label['advertiser_publish_pixwait']; ?></div></p>
		<?php
	} else {

		$sql = "select * from orders where user_id='".intval($_SESSION['MDS_ID'])."' AND status='completed' and  approved='Y' and published='Y' and banner_id='".intval($BID)."' ";
		//echo $sql;
		$result4 = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection'])); 

		if (mysqli_num_rows($result4)>0) {	
			?>
			<p><div width='100%' style="border-color:green;border-style:solid;padding:5px;"><?php echo $label['advertiser_publish_published']; ?></div></p>
			<?php
		} else {

			$sql = "select * from orders where user_id='".intval($_SESSION['MDS_ID'])."' AND status='completed' and  approved='Y' and published='N' and banner_id='".intval($BID)."' ";
			
			$result4 = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection'])); 

			if (mysqli_num_rows($result4)>0) {	
				?>
				<p><div width='100%' style="border-color:yellow;border-style:solid;padding:5px;"><?php echo $label['advertiser_publish_waiting']; ?></div></p>
				<?php
			}

		}

	}

	


	?>

	<?php

	// Generate the Area map form the current sold blocks.

	$sql = "SELECT * FROM blocks WHERE user_id='".intval($_SESSION['MDS_ID'])."' AND status='sold' and banner_id='".intval($BID)."' ";
	$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));

	?>
	</div>
	<center>
	<map name="main" id="main">

	<?php

	while ($row=mysqli_fetch_array($result)) {

		//if (strlen($row['image_data'])>0) {
	?>

	<area shape="RECT" coords="<?php echo $row['x'];?>,<?php echo $row['y'];?>,<?php echo $row['x']+$banner_data['BLK_WIDTH'];?>,<?php echo $row['y']+$banner_data['BLK_HEIGHT'];?>" href="publish.php?BID=<?php echo $BID;?>&block_id=<?php echo ($row['block_id']);?>" title="<?php echo ($row['alt_text']);?>" alt="<?php echo ($row['alt_text']);?>"  />

	<?php
	//	}

	}
	?>

	<img style="border:0; border-bottom:1px solid; border-right:1px solid; border-color:#D4D4D4;" src="show_map.php?BID=<?php echo $BID;?>&time=<?php echo (time()); ?>" width="<?php echo ($banner_data['G_WIDTH']*$banner_data['BLK_WIDTH']); ?>" height="<?php echo ($banner_data['G_HEIGHT']*$banner_data['BLK_HEIGHT']); ?>" border="0" usemap="#main" />
	</center>
</div>
<?php
require ("footer.php");

?>
