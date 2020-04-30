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

// setup filters
require_once '../include/functions2.php';
global $f2;
$f2 = new functions2();

require_once '../vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';
global $purifier;
$purifier = new HTMLPurifier();

if ((isset($_REQUEST['pass']) && $_REQUEST['pass'] != '') && (defined("MAIN_PHP") && MAIN_PHP=='1')) {
	if (stripslashes($_REQUEST['pass']) == ADMIN_PASSWORD) {
		$_SESSION['ADMIN'] = '1';
	}
}
if (!isset($_SESSION['ADMIN']) || empty($_SESSION['ADMIN'])) {
	if (defined("MAIN_PHP") && MAIN_PHP=='1') {
		?>
Please input admin password:<br>
<form method='post'>
<input type="password" name='pass'>
<input type="submit" value="OK">
</form>
	<?php

	} else {
		echo '<script type="text/javascript">parent.document.location.href = "' . BASE_HTTP_PATH . basename(SERVER_PATH_TO_ADMIN) . '";</script>';
    }
	die();
}

?>