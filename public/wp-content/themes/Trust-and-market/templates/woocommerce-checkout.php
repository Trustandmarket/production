<?php
/*
Template Name: Woocommerce Checkout
*/
?>

<?php get_template_part('includes/header'); ?>
<div class="menu-background"></div>
<div class="whole-container full-width-template">
  <div class="container">
    <div class="row">
      <div class="col-sm-12">
        	<?php tha_content_before(); ?>
    </div><!-- /.row -->
    <?php get_template_part('includes/loops/content', 'page'); ?>
  </div><!-- /.container -->
</div>
<?php get_template_part('includes/footer'); ?>
