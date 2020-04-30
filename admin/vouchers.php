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

require("../config.php");
require ('admin_common.php');

?>
<?php echo $f2->get_doc(); ?>

<style>
body {

	font-family: 'Arial', sans-serif; 
	font-size:10pt;

}
</style>

<script language="JavaScript" type="text/javascript">

function confirmLink(theLink, theConfirmMsg)
   {

       if (theConfirmMsg == '' || typeof(window.opera) != 'undefined') {
           return true;
       }

       var is_confirmed = confirm(theConfirmMsg + '\n');
       if (is_confirmed) {
           theLink.href += '&is_js_confirmed=1';
       }

       return is_confirmed;
   } // end of the 'confirmLink()' function

</script>

</head>

<body>


<?php


function validate_input () {
    $error = '';

	if ($_REQUEST['price_discount']=='' && $_REQUEST['blocks_discount']=='') {

		$error .= "- You muster enter a price OR blocks discount <br>";

	}

	if($_REQUEST['price_discount'] and !is_numeric($_REQUEST['price_discount'])) {
		$error .= "- Price discount must be a number <br>";
	}

	if($_REQUEST['blocks_discount'] and !is_numeric($_REQUEST['blocks_discount'])) {
		$error .= "- Blocks discount must be a number <br>";
	}

	return $error;



}

if ($_REQUEST['action']=='activate') {

	$sql = "UPDATE vouchers set active=1 where voucher_id='".mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['voucher'])."' ";
	mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));

}

if ($_REQUEST['action']=='deactivate') {

	$sql = "UPDATE vouchers set active=0 where voucher_id='".mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['voucher'])."' ";
	mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));

}

if ($_REQUEST['action']=='delete') {

	$sql = "DELETE FROM vouchers WHERE voucher_id='".mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['voucher'])."' ";
	mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));

}


if ($_REQUEST['submit']!='') {

	$error = validate_input();

	if ($error == '') {

		$voucher_id = mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['voucher']);
		$code = mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['code']);
		$price_discount = $_REQUEST['price_discount'] == '' ? 'NULL' : '"' . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['price_discount']) . '"';
		$blocks_discount = $_REQUEST['blocks_discount'] == '' ? 'NULL' : '"' . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['blocks_discount']) . '"';
		$name = mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['name']);
		$do_username = mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['do_username']);
		$banner_id = mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['banner_id']);
		$notes = mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['notes']);
		$single_use = $_REQUEST['single_use'] == 1 ? 1 : 0;
		$active = $_REQUEST['active'] == 1 ? 1 : 0;

		if ($_REQUEST['action']=='edit') {

			$sql = <<<SQL
			UPDATE vouchers
			SET code = "$code",
			price_discount = $price_discount,
			blocks_discount = $blocks_discount,
			name = "$name",
			do_username = "$do_username",
			banner_id = $banner_id,
			notes = "$notes",
			single_use = $single_use,
			active = $active
			WHERE voucher_id=$voucher_id
SQL;

		} else {
		
			$sql = <<<SQL
			INSERT INTO vouchers (
				code,
				price_discount,
				blocks_discount,
				name,
				do_username,
				banner_id,
				notes,
				single_use,
				active
			)
			VALUES (
				"$code",
				$price_discount,
				$blocks_discount,
				"$name",
				"$do_username",
				$banner_id,
				"$notes",
				$single_use,
				$active
			)
SQL;
		}
		

		mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);

		$_REQUEST['new']='';

	}

}



if (($_REQUEST['new']!='') || ($_REQUEST['action']=='edit')) {

	if ($_REQUEST['new']=='1') {
		echo "<h4>New Voucher:</h4>";
	}
	if ($_REQUEST['action']=='edit') {
		echo "<h4>Edit Voucher:</h4>";

		$sql = "SELECT * FROM vouchers WHERE `voucher_id`='".mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['voucher'])."' ";
		$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));
		$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
		
		$_REQUEST['code'] = $row['code'];
		$_REQUEST['price_discount'] = $row['price_discount'];
		$_REQUEST['blocks_discount'] = $row['blocks_discount'];
		$_REQUEST['name'] = $row['name'];
		$_REQUEST['do_username'] = $row['do_username'];
		$_REQUEST['banner_id'] = $row['banner_id'];
		$_REQUEST['notes'] = $row['notes'];
		$_REQUEST['single_use'] = $row['single_use'];
		$_REQUEST['active'] = $row['active'];

		$single_use_checked = $_REQUEST['single_use'] == 1 ? ' checked' : '';
		$active_checked = $_REQUEST['active'] == 1 ? ' checked' : '';
	}

?>
<form enctype="multipart/form-data" method="post">
<input type="hidden" value="<?php echo $_REQUEST['action']?>" name="action" >
<input type="hidden" value="<?php echo $_REQUEST['voucher']?>" name="voucher" >
<table border="0" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9">
<tr bgcolor="#ffffff" >
	<td><font size="2">Code *:</font></td>
	<td><input size="30" type="text" name="code" required value="<?php echo $_REQUEST['code']; ?>"></td>
</tr>
<tr bgcolor="#ffffff" >
	<td><font size="2">$ Discount:</font></td>
	<td><input size="4" type="text" name="price_discount" value="<?php echo $_REQUEST['price_discount']; ?>"></td>
