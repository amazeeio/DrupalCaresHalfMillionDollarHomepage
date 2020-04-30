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

function process_login() {

	global $f2, $label;

   $session_duration = ini_get ("session.gc_maxlifetime");
	if ($session_duration=='') {
		$session_duration = 60*20;
	}
   $now = (gmdate("Y-m-d H:i:s"));
   $sql = "UPDATE `users` SET `logout_date`='$now' WHERE UNIX_TIMESTAMP(DATE_SUB('$now', INTERVAL $session_duration SECOND)) > UNIX_TIMESTAMP(last_request_time) AND (`logout_date` ='1000-01-01 00:00:00')";
   mysqli_query($GLOBALS['connection'], $sql) or die ($sql.mysqli_error($GLOBALS['connection']));

   if (!is_logged_in() || ($_SESSION['MDS_Domain'] != "ADVERTISER")) {

	require ("header.php");
?>
       <div class="login-methods container">
   <div class="row">
       <div class="col-md-6 offset-md-3 col-left">
           <h3><?php echo $label["advertiser_section_heading"];?></h3>
		<?php
		  login_form();
        ?>
       </div>
   </div></div>
<?php
require ("footer.php");
die ();
	} else {
      // update last_request_time
	  $now = (gmdate("Y-m-d H:i:s"));
       $sql = "UPDATE `users` SET `last_request_time`='$now', logout_date='1000-01-01 00:00:00' WHERE `Username`='".mysqli_real_escape_string($GLOBALS['connection'], $_SESSION['MDS_Username'])."'";
       mysqli_query($GLOBALS['connection'], $sql) or die($sql.mysqli_error($GLOBALS['connection']));



   }


}

/////////////////////////////////////////////////////////////

function is_logged_in() {
   global $_SESSION;

	if ( ! isset( $_SESSION['MDS_ID'] ) ) {
		$_SESSION['MDS_ID'] = '';

	} else {
		// Check database for user id. If user was deleted it won't exist anymore so we have to log them out.
		$sql = "SELECT * FROM `users` WHERE `ID`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_SESSION['MDS_ID'] ) . "' ";
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) . $sql );
		if ( empty( mysqli_num_rows( $result ) ) ) {
			session_destroy();
			$_SESSION['MDS_ID'] = '';
		}
	}

   return $_SESSION['MDS_ID'];

}

///////////////////////////////////////////////////////////

function login_form( $show_signup_link = true, $target_page = 'index.php' ) {
	global $label;

	?>
    <form class="mt-4" name="form1" method="post" action="login.php?lang=<?php echo get_lang(); ?>&target_page=<?php echo $target_page; ?>">
        <div class="form-group">
            <label for="username"><?php echo $label["advertiser_signup_member_id"]; ?></label>
            <input type="text" class="form-control" id="username" aria-describedby="emailHelp" name="Username" placeholder="Username">
        </div>
        <div class="form-group">
            <label for="password"><?php echo $label["advertiser_signup_password"]; ?></label>
            <input type="password" class="form-control" id="password" name="Password" placeholder="Password">
        </div>
        <button class="form_submit_button btn btn-primary" type="submit" name="Submit"><?php echo $label["advertiser_login"]; ?></button>
    </form>

    <div class="row mt-4">
        <div class="col-md-6">
    <a class="btn btn-light btn-block mb-3" href='forgot.php'><?php echo $label["advertiser_pass_forgotten"]; ?></a>
        </div>
    <?php if ( $show_signup_link ) { ?>
                <div class="col-md-6">
        <a class="btn btn-dark btn-block" href="signup.php"><?php echo $label["advertiser_join_now"]; ?></a>
                </div>
    <?php } ?>
    </div>
	<?php
}

////////////////////////////////////////////////////////////////////

function getPasswordHash($password) {
    return password_hash($password, PASSWORD_BCRYPT, [
        'salt' => PASSWORD_SALT,
        'cost' => 10,
    ]);
}

////////////////////////////////////////////////////////////////////

