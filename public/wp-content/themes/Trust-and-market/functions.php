<?php

remove_filter('the_content', 'wpautop');
// OR
remove_filter('the_excerpt', 'wpautop');

/*
All the functions are in the PHP pages in the functions/ folder.
*/
add_filter('woocommerce_product_add_to_cart_text', 'woo_archive_custom_cart_button_text');    // 2.1 +



remove_filter( 'the_content', 'wpautop' );
remove_filter( 'the_excerpt', 'wpautop' );




function woo_archive_custom_cart_button_text()
{

    return __("Voir l'annonce", 'woocommerce');

}

require_once locate_template('/functions/cleanup.php');
require_once locate_template('/functions/setup.php');
require_once locate_template('/functions/enqueues.php');
require_once locate_template('/functions/navbar.php');
require_once locate_template('/functions/widgets.php');
require_once locate_template('/functions/search.php');
require_once locate_template('/functions/feedback.php');
require_once locate_template('/functions/woocommerce-setup.php');
// require_once locate_template('/functions/custom-post-type-resource.php');
require locate_template('/functions/theme-hooks.php');

include locate_template('/includes/options/bst-options.php');

add_action('after_setup_theme', 'true_load_theme_textdomain');

function true_load_theme_textdomain()
{
    load_theme_textdomain('bst-plus', get_template_directory() . '/languages');
}


function gootheme_theme_customizer($wp_customize)
{

    $wp_customize->add_section('gootheme_logo_section', array(
        'title' => __('Logo', 'gootheme'),
        'priority' => 30,
        'description' => 'Upload a logo for this theme',
    ));

    $wp_customize->add_setting('gootheme_logo', array(
        'default' => get_bloginfo('template_directory') . '/images/default-logo.png',
    ));
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'gootheme_logo', array(

        'label' => __('Current logo', 'gootheme'),
        'section' => 'gootheme_logo_section',
        'settings' => 'gootheme_logo',
    )));

}

add_action('customize_register', 'gootheme_theme_customizer');

function register_my_menu()
{
    register_nav_menu('header-menu-right-cart', __('Header Menu Cart'));
}

add_action('init', 'register_my_menu');


