<?php
/*
Template Name: Page Blog
*/
?>

<?php get_template_part('includes/header'); ?>

<div class="container-fluid page-with-cover header-cover">
  <?php if ( has_post_thumbnail()) : ?>
     <div class="header-image">
      <?php the_post_thumbnail(); ?>
     </div>
   <?php endif; ?>
   <div class="container lead-title">
    <div class="page-title"><?php echo get_post_meta($post->ID, 'head_title', true); ?></div>  
   </div>
</div>
<div class="container page-with-cover">
      <div class="row">
      <div class="col-sm-12">
          <?php 
              wp_nav_menu( array(
                  'theme_location'    => 'blog_menu',
                  'depth'             => 3,
                  'menu_class'        => 'nav navbar-nav navbar-user-profile',
                  'fallback_cb'       => 'wp_bootstrap_navwalker::fallback',
                  'walker'            => new wp_bootstrap_navwalker())
              );
          
          ?>
    
      </div>
    </div><!-- /.row -->
  <div class="row">

    <div class="col-sm-12">
      <div id="content" role="main">
      	<?php tha_content_before(); ?>
        <?php get_template_part('includes/loops/content', 'page'); ?>
      </div><!-- /#content -->
    </div>
    
  </div><!-- /.row -->
</div><!-- /.container -->

<?php get_template_part('includes/footer'); ?>