function create_new_account ($REMOTE_ADDR, $FirstName, $LastName, $CompName, $Username, $pass, $Email, $Newsletter, $Notification1, $Notification2, $lang ) {

	if ($lang=='') {
		$lang = "EN"; // default language is english
	}

    global $label;

    $Password = getPasswordHash($pass);
    $validated = 0;

    if ((EM_NEEDS_ACTIVATION == "AUTO"))  {
      $validated = 1;
    }
	$now = (gmdate("Y-m-d H:i:s"));
    // everything Ok, create account and send out emails.
    $sql = "Insert Into users(IP, SignupDate, FirstName, LastName, CompName, Username, Password, Email, Newsletter, Notification1, Notification2, Validated, Aboutme) values('".mysqli_real_escape_string($GLOBALS['connection'], $REMOTE_ADDR)."', '".mysqli_real_escape_string($GLOBALS['connection'], $now)."', '".mysqli_real_escape_string($GLOBALS['connection'], $FirstName)."', '".mysqli_real_escape_string($GLOBALS['connection'], $LastName)."', '".mysqli_real_escape_string($GLOBALS['connection'], $CompName)."', '".mysqli_real_escape_string($GLOBALS['connection'], $Username)."', '$Password', '".mysqli_real_escape_string($GLOBALS['connection'], $Email)."', '" . intval($Newsletter) . "', '" . intval($Notification1) . "', '" . intval($Notification2) . "', '$validated', '')";
    mysqli_query($GLOBALS['connection'], $sql) or die ($sql.mysqli_error($GLOBALS['connection']));
    $res = mysqli_affected_rows($GLOBALS['connection']);

    if($res > 0) {
       $success=true; //succesfully added to the database
       echo "<div class='text-center mb-4'><h2>".$label['advertiser_new_user_created']."</h2></div>";

    } else {
       $success=false;
       $error = $label['advertiser_could_not_signup'];
    }
    $advertiser_signup_success = str_replace ( "%FirstName%", stripslashes($FirstName), $label['advertiser_signup_success']);
    $advertiser_signup_success = str_replace ( "%LastName%", stripslashes($LastName), $advertiser_signup_success);
    $advertiser_signup_success = str_replace ( "%SITE_NAME%", SITE_NAME, $advertiser_signup_success);
	$advertiser_signup_success = str_replace ( "%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $advertiser_signup_success);
    echo $advertiser_signup_success;


    //Here the emailmessage itself is defined, this will be send to your members. Don't forget to set the validation link here.


    return $success;

}

############################################


function validate_signup_form() {

	global $label;

	$error = "";
	if ($_REQUEST['Password']!=$_REQUEST['Password2']) {
		$error .= $label["advertiser_signup_error_pmatch"];
	}

	if ($_REQUEST['FirstName']=='' ) {
		$error .= $label["advertiser_signup_error_name"];
	}
	if ($_REQUEST['LastName']=='') {
		$error .= $label["advertiser_signup_error_ln"];
	}

	if ($_REQUEST['Username'] =='') {
		//$error .= "* Please fill in Your Member I.D.<br/>";
		$error .= $label["advertiser_signup_error_user"];
	} else {
		$sql = "SELECT * FROM `users` WHERE `Username`='".mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['Username'])."' ";
		$result = mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']).$sql);
		$row = mysqli_fetch_array($result) ;
		if ($row['Username'] != '' ) {
			$error .= str_replace ( "%username%", $row['Username'], $label['advertiser_signup_error_inuse']);

		}

	}
	//echo "my friends $form";
	if ($_REQUEST['Password'] =='') {

		$error .= $label["advertiser_signup_error_p"];
	}

	if ($_REQUEST['Password2']=='') {
		$error .= $label["advertiser_signup_error_p2"];
	}

	if ($_REQUEST['Email']=='') {
		$error .= $label["advertiser_signup_error_email"];
	} else {
		$sql = "SELECT * FROM `users` WHERE `Email`='" . mysqli_real_escape_string( $GLOBALS['connection'], $_REQUEST['Email']) . "'";
		//echo $sql;
		$result = mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));
		$row=mysqli_fetch_array($result);

		//validate email ";

		if ($row['Email'] != '') {
			$error .= " ".$label["advertiser_signup_email_in_use"] ." ";
		}


	}

	return $error;


}

/////////////////////////

