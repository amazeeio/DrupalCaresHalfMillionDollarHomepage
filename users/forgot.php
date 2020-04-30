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

require "../config.php";
require_once '../include/session.php';
$db_sessions = new DBSessionHandler();
include( 'login_functions.php' );
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

$submit = $_REQUEST['submit'];
$email  = $_REQUEST['email'];
?>
<?php echo $f2->get_doc();

require( "header.php" );

?>
    <div class="container" style="width: 320px">
        <h3><?php echo $label["advertiser_forgot_title"]; ?></h3>
        <form method="post">
            <div class="form-group">
                <label for="email"><?php echo $label["advertiser_forgot_enter_email"] ?></label>
                <input type="text" name="email" class="form-control" id="email" aria-describedby="emailHelp" placeholder="Enter email">
            </div>
            <input class="btn btn-success btn-block" type="submit" name="submit" value="<?php echo $label["advertiser_forgot_submit"]; ?>">
        </form>
<?php

function make_password() {
	$pass = "";
	while ( strlen( $pass ) < 20 ) {
		$pass .= chr( rand( 97, 122 ) );
	}
	return $pass;
}

if ( $email != '' ) {
    echo "<div class='alert alert-info mt-4'>";

	$sql    = "select * from users where `Email`='" . mysqli_real_escape_string( $GLOBALS['connection'], $email ) . "'";
	$result = mysqli_query( $GLOBALS['connection'], $sql );
	$row    = mysqli_fetch_array( $result );

	if ( $row['Email'] != '' ) {

		if ( $row['Validated'] == '0' ) {
			$label["advertiser_forgot_error1"] = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $label["advertiser_forgot_error1"] );
			echo "<div style='text-align:center;'>" . $label["advertiser_forgot_error1"] . "</div>";

		} else {
			$password    = make_password();
			$passwordHashed = getPasswordHash($password);
			$sql     = "update `users` SET `Password`='$passwordHashed' where `ID`='" . mysqli_real_escape_string( $GLOBALS['connection'], $row['ID'] ) . "'";
			mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );

			$to        = trim( $row['Email'] );
			$from      = trim( SITE_CONTACT_EMAIL );
			$form_name = trim( SITE_NAME );

			$subject = $label['advertiser_forgot_subject'];
			$subject = str_replace( "%SITE_NAME%", SITE_NAME, $subject );
			//$subject = str_replace( "%MEMBERID%", trim( $row['Username'] ), $subject );

        $message = $label["forget_pass_email_template"];
        $message = str_replace( "%FNAME%", $row['FirstName'], $message );
			$message = str_replace( "%LNAME%", $row['LastName'], $message );
			$message = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $message );
			$message = str_replace( "%SITE_NAME%", SITE_NAME, $message );
			$message = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $message );
			$message = str_replace( "%MEMBERID%", $row['Username'], $message );
			$message = str_replace( "%PASSWORD%", $password, $message );

        $html_msg = $label["forget_pass_email_template_html"];
        $html_msg = str_replace( "%FNAME%", $row['FirstName'], $html_msg );
        $html_msg = str_replace( "%LNAME%", $row['LastName'], $html_msg );
        $html_msg = str_replace( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $html_msg );
        $html_msg = str_replace( "%SITE_NAME%", SITE_NAME, $html_msg );
        $html_msg = str_replace( "%SITE_URL%", BASE_HTTP_PATH, $html_msg );
        $html_msg = str_replace( "%MEMBERID%", $row['Username'], $html_msg );
        $html_msg = str_replace( "%PASSWORD%", $password, $html_msg );

			if ( USE_SMTP == 'YES' ) {
				$mail_id = queue_mail( $to, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_msg, 6 );
				process_mail_queue( 2, $mail_id );
			} else {
				send_email( $to, $row['FirstName'] . " " . $row['LastName'], SITE_CONTACT_EMAIL, SITE_NAME, $subject, $message, $html_msg, 6 );
			}

			$str = str_replace( "%BASE_HTTP_PATH%", BASE_HTTP_PATH, $label["advertiser_forgot_success1"] );

			echo "<p style='text-align:center;'>" . $str . "</p>";
		}

	} else {
		echo "<div style='text-align:center;'>" . $label["advertiser_forgot_email_notfound"] . "</div>";
	}
    echo "</div>";
}

?>
    </div>

<?php

require( "footer.php" );
