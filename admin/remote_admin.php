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

session_save_path('/app/files/sessions/');
session_start([
	'name' => 'MDSADMIN_PHPSESSID',
]);
require("../config.php");

if ($_REQUEST['key']!='') {

	$mykey = substr(md5(ADMIN_PASSWORD),1,15);

	if ($mykey == $_REQUEST['key']) {
		$_SESSION['ADMIN']='1'; // automatically log in
		$admin = true;
	}

}

if (!$admin) {
	require ('admin_common.php');
}



if ($f2->bid($_REQUEST['BID'])!='') {
	$BID = $f2->bid($_REQUEST['BID']);
} else {
	$BID = 1;

}

$banner_data = load_banner_constants($BID);

$sql = "select * from banners where banner_id=".intval($BID);
$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);
$b_row = mysqli_fetch_array($result);
$sql = "select * from users where ID=".intval($_REQUEST['user_id']);
$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);
$u_row = mysqli_fetch_array($result);

if ($_REQUEST['approve_links']!='') {

	//echo "Saving links...";
	if (sizeof($_REQUEST['urls'])>0) {
		//echo " * * *";
		$i=0;

		foreach ($_REQUEST['urls'] as $url) {
			$sql = "UPDATE blocks SET url='".mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['new_urls'][$i])."', alt_text='".mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['new_alts'][$i])."' WHERE user_id='".intval($_REQUEST['user_id'])."' and url='".mysqli_real_escape_string( $GLOBALS['connection'], $url)."' and banner_id='".$f2->bid($_REQUEST['BID'])."'  ";
			//echo $sql."<br>";
			mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);
			$i++;
		}



	}
	// approve pixels
	$sql = "UPDATE blocks set approved='Y' WHERE user_id=".intval($_REQUEST['user_id'])." AND banner_id=".intval($BID);
	mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);

	$sql = "UPDATE orders set approved='Y' WHERE user_id=".intval($_REQUEST['user_id'])." AND banner_id=".intval($BID);
	mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);

	// process the image

	echo process_image($BID);
	publish_image($BID);
	process_map($BID);

	echo "<p><b>Links Approved, grid updated!</b></p>";

}

if ($_REQUEST['disapprove_links']!='') {

	$sql = "UPDATE blocks set approved='N' WHERE user_id=".intval($_REQUEST['user_id'])." and banner_id=".intval($BID);
	mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);

	$sql = "UPDATE orders set approved='N' WHERE user_id=".intval($_REQUEST['user_id'])." and banner_id=".intval($BID);
	mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);

	echo process_image($BID);
	publish_image($BID);
	process_map($BID);

	echo "<p><b>Links Disapproved, grid updated!</b></p>";


}

?>
<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
<b>Listing Links for:</b> <?php echo $u_row['LastName']." ".$u_row['FirstName'];?> (<?php echo $u_row['Username'];?>)
<input type="hidden" name="offset" value="<?php echo $_REQUEST['offset']; ?>">
<input type="hidden" name="BID" value="<?php echo $f2->bid($_REQUEST['BID']); ?>">
<input type="hidden" name="user_id" value="<?php echo $_REQUEST['user_id']; ?>">
<table>
<tr>
<td><b>URL</b></td>
<td><b>Alt Text</b></td>
</tr>

<?php

$sql = "SELECT alt_text, url, count(alt_text) AS COUNT, banner_id FROM blocks WHERE user_id=".intval($_REQUEST['user_id'])."  $bid_sql group by url, alt_text ";

$m_result = mysqli_query($GLOBALS['connection'], $sql);
$i=0;
while ($m_row=mysqli_fetch_array($m_result)) {
	$i++;
	if ($m_row['url'] !='') {
		echo "<tr><td>
		<input type='hidden' name='urls[]' value='".htmlspecialchars($m_row['url'])."'>
		<input type='text' name='new_urls[]' size='40' value=\"".escape_html($m_row['url'])."\"></td>
				<td><input name='new_alts[]' type='text' size='80' value=\"".escape_html($m_row['alt_text'])."\"></td></tr>";
	}
}

?>

</table>

<input type="submit" value="Approve (OK)" name="approve_links"> | <input type="submit" value="Disapprove (No)" name="disapprove_links">
&nbsp; &nbsp;<a href="index.php">Go to Admin</a> | <a href='../users/login.php?Username=<?php echo $u_row['Username'];?>&Password=<?php echo ADMIN_PASSWORD; ?>' target='_blank'>Login to this Advertiser's Account</a>
</form>

<?php
echo "<iframe width=\"".($banner_data['G_WIDTH']*$banner_data['BLK_WIDTH'])."\" height=\"".($banner_data['G_HEIGHT']*$banner_data['BLK_HEIGHT'])."\" frameborder=0 marginwidth=0 marginheight=0 VSPACE=0 HSPACE=0 SCROLLING=no  src=\""."show_map.php?BID=$BID&user_id=".$_REQUEST['user_id']."\"></iframe>";
?>