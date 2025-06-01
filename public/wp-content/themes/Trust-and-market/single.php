<?php get_template_part('includes/header'); ?>
<!--<div class="menu-background"></div>-->
<div class="container-fluid page-with-cover header-cover">
  <?php if ( has_post_thumbnail()) : ?>
     <div class="header-image">
      <?php the_post_thumbnail(); ?>
     </div>
   <?php endif; ?>
   <div class="container lead-title">
    <div class="page-title-back"><a href="<?php echo site_url(); ?>/blog">< <?php echo get_post_meta($post->ID, 'head_back', true); ?></a></div>  
   </div>
</div>
<div class="container page-child">
  
  <div class="row">

    <div class="col-sm-12">
      <div id="content" role="main">
          <h2 class="text-orange"><?php echo $post->post_title;?></h2>
      	<?php tha_content_before(); ?>
        <?php get_template_part('includes/loops/content', 'single'); ?>
      </div><!-- /#content -->
    </div>
    
  </div><!-- /.row -->
</div><!-- /.container -->
<div class="container">
<div class="row"> 
<div class="col-md-10 col-md-offset-1 text-center">
 
 <h4> Ces articles pourraient vous intéresser</h4>   
  <hr></hr>
  <?php 
 	$args = array( 'numberposts' => '5',
 	               'post__not_in' => array( $post->ID )
 	
 	);
	$recent_posts = wp_get_recent_posts( $args );	

	if($recent_posts):
    ?>
      <div class="carousel slide" id="myCarousel">
        <div class="carousel-inner">
                         
      <?php
       $i=0;    
      foreach( $recent_posts as $key=>$recent ){
      
		echo '<li><a href="' . get_permalink($recent["ID"]) . '">' .   $recent["post_title"].'</a> </li> ';
	  $content = $recent["post_content"];

    $regex = '/src="([^"]*)"/';

   preg_match_all( $regex, $content, $matches );

  
   $matches = array_reverse($matches);
   if(!empty($matches[0])){ 
       
    $src_img=$matches[0][0];
     if($key == 0){
    ?>
   
      <div class="item active">
           <div class="col-md-4 bloc-article">
                <a class="thumbnail custom-thumb" href="<?php echo $recent['guid']; ?>"><img alt="" src="<?php echo $src_img;?>"></a>
                <h4 class="title-b-article"><a class="text-orange" href="<?php echo $recent['guid']; ?>"><?php echo $recent['post_title']; ?></a></h4>
              </div>          
 
          </div>
         <?php
	}else{
	   
	    ?>
	     <div class="item">
           <div class="col-md-4 bloc-article">
                <a class="thumbnail custom-thumb" href="<?php echo $recent['guid']; ?>"><img alt="" src="<?php echo $src_img;?>"></a>
                  <h4 class="title-b-article"><a class="text-orange" href="<?php echo $recent['guid']; ?>"><?php echo $recent['post_title']; ?></a></h4>
              </div>          
 
          </div> 
	    <?php
	}
   } 
    }
	?>

        </div>
        <a data-slide="prev" href="#myCarousel"></a>
        <a data-slide="next" href="#myCarousel" ></a>
      </div> 
    <?php
 endif;
	wp_reset_query();

	?>
    <?php
    $next_post = get_next_post();
    if (!empty( $next_post )): ?>
        <a href="<?php echo esc_url( get_permalink( $next_post->ID ) ); ?>" class="right carousel-control" >› </a>
    <?php endif; ?>
    <?php
    $prev_post = get_previous_post();
    if (!empty( $prev_post )): ?>
        <a href="<?php echo $prev_post->guid ?>"  class="left carousel-control" >‹ </a>
    <?php endif ?>
  
</div>


</div>
<hr></hr>
<?php comments_template('/includes/loops/comments.php'); ?>
</div>
<?php get_template_part('includes/footer'); ?>


<?php /*

<?php get_template_part('includes/header'); ?>

<div class="container">
  <div class="row">
    
    <div class="col-xs-12 col-sm-8">
      <div id="content" role="main">
        <?php get_template_part('includes/loops/content', 'single'); ?>
      </div><!-- /#content -->
    </div>
    
    <div class="col-xs-6 col-sm-4" id="sidebar" role="navigation">
        <?php get_template_part('includes/sidebar'); ?>
    </div>
    
  </div><!-- /.row -->
</div><!-- /.container -->

<?php get_template_part('includes/footer'); ?>

*/ ?>