</tr>
<tr bgcolor="#ffffff" >
	<td><font size="2">Block Discount:</font></td>
	<td><input size="4" type="text" name="blocks_discount" value="<?php echo $_REQUEST['blocks_discount']; ?>"></td>
</tr>
<tr bgcolor="#ffffff" >
	<td><font size="2">Name:</font></td>
	<td><input size="30" type="text" name="name" value="<?php echo $_REQUEST['name']; ?>"></td>
</tr>
<tr bgcolor="#ffffff" >
	<td><font size="2">D.O Username:</font></td>
	<td><input size="30" type="text" name="do_username" value="<?php echo $_REQUEST['do_username']; ?>"></td>
</tr>
<tr bgcolor="#ffffff" >
	<td><font size="2">Banner *:</font></td>
	<td>
		<select name="banner_id" required>
		<?php
			$sql = "SELECT banner_id, name FROM banners";
			$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));
			$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

			foreach ($rows as $banner) {
				$selected = $_REQUEST['banner_id'] == $banner['banner_id'] ? ' selected' : '';
				echo '<option value="' . $banner['banner_id'] . '" ' . $selected . '>' . $banner['name'] . '</option>';
			}
		?>
		</select>
	</td>
</tr>
<tr bgcolor="#ffffff" >
	<td><font size="2">Notes:</font></td>
	<td><textarea name="notes"><?php echo $_REQUEST['notes']; ?></textarea></td>
</tr>
<tr bgcolor="#ffffff" >
	<td><font size="2">Single Use:</font></td>
	<td><input type="checkbox" name="single_use" value="1" <?php echo $single_use_checked; ?>></td>
</tr>
<tr bgcolor="#ffffff" >
	<td><font size="2">Active:</font></td>
	<td><input type="checkbox" name="active" value="1" <?php echo $active_checked; ?>></td>
</tr>
</table>
<input type="submit" name="submit" value="Submit">
</form>
<?php

	if ($error !='') {
		echo "<b><font color='red'>ERROR:</font></b> Cannot save voucher into database.<br>";
		echo $error;
	}

}

?>
<hr />

<input type="button" value="New Voucher..." onclick="window.location='vouchers.php?new=1'">
<table border="0" cellSpacing="1" cellPadding="3" bgColor="#d9d9d9" >
			<tr bgColor="#eaeaea">
				<td><b><font size="2">ID</b></font></td>
				<td><b><font size="2">Code</b></font></td>
				<td><b><font size="2">$ Discount</b></font></td>
				<td><b><font size="2">Block Discount</b></font></td>
				<td><b><font size="2">Name</b></font></td>
				<td><b><font size="2">D.O Username</b></font></td>
				<td><b><font size="2">Banner</b></font></td>
				<td><b><font size="2">Notes</b></font></td>
				<td><b><font size="2">Single Use</b></font></td>
				<td><b><font size="2">Active</b></font></td>
				<td><b><font size="2">Tools</b></font></td>
			</tr>
<?php
			$result = mysqli_query($GLOBALS['connection'], "select v.*, b.name as banner_name FROM vouchers v LEFT JOIN banners b on v.banner_id = b.banner_id order by voucher_id desc") or die (mysqli_error($GLOBALS['connection']));
			while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {

				?>

				<tr bgcolor="#ffffff">

				<td><font size="2"><?php echo $row['voucher_id']; ?></font></td>
				<td><font size="2"><?php echo $row['code']; ?></font></td>
				<td><font size="2"><?php echo $row['price_discount']; ?></font></td>
				<td><font size="2"><?php echo $row['blocks_discount']; ?></font></td>
				<td><font size="2"><?php echo $row['name']; ?></font></td>
				<td><font size="2"><?php echo $row['do_username']; ?></font></td>
				<td><font size="2"><a href="inventory.php?action=edit&BID=<?php echo $row['banner_id']; ?>"><?php echo $row['banner_name']; ?></a></font></td>
				<td><font size="2"><?php echo $row['notes']; ?></font></td>
				<td><font size="2"><?php echo $row['single_use'] ? 'Y' : 'N'; ?></font></td>
				<td><font size="2">
					<?php if ($row['active']) { ?><IMG SRC="active.gif" WIDTH="16" HEIGHT="16" BORDER="0" ALT=""><?php } else { ?><IMG SRC="notactive.gif" WIDTH="16" HEIGHT="16" BORDER="0" ALT=""><?php } ;?></font>
					<?php if (!$row['active']) {?>
					[<a href="<?php echo $_SERVER['PHP_SELF'];?>?action=activate&voucher=<?php echo $row['voucher_id'];?>">Activate</a>]
					<?php } if ($row['active']) {?>
					[<a href="<?php echo $_SERVER['PHP_SELF'];?>?action=deactivate&voucher=<?php echo $row['voucher_id'];?>">Deactivate</a>]
					<?php }?>
				</td>

				<td>
					<font size="2">
					[<a href="<?php echo $_SERVER['PHP_SELF'];?>?action=edit&voucher=<?php echo $row['voucher_id'];?>">Edit</a>]
					[<a onclick=" return confirmLink(this, 'Delete, are you sure?') " href="<?php echo $_SERVER['PHP_SELF'];?>?action=delete&voucher=<?php echo $row['voucher_id'];?>">Delete</a>]
					</font>
				</td>
				

				</tr>


				<?php

			}
?>
</table>


</body>

</html>
