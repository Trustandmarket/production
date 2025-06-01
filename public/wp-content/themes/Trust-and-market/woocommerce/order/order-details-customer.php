<?php
/**
 * Order Customer Details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details-customer.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	https://docs.woocommerce.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<section class="woocommerce-customer-details">
	<h4><?php _e( 'Vos coordonées', 'woocommerce' ); ?></h4>
	<?php $customer = get_userdata($order->get_customer_id()); ?>
	<table class="">
		
		<?php if ( $order->get_customer_note() ) : ?>
			<tr>
				<th><?php _e( 'Note:', 'woocommerce' ); ?></th>
				<td><?php echo wptexturize( $order->get_customer_note() ); ?></td>
			</tr>
		<?php endif; ?>
		
		<?php if ( $customer ) : ?>
			<tr>
				<td><?php echo esc_html( $customer->first_name ); ?>&nbsp;<?php echo esc_html( $customer->last_name ); ?></td>
			</tr>
		<?php endif; ?>
		
		<?php if ( $order->get_billing_email() ) : ?>
			<tr>
				<td><?php _e( 'Email:', 'woocommerce' ); ?></td>
				<th><?php echo esc_html( $order->get_billing_email() ); ?></th>
			</tr>
		<?php endif; ?>

		<?php if ( $order->get_billing_phone() ) : ?>
			<tr>
				<td><?php _e( 'Phone:', 'woocommerce' ); ?></td>
				<th><?php echo esc_html( $order->get_billing_phone() ); ?></th>
			</tr>
		<?php endif; ?>

		<?php do_action( 'woocommerce_order_details_after_customer_details', $order ); ?>

	</table>

	<?php if ( ! wc_ship_to_billing_address_only() && $order->needs_shipping_address() ) : ?>

	<section class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--addresses col2-set addresses">

		<div class="woocommerce-column woocommerce-column--1 woocommerce-column--billing-address col-1">

			<?php endif; ?>

			<h4 class="woocommerce-column__title"><?php _e( 'Adresse de facturation', 'woocommerce' ); ?></h4>
			<address class="text-bold">
					<?php /* echo ( $address = $order->get_formatted_billing_address() ) ? $address : __( 'N/A', 'woocommerce' ); */ ?>
					<?php if(strlen($order->get_data()['billing']['company']) > 1) : ?> 
						<?php echo $order->get_data()['billing']['company'] ?><br>
					<?php endif ?> 
					<?php if(strlen($order->get_data()['billing']['address_1']) > 1) : ?> 
						<?php echo $order->get_data()['billing']['address_1'] ?><br>
					<?php endif ?>
					<?php if(strlen($order->get_data()['billing']['address_2']) > 1) : ?> 
						<?php echo $order->get_data()['billing']['address_2'] ?><br>
					<?php endif ?>
					<?php if(strlen($order->get_data()['billing']['postcode']) > 1) : ?> 
						<?php echo $order->get_data()['billing']['postcode'] ?> -
					<?php endif ?>
					<?php if(strlen($order->get_data()['billing']['city']) > 1) : ?> 
						<?php echo $order->get_data()['billing']['city'] ?> <br>
					<?php endif ?>
					<?php if(strlen($order->get_data()['billing']['state']) > 1) : ?> 
						<?php echo $order->get_data()['billing']['state'] ?> <br>
					<?php endif ?>
					<?php if(strlen($order->get_data()['billing']['country']) > 1) : ?> 
						<?php echo WC()->countries->countries[$order->get_data()['billing']['country']] ?>
					<?php endif ?>
			</address>

			<?php if ( ! wc_ship_to_billing_address_only() && $order->needs_shipping_address() ) : ?>

		</div><!-- /.col-1 -->

		<div class="woocommerce-column woocommerce-column--2 woocommerce-column--shipping-address col-2">

			<h3 class="woocommerce-column__title"><?php _e( 'Shipping address', 'woocommerce' ); ?></h3>

			<address>
				<?php echo ( $address = $order->get_formatted_shipping_address() ) ? $address : __( 'N/A', 'woocommerce' ); ?>
			</address>

		</div><!-- /.col-2 -->

	</section><!-- /.col2-set -->

	<?php endif; ?>

</section>