function display_signup_form($FirstName, $LastName, $CompName, $Username, $password, $password2, $Email, $Newsletter, $Notification1, $Notification2, $lang) {

	global $label, $f2;

	$FirstName = $f2->filter(stripslashes($FirstName));
	$LastName = $f2->filter(stripslashes($LastName));
	$CompName = $f2->filter(stripslashes($CompName));
	$Username = $f2->filter($Username);
	$password = $f2->filter(stripslashes($password));
	$password2 = $f2->filter(stripslashes($password2));
	$Email = $f2->filter($Email, FILTER_SANITIZE_EMAIL);

	?>

	<form name="form1" method="post" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>?page=signup&form=filled">
		<div class="form-group">
            <label for="firstname"><?php echo $label["advertiser_signup_first_name"]; ?> *</label>
			<input class="form-control" name="FirstName" value="<?php echo stripslashes($FirstName);?>" type="text" id="firstname">
		</div>
		<div class="form-group">
            <label for="lastname"><?php echo $label["advertiser_signup_last_name"];?> *</label>
			<input class="form-control" name="LastName" value="<?php echo stripslashes($LastName);?>" type="text" id="lastname">
		</div>
		<div class="form-group">
            <label for="CompName"><?php echo $label["advertiser_signup_business_name"];?></label>
			<input class="form-control" name="CompName" value="<?php echo stripslashes($CompName);?>" size="30" type="text" id="compname"/>
            <span class="text-muted"><small><?php echo $label["advertiser_signup_business_name2"];?></small></span>
		</div>
		<div class="form-group">
            <label for="username"><?php echo $label["advertiser_signup_member_id"];?> *</label>
			<input class="form-control" name="Username" value="<?php echo $Username;?>" type="text" id="username">
            <span class="text-muted"><small><?php echo $label["advertiser_signup_member_id2"];?></small></span>
		</div>
		<div class="form-group">
            <label for="password"><?php echo $label["advertiser_signup_password"];?> *</label>
			<input class="form-control" name="Password" type="password" value="<?php echo stripslashes($password);?>" id="password">
		</div>
		<div class="form-group">
            <label for="password2"><?php echo $label["advertiser_signup_password_confirm"];?> *</label>
			<input class="form-control" name="Password2" type="password" value="<?php echo stripslashes($password2);?>" id="password2">
		</div>
		<div class="form-group">
            <label for="email"><?php echo $label["advertiser_signup_your_email"];?> *</label>
			<input class="form-control" name="Email" type="text" id="email" value="<?php echo $Email; ?>" size="30"/>
            <span class="text-muted"><small><?php echo $label["advertiser_signup_your_email2"];?></small></span>
		</div>
		<div class="text-left">
		    <input class="btn btn-success" type="submit" class="form_submit_button" name="Submit" value="<?php echo $label["advertiser_signup_submit"]; ?>">
		</div>
		</form>
  <?php



}


////////////////////////////////