function site_router()
{
    $root = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);
    if (strlen($root) > 1) {
        $url = str_replace($root, '', $_SERVER['REQUEST_URI']);
    } else {
        $url = $_SERVER['REQUEST_URI'];
    }
    //$url = $_SERVER['REQUEST_URI'];
    $url = explode('/', $url);
    $url = array_values(array_filter($url));
    //die(var_dump($url));
    if (count($url))
        if (count($url) == 1 && $url[0] == 'deleteUser?confirm=OK') {
            $user = wp_get_current_user();
            $not_allowed_roles = array('editor', 'administrator', 'author');
            if (!array_intersect($not_allowed_roles, $user->roles)) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
                ob_start();
                ?>

                <!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Transitional //EN"
                        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
                <html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml"
                      xmlns:o="urn:schemas-microsoft-com:office:office">
                <head> <!--[if gte mso 9]>
                    <xml>
                        <o:OfficeDocumentSettings>
                            <o:AllowPNG/>
                            <o:PixelsPerInch>96</o:PixelsPerInch>
                        </o:OfficeDocumentSettings>
                    </xml><![endif]-->
                    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                    <meta name="viewport" content="width=device-width"> <!--[if !mso]><!-->
                    <meta http-equiv="X-UA-Compatible" content="IE=edge"><!--<![endif]--> <title></title>
                    <!--[if !mso]><!-- -->
                    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro" rel="stylesheet"
                          type="text/css"> <!--<![endif]-->
                    <style type="text/css" id="media-query"> body {
                            margin: 0;
                            padding: 0;
                        }

                        table, tr, td {
                            vertical-align: top;
                            border-collapse: collapse;
                        }

                        .ie-browser table, .mso-container table {
                            table-layout: fixed;
                        }

                        * {
                            line-height: inherit;
                        }

                        a[x-apple-data-detectors=true] {
                            color: inherit !important;
                            text-decoration: none !important;
                        }

                        [owa] .img-container div, [owa] .img-container button {
                            display: block !important;
                        }

                        [owa] .fullwidth button {
                            width: 100% !important;
                        }

                        [owa] .block-grid .col {
                            display: table-cell;
                            float: none !important;
                            vertical-align: top;
                        }

                        .ie-browser .num12, .ie-browser .block-grid, [owa] .num12, [owa] .block-grid {
                            width: 500px !important;
                        }

                        .ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div {
                            line-height: 100%;
                        }

                        .ie-browser .mixed-two-up .num4, [owa] .mixed-two-up .num4 {
                            width: 164px !important;
                        }

                        .ie-browser .mixed-two-up .num8, [owa] .mixed-two-up .num8 {
                            width: 328px !important;
                        }

                        .ie-browser .block-grid.two-up .col, [owa] .block-grid.two-up .col {
                            width: 250px !important;
                        }

                        .ie-browser .block-grid.three-up .col, [owa] .block-grid.three-up .col {
                            width: 166px !important;
                        }

                        .ie-browser .block-grid.four-up .col, [owa] .block-grid.four-up .col {
                            width: 125px !important;
                        }

                        .ie-browser .block-grid.five-up .col, [owa] .block-grid.five-up .col {
                            width: 100px !important;
                        }

                        .ie-browser .block-grid.six-up .col, [owa] .block-grid.six-up .col {
                            width: 83px !important;
                        }

                        .ie-browser .block-grid.seven-up .col, [owa] .block-grid.seven-up .col {
                            width: 71px !important;
                        }

                        .ie-browser .block-grid.eight-up .col, [owa] .block-grid.eight-up .col {
                            width: 62px !important;
                        }

                        .ie-browser .block-grid.nine-up .col, [owa] .block-grid.nine-up .col {
                            width: 55px !important;
                        }

                        .ie-browser .block-grid.ten-up .col, [owa] .block-grid.ten-up .col {
                            width: 50px !important;
                        }

                        .ie-browser .block-grid.eleven-up .col, [owa] .block-grid.eleven-up .col {
                            width: 45px !important;
                        }

                        .ie-browser .block-grid.twelve-up .col, [owa] .block-grid.twelve-up .col {
                            width: 41px !important;
                        }

                        @media only screen and (min-width: 520px) {
                            .block-grid {
                                width: 500px !important;
                            }

                            .block-grid .col {
                                display: table-cell;
                                Float: none !important;
                                vertical-align: top;
                            }

                            .block-grid .col.num12 {
                                width: 500px !important;
                            }

                            .block-grid.mixed-two-up .col.num4 {
                                width: 164px !important;
                            }

                            .block-grid.mixed-two-up .col.num8 {
                                width: 328px !important;
                            }

                            .block-grid.two-up .col {
                                width: 250px !important;
                            }

                            .block-grid.three-up .col {
                                width: 166px !important;
                            }

                            .block-grid.four-up .col {
                                width: 125px !important;
                            }

                            .block-grid.five-up .col {
                                width: 100px !important;
                            }

                            .block-grid.six-up .col {
                                width: 83px !important;
                            }

                            .block-grid.seven-up .col {
                                width: 71px !important;
                            }

                            .block-grid.eight-up .col {
                                width: 62px !important;
                            }

                            .block-grid.nine-up .col {
                                width: 55px !important;
                            }

                            .block-grid.ten-up .col {
                                width: 50px !important;
                            }

                            .block-grid.eleven-up .col {
                                width: 45px !important;
                            }

                            .block-grid.twelve-up .col {
                                width: 41px !important;
                            }
                        }

                        @media (max-width: 520px) {
                            .block-grid, .col {
                                min-width: 320px !important;
                                max-width: 100% !important;
                            }

                            .block-grid {
                                width: calc(100% - 40px) !important;
                            }

                            .col {
                                width: 100% !important;
                            }

                            .col > div {
                                margin: 0 auto;
                            }

                            img.fullwidth {
                                max-width: 100% !important;
                            }
                        } </style>
                </head>
                <body class="clean-body"
                      style="margin: 0;padding: 0;-webkit-text-size-adjust: 100%;background-color: #efefef"><!--[if IE]>
                <div class="ie-browser"><![endif]--> <!--[if mso]>
                <div class="mso-container"><![endif]-->
                <div class="nl-container" style="min-width: 320px;Margin: 0 auto;background-color: #efefef">
                    <!--[if (mso)|(IE)]>
                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td align="center" style="background-color: #efefef;"><![endif]-->
                    <div style="background-color:transparent;">
                        <div style="Margin: 0 auto;min-width: 320px;max-width: 500px;width: 500px;width: calc(19000% - 98300px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;"
                             class="block-grid two-up">
                            <div style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]>
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="background-color:transparent;" align="center">
                                            <table cellpadding="0" cellspacing="0" border="0" style="width: 500px;">
                                                <tr class="layout-full-width" style="background-color:transparent;">
                                <![endif]--> <!--[if (mso)|(IE)]>
                                <td align="center" width="250"
                                    style=" width:250px; padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;"
                                    valign="top"><![endif]-->
                                <div class="col num6"
                                     style="Float: left;max-width: 320px;min-width: 250px;width: 250px;width: calc(35250px - 7000%);background-color: transparent;">
                                    <div style="background-color: transparent; width: 100% !important;">
                                        <!--[if (!mso)&(!IE)]><!-->
                                        <div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;">
                                            <!--<![endif]-->
                                            <div align="left" class="img-container left"
                                                 style="padding-right: 0px; padding-left: 0px;"> <!--[if mso]>
                                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                    <tr>
                                                        <td style="padding-right: 0px; padding-left: 0px;" align="left">
                                                <![endif]--> <img class="left" align="left" border="0"
                                                                  src="https://www.trustandmarket.com/wp-content/uploads/2017/08/logo-email.png"
                                                                  alt="Trust & Market" title="Trust & Market"
                                                                  style="outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;clear: both;display: block !important;border: 0;height: auto;float: none;width: 100%;max-width: 205px"
                                                                  width="205"> <!--[if mso]></td></tr></table>
                                                <![endif]--> </div> <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]-->
                                    </div>
                                </div>
                                <!--[if (mso)|(IE)]></td>
                                <td align="center" width="250"
                                    style=" width:250px; padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;"
                                    valign="top"><![endif]-->
                                <div class="col num6"
                                     style="Float: left;max-width: 320px;min-width: 250px;width: 250px;width: calc(35250px - 7000%);background-color: transparent;">
                                    <div style="background-color: transparent; width: 100% !important;">
                                        <!--[if (!mso)&(!IE)]><!-->
                                        <div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;">
                                            <!--<![endif]--> &#160; <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]-->
                                    </div>
                                </div>
                                <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]--> </div>
                        </div>
                    </div>
                    <div style="background-color:transparent;">
                        <div style="Margin: 0 auto;min-width: 320px;max-width: 500px;width: 500px;width: calc(19000% - 98300px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;"
                             class="block-grid ">
                            <div style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]>
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="background-color:transparent;" align="center">
                                            <table cellpadding="0" cellspacing="0" border="0" style="width: 500px;">
                                                <tr class="layout-full-width" style="background-color:transparent;">
                                <![endif]--> <!--[if (mso)|(IE)]>
                                <td align="center" width="500"
                                    style="background-color:#ff7e10; width:500px; padding-right: 0px; padding-left: 0px; padding-top:0px; padding-bottom:0px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;"
                                    valign="top"><![endif]-->
                                <div class="col num12"
                                     style="min-width: 320px;max-width: 500px;width: 500px;width: calc(18000% - 89500px);background-color: transparent;">
                                    <div style="background-color: #ff7e10; width: 100% !important;">
                                        <!--[if (!mso)&(!IE)]><!-->
                                        <div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:0px; padding-bottom:0px; padding-right: 0px; padding-left: 0px;">
                                            <!--<![endif]-->
                                            <div align="center" class="img-container center"
                                                 style="padding-right: 30px; padding-left: 30px;"> <!--[if mso]>
                                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                    <tr>
                                                        <td style="padding-right: 30px; padding-left: 30px;"
                                                            align="center"><![endif]-->
                                                <div style="line-height:30px;font-size:1px">&#160;</div>
                                                <img class="center" align="center" border="0"
                                                     src="https://www.trustandmarket.com/wp-content/uploads/2017/08/sadeness-icon.png"
                                                     alt="Image" title="Image"
                                                     style="outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;clear: both;display: block !important;border: 0;height: auto;float: none;width: 100%;max-width: 105px"
                                                     width="105">
                                                <div style="line-height:30px;font-size:1px">&#160;</div>
                                                <!--[if mso]></td></tr></table><![endif]--> </div>
                                            <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]--> </div>
                                </div>
                                <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]--> </div>
                        </div>
                    </div>
                    <div style="background-color:transparent;">
                        <div style="Margin: 0 auto;min-width: 320px;max-width: 500px;width: 500px;width: calc(19000% - 98300px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;"
                             class="block-grid ">
                            <div style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]>
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="background-color:transparent;" align="center">
                                            <table cellpadding="0" cellspacing="0" border="0" style="width: 500px;">
                                                <tr class="layout-full-width" style="background-color:transparent;">
                                <![endif]--> <!--[if (mso)|(IE)]>
                                <td align="center" width="500"
                                    style="background-color:#ff7e10; width:500px; padding-right: 0px; padding-left: 0px; padding-top:0px; padding-bottom:0px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;"
                                    valign="top"><![endif]-->
                                <div class="col num12"
                                     style="min-width: 320px;max-width: 500px;width: 500px;width: calc(18000% - 89500px);background-color: transparent;">
                                    <div style="background-color: #ff7e10; width: 100% !important;">
                                        <!--[if (!mso)&(!IE)]><!-->
                                        <div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:0px; padding-bottom:0px; padding-right: 0px; padding-left: 0px;">
                                            <!--<![endif]--> <!--[if mso]>
                                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">
                                            <![endif]-->
                                            <div style="color:#FFFFFF;line-height:120%;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">
                                                <div style="font-size:12px;line-height:14px;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif;color:#FFFFFF;text-align:left;">
                                                    <p style="margin: 0;font-size: 14px;line-height: 17px;text-align: center">
                                                        <span style="font-size: 24px; line-height: 28px;"> Votre compte vient d'être supprimé</span>
                                                    </p></div>
                                            </div>
                                            <!--[if mso]></td></tr></table><![endif]--> <!--[if mso]>
                                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">
                                            <![endif]-->
                                            <div style="color:#FFFFFF;line-height:120%;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">
                                                <div style="font-size:12px;line-height:14px;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif;color:#FFFFFF;text-align:left;">
                                                    <p style="margin: 10px;font-size: 14px;line-height: 17px;text-align: center">
                                                        <span style="font-size: 16px; line-height: 19px;">En supprimant votre compte, vous quittez la communauté Trust &amp; Market.</span>
                                                    </p></div>
                                            </div>
                                            <!--[if mso]></td></tr></table><![endif]--> <!--[if (!mso)&(!IE)]><!-->
                                        </div><!--<![endif]--> </div>
                                </div>
                                <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]--> </div>
                        </div>
                    </div>
                    <div style="background-color:transparent;">
                        <div style="Margin: 0 auto;min-width: 320px;max-width: 500px;width: 500px;width: calc(19000% - 98300px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;"
                             class="block-grid ">
                            <div style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]>
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="background-color:transparent;" align="center">
                                            <table cellpadding="0" cellspacing="0" border="0" style="width: 500px;">
                                                <tr class="layout-full-width" style="background-color:transparent;">
                                <![endif]--> <!--[if (mso)|(IE)]>
                                <td align="center" width="500"
                                    style="background-color:#ff7e10; width:500px; padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:20px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;"
                                    valign="top"><![endif]-->
                                <div class="col num12"
                                     style="min-width: 320px;max-width: 500px;width: 500px;width: calc(18000% - 89500px);background-color: transparent;">
                                    <div style="background-color: #ff7e10; width: 100% !important;">
                                        <!--[if (!mso)&(!IE)]><!-->
                                        <div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:5px; padding-bottom:20px; padding-right: 0px; padding-left: 0px;">
                                            <!--<![endif]-->
                                            <div align="center" class="button-container center"
                                                 style="padding-right: 10px; padding-left: 10px; padding-top:10px; padding-bottom:10px;">
                                                <!--[if mso]>
                                                <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                                       style="border-spacing: 0; border-collapse: collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;">
                                                    <tr>
                                                        <td style="padding-right: 10px; padding-left: 10px; padding-top:10px; padding-bottom:10px;"
                                                            align="center">
                                                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml"
                                                                         xmlns:w="urn:schemas-microsoft-com:office:word"
                                                                         href="https://www.trustandmarket.com/"
                                                                         style="height:52px; v-text-anchor:middle; width:276px;"
                                                                         arcsize="8%" strokecolor="#ffffff"
                                                                         fillcolor="#ffffff">
                                                                <w:anchorlock/>
                                                                <center style="color:#000000; font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif; font-size:16px;">
                                                <![endif]--> <a href="https://https://www.trustandmarket.com/aide/avis"
                                                                target="_blank"
                                                                style="display: inline-block;text-decoration: none;-webkit-text-size-adjust: none;text-align: center;color: #000000; background-color: #ffffff; border-radius: 4px; -webkit-border-radius: 4px; -moz-border-radius: 4px; max-width: 256px; width: 196px; width: auto; border-top: 0px solid transparent; border-right: 0px solid transparent; border-bottom: 0px solid transparent; border-left: 0px solid transparent; padding-top: 10px; padding-right: 30px; padding-bottom: 10px; padding-left: 30px; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;mso-border-alt: none">
                                                    <span style="font-size:16px;line-height:32px;"><strong>Laissez-nous un commentaire </strong></span>
                                                </a> <!--[if mso]></center></v:roundrect></td></tr></table><![endif]-->
                                            </div> <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]--> </div>
                                </div>
                                <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]--> </div>
                        </div>
                    </div>
                    <div style="background-color:transparent;">
                        <div style="Margin: 0 auto;min-width: 320px;max-width: 500px;width: 500px;width: calc(19000% - 98300px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;"
                             class="block-grid ">
                            <div style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]>
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="background-color:transparent;" align="center">
                                            <table cellpadding="0" cellspacing="0" border="0" style="width: 500px;">
                                                <tr class="layout-full-width" style="background-color:transparent;">
                                <![endif]--> <!--[if (mso)|(IE)]>
                                <td align="center" width="500"
                                    style="background-color:#FFFFFF; width:500px; padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:0px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;"
                                    valign="top"><![endif]-->
                                <div class="col num12"
                                     style="min-width: 320px;max-width: 500px;width: 500px;width: calc(18000% - 89500px);background-color: transparent;">
                                    <div style="background-color: #FFFFFF; width: 100% !important;">
                                        <!--[if (!mso)&(!IE)]><!-->
                                        <div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:5px; padding-bottom:0px; padding-right: 0px; padding-left: 0px;">
                                            <!--<![endif]--> <!--[if mso]>
                                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 5px;">
                                            <![endif]-->
                                            <div style="color:#262626;line-height:120%;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 5px;">
                                                <div style="font-size:12px;line-height:14px;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif;color:#262626;text-align:left;">
                                                    <p style="padding-bottom: 10px;margin: 0;font-size: 14px;line-height: 17px">
                                                        <span style="font-size: 18px; line-height: 21px;"><strong><span
                                                                        style="line-height: 21px; font-size: 18px;">Bonjour, </span></strong></span>
                                                    </p></div>
                                            </div>
                                            <!--[if mso]></td></tr></table><![endif]--> <!--[if (!mso)&(!IE)]><!-->
                                        </div><!--<![endif]--> </div>
                                </div>
                                <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]--> </div>
                        </div>
                    </div>
                    <div style="background-color:transparent;">
                        <div style="Margin: 0 auto;min-width: 320px;max-width: 500px;width: 500px;width: calc(19000% - 98300px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;"
                             class="block-grid ">
                            <div style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]>
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="background-color:transparent;" align="center">
                                            <table cellpadding="0" cellspacing="0" border="0" style="width: 500px;">
                                                <tr class="layout-full-width" style="background-color:transparent;">
                                <![endif]--> <!--[if (mso)|(IE)]>
                                <td align="center" width="500"
                                    style="background-color:#FFFFFF; width:500px; padding-right: 0px; padding-left: 0px; padding-top:0px; padding-bottom:5px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;"
                                    valign="top"><![endif]-->
                                <div class="col num12"
                                     style="min-width: 320px;max-width: 500px;width: 500px;width: calc(18000% - 89500px);background-color: transparent;">
                                    <div style="background-color: #FFFFFF; width: 100% !important;">
                                        <!--[if (!mso)&(!IE)]><!-->
                                        <div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:0px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;">
                                            <!--<![endif]--> <!--[if mso]>
                                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td style="padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 10px;">
                                            <![endif]-->
                                            <div style="color:#262626;line-height:120%;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 10px;">
                                                <div style="font-size:12px;line-height:14px;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif;color:#262626;text-align:left;">
                                                    <p style="padding-bottom: 10px;margin: 0;font-size: 14px;line-height: 17px">
                                                        <span style="font-size: 16px; line-height: 19px;">C’est avec regret que nous constatons la suppression de votre compte utilisateur. </span>
                                                    </p>
                                                    <p style="margin: 0;font-size: 16px;line-height: 19px"><span
                                                                style="font-size: 16px; line-height: 19px;"> Toutefois n’hésitez pas à nous laisser vos commentaires. Nous espérons vous revoir bientôt sur Trust &amp; Market. </span><br>
                                                    </p></div>
                                            </div>
                                            <!--[if mso]></td></tr></table><![endif]--> <!--[if mso]>
                                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px;">
                                            <![endif]-->
                                            <div style="color:#262626;line-height:120%;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif; padding-right: 10px; padding-left: 10px; padding-top: 30px; padding-bottom: 10px;">
                                                <div style="font-size:12px;line-height:14px;font-family:'Source Sans Pro', Tahoma, Verdana, Segoe, sans-serif;color:#262626;text-align:left;">
                                                    <p style="margin: 0;font-size: 14px;line-height: 17px"><em>Bien à
                                                            vous,<br>Votre Equipe de service client Trust &amp;
                                                            Market</em></p></div>
                                            </div>
                                            <!--[if mso]></td></tr></table><![endif]--> <!--[if (!mso)&(!IE)]><!-->
                                        </div><!--<![endif]--> </div>
                                </div>
                                <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]--> </div>
                        </div>
                    </div>
                    <div style="background-color:transparent;">
                        <div style="Margin: 0 auto;min-width: 320px;max-width: 500px;width: 500px;width: calc(19000% - 98300px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;background-color: transparent;"
                             class="block-grid three-up">
                            <div style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]>
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="background-color:transparent;" align="center">
                                            <table cellpadding="0" cellspacing="0" border="0" style="width: 500px;">
                                                <tr class="layout-full-width" style="background-color:transparent;">
                                <![endif]--> <!--[if (mso)|(IE)]>
                                <td align="center" width="167"
                                    style=" width:167px; padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;"
                                    valign="top"><![endif]-->
                                <div class="col num4"
                                     style="Float: left;max-width: 320px;min-width: 166px;width: 167px;width: calc(77166px - 15400%);background-color: transparent;">
                                    <div style="background-color: transparent; width: 100% !important;">
                                        <!--[if (!mso)&(!IE)]><!-->
                                        <div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;">
                                            <!--<![endif]--> &#160; <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]-->
                                    </div>
                                </div>
                                <!--[if (mso)|(IE)]></td>
                                <td align="center" width="167"
                                    style=" width:167px; padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;"
                                    valign="top"><![endif]-->
                                <div class="col num4"
                                     style="Float: left;max-width: 320px;min-width: 166px;width: 167px;width: calc(77166px - 15400%);background-color: transparent;">
                                    <div style="background-color: transparent; width: 100% !important;">
                                        <!--[if (!mso)&(!IE)]><!-->
                                        <div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;">
                                            <!--<![endif]-->
                                            <div align="center"
                                                 style="padding-right: 10px; padding-left: 10px; padding-bottom: 10px;">
                                                <div style="line-height:10px;font-size:1px">&#160;</div>
                                                <div style="display: table; max-width:151;"> <!--[if (mso)|(IE)]>
                                                    <table width="131" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="border-collapse:collapse; padding-right: 10px; padding-left: 10px; padding-bottom: 10px;"
                                                                align="center">
                                                                <table width="100%" cellpadding="0" cellspacing="0"
                                                                       border="0"
                                                                       style="border-collapse:collapse; mso-table-lspace: 0pt;mso-table-rspace: 0pt; width:131px;">
                                                                    <tr>
                                                                        <td width="32"
                                                                            style="width:32px; padding-right: 5px;"
                                                                            valign="top"><![endif]-->
                                                    <table align="left" border="0" cellspacing="0" cellpadding="0"
                                                           width="32" height="32"
                                                           style="border-collapse: collapse;table-layout: fixed;border-spacing: 0;mso-table-lspace: 0pt;mso-table-rspace: 0pt;vertical-align: top;Margin-right: 5px">
                                                        <tbody>
                                                        <tr style="vertical-align: top">
                                                            <td align="left" valign="middle"
                                                                style="word-break: break-word;border-collapse: collapse !important;vertical-align: top">
                                                                <a href="https://www.facebook.com/trustandmarket"
                                                                   title="Facebook" target="_blank"> <img
                                                                            src="https://www.trustandmarket.com/wp-content/uploads/2017/08/facebook@2x.png"
                                                                            alt="Facebook" title="Facebook" width="32"
                                                                            style="outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;clear: both;display: block !important;border: none;height: auto;float: none;max-width: 32px !important">
                                                                </a>
                                                                <div style="line-height:5px;font-size:1px">&#160;</div>
                                                            </td>
                                                        </tr>
                                                        </tbody>
                                                    </table>
                                                    <!--[if (mso)|(IE)]></td>
                                                    <td width="32" style="width:32px; padding-right: 5px;" valign="top">
                                                    <![endif]-->
                                                    <table align="left" border="0" cellspacing="0" cellpadding="0"
                                                           width="32" height="32"
                                                           style="border-collapse: collapse;table-layout: fixed;border-spacing: 0;mso-table-lspace: 0pt;mso-table-rspace: 0pt;vertical-align: top;Margin-right: 5px">
                                                        <tbody>
                                                        <tr style="vertical-align: top">
                                                            <td align="left" valign="middle"
                                                                style="word-break: break-word;border-collapse: collapse !important;vertical-align: top">
                                                                <a href="https://twitter.com/trustandmarket"
                                                                   title="Twitter" target="_blank"> <img
                                                                            src="https://www.trustandmarket.com/wp-content/uploads/2017/08/twitter@2x.png"
                                                                            alt="Twitter" title="Twitter" width="32"
                                                                            style="outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;clear: both;display: block !important;border: none;height: auto;float: none;max-width: 32px !important">
                                                                </a>
                                                                <div style="line-height:5px;font-size:1px">&#160;</div>
                                                            </td>
                                                        </tr>
                                                        </tbody>
                                                    </table>
                                                    <!--[if (mso)|(IE)]></td>
                                                    <td width="32" style="width:32px; padding-right: 0;" valign="top">
                                                    <![endif]-->
                                                    <table align="left" border="0" cellspacing="0" cellpadding="0"
                                                           width="32" height="32"
                                                           style="border-collapse: collapse;table-layout: fixed;border-spacing: 0;mso-table-lspace: 0pt;mso-table-rspace: 0pt;vertical-align: top;Margin-right: 0">
                                                        <tbody>
                                                        <tr style="vertical-align: top">
                                                            <td align="left" valign="middle"
                                                                style="word-break: break-word;border-collapse: collapse !important;vertical-align: top">
                                                                <a href="https://www.instagram.com/trustandmarket"
                                                                   title="Instagram" target="_blank"> <img
                                                                            src="https://www.trustandmarket.com/wp-content/uploads/2017/12/instagram@2x.png"
                                                                            alt="Instagram" title="Instagram" width="32"
                                                                            style="outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;clear: both;display: block !important;border: none;height: auto;float: none;max-width: 32px !important">
                                                                </a>
                                                                <div style="line-height:5px;font-size:1px">&#160;</div>
                                                            </td>
                                                        </tr>
                                                        </tbody>
                                                    </table>
                                                    <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]-->
                                                </div>
                                            </div> <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]--> </div>
                                </div>
                                <!--[if (mso)|(IE)]></td>
                                <td align="center" width="167"
                                    style=" width:167px; padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;"
                                    valign="top"><![endif]-->
                                <div class="col num4"
                                     style="Float: left;max-width: 320px;min-width: 166px;width: 167px;width: calc(77166px - 15400%);background-color: transparent;">
                                    <div style="background-color: transparent; width: 100% !important;">
                                        <!--[if (!mso)&(!IE)]><!-->
                                        <div style="border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;">
                                            <!--<![endif]--> &#160; <!--[if (!mso)&(!IE)]><!--></div><!--<![endif]-->
                                    </div>
                                </div>
                                <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]--> </div>
                        </div>
                    </div>
                    <!--[if (mso)|(IE)]></td></tr></table><![endif]--> </div>
                <!--[if (mso)|(IE)]></div><![endif]--> </body>
                </html>

                <?php
                $message = ob_get_clean();
                wp_mail($user->user_email, 'Suppression de compte Trust & Market', $message, $headers = array('Content-Type: text/html; charset=UTF-8'), $attachments = array());
                wp_delete_user($user->ID);
                wp_redirect(home_url());
                exit;
            }
        }
    if (count($url))
        if (count($url) == 1 && $url[0] == 'advanced-search') {
            require('advanced-search-result.php');
            die();
        }
    if (count($url))
        if ((count($url) >= 2 || count($url) <= 4) && $url[0] == 'product-category') {
            $parent_categ = $url[1];
            $child_categ = '';
            if (count($url) == 4) {
                $child_categ = $url[2];
            }
            $toadvanced_search = home_url() . '/advanced-search?search=advanced&parent_categ=' . urlencode($parent_categ) . '&child_categ=' . urlencode($child_categ);
            if (wp_redirect($toadvanced_search)) {
                exit;
            }
            die();
        }

