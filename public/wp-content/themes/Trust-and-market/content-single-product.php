<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-single-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<?php
  global $product;
  $product_id=$product->get_ID();
  $disponibilities_bd = Anonnocesdisponibility::findByProductId($product_id);
//  die(var_dump($disponibilities_bd));
  $disponibilities = [];
  
  foreach($disponibilities_bd as $value){
    
      array_push($disponibilities, $value->date_value);
  }

    $disponibilities = implode(',', $disponibilities);
  
  if(count($disponibilities_bd) > 0) {
      
  $date_1= date_format(date_create($disponibilities_bd[0]->date_value), 'j M');
  
   if(count($disponibilities_bd) > 1) { 
    $date_2 =date_format(date_create($disponibilities_bd[count($disponibilities_bd) - 1]->date_value), 'j M');  
   } else{
      $date_2 = $date_1;
   }
  }else{
      $date_1=$date_2="";
  }

  if(isset($_POST['key-line'])){
   
      $param=$_POST['key-line'];
    //   var_dump($param);
    
       $disponibilities_dates = Anonnocesdisponibility::all();
       foreach($disponibilities_dates as $v){
       if($param==$v->item_cart_key){
        Anonnocesdisponibility::update(array('reserved_by' => '0', 'reservation_date' => null, 'item_cart_key' => null), array('ID' => $v->ID));
       }
       }
     
       $edates = Anonnocesdisponibility::findDateByKey($param);
       
        foreach ($edates as $value) {
        $v = $value->date_value;
        ?>
        <script type="text/javascript">
      

        </script>
        <?php
        }
}
?>

<div class="whole-container full-width-template">
<div class="container">
<div class="row">
<?php do_action('woocommerce_before_single_product'); ?>
<div class="col-md-4">

<h3 class="green"><?php echo $product->get_price(); ?> € </h3>

<hr></hr>
<div class="tab-cal">
<img class="icon-cal" src="../../wp-content/uploads/2017/09/ic_calendar.png" alt="icon-calendar" />
<h4 class="title-cal"> Disponibilités</h4>
</div>
<p class="gray">Sélectionner une ou plusieurs dates parmi celles proposées par le prestataire. </p>
<div id="calendar"></div>
<input type="hidden" value="<?php echo $disponibilities ?>" id="string_dates"/>

<div class="col-md-12">
    <input type="hidden" value="<?php echo ($product->get_price()) ?>" id="price" />
<table cellspacing="0" class="total-table">
		<tbody>
		<tr>
		<th id="detail-prix"></th>
		<td id="prix"></td>
		</tr>
		<tr>
		<th id="title-frais"></th>
		<td id="frais"></td>
		</tr>
		<tr>
		<th id="title-total"></th>
		<td id="total"></td>
		</tr>
        </tbody>
</table>
</div>

<?php
global $product;
$attachment_ids = $product->get_gallery_image_ids();

$product_categ_str = wc_get_product_category_list($product->get_id());
$product_categ = explode(',',$product_categ_str);
?>

<div id="product-<?php the_ID(); ?>" class="col-md-12 custom-single-product text-center">

<?php do_action( 'woocommerce_single_product_summary' ); ?>

</div>


