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

// Contributed by Martin 
	// AREA render  function
	// Million Penny Home Page
	// http://www.onecentads.com/
	function render_map_area($fh,$data, $b_row) {

		$BID = $b_row['banner_id'];
		
		if (isset($data['x2'])) {
		  $x2 = $data['x2'];
		  $y2 = $data['y2'];
		} else {
		  $x2 = $data['x1'];
		  $y2 = $data['y1'];
		}
		fwrite($fh, "<area ");
		if (ENABLE_CLOAKING == 'YES') {
		  fwrite($fh, "onclick=\"return po(".$data['block_id'].");\" href=\"http://".$data['url']."\" ");
		} else {
		  fwrite($fh, "onclick=\"block_clicked=true;\" href=\"click.php?block_id=".$data['block_id']."&BID=$BID\" target=\"_blank\" " );
		}
		if ((ENABLE_MOUSEOVER=='YES') || (ENABLE_MOUSEOVER=='POPUP')) {
			//$data['alt_text']=$data['ad_id'];
			if ($data['ad_id']>0) {
			  $data['alt_text'] = $data['alt_text'].'<img src="'.BASE_HTTP_PATH.'periods.gif" border="0">';
		  }
		fwrite( $fh, "onmouseover=\"sB(event,'" . htmlspecialchars( $data['alt_text'] ) . "',this, " . $data['ad_id'] . ")\" onmousemove=\"sB(event,'" . htmlspecialchars( $data['alt_text'] ) . "',this, " . $data['ad_id'] . ")\" onmouseout=\"hI()\" " );
		}
		fwrite($fh, "coords=\"".$data['x1'].",".$data['y1'].",".($x2+$b_row['block_width']).",".($y2+$b_row['block_height'])."\"");
		if (ENABLE_MOUSEOVER=='NO') {
		  fwrite($fh, " title=\"".htmlspecialchars($data['alt_text'])."\" alt=\"".htmlspecialchars($data['alt_text'])."\"");
		}
		fwrite($fh, ">\n");
	
	}

/*

This function generates the <AREA> tags
The output is saved into a file.

*/

function process_map($BID, $map_file='') {
	

	if ( ! is_numeric( $BID ) ) {
		die();
	}

	$sql = "UPDATE orders SET published='N' where `status`='expired' ";
	mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));

	$sql = "SELECT * FROM `banners` WHERE `banner_id`='".intval($BID)."' ";
	$result = mysqli_query($GLOBALS['connection'], $sql) or die(mysqli_error($GLOBALS['connection']));
	$b_row = mysqli_fetch_array($result);

	if (!$b_row['block_width']) { $b_row['block_width'] = 10;}
	if (!$b_row['block_height']) { $b_row['block_height'] = 10;}



	if (!$map_file) {
		$map_file = get_map_file_name($BID);
	}

  // open file
  $fh = fopen("$map_file","w");

  fwrite($fh, '<map name="main" id="main" onmousemove="cI()">');

  // render client-side click areas
  $sql = "SELECT DISTINCT order_id, user_id,url,image_data,block_id,alt_text,MIN(x) AS x1,MAX(x) AS x2,MIN(y) AS y1,MAX(y) AS y2, ad_id, COUNT(*) AS Total
                     FROM blocks
                    WHERE (published = 'Y')
					  AND (status = 'sold' ) 
                      AND (banner_id = '".intval($BID)."')
                      AND (image_data > '')
                      AND (image_data = image_data)
                 GROUP BY order_id, user_id,url,image_data,block_id,alt_text";
  $result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']));
  
  while ($row = mysqli_fetch_array($result)) {

	// Determine height and width of an optimized rect
	$x_span = $row['x2'] - $row['x1'] + $b_row['block_width'];
	$y_span = $row['y2'] - $row['y1'] + $b_row['block_height'];

	// Determine if reserved space is not equal to a single-ad user's optimized RECT
	if ( ( ($x_span * $y_span) / ($b_row['block_width']*$b_row['block_height']) ) != $row['Total'] ) {

	  // Render POLY or RECT (given reasonable possibilities)
	  $sql_i = "SELECT DISTINCT url, image_data, block_id, alt_text, MIN(x) AS x1, MAX(x) AS x2, y AS y1, y AS y2, ad_id, COUNT(*) AS Total
						   FROM blocks
						  WHERE (published = 'Y')
							AND (status = 'sold' ) 
							AND (banner_id = '".intval($BID)."')
							AND (image_data > '')
							AND (image_data = image_data)
							AND (order_id = ".intval($row['order_id']).")
					   GROUP BY y";
	  $res_i = mysqli_query($GLOBALS['connection'], $sql_i) or die(mysqli_error($GLOBALS['connection']));
	  while ($row_i = mysqli_fetch_array($res_i)) {

		// If the min/max measure does not equal number of boxes, then we have to render this row's boxes individually
		//$box_count = ( ( ( $row_i['x2'] + 10 ) - $row_i['x1'] ) / 10 );
		$box_count = ( ( ( $row_i['x2'] + $b_row['block_width'] ) - $row_i['x1'] ) / $b_row['block_width'] );
		if ($box_count != $row_i['Total']) {
		  // must render individually as RECT
		  $sql_r = "SELECT ad_id, url, image_data, block_id, alt_text, x AS x1, x AS x2, y AS y1, y AS y2
					  FROM blocks
					 WHERE (published = 'Y')
					   AND (status = 'sold' ) 
					   AND (banner_id = '".intval($BID)."')
					   AND (image_data > '')
					   AND (image_data = image_data)
					   AND (order_id = ".intval($row['order_id']).")
					   AND (y = ".intval($row_i['y1']).")";
		  $res_r = mysqli_query($GLOBALS['connection'], $sql_r);
		  while ($row_r = mysqli_fetch_array($res_r)) {
			// render single block RECT
			render_map_area($fh,$row_r, $b_row);
		  }
		} else {
		  // render multi-block RECT
		  render_map_area($fh,$row_i, $b_row);
		}
	  }
	} else {
	  // Render full ad RECT
	  render_map_area($fh,$row, $b_row);
	}

  }

  fwrite($fh, "</map>");
  fclose($fh);

}