// 	die(var_dump($url));
    if (count($url))
        if (count($url) == 1 && $url[0] == 'shop') {
            if (wp_redirect(home_url() . '/advanced-search?search=advanced')) {
                exit;
            }
            die();
        }
    if (get_current_user_id() == 0) {
        if (count($url))
            if (count($url) == 1 && $url[0] == 'checkout') {
                wp_redirect(site_url() . '/login/?redirect_to=' . site_url() . '/checkout');
                die();
            }
    }
}

function get_product_category_by_slug($cat_slug)
{
    $category = get_term_by('slug', $cat_slug, 'product_cat', 'ARRAY_A');
    return $category['name'];
}

add_action('send_headers', 'site_router');

function myplugin_register_form()
{
    $subscribed = (!empty($_POST['subscribed'])) ? trim($_POST['subscribed']) : '';

    ?>
    <input type="text" class="form-control bootstrap-datepicker" name="bdaytime" placeholder="Date de naissance">
    <p>
        <label for="contract">
            <input type="checkbox" name="contract" id="contract" class="input" required="required"/>
            <?php _e('En envoyant mon inscription, ') ?>
            <a href="<?php echo get_site_url() . "/nos-conditions/" ?>" class="hover white-text" target="_blank">
                <?php _e('&nbsp;j\'accepte les conditions de service et la politique de confidentialité et sécurité. ', 'theme-my-login') ?>
            </a>
        </label>
    </p>
    <p>
        <label for="subscribed">
            <input type="checkbox" name="subscribed" id="subscribed" class="input"/>
            <?php _e('Je souhaite recevoir par email des coupons des promotion.', 'theme-my-login') ?>
        </label>
    </p>

    <?php
}

