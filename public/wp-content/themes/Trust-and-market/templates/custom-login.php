<?php
/*
Template Name: Custom Login
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
       <h2 class="title-login"><?php echo get_post_meta($post->ID, 'category', true); ?></h2>
       <p class="subtitle-lp"><?php echo get_post_meta($post->ID, 'description', true); ?></p>
      
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
<script>
/* Password confirmation field custom script */
$(document).ready(function(){$("body").hasClass("page-id-943")&&($(".user-pass2-wrap").is(":visible")||($("#wp-submit").attr("disabled","disabled"),$('<p class="user-confirm-wrap user-pass2-wrap"><label for="user-confirm">Confirm password</label><input type="password" name="user-confirm" id="user-confirm" class="input form-control" size="20" value="" autocomplete="off"></p>').insertAfter(".user-pass1-wrap"),$('label[for="user-confirm"]').text($('.user-pass2-wrap label[for="pass2"]').text()),$("#user-confirm").on("input",function(){$("#pass1-text").val()===$("#user-confirm").val()?($("#user-confirm").hasClass("mismatch")?$("#user-confirm").removeClass("mismatch"):null,$("#wp-submit").removeAttr("disabled")):$("#user-confirm").hasClass("mismatch")?null:$("#user-confirm").addClass("mismatch")})))});
</script>
