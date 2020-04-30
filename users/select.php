<?php
/**
 * @package        mds
 * @copyright      (C) Copyright 2020 Ryan Rhode, All rights reserved.
 * @author         Ryan Rhode, ryan@milliondollarscript.com
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

session_save_path('/app/files/sessions/');
session_start();
require_once( __DIR__ . "/../config.php" );

require_once( __DIR__ . "/login_functions.php" );

process_login();

if ( isset( $_REQUEST['BID'] ) && ! empty( $_REQUEST['BID'] ) ) {
	if ( $f2->bid( $_REQUEST['BID'] ) != '' ) {
		$BID             = $f2->bid( $_REQUEST['BID'] );
		$_SESSION['BID'] = $BID;
	} else {
		$BID = $_SESSION['BID'];
	}
} else {
	$BID = 1;
}

if ( ! is_numeric( $BID ) ) {
	die();
}

$banner_data = load_banner_constants( $BID );

$sql          = "SELECT * from orders where user_id='" . intval( $_SESSION['MDS_ID'] ) . "' and status='new' and banner_id='$BID' ";
$order_result = mysqli_query( $GLOBALS['connection'], $sql );
$order_row    = mysqli_fetch_array( $order_result );

if ( $order_row != null ) {

	// do a test, just in case.
	if ( ( $order_row['user_id'] != '' ) && $order_row['user_id'] != $_SESSION['MDS_ID'] ) {
		die( 'you do not own this order!' );
	}

	// only 1 new order allowed per user per grid
	if ( isset( $_REQUEST['banner_change'] ) && ! empty( $_REQUEST['banner_change'] ) ) {
		// clear the current order
		$_SESSION['MDS_order_id'] = '';

		// delete the old order and associated blocks
		$sql = "delete from orders where order_id=" . intval( $order_row['order_id'] );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );
		$sql = "delete from blocks where order_id=" . intval( $order_row['order_id'] );
		$result = mysqli_query( $GLOBALS['connection'], $sql ) or die ( mysqli_error( $GLOBALS['connection'] ) . $sql );

	} else if ( ( $_SESSION['MDS_order_id'] == '' ) || ( USE_AJAX == 'YES' ) ) {
		// save the order id to session
		$_SESSION['MDS_order_id'] = $order_row['order_id'];
	}
}

if ( isset( $_REQUEST['select'] ) && ! empty( $_REQUEST['select'] ) ) {

	if ( $_REQUEST['sel_mode'] == 'sel4' ) {

		$max_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
		$max_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

		$cannot_sel = select_block( $_REQUEST['map_x'], $_REQUEST['map_y'] );
		if ( ( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'] <= $max_x ) ) {
			$cannot_sel = select_block( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'], $_REQUEST['map_y'] );
		}
		if ( ( $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] <= $max_y ) ) {
			$cannot_sel = select_block( $_REQUEST['map_x'], $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] );
		}
		if ( ( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'] <= $max_x ) && ( $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] <= $max_y ) ) {
			$cannot_sel = select_block( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'], $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] );
		}

	} elseif ( $_REQUEST['sel_mode'] == 'sel6' ) {

		$max_x = $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH'];
		$max_y = $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT'];

		$cannot_sel = select_block( $_REQUEST['map_x'], $_REQUEST['map_y'] );

		if ( ( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'] <= $max_x ) ) {
			$cannot_sel = select_block( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'], $_REQUEST['map_y'] );
		}
		if ( ( $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] <= $max_y ) ) {
			$cannot_sel = select_block( $_REQUEST['map_x'], $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] );
		}
		if ( ( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'] <= $max_x ) && ( $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] <= $max_y ) ) {
			$cannot_sel = select_block( $_REQUEST['map_x'] + $banner_data['BLK_WIDTH'], $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] );
		}

		if ( ( $_REQUEST['map_x'] + ( $banner_data['BLK_WIDTH'] * 2 ) <= $max_x ) ) {
			$cannot_sel = select_block( $_REQUEST['map_x'] + ( $banner_data['BLK_WIDTH'] * 2 ), $_REQUEST['map_y'] );
		}

		if ( ( $_REQUEST['map_x'] + ( $banner_data['BLK_WIDTH'] * 2 ) <= $max_x ) && ( $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] <= $max_y ) ) {
			$cannot_sel = select_block( $_REQUEST['map_x'] + ( $banner_data['BLK_WIDTH'] * 2 ), $_REQUEST['map_y'] + $banner_data['BLK_HEIGHT'] );
		}

	} else {

		$cannot_sel = select_block( $_REQUEST['map_x'], $_REQUEST['map_y'] );

	}

}

require( "header.php" );

$block_str = ( $order_row['blocks'] == "" ) ? "-1" : $order_row['blocks'];

?>
    <div id="blocks"></div>

    <script>

		// Initialize
		let block_str = "<?php echo $block_str; ?>";
		let selectedBlocks = block_str.split(',').map(Number);
		let selBlocksIndex = 0;
		let selecting = false;
		let ajaxing = false;

		// noinspection JSAnnotator
		const grid_width = <?php echo $banner_data['G_WIDTH']?>;
		// noinspection JSAnnotator
		const grid_height = <?php echo $banner_data['G_HEIGHT']?>;

		// noinspection JSAnnotator
		const BLK_WIDTH = <?php echo $banner_data['BLK_WIDTH']?>;
		// noinspection JSAnnotator
		const BLK_HEIGHT = <?php echo $banner_data['BLK_HEIGHT']?>;

		const GRD_WIDTH = BLK_WIDTH * grid_width;
		const GRD_HEIGHT = BLK_HEIGHT * grid_height;

		// noinspection JSAnnotator
		const G_PRICE = <?php echo $banner_data['G_PRICE']; ?>;

		let myblocks;
		let total_cost;
		let grid;
		let submit_button1;
		let submit_button2;
		let pointer;

		window.onload = function () {
			grid = document.getElementById("pixelimg");
			myblocks = document.getElementById('blocks');
			total_cost = document.getElementById('total_cost');
			submit_button1 = document.getElementById('submit_button1');
			submit_button2 = document.getElementById('submit_button2');
			pointer = document.getElementById('block_pointer');
			load_order();
			update_total_cost();
			window.onresize = refreshSelectedLayers;

			// support touch screens
			grid.addEventListener('touchend', function onTouchEnd(event) {
				let touches = event.changedTouches;
				show_pointer(touches[0]);
				select_pixels(touches[0]);
			}, false);
			grid.addEventListener('touchmove', function onTouchMove(event) {
				let touches = event.changedTouches;
				show_pointer(touches[0]);
				select_pixels(touches[0]);
			}, false);
		};

		function update_total_cost() {
			if (typeof (total_cost) != 'undefined' && total_cost != null) {
				total_cost.innerText = "₿" + (myblocks.childElementCount * G_PRICE);
			}
		}

		function load_order() {
			<?php
			// load any existing blocks for this order
			$order_blocks = array();

			if(isset( $order_row['blocks'] ) && $order_row['blocks'] != "") {

			$block_ids = explode( ',', $order_row['blocks'] );
			foreach ( $block_ids as $block_id ) {
				$pos            = get_block_position( $block_id, $BID );
				$order_blocks[] = array(
					'block_id' => $block_id,
					'x'        => $pos['x'],
					'y'        => $pos['y'],
				);
			}
			?>

			let blocks = JSON.parse('<?php echo json_encode( $order_blocks ); ?>');

			for (var i = 0; i < blocks.length; i++) {
				add_block(parseInt(blocks[i].block_id), parseInt(blocks[i].x), parseInt(blocks[i].y));
			}
			<?php
			}
			?>

			const form1 = document.getElementById('form1');
			form1.addEventListener('submit', form1Submit);
		}

		function update_order() {
			if (selectedBlocks !== -1) {
				document.form1.selected_pixels.value = selectedBlocks.join(',');
			}
		}

		function reserve_block(clicked_block) {
			if (selectedBlocks.indexOf(clicked_block) === -1) {
				selectedBlocks.push(parseInt(clicked_block));

				// remove default value of -1 from array
				let index = selectedBlocks.indexOf(-1);
				if (index > -1) {
					selectedBlocks.splice(index, 1);
				}

				update_order();
			}
		}

		function unreserve_block(clicked_block) {
			let index = selectedBlocks.indexOf(clicked_block);
			if (index > -1) {
				selectedBlocks.splice(index, 1);
				update_order();
			}
		}

		function add_block(clicked_block, OffsetX, OffsetY) {

			//-J- Edit: Added tempTop and tempLeft values to span tag for resize bug
			myblocks.innerHTML = myblocks.innerHTML + "<span id='block" + clicked_block.toString() + "' tempTop=" + OffsetY + " tempLeft=" + OffsetX + " style='top: " + (OffsetY + grid.offsetTop) + "px; left: " + (OffsetX + grid.offsetLeft) + "px;' onclick='select_pixels(event, " + OffsetX + ", " + OffsetY + ");' onmousemove='show_pointer(event)' ><img src='selected_block.png' width='<?php echo $banner_data['BLK_WIDTH']; ?>' height='<?php echo $banner_data['BLK_HEIGHT']; ?>'></span>";

			reserve_block(clicked_block);
		}

		function remove_block(clicked_block) {
			let myblock = document.getElementById("block" + clicked_block.toString());
			if (myblock !== null) {
				myblock.remove();
			}

			unreserve_block(clicked_block);
		}

		//Begin -J- Edit: Custom functions for resize bug
		//Taken from http://www.quirksmode.org/js/findpos.html; but modified
		function findPosX(obj) {
			let curleft = 0;
			if (obj.offsetParent) {
				while (obj.offsetParent) {
					curleft += obj.offsetLeft;
					obj = obj.offsetParent;
				}
			} else if (obj.x)
				curleft += obj.x;
			return curleft;
		}

		//Taken from http://www.quirksmode.org/js/findpos.html; but modified
		function findPosY(obj) {
			let curtop = 0;
			if (obj.offsetParent) {
				while (obj.offsetParent) {
					curtop += obj.offsetTop;
					obj = obj.offsetParent;
				}
			} else if (obj.y)
				curtop += obj.y;
			return curtop;
		}

		function refreshSelectedLayers() {
			let gridLeft = findPosX(grid); //Get image grid's new X position
			let gridTop = findPosY(grid); //Get image grid's new Y position
			let layer; //Used to hold layer elements below
			for (let i = 0; i < selectedBlocks.length; i++) //Loop through selectedBlocks array
			{
				if (selectedBlocks[i] !== '') //If spot isn't empty
				{
					layer = document.getElementById("block" + selectedBlocks[i]); //Get layer element given blockID stored in selectedBlocks array
					if (layer !== null) {
						//Update layer relative to new pos of image grid
						layer.style.left = gridLeft + parseFloat(layer.getAttribute("tempLeft")) + "px";
						layer.style.top = gridTop + parseFloat(layer.getAttribute("tempTop")) + "px";
					}
				} //End of if(selectedBlockIDs[i] != ''....
			} //End for loop
		} //End testing()
		//End -J- Edit: Custom functions for resize bug

		function invert_block(clicked_block) {
			let myblock = document.getElementById("block" + clicked_block.id.toString());
			if (myblock !== null) {
				remove_block(clicked_block.id);
			} else {
				add_block(clicked_block.id, clicked_block.x, clicked_block.y);
			}
			update_total_cost();
		}

		function invert_blocks(block, OffsetX, OffsetY) {
			let clicked_blocks = [];
			let x;
			let y;

			// actual clicked block
			x = OffsetX;
			y = OffsetY;
			clicked_blocks.push({
				id: block,
				x: x,
				y: y
			});

			// additional blocks if multiple selection radio buttons are selected
			if (document.getElementById('sel4').checked) {
				// select 4 - 4x4

				x = OffsetX + BLK_WIDTH;
				y = OffsetY;
				clicked_blocks.push({
					id: get_block_id_from_position(x, y),
					x: x,
					y: y
				});

				x = OffsetX;
				y = OffsetY + BLK_HEIGHT;
				clicked_blocks.push({
					id: get_block_id_from_position(x, y),
					x: x,
					y: y
				});

				x = OffsetX + BLK_WIDTH;
				y = OffsetY + BLK_HEIGHT;
				clicked_blocks.push({
					id: get_block_id_from_position(x, y),
					x: x,
					y: y
				});

			} else {
				// select 6 - 3x2

				if (document.getElementById('sel6').checked) {

					x = OffsetX + BLK_WIDTH;
					y = OffsetY;
					clicked_blocks.push({
						id: get_block_id_from_position(x, y),
						x: x,
						y: y
					});

					x = OffsetX + (BLK_WIDTH * 2);
					y = OffsetY;
					clicked_blocks.push({
						id: get_block_id_from_position(x, y),
						x: x,
						y: y
					});

					x = OffsetX;
					y = OffsetY + BLK_HEIGHT;
					clicked_blocks.push({
						id: get_block_id_from_position(x, y),
						x: x,
						y: y
					});

					x = OffsetX + BLK_WIDTH;
					y = OffsetY + BLK_HEIGHT;
					clicked_blocks.push({
						id: get_block_id_from_position(x, y),
						x: x,
						y: y
					});

					x = OffsetX + (BLK_WIDTH * 2);
					y = OffsetY + BLK_HEIGHT;
					clicked_blocks.push({
						id: get_block_id_from_position(x, y),
						x: x,
						y: y
					});
				}
			}

			for (const clicked of clicked_blocks) {

				// invert block
				invert_block(clicked);
			}
		}

		function select_pixels(e, x = -1, y = -1) {
			e.preventDefault();
			e.stopPropagation();

			if (selecting) {
				return false;
			}
			selecting = true;

			// cannot select while AJAX is in action
			if (submit_button1.disabled) {
				return false;
			}

			pointer.style.visibility = 'hidden';
			let OffsetX = (x > -1) ? x : pointer.map_x;
			let OffsetY = (y > -1) ? y : pointer.map_y;

			change_block_state(OffsetX, OffsetY);

			return true;
		}

		/**
		 * @return {boolean}
		 */
		function IsNumeric(str) {
			let ValidChars = "0123456789";
			let IsNumber = true;
			let Char;

			for (let i = 0; i < str.length && IsNumber === true; i++) {
				Char = str.charAt(i);
				if (ValidChars.indexOf(Char) === -1) {
					IsNumber = false;
				}
			}
			return IsNumber;

		}

		function get_block_position(block_id) {

			let cell = "0";
			let ret = {};
			ret.x = 0;
			ret.y = 0;

			for (let i = 0; i < grid_height; i++) {
				for (let j = 0; j < grid_width; j++) {
					if (block_id === cell) {
						ret.x = j * BLK_WIDTH;
						ret.y = i * BLK_HEIGHT;
						return ret;

					}
					cell++;
				}
			}

			return ret;
		}

		function get_block_id_from_position(x, y) {

			let id = 0;
			for (let y2 = 0; y2 < GRD_HEIGHT; y2 += BLK_HEIGHT) {
				for (let x2 = 0; x2 < GRD_WIDTH; x2 += BLK_WIDTH) {
					if (x === x2 && y === y2) {
						return id;
					}
					id++;
				}
			}

			return id;
		}

		function change_block_state(OffsetX, OffsetY) {

			let clicked_block = ((OffsetX) / BLK_WIDTH) + ((OffsetY / BLK_HEIGHT) * (GRD_WIDTH / BLK_WIDTH));

			var xmlhttp = false;
			if (!xmlhttp && typeof XMLHttpRequest != 'undefined') {
				xmlhttp = new XMLHttpRequest();
			}

			submit_button1.disabled = true;
			submit_button2.disabled = true;
			pointer.style.cursor = 'wait';
			grid.style.cursor = 'wait';

			if (ajaxing === false) {
				ajaxing = true;
				xmlhttp.open("GET", "update_order.php?sel_mode=" + document.getElementsByName('pixel_form')[0].elements.sel_mode.value + "&user_id=<?php echo $_SESSION['MDS_ID'];?>&block_id=" + clicked_block.toString() + "&BID=<?php echo $BID . "&t=" . time(); ?>", true);

				xmlhttp.onreadystatechange = function () {
					if (xmlhttp.readyState === 4) {

						if ((xmlhttp.responseText === 'new')) {
							invert_blocks(clicked_block, OffsetX, OffsetY);

						} else {
							if (IsNumeric(xmlhttp.responseText)) {

								// save order id
								document.form1.order_id.value = xmlhttp.responseText;
								invert_blocks(clicked_block, OffsetX, OffsetY);

							} else {

								if (xmlhttp.responseText.indexOf('max_selected') > -1) {
									<?php
									$label['max_blocks_selected'] = str_replace( '%MAX_BLOCKS%', $banner_data['G_MAX_BLOCKS'], $label['max_blocks_selected'] );
									?>
									alert('<?php echo js_out_prep( $label['max_blocks_selected'] ); ?> ');
								} else if (xmlhttp.responseText.indexOf('max_orders') > -1) {
									alert('<?php echo js_out_prep( $label['advertiser_max_order'] )?>');
								} else if (xmlhttp.responseText.length > 0) {
									alert(xmlhttp.responseText);
								}
							}
						}

						submit_button1.disabled = false;
						submit_button2.disabled = false;
						pointer.style.cursor = 'pointer';
						pointer.style.visibility = 'visible';
						grid.style.cursor = 'pointer';
						selecting = false;
						ajaxing = false;
					}
				};

				xmlhttp.send(null);
			}
		}

		function implode(myArray) {

			let str = '';
			let comma = '';

			for (let i in myArray) {
				if (myArray.hasOwnProperty(i)) {
					str = str + comma + myArray[i];
				}
				comma = ',';
			}

			return str;
		}

		let pos;

		function getObjCoords(obj) {
			let pos = {x: 0, y: 0};
			let curtop = 0;
			let curleft = 0;
			if (obj.offsetParent) {
				while (obj.offsetParent) {
					curtop += obj.offsetTop;
					curleft += obj.offsetLeft;
					obj = obj.offsetParent;
				}
			} else if (obj.y) {
				curtop += obj.y;
				curleft += obj.x;
			}
			pos.x = curleft;
			pos.y = curtop;
			return pos;
		}

		function show_pointer(e) {
			if (grid == null) {
				// grid may not be loaded yet
				return;
			}

			let pos = getObjCoords(grid);

			let OffsetX;
			let OffsetY;
			// if (e.offsetX) {
			// 	OffsetX = e.offsetX;
			// 	OffsetY = e.offsetY;
			// } else {
			OffsetX = e.pageX - pos.x;
			OffsetY = e.pageY - pos.y;
			// }

			// drop 1/10 from the OffsetX and OffsetY, eg 612 becomes 610

			OffsetX = Math.floor(OffsetX / BLK_WIDTH) * BLK_WIDTH;
			OffsetY = Math.floor(OffsetY / BLK_HEIGHT) * BLK_HEIGHT;

			// keep within range
			OffsetX = Math.max(Math.min(OffsetX, GRD_WIDTH), 0);
			OffsetY = Math.max(Math.min(OffsetY, GRD_HEIGHT), 0);

			pointer.style.visibility = 'visible';
			pointer.style.display = 'block';

			pointer.style.top = (pos.y + OffsetY) + "px";
			pointer.style.left = (pos.x + OffsetX) + "px";

			pointer.map_x = OffsetX;
			pointer.map_y = OffsetY;

			let pointer_img = pointer.querySelector('img');

			if (document.getElementById('sel4').checked) {
				pointer.style.width = BLK_WIDTH * 2 + "px";
				pointer.style.height = BLK_HEIGHT * 2 + "px";
				pointer_img.style.width = BLK_WIDTH * 2 + "px";
				pointer_img.style.height = BLK_HEIGHT * 2 + "px";
			} else {

				if (document.getElementById('sel6').checked) {
					pointer.style.width = BLK_WIDTH * 3 + "px";
					pointer.style.height = BLK_HEIGHT * 2 + "px";
					pointer_img.style.width = BLK_WIDTH * 3 + "px";
					pointer_img.style.height = BLK_HEIGHT * 2 + "px";
				} else {
					pointer.style.width = BLK_WIDTH + "px";
					pointer.style.height = BLK_HEIGHT + "px";
					pointer_img.style.width = BLK_WIDTH + "px";
					pointer_img.style.height = BLK_HEIGHT + "px";
				}
			}

			return true;
		}

		function form1Submit(event) {
			event.preventDefault();
			event.stopPropagation();

			if (myblocks.innerHTML.trim() === '') {
				alert("<?php echo $label['no_blocks_selected'] ?>");
				return false;
			} else {
				document.form1.submit();
			}
		}
    </script>

    <style>
        #block_pointer {
            padding: 0;
            margin: 0;
            cursor: pointer;
            position: absolute;
            left: 0;
            top: 0;
            background-color: #FFFFFF;
            visibility: hidden;
            height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
            width: <?php echo $banner_data['BLK_WIDTH']; ?>px;
            line-height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
            font-size: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
            user-select: none;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
        }

        span[id^='block'] {
            padding: 0;
            margin: 0;
            cursor: pointer;
            position: absolute;
            background-color: #FFFFFF;
            width: <?php echo $banner_data['BLK_WIDTH']; ?>px;
            height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
            line-height: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
            font-size: <?php echo $banner_data['BLK_HEIGHT']; ?>px;
        }

        #pixelimg {
            cursor: pointer;
            outline: none;
            border: none;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            margin: 0 auto;
            float: none;
            display: block;
        }
    </style>

    <span id='block_pointer' oncontextmenu="return false;" ; unselectable="on" draggable="false" onclick="select_pixels(event);" onmousemove="show_pointer(event)"><img src='pointer.png' width="<?php echo $banner_data['BLK_WIDTH']; ?>" height="<?php echo $banner_data['BLK_HEIGHT']; ?>" alt=""></span>

    <p>
		<?php echo $label['advertiser_sel_trail']; ?>
    </p>

    <p id="select_status"><?php echo $cannot_sel; ?></p>

