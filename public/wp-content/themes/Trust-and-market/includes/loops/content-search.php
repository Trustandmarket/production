<?php
/*
The Search Loop
===============
*/
?>
<?php tha_content_before(); ?>
<?php tha_content_top(); ?>
<?php if(have_posts()): while(have_posts()): the_post(); ?>
    <?php // all statique pages and products have a thumb ?>
    <?php if( strlen(get_the_post_thumbnail_url()) ) : ?>
    	<?php tha_entry_before(); ?>
        <article class="col-md-4" role="article" id="post_<?php the_ID()?>" <?php post_class()?>>
            <header>
            	<?php tha_entry_top(); ?>
            	<div class="simple-search-img" style="background: url('<?php echo get_the_post_thumbnail_url(); ?>');height: 200px; background-size:cover; background-position:center;"></div>
                <h4><a href="<?php the_permalink(); ?>"><?php the_title()?></a></h4>
            </header>
          <?php the_excerpt(); ?>
        </article>
        <?php tha_entry_bottom(); ?>
    <?php endif ?>
<?php endwhile; else: ?>
	<?php tha_entry_after(); ?>
    <div class="alert alert-warning">
        <i class="glyphicon glyphicon-exclamation-sign"></i> <?php _e('Sorry, your search yielded no results.', 'bst-plus'); ?>
    </div>
<?php endif; ?>