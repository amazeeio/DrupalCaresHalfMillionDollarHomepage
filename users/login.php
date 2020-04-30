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

require_once( 'login_functions.php' );

if ( ! is_logged_in() ) {
	if ( isset( $_COOKIE['PHPSESSID'] ) ) {
		unset( $_COOKIE['PHPSESSID'] );
		setcookie( 'PHPSESSID', null, - 1 );
	}
}

session_save_path('/app/files/sessions/');
session_start();
require __DIR__ . "/../config.php";

$target_page = $_REQUEST['target_page'];

if ($target_page=='') {
    $target_page='select.php';
} else if($target_page != "index.php" && $target_page != "confirm_order.php") {
    $target_page = "index.php";
}

?>
<?php require ("header.php"); ?>
			<?php
				if (do_login()) {
					$ok = str_replace ( "%username%", $_SESSION['MDS_Username'], $label['advertiser_login_success2']);
					$ok = str_replace ( "%firstname%", $_SESSION['MDS_FirstName'], $ok);
					$ok = str_replace ( "%lastname%", $_SESSION['MDS_LastName'], $ok);
					$ok = str_replace ( "%target_page%", $target_page, $ok);
					echo "<div align='center' >".$ok."</div>";

				} else {
					//echo "<div align='center' >".$label["advertiser_login_error"]."</div>";
				}
			?>
<div class="text-center">
    <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Redirecting...</span>
    </div>
</div>
<script type="text/JavaScript">
    setTimeout("location.href = '<?php echo $target_page; ?>';",5000);
</script>
<?php require("footer.php"); ?>
