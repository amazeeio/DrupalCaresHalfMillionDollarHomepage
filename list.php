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

// set the root path
define( "MDSROOT", dirname( __FILE__ ) );

// include the config file
include_once( MDSROOT . "/config.php" );

// include the header
include_once( MDSROOT . "/html/header.php" );

global $label;
?>
<div class="container px-0">

<?php include( MDSROOT . "/top_ads_js.php" ); ?>
<?php include( 'mouseover_box.htm' ); ?>

    <table class="table table-striped text-left">
        <thead>
        <tr>
            <th scope="col"><?php echo $label['list_date_of_purchase']; ?></th>
            <th scope="col"><?php echo $label['list_name']; ?></th>
            <th scope="col"><?php echo $label['list_ads']; ?></th>
            <th scope="col"><?php echo $label['list_pixels']; ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        require_once( "include/ads.inc.php" );

        $sql = "SELECT *, MAX(order_date) as max_date, sum(quantity) AS pixels FROM orders where status='completed' AND approved='Y' AND published='Y' AND banner_id='".intval($BID)."' GROUP BY user_id, banner_id, order_id order by pixels desc ";
        $result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
        while ( $row = mysqli_fetch_array( $result ) ) {
            $q = "SELECT FirstName, LastName FROM users WHERE ID=" . intval($row['user_id']);
            $q = mysqli_query( $GLOBALS['connection'], $q ) or die( mysqli_error( $GLOBALS['connection'] ) );
            $user = mysqli_fetch_row( $q );
            ?>
        <tr>
                <td>
                    <?php echo get_formatted_date( get_local_time( $row['max_date'] ) ); ?>
                </td>
                <td>
                    <?php echo $user['0'] . " " . $user['1']; ?>
                </td>
                <td>
                    <?php

                    $br = "";
                    $sql = "Select * FROM  `ads` as t1, `orders` AS t2 WHERE t1.ad_id=t2.ad_id AND t1.banner_id='".intval($BID)."' and t1.order_id='" . intval($row['order_id']) . "' AND t1.user_id='" . intval($row['user_id']) . "' AND status='completed' AND approved='Y' ORDER BY `ad_date`";
                    $m_result = mysqli_query( $GLOBALS['connection'], $sql ) or die( mysqli_error( $GLOBALS['connection'] ) );
                    while ( $prams = mysqli_fetch_array( $m_result, MYSQLI_ASSOC ) ) {

                        $ALT_TEXT = get_template_value( 'ALT_TEXT', 1 );
                        $ALT_TEXT = str_replace( "'", "", $ALT_TEXT );
                        $ALT_TEXT = ( str_replace( "\"", '', $ALT_TEXT ) );
                        $js_str   = "onmouseover=\"sB(event, '" . $ALT_TEXT . "', this, " . $prams['ad_id'] . ")\" onmousemove=\"sB(event, '" . $ALT_TEXT . "', this, " . $prams['ad_id'] . ")\" onmouseout=\"hI()\" ";
                        echo $br . '<a target="_blank" ' . $js_str . ' href="http://' . get_template_value( 'URL', 1 ) . '">' . get_template_value( 'ALT_TEXT', 1 ) . '</a>';
                        $br = '<br>';
                    }

                    ?>
                </td>
                <td>
                    <?php echo $row['pixels']; ?>
                </td>
        </tr>
            <?php

        }
        ?>
        </tbody>
    </table>
</div>
<?php
include_once( MDSROOT . "/html/footer.php" );
?>