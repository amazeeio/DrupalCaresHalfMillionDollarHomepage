<?php
/**
 * @version        2.1
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

session_save_path('/app/files/sessions/');
session_start();
define( 'NO_HOUSE_KEEP', 'YES' );
// check the image selection.
require( "../config.php" );

header( "Cache-Control: no-cache, must-revalidate" ); // HTTP/1.1
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); // Date in the past

$BID = ( isset( $_REQUEST['BID'] ) && $f2->bid( $_REQUEST['BID'] ) != '' ) ? $f2->bid( $_REQUEST['BID'] ) : 1;
$banner_data = load_banner_constants( $BID );

// normalize...
//$_REQUEST['map_x']    = floor( $_REQUEST['map_x'] / $banner_data['BLK_WIDTH'] ) * $banner_data['BLK_WIDTH'];
//$_REQUEST['map_y']    = floor( $_REQUEST['map_y'] / $banner_data['BLK_HEIGHT'] ) * $banner_data['BLK_HEIGHT'];
//$_REQUEST['block_id'] = floor( $_REQUEST['block_id'] );

$floorx = floor( intval($_REQUEST['map_x']) / intval($banner_data['BLK_WIDTH']) );
$floory = floor( intval($_REQUEST['map_y']) / intval($banner_data['BLK_HEIGHT']) );
$floorid = floor( intval($_REQUEST['block_id']) );
$floorx = $floorx ? $floorx : 0;
$floory = $floory ? $floory : 0;
$floorid = $floorid ? $floorid : 0;
$_REQUEST['map_x']    = $floorx * $banner_data['BLK_WIDTH'];
$_REQUEST['map_y']    = $floory * $banner_data['BLK_HEIGHT'];
$_REQUEST['block_id'] = $floorid;

# place on temp order -> then
function place_temp_order( $in_str ) {

	global $f2, $BID, $banner_data;

	// cannot place order if there is no session!
	if ( session_id() == '' ) {
		$f2->write_log( 'Cannot place order if there is no session!' );

		return false;
	}
	$blocks = explode( ',', $in_str );

	$quantity = sizeof( $blocks ) * ( $banner_data['BLK_WIDTH'] * $banner_data['BLK_HEIGHT'] );

	$now = ( gmdate( "Y-m-d H:i:s" ) );

	// preserve ad_id & block info...
	$sql = "SELECT ad_id, block_info FROM temp_orders WHERE session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], session_id() ) . "' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
	$row        = mysqli_fetch_array( $result );
	$ad_id      = intval( $row['ad_id'] );
	$block_info = mysqli_real_escape_string( $GLOBALS['connection'], $row['block_info'] );

	// DAYS_EXPIRE comes form load_banner_constants()
	$sql = "REPLACE INTO `temp_orders` ( `session_id` , `blocks` , `order_date` , `price` , `quantity` ,  `days_expire`, `banner_id` , `currency` ,  `date_stamp` , `ad_id`, `block_info` )  VALUES ('" . mysqli_real_escape_string( $GLOBALS['connection'], session_id() ) . "', '" . $in_str . "', '" . $now . "', '0', '" . intval($quantity) . "', '" . intval($banner_data['DAYS_EXPIRE']) . "', '" . $BID . "', '" . mysqli_real_escape_string( $GLOBALS['connection'], get_default_currency()) . "',  '$now', '$ad_id', '$block_info' )";
	$f2->write_log( 'Placed Temp order. ' . $sql );
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) );

}

# reserves the pixels for the temp order..

$price_table = '';

function reserve_temp_order_pixels( $block_info, $in_str ) {

	global $f2, $label, $banner_data;

	// cannot reserve pixels if there is no session
	if ( session_id() == '' ) {
		return false;
	}

	if ( isset( $_REQUEST['BID'] ) && $f2->bid( $_REQUEST['BID'] ) != '' ) {
		$BID = $f2->bid( $_REQUEST['BID'] );
	} else {
		$BID = 1;

	}

	$total = 0;
	foreach ( $block_info as $key => $block ) {

		$price = get_zone_price( $BID, $block['map_y'] / $banner_data['BLK_HEIGHT'], $block['map_x'] / $banner_data['BLK_WIDTH'] );

		$currency = get_default_currency();

		// enhance block info...
		$block_info[ $key ]['currency']  = $currency;
		$block_info[ $key ]['price']     = $price;
		$block_info[ $key ]['banner_id'] = $f2->bid( $_REQUEST['BID'] );

		$total += $price;
	}

	$sql = "UPDATE temp_orders set price='".floatval($total)."', block_info='" . serialize( $block_info ) . "' where session_id='" . mysqli_real_escape_string( $GLOBALS['connection'], session_id()) . "'  ";
	mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

	return true;
}

#######################################################################
## MAIN
#######################################################################
// return true, or false if the image can fit

check_selection_main();

function check_selection_main() {

	global $f2, $banner_data;

	$upload_image_file = get_tmp_img_name();

	$imagine = new Imagine\Gd\Imagine();

	$image = $imagine->open( $upload_image_file );
	$size  = $image->getSize();

	$new_size = get_required_size( $size->getWidth(), $size->getHeight(), $banner_data );

	if ( $size->getWidth() != $new_size[0] || $size->getHeight() != $new_size[1] ) {
		$resize = new Imagine\Image\Box( $new_size[0], $new_size[1] );
		$image->resize( $resize );
	}

	$block_size = new Imagine\Image\Box( $banner_data['BLK_WIDTH'], $banner_data['BLK_HEIGHT'] );
	$palette    = new Imagine\Image\Palette\RGB();
	$color      = $palette->color( '#000', 0 );
	//$zero_point = new Imagine\Image\Point( 0, 0 );

	$block_info = $cb_array = array();
	for ( $y = 0; $y < ( $size->getHeight() ); $y += $banner_data['BLK_HEIGHT'] ) {
		for ( $x = 0; $x < ( $size->getWidth() ); $x += $banner_data['BLK_WIDTH'] ) {

			$map_x = $x + $_REQUEST['map_x'];
			$map_y = $y + $_REQUEST['map_y'];

			$GRD_WIDTH  = $banner_data['BLK_WIDTH'] * $banner_data['G_WIDTH'];
			$cb         = ( ( $map_x ) / $banner_data['BLK_WIDTH'] ) + ( ( $map_y / $banner_data['BLK_HEIGHT'] ) * ( $GRD_WIDTH / $banner_data['BLK_WIDTH'] ) );
			$cb_array[] = $cb;

			$block_info[ $cb ]['map_x'] = $map_x;
			$block_info[ $cb ]['map_y'] = $map_y;

			// create new destination image
			$dest = $imagine->create( $block_size, $color );

			// crop a part from the tiled image
			//$block = $image->copy();
			//$block->crop( new Imagine\Image\Point( $x, $y ), $block_size );

			// paste the block into the destination image
			//$dest->paste( $block, $zero_point );

			// much faster
			imagecopy ( $dest->getGdResource(), $image->getGdResource(), 0, 0, $x, $y, $banner_data['BLK_WIDTH'],  $banner_data['BLK_HEIGHT']);

			// save the image as a base64 encoded string
			$data = base64_encode( $dest->get( "png", array( 'png_compression_level' => 9 ) ) );

			$block_info[ $cb ]['image_data'] = $data;
		}
	}

	$in_str = implode( ',', $cb_array );

	// create a temporary order and place the blocks on a temp order
	place_temp_order( $in_str );
	$f2->write_log( "in_str is:" . $in_str );
	reserve_temp_order_pixels( $block_info, $in_str );
}
