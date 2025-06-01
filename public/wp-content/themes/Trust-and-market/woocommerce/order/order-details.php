<?php
/**
 * Order details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details.php.
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

if ( ! $order = wc_get_order( $order_id ) ) {
	return;
}
$show_purchase_note    = $order->has_status( apply_filters( 'woocommerce_purchase_note_order_statuses', array( 'completed', 'processing' ) ) );
$show_customer_details = is_user_logged_in() && $order->get_user_id() === get_current_user_id();
?>

<section class="woocommerce-order-details">

	<h4 class="woocommerce-order-details__title"><?php _e( 'Détails de la commande', 'woocommerce' ); ?></h4>

	<table class="table shop_table woocommerce-checkout-review-order-table">

		<thead>
			<tr>
				<th class="woocommerce-table__product-name product-name"><?php _e( 'Prestation (détails)', 'woocommerce' ); ?></th>
				<th class="woocommerce-table__product-table product-total"><strong><?php _e( 'Total', 'woocommerce' ); ?></strong></th>
			</tr>
		</thead>

		<tbody>
			<?php
				$subtotal_totals = 0;
				foreach ( $order->get_items() as $item_id => $item ) {
					$product = apply_filters( 'woocommerce_order_item_product', $item->get_product(), $item );
					$reserved_dates = wc_get_order_item_meta( $item_id, '_reserved_dates', true );
					$disponibilities = array_values(array_filter(explode(',', $reserved_dates)));
					$subtotal_totals += count($disponibilities) * $product->get_price();
					wc_get_template( 'order/order-details-item.php', array(
						'order'			     => $order,
						'item_id'		     => $item_id,
						'item'			     => $item,
						'show_purchase_note' => $show_purchase_note,
						'purchase_note'	     => $product ? $product->get_purchase_note() : '',
						'product'	         => $product,
					) );
				}
			?>
			<?php do_action( 'woocommerce_order_items_table', $order ); ?>
		</tbody>

		<tfoot>
			<?php
				$totals = $order->get_order_item_totals();
				// die(var_dump($totals));
				preg_match('/\d+,\d+/', $totals['order_total']['value'], $order_total);
				$order_total = $order_total[0];
				preg_match('/\d+,\d+/', $totals['cart_subtotal']['value'], $cart_subtotal);
				$cart_subtotal = $cart_subtotal[0];
		
			?>
			<tr class="order-details-footer">
				<th scope="row">
					<?php _e('Sous-total', 'woocommerce'); ?>
				</th>
				<td class="woocommerce-table__product-total product-total">
					<?php echo $subtotal_totals; ?>&nbsp;<?php echo get_woocommerce_currency_symbol() ?>
				</td>
			</tr>
			<tr class="order-details-footer">
				<th scope="row text-blue">
					<span class="text-blue"><?php _e('Frais de service', 'woocommerce'); ?></span>
				</th>
				<td class="woocommerce-table__product-total product-total text-blue">
					<span class="text-blue"><?php echo (floatval($order_total) - $subtotal_totals); ?></span>&nbsp;<?php echo get_woocommerce_currency_symbol() ?>
				</td>
			</tr>
			<tr class="order-details-footer">
				<th scope="row text-blue">
					<?php _e('Mode de paiement', 'woocommerce'); ?>
				</th>
				<td class="woocommerce-table__product-total product-total">
					<?php echo $totals['payment_method']['value']; ?>
				</td>
			</tr>
			<tr class="order-details-footer">
				<th scope="row">
					<span class="text-blue"><?php _e('Prix total final', 'woocommerce'); ?></span>
				</th>
				<td class="woocommerce-table__product-total product-total text-blue">
					<?php echo $order_total; ?>&nbsp;<?php echo get_woocommerce_currency_symbol() ?>
				</td>
			</tr>
		</tfoot>

	</table>

	<?php do_action( 'woocommerce_order_details_after_order_table', $order ); ?>

	<?php if ( $show_customer_details ) : ?>
		<?php wc_get_template( 'order/order-details-customer.php', array( 'order' => $order ) ); ?>
	<?php endif; ?>

</section>
