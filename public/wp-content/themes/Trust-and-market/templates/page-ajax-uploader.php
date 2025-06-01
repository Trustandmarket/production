<?php
/*
Template Name: Page Ajax uploader
*/
?>
<?php get_template_part('includes/header'); ?>


    <section class = "inner-page-wrapper">
        <section class = "container">
            <section class = "row content">
              
                    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                        <h1><?php the_title(); ?></h1>
                        <article class="entry-content">
                            <?php the_content(); ?>
                            
                                <div class = "col-md-6 upload-form">
                                    <div class= "upload-response"></div>
                                    <div class = "form-group">
                                         <label><?php __('Select Files:', 'cvf-upload'); ?></label>
                                         <input type = "file" id="files" name = "files[]" accept = "image/*" class = "files-data form-control" multiple />
                                         <!--<output id="result" />-->
                                         <div class="row content-image" id="result">
                                             <div class="col-md-4 prev-img outer">
                                                 <div class="box-inner">
                                                     <h4 class="title-inner">Ajouter</h4>
                                                     <div class="btn-upload"><img src="../wp-content/uploads/2017/09/Forme-1572.png" class="centered"/></div>
                                                 
                                                 </div>
                                             
                                             </div>
                                             <div class="col-md-4 prev-img">
                                               <div class="box-inner" style="display:none">
                                                     <h4 class="title-inner">Ajouter</h4>
                                                     <div class="btn-upload"><img src="../wp-content/uploads/2017/09/Forme-1572.png" class="centered"/></div>
                                                 </div>  
                                             </div>
                                             <div class="col-md-4 prev-img">
                                                <div class="box-inner" style="display:none">
                                                     <h4 class="title-inner">Ajouter</h4>
                                                     <div class="btn-upload"><img src="../wp-content/uploads/2017/09/Forme-1572.png" class="centered"/></div>
                                                 </div> 
                                             </div>
                                             <div class="col-md-4 prev-img">
                                                 <div class="box-inner" style="display:none">
                                                     <h4 class="title-inner">Ajouter</h4>
                                                     <div class="btn-upload"><img src="../wp-content/uploads/2017/09/Forme-1572.png" class="centered"/></div>
                                                 </div>
                                             </div>
                                             <div class="col-md-4 prev-img">
                                                <div class="box-inner" style="display:none">
                                                     <h4 class="title-inner">Ajouter</h4>
                                                     <div class="btn-upload"><img src="../wp-content/uploads/2017/09/Forme-1572.png" class="centered"/></div>
                                                 </div>
                                             </div>
                                             <div class="col-md-4 prev-img">
                                                <div class="box-inner" style="display:none">
                                                     <h4 class="title-inner">Ajouter</h4>
                                                     <div class="btn-upload"><img src="../wp-content/uploads/2017/09/Forme-1572.png" class="centered"/></div>
                                                 </div> 
                                             </div>
                                             <div class="col-md-4 prev-img">
                                               <div class="box-inner" style="display:none">
                                                     <h4 class="title-inner">Ajouter</h4>
                                                     <div class="btn-upload"><img src="../wp-content/uploads/2017/09/Forme-1572.png" class="centered"/></div>
                                                 </div>
                                             </div>
                                             <div class="col-md-4 prev-img">
                                               <div class="box-inner" style="display:none">
                                                     <h4 class="title-inner">Ajouter</h4>
                                                     <div class="btn-upload"><img src="../wp-content/uploads/2017/09/Forme-1572.png" class="centered"/></div>
                                                 </div>
                                             </div>
                                             <div class="col-md-4 prev-img">
                                               <div class="box-inner" style="display:none">
                                                     <h4 class="title-inner">Ajouter</h4>
                                                     <div class="btn-upload"><img src="../wp-content/uploads/2017/09/Forme-1572.png" class="centered"/></div>
                                                 </div>
                                             </div>
                                        </div>
                                    </div>
                                    <input type="hidden" value="<?php echo admin_url( "admin-ajax.php" ); ?>" id="urladmin">
                                </div>
                             <script type="text/javascript">
                                 /************ Save uploaded image *****************/