add_action('register_form', 'myplugin_register_form');

function myplugin_custom_register_form()
{

    ?>

    <div class="tml-user-first-last-name"><input name="first_name" type="text" placeholder="Prénom"
                                                 class="input form-control" size="20"/>
        <input name="last_name" placeholder="Nom" type="text" class="input form-control" size="20"/></div>

    <?php
}

add_action('custom_register_form', 'myplugin_custom_register_form');

function tml_user_register($user_id)
{
    if (!empty($_POST['first_name']) && !empty($_POST['last_name'])) {
        $firstname = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        update_user_meta($user_id, 'first_name', $firstname);
        update_user_meta($user_id, 'last_name', $last_name);

        update_user_meta($user_id, 'display_name', $firstname . " " . $last_name);

        // Notification init
        update_user_meta($user_id, '_email_reservation_service_client', 1);
        update_user_meta($user_id, '_email_reservation_service_annonceur', 1);
        update_user_meta($user_id, '_email_annulation_reservation', 1);
        update_user_meta($user_id, '_email_annonce_en_brouillon', 1);
        update_user_meta($user_id, '_email_annonce_en_moderation', 1);
        update_user_meta($user_id, '_email_annonce_rejete', 1);
        update_user_meta($user_id, '_email_annonce_publie', 1);

    }
    if (!empty($_POST['bdaytime'])) {
        update_user_meta($user_id, 'bdaytime', sanitize_text_field($_POST['bdaytime']));
        update_user_meta($user_id, 'user_birthday', sanitize_text_field($_POST['bdaytime']));
    }

    if (($_POST['subscribed'] == "on")) {
        update_user_meta($user_id, '_email_newsletter', 1);
    }

}

add_action('user_register', 'tml_user_register');

function myplugin_user_register($user_id)
{

    if (($_POST['subscribed'] == "on")) {
        $subscription_lists = \MailPoet\API\API::MP('v1')->getLists();
        foreach ($subscription_lists as $list) {
            $subscriber = $_POST['user_email'];
            try {
                $subscriber = \MailPoet\API\API::MP('v1')->subscribeToList($subscriber, $list['id']);
            } catch (Exception $exception) {
                return $exception->getMessage();
            }
        }

    }

}

add_action('user_register', 'myplugin_user_register');

function add_new_menu()
{
    register_nav_menu('navbar-connected-upper-right', __('Header Logged in'));
}

add_action('init', 'add_new_menu');

function add_new_menu_profile()
{
    register_nav_menu('navbar-user-profile', __('User Profile'));
}

add_action('init', 'add_new_menu_profile');
function add_new_menu_blog()
{
    register_nav_menu('blog_menu', __('Blog'));
}

add_action('init', 'add_new_menu_blog');


// redirect to home after register

add_filter('registration_redirect', 'my_redirect_home');
function my_redirect_home($registration_redirect)
{
    return home_url();
}

add_filter('lostpassword_redirect', 'lostpassword_confirmation_email');
function lostpassword_confirmation_email($lostpassword_redirect)
{
    return esc_url(get_permalink(get_page_by_title('lostpassword_confirmation_email_dont_delete_or_rename_this_page')));
}

// show avatar short_code
// [show_user_avatar size="200"]
function show_user_avatar_func($atts)
{
    $current_user = wp_get_current_user();
    isset($atts["size"]) ? $size = $atts["size"] : $size = null;
    ob_start();
    ?>
    <div class="tml-user-avatar"><?php echo get_avatar($current_user->ID, $size); ?></div> <?php
    return ob_get_clean();
}

add_shortcode('show_user_avatar', 'show_user_avatar_func');

// delete user lin short code
// [link_confirm_delete_current_user texte="OUI"]
function link_confirm_delete_current_user_func($atts)
{
    isset($atts["texte"]) ? $texte = $atts["texte"] : $texte = null;
    $link = home_url() . '/deleteUser?confirm=OK';
    ob_start();
    ?> <a href="<?php echo $link ?>"> <?php echo $texte ?> </a> <?php
    return ob_get_clean();
}

add_shortcode('link_confirm_delete_current_user', 'link_confirm_delete_current_user_func');


add_action('personal_options_update', 'my_save_extra_profile_fields');
add_action('edit_user_profile_update', 'my_save_extra_profile_fields');

