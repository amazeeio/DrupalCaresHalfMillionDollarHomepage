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

header( "Cache-Control: no-cache, must-revalidate" ); // HTTP/1.1
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); // Date in the past

require_once( "../config.php" );
require_once '../include/session.php';
$db_sessions = new DBSessionHandler();
$block_id      = intval( $_REQUEST['block_id'] );
$BID           = $f2->bid( $_REQUEST['BID'] );
$output_result = "";

if ( $_SESSION['MDS_ID'] == '' ) {
	echo "error";
	die();
}

$banner_data = load_banner_constants( $BID );

if ( ! is_numeric( $BID ) ) {
	die();
}

if ( $_REQUEST['user_id'] != '' ) {
	$user_id = intval( $_REQUEST['user_id'] );
	if ( ! is_numeric( $_REQUEST['user_id'] ) ) {
		die();
	}

} else {
	$user_id = intval( $_SESSION['MDS_ID'] );
}

if ( ! can_user_order( $banner_data, $_SESSION['MDS_ID'] ) ) {
	$max_orders = true;
	echo 'max_orders';
	die();
}

// check the max pixels
if ( $banner_data['G_MAX_BLOCKS'] > 0 ) {
	$sql = "SELECT * from blocks where user_id='$user_id' and status='reserved' and banner_id='$BID' ";
	$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );

	$count = mysqli_num_rows( $result );

	if ( ( $count ) >= $banner_data['G_MAX_BLOCKS'] ) {
		echo 'max_selected';
		die();
	}
}

$output_result = select_block( '', '', $block_id );

echo $output_result;