////////////////////////////////////////
/*

This function outputs the HTML for the display_map.php file.
The structure of output:

<head>
<script>
<!-- Javascript in here ->
</script>
</head>
<body> <!--- render the grid's background image ->

<MAP> <!--- generated by process_map() ->

<AREA></AREA> <!--- generated by process_map() ->
<AREA></AREA> <!--- generated by process_map() ->
<AREA></AREA> <!--- generated by process_map() ->
...

</MAP> <!--- generated by process_map() ->

<img>

</body>

*/

function show_map($BID = 1) {
	

if ( ! is_numeric( $BID ) ) {
	die();
}

	if (BANNER_DIR=='BANNER_DIR') {	
		$BANNER_DIR = "banners/";
	} else {
		$BANNER_DIR = BANNER_DIR;
	}

	$BANNER_PATH = BASE_PATH . "/" . $BANNER_DIR;

	$sql = "SELECT grid_width,grid_height, block_width, block_height, bgcolor, time_stamp FROM banners WHERE (banner_id = '".intval($BID)."')";
	$result = mysqli_query($GLOBALS['connection'], $sql) or die (mysqli_error($GLOBALS['connection']).$sql);
	$b_row = mysqli_fetch_array($result);

	if (!$b_row['block_width']) { $b_row['block_width'] = 10;}
	if (!$b_row['block_height']) { $b_row['block_height'] = 10;}

	/*

	Cache controls:

	We have to make sure that this html page is cashed by the browser.
	If the banner was not modified, then send out a HTTP/1.0 304 Not Modified and exit
	otherwise output the HTML to the browser.

	*/

	if (MDS_AGRESSIVE_CACHE=='YES') {

		header('Cache-Control: public, must-revalidate'); // cache all requests, browsers must respect this php script
		$if_modified_since = preg_replace('/;.*$/', '', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
		$gmdate_mod = gmdate('D, d M Y H:i:s', $b_row['time_stamp']) . ' GMT';

		if ($if_modified_since == $gmdate_mod) {
			header("HTTP/1.0 304 Not Modified");
			exit;	
		}
		header("Last-Modified: $gmdate_mod");

	}

?><!DOCTYPE html>
<html>
	<head>
        <script>
	var h_padding=10;
	var v_padding=10;

		var winWidth = 0;
		var winHeight = 0;
		
		var pos = 'right';

		var strCache = [];

		var lastStr;
		var trip_count = 0;

		function initialize() {
			bubblebox();
			initFrameSize();
        }

		function bubblebox() {
			window.bubblebox = document.getElementById('bubble');
		}

		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", initialize);
		} else {
			initialize();
		}

		function initFrameSize() {

			winWidth =<?php echo $b_row['grid_width'] * $b_row['block_width']; ?>;
			winHeight =<?php echo $b_row['grid_height'] * $b_row['block_height']; ?>;
		}

		function is_right_available(e) {
			if ((window.bubblebox.clientWidth + e.clientX + h_padding) >= winWidth) {
				// not available
				return false;
		}
		return true;
	}

		function is_top_available(e) {
			if ((e.clientY - window.bubblebox.clientHeight - v_padding) < 0) {
			return false;
		}
		return true;

	}

		function is_bot_available(e) {
			if ((e.clientY + window.bubblebox.clientHeight + v_padding) > winHeight) {
			return false;
		}
		return true;
	}

		function is_left_available(e) {
			if ((e.clientX - window.bubblebox.clientWidth - h_padding) < 0) {
			return false;
		}
		return true;

	}

		function boxFinishedMoving() {
			var y = window.bubblebox.offsetTop;
			var x = window.bubblebox.offsetLeft;

			if ((y < window.bubblebox.ypos) || (y > window.bubblebox.ypos) || (x < window.bubblebox.xpos) || (x > window.bubblebox.xpos)) {
			return false;
		} else {
			return true;
		}
	}
	function moveBox() {
			var y = window.bubblebox.offsetTop;
			var x = window.bubblebox.offsetLeft;

			var diffx = Math.abs(x - window.bubblebox.xpos);
			var diffy = Math.abs(y - window.bubblebox.ypos);

			if (!boxFinishedMoving()) {
				if (y < window.bubblebox.ypos) {
				y+=Math.round(diffy*(0.01))+1; // calculate acceleration
					window.bubblebox.style.top = y + "px";
			}

				if (y > window.bubblebox.ypos) {
				y-=Math.round(diffy*(0.01))+1;
					window.bubblebox.style.top = y + "px";
			}

				if (x < window.bubblebox.xpos) {
				x+=Math.round(diffx*(0.01))+1; 
					window.bubblebox.style.left = x + "px";
			}

				if (x > window.bubblebox.xpos) {
					x -= Math.round(diffx * (0.01)) + 1;
					window.bubblebox.style.left = x + "px";
			}
			}
		} 

	// This function is used for the instant pop-up box
	function moveBox2() {

			var y = window.bubblebox.offsetTop;
			var x = window.bubblebox.offsetLeft;

			var diffx = Math.abs(x - window.bubblebox.xpos);
			var diffy = Math.abs(y - window.bubblebox.ypos);

			if (!boxFinishedMoving()) {
				if (y < window.bubblebox.ypos) {
				y=y+diffy;
					window.bubblebox.style.top = y + "px";
			}

				if (y > window.bubblebox.ypos) {
				y=y-diffy;
					window.bubblebox.style.top = y + "px";
			}

				if (x < window.bubblebox.xpos) {
				x=x+diffx;
					window.bubblebox.style.left = x + "px";
			}

				if (x > window.bubblebox.xpos) {
				x=x-diffx;
					window.bubblebox.style.left = x + "px";
			}
		} 

		
	}

	function isBrowserCompatible() {

		// check if we can XMLHttpRequest

		var xmlhttp=false;
		if (!xmlhttp && typeof XMLHttpRequest!='undefined') {
		  xmlhttp = new XMLHttpRequest();
		}

		if (!xmlhttp) {
			return false
		}
		return true;

	}

////////////////////

	function fillAdContent(aid, bubble) {

		if (!isBrowserCompatible()) {
			return false;
		}

		// is the content cached?
		if (strCache[aid])
		{
			bubble.innerHTML = strCache[aid];
			return true;
		}

		var xmlhttp=false;
		if (!xmlhttp && typeof XMLHttpRequest!='undefined') {
		  xmlhttp = new XMLHttpRequest();
		}

		xmlhttp.open("GET", "ga.php?AID="+aid+"<?php 
		
		echo "&t=".time(); ?>", true);

		xmlhttp.onreadystatechange=function() {
			if (xmlhttp.readyState==4) {
				if (xmlhttp.responseText.length > 0) {
					bubble.innerHTML = xmlhttp.responseText;
					strCache[''+aid] = xmlhttp.responseText
				} else {
					
					bubble.innerHTML = bubble.innerHTML.replace('<img src=\"<?php echo BASE_HTTP_PATH;?>periods.gif\" border=\"0\">','');
					


				}
				

				trip_count--;
			}
			
			};

		xmlhttp.send(null)


	}

	function sB(e, str, area, aid) {
		window.clearTimeout(timeoutId);

		var relTarg;
			if (!e) e = window.event;
		if (e.relatedTarget) relTarg = e.relatedTarget;
		else if (e.fromElement) relTarg = e.fromElement;

			var b = window.bubblebox.style;

			if (lastStr !== str) {

			lastStr=str;

			
			hideBubble(e);

			document.getElementById('content').innerHTML=str;
			trip_count++;
			
			fillAdContent(aid, document.getElementById('content'));
		}

        var mytop = is_top_available(e);
        var mybot = is_bot_available(e);
        var myright = is_right_available(e);
        var myleft = is_left_available(e);

		if (mytop)
		{
			// move to the top
			window.bubblebox.ypos = e.clientY - window.bubblebox.clientHeight - v_padding;
		}

		if (myright)
		{
			// move to the right
			window.bubblebox.xpos = e.clientX + h_padding;
		}

		if (myleft)
		{
			// move to the left
			window.bubblebox.xpos = e.clientX - window.bubblebox.clientWidth - h_padding;
		}

		
        if (mybot) {
            window.bubblebox.ypos = e.clientY + v_padding;
		}
	
		b.visibility='visible';

		<?php
		if (ENABLE_MOUSEOVER=='POPUP') {
		?>

			moveBox2();
			//moveBox(bubble);
			window.setTimeout("moveBox2()", <?php if (!is_numeric(ANIMATION_SPEED)) { echo '10'; } else { echo ANIMATION_SPEED; } ?>);
			<?php
		} else {

		?>
			moveBox();
			window.setTimeout("moveBox()", <?php if (!is_numeric(ANIMATION_SPEED)) { echo '10'; } else { echo ANIMATION_SPEED; } ?>);

		<?php

		}


		?>

	}

	function hBTimeout(e) {
		lastStr='';
		hideBubble(e);
	}

	function hideBubble(e) {

		window.clearTimeout(timeoutId);
			var b = window.bubblebox.style;
		b.visibility='hidden';

	}

	var timeoutId=0;

	function hI() {
		
			if (timeoutId === 0) {

			timeoutId = window.setTimeout('hBTimeout()', '<?php echo HIDE_TIMEOUT; ?>')

		}

	}

	function cI() {

			if (timeoutId !== 0) {
			timeoutId=0;
		}

	}

	function po(block_id) {

	  block_clicked=true;
	  window.open('click.php?block_id=' + block_id + '&BID=<?php echo $BID; ?>','','');
	  return false;
	}
	<?php if (REDIRECT_SWITCH=='YES') { ?>
	p = parent.window;
	<?php } ?>

	var block_clicked=false; // did the user click a sold block? 
		</script>
		<title></title>
        <link rel="stylesheet" href="/assets/css/grid.css">
	</head>
	<body class="grid-body">
	<?php
	include ('mouseover_box.htm'); // edit this file to change the style of the mouseover box!
	?>
	<style>
		body {
			overflow:hidden;
			padding:0;
			margin:0;
			<?php
			if (DISPLAY_PIXEL_BACKGROUND =='YES') {
				global $f2;
				?>
			background:#<?php echo $f2->filter($b_row['bgcolor']); ?> url('<?php echo BASE_HTTP_PATH.$BANNER_DIR;?>bg-main<?php echo $BID; ?>.gif');
			<?php } ?>
		}
	</style>
	<?php
	

	$map_file = get_map_file_name($BID);

	if (!file_exists($map_file)) {
		process_map($BID, $map_file);
	}

	include_once($map_file);

?>
<?php

	if (OUTPUT_JPEG == 'Y') {
		$ext = "jpg";
	} elseif (OUTPUT_JPEG=='N') {
		$ext = 'png';
	} elseif (OUTPUT_JPEG == 'GIF') {
		$ext = 'gif';
	}

	if (file_exists($BANNER_PATH."main".$BID.".$ext")) {
		if (REDIRECT_SWITCH=='YES') {
			$available_block_window = "parent.window.open('".REDIRECT_URL."', '', '');return false;";
		}
		?><img <?php if (REDIRECT_SWITCH=='YES') { ?>onclick="if (!block_clicked) {<?php echo $available_block_window; 
?> }block_clicked=false;" <?php } ?> id="theimage" src="<?php echo $BANNER_DIR; ?>main<?php echo $BID;?>.<?php echo $ext;?>?time=<?php echo ($b_row['time_stamp']); ?>" width="<?php echo $b_row['grid_width']*$b_row['block_width']; ?>" height="<?php echo $b_row['grid_height']*$b_row['block_height']; ?>" border="0" usemap="#main" /><?php

	} else {
		echo "<b>The file: ".$BANNER_PATH."main".$BID.".$ext"." doesn't exist.</b><br>";
		echo "<b>Please process your pixels from the Admin section (Look under 'Pixel Admin')</b>";
	}
	?>
	</body>
</html>
	<?php

}

///////////////////

function get_map_file_name($BID) {

	if (!is_numeric($BID)) {
		return false;

	}

	if (BANNER_DIR=='BANNER_DIR') {	
		$BANNER_DIR = "banners/";
	} else {
		$BANNER_DIR = BANNER_DIR;
	}

	$BANNER_PATH = BASE_PATH . "/" . $BANNER_DIR;

	$map_file = $BANNER_PATH."map_$BID.inc";

	return $map_file;


}


?>