</div>
<?php
$array_dates_disponibles = get_post_meta( $product->get_ID(), '_product_disponibilities', true );
$size = sizeof($array_dates_disponibles). " dates dispo.";
$country_code= get_post_meta( $product->get_id(), '_product_country', true );
?>
<div class="col-md-8 col-sm-8">
        <div id="main_area">
                        <!-- Top part of the slider -->
                        <div class="row">
                            <div class="col-sm-12" id="carousel-bounding-box">
                              <h2><?php echo $product->get_title(); ?></h2>
                              <hr></hr>
                              <p class="bold">Caractéristiques</p>
                              <div class="characteristics-product">
                              <div class="col-md-6 text-left">
                               <div class="gray"> <?php _e('Ref : ', 'bst-plus'); echo $product->get_id(); ?></div>
                                <h4><?php echo (isset($product_categ[0])?$product_categ[0]:''); ?></h4>
                                <h4 class="orange"><?php echo (isset($product_categ[1])?$product_categ[1]:''); ?></h4>
                               <img src="../../wp-content/uploads/2017/09/local.png" alt=""> <?php  echo get_post_meta( $product->get_id(), '_product_code_postal', true ); echo "&nbsp"; echo get_post_meta($product->get_id(), '_product_city', true ); echo ",&nbsp"; echo WC()->countries->countries[$country_code];?><br>
                               <img src="../../wp-content/uploads/2017/09/s-cal.png" alt=""> <?php echo $date_1; ?> - <?php echo $date_2; ?>(<?php echo count($disponibilities_bd) . " dates dispo.";?>)
                              </div>
                               <div class="col-md-6 gray text-right">
                              <?php
                                   
                                 $author_id  = get_the_author_meta( 'ID' );
                                $author =  new WP_User( $author_id );
                                      
                               ?>
                               <span class="avatar-pr">
                              <?php
                               if ( ( $author instanceof WP_User) ) {
                             
                               echo get_avatar( $author->user_email, 48 ); 
              
                                 }
                                
                                ?>
                             </span><br>
                             <span class="name-avatar-comment"></span>
                            <?php 
                           
                            //  echo get_user_meta($author_id, 'last_name', true );
                            //  echo "&nbsp";
                             echo get_user_meta($author_id, 'first_name', true );
                            ?>
                            </span>
                            <br>
                             <?php _e('Postée le','bst-plus') ?>&nbsp;
                            <?php echo date_i18n( get_option( 'date_format' ), strtotime($product->get_date_created()) ); ?>
                            <?php if( $product->get_date_modified() != $product->get_date_created() ) : ?>
                              <?php _e('mise à jour le','bst-plus') ?>&nbsp;
                              <?php echo date_i18n( get_option( 'date_format' ), strtotime($product->get_date_modified()) ); ?>
                            <?php endif ?><br>
                             <img src="../../wp-content/uploads/2017/09/comment.png" alt="icon-comment" /> commentaires
                            </div>
                            <div style="clear: both;"></div>
                           
                              </div>
                                <div class="carousel slide" id="myCarousel">
                                    <!-- Carousel items -->
                                    <div class="carousel-inner">
                                        <div class="active item" data-slide-number="0"  style="background: url('<?php echo wp_get_attachment_url($product->get_image_id()); ?>'); background-size:cover; background-position:center;background-repeat:no-repeat;">
                                        <img src=""></div>
                             <?php $i=1; foreach( $attachment_ids as $attachment_id ) {   ?>
                                        <div class="item" data-slide-number="<?php echo $i; ?>" style="background: url('<?php echo wp_get_attachment_url( $attachment_id ); ?>'); background-size:cover; background-position:center;background-repeat:no-repeat;">
                                        <img src=""></div>
                               <?php $i++; } ?>
                                    
                                    </div><!-- Carousel nav -->
                                    <!--<a class="left carousel-control" href="#myCarousel" role="button" data-slide="prev">-->
                                    <!--    <span class="glyphicon glyphicon-chevron-left"></span>                                       -->
                                    <!--</a>-->
                                    <!--<a class="right carousel-control" href="#myCarousel" role="button" data-slide="next">-->
                                    <!--    <span class="glyphicon glyphicon-chevron-right"></span>                                       -->
                                    <!--</a>                                -->
                                    </div>
                            </div>

                        </div>
    
