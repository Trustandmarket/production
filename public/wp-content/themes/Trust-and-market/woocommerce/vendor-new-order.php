<?php
/**
 * Vendor new order email
 *
 * @author WC Vendors
 * @package WooCommerce/Templates/Emails/HTML
 * @version 1.9.9
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

// set header variables 
$items = $order->get_items();
$count_items = count($items);

$product_instance = new WC_Product_Factory();
foreach ($items as $item) {
  $product = $product_instance->get_product($item->get_product_id());
  $author_id = get_post($product->get_id())->post_author;
  if(! (get_user_meta($author_id, '_email_reservation_service_annonceur', true) == '1') )
  return null;
  break;
}


$billing_first_name = ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->billing_first_name : $order->get_billing_first_name(); 
$billing_last_name 	= ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->billing_last_name : $order->get_billing_last_name(); 
$billing_email 		= ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->billing_email : $order->get_billing_email(); 
$billing_phone 		= ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->billing_phone : $order->get_billing_phone(); 
$order_date 		= ( version_compare( WC_VERSION, '2.7', '<' ) ) ? $order->order_date : $order->get_date_created();  





$header_big_title = "Vous avez une commande";

$header_title = "Allez sur votre agenda, vous avez une commande en attente d'exécution.";

$header_img_url = site_url() . "/wp-content/uploads/2017/10/icon-ok-finger.png";

// echo the header
require("emails/email-header.php");
echo $header_to_html;

// begin the content

ob_start();
?>
<div style="background-color:transparent;"> <div style="Margin: 0 auto;min-width: 320px;max-width: 500px;width: 500px;width: calc(19000% - 98300px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;" class="block-grid "> <div style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="background-color:transparent;" align="center"><table cellpadding="0" cellspacing="0" border="0" style="width: 500px;"><tr class="layout-full-width" style="background-color:transparent;"><![endif]--> <!--[if (mso)|(IE)]><td align="center" width="500" style="background-color:#FFFFFF; width:500px; padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:0px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;" valign="top"><![endif]--> <div class="col num12" style="min-width: 320px;max-width: 500px;width: 500px;width: calc(18000% - 89500px);background-color: transparent;"> <div style="background-color: #FFFFFF; width: 100% !important;"> <!--[if (!mso)&(!IE)]><!--><div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:5px; padding-bottom:0px; padding-right: 0px; padding-left: 0px;"><!--<![endif]--> <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 5px;"><![endif]--> <div style="color:#262626;line-height:120%;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 5px;"> <div style="font-size:12px;line-height:14px;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif;color:#262626;text-align:left;"><p style="padding-bottom: 10px;margin: 0;font-size: 14px;line-height: 17px"><span style="font-size: 18px; line-height: 21px;"><strong><span style="line-height: 21px; font-size: 18px;">
    Bonjour,</span></strong></span></p></div> </div> <!--[if mso]></td></tr></table><![endif]--> <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]--> </div> </div> <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]--> </div> </div> </div>

<div style="background-color:transparent;"> <div style="Margin: 0 auto;min-width: 320px;max-width: 500px;width: 500px;width: calc(19000% - 98300px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;" class="block-grid "> <div style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="background-color:transparent;" align="center"><table cellpadding="0" cellspacing="0" border="0" style="width: 500px;"><tr class="layout-full-width" style="background-color:transparent;"><![endif]--> <!--[if (mso)|(IE)]><td align="center" width="500" style="background-color:#FFFFFF; width:500px; padding-right: 0px; padding-left: 0px; padding-top:0px; padding-bottom:5px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;" valign="top"><![endif]--> <div class="col num12" style="min-width: 320px;max-width: 500px;width: 500px;width: calc(18000% - 89500px);background-color: transparent;"> <div style="background-color: #FFFFFF; width: 100% !important;"> <!--[if (!mso)&(!IE)]><!--><div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:0px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;"><!--<![endif]--> <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 10px;"><![endif]--> <div style="color:#262626;line-height:120%;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 10px;"> <div style="font-size:12px;line-height:14px;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif;color:#262626;text-align:left;"><p style="padding-bottom: 10px;margin: 0;font-size: 14px;line-height: 17px"><span style="font-size: 16px; line-height: 19px;">
    Nous vous informons qu'une de vos annonce a été réservée :</span></p> </div> </div> <!--[if mso]></td></tr></table><![endif]-->
<?php
$content_head =  ob_get_clean();

echo $content_head;

// echo products 
$count_for_devider = 0;
foreach ($items as $item) {
    if($count_for_devider){
      ob_start();
      ?>
         <!--[if (mso)|(IE)]><td align="center" width="500" style=" width:500px; padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;" valign="top"><![endif]-->
            <div class="col num12" style="min-width: 320px;max-width: 500px;display: table-cell;vertical-align: top;">
              <div style="background-color: transparent; width: 100% !important;">
              <!--[if (!mso)&(!IE)]><!--><div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;"><!--<![endif]-->

                  
                    <div style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">
  <!--[if (mso)]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px;padding-left: 10px; padding-top: 10px; padding-bottom: 10px;"><table width="100%" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
  <div align="center"><div style="border-top: 10px solid #efefef; width:100%; line-height:10px; height:10px; font-size:10px;">&#160;</div></div>
  <!--[if (mso)]></td></tr></table></td></tr></table><![endif]-->
</div>

                  
              <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]-->
              </div>
            </div>
          <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]-->
      <?php 
      $devider = ob_get_clean();
      echo $devider;
    }
    $count_for_devider++;
    $product_instance = new WC_Product_Factory();
    $product = $product_instance->get_product($item->get_product_id());
    require "emails/email-single-product.php";
    echo $product_to_html;

    ob_start();
?>
                  
<!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;"><![endif]-->
<div style="color:#32b4a6;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">  
  <div style="font-size:12px;line-height:14px;color:#32b4a6;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><p style="margin: 0;font-size: 12px;line-height: 14px;text-align: right"><span style="font-size: 20px; line-height: 24px;"><strong>
    <span style="line-height: 24px; font-size: 20px;">﻿Prix total : <?php echo $order->get_total(); ?>&#160;€&#160;&#160;&#160;</span></strong></span></p></div>  
</div>
<!--[if mso]></td></tr></table><![endif]-->

                  
                  
                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;"><![endif]-->
<div style="color:#32b4a6;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">  
  <div style="font-size:12px;line-height:14px;color:#32b4a6;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><p style="margin: 0;font-size: 16px;line-height: 14px">CONTACT <span style="color: rgb(0, 0, 0); font-size: 12px; line-height: 14px;">___________________________________________________________</span></p></div> 
</div>
<!--[if mso]></td></tr></table><![endif]-->

                  
                  
                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 0px;"><![endif]-->
<div style="color:#000000;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 0px;">  
  <div style="font-size:12px;line-height:14px;color:#000000;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><p style="margin: 0;font-size: 12px;line-height: 14px"><span style="font-size: 16px; line-height: 19px;">
    Nom : <strong><?php echo $billing_first_name; ?></strong></span></p></div>  
</div>
<!--[if mso]></td></tr></table><![endif]-->

                  
                  
                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 0px;"><![endif]-->
<div style="color:#000000;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 0px;">  
  <div style="font-size:12px;line-height:14px;color:#000000;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><p style="margin: 0;font-size: 12px;line-height: 14px"><span style="font-size: 16px; line-height: 19px;">
    Prénom :&#160;<strong><?php echo $billing_last_name; ?></strong></span></p></div> 
</div>
<!--[if mso]></td></tr></table><![endif]-->

                  
         
                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 0px;"><![endif]-->
<div style="color:#000000;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 0px;">  
  <div style="font-size:12px;line-height:14px;color:#000000;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><p style="margin: 0;font-size: 12px;line-height: 14px"><span style="font-size: 16px; line-height: 19px;">
    Téléphone:&#160;<strong><?php echo $billing_phone; ?></strong></span></p></div>  
</div>
<!--[if mso]></td></tr></table><![endif]-->

                  
                  
                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 0px;"><![endif]-->
<div style="color:#000000;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 0px;">  
  <div style="font-size:12px;line-height:14px;color:#000000;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><p style="margin: 0;font-size: 16px;line-height: 19px"><span style="font-size: 16px; line-height: 19px;">
    e-mail: <a style="color:#0068A5;text-decoration: underline;" title="<?php echo $billing_email; ?>" href="mailto:<?php echo $billing_email ?>"><?php echo $billing_email; ?></a></span></p></div>  
</div>
<!--[if mso]></td></tr></table><![endif]-->

                  
                  
                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 10px;"><![endif]-->
<div style="color:#000000;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 10px;"> 
  <div style="font-size:12px;line-height:14px;color:#000000;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><br></div> 
</div>
<!--[if mso]></td></tr></table><![endif]-->

                  

<!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 20px; padding-bottom: 10px;"><![endif]-->
<div style="color:#000000;line-height:120%;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 20px; padding-bottom: 10px;">  
  <div style="font-size:12px;line-height:14px;color:#000000;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;text-align:left;"><p style="margin: 0;font-size: 16px;line-height: 19px"><span style="font-size: 16px; line-height: 19px;">Rentrez rapidement en contact avec votre client afin d'organiser la prestation dans les plus brefs délais. N'hésitez pas à consulter&#160;<a style="color:#000000;text-decoration: underline;" href="https://www.trustandmarket.com/nos-conditions" target="_blank" rel="noopener noreferrer">nos conditions</a>&#160;et&#160;<a style="color:#000000;text-decoration: underline;" href="https://www.trustandmarket.com/conditions-generales-dutilisation" target="_blank" rel="noopener noreferrer">nos conseils et recommandations</a>&#160;pour une prestation de qualité. A tout moment, n’hésitez pas à nous contacter&#160;</span></p></div> 
</div>
<!--[if mso]></td></tr></table><![endif]-->

<?php
$product_contact_info =  ob_get_clean();
echo $product_contact_info;


}

ob_start();
?>

<div align="center" class="button-container center" style="margin-top:20px;padding-right: 10px; padding-left: 10px; padding-top:10px; padding-bottom:10px;">
  <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-spacing: 0; border-collapse: collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top:10px; padding-bottom:10px;" align="center"><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="" style="height:54px; v-text-anchor:middle; width:220px;" arcsize="8%" strokecolor="" fillcolor="#ff7e10"><w:anchorlock/><center style="color:#ffffff; font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; font-size:16px;"><![endif]-->
    <a href="<?php echo site_url()."/mon-profil/annonces/mes-reservations" ?>" target="_blank" style="cdisplay: inline-block;text-decoration: none;-webkit-text-size-adjust: none;text-align: center;color: #ffffff; background-color: #ff7e10; border-radius: 4px; -webkit-border-radius: 4px; -moz-border-radius: 4px; max-width: 256px; width: 196px; width: auto; border-top: 0px solid transparent; border-right: 0px solid transparent; border-bottom: 0px solid transparent; border-left: 0px solid transparent; padding-top: 15px; padding-right: 30px; padding-bottom: 15px; padding-left: 30px; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;mso-border-alt: none">
      <span style="font-size:12px;line-height:24px;"><span style="font-size: 16px; line-height: 32px;" data-mce-style="font-size: 16px;"><strong>Voir votre réservation</strong></span></span>
    </a>
  <!--[if mso]></center></v:roundrect></td></tr></table><![endif]-->
</div>
                  

<?php
$content_foot =  ob_get_clean();
echo $content_foot;


// echo the header
require "emails/email-footer.php";
echo $footer_to_html;