function my_save_extra_profile_fields($user_id)
{
    $theme_my_login = Theme_My_Login::get_object();
    $wp_error = new WP_Error();
    if (!current_user_can('edit_user', $user_id))
        return false;
    if (!empty($_POST['sexe'])) {
        update_user_meta($user_id, 'sexe', $_POST['sexe']);
    }
    if (!empty($_POST['bdaytime'])) {
        update_user_meta($user_id, 'bdaytime', sanitize_text_field($_POST['bdaytime']));
        update_user_meta($user_id, 'user_birthday', sanitize_text_field($_POST['bdaytime']));
    } else {
        $message = 'le champ renseigné n\'est pas correct';
        $wp_error->add_data('invalid_date_naissance', $message);
        $theme_my_login->errors = $wp_error;
        // die(var_dump($theme_my_login));

    }
//     update_user_meta( $user_id, 'lieu_naissance', $_POST['lieu_naissance'] );
// 	update_user_meta( $user_id, 'telephone', $_POST['telephone'] );
// 	update_user_meta( $user_id, 'raison_sociale', $_POST['raison_sociale'] );
// 	update_user_meta( $user_id, 'siret', $_POST['siret'] );
//     update_user_meta( $user_id, 'tva', $_POST['tva'] );
}

/**********upload images ***************/
// function enqueue_media_uploader()
// {
//     //this function enqueues all scripts required for media uploader to work
//     wp_enqueue_media();
//     wp_register_script('media-uploader-js', get_template_directory_uri().'/js/custom_uploader.js', array('jquery'));
//     wp_enqueue_script('media-uploader-js');
// }
// add_action("wp_enqueue_scripts", "enqueue_media_uploader");

// function kv_handle_attachment($file_handler,$post_id,$set_thu=false) {
// 	// check to make sure its a successful upload
// 	if ($_FILES[$file_handler]['error'] !== UPLOAD_ERR_OK) __return_false();

// 	require_once(ABSPATH . "wp-admin" . '/includes/image.php');
// 	require_once(ABSPATH . "wp-admin" . '/includes/file.php');
// 	require_once(ABSPATH . "wp-admin" . '/includes/media.php');

// 	$attach_id = media_handle_upload( $file_handler, $post_id );

//          // If you want to set a featured image frmo your uploads. 
// 	if ($set_thu) set_post_thumbnail($post_id, $attach_id);
// 	return $attach_id;
// }


/********** ajax ***********/
add_action('wp_ajax_cvf_upload_files', 'cvf_upload_files');
add_action('wp_ajax_nopriv_cvf_upload_files', 'cvf_upload_files'); // Allow front-end submission 

function cvf_upload_files()
{

    $parent_post_id = isset($_POST['post_id']) ? $_POST['post_id'] : 0;  // The parent ID of our attachments
    $valid_formats = array("jpg", "png", "gif", "bmp", "jpeg"); // Supported file types
    $max_file_size = 1132 * 703; // in kb
    $max_image_upload = 9; // Define how many images can be uploaded to the current post
    $wp_upload_dir = wp_upload_dir();
    $path = $wp_upload_dir['path'] . '/';
    $count = 0;

    $attachments = get_posts(array(
        'post_type' => 'attachment',
        'posts_per_page' => -1,
        'post_parent' => $parent_post_id,
        'exclude' => get_post_thumbnail_id() // Exclude post thumbnail to the attachment count
    ));

    // Image upload handler
    if ($_SERVER['REQUEST_METHOD'] == "POST") {
		// var_dump($_FILES['files']);die;
        // Check if user is trying to upload more than the allowed number of images for the current post
        // if( ( count( $attachments ) + count( $_FILES['files']['name'] ) ) > $max_image_upload ) {
        //     $upload_message[] = "Sorry you can only upload " . $max_image_upload . " images for each Ad";
        // } else {

        foreach ($_FILES['files']['name'] as $f => $name) {
            $extension = pathinfo($name, PATHINFO_EXTENSION);
			
			if($name == 'blob'){
				$extension = 'jpg';
			}
            // Generate a randon code for each file name
            $new_filename = cvf_td_generate_random_code(20) . '.' . $extension;

            if ($_FILES['files']['error'][$f] == 4) {
                continue;
            }
// var_dump($_FILES);die;
            if ($_FILES['files']['error'][$f] == 0) {
                // Check if image size is larger than the allowed file size
                if ($_FILES['files']['size'][$f] > $max_file_size) {
                    $upload_message[] = "$name is too large!.";
                    continue;

                    // Check if the file being uploaded is in the allowed file types
                } elseif (!in_array(strtolower($extension), $valid_formats)) {
                    $upload_message[] = "$name is not a valid format";
                    continue;
                } else {
                    // If no errors, upload the file...
                    if (move_uploaded_file($_FILES["files"]["tmp_name"][$f], $path . $new_filename)) {

                        $count++;

                        $filename = $path . $new_filename;
                        $filetype = wp_check_filetype(basename($filename), null);
                        $wp_upload_dir = wp_upload_dir();
                        $attachment = array(
                            'guid' => $wp_upload_dir['url'] . '/' . basename($filename),
                            'post_mime_type' => $filetype['type'],
                            'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
                            'post_content' => '',
                            'post_status' => 'inherit'
                        );
                        // Insert attachment to the database

                        $attach_id = wp_insert_attachment($attachment, $filename, $parent_post_id);
                        $url = wp_get_attachment_url($attach_id);
                        //var_dump(die($url));
                        require_once(ABSPATH . 'wp-admin/includes/image.php');

                        // Generate meta data
                        $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
                        wp_update_attachment_metadata($attach_id, $attach_data);

                    }
                }
            }
        }
        // }
    }
    // Loop through each error then output it to the screen
    if (isset($upload_message)) :
        foreach ($upload_message as $msg) {
            printf(__('<p class="bg-danger">%s</p>', 'wp-trade'), $msg);
        }
    endif;

    // If no error, show success message
    if ($count != 0) {
        //printf( __('<p class = "bg-success">%d files added successfully!</p>', 'wp-trade'), $count );  
        // printf($url);
        $data['id'] = $attach_id;
        $data['src'] = $url;
        echo json_encode($data);

    }

    exit();
}

// Random code generator used for file names.
function cvf_td_generate_random_code($length = 10)
{

    $string = '';
    $characters = "23456789ABCDEFHJKLMNPRTVWXYZabcdefghijklmnopqrstuvwxyz";

    for ($p = 0; $p < $length; $p++) {
        $string .= $characters[mt_rand(0, strlen($characters) - 1)];
    }

    return $string;

}

add_action('wp_ajax_delete_attachment', 'delete_attachment');
function delete_attachment($post)
{
    echo $_POST['att_ID'];
    $msg = 'Attachment ID [' . $_POST['att_ID'] . '] has been deleted!';
    if (wp_delete_attachment($_POST['att_ID'], true)) {
        echo $msg;
    }
    die();
}

add_image_size('product_thumbnail', 290, 260, true);

// advanced search form short code
// [advanced_searchform]
function advanced_searchform_func($atts)
{
    return get_template_part('advanced', 'searchform');
}

add_shortcode('advanced_searchform', 'advanced_searchform_func');

// advanced search form redirect after submit
function wpse_load_custom_search_template()
{
    if (isset($_REQUEST['search']) == 'advanced') {
        require('advanced-search-result.php');
        die();
    }
}

add_action('wp_loaded', 'wpse_load_custom_search_template');
/**************** Adding custom product field reservation date to woocommerce  ***********/

function add_dates_customer_field()
{
    //   session_start();
    $_SESSION['reserved_dates'] = "";
    if (isset($_REQUEST['reserved_dates'])) {
        $_SESSION['reserved_dates'] = $_REQUEST['reserved_dates'];
    }
    $value = isset($_SESSION['reserved_dates']) ? $_SESSION['reserved_dates'] : '';
    echo '<input type="hidden" name="reserved_dates" id="reserved_dates" class="datepicker_values" value="' . $value . '" />';
}

add_action('woocommerce_before_add_to_cart_button', 'add_dates_customer_field');

function dates_customer_validation($passed_validation, $product_id)
{

    $cart_item_data['reserved_dates'] = $_REQUEST['reserved_dates'];
    //var_dump($product_id);
    $reserved_dates = explode(',', $cart_item_data['reserved_dates']);
    $disponibilities = Anonnocesdisponibility::findReservedByProductId($product_id);
    foreach ($disponibilities as $value) {
        foreach ($reserved_dates as $reserved_date) {
            if ($value->date_value == $reserved_date)
                $passed_validation = false;

        }
    }
    if ($passed_validation == false) {
        wc_add_notice(__('Date déjà reservée  ;', 'woocommerce'), 'error');
        return false;
    }
    if (empty($_REQUEST['reserved_dates'])) {

        wc_add_notice(__('Merci de reserver au moins une date  ;', 'woocommerce'), 'error');

        return false;
    }

    return true;
}

add_action('woocommerce_add_to_cart_validation', 'dates_customer_validation', 10, 3);

function save_dates_customer_field($cart_item_data, $product_id)
{
    if (isset($_REQUEST['reserved_dates'])) {
        $id_dates = array();
        $cart_item_data['reserved_dates'] = $_REQUEST['reserved_dates'];
        /* below statement make sure every add to cart action as unique line item */
        $cart_item_data['unique_key'] = md5(microtime() . rand());
        $disponibilities = Anonnocesdisponibility::findByProductId($product_id);
        $reserved_dates = explode(',', $cart_item_data['reserved_dates']);
        $now = new DateTime('NOW');
        $now = $now->format('Y-m-d H:i:s');
        foreach ($disponibilities as $value) {
            foreach ($reserved_dates as $reserved_date) {
                if ($value->date_value == $reserved_date) {
                    Anonnocesdisponibility::update(array('reserved_by' => get_current_user_id(), 'reservation_date' => $now, 'item_cart_key' => $cart_item_data['unique_key']), array('ID' => $value->ID));
                    array_push($id_dates, $value->ID);

                }

            }
        }

        $cart_item_data['expire_at'] = time() + 30 * 60;
        $cart_item_data['reserved_dates_ids'] = $id_dates;
    }

    return $cart_item_data;
}