<?php $j=1; ?>
                <div class="row hidden-xs" id="slider-thumbs">
                        <!-- Bottom switcher of slider -->
                        <ul class="hide-bullets">
                            <li class="col-sm-2">
                                <?php //var_dump(wp_get_attachment_url($product->get_image_id())); ?>
                                <a class="thumbnail" id="carousel-selector-0" style="background: url('<?php echo wp_get_attachment_url($product->get_image_id()); ?>');background-size:cover;background-position:center;background-repeat:no-repeat;"><img src=""></a>
                            </li>
   <?php foreach( $attachment_ids as $attachment_id ) {  ?>
                            <li class="col-sm-2">
                                <a class="thumbnail" id="carousel-selector-<?php echo $j ?>" style="background: url('<?php echo wp_get_attachment_url( $attachment_id ); ?>');background-size:cover;background-position:center;background-repeat:no-repeat;"><img src=""></a>
                            </li>
   <?php $j++; } ?>
                           
                        </ul>                 
                </div>
        </div>
        <div class="detail-produit">
            <h3>Description de la prestation</h3>
            <hr></hr>
           <?php 
              if($_SERVER['REMOTE_ADDR']=='196.178.185.126') {
          //      echo wpautop( wptexturize( $product->get_description() ) );
          
    //exit ; 
          }

           echo wpautop( wptexturize( $product->get_description() ) ); ?>
            <h3>Références</h3>
            <hr></hr>
          <?php echo $product->get_attribute('reference'); ?>
            <h3>Équipements mis à disposition</h3>
            <hr></hr>
          <?php if(get_post_meta( $product->get_ID(), '_product_has_equipments_wifi', true ) == 1){
              echo "<p class='orange'>- WI-FI</p>";
          }
          if(get_post_meta( $product->get_ID(), '_product_has_equipments_cofe', true ) == 1){
              echo "<p class='orange'>- Café, Thé</p>";
          }
            if(get_post_meta( $product->get_ID(), '_product_has_equipments_bureau', true ) == 1){
              echo "<p class='orange'>- Bureau / Espace de travail</p>";
          }
          ?>
         <p class='orange'>- <?php echo get_post_meta( $product->get_ID(), '_product_other_equipments', true ); ?></p>
 
          <h3>Processus et garantie d’exécution</h3>
          <hr></hr>
          <?php 
            echo wpautop( wptexturize( $product->get_attribute('garantie') ) );

       


           ?>
            <h3>Vidéos</h3>
            <hr></hr>
            <?php 
            $videos= explode(',',$product->get_attribute('videos'));
			if(count($videos) > 3) $videos= array_slice($videos,0,3);
			
			foreach($videos as $video){
				if($video != "" && ( strpos($video,"www.youtube.com") != false )){?>
					
					<?php $video = str_replace("watch?v=", "embed/", $video);
					if( strpos($video,"&") != false ) $video = substr($video,0,strpos($video,"&"));
					$headers = get_headers($video); 
					if(strpos($headers[0],'404') != false) $video =""?>

					<iframe class="embed-responsive-item" width="245" height="225" src="<?php echo $video ?>" <?php echo $video ?> frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>	

            <?php
       			}
            }
            echo '<br>';
            ?>
         <?php $my_var = get_comments_number( $product->get_ID() ); ?>
            <h3 id="section-comment"><?php echo $my_var; ?><?php _e(' Commentaires', 'woocommerce'); ?></h3>
            <hr></hr>
            <?php $args_comments = array(
            'parent'=>'0',
        	'post_id' => $product->get_ID(),

        );
 $comments=get_comments( $args_comments ); 
