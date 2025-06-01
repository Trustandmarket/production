
<?php get_template_part('includes/header'); ?>
<div class="menu-background"></div>
<div class="whole-container full-width-template">
  <div class="container">
    <div class="row">
      <div class="col-sm-12">
        	<div id="content" role="main">
          <?php tha_content_before(); ?>
          <h2><?php _e('Search Results for', 'bst-plus'); ?> &ldquo;<?php the_search_query(); ?>&rdquo;</h2>
          <hr/>
          <?php get_template_part('includes/loops/content', 'search'); ?>
        </div><!-- /#content -->
      </div>
    </div><!-- /.row -->
  </div><!-- /.container -->
</div>
<?php get_template_part('includes/footer'); ?>