add_action('woocommerce_add_cart_item_data', 'save_dates_customer_field', 10, 2);

function do_this_in_an_one_minute()
{
    global $woocommerce;

    $disponibilities = Anonnocesdisponibility::all();
    foreach ($disponibilities as $disponibilitie) {
        if (!$disponibilitie->is_payed) {
            $date_res = strtotime($disponibilitie->reservation_date);
            $date_res += 1800;
            $now = time();
            if ($now > $date_res) {
                Anonnocesdisponibility::update(array('reserved_by' => '0', 'reservation_date' => null, 'item_cart_key' => null), array('ID' => $disponibilitie->ID));
            }
        }
    }
}

add_action('update_reserved_date', 'do_this_in_an_one_minute');

wp_schedule_single_event(time() + 2, 'update_reserved_date');


function render_meta_on_cart_and_checkout($cart_data, $cart_item = null)
{
    // WC()->session->__unset('reserved_d');
    $custom_items = array();
    /* Woo 2.4.2 updates */
    if (!empty($cart_data)) {
        $custom_items = $cart_data;
    }

    $custom_items[] = array("name" => 'X', "value" => $cart_item['reserved_dates']);

    return $custom_items;
}

add_filter('woocommerce_get_item_data', 'render_meta_on_cart_and_checkout', 10, 2);


function dates_order_meta_handler($item, $cart_item_key, $values, $order)
{
    if (isset($values['reserved_dates'])) {
        $item->add_meta_data('_reserved_dates', $values['reserved_dates']);
        $item->add_meta_data('_reserved_dates_ids', implode(',', $values['reserved_dates_ids']));
    }
}

add_action('woocommerce_checkout_create_order_line_item', 'dates_order_meta_handler', 10, 4);


// load categories by ajax 
add_action('wp_ajax_get_product_categories', 'get_product_categories');
add_action('wp_ajax_nopriv_get_product_categories', 'get_product_categories');

function get_product_categories()
{
    $parent_id = isset($_POST['parent_id']) ? $_POST['parent_id'] : 0;
    $selected_child_value = isset($_POST['selected_child_value']) ? $_POST['selected_child_value'] : 0;
    if ($parent_id > 0) {
        $taxonomy = 'product_cat';
        $orderby = 'name';
        $show_count = 0;      // 1 for yes, 0 for no
        $pad_counts = 0;      // 1 for yes, 0 for no
        $hierarchical = 0;      // 1 for yes, 0 for no
        $title = '';
        $empty = 0;      // 1 for yes, 0 for no

        $args = array(
            'taxonomy' => $taxonomy,
            'orderby' => $orderby,
            'show_count' => $show_count,
            'pad_counts' => $pad_counts,
            'hierarchical' => $hierarchical,
            'title_li' => $title,
            'hide_empty' => $empty,
            'parent' => $parent_id
        );
        $all_categories = get_categories($args);

        ob_start();
        ?>
        <option value=""
                disabled <?php if (!$selected_child_value) echo "selected"; ?>><?php _e('Besoins', 'bst-plus') ?></option>
        <?php foreach ($all_categories as $category) : ?>
            <option value="<?php echo sanitize_title_with_dashes($category->slug); ?>" <?php if ($selected_child_value == sanitize_title_with_dashes($category->name)) echo "selected"; ?>><?php echo $category->name; ?></option>
        <?php endforeach ?>
        <?php
        $options = ob_get_clean();

        echo $options;
    } else {
        echo 'error';
    }
    exit;

}

/************** Custom price item line ***************/

// Replace the item price by your custom calculation
add_action('woocommerce_before_calculate_totals', 'add_custom_item_price');
function add_custom_item_price($cart_object)
{


    foreach ($cart_object->get_cart() as $item_values) {

        ##  Get cart item data
        $item_id = $item_values['product_id']; // Product ID
        // $original_price = $item_values['data']->price; // Product original price
        $original_price = $item_values['data']->get_price();

        ## Get your custom fields values
        $dates = $item_values['reserved_dates'];


        $array_dates = explode(",", $dates);
        //var_dump($array_dates);

        $count = 0;
        foreach ($array_dates as $date) {
            $count++;
        }

        // CALCULATION FOR EACH ITEM:
        ## my own calculation  <==  <==  <==  <==
        $t_price = $original_price * $count;

        $default_commission = WC_Vendors::$pv_options->get_option('default_commission');
        $fee = $t_price - ($t_price * $default_commission / 100);
        $new_price = $t_price + $fee;
        ## Set the new item price in cart
        //   var_dump($item_values);
        $item_values['data']->set_price($new_price);

        $item_values['data']->old_price = $original_price;
        $item_values['data']->fee = $fee;
        $item_values['data']->price_without_fee = $t_price;
        //  var_dump($item_values['data']);

    }
}

// Calculate Total
add_action('woocommerce_calculate_totals', 'action_cart_calculate_totals', 10, 1);
function action_cart_calculate_totals($cart_object)
{

    if (is_admin() && !defined('DOING_AJAX'))
        return;
    if (!WC()->cart->is_empty()):
        ## Displayed subtotal (+10%)
        $default_commission = WC_Vendors::$pv_options->get_option('default_commission');
        $total_without_fees = 0;
        foreach ($cart_object->cart_contents as $item_key => $item) {
            $product_instance = new WC_Product_Factory();
            $product = $product_instance->get_product($item['product_id']);
            $reserved_dates = array_values(array_filter(explode(',', $item['reserved_dates'])));
            foreach ($reserved_dates as $key => $r_date) {
                $total_without_fees += $product->get_price();
            }
        }
        $cart_total = $cart_object->cart_contents_total;
        $fee = $cart_total - $total_without_fees;
        $cart_object->fee = $fee;
        $cart_object->subtotal = $total_without_fees;
        $cart_object->subtotal_ex_tax = $total_without_fees;

    endif;
}

// hide coupon field on cart page
function hide_coupon_field_on_cart($enabled)
{
    if (is_cart()) {
        $enabled = false;
    }
    return $enabled;
}

add_filter('woocommerce_coupons_enabled', 'hide_coupon_field_on_cart');

function remove_disponibilites_in_database($cart_item_key, $cart)
{
    $product_id = $cart->cart_contents[$cart_item_key]['product_id'];
    $disponibilities = Anonnocesdisponibility::all();
    foreach ($disponibilities as $value) {
        if ($cart->cart_contents[$cart_item_key]['unique_key'] == $value->item_cart_key) {
            Anonnocesdisponibility::update(array('reserved_by' => '0', 'reservation_date' => null, 'item_cart_key' => null), array('ID' => $value->ID));
        }
    }
}

add_action('woocommerce_remove_cart_item', 'remove_disponibilites_in_database', 10, 2);
add_filter('script_loader_tag', 'add_defer_attribute', 10, 2);
/* Adds async defer tag to the scripts and links for css to avoid content
blocking */
function add_defer_attribute($tag)
{
    if (is_admin()) {
        return $tag;
    }
    if (strpos($tag, '/wp-includes/js/jquery/jquery')) {
        return $tag;
    }
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 9.') !== false) {
        return $tag;
    } else {
        // array of scripts to defer
        $scripts_to_defer = array('some-script.js', 'custom.js');

        foreach ($scripts_to_defer as $defer_script) {
            if (true == strpos($tag, $defer_script))
                return str_replace(' src', ' defer="defer" src', $tag);
        }

        // array of scripts to async
        $scripts_to_async = array('comment-reply.min.js', 'custom.js');

        foreach ($scripts_to_async as $async_script) {
            if (true == strpos($tag, $async_script))
                return str_replace(' src', ' async="async" src', $tag);
        }
    }

    return $tag;
}

remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50);


/**
 * Register our sidebars and widgetized areas.
 *
 */
function checkout_left_widget()
{

    register_sidebar(array(
        'name' => 'Checkout left sidebar',
        'id' => 'checkout_left_1',
        'before_widget' => '<div class="checkout-widget">',
        'after_widget' => '</div>',
        'before_title' => '<h2 class="title-widget">',
        'after_title' => '</h2>',
    ));

}

add_action('widgets_init', 'checkout_left_widget');

add_filter('woocommerce_checkout_fields', 'alter_woocommerce_checkout_fields');
function alter_woocommerce_checkout_fields($fields)
{
    unset($fields['order']['order_comments']);
    return $fields;
}

remove_filter('woocommerce_enable_order_notes_field', 'filter_woocommerce_enable_order_notes_field', 10, 1);
/**
 * Get the custom fee.
 *
 * @access public
 * @return string
 */
function wc_cart_totals_custom_fee_html()
{
    echo WC()->cart->fee . "€";
}

