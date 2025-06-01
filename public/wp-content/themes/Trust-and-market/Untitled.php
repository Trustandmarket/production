<?php 
	global $woocommerce;
 ?>

<?php if ( WCV_Vendors::is_pending( get_current_user_id() ) ) { ?>

	<p><?php _e( 'Your account has not yet been approved to become a vendor.  When it is, you will receive an email telling you that your account is approved!', 'wcvendors' ); ?></p>

<?php } else { ?>

	<p><?php _e( 'Your account is not setup as a vendor.', 'wcvendors' ); ?></p>

	<?php if ( WC_Vendors::$pv_options->get_option( 'show_vendor_registration' ) ) { ?>
		<?php 
			$error = ''; $error_in = '';
			$countries_obj = new WC_Countries();
			$countries = $countries_obj->__get('countries');
			$countries_keys = [];
			$customer_id = get_current_user_id();
			
			foreach ($countries as $key => $country){
				array_push($countries_keys, $key);
			}
			if( isset($_REQUEST['set-vendor-role']) == 'set-vendor-role' ) {
				
				if ( isset( $_POST['user_mp_status'] ) ) {
					$user_mp_status = $_POST['user_mp_status'];
		            // New custom user meta field
		            if($user_mp_status == "individual" || $user_mp_status == "business"){
		            	update_user_meta( $customer_id, 'user_mp_status', sanitize_text_field( $user_mp_status ) );
		            }else{
		            	$error = __("Erreur Statut", 'bst-plus'); $error_in = 'user_mp_status';
		            }
		        }else{
		        	$error = __("Erreur required Statut", 'bst-plus'); $error_in = 'user_mp_status';
		        }
				
				if ( isset( $_POST['user_business_type'] ) ) {
					$user_business_type = $_POST['user_business_type'];
		            // New custom user meta field
		            if($user_business_type == "organisation" || $user_business_type == "business" || $user_business_type == "soletrader"){
		            	update_user_meta( $customer_id, 'user_business_type', sanitize_text_field( $user_business_type ) );
		            }else{
		            	$error = __("Erreur type", 'bst-plus'); $error_in = 'user_business_type';
		            }
		        }
		        
		        if ( isset( $_POST['user_nationality'] ) ) {
					$user_nationality = $_POST['user_nationality'];
		            // New custom user meta field
		            if(in_array($user_nationality, $countries_keys)){
		            	update_user_meta( $customer_id, 'user_nationality', sanitize_text_field( $user_nationality ) );
		            }else{
		            	$error = __("Nationalité obligatoire", 'bst-plus'); $error_in = 'user_nationality';
		            }
		        }else{
		        	$error = __("Nationalité obligatoire", 'bst-plus'); $error_in = 'user_nationality';
		        }
		        
		        if ( isset( $_POST['billing_country'] ) ) {
					$billing_country = $_POST['billing_country'];
		            // New custom user meta field
		            if(in_array($billing_country, $countries_keys)){
		            	update_user_meta( $customer_id, 'billing_country', sanitize_text_field( $billing_country ) );
		            }else{
		            	$error = __("Pays obligatoire", 'bst-plus'); $error_in = 'billing_country';
		            }
		        }else{
		        	$error = __("Pays obligatoire", 'bst-plus'); $error_in = 'billing_country';
		        }
		        if($error == '' && $error_in == '' ){
		        	$user = wp_get_current_user();
		        	$roles = $user->roles;
		        	if(in_array('administrator', $roles))
		    			$user->set_role('vendor');;
		        }else{
		        	$error = __("Erreur : role = adminstrateur", 'bst-plus'); $error_in = 'billing_country';
		        }
			}
		?>
		<form method="POST" action="">
			 <div class="alert-danger"><h3><?php echo $error; ?></h3></div>
			<div class="form-group">
				<label for="vendor_stat">Statut de l'utilisateur</label>
				<select name="user_mp_status" id="vendor_stat">
                    <option value="individual"><?php _e('Particulier', 'bst-plus') ?></option>
                    <option value="business"><?php _e('Professionnel', 'bst-plus') ?></option>
				</select>
			</div>
			<div class="form-group" id="div_vendor_type" style="display:none;">
				<label for="vendor_type">Type d'organisation</label>
				<select name="user_business_type" id="vendor_type">
                    <option value="organisation"><?php _e('Organisation', 'bst-plus') ?></option>
                    <option value="business"><?php _e('Société', 'bst-plus') ?></option>
                    <option value="soletrader"><?php _e('Auto-entrepreneur', 'bst-plus') ?></option>
				</select>
			</div>
			<div class="form-group">
        		<?php 
		        	if( $wp_user_id = get_current_user_id() ){
			            $cur_value = get_user_meta( $wp_user_id, 'user_nationality', true );
			        }
		        ?>
		        <?php $countries_obj = new WC_Countries(); ?>
        		<?php $countries = $countries_obj->__get('countries'); ?>
        		<label for="reg_user_nationality"><?php _e( 'Nationality', 'mangopay' ); ?> <span class="required">*</span></label>
	            <select class="nationality_select" name="user_nationality" id="reg_user_nationality">
	                <option value=""><?php _e( 'Select a country...', 'mangopay' ); ?></option>
		            <?php foreach ($countries as $key => $value): $selected=($key==$cur_value?'selected="selected"':''); ?>
		                <option value="<?php echo $key?>" <?php echo $selected?>><?php echo $value?></option>
		            <?php endforeach; ?>
	            </select>
        	</div>
			
			<div class="form-group">
        		<?php 
		        	if( $wp_user_id = get_current_user_id() ){
			            $cur_value = get_user_meta( $wp_user_id, 'billing_country', true );
			        }
		        ?>

        		<label for="billing_country"><?php _e( 'Country', 'woocomerce' ); ?> <span class="required">*</span></label>
	            <select class="billing_country" name="billing_country" id="billing_country">
	                <option value=""><?php _e( 'Select a country...', 'woocomerce' ); ?></option>
		            <?php foreach ($countries as $key => $value): $selected=($key==$cur_value?'selected="selected"':''); ?>
		                <option value="<?php echo $key?>" <?php echo $selected?>><?php echo $value?></option>
		            <?php endforeach; ?>
	            </select>
        	</div>
			
			
			<div class="form-group">
				<input class="input-checkbox"
						   id="apply_for_vendor" <?php checked( isset( $_POST[ 'apply_for_vendor' ] ), true ) ?>
						   type="checkbox" name="apply_for_vendor" value="1" required="true"/>
				<label for="apply_for_vendor" class="checkbox">
					<?php echo apply_filters('wcvendors_vendor_registration_checkbox', __( 'Apply to become a vendor? ', 'wcvendors' )); ?>
				</label>
			</div>
			<?php if ( $terms_page = WC_Vendors::$pv_options->get_option( 'terms_to_apply_page' ) ) : ?>
				<div class="form-group">
					<input class="input-checkbox"
						   id="agree_to_terms"
						   type="checkbox" name="agree_to_terms" value="1" required="true"/>
					<label for="agree_to_terms" class="checkbox">
						<?php printf( __( 'I have read and accepted the <a target="_blank" href="%s">terms and conditions</a>', 'wcvendors' ), get_permalink( $terms_page ) ); ?>
					</label>
				</div>
			<?php endif ?>
			
			<input type="hidden" name="set-vendor-role" value="set-vendor-role"/>
			<input type="submit" class="button" value="<?php _e( 'Submit', 'wcvendors' ); ?>"/>
		</form>
		<script type="text/javascript">$('#vendor_stat').change(function(){ $(this).val() == "business" ? $('#div_vendor_type').show() : $('#div_vendor_type').hide() })</script>
	<?php } ?>

<?php } ?>

<br class="clear">