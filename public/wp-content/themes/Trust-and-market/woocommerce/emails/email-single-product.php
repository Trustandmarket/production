<?php 
// used variabels 
// $product
// $resreved_dates

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reserved_dates = wc_get_order_item_meta( $item->get_id(), '_reserved_dates', true );
$disponibilities = explode(',', $reserved_dates);
ob_start();
?>


<!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;"><![endif]-->
<div style="color:#959595;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">  
  <div style="font-size:12px;line-height:14px;color:#959595;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><p style="margin: 0;font-size: 12px;line-height: 14px"><span style="font-size: 16px; line-height: 19px;"><strong>Ref : <?php echo $product->get_id(); ?></strong></span></p></div>  
</div>
<!--[if mso]></td></tr></table><![endif]-->


                  
                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;"><![endif]-->
<div style="color:#ff7e10;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">  
  <div style="line-height:14px;font-size:12px;color:#ff7e10;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><p style="margin: 0;line-height: 14px;font-size: 12px"><span style="font-size: 22px; line-height: 26px;"><strong><?php echo get_term_by('id', $product->get_category_ids()[1], 'category')->name; ?></strong></span></p></div>  
</div>
<!--[if mso]></td></tr></table><![endif]-->

                  

                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;"><![endif]-->
<div style="color:#959595;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">  
  <div style="font-size:12px;line-height:14px;color:#959595;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><p style="margin: 0;font-size: 12px;line-height: 14px"><span style="font-size: 16px; line-height: 19px;"><strong><span style="line-height: 19px; font-size: 16px;"><?php echo $product->get_title(); ?></span></strong></span></p></div>  
</div>
<!--[if mso]></td></tr></table><![endif]-->


                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;"><![endif]-->
<div style="color:#ff7e10;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">  
  <div style="font-size:12px;line-height:14px;color:#ff7e10;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><p style="margin: 0;font-size: 12px;line-height: 14px"><span style="font-size: 16px; line-height: 19px;"><img class="left" align="left" border="0" src="https://www.trustandmarket.com/wp-content/uploads/2017/10/icon-small-datepicker.png" alt="Trust & Market" title="Trust & Market" style="outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;clear: both;display: block !important;border: 0;height: auto;float: none;width: 100%;max-width: 15px;float:left;" width="15">&#160;<?php echo count($disponibilities) ?> dates réservées&#160;</span></p></div> 
</div>
<!--[if mso]></td></tr></table><![endif]-->


                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;"><![endif]-->
<div style="color:#555555;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">  
  <div style="font-size:12px;line-height:14px;color:#555555;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><p style="margin: 0;font-size: 12px;line-height: 14px"><?php foreach($disponibilities as $disponibilitie): ?> &#160;<span style="background-color: rgb(239, 239, 239); color: rgb(149, 149, 149); font-size: 16px; line-height: 19px;">&#160;<?php echo date_format(date_create($disponibilitie), 'd/m/Y'); ?>&#160;</span>&#160;<?php endforeach ?></p></div>  
</div>
<!--[if mso]></td></tr></table><![endif]-->


<?php $product_to_html = ob_get_clean(); ?>