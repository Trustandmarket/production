<?php
/*
Template Name: Custom Register
*/
?>
<div class="hidden">
<?php get_template_part('includes/header'); ?>
</div>


<div class="container-fluid no-margin no-padding backg-login"
     <?php if ( has_post_thumbnail()) : ?>
        style="background: url(<?php the_post_thumbnail_url(); ?>)"
     <?php endif; ?>
   >
  <div class="row">
    
    <div class="col-md-4 col-md-offset-4 col-xs-8 col-xs-offset-2 col-sm-8 col-sm-offset-2 text-center">
     
      <img class="custom-navbar-logo" src="<?php echo  get_theme_mod( 'gootheme_logo' )  ?>">
       <h2 class="title-login">Inscription</h2>
      <div id="content" role="main">
        <?php tha_content_before(); ?>
        <?php get_template_part('includes/loops/content', 'page'); ?>
      
      </div><!-- /#content -->
    </div>
  
    
  </div><!-- /.row -->
</div><!-- /.container -->
<div class="hidden">
<?php get_template_part('includes/footer'); ?>
</div>