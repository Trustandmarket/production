<?php 
	$parent_categ = '';
	if(isset($_GET['parent_categ'])){
	//$query = $_GET['query'] != '' ? $_GET['query'] : '';
	$parent_categ = $_GET['parent_categ'] != '' ? $_GET['parent_categ'] : '';
	$parent_categ = sanitize_title_with_dashes($parent_categ);
	}
	$child_categ = '';
	if(isset($_GET['child_categ'])){
		$child_categ = $_GET['child_categ'] != '' ? $_GET['child_categ'] : '';
		$child_categ = sanitize_title_with_dashes($child_categ);
	}
	
	$beginOriginalDate = '';
	if(isset($_GET['date_start'])){
		$beginOriginalDate =sanitize_text_field( $_GET['date_start']);
	}
	
	$endOriginalDate = '';
	if(isset($_GET['date_end'])){
		$endOriginalDate = sanitize_text_field($_GET['date_end']);
	}
	
	$city = '';
	if(isset($_GET['city'])){
		$city = $_GET['city'];
		$city = sanitize_text_field($_GET['city']);
	}
?>
<script src="https://maps.googleapis.com/maps/api/js?v=3&amp;key=AIzaSyC-RbYn2psj7TDIhyf8mgQT-s3sIBdIghY&amp;callback=loadmap" defer></script>

<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.4/css/select2.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.4/js/select2.min.js"></script>

<div class="row advanced-searchform-div">
	<form method="get" id="advanced-searchform" class="form advanced-searchform form-inline style-for-home-site-origin" role="search" action="<?php echo esc_url( home_url( '/advanced-search' ) ); ?>">

	    <?php
	
		  $taxonomy     = 'product_cat';
		  $orderby      = 'name';  
		  $show_count   = 0;      // 1 for yes, 0 for no
		  $pad_counts   = 0;      // 1 for yes, 0 for no
		  $hierarchical = 0;      // 1 for yes, 0 for no  
		  $title        = '';  
		  $empty        = 0;
		
		  $args = array(
		         'taxonomy'     => $taxonomy,
		         'orderby'      => $orderby,
		         'show_count'   => $show_count,
		         'pad_counts'   => $pad_counts,
		         'hierarchical' => $hierarchical,
		         'title_li'     => $title,
		         'hide_empty'   => $empty
		  );
		 $all_categories = get_categories( $args );
		?>

		<div class="form-group">
			<select class="form-control" name="parent_categ" id="parent_categ" placeholder="<?php _e('Type de prestation', 'bst-plus')?>">
				<option disabled selected value=""><?php _e('Type de prestation', 'bst-plus')?></option>
				<?php foreach($all_categories as $category) : ?>


                   <?php  echo '<pre>' ; var_dump( $category->slug) ; echo '</pre>' ; ?>
					<?php if($category->category_parent  == 0) : ?>*

						<option value="<?php echo htmlspecialchars($category->slug); ?>" data-id="<?php echo $category->term_id; ?>" <?php if($parent_categ === $category->slug) echo 'selected'; ?>><?php echo $category->name; ?></option>
					<?php endif ?>
				<?php endforeach  ?>
			</select>
		</div>
		<div class="form-group">
			<select class="form-control" name="child_categ" id="child_categ" placeholder="<?php _e('Besoins', 'bst-plus')?>" disabled>
				<option disabled selected value=""><?php _e('Besoins', 'bst-plus')?></option>
			</select>
		</div>
		<div class="form-group">
			<input type="text" id="city" name="city" placeholder="<?php _e('Pays, région, ville ...','bst-plus') ?>" class="form-control " value="<?php if($city != '') echo $city; ?>"/>
			<div class="gmap-finder-input-auto-complite"/>&nbsp;</div>
			<!-- gmap-finder-input-txt -->
		</div>

		<div class="input-daterange form-group" id="datepicker">
			<div style="display:none;" class="periode-label"><h4><img src="<?php echo site_url(). "/wp-content/uploads/2017/09/ic_calendar.png" ?>"> <span><?php _e('Période', 'bst-plus') ?> </span></h4></div>
		    <input type="text" class="form-control" placeholder="<?php _e('Date de début', 'bst-plus')?>" type="text" id="date_start" name="date_start" value="<?php if($beginOriginalDate != '') echo $beginOriginalDate; ?>" />
		    <input type="text" class="form-control" placeholder="<?php _e('Date de fin', 'bst-plus')?>" type="text" id="date_end" name="date_end" value="<?php if($endOriginalDate != '') echo $endOriginalDate; ?>" />
		</div>
		
		<div class="devider"></div>
		<div class="search-form-footer">
	    	<button type="submit" class="btn btn-orange btn-lg  btn-huge edit-btn" id="advanced-search-submit" disabled/><?php _e('Rechercher','bst-plus') ?></button>
    	</div>
    	
	    <!-- PASSING THIS TO TRIGGER THE ADVANCED SEARCH RESULT PAGE FROM functions.php -->
    	<input type="hidden" name="search" value="advanced">
        <input type="hidden" value="<?php echo admin_url( "admin-ajax.php" ); ?>" id="urladmin">
        <input type="hidden" value="<?php echo $child_categ; ?>" id="selected_child_value">
	</form>
</div>

<script type="text/javascript">

	$(document).ready(function() {
		if(($('#parent_categ').val() != '' ) && ( $('#parent_categ').val() != null)){
			var parent_id =  $('#parent_categ').find(':selected').attr('data-id');
			var selected_child_value = $('#selected_child_value').val();
		 //     getChildCategories(parent_id, selected_child_value);
		}
	});
	$('#parent_categ').change(function(){
	    var parent_id =  $(this).find(':selected').attr('data-id');
	    getChildCategories(parent_id);
	});
	function getChildCategories(parent_id, selected_child_value){
		$('#child_categ').prop('disabled', true);
		var ajax_url = $("#urladmin").val(); 
		var myForm = new FormData();
	    myForm.append('parent_id', parent_id);
	    myForm.append('selected_child_value', selected_child_value);
	    myForm.append('action', 'get_product_categories');
	    $.ajax({
			type: 'POST',
			url: ajax_url,
			data: myForm,
			contentType: false,
			processData: false,
            beforeSend: function(){

                $('#advanced-search-submit').html('Chargement...');
            },

			success: function(response) {
				if(response !== 'error'){
					$('#child_categ').html(response);
					$('#child_categ').prop('disabled', false);
					$('#advanced-search-submit').prop('disabled', false);
                    $('#advanced-search-submit').html('Rechercher');
				}
				else{
					alert('Error !!');
				}
			}
		});
	}
	
	if($('.search-form-in-result-page').length){
		$('#parent_categ').trigger('change');
	}
	// $('select').select2({
	//     minimumResultsForSearch: -1
	// });
</script>
