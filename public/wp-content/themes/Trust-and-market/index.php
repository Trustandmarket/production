<?php get_template_part('includes/header'); ?>
<div class="container-fluid page-with-cover header-cover">
  <?php  if (is_home() && get_option('page_for_posts') ) :
    $img = wp_get_attachment_image_src(get_post_thumbnail_id(get_option('page_for_posts')),'full'); 
    $featured_image = $img[0];
 ?>
     <div class="header-image">
      <?php 
      echo '<img src="'.$featured_image.'" alt="blog" >';
      ?>
     </div>
   <?php endif; ?>
    <div class="container lead-title">
    <div class="page-title"><?php echo get_post_meta(get_option('page_for_posts'), 'head_title', true); ?></div>  
   </div>
   </div>
<div class="container page-with-cover">
    <div class="row">
      <div class="col-sm-12">
          <?php 
              wp_nav_menu( array(
                  'theme_location'    => 'blog_menu',
                  'depth'             => 3,
                  'menu_class'        => 'nav navbar-nav navbar-user-profile blog_navbar',
                  'fallback_cb'       => 'wp_bootstrap_navwalker::fallback',
                  'walker'            => new wp_bootstrap_navwalker())
              );
          
          ?>
    
      </div>
    </div><!-- /.row -->
 <div class="row">
 
<?php   
echo do_shortcode('[posts_by_category category=all]');
// do_shortcode('posts_by_category');
 ?>    
 </div>
</div>
<?php get_template_part('includes/footer'); ?>