/**** Add custom fields to Profil user admin panel ********/
function new_contact_methods($contactmethods)
{
    $contactmethods['lieu_naissance'] = 'Lieu de naissance';
    $contactmethods['telephone'] = 'Téléphone';
    $contactmethods['raison_sociale'] = 'Raison social';
    $contactmethods['siret'] = 'Siret';
    $contactmethods['tva'] = 'TVA';

    return $contactmethods;
}

add_filter('user_contactmethods', 'new_contact_methods', 10, 1);

// function new_modify_user_table( $column ) {
//     $column['lieu_naissance'] = 'Lieu de naissance';
//     $column['telephone'] = 'Téléphone';
//     $column['raison_sociale'] = 'Raison social';
//     $column['siret'] = 'Siret';
//     $column['tva'] = 'TVA';
//     return $column;
// }
// add_filter( 'manage_users_columns', 'new_modify_user_table' );

// function new_modify_user_table_row( $val, $column_name, $user_id ) {
//     switch ($column_name) {
//         case 'lieu_naissance' :
//             return get_the_author_meta( 'lieu_naissance', $user_id );
//             break;
//         case 'telephone' :
//             return get_the_author_meta('telephone', $user_id);
//             break;
//         default:
//     }
//     return $val;
// }
// add_action( 'wcvendors_shop_settings_saved', 'add_action("wcvendors_settings_after_paypal")');


add_action('wp_loaded', 'save_wc_fields');
// profile save extra Wc fields 
function save_wc_fields()
{

    if (isset($_REQUEST['profile-wc-fields']) == 'save-wc-extra-fields') {

        $user = get_userdata(get_current_user_id());
        //$user->set_role('vendor');


        $mangoPay = new mangopayWCMain();

        $mangoPay->bank_account_form(get_current_user_id());


        if (isset($_POST['user_nationality'])) {
            // New custom user meta field
            update_user_meta($user->ID, 'user_nationality', sanitize_text_field($_POST['user_nationality']));
        }

        if (isset($_POST['vendor_ot_country'])) {
            // WooCommerce billing country.
            update_user_meta($user->ID, 'billing_country', sanitize_text_field($_POST['vendor_ot_country']));
        }

        $errors = new WP_Error;
        //var_dump($errors);
        $mangoPay->validate_bank_account_data($errors, NULL, get_current_user_id());
        $e = $errors->get_error_code();
        // 	 var_dump($e);
        //die(var_dump($errors));
        if (empty($e)) {
            $x = $mangoPay->save_account_form(get_current_user_id());
            return true;

        }
        foreach ($errors->errors as $error) {
            echo '<div class="error"><p>';
            echo $error[0];
            echo '</p></div>';
        }
        return $errors;
        die();

    }
}

add_action('wp_ajax_edit_line_cart', 'edit_line_cart');
add_action('wp_ajax_nopriv_edit_line_cart', 'edit_line_cart');

function edit_line_cart()
{

    $key = $_POST['param'];

    $cart = WC()->instance()->cart;

    if ($cart->remove_cart_item($key)) {
        echo "réservation supprimée";
    }
    die();
}


// set completed status to order after payment success
add_action('woocommerce_thankyou', 'my_change_status_function');
function my_change_status_function($order_id)
{
    wc_clear_cart_after_payment();
    //$order->update_status( 'completed' );

}

add_action('woocommerce_order_status_completed', 'order_status_completed', 10, 1);
function order_status_completed($order_id)
{
    $order = new WC_Order($order_id);
    $items = $order->get_items();

    foreach ($items as $item) {
        $reserved_dates_ids = wc_get_order_item_meta($item->get_id(), '_reserved_dates_ids', true);
        $reserved_dates_ids = array_values(array_filter(explode(',', $reserved_dates_ids)));
        foreach ($reserved_dates_ids as $id) {
            Anonnocesdisponibility::update(array('is_payed' => 2), array('ID' => $id));
        }
    }
}

add_action('woocommerce_order_status_processing', 'order_status_processing', 10, 1);
function order_status_processing($order_id)
{
    $order = new WC_Order($order_id);
    $customer = $order->get_user();
    $customer_id = $customer->ID;
    $items = $order->get_items();

    foreach ($items as $item) {
        $reserved_dates_ids = wc_get_order_item_meta($item->get_id(), '_reserved_dates_ids', true);
        $reserved_dates_ids = array_values(array_filter(explode(',', $reserved_dates_ids)));
        foreach ($reserved_dates_ids as $id) {
            Anonnocesdisponibility::update(array('order_id' => $order_id, 'is_payed' => 1, 'reserved_by' => $customer_id), array('ID' => $id));
        }
    }
}

add_action('wp_ajax_send_email_comment', 'send_email_comment');
function send_email_comment()
{

    $admin_email = get_option('admin_email');
    $message = $_POST['msg'];
    $user = wp_get_current_user();

    $user_email = $user->user_email;
    $subject = "Réponse automatique :" . $_POST['title'];
    require "emails/avis.php";

    wp_mail($admin_email, $_POST['title'], $message, $headers = array('Content-Type: text/html; charset=UTF-8'), $attachments = array());
    wp_mail($user_email, $subject, $message_avis, $headers = array('Content-Type: text/html; charset=UTF-8'), $attachments = array());
    die();

}


// fix pagination bug
add_filter('redirect_canonical', 'pif_disable_redirect_canonical');

function pif_disable_redirect_canonical($redirect_url)
{
    if (is_singular()) $redirect_url = false;
    return $redirect_url;
}

/**************** Paramètres mot de passe**********************/
add_action('wp_ajax_checkpassword', 'checkpassword');
function checkpassword()
{
    $error = "";
    $param = $_POST['param'];
    $user = wp_get_current_user();
    $currentUserID = get_current_user_id();

    $user_info = get_userdata($currentUserID);

    $user_pass = $user_info->user_pass;
    require_once ABSPATH . 'wp-includes/class-phpass.php';
    $wp_hasher = new PasswordHash(8, TRUE);

    if ($wp_hasher->CheckPassword($param, $user_pass)) {

        echo $param;
    } else {
        $response["status"] = 1001; //Make up your own error codes! Yippee! Fun!
        $response["statusText"] = "Le mot de passe actuel renseigné n'est pas correct.";
        echo json_encode($response);
        // return;

    }
    die();
}


function modify_password_frontend()
{

    $user = wp_get_current_user();
    $currentUserID = get_current_user_id();

    $user_info = get_userdata($currentUserID);

    $user_pass = $user_info->user_pass;
//var_dump($user_pass);

    if (isset($_POST['submit'])) {
        $currentpass = $_POST['yourpass'];
        $newpass = $_POST['newpass'];
        $confirmpass = $_POST['comfirmpass'];

        $passhash = wp_hash_password($newpass);
//var_dump($passhash);

        global $wpdb;
//$update= wp_set_password($passhash, $currentUserID);
        $update = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->users} SET `user_pass` = %s WHERE `ID` = %d", array($passhash, $currentUserID)));

        if (!is_wp_error($update)) {

            ?>
            <div class="modal fade delete-modal" id="modal-change-pwd" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-body col-md-8 col-md-offset-2 text-center">
                            <img src="<?php echo site_url(); ?>/wp-content/uploads/2017/08/locker.png" width="7%"
                                 alt="info-modal">
                            <h4> <?php _e('Nouveau mot de passe mis à jour.', 'woocommerce'); ?></h4>
                        </div>

                        <div class="modal-footer col-md-8 col-md-offset-2">
                            <button type="button" class="btn btn-orange btn-lg edit-btn" data-dismiss="modal">OK
                            </button>

                        </div>
                    </div>
                </div>
            </div>
            <script type="text/javascript"> $(document).ready(function () {
                    $('#modal-change-pwd').modal("show");
                }); </script>
            <?php
            wp_cache_delete($currentUserID, 'users');
            wp_cache_delete($user->user_login, 'userlogins');
            // wp_logout();
            // if (wp_signon(array('user_login'=>$user->user_login,'user_password'=>$_POST['newpass']),false)):
            //   wp_redirect(admin_url());
            // endif;
            // ob_start();
        } else {
            wp_set_auth_cookie($currentUserID, true);
        }


    }
    wp_enqueue_script('password-strength-meter');
    ob_start();
    ?>
    <form method="post" action="" class="form-change-pwd">
        <div class="col-md-12">
            <div class="col-md-6 text-left">
                <h4>Mot de passe</h4>
                <label>Tapez votre mot de passe actuel</label>

                <input type="text" id="yourpass" name="yourpass">
                <label>Tapez votre nouveau mot de passe</label>
                <input type="text" name="newpass" id="newpass" data-reveal="1"
                       data-pw="<?php echo esc_attr(wp_generate_password(16)); ?>" autocomplete="off"
                       aria-describedby="password-strength">
                <div id="password-strength"></div>
                <label>Confirmez votre nouveau mot de passe</label>
                <input type="text" name="comfirmpass">
            </div>
            <div class="col-md-6"></div>
        </div>
        <div class="col-md-12">
            <div class="col-md-6">
                <p><strong><?php _e('Astuce: le mot de passe devrait contenir au moins 12 caractères.Pour le rendre
plus sûr, utilisez des lettres en majuscule et minuscule, des nombres et des symboles tels que ! " ? $ % ^ &.', 'woocommerce'); ?></strong>
                </p>
            </div>
            <div class="col-md-6 text-right">

                <input type="submit" name="submit" disabled="disabled" class="btn btn-orange edit-btn"
                       value="Enregistrer">
            </div>
        </div>
    </form>
    <?php
    $content = ob_get_clean();
    return ($content);
}

