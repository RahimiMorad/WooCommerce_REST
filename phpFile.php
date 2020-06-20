<?php
/**
 *
 * Plugin Name:  WooCommerce RESTAPI Sample
 * Description:
 * Version:      1.0.0
 * Plugin URI:
 * Author:       Mehdi Rahimi
 * Author URI:
 * Text Domain:  testtask
 * Domain Path:  /languages/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}
include_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {

	define( 'TOKEN', '5582f18d-8369-fa09-faac-d658b23d0797' );
	function test_enqueue() {
		wp_enqueue_style( 'testcss', plugins_url( 'cssFile.css', __FILE__ ) );
		wp_enqueue_script( 'testjs', plugins_url( 'jsFile.js', __FILE__ ) );
		wp_localize_script(
			'testjs',
			'customajax',
			array(
				'siteurl'      => site_url(),
				'access_token' => TOKEN,
			)
		);
	}
	add_action( 'admin_enqueue_scripts', 'test_enqueue' );

// Register Routes.
	add_action( 'rest_api_init', function () {

		register_rest_route( 'wooapi/v1', '/products/', array(
			'methods'  => 'GET',
			'callback' => 'products',
		) );

		register_rest_route( 'wooapi/v1', '/changetitle/', array(
			'methods'  => 'POST',
			'callback' => 'changetitle',
		) );

	} );

	function products( WP_REST_Request $request ) {

		$result_rest_response = [];
		$args                 = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'date',
		);
		$i    = 1;
		$loop = new WP_Query( $args );

		while ( $loop->have_posts() ): $loop->the_post();
			global $product;
			$result_rest_response['Products'][$i]['ID']    = get_the_ID();
			$result_rest_response['Products'][$i]['title'] = get_the_title();
			$result_rest_response['Products'][$i]['price'] = $product->get_price();
			$terms                                         = get_the_terms( $loop->ID, 'product_cat' );
			if ( $terms && !is_wp_error( $terms ) ) {
				$category = array();
				foreach ( $terms as $term ) {
					$category[] = $term->name;
				}
				$result_rest_response['Products'][$i]['categories'] = join( ", ", $category );
			}

			$i++;
		endwhile;
		wp_reset_query();
		$order_loop = new WP_Query( array(
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'posts_per_page' => 20,
		) );
		$i    = 1;
		$line = 1;
		while ( $order_loop->have_posts() ): $order_loop->the_post();
			$result_rest_response['Orders'][$i]['order_id']       = get_the_ID();
			$result_rest_response['Orders'][$i]['order_date']     = get_the_date();
			$order_details                                        = new WC_Order( get_the_ID() );
			$order_data                                           = $order_details->get_data();
			$result_rest_response['Orders'][$i]['status']         = $order_data['status'];
			$result_rest_response['Orders'][$i]['total']          = $order_data['total'];
			$result_rest_response['Orders'][$i]['payment_method'] = $order_data['payment_method_title'];
			foreach ( $order_details->get_items() as $lineItem ) {
				$result_rest_response['Orders'][$i]['line_items'][$line]['name']       = $lineItem['name'];
				$result_rest_response['Orders'][$i]['line_items'][$line]['product_id'] = $lineItem['product_id'];
				if ( $lineItem['variation_id'] ) {
					$result_rest_response['Orders'][$i]['line_items'][$line]['type'] = 'variation';
				} else {
					$result_rest_response['Orders'][$i]['line_items'][$line]['type'] = 'simple';
				}
				$line++;
			}
			$i++;
		endwhile;
		wp_reset_query();
		return $result_rest_response;

	}

	function changetitle( WP_REST_Request $request ) {
		$pid       = $request->get_param( 'ID' );
		$new_title = sanitize_text_field( $request->get_param( 'Title' ) );
		$token     = $request->get_param( 'access_token' );
		if ( TOKEN == $token ) {
			$product = array(
				'ID'         => $pid,
				'post_title' => $new_title,
			);
			$result = wp_update_post( $product );
			if ( $result ) {
				return new WP_REST_Response( __( 'The Title Was Changed!', 'testtask' ), 200 );
			} else {
				return new WP_Error( 'Error', 'Error ...', array( 'status' => 404 ) );
			}
		} else {
			return new WP_Error( 'Error', 'Access is denied!', array( 'status' => 404 ) );
		}

	}
	function register_woo_api() {
		add_menu_page(
			'Woo API',
			'Woo API',
			'manage_options',
			'abu',
			'latest_product'
		);
	}
	add_action( 'admin_menu', 'register_woo_api' );

	function latest_product() {
		$site         = site_url();
		$apiUrl       = $site . '/wp-json/wooapi/v1/products';
		$response     = wp_remote_get( $apiUrl );
		$responseBody = wp_remote_retrieve_body( $response );
		$result       = json_decode( $responseBody, true );
		$products     = $result['Products'];
		$orders       = $result['Orders'];
		?>
<div class="wrap">
    <div class="">
        <h2 class=""><?php _e( 'Products', 'testtask' );?></h2>

    </div>
    <table class="widefat fixed table" cellspacing="0">
        <thead>
            <tr>
                <th width="5%"><?php _e( 'ID', 'testtask' );?></th>
                <th width="10%"><?php _e( 'Title', 'testtask' );?></th>
                <th width="10%"><?php _e( 'Price', 'testtask' );?></th>
                <th><?php _e( 'Category', 'testtask' );?></th>
                <th><?php _e( 'Action', 'testtask' );?></th>
            </tr>
        </thead>

        <tfoot>
            <tr>
                <th><?php _e( 'ID', 'testtask' );?></th>
                <th><?php _e( 'Title', 'testtask' );?></th>
                <th><?php _e( 'Price', 'testtask' );?></th>
                <th><?php _e( 'Category', 'testtask' );?></th>
                <th><?php _e( 'Action', 'testtask' );?></th>
            </tr>
        </tfoot>

        <tbody>
            <?php
$i = 1;
		if ( $products ) {
			foreach ( $products as $product ) {

				$bg = ( $i % 2 == 0 ? '#fff' : '#f5f5f5' );
				?>
            <tr style="background-color:<?php echo $bg; ?>">
                <td class="id"><?=$product['ID'];?></td>
                <td class="title"><?=$product['title'];?></td>
                <td><?=$product['price'];?></td>
                <td><?=$product['categories'];?></td>
                <td>
                    <button class="save">Save</button>
                    <button class="edit">Edit Product Title</button>
                </td>

            </tr>
            <?php
$i++;
			}
		}
		?>

        </tbody>
    </table>
    <div class="">
        <h2 class=""><?php _e( 'Orders', 'testtask' );?></h2>

    </div>
    <table class="widefat fixed table" cellspacing="0">
        <thead>
            <tr>
                <th width="10%"><?php _e( 'Order ID', 'testtask' );?></th>
                <th width="10%"><?php _e( 'Order Date', 'testtask' );?></th>
                <th width="10%"><?php _e( 'Order Status', 'testtask' );?></th>
                <th width="10%"><?php _e( 'Total', 'testtask' );?></th>
                <th width="10%"><?php _e( 'Payment Method', 'testtask' );?></th>
                <th><?php _e( 'Items', 'testtask' );?></th>
            </tr>
        </thead>

        <tfoot>
            <tr>
                <th><?php _e( 'Order ID', 'testtask' );?></th>
                <th><?php _e( 'Order Date', 'testtask' );?></th>
                <th><?php _e( 'Order Status', 'testtask' );?></th>
                <th><?php _e( 'Total', 'testtask' );?></th>
                <th><?php _e( 'Payment Method', 'testtask' );?></th>
                <th><?php _e( 'Items', 'testtask' );?></th>
            </tr>
        </tfoot>

        <tbody>
            <?php
$i = 1;
		if ( $orders ) {

			foreach ( $orders as $order ) {

				$bg = ( $i % 2 == 0 ? '#fff' : '#f5f5f5' );
				?>
            <tr style="background-color:<?php echo $bg; ?>">
                <td><?=$order['order_id'];?></td>
                <td><?=$order['order_date'];?></td>
                <td><?=$order['status'];?></td>
                <td><?=$order['total'];?></td>
                <td><?=$order['payment_method'];?></td>
                <td>
                    <?php
$row = 1;
				foreach ( $order['line_items'] as $line ) {
					echo $row . "- Name: " . $line['name'];
					echo " | ID: " . $line['product_id'];
					echo " | Type: " . $line['type'];
					echo "<br>";
					$row++;
				}
				?>
                </td>

            </tr>
            <?php
$i++;
			}
		}

		?>

        </tbody>
    </table>
</div>
<?php

	}

} else {
	function sample_admin_notice__success() {
		?>
		<div class="update-nag mb-3" style="border-left: 4px solid #000;    background-color: #fbff00;">

    <?php _e( 'Hey Vishal , Seems WooCommerce is not active ! so for using Woo API plugin it is a MUST ;)' );?>
</div>
		<?php
}
	add_action( 'admin_notices', 'sample_admin_notice__success' );
}
