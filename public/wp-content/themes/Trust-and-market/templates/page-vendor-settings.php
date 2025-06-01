<?php
/*
Template Name: Vendor settings
*/
?>

<?php get_template_part('includes/header'); ?>
<div class="menu-background"></div>
<div class="whole-container full-width-template">
  <div class="container">
    <div class="row">
      <div class="col-sm-12">
        	<?php tha_content_before(); ?>
        	<?php
              wp_nav_menu( array(
                  'theme_location'    => 'navbar-user-profile',
                  'depth'             => 3,
                  'menu_class'        => 'nav navbar-nav navbar-user-profile',
                  'fallback_cb'       => 'wp_bootstrap_navwalker::fallback',
                  'walker'            => new wp_bootstrap_navwalker())
              );
          ?>
      </div>
    </div><!-- /.row -->
    
    <div class="row">
    	<div class="col-md-3">
    		<h4><?php _e('Vous êtes fournisseur de service', 'bst-plus') ?></h4>
    		<p><?php _e('Avant de poster une annonce sur Trust & Market, il faut obligatoirement renseigner vos coordonnées de fournisseur. Cela nous permettra de vous verser les sommes correspondantes à vos réservations de service.', 'bst-plus') ?></p>
    	</div>
    	<div class="col-md-9">
    	  <?php $user = wp_get_current_user();
		        	$roles = $user->roles;
		        	if(in_array('vendor', $roles)){
		        	  echo do_shortcode('[wcv_shop_settings paypal_address="false" shop_description="false"]');
		        	}else{
		        	   echo do_shortcode('[set-vendor-role]');
		        	}
  			?>
      </div>
    </div>

    <?php /* get_template_part('includes/loops/content', 'page'); */ ?>
    
  </div><!-- /.container -->
</div>
</div>
<?php get_template_part('includes/footer'); ?>