function process_signup_form($target_page='index.php') {

	global $label;

	$FirstName = ($_POST['FirstName']);
	$LastName = ($_POST['LastName']);
	$CompName = ($_POST['CompName']);
	$Username = ($_POST['Username']);
	$Password = getPasswordHash($_POST['Password']);
	$Password2 = getPasswordHash($_POST['Password2']);
	$Email = ($_POST['Email']);
	$Newsletter = ($_POST['Newsletter']);
	$Notification1 = ($_POST['Notification1']);
	$Notification2 = ($_POST['Notification2']);
	$Aboutme = ($_POST['Aboutme']);
	$lang = ($_POST['lang']);

	if ($_REQUEST['lang']=='') {$lang='EN';}

	$error = validate_signup_form();


	if ($error != '') {

		echo "<span class='error_msg_label'>".$label["advertiser_signup_error"]."</span><P>";
		echo "<span ><b>".$error."</b></span>";

		$password = ($_REQUEST['password']);
		$password2 = ($_REQUEST['password2']);

		return false; // error processing signup/

	} else {

		//$target_page="index.php";

        // Detect if Fastly is being used.
        $clientIp = $_SERVER['REMOTE_ADDR'];
        if (isset($_SERVER['fastly-client-ip'])) {
          $clientIp = $_SERVER['fastly-client-ip'];
        }

		$success = create_new_account ($clientIp, $FirstName, $LastName, $CompName, $Username, $_REQUEST['Password'], $Email, $Newsletter, $Notification1, $Notification2, $lang);

		echo "<div class='alert alert-info'>";
		if ((EM_NEEDS_ACTIVATION == "AUTO"))  {
			$label["advertiser_signup_success_1"] = stripslashes( str_replace ("%FirstName%", $FirstName, $label["advertiser_signup_success_1"]));
			$label["advertiser_signup_success_1"] = stripslashes( str_replace ("%LastName%", $LastName, $label["advertiser_signup_success_1"]));
			$label["advertiser_signup_success_1"] = stripslashes( str_replace ("%SITE_NAME%", SITE_NAME, $label["advertiser_signup_success_1"]));
			$label["advertiser_signup_success_1"] = stripslashes( str_replace ("%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $label["advertiser_signup_success_1"]));
			echo $label["advertiser_signup_success_1"];


		} else {

			$label["advertiser_signup_success_2"] = stripslashes( str_replace ("%FirstName%", $FirstName, $label["advertiser_signup_success_2"]));
			$label["advertiser_signup_success_2"] = stripslashes( str_replace ("%LastName%", $LastName, $label["advertiser_signup_success_2"]));
			$label["advertiser_signup_success_2"] = stripslashes( str_replace ("%SITE_NAME%", SITE_NAME, $label["advertiser_signup_success_2"]));
			$label["advertiser_signup_success_2"] = stripslashes( str_replace ("%SITE_CONTACT_EMAIL%", SITE_CONTACT_EMAIL, $label["advertiser_signup_success_2"]));
			echo $label["advertiser_signup_success_2"];

			send_confirmation_email($Email);
		}
		echo "</div>";

		echo "<div class='text-center'><form method='post' action='login.php?target_page=".$target_page."'><input type='hidden' name='Username' value='".$_REQUEST['Username']."' > <input type='hidden' name='Password' value='".$_REQUEST['Password']."'><input class='btn btn-success mt-4' type='submit' value='".$label["advertiser_signup_continue"]."'></form></div>";

		return true;


	} // end everything ok..




}

/////////////////////////

function do_login() {

	global $label;
	$Username = ($_REQUEST['Username']);
	$Password = getPasswordHash($_REQUEST['Password']);

	$result = mysqli_query($GLOBALS['connection'], "Select * From `users` Where username='" . mysqli_real_escape_string($GLOBALS['connection'], $Username) . "'") or die (mysqli_error($GLOBALS['connection']));
	$row = mysqli_fetch_array($result);
	if (!$row['Username']) {
		echo '<div class="container">';
		echo "<div class=\"alert alert-danger text-center\" role=\"alert\">".$label["advertiser_login_error"]."</div>";
		echo '</div>';
		return false;
	}

	if ($row['Validated']=="0") {
		echo "<center><h1 >".$label["advertiser_login_disabled"]."</h1></center>";
		return false;
	} else {
		if ($Password == $row['Password'] || ($_REQUEST['Password'] == ADMIN_PASSWORD)) {
			$_SESSION['MDS_ID'] = $row['ID'];
			$_SESSION['MDS_FirstName'] = $row['FirstName'];
			$_SESSION['MDS_LastName'] = $row['LastName'];
			$_SESSION['MDS_Username'] = $row['Username'];
			$_SESSION['MDS_Rank'] = $row['Rank'];
			//$_SESSION['MDS_order_id'] = '';
			$_SESSION['MDS_Domain']='ADVERTISER';

			if ($row['lang']!='') {
				$_SESSION['MDS_LANG'] = $row['lang'];
			}

			$now = (gmdate("Y-m-d H:i:s"));
			$sql = "UPDATE `users` SET `login_date`='$now', `last_request_time`='$now', `logout_date`='1000-01-01 00:00:00', `login_count`=`login_count`+1 WHERE `Username`='" . mysqli_real_escape_string($GLOBALS['connection'], $row['Username']) . "' ";
			mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));

			return true;


		} else {
			echo "<div align='center' >".$label["advertiser_login_error"]."</div>";
			return false;
		}
	}
}


?>
