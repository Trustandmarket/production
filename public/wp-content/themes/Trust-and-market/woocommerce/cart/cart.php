<?php
/**
 * Cart Page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart.php.
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
 * @version 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wc_print_notices();

do_action( 'woocommerce_before_cart' ); ?>

<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
	<?php do_action( 'woocommerce_before_cart_table' ); ?>
   <div class="col-md-8 text-left"> <h2 class="title-cart">Votre panier</h2></div>
   <div class="col-md-4 notice text-right">
   <div class="img-notice"><img src="../wp-content/uploads/2017/10/timer-icon.png" alt="" ></div>
   <div class="sub-notice">Afin de permettre une bonne gestion des annonces et dans le respect des autres utilisateurs, chaque résevation ajoutée à votre panier est conservée pour une durée de <strong>30 min</strong></div></div>
   <hr></hr>
	<table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0">

		<thead>
			<tr class="table-cart">
				<!--<th class="product-remove">&nbsp;</th>-->
				<!--<th class="product-thumbnail">&nbsp;</th>-->
				<th class="product-name" colspan="2"><?php _e( 'Annonces', 'woocommerce' ); ?></th>
				<th class="product-price"><?php _e( 'Prix/u', 'woocommerce' ); ?></th>
				<th class="product-quantity"><?php _e( 'Qté - Dates', 'woocommerce' ); ?></th>
				<th class="product-subtotal"><?php _e( 'Total', 'woocommerce' ); ?></th>
			</tr>
		</thead>
				
		<tbody>
			<?php do_action( 'woocommerce_before_cart_contents' ); ?>

			<?php
	
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
             
				$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
               
				if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
					$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
					$expire_time=$cart_item['expire_at'] - time();
			 
					if(time() < $cart_item['expire_at']){
						?>
			        <tr colspan="2"><td class="ref"><?php _e('Ref :', 'bst-plus'); 
				     echo $_product->get_ID();?> </td>
				    </tr>
					<tr class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
						<td class="product-thumbnail">
						<?php
								$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );

								if ( ! $product_permalink ) {
									echo $thumbnail;
								} else {
									printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail );
								}
							?>
						</td>

						<td class="product-name">
							<!--<div class="product-thumbnail">-->
								
							<!--</div>-->
						    <div class="product-description">
						    <div class="name-product"><?php
							  echo apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . '&nbsp;';
							

								// Meta data
								// echo WC()->cart->get_item_data( $cart_item );
                               ?></div><div class="parent-cat"> <?php echo get_term_by('id', $_product->get_category_ids()[0], 'category')->name; ?></div>
                                <div class="child-cat orange"> <?php echo get_term_by('id', $_product->get_category_ids()[1], 'category')->name; ?></div>
                                <img class="icon-local-cart" src="../wp-content/uploads/2017/09/local.png" alt=""> <?php echo get_post_meta($product_id, '_product_adress', true );echo "&nbsp"; echo get_post_meta( $product_id, '_product_code_postal', true ); echo "&nbsp"; echo get_post_meta( $product_id, '_product_city', true );
								// Backorder notification
								if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
									echo '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>';
								}
							?>
						</td>
						<td class="product-price">
							
						       <span class="orange semi-bold"><?php echo get_post_meta($cart_item['product_id'],'_sale_price', true) . "€"?> </span> <br>
						</td>

						<td class="product-quantity">
							<?php
				        // var_dump($cart_item);
							$array_dates = explode(',' ,$cart_item['reserved_dates']);
							$count=0;
							$new_array=[];
							foreach($array_dates as $date){
							
								array_push($new_array,date_format(date_create($date), 'd/m/Y'));
								
								$count ++;
							}
							$string_dates=implode(",",$new_array);
							
					?>	<span class="orange semi-bold"><?php	echo "X" .$count. ": <br>";?></span><?php
							//$string_dates=$cart_item['reserved_dates'];
						
							$new_dates= str_replace(",", " | ", $string_dates);
						
						    echo $new_dates;
						//	 echo WC()->cart->get_item_data( $cart_item );
							?>
						</td>

						<td class="product-subtotal" >
							<?php
						// var_dump($cart_item);
								// echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->price_without_fee( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key );
								 echo  $cart_item['data']->price_without_fee . " " . get_woocommerce_currency_symbol() ;
							?>
						</td>
							
					</tr>
			
								<tr>
			<td colspan="6" class="actions">
				 <input id="key_cart" value="<?php echo $cart_item_key; ?>" type="hidden">
			         <input id="url-product" value="<?php echo esc_url( $product_permalink ); ?>" type="hidden">
			    
			   
	          	    <a href="#" name="submit-key" data-url="<?php echo esc_url( $product_permalink ); ?>" data-k="<?php echo $cart_item_key; ?>" data-id="<?php echo $cart_item['unique_key']; ?>" class="edit-cart-line orange cart-href"><?php _e('Modifier', 'woocommerce') ?></a><span class="orange"> | </span> 
					<a class="orange cart-href" data-toggle="modal" data-target="#delete-modal-<?php echo $cart_item_key ?>"><?php _e('Supprimer', 'woocommerce') ?></a>
		
					<?php do_action( 'woocommerce_cart_actions' ); ?>

					<?php wp_nonce_field( 'woocommerce-cart' ); ?>

				</td>
	</tr>	
				
					<?php
					}else{
						 WC()->cart->remove_cart_item($cart_item_key);
					}
				}
			?>	
									         			<!-- Modal -->
	
     <div class="modal fade delete-modal" id="delete-modal-<?php echo $cart_item_key ?>" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
       <div class="modal-content">
        <div class="modal-body col-md-8 col-md-offset-2 text-center">
       <img src="../wp-content/uploads/2017/10/icon-info.png" alt="info-modal">
        <h4> <?php _e('Êtes vous sûr de vouloir supprimer cette réservation?', 'woocommerce') ?></h4>
       <?php _e('Cette réservation sera définitivement supprimée de votre panier.', 'woocommerce') ?>
        </div>
      
      <div class="modal-footer col-md-8 col-md-offset-2">
        <button type="button" class="btn btn-lg btn-dismiss bg-gray" data-dismiss="modal">Annuler</button>
       
        <a type="button" href="<?php echo esc_url( WC()->cart->get_remove_url( $cart_item_key ) );?>" class="btn btn-orange btn-lg edit-btn" id="remove-item" >OK</a>
      </div>
    </div>
  </div>
</div>	
		
			<?php	
			
			}
			
			?>

			<?php do_action( 'woocommerce_cart_contents' ); ?>

		

			<?php do_action( 'woocommerce_after_cart_contents' ); ?>

			<!-- Modal time depassed-->
<div class="modal fade" id="time-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body col-md-8 col-md-offset-2 text-center">
       <img src="../wp-content/uploads/2017/10/icon-time.png" alt="time-modal">
        <h4> <?php _e('Délai dépassé', 'woocommerce') ?></h4>
       <?php _e('Les réservations ajoutées à votre panier sont retirées automatiquement après ', 'woocommerce') ?>
       <strong><?php _e('30 min sans action de votre part.','woocommerce') ?></strong>
        </div>
      
      <div class="modal-footer col-md-8 col-md-offset-2">
        <button type="button" class="btn btn-lg btn-dismiss bg-gray" data-dismiss="modal">Annuler</button>
        <a type="button" href="<?php echo esc_url( wc_get_cart_url()) ;?>" class="btn btn-orange btn-lg edit-btn" id="remove-item" >OK</a>
      </div>
    </div>
  </div>
</div>

		</tbody>
	</table>
	<?php do_action( 'woocommerce_after_cart_table' ); ?>
</form>
	
<div class="row">
<div class="col-md-6 col-md-offset-6">
	
<div class="cart-collaterals">
	<?php
		/**
		 * woocommerce_cart_collaterals hook.
		 *
		 * @hooked woocommerce_cross_sell_display
		 * @hooked woocommerce_cart_totals - 10
		 */
		 	 if( is_cart() && WC()->cart->cart_contents_count != 0){
	 	     do_action( 'woocommerce_cart_collaterals' );
			}
	?>
</div>
</div>
</div>
<?php do_action( 'woocommerce_after_cart' ); ?>

   <script type="text/javascript">

$(document).ready(function() {
	 //var url = $("#url-product").val();
	 $('.edit-cart-line').click(function(e) {
	 	e.preventDefault();
	 	var key_cart=$(this).attr('data-k');
	 	var url=$(this).attr('data-url');
		var data ={
				action: 'edit_line_cart',
				param:   key_cart
			};


	   $.post(ajaxurl, data,function(response) {
	   	   console.log(response);
	   	   var form = document.createElement("form");
           form.setAttribute("method", "POST");
           form.setAttribute("action", url);

           var hiddenField = document.createElement("input");
            hiddenField.setAttribute("type", "hidden");
            hiddenField.setAttribute("name", "key-line");
            hiddenField.setAttribute("value", $(this).attr('data-id'));
 
            form.appendChild(hiddenField);
            document.body.appendChild(form);
            form.submit();
        });

	 });
	 /************ modal timing **********/
	 var expire_time= "<?php echo $expire_time; ?>";

    setTimeout(function() {
      $('#time-modal').modal('show');
    }, expire_time * 1000); // milliseconds
	 
	 });
    </script>
