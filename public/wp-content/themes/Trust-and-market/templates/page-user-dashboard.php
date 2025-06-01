<?php
/*
Template Name: User Dashboard
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
      <!--<div class="col-md-3">
          <h3><?php _e( 'Profil', 'theme-my-login' ); ?></h3>
          <div class="tml-user-avatar img-responsive"><?php echo get_avatar( $current_user->ID, 300); ?></div>
          <h3><?php echo $current_user->user_lastname .' '. $current_user->user_firstname ?></h3>
         
          <div class="about-user">
            <p><?php echo get_user_meta( $current_user->ID, 'sexe', true ); ?></p>
            <p><?php _e('Né(e) le ', 'bst-plus'); echo get_user_meta( $current_user->ID, 'bdaytime', true ); ?></p>
            <p><?php _e('À ', 'bst-plus'); echo get_user_meta( $current_user->ID, 'lieu_naissance', true ); ?></p>
            <p><?php _e('Email : ', 'bst-plus'); echo $current_user->user_email; ?></p>
            <p><?php _e('Tél : ', 'bst-plus'); echo get_user_meta( $current_user->ID, 'telephone', true ); ?></p>
          </div>
          <div class="row">
            <div class="col-md-6 col-sm-6 text-left no-padding"> 
              <button class="btn btn-orange edit-btn" type="button" onclick="location.href='../mon-profil';">
                  <?php _e('Voir mon profil', 'bst-plus'); ?>
              </button>
            </div>
            <div class="col-md-6 col-sm-6 text-right no-padding">  <a href="../mon-profil/?edit=1" class="black-simple-link">
                  <?php _e('Modifier mon profil', 'bst-plus'); ?>
                </a>
            </div>
          </div>
      </div>-->
      <div class="col-md-9">
      <style type="text/css"> h4.vendor-annonces-title {display: none;} </style>
        <?php
          $user = wp_get_current_user();
        	$roles = $user->roles;
        	if(in_array('vendor', $roles)): ?>
          <h3 class="title-dashboard"><?php _e('Dernières annonces publiées', 'bst-plus') ?></h3>
          <?php echo do_shortcode( '[vendor-annonces statut="publish" posts_per_page="3" with_pagination="false"]' ); ?>
        <?php endif; ?>
        <h3 class="title-dashboard"><?php _e('Réservations en cours', 'bst-plus') ?></h3>
        <?php echo do_shortcode( '[client-annonces statut="reserved" posts_per_page="3" with_pagination="false"]' ); ?>
        <div class="container-fluid bottom-buttons">
          <a href="<?php echo esc_url( home_url( '/mon-profil' ) ); ?>" class="btn btn-orange btn-lg edit-btn"><?php _e('Voir mon profil','bst-plus') ?></a>
          <a href="<?php echo esc_url( home_url( '/mon-profil/annonces/' ) ); ?>" class="btn btn-default btn-lg edit-btn"><?php _e('Mes annonces','bst-plus') ?></a>
        </div>
      </div>
    </div>
  </div><!-- /.container -->
</div>

<?php get_template_part('includes/footer'); ?>