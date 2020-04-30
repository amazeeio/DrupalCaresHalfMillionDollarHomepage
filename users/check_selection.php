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

define( 'NO_HOUSE_KEEP', 'YES' );
// check the image selection.
require( "../config.php" );

require_once '../include/session.php';
$db_sessions = new DBSessionHandler();

header( "Cache-Control: no-cache, must-revalidate" ); // HTTP/1.1
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); // Date in the past

global $f2;

$BID = ( isset( $_REQUEST['BID'] ) && $f2->bid( $_REQUEST['BID'] ) != '' ) ? $f2->bid( $_REQUEST['BID'] ) : 1;
$banner_data = load_banner_constants( $BID );

// normalize...

$_REQUEST['map_x']    = floor( $_REQUEST['map_x'] / $banner_data['BLK_WIDTH'] ) * $banner_data['BLK_WIDTH'];
$_REQUEST['map_y']    = floor( $_REQUEST['map_y'] / $banner_data['BLK_HEIGHT'] ) * $banner_data['BLK_HEIGHT'];
$_REQUEST['block_id'] = floor( $_REQUEST['block_id'] );

/**
 * Check available pixels
 *
 * @param $in_str
 *
 * @return bool
 */
function check_pixels( $in_str ) {

	global $f2, $label;

	// cannot reserve pixels if there is no session
	if ( session_id() == '' ) {
		return false;
	}

	if ( isset( $_REQUEST['BID'] ) && $f2->bid( $_REQUEST['BID'] ) != '' ) {
		$BID = $f2->bid( $_REQUEST['BID'] );
	} else {
		$BID = 1;

	}

	// check if it is free
	$available = true;

	$sql = "SELECT block_id FROM blocks WHERE banner_id='" . intval($BID) . "' AND block_id IN(".mysqli_real_escape_string( $GLOBALS['connection'], $in_str) . ")";

	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( $sql . mysqli_error( $GLOBALS['connection'] ) );
	if ( mysqli_num_rows( $result ) > 0 ) {
		echo js_out_prep( $label['check_sel_notavailable'] . " (E432)" );
		$available = false;
	}

	if ( $available ) {

		// from temp_orders table
		$sql = "SELECT blocks FROM temp_orders WHERE banner_id='" . intval( $BID ) . "'";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );

		$selected = explode( ",", $in_str );
		while ( $row = mysqli_fetch_array( $result ) ) {
			$entries  = explode( ",", $row['blocks'] );
			if ( ! empty( array_intersect( $entries, $selected ) ) ) {
				echo js_out_prep( $label['check_sel_notavailable'] . " (E432)" );
				$available = false;
				break;
			}
		}
	}

	return $available;
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

	$cb_array = array();
	for ( $y = 0; $y < ( $size->getHeight() ); $y += $banner_data['BLK_HEIGHT'] ) {
		for ( $x = 0; $x < ( $size->getWidth() ); $x += $banner_data['BLK_WIDTH'] ) {

			$map_x = $x + intval($_REQUEST['map_x']);
			$map_y = $y + intval($_REQUEST['map_y']);

			$GRD_WIDTH  = $banner_data['BLK_WIDTH'] * $banner_data['G_WIDTH'];
			$cb         = ( ( $map_x ) / $banner_data['BLK_WIDTH'] ) + ( ( $map_y / $banner_data['BLK_HEIGHT'] ) * ( $GRD_WIDTH / $banner_data['BLK_WIDTH'] ) );
			$cb_array[] = $cb;
		}
	}

	$in_str = implode( ',', $cb_array );
	$f2->write_log( "in_str is:" . $in_str );
	check_pixels( $in_str );
}