// var_dump($comments);
 if($my_var > 0){
 foreach($comments as $comment){
 ?>
 	  <div class="col-md-12">
	   
        <div class="col-md-1">
            
            <?php
            $comment_author= new WP_User($comment->user_id);
              if (($comment_author instanceof WP_User)){
                     echo get_avatar( $comment->comment_author_email, 48 ); 
                     }
            ?>
                          
        </div>
          <div class="col-md-11">
             <span class="name-avatar-comment">
            <?php 
            echo get_user_meta($comment->user_id, 'last_name', true );
            echo "&nbsp";
            echo get_user_meta($comment->user_id, 'first_name', true );
            ?>
            </span>  
    
         <div class="comment-content">
    <?php
	echo  $comment->comment_content . "<br>" ; 
	echo  date_format(date_create($comment->comment_date), 'd M Y \à  h\hi');
	 if(!is_user_logged_in()){ 
	     echo 'Connectez vous pour répondre.';
	 }else{
	echo '<a class="link-comment" target="'.$comment->comment_ID.'" >Répondre</a>';
	 }
//var_dump($comment)
	?>
    <form action="<?php echo get_option('siteurl'); ?>/wp-comments-post.php" method="post" id="form<?php echo $comment->comment_ID; ?>" class="single-form-comment">
      <textarea name="comment" class="form-control" id="comment" placeholder="<?php _e('Votre commentaire', 'bst-plus'); ?>" rows="8" aria-required="true"></textarea>
    <p><input type="hidden" name="comment_parent" id="comment_parent" value="<?php echo $comment->comment_ID; ?>"><input type="hidden" name="comment_type" id="comment_type" value="product"> <input type='hidden' name='comment_post_ID' value="<?php echo $product->get_ID(); ?>" id='comment_post_ID' />
    <input name="submit" class="btn btn-default" type="submit" id="submit" value="<?php _e('Envoyer Commentaire', 'bst-plus'); ?>"></p>
    <?php //comment_id_fields(); ?>
    <?php //do_action('comment_form', $product->get_ID()); 
   
    ?>
  
  </form>
	</div>
	
		     <!-- First Childreen --->
	   <div class="col-md-12">
	      <?php
	    $child_comments=get_comments(array('parent'=> $comment->comment_ID));
	    foreach($child_comments as $ccomment){
	       $cauthor=new WP_User($ccomment->user_id);
	      ?>
	       <div class="col-md-1">
            <?php
              if ( ( $cauthor instanceof WP_User) ) {
                     echo get_avatar( $cauthor->user_email, 48 ); 
                   }
              ?>
             </div>
            <div class="col-md-11">
            <span class="name-avatar-comment">
                            <?php 
                           
                             echo get_user_meta($cauthor->ID, 'last_name', true );
                             echo "&nbsp";
                             echo get_user_meta($cauthor->ID, 'first_name', true );
                            ?>
            </span>  
      
        <div class="comment-content">
        <?php
    	echo  $ccomment->comment_content . "<br>" ; 
    	echo  date_format(date_create($ccomment->comment_date), 'd M Y \à  h\hi');
        ?>
	     </div>
	
	   
       </div>
	      
	      <?php
	      }
	      ?>
	   </div>
	
	
	
	  </div>
	  </div>
	  <?php
    // var_dump($product->get_ID());
 
 }
}else{
   echo "Soyez le premier à commenter cette annonce";
}
 ?>
            <h3 id="section-comment"><?php _e('Ajouter un commentaire', 'woocommerce'); ?></h3>
            <hr></hr>
           <?php if(!is_user_logged_in()){ ?>
            <div class="bloc-comment"><?php _e('Vous devez être ', 'woocommerce'); ?><a class="orange" href="<?php echo site_url()."/login"; ?>"><?php _e('connecté', 'woocommerce'); ?></a><?php _e(' pour poster un commentaire.', 'woocommerce'); ?></div>
           <?php }else{ ?>
           <div><span class="btn-plus">+</span></div>

     <form action="<?php echo get_option('siteurl'); ?>/wp-comments-post.php" method="post" class="comment-form-add" style="display: none">
      <textarea name="comment" class="form-control" id="comment" placeholder="<?php _e('Votre commentaire', 'bst-plus'); ?>" rows="8" aria-required="true"></textarea>
    <p><input name="submit" class="btn btn-default btn-comment" type="submit" id="submit" value="<?php _e('Envoyer', 'bst-plus'); ?>"></p>
    <?php comment_id_fields(); ?>
    <?php do_action('comment_form', $product->get_ID()); ?>
  
  </form>
           <?php } ?>
       </div> 

</div>
  
</div>
  </div><!-- /.row -->
</div><!-- /.container -->









