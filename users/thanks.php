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

session_save_path('/app/files/sessions/');
session_start();
include ("../config.php");

include ("login_functions.php");
include ("../payment/payment_manager.php");
//process_login();

require ("header.php");
?>
<div class="container text-center">
    <?php show_nav_status (5); ?>

    <h3>
        <?php
        if(isset($_REQUEST['nhezk5']) && !empty($_REQUEST['nhezk5'])) {
            echo $label['payment_return_thanks_manual'];
        } else {
	        echo $label['payment_return_thanks'];
        }
        ?>
</h3>

<?php
$className = $_REQUEST['m'];
process_payment_return($className);
?>
</div>

<?php require "footer.php"; ?>