add_shortcode('modify_password_frontend', 'modify_password_frontend');

//allow redirection, even if my theme starts to send output to the browser
add_action('init', 'do_output_buffer');
function do_output_buffer()
{
    ob_start();
}


// send invoice email 

add_action('wp_loaded', 'send_envoice_mail_function');
// profile save extra Wc fields 
function send_envoice_mail_function()
{

    if (isset($_REQUEST['send_invoice_email']) == 'send_invoice_email') {
        if (isset($_GET['send_invoice_email'])) {
            $user_email = $_GET['user_email'];
            $data_url = $_GET['data_url'];
            $attachment = array($data_url);
            $f = file_get_contents($data_url);
            // die(var_dump($data_url));
            //  file_put_contents("Tmpfile.pdf", fopen(file_get_contents($data_url), 'r'));
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($user_email, "Facture Client", $data_url, $headers, $attachment);
        }
    }


}

function resultallprestation()
{
    $args = array(
        'numberposts' => 1000,
        'offset' => 0,
        'category' => 0,
        'orderby' => 'post_date',
        'order' => 'DESC',
        'show_all'           => false,
        'end_size'           => 1,
        'mid_size'           => 2,
        'prev_next'          => true,
        'prev_text'          => __('« Previous'),
        'next_text'          => __('Next »'),
        'type'               => 'plain',
        'add_args'           => false,
        'add_fragment'       => '',
        'before_page_number' => '',
        'after_page_number'  => '',
        'post_type' => 'product',
        'post_status' => 'publish',
        'suppress_filters' => true
    );




    $recent_posts = wp_get_recent_posts($args, ARRAY_A);
//var_dump($recent_posts);
    $products = [];
    $product_instance = new WC_Product_Factory();
    if ($recent_posts) {
        foreach ($recent_posts as $db_product) {
            array_push($products, $product_instance->get_product($db_product['ID']));
        }
    }
    $price_order = '';
    if (isset($_GET['price_order_all'])) {
        $price_order = sanitize_text_field($_GET['price_order_all']);

        $price = array();
        foreach ($products as $row) {
            array_push($price, $row->get_price());
        }

        if ($price_order == 'ASC') {
            array_multisort($price, SORT_ASC, $products);
            //	usort($products, "desc_price");
        }
        if ($price_order == 'DESC') {
            array_multisort($price, SORT_DESC, $products);
            //	usort($products, "asc_price");
        }

    }
    $date_order = '';
    if (isset($_GET['date_order_all'])) {
        $date_order = sanitize_text_field($_GET['date_order_all']);
        if (!function_exists('desc_date')) {
            function desc_date($a, $b)
            {
                return strtotime($b->get_date_modified()) - strtotime($a->get_date_modified());
            }
        }
        if (!function_exists('asc_date')) {
            function asc_date($a, $b)
            {
                return strtotime($a->get_date_modified()) - strtotime($b->get_date_modified());
            }
        }
        //	var_dump(function_exists('desc_date'));
        if ($date_order == 'ASC') {
            usort($products, "desc_date");
        }
        if ($date_order == 'DESC') {
            usort($products, "asc_date");
        }
    }
    //var_dump($products);
    ob_start();
    ?>

    <div class="whole-container full-width-template">
        <div class="container">
            <div class="row">

                <div class="col-md-12 result-side">
                    <div class="col-md-12 result-heading">
                        <div class="col-md-6 result-count">
                            <h4><?php _e('Annonces', 'bst-plus'); ?>(<?php echo count($recent_posts); ?>)</h4>
                            <hr>
                        </div>
                        <div class="col-md-6 result-sort">
                            <div class="form-inline pull-right">
                                <div class="form-group">
                                    <h4 class="text-blue"><?php _e('Trier par', 'bst-plus'); ?>&nbsp;&nbsp;</h4>
                                </div>
                                <div class="form-group">
                                    <select class="form-control" name="price_order_all" id="price_order_all"
                                            placeholder="<?php _e('Prix', 'bst-plus') ?>">
                                        <option selected value=""><?php _e('Prix', 'bst-plus') ?></option>
                                        <option value="ASC" <?php if ($price_order == 'ASC') echo 'selected'; ?>><?php _e('Croissant', 'bst-plus') ?></option>
                                        <option value="DESC" <?php if ($price_order == 'DESC') echo 'selected'; ?>><?php _e('Décroissant', 'bst-plus') ?></option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <select class="form-control" name="date_order_all" id="date_order_all"
                                            placeholder="<?php _e('Date d\'ajout ', 'bst-plus') ?>">
                                        <option selected value=""><?php _e('Date d\'ajout ', 'bst-plus') ?></option>
                                        <option value="ASC" <?php if ($date_order == 'ASC') echo 'selected'; ?>><?php _e('Récent > Ancien', 'bst-plus') ?></option>
                                        <option value="DESC" <?php if ($date_order == 'DESC') echo 'selected'; ?>><?php _e('Ancien > Récent', 'bst-plus') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php $i= 3 ; foreach ($products as $product):
                        if ($i%3==0): echo '<div class="row"></div>' ; endif ; $i++ ; ?>
							<?php 
								$product_categ_str = wc_get_product_category_list($product->get_id());
								$product_categ = explode(',',$product_categ_str);
								// var_dump($product_categ);die;
								
							?>

                            <div class="col-sm-6 col-md-4 ">
                                <div class="product-mini-content thumbnail">
                                    <div class="product-img"
                                         style="background: url('<?php echo wp_get_attachment_url($product->get_image_id()); ?>'); background-size:cover; background-position:center;"></div>
                                    <img class="hidden"
                                         src="<?php echo wp_get_attachment_url($product->get_image_id()); ?>"
                                         alt="<?php echo $product->get_title(); ?>">
                                    <div class="caption">
                                        <h4 class="product-title text-gray"><?php echo $product->get_title(); ?></h4>
                                        <p class="font-size-12-em text-gray"><?php echo (isset($product_categ[0])?$product_categ[0]:''); ?></p>
                                        <h3 class="text-orange no-margin"><?php echo (isset($product_categ[1])?$product_categ[1]:''); ?></h3>
                                        <h3 class="text-black no-margin"><?php echo $product->get_price(); ?>
                                            € <?php _e('', 'bst-plus') ?></h3>
                                        <div class="devider"></div>
                                        <h5 class="text-gray">
                                        <span class="product-icon picker"><img
                                                    src="<?php echo site_url() . "/wp-content/uploads/2017/09/local-picker.png"; ?>"></span>
                                            <?php echo $product->get_meta('_product_city'); ?>
                                        </h5>
                                        <p class="font-size-12-em text-gray">
                                            <?php $disponibilities = Anonnocesdisponibility::findByProductId($product->get_id()); ?>
                                            <?php if (count($disponibilities) > 0) : ?>
                                                <span class="product-icon calendar"><img
                                                            src="<?php echo site_url() . "/wp-content/uploads/2017/09/icon-date-picker.png"; ?>"></span>
                                                <?php echo date_format(date_create($disponibilities[0]->date_value), 'j M'); ?>
                                                <?php if (count($disponibilities) > 1) : ?>
                                                    &nbsp;-&nbsp;<?php echo date_format(date_create($disponibilities[count($disponibilities) - 1]->date_value), 'j M'); ?>
                                                    &nbsp;(<?php echo count($disponibilities); ?>&nbsp;<?php _e('dates dispo.', 'bst-plus') ?>)
                                                <?php endif ?>
                                            <?php else : ?>
                                                <span class="product-icon calendar"><img
                                                            src="<?php echo site_url() . "/wp-content/uploads/2017/09/icon-date-picker.png"; ?>"></span>&nbsp; (<?php _e('0 dates dispo', 'bst-plus') ?> .)
                                            <?php endif ?>
                                        </p>
                                        <div class="devider"></div>
                                        <div class="text-center">
                                            <a href="<?php echo $product->get_permalink(); ?>"
                                               class="btn btn-orange btn-lg  btn-huge edit-btn"><?php _e('Voir l\'annonce', 'bst-plus') ?></a>
                                        </div>
                                        <div class="devider"></div>
                                        <a href="<?php echo $product->get_permalink() ?>#section-comment"
                                           class="text-gray text-underline">
                                            <?php echo count(get_comments(array('post_id' => $product->get_id()))); ?>
                                            &nbsp;<?php _e('Commentaires', 'bst-plus') ?>
                                        </a>
                                        <p class="product-footer text-gray">
                                            <?php _e('Postée le', 'bst-plus') ?>&nbsp;
                                            <?php echo date_i18n(get_option('date_format'), strtotime($product->get_date_created())); ?>
                                            <?php if ($product->get_date_modified() != $product->get_date_created()) : ?>
                                                <?php _e('mise à jour le', 'bst-plus') ?>&nbsp;
                                                <?php echo date_i18n(get_option('date_format'), strtotime($product->get_date_modified())); ?>
                                            <?php endif ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div><!-- /.container -->
    </div>
    <?php
   
    return ob_get_clean();
}

add_shortcode('resultallprestation', 'resultallprestation');