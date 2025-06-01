<?php
/**
 * Checkout billing information form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-billing.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 3.0.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/** @global WC_Checkout $checkout */

?>
<div class="woocommerce-billing-fields">
	<?php if ( wc_ship_to_billing_address_only() && WC()->cart->needs_shipping() ) : ?>

		<h3><?php _e( 'Billing &amp; Shipping', 'woocommerce' ); ?></h3>

	<?php else : ?>

		<h3><?php _e( 'Détail de votre facture', 'woocommerce' ); ?></h3>

	<?php endif; ?>
	
	<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>
	<?php $fields = $checkout->get_checkout_fields( 'billing' ); ?>

	<?php $created_fields = []; ?>
	<div class="form-group name-group">
		<?php array_push($created_fields, 'billing_first_name'); ?>
		<?php woocommerce_form_field( 'billing_first_name', $fields['billing_first_name'], $checkout->get_value( 'billing_first_name' ) ); ?> 
		<?php array_push($created_fields, 'billing_last_name'); ?>
		<?php woocommerce_form_field( 'billing_last_name', $fields['billing_last_name'], $checkout->get_value( 'billing_last_name' ) ); ?> 
	</div>
	<div class="form-group">
		<?php array_push($created_fields, 'billing_company'); ?>
		<?php woocommerce_form_field( 'billing_company', $fields['billing_company'], $checkout->get_value( 'billing_company' ) ); ?> 
	</div>
	<div class="form-group">
		<?php array_push($created_fields, 'billing_country'); ?>
		<?php woocommerce_form_field( 'billing_country', $fields['billing_country'], $checkout->get_value( 'billing_country' ) ); ?> 
	</div>
	<div class="form-group address-group">
		<?php array_push($created_fields, 'billing_address_1'); ?>
		<?php woocommerce_form_field( 'billing_address_1', $fields['billing_address_1'], $checkout->get_value( 'billing_address_1' ) ); ?> 
		<?php array_push($created_fields, 'billing_address_2'); ?>	
		<?php woocommerce_form_field( 'billing_address_2', $fields['billing_address_2'], $checkout->get_value( 'billing_address_2' ) ); ?> 
	</div>
	<div class="form-group city-group">
		<?php array_push($created_fields, 'billing_postcode'); ?>	
		<?php woocommerce_form_field( 'billing_postcode', $fields['billing_postcode'], $checkout->get_value( 'billing_postcode' ) ); ?> 
		<?php array_push($created_fields, 'billing_city'); ?>	
		<?php woocommerce_form_field( 'billing_city', $fields['billing_city'], $checkout->get_value( 'billing_city' ) ); ?> 
	</div>
	<div class="form-group city-group">
		<?php array_push($created_fields, 'billing_state'); ?>	
		<?php woocommerce_form_field( 'billing_state', $fields['billing_state'], $checkout->get_value( 'billing_state' ) ); ?> 
	</div>
	<div class="form-group phone-group">
		<?php array_push($created_fields, 'billing_phone'); ?>
		<?php woocommerce_form_field( 'billing_phone', $fields['billing_phone'], $checkout->get_value( 'billing_phone' ) ); ?> 
		<?php array_push($created_fields, 'billing_email'); ?>
		<?php woocommerce_form_field( 'billing_email', $fields['billing_email'], $checkout->get_value( 'billing_email' ) ); ?> 
	</div>
	
	<?php
		foreach ( $fields as $key => $field ) {
			if(! (in_array($key, $created_fields)) )
				woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
		}
	?>
	
	<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
	
	
	
	<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
		<div class="woocommerce-account-fields">
			<?php if ( ! $checkout->is_registration_required() ) : ?>
	
				<p class="form-row form-row-wide create-account">
					<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
						<input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ) ?> type="checkbox" name="createaccount" value="1" /> <span><?php _e( 'Create an account?', 'woocommerce' ); ?></span>
					</label>
				</p>
	
			<?php endif; ?>
	
			<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>
	
			<?php if ( $checkout->get_checkout_fields( 'account' ) ) : ?>
	
				<div class="create-account">
					<?php foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $field ) : ?>
						<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
					<?php endforeach; ?>
					<div class="clear"></div>
				</div>
	
			<?php endif; ?>
	
			<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>
		</div>
	<?php endif; ?>
	
</div>
