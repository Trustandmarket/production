<?php
// Get data from URL into variables
$productsSearchQuery = null;
if(isset($_GET['parent_categ'], $_GET['child_categ'])){
	$parent_categ = $_GET['parent_categ'] != '' ? sanitize_text_field($_GET['parent_categ']) : '';
	$child_categ = $_GET['child_categ'] != '' ? sanitize_text_field($_GET['child_categ']) : '';
	$city = '';
	if(isset($_GET['city'])){
		$city = sanitize_text_field($_GET['city']);
	}
	$date_start = '';
	if(isset($_GET['date_start'])){
		$date_start = sanitize_text_field($_GET['date_start']);
	}
	$date_end = '';
	if(isset($_GET['date_end'])){
		$date_end = sanitize_text_field($_GET['date_end']);
	}

	function generateQuery($child_categ = '', $parent_categ = '', $city = '', $date_end = '', $date_start = '', $count = false){
		global $wpdb;
		$query = '';
		if($child_categ != ''){
			if($count){
				$query .= "
						SELECT COUNT(ID) FROM `".$wpdb->prefix."posts` 
							WHERE `post_status` = 'publish' 
							AND `ID` IN ( 
								SELECT `post_id` FROM `".$wpdb->prefix."postmeta` 
									WHERE (`meta_key` = '_product_child_categ' AND `meta_value` LIKE '".$child_categ."') 
							)
						";
			}else{
				$query .= "
						SELECT ID FROM `".$wpdb->prefix."posts`
							WHERE `post_status` = 'publish' 
							AND `ID` IN ( 
								SELECT DISTINCT `post_id` FROM `".$wpdb->prefix."postmeta` 
									WHERE (`meta_key` = '_product_child_categ' AND `meta_value` LIKE '".$child_categ."') 
							)
						";
			}
		}else{
			if($count){
				$query .= "
						SELECT COUNT(ID) FROM `".$wpdb->prefix."posts`
							WHERE `post_status` = 'publish' 
							AND `ID` IN ( 
								SELECT `post_id` FROM `".$wpdb->prefix."postmeta` 
									WHERE (`meta_key` = '_product_parent_categ' AND `meta_value` LIKE '".$parent_categ."') 
							)
						";
			}else{
				$query .= "
						SELECT ID FROM `".$wpdb->prefix."posts`
							WHERE `post_status` = 'publish' 
							AND `ID` IN ( 
								SELECT `post_id` FROM `".$wpdb->prefix."postmeta` 
									WHERE (`meta_key` = '_product_parent_categ' AND `meta_value` LIKE '".$parent_categ."') 
							)
						";
			}
		}
		if($city != ''){
				$query .= "
					AND `ID` IN ( 
						SELECT `post_id` FROM `".$wpdb->prefix."postmeta` 
							WHERE (`meta_key` = '_product_city' AND `meta_value` LIKE '%%".$city."%%') )
						"; 
		}
		
		if(($date_end != '') && ($date_start != '')){
			$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
	
			switch ($lang){
				case "fr":
						$begin = DateTime::createFromFormat('d/m/Y', $date_start);
						$end = DateTime::createFromFormat('d/m/Y', $date_end);
						break;
				default: 
					$begin = DateTime::createFromFormat('m/d/Y', $date_start);
					$end = DateTime::createFromFormat('m/d/Y', $date_end);
					break;
			}
			if($begin < new DateTime("now")){
				$begin = new DateTime("now");
			}
			if($end < new DateTime("now")){
				$query = "";
			}
			if($query != ""){
				$query .= "
					AND `ID` IN ( 
						SELECT `product_id` FROM `".$wpdb->prefix."ns_product_disponibilities` 
							WHERE (`date_value` BETWEEN '".$begin->format('Y-m-d')."' AND '".$end->format('Y-m-d')."') )
						"; 
			}
		}else{
			$date_end = '';
			$date_start = '';
			$query .= "
					AND `ID` IN ( 
						SELECT `product_id` FROM `".$wpdb->prefix."ns_product_disponibilities` 
							WHERE (`date_value` > CURDATE()) )
						"; 
		// $sort_by = isset($_GET['s']) ? $_GET['s'] : false;				
		//  $query .= ' ORDER BY '.$sort_by.' ';				 
		}
	
		if ($_SERVER['REMOTE_ADDR']  == '196.178.135.164')
		{
			var_dump($query);
			
		}
	

		return $query;
	}
	

	global $wpdb;
	
	$query_count =  $wpdb->get_var(generateQuery($child_categ, $parent_categ, $city, $date_end, $date_start, true));
	if($query_count != null){
			

			$per_page = 6;
			$nb_page = ceil($query_count/$per_page);
			if(isset($_GET['page']) && filter_var($_GET['page'], FILTER_SANITIZE_NUMBER_INT)  && $_GET['page'] > 0  && $_GET['page'] <= $nb_page){
				$page = filter_var($_GET['page'], FILTER_SANITIZE_NUMBER_INT);
			}else{$page = 1;}
			
			$my_query = generateQuery($child_categ, $parent_categ, $city, $date_end, $date_start). " LIMIT ".(($page-1) * $per_page).",".$per_page;
			$productsSearchQuery = $wpdb->get_results( $my_query, OBJECT );
			// die(var_dump($productsSearchQuery));
			$products = [];
			$product_instance = new WC_Product_Factory();
			if($productsSearchQuery){
				foreach ($productsSearchQuery as $db_product) {
					array_push($products, $product_instance->get_product($db_product->ID));
				}
			}
			$price_order = '';
			if(isset($_GET['price_order'])){
				$price_order = sanitize_text_field($_GET['price_order']);
				// function desc_price($a, $b)
				// {
				//     return strcmp($b->get_price(), $a->get_price());
				// }
				// function asc_price($a, $b)
				// {
				//     return strcmp($a->get_price(), $b->get_price());
				// }
				$price = array();
               foreach ($products as $row)
              {
                array_push($price, $row->get_price());
              }

				if($price_order == 'ASC'){
				array_multisort($price, SORT_ASC, $products);
				//	usort($products, "desc_price");
				}
				if($price_order == 'DESC'){
					array_multisort($price, SORT_DESC, $products);
				//	usort($products, "asc_price");
				}
				
			}
			$date_order = '';
			if(isset($_GET['date_order'])){
				$date_order = sanitize_text_field($_GET['date_order']);
					function desc_date($a, $b)
					{
					    return strtotime($b->get_date_modified()) - strtotime($a->get_date_modified());
					}
					function asc_date($a, $b)
					{
					    return strtotime($a->get_date_modified()) - strtotime($b->get_date_modified());
					}
					if($date_order == 'ASC'){
						usort($products, "desc_date");
					}
					if($date_order == 'DESC'){
						usort($products, "asc_date");
					}
			}
	}
}
?>
<?php get_template_part('includes/header'); ?>
<div class="menu-background"></div>
<div class="whole-container full-width-template">
  <div class="container">
    <div class="row">
      <div class="col-md-3 no-margin no-padding  form-side">
      	<div class="row">
      		<h4><?php _e('Filtrer par','bst-plus'); ?></h4>
      	</div>
      	<div class="search-form-in-result-page">
	      <?php echo do_shortcode('[advanced_searchform]'); ?>
      	</div>
      </div>
      <div class="col-md-9 result-side">
      	<?php if( count( $productsSearchQuery ) > 0) : ?>
	      	<div class="row">
	      		<div class="col-md-12 result-heading">
	      			<h4><?php _e('Résultat de recherche pour','bst-plus'); ?>&nbsp; “<?php if(strlen($child_categ)) echo $child_categ; else echo $parent_categ; ?>”</h4>
      				<div class="row">
      					<div class="col-md-6 result-count">
      						<h4><?php _e('Annonces','bst-plus'); ?> (<?php echo $query_count; ?>)</h4>
      					</div>
      					<div class="col-md-6 result-sort">
      						<div class="form-inline pull-right">
      							<div class="form-group">
      								<h4 class="text-blue"><?php  _e('Trier par','bst-plus'); ?>&nbsp;&nbsp;</h4>
      							</div>
			      				<div class="form-group">
									<select class="form-control" name="price_order" id="price_order" placeholder="<?php _e('Prix', 'bst-plus')?>">
										<option selected value=""><?php _e('Prix', 'bst-plus')?></option>
										<option value="ASC" <?php if($price_order == 'ASC') echo 'selected'; ?>><?php _e('Croissant', 'bst-plus')?></option>
										<option value="DESC" <?php if($price_order == 'DESC') echo 'selected'; ?>><?php _e('Décroissant', 'bst-plus')?></option>
									</select>
								</div>
								<div class="form-group">
									<select class="form-control" name="date_order" id="date_order" placeholder="<?php _e('Date d\'ajout ', 'bst-plus')?>">
										<option selected value=""><?php _e('Date d\'ajout ', 'bst-plus')?></option>
										<option value="ASC" <?php if($date_order == 'ASC') echo 'selected'; ?>><?php _e('Récent > Ancien', 'bst-plus')?></option>
										<option value="DESC" <?php if($date_order == 'DESC') echo 'selected'; ?>><?php _e('Ancien > Récent', 'bst-plus')?></option>
									</select>
								</div>
							</div>
      					</div>
      					<div class="col-md-12">
      						<hr/>
      					</div>
      				</div>
	      		</div>
		      	<?php foreach( $products as $product ): ?>
		      	<?php 
					$product_categ_str = wc_get_product_category_list($product->get_id());
					$product_categ = explode(',',$product_categ_str);
					// if($_GET['devCheck']){
						// var_dump($product_categ);die;
					// }
				?>
					  <div class="col-sm-6 col-md-4">
					    <div class="product-mini-content thumbnail">
					    	<div class="product-img" style="background: url('<?php echo wp_get_attachment_url($product->get_image_id()); ?>'); background-size:cover; background-position:center;"></div>
					      <img class="hidden" src="<?php echo wp_get_attachment_url($product->get_image_id()); ?>" alt="<?php echo $product->get_title(); ?>">
					      <div class="caption">
					        <h4 class="product-title text-gray"><?php echo $product->get_title(); ?></h4>
					        <p class="font-size-12-em text-gray"><?php echo (isset($product_categ[0])?$product_categ[0]:''); ?></p>
					        <h3 class="text-orange no-margin"><?php echo (isset($product_categ[1])?$product_categ[1]:''); ?></h3>
					        <h3 class="text-black no-margin"><?php echo $product->get_price(); ?> € <?php _e('','bst-plus') ?></h3>
					        <div class="devider"></div>
					        <h5 class="text-gray">
					        	<span class="product-icon picker"><img src="<?php echo site_url()."/wp-content/uploads/2017/09/local-picker.png"; ?>"></span>
					        	<?php echo $product->get_meta( '_product_code_postal'); echo "&nbsp"; echo $product->get_meta('_product_city'); ?>
				        	</h5>
					        <p class="font-size-12-em text-gray">
					        	<?php $disponibilities = Anonnocesdisponibility::findByProductId($product->get_id());?>
				        		<?php if(count($disponibilities) > 0) : ?>
							        <span class="product-icon calendar"><img src="<?php echo site_url()."/wp-content/uploads/2017/09/icon-date-picker.png"; ?>"></span>
					        		<?php echo date_format(date_create($disponibilities[0]->date_value), 'j M');  ?>
									<?php if(count($disponibilities) > 1) :  ?>
										&nbsp;-&nbsp;<?php  echo date_format(date_create($disponibilities[count($disponibilities) - 1]->date_value), 'j M');  ?>
										&nbsp;(<?php  echo count($disponibilities); ?>&nbsp;<?php _e('dates dispo.','bst-plus') ?>)
									<?php endif ?>
								<?php else : ?>
									<span class="product-icon calendar"><img src="<?php echo site_url()."/wp-content/uploads/2017/09/icon-date-picker.png"; ?>"></span>&nbsp; (<?php _e('0 dates dispo', 'bst-plus') ?> .)
								<?php endif ?>
							</p>
							<div class="devider"></div>
							<div class="text-center">
                          		<a href="<?php echo $product->get_permalink();  ?>" class="btn btn-orange btn-lg  btn-huge edit-btn"><?php _e('Voir l\'annonce','bst-plus') ?></a>
                          	</div>
                          	<div class="devider"></div>
                             <a href="<?php echo $product->get_permalink() ?>#section-comment" class="text-gray text-underline">	
                          		<?php echo count(get_comments(array('post_id' => $product->get_id() ))); ?>
                          		&nbsp;<?php _e('Commentaires','bst-plus') ?>
                          	</a>
					        <p class="product-footer text-gray">
	                            <?php _e('Postée le','bst-plus') ?>&nbsp;
	                            <?php echo date_i18n( get_option( 'date_format' ), strtotime($product->get_date_created()) ); ?>
	                            <?php if( $product->get_date_modified() != $product->get_date_created() ) : ?>
	                              <?php _e('mise à jour le','bst-plus') ?>&nbsp;
	                              <?php echo date_i18n( get_option( 'date_format' ), strtotime($product->get_date_modified()) ); ?>
	                            <?php endif ?>
	                          </p>
					      </div>
					    </div>
					  </div>
		      	<?php endforeach; ?>
	      	</div>
	      	<div class="row">
	      		<div class="col-md-12">
		      		<div class="center-block text-center">
		      			<?php $pagination = paginate_links( array(
								'base' => add_query_arg( 'page', '%#%' ),
						        'format' => '',
						        'prev_text' => __('&laquo;'),
						        'next_text' => __('&raquo;'),
						        'total' => $nb_page,
						        'current' => $page,
						        'type' => 'array'
							) ); ?>
						<?php if ( ! empty( $pagination ) ) : ?>
							<ul class="pagination">
								<?php foreach ( $pagination as $key => $page_link ) : ?>
									<li class="paginated_link<?php if ( strpos( $page_link, 'current' ) !== false ) { echo ' active'; } ?>"><?php echo $page_link ?></li>
								<?php endforeach ?>
							</ul>
						<?php endif ?>
		      		</div>
		      	</div>
	      	</div>
      	<?php else : 
      	$args = array(
			'numberposts' => 3,
			'offset' => 0,
			'category' => 0,
			'orderby' => 'post_date',
			'order' => 'DESC',
			'post_type' => 'product',
			'post_status' => 'publish',
			'suppress_filters' => true
);

$recent_posts = wp_get_recent_posts( $args, ARRAY_A ); 
//var_dump($recent_posts);

	      
   ?>
      		<div class="row">
	      		<div class="col-md-12 result-heading">
	      			<h4><?php _e( 'Annonces (0)', 'bst-plus' ); ?></h4>
	      			<hr>
      				<img class="float-left" src="<?php echo site_url()."/wp-content/uploads/2017/10/icon-sad.png"; ?>">
					<h3>Désolé !</h3>
					<p class="font-size-12-em">Nous n'avons trouvé aucun résultat à votre recherche</p>

  				</div>
  			</div>
  		<?php endif; ?>
      </div>
    </div>
  </div><!-- /.container -->
</div>
<style type="text/css">
	#datepicker .periode-label{
		display:block!important;
	}
</style>
<script type="text/javascript">
	$('#date_start').prop('placeholder', 'Sélectionner une date de début');
	$('#date_end').prop('placeholder', 'Sélectionner une date de fin');
</script>
<?php get_template_part('includes/footer'); ?>
