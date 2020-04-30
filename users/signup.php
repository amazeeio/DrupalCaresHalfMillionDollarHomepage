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


require "../config.php";
require_once '../include/session.php';
$db_sessions = new DBSessionHandler();
?>

<?php include('login_functions.php'); ?>
<?php
/*
COPYRIGHT 2008 - see www.milliondollarscript.com for a list of authors

This file is part of the Million Dollar Script.

Million Dollar Script is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Million Dollar Script is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with the Million Dollar Script.  If not, see <http://www.gnu.org/licenses/>.

*/
	
echo $f2->get_doc();

require ("header.php");

$label["advertiser_signup_heading1"] = str_replace ("%SITE_NAME%", SITE_NAME , $label["advertiser_signup_heading1"]);

?>
<table width="60%" align="center" width="100%"  border="0" cellspacing="0" cellpadding="0" >
	<tr>
		<td width="35" height="26">&nbsp;</td>
		<td height="26" valign="bottom"><div align="center"><h3 ><?php echo $label["advertiser_signup_heading2"]; ?></h3> </div></td>
		<td width="35" height="26">&nbsp;</td>
	</tr>
	<tr>
		<td width="35">&nbsp;</td>
		<td>
			<?php
				if ($_REQUEST['form']=="filled") {

					$success = process_signup_form();
					
				} // end submit

				if (!$success) {
					//Signup form is shown below

					display_signup_form($_REQUEST['FirstName'], $_REQUEST['LastName'], $_REQUEST['CompName'], $_REQUEST['Username'], $_REQUEST['Password'], $_REQUEST['Password2'], $_REQUEST['Email'], $_REQUEST['Newsletter'], $_REQUEST['Notification1'], $_REQUEST['Notification2'], $_REQUEST['lang']);
					
				} else {


				}

				
			?>

		</td>
		<td width="35">&nbsp;</td>
	</tr>
	<tr>
		<td width="35" height="26">&nbsp;</td>
		<td height="26"></td>
		<td width="35" height="26">&nbsp;</td>
	</tr>
</table>

<?php

require ("footer.php");
?>