<?php

$sql = "SELECT * FROM banners order by `name`";
$res = mysqli_query( $GLOBALS['connection'], $sql );

if ( mysqli_num_rows( $res ) > 1 ) {
	?>
    <div class="fancy_heading" style="width:85%;"><?php echo $label['advertiser_sel_pixel_inv_head']; ?></div>
    <p>
		<?php
		$label['advertiser_sel_select_intro'] = str_replace( "%IMAGE_COUNT%", mysqli_num_rows( $res ), $label['advertiser_sel_select_intro'] );
		echo $label['advertiser_sel_select_intro'];
		?>
    </p>
    <p>
		<?php display_banner_selecton_form( $BID, $_SESSION['MDS_order_id'], $res ); ?>
    </p>
	<?php
}

if ( isset( $order_exists ) && $order_exists ) {
	echo "<p>" . $label['advertiser_order_not_confirmed'] . "</p>";
}

$has_packages = banner_get_packages( $BID );
if ( $has_packages ) {
	display_package_options_table( $BID, '', false );

} else {
	display_price_table( $BID );
}
?>
    <div class="fancy_heading" style="width:85%;"><?php echo $label['advertiser_select_pixels_head']; ?></div>
<?php
$label['advertiser_select_instructions2'] = str_replace( '%PIXEL_C%', $banner_data['BLK_HEIGHT'] * $banner_data['BLK_WIDTH'], $label['advertiser_select_instructions2'] );
$label['advertiser_select_instructions2'] = str_replace( '%BLK_HEIGHT%', $banner_data['BLK_HEIGHT'], $label['advertiser_select_instructions2'] );
$label['advertiser_select_instructions2'] = str_replace( '%BLK_WIDTH%', $banner_data['BLK_WIDTH'], $label['advertiser_select_instructions2'] );
echo $label['advertiser_select_instructions2']; ?>

    <form method="post" action="select.php" name='pixel_form'>
        <input type="hidden" name="jEditOrder" value="true">
        <p><b><?php echo $label['selection_mode']; ?></b> <input type="radio" id='sel1' name='sel_mode' value='sel1' <?php if ( ( $_REQUEST['sel_mode'] == '' ) || ( $_REQUEST['sel_mode'] == 'sel1' ) ) {
				echo " checked ";
			} ?> > <label for='sel1'><?php echo $label['select1']; ?></label> | <input type="radio" name='sel_mode' id='sel4' value='sel4' <?php if ( ( $_REQUEST['sel_mode'] == 'sel4' ) ) {
				echo " checked ";
			} ?> > <label for="sel4"><?php echo $label['select4']; ?></label> | <input type="radio" name='sel_mode' id='sel6' value='sel6' <?php if ( ( $_REQUEST['sel_mode'] == 'sel6' ) ) {
				echo " checked ";
			} ?> > <label for="sel6"><?php echo $label['select6']; ?></label>
        </p>
        <p>
            <input type="button" name='submit_button1' id='submit_button1' value='<?php echo htmlspecialchars( $label['advertiser_buy_button'] ); ?>' onclick='form1Submit(event)'>
        </p>

        <input type="hidden" value="1" name="select">
        <input type="hidden" value="<?php echo $BID; ?>" name="BID">

        <input id="pixelimg" draggable="false" unselectable="on" <?php if ( USE_AJAX == 'YES' ) { ?> onmouseout="pointer.style.visibility='hidden'" onmousemove="show_pointer(event)" onclick="select_pixels(event)" <?php } ?> type="image" name="map" value='Select Pixels.' style="width:<?php echo $banner_data['G_WIDTH'] * $banner_data['BLK_WIDTH']; ?>px;height:<?php echo $banner_data['G_HEIGHT'] * $banner_data['BLK_HEIGHT']; ?>;border:none;outline:none;" src="show_selection.php?BID=<?php echo $BID; ?>&gud=<?php echo time(); ?>" alt=""/>

        <input type="hidden" name="action" value="select">
    </form>
    <div style='display:none;background-color: #ffffff; border-color:#C0C0C0; border-style:solid;padding:10px'>
        <hr>

        <form method="post" action="order.php" id="form1" name="form1">
            <input type="hidden" name="package" value="">
            <input type="hidden" name="selected_pixels" value=''>
            <input type="hidden" name="order_id" value="<?php echo $_SESSION['MDS_order_id']; ?>">
            <input type="hidden" value="<?php echo $BID; ?>" name="BID">
            <input type="submit" name='submit_button2' id='submit_button2' value='<?php echo htmlspecialchars( $label['advertiser_buy_button'] ); ?>'>
            <hr>
        </form>

    </div>

<?php require "footer.php"; ?>