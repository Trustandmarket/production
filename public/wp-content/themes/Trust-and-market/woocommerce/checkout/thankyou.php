<?php
/**
 * Thankyou page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/thankyou.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="woocommerce-order">
	<div class="row">
		<?php if ( $order ) : ?>
	
			<?php  if ( $order->has_status( 'failed' ) ) : ?>
	
				<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php _e( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce' ); ?></p>
	
				<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
					<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay"><?php _e( 'Pay', 'woocommerce' ) ?></a>
					<?php if ( is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button pay"><?php _e( 'My account', 'woocommerce' ); ?></a>
					<?php endif; ?>
				</p>
	
			<?php else : ?>
				<div class="col-md-3">
					<img class="like-big-icon" src="<?php echo site_url() . "/wp-content/uploads/2017/10/icon-like.png"; ?>"></img>
					<h2 class="text-orange"><strong><?php _e('Merci,', 'woocommerce'); ?></strong></h2>
					<h2><strong></stro><?php _e('votre commande a bien été validée.', 'woocommerce'); ?></strong></h2>
				</div>
				<div class="col-md-9">
	
					<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
						<script>
							window.dataLayer = window.dataLayer || []
							dataLayer.push({  
								'event': 'purchase',
								'transactionId': '<?php echo $order->get_order_number(); ?>',
								'transactionTotal': parseFloat('<?php echo floatval($order->get_formatted_order_total()); ?>')
							});
						</script>
						
						<li class="woocommerce-order-overview__order order">
							<?php _e( 'N° de commande', 'woocommerce' ); ?>
							<strong><?php echo $order->get_order_number(); ?></strong>
						</li>
		
						<li class="woocommerce-order-overview__date date">
							<?php _e( 'Date', 'woocommerce' ); ?>
							<strong><?php echo wc_format_datetime( $order->get_date_created() ); ?></strong>
						</li>
		
						<li class="woocommerce-order-overview__total total">
							<?php _e( 'Total', 'woocommerce' ); ?>
							<strong><?php echo $order->get_formatted_order_total(); ?></strong>
						</li>
		
						<?php if ( $order->get_payment_method_title() ) : ?>
		
						<li class="woocommerce-order-overview__payment-method method">
							<?php _e( 'Mode de paiment', 'woocommerce' ); ?>
							<strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
						</li>
		
						<?php endif; ?>
		
					</ul>
				</div>
			<?php endif; ?>
			<div class="col-md-9">
				<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
				<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>
			</div>
		<?php else : ?>
	
			<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received"><?php echo apply_filters( 'woocommerce_thankyou_order_received_text', __( 'Thank you. Your order has been received.', 'woocommerce' ), null ); ?></p>
	
		<?php endif; ?>
		<div class="col-md-5 col-md-offset-7">
			<a href="<?php echo site_url(); ?>" class="btn btn-orange edit-btn">
		    	<?php _e('Rechercher une nouvelle prestation', 'woocommerce'); ?>			
		    </a>
			<a class="black-simple-link" href="<?php echo site_url() . "/mon-profil/annonces/mes-reservations/" ?>" style="cursor: pointer;">
				<?php _e('Voir mes réservations', 'woocommerce'); ?>				
			</a>
		</div>
	</div>
</div>
