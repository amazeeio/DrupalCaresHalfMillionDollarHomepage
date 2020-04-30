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

$BID = (isset($_REQUEST['BID']) && $f2->bid($_REQUEST['BID'])!='') ? $f2->bid($_REQUEST['BID']) : $BID = 1;
$sql = "SELECT grid_width,grid_height, block_width, block_height, bgcolor, time_stamp FROM banners WHERE (banner_id = '$BID')";
$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);
$b_row = mysqli_fetch_array($result);

if (!$b_row['block_width']) { $b_row['block_width'] = 10;}
if (!$b_row['block_height']) { $b_row['block_height'] = 10;}

$sql = "select block_id from blocks where user_id='".intval($_SESSION['MDS_ID'])."' and status='sold' ";
$result = mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));
$pixels = mysqli_num_rows($result) * ($b_row['block_width'] * $b_row['block_height']);

$sql = "select block_id from blocks where user_id='".intval($_SESSION['MDS_ID'])."' and status='ordered' ";
$result = mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));
$ordered = mysqli_num_rows($result) * ($b_row['block_width'] * $b_row['block_height']);

$sql = "select * from users where ID='".intval($_SESSION['MDS_ID'])."' ";
$result = mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));
$user_row = mysqli_fetch_array($result);

?>
<div class="container">
<h3><?php echo $label['advertiser_home_welcome'];?></h3>
<p>
<?php echo $label['advertiser_home_line2']."<br>"; ?>
<p>
<p>
<?php
$label['advertiser_home_blkyouown'] = str_replace("%PIXEL_COUNT%", $pixels, $label['advertiser_home_blkyouown']);
echo $label['advertiser_home_blkyouown']."<br>";

$label['advertiser_home_blkonorder'] = str_replace("%PIXEL_ORD_COUNT%", $ordered, $label['advertiser_home_blkonorder']);


if (USE_AJAX=='SIMPLE') {
	$label['advertiser_home_blkonorder'] = str_replace('select.php', 'order_pixels.php', $label['advertiser_home_blkonorder']);
} 
echo $label['advertiser_home_blkonorder']."<br>";

$label['advertiser_home_click_count'] = str_replace("%CLICK_COUNT%", number_format($user_row['click_count']), $label['advertiser_home_click_count']);
echo $label['advertiser_home_click_count']."<br>";
?>
</p>

<h3><?php echo $label['advertiser_home_sub_head']; ?></h3>
<p>
<?php 

if (USE_AJAX=='SIMPLE') {
	$label['advertiser_home_selectlink'] = str_replace('select.php', 'order_pixels.php', $label['advertiser_home_selectlink']);
} 

echo $label['advertiser_home_selectlink']; ?><br>
<?php echo $label['advertiser_home_managelink']; ?><br>
<?php echo $label['advertiser_home_ordlink']; ?><br>
<?php echo $label['advertiser_home_editlink']; ?><br>
</p>
<p>
<?php echo $label['advertiser_home_quest']; ?> <?php echo SITE_CONTACT_EMAIL; ?>
</p>
</div>
<?php
require ("footer.php");
?>