$(document).ready(function() {
        	
var urladmin = $("#urladmin").val();
// When the Upload button is clicked...
$('body').on('change', '.upload-form .files-data', function(e) {
	e.preventDefault;

	var fd = new FormData();
	var files_data = $('.upload-form .files-data'); // The <input type="file" /> field

	// Loop through each data and create an array file[] containing our files data.
	$.each($(files_data), function(i, obj) {
		$.each(obj.files, function(j, file) {
			fd.append('files[' + j + ']', file);

		})
	});

	// our AJAX identifier
	fd.append('action', 'cvf_upload_files');

	// uncomment this code if you do not want to associate your uploads to the current page.
	fd.append('post_id', 1547);
 
	$.ajax({
		type: 'POST',
		url: urladmin,
		data: fd,
		contentType: false,
		processData: false,
		success: function(response) {
		    
			var res = $.parseJSON(response);
      
			var div = $(".prev-img.outer");
		  	div.removeClass("outer");
		
		
		
			var elem = document.createElement("img");
			elem.setAttribute("src", res.src);
			//   console.log(picFile.result);
			elem.setAttribute("data-id", res.id);
			elem.setAttribute("width", "100%");
		    elem.setAttribute("class", "myImage");
			elem.style.maxHeight = "100%";
		
			div.append(elem);
		  
			div.append('<i onclick="deleteImage(' + res.id + ');" class="fa fa-times delete-image" aria-hidden="true" >x</i>');
			div.addClass('html_' + res.id);
		
				div.find('.box-inner').hide();
			div.find('.delete-image').show();
           

				div.next().find('.box-inner').css('display', 'block');
		//	div.next().append('<div class="box-inner"><h4 class="title-inner">Ajouter</h4><div class="btn-upload"><img src="../wp-content/uploads/2017/09/Forme-1572.png" class="centered"></div></div>');

		     div.next().addClass('outer');
			/****** end preview *********/

			// Enable button next step
		//	$('.fake-next-step-btn').prop('disabled', false);
		$(".prev-img .myImage").load(function() { // We break the cache and force the browser to check for the image again
					$(".prev-img .myImage").attr( 'src', res.src );
			  }); 
		    
		}
	});


});
});

/*************** Delete uploaded image ******************/
function deleteImage(id) {
	$(".box-inner").click(function() {
	$("#files").trigger("click");
});
	var urladmin = $("#urladmin").val();
	$.ajax({
		type: 'POST',
		url: urladmin,
		data: {
			action: 'delete_attachment',
			att_ID: id,
			post_type: 'attachment'
		},
		success: function(response) {
		    if($('.html_' + id).is(':last-child'))
{
var lastdiv=$('.prev-img.html_' + id);
lastdiv.detach();
	var div = '<div class="col-md-4 prev-img outer"><div class="box-inner" style="display:block;"><h4 class="title-inner">Ajouter</h4><div class="btn-upload"><img src="../wp-content/uploads/2017/09/Forme-1572.png" class="centered"></div></div></div>';
	$("#result").append(div);


	$(".box-inner").click(function() {
	$("#files").trigger("click");
});	
}else{
	        var next =$('.html_' + id).next();
			$('.html_' + id).detach();
			var div = '<div class="col-md-4 prev-img"><div class="box-inner" style="display:none;"><h4 class="title-inner">Ajouter</h4><div class="btn-upload"><img src="../wp-content/uploads/2017/09/Forme-1572.png" class="centered"></div></div></div>';
		 
		$(div).insertAfter(".prev-img:last");

		}}
	});

}

                             </script>                                      
                        </article>
                    </article>
                
            </section>
        </section>
    </section>


<?php get_template_part('includes/footer'); ?>