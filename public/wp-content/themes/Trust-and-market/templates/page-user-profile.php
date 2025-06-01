<?php
/*
Template Name: User profile
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
    
    <?php get_template_part('includes/loops/content', 'page'); ?>
  </div><!-- /.container -->
</div>
<?php get_template_part('includes/footer'); ?>
