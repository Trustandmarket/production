<?php
/**
 * Order Item Details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details-item.php.
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

if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
	return;
}


$reserved_dates = wc_get_order_item_meta( $item->get_id(), '_reserved_dates', true );
//die(var_dump($reserved_dates));
$disponibilities = array_values(array_filter(explode(',', $reserved_dates)));
$product_instance = new WC_Product_Factory();
$subtotal = count($disponibilities) * $product->get_price();

$product = $product_instance->get_product($item->get_product_id());
$author_id = get_post($product->get_id())->post_author;
$user = get_userdata($author_id);
?>
<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'woocommerce-table__line-item order_item', $item, $order ) ); ?>">

	<td class="woocommerce-table__product-name product-name">
		<?php
			$is_visible        = $product && $product->is_visible();
			$product_permalink = apply_filters( 'woocommerce_order_item_permalink', $is_visible ? $product->get_permalink( $item ) : '', $item, $order );

			echo apply_filters( 'woocommerce_order_item_name', $product_permalink ? sprintf( '<h4><a href="%s" class="text-blue text-bold">%s</a>', $product_permalink, $item->get_name() ) : $item->get_name(), $item, $is_visible );
			echo apply_filters( 'woocommerce_order_item_quantity_html', ' <strong class="product-quantity">' . sprintf( '&times; %s', count($disponibilities) ) . '</strong></h4>', $item );

			do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order );
			
			//wc_display_item_meta( $item );
			//wc_display_item_downloads( $item );

			do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order );
		?>
		<ul class="wc-item-meta">
			<li>
				<strong class="wc-item-meta-label"><?php _e('Vendu par:', 'woocommerce'); ?></strong>
				<p><?php echo $user->user_firstname; ?>&nbsp;<?php echo $user->user_lastname; ?></p>
			</li>
			<li>
				<strong class="wc-item-meta-label"><?php _e('date(s) réservée(s) :', 'woocommerce'); ?></strong> 
				<p>
					<?php $pos = 0; ?> 
					<?php foreach($disponibilities as $disponibilitie) : ?> 
						<?php $pos++; ?> 
						<?php echo date_format(date_create($disponibilitie), 'd/m/Y'); ?>
						<?php if($pos < count($disponibilities)) echo ', &nbsp;'; ?>
					<?php endforeach ?> 
				</p>
			</li>
		</ul>
	</td>

	<td class="woocommerce-table__product-total product-total">
	
	<span class="woocommerce-Price-amount amount">	<?php echo $subtotal;?><span class="woocommerce-Price-currencySymbol">€</span></span>
	
	</td>

</tr>

<?php if ( $show_purchase_note && $purchase_note ) : ?>

<tr class="woocommerce-table__product-purchase-note product-purchase-note">

	<td colspan="3"><?php echo wpautop( do_shortcode( wp_kses_post( $purchase_note ) ) ); ?></td>

</tr>

<?php endif; ?>
