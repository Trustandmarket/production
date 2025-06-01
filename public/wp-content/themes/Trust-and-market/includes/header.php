<!DOCTYPE html>
<?php tha_html_before(); ?>
<html class="no-js">

<head>
	<!-- Google Tag Manager -->
	<script>
	(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start' : new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-NQHXBV2');
	</script>
	<!-- End Google Tag Manager -->
	
    <?php tha_head_top(); ?>
    <title>
        <?php wp_title('•', true, 'right');// bloginfo('name'); ?>
    </title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport">
    <?php tha_head_bottom(); ?>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<!-- Google Tag Manager (noscript) -->
	<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-NQHXBV2"
	height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
	<!-- End Google Tag Manager (noscript) -->

    <?php tha_body_top(); ?>
    <!--[if lt IE 8]>
    <div class="alert alert-warning">
      You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> to improve your experience.
    </div>
    <![endif]-->
    <div class="ajax-loader">
    </div>
    <!-- Navigation menu for desktop -->
    <nav class="navbar navbar-static-top navbar-upper hidden-xs hidden-sm">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-upper">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
				<?php if(!(is_front_page() or is_home())){ ?>
					<div class="home-advanced-search-ajax" style="float: right;margin-top: -5px;">
						<?php echo do_shortcode('[wcas-search-form]'); ?>
					</div>
				<?php } ?>
                <a class="navbar-brand" href="<?php echo home_url('/'); ?>" title="<?php bloginfo('name'); ?>">
                  <img class="navbar-logo" src="<?php echo  get_theme_mod( 'gootheme_logo' )  ?>" alt="logo Trust & Market" >
                  <?php 
				  /* <span class="navbar-brand-name"><svg xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" id="svg8" version="1.1" viewBox="0 0 80.433602 6.8791666" height="6.8791666mm" width="80.433601mm"> <defs id="defs2"> <filter id="Adobe_OpacityMaskFilter" filterUnits="userSpaceOnUse" x="-3157.8" y="3974.3" width="2457.1001" height="981.5"> <feColorMatrix type="matrix" values="1 0 0 0 0 0 1 0 0 0 0 0 1 0 0 0 0 0 1 0" id="feColorMatrix4519"/> </filter> <filter id="Adobe_OpacityMaskFilter_1_" filterUnits="userSpaceOnUse" x="1621.7" y="3974.3" width="2457.1001" height="981.5"> <feColorMatrix type="matrix" values="1 0 0 0 0 0 1 0 0 0 0 0 1 0 0 0 0 0 1 0" id="feColorMatrix4660"/> </filter> <filter id="Adobe_OpacityMaskFilter_2_" filterUnits="userSpaceOnUse" x="-3157.8" y="3974.3" width="2457.1001" height="981.5"> <feColorMatrix type="matrix" values="1 0 0 0 0 0 1 0 0 0 0 0 1 0 0 0 0 0 1 0" id="feColorMatrix4767"/> </filter> <filter id="Adobe_OpacityMaskFilter_3_" filterUnits="userSpaceOnUse" x="1621.7" y="3974.3" width="2457.1001" height="981.5"> <feColorMatrix type="matrix" values="1 0 0 0 0 0 1 0 0 0 0 0 1 0 0 0 0 0 1 0" id="feColorMatrix4794"/> </filter> <filter id="Adobe_OpacityMaskFilter_4_" filterUnits="userSpaceOnUse" x="214" y="-4.1999998" width="35.200001" height="14.1"> <feColorMatrix type="matrix" values="1 0 0 0 0 0 1 0 0 0 0 0 1 0 0 0 0 0 1 0" id="feColorMatrix5033"/> </filter> </defs> <metadata id="metadata5"> <rdf:RDF> <cc:Work rdf:about=""> <dc:format>image/svg+xml</dc:format> <dc:type rdf:resource="http://purl.org/dc/dcmitype/StillImage"/> <dc:title></dc:title> </cc:Work> </rdf:RDF> </metadata> <g transform="translate(103.70135,-130.23338)" id="layer1"> <g style="fill:#ffffff" transform="matrix(0.02138047,0,0,0.02138047,-122.64431,-4.7634457)" id="g4652"> <g style="fill:#ffffff" id="g4650"> <path style="fill:#ffffff" id="path4626" d="m 1059.2,6574.6 c 0,17.6 6.1,20.5 26.2,25 5.3,0.8 6.1,4.1 4.5,7.4 l -3.3,8.2 c -1.2,2 -3.3,3.3 -7,3.3 H 975.5 c -3.3,0 -7.4,-0.8 -5.3,-7 l 2.5,-7 c 1.6,-4.5 4.9,-4.9 7.8,-5.7 10.3,-2 20.9,-8.6 20.9,-24.2 v -214.4 h -95.1 c -5.7,0 -11.1,-1.6 -5.7,-8.6 l 9,-14.8 c 3.7,-4.5 7.8,-5.3 14.3,-5.3 h 231.2 c 5.7,0 11.1,1.6 5.7,8.6 l -9,14.8 c -3.7,4.5 -7.8,5.3 -14.3,5.3 h -78.3 z"/> <path style="fill:#ffffff" id="path4628" d="m 1446.6,6618.4 h -61.5 c -6.6,0 -9.4,-6.6 -11.5,-9.8 l -64.4,-114 h -30.8 v 80 c 0,17.6 6.2,20.5 26.2,25 5.3,0.8 6.2,4.1 4.5,7.4 l -3.3,8.2 c -1.2,2 -3.3,3.3 -7,3.3 h -104.1 c -3.3,0 -7.4,-0.8 -5.3,-7 l 2.5,-7 c 1.6,-4.5 4.9,-4.9 7.8,-5.7 10.3,-2 20.9,-8.6 20.9,-24.2 v -196 c 0,-17.2 -8.2,-24.2 -25.8,-27.1 -6.2,-1.6 -9.8,-2.9 -7.8,-7.4 l 3.3,-7.8 c 1.6,-3.7 4.5,-4.9 7,-4.9 H 1340 c 38.1,0 75.9,21.7 76.3,70.5 0,32.8 -17.2,66 -56.2,83.6 36.9,64 70.1,109.9 86.5,119.3 z m -168.1,-145.9 h 25.4 c 28.7,0 53.3,-20.9 52.5,-66 -0.8,-41 -19.7,-52.9 -45.1,-52.9 h -32.8 z"/> <path style="fill:#ffffff" id="path4630" d="m 1561.4,6507.7 c 0,40.2 11.9,85.3 64.4,85.3 42.2,0 67.2,-27.1 67.2,-85.7 v -132 c 0,-17.6 -6.1,-20.5 -26.2,-25 -5.3,-0.8 -6.1,-4.1 -4.5,-7.4 l 3.3,-8.2 c 1.2,-2 3.3,-3.3 7,-3.3 h 76.3 c 3.3,0 7.4,0.8 5.3,7 l -2.5,7 c -1.6,4.5 -4.9,4.9 -7.8,5.7 -10.3,2 -20.9,8.6 -20.9,24.2 v 132.4 c 0,93.9 -59,115.6 -103.3,115.6 -91.4,0 -116,-52.5 -116,-114.4 v -133.7 c 0,-17.6 -6.1,-20.5 -26.2,-25 -5.3,-0.8 -6.1,-4.1 -4.5,-7.4 l 3.3,-8.2 c 1.2,-2 3.3,-3.3 7,-3.3 h 104.1 c 3.3,0 7.4,0.8 5.3,7 l -2.5,7 c -1.6,4.5 -4.9,4.9 -7.8,5.7 -10.3,2 -20.9,8.6 -20.9,24.2 v 132.5 z"/> <path style="fill:#ffffff" id="path4632" d="m 1942.3,6393.8 c -3.3,2.9 -11.1,-13.9 -16.4,-24.6 -8.2,-16.4 -20.5,-22.5 -37.3,-22.5 -16.4,0 -36.9,10.7 -40.2,31.6 -4.5,29.5 16.8,44.7 50.4,67.2 44.7,29.9 71.8,52.5 73,90.6 2.1,60.3 -50.4,87.3 -107,87.3 -23.4,0 -44.7,-2.9 -58.2,-7.4 -21.3,-6.6 -21.3,-7 -21.3,-17.6 v -39.8 c 0,-4.5 -1.6,-10.7 2.5,-12.3 l 13.1,-4.9 c 4.5,-1.2 6.6,16.4 16.4,33.2 8.2,16.4 24.2,25 41.8,25 30.3,0 52.9,-19.7 54.1,-48.4 2,-35.7 -34.9,-56.2 -66.8,-78.7 -24.2,-17.2 -56.6,-31.6 -56.6,-73.8 0,-37.3 34,-72.2 99.2,-72.2 22.5,0 37.3,3.3 49.6,8.2 12.3,4.9 18.9,8.6 18,15.6 v 30.3 c 0,1.6 -1.2,2.9 -2.5,4.5 z"/> <path style="fill:#ffffff" id="path4634" d="m 2152.6,6574.6 c 0,17.6 6.2,20.5 26.2,25 5.3,0.8 6.2,4.1 4.5,7.4 l -3.3,8.2 c -1.2,2 -3.3,3.3 -7,3.3 h -104 c -3.3,0 -7.4,-0.8 -5.3,-7 l 2.5,-7 c 1.6,-4.5 4.9,-4.9 7.8,-5.7 10.3,-2 20.9,-8.6 20.9,-24.2 v -214.4 h -95.1 c -5.7,0 -11.1,-1.6 -5.7,-8.6 l 9,-14.8 c 3.7,-4.5 7.8,-5.3 14.3,-5.3 h 231.2 c 5.7,0 11.1,1.6 5.7,8.6 l -9,14.8 c -3.7,4.5 -7.8,5.3 -14.3,5.3 h -78.3 v 214.4 z"/> <path style="fill:#ffffff" id="path4636" d="m 2671.3,6589.7 c 7,7 18.9,14.3 18.9,19.7 v 8.6 H 2643 c -8.6,0 -15.2,-3.3 -22.6,-9.8 l -23.8,-23 c -16.8,17.2 -45.1,38.1 -91.8,38.1 -64.8,0 -107.8,-36.5 -107.8,-84.5 0,-31.2 20.9,-64.4 69.3,-77.1 -17.2,-18 -44.7,-38.1 -44.7,-70.1 0,-38.1 29.5,-65.2 83.2,-65.2 18,0 31.2,2.9 43.9,7.8 9.4,4.1 15.2,7.4 14.8,12.7 v 28.3 c 0,1.2 -1.2,2.5 -2.5,3.7 l -8.6,6.2 c -1.2,0.8 -3.3,0.4 -4.1,-1.6 l -7.8,-15.2 c -5.3,-10.3 -18.9,-19.7 -36.1,-19.7 -19.7,0 -34,9.8 -34,29.1 0,19.7 14.8,39 38.5,61.5 l 95.1,92.7 c 10.7,-23.8 11.9,-69.3 -2.5,-87.7 h -42.6 c -5.7,0 -11.5,-1.6 -5.7,-7.8 l 12.3,-12.7 c 4.5,-4.1 9,-4.5 15.6,-4.5 h 116 c 5.7,0 11.5,2 5.7,7.8 l -12.3,12.7 c -4.5,4.1 -9,4.5 -15.6,4.5 h -42.2 c 6.6,21.3 6.1,69.3 -11.5,102.1 z m -184.9,-111.9 c -29.1,14.3 -34,41.8 -34,59.9 0,34.8 26.7,61.1 62.3,61.1 27.9,0 50.8,-13.5 64.8,-31.6 z"/> <path style="fill:#ffffff" id="path4638" d="m 3183,6574.6 c 0,17.6 6.2,20.5 26.2,25 5.3,0.8 6.2,4.1 4.5,7.4 l -3.3,8.2 c -1.2,2 -3.3,3.3 -7,3.3 h -104.1 c -3.3,0 -7.4,-0.8 -5.3,-7 l 2.5,-7 c 1.6,-4.5 4.9,-4.9 7.8,-5.7 10.3,-2 20.9,-8.6 20.9,-24.2 v -164.4 h -0.8 l -97.2,199.3 c -2.5,4.9 -4.5,9 -7.8,9 h -11.9 c -2.5,0 -5.7,-5.7 -9,-13.1 l -90.6,-195.2 h -0.8 v 164.4 c 0,17.6 6.2,20.5 26.2,25 5.3,0.8 6.2,4.1 4.5,7.4 l -3.3,8.2 c -1.2,2 -3.3,3.3 -7,3.3 h -74.6 c -3.3,0 -7.4,-0.8 -5.3,-7 l 2.5,-7 c 1.6,-4.5 4.9,-4.9 7.8,-5.7 10.3,-2 20.9,-8.6 20.9,-24.2 V 6379 c -11.9,-17.2 -25.4,-25.8 -33.6,-27.5 -8.2,-2 -9,-4.9 -7.4,-8.6 l 3.3,-8.2 c 1.2,-1.6 3.3,-3.3 7,-3.3 h 65.2 c 13.5,0 16.4,1.6 23.4,16.8 l 92.3,194.8 99.6,-198.4 c 5.3,-11.1 11.5,-13.1 17.2,-13.1 h 65.2 c 4.1,0 7.8,0.8 5.7,7.4 l -2.5,7.8 c -1.6,4.5 -5.7,5.3 -10.3,6.6 -12.3,2.9 -20.9,7.8 -20.9,25.4 z"/> <path style="fill:#ffffff" id="path4640" d="m 3529.4,6614.3 c -2,3.7 -5.3,4.1 -10.3,4.1 h -96.4 c -4.1,0 -7.8,-1.2 -5.7,-6.6 l 3.7,-8.2 c 3.7,-8.2 25.8,-4.5 17.6,-29.5 l -16,-41.4 h -102.9 l -18.5,46.7 c -7.8,21.7 24.6,17.6 19.3,27.5 l -2.5,4.5 c -1.2,2 -2.5,7 -8.2,7 h -63.6 c -7.8,0 -16.4,1.6 -12.7,-7.8 l 2,-5.7 c 1.2,-3.3 4.5,-4.5 10.7,-6.2 17.2,-4.1 20.1,-6.6 28.7,-27.1 l 70.5,-181.2 c 7.4,-19.7 -2.9,-26.7 -7.4,-31.2 -9.4,-9 -28.7,-6.2 -23,-16.4 l 2,-6.6 c 1.6,-4.5 5.3,-4.9 9.8,-4.9 h 70.9 c 5.7,0 9,5.7 10.3,11.9 28.3,82 62.7,165.2 89.4,228.4 7.8,20.1 10.3,21.3 30.8,26.7 4.1,1.2 6.6,3.7 5.3,7 z m -116,-106.1 -42.2,-111.1 -43.1,111.1 z"/> <path style="fill:#ffffff" id="path4642" d="m 3815.6,6618.4 h -61.5 c -6.6,0 -9.4,-6.6 -11.5,-9.8 l -64.4,-114 h -30.8 v 80 c 0,17.6 6.2,20.5 26.2,25 5.3,0.8 6.2,4.1 4.5,7.4 l -3.3,8.2 c -1.2,2 -3.3,3.3 -7,3.3 h -104.1 c -3.3,0 -7.4,-0.8 -5.3,-7 l 2.5,-7 c 1.6,-4.5 4.9,-4.9 7.8,-5.7 10.3,-2 20.9,-8.6 20.9,-24.2 v -196 c 0,-17.2 -8.2,-24.2 -25.8,-27.1 -6.2,-1.6 -9.8,-2.9 -7.8,-7.4 l 3.3,-7.8 c 1.6,-3.7 4.5,-4.9 7,-4.9 H 3709 c 38.1,0 75.9,21.7 76.3,70.5 0,32.8 -17.2,66 -56.2,83.6 36.9,64 70.1,109.9 86.5,119.3 z m -168.1,-145.9 h 25.4 c 28.7,0 53.3,-20.9 52.5,-66 -0.8,-41 -19.7,-52.9 -45.1,-52.9 h -32.8 z"/> <path style="fill:#ffffff" id="path4644" d="m 3936.1,6463.9 h 0.8 l 108.7,-132.4 h 45.5 v 11.9 c 0,11.9 -20.9,6.2 -45.5,36.1 l -64,77.1 c 38.1,48.8 95.9,120.1 111.5,135.3 6.6,6.6 19.3,13.1 19.3,17.2 v 9.4 h -50.8 c -17.2,0 -25.4,-12.3 -44.3,-34.4 l -80.4,-106.6 h -0.8 v 97.2 c 0,17.6 6.2,20.5 26.2,25 5.3,0.8 6.2,4.1 4.5,7.4 l -3.3,8.2 c -1.2,2 -3.3,3.3 -7,3.3 h -104.1 c -3.3,0 -7.4,-0.8 -5.3,-7 l 2.5,-7 c 1.6,-4.5 4.9,-4.9 7.8,-5.7 10.3,-2 20.9,-8.6 20.9,-24.2 v -199.3 c 0,-17.6 -6.2,-20.5 -26.2,-25 -5.3,-0.8 -6.2,-4.1 -4.5,-7.4 l 3.3,-8.2 c 1.2,-2 3.3,-3.3 7,-3.3 H 3962 c 3.3,0 7.4,0.8 5.3,7 l -2.5,7 c -1.6,4.5 -4.9,4.9 -7.8,5.7 -10.3,2 -20.9,8.6 -20.9,24.2 z"/> <path style="fill:#ffffff" id="path4646" d="m 4223.5,6451.2 h 94.7 c 5.3,0 7.8,2.5 5.3,6.1 l -7.4,12.7 c -2.9,4.5 -6.6,5.7 -13.1,5.7 h -79.5 v 79.5 c 0,30.8 7.4,38.5 29.9,38.5 h 33.6 c 40.2,0 45.9,-34.4 51.7,-32.8 l 10.3,3.3 c 1.6,0.4 2.5,2 2.5,3.7 v 40.2 c 0,6.6 -7.8,10.3 -18.9,10.3 h -126.7 c -31.2,0 -40.2,0.4 -40.2,-28.3 v -211.6 c 0,-17.2 -8.2,-24.2 -25.8,-27.1 -6.1,-1.6 -9.8,-2.9 -7.8,-7.4 l 3.3,-7.8 c 1.6,-3.7 4.5,-4.9 7,-4.9 h 178.8 c 7.8,0 12.7,4.5 11.9,10.3 v 32.8 c 0,1.2 -0.8,2.5 -2,3.7 l -7.4,6.1 c -6.1,4.9 -12.3,-28.3 -39.8,-28.3 h -60.3 v 95.3 z"/> <path style="fill:#ffffff" id="path4648" d="m 4531.8,6574.6 c 0,17.6 6.1,20.5 26.2,25 5.3,0.8 6.1,4.1 4.5,7.4 l -3.3,8.2 c -1.2,2 -3.3,3.3 -7,3.3 h -104.1 c -3.3,0 -7.4,-0.8 -5.3,-7 l 2.5,-7 c 1.6,-4.5 4.9,-4.9 7.8,-5.7 10.3,-2 20.9,-8.6 20.9,-24.2 v -214.4 h -95.1 c -5.7,0 -11.1,-1.6 -5.7,-8.6 l 9,-14.8 c 3.7,-4.5 7.8,-5.3 14.3,-5.3 h 231.2 c 5.7,0 11.1,1.6 5.7,8.6 l -9,14.8 c -3.7,4.5 -7.8,5.3 -14.3,5.3 h -78.3 z"/> </g> </g> </g> <style type="text/css" id="style4485">.st0{filter:url(#Adobe_OpacityMaskFilter);}.st1{fill:url(#SVGID_2_);}.st2{mask:url(#SVGID_1_);}.st3{fill:none;stroke:#676766;stroke-width:19;stroke-miterlimit:10;}.st4{fill:none;stroke:#676766;stroke-width:21;stroke-miterlimit:10;}.st5{fill:#FFFFFF;}.st6{filter:url(#Adobe_OpacityMaskFilter_1_);}.st7{fill:url(#SVGID_4_);}.st8{mask:url(#SVGID_3_);}.st9{filter:url(#Adobe_OpacityMaskFilter_2_);}.st10{fill:url(#SVGID_6_);}.st11{mask:url(#SVGID_5_);}.st12{filter:url(#Adobe_OpacityMaskFilter_3_);}.st13{fill:url(#SVGID_8_);}.st14{mask:url(#SVGID_7_);}.st15{fill:#010202;}.st16{filter:url(#Adobe_OpacityMaskFilter_4_);}.st17{fill:url(#SVGID_10_);}.st18{mask:url(#SVGID_9_);}</style></svg></span>
				  */ ?>
                </a>
            </div>
            <div class="search-status">
                <?php
                    wp_nav_menu( array(
                        'theme_location'    => 'header-menu-right-cart',
                        'depth'             => 3,
                        'menu_class'        => 'nav navbar-nav navbar-right',
                        'fallback_cb'       => 'wp_bootstrap_navwalker::fallback',
                        'walker'            => new wp_bootstrap_navwalker())
                    );
                ?>
                <?php get_template_part('includes/navbar-search'); ?>
                <?php
                  if ( is_user_logged_in() ):
                      $current_user = wp_get_current_user();
          
                  if ( ($current_user instanceof WP_User) ) {
                    //  echo 'Welcome : ' . $current_user->display_name ;
                      echo '<ul class="nav navbar-nav pull-right avatar-in-nav"><li class="menu-item"><a href="'. get_site_url() .'/tableau-de-bord">' .get_avatar( $current_user->user_email, 32 );
                      echo '</a></li></ul>';
                  }
                  endif;
                ?>
            </div>
            <div class="collapse navbar-collapse" id="navbar-upper">
              <?php
                  wp_nav_menu( array(
                      'theme_location'    => is_user_logged_in() ?  'navbar-connected-upper-right' : 'navbar-upper-right',
                      'depth'             => 3,
                      'menu_class'        => 'nav navbar-nav navbar-right',
                      'fallback_cb'       => 'wp_bootstrap_navwalker::fallback',
                      'walker'            => new wp_bootstrap_navwalker())
                  );
              ?>
            </div>
            <!-- /.navbar-collapse -->
        </div>
        <!-- /.container -->
    </nav>
    <!-- Navigation menu for mobile -->
    <nav class="navbar mobile-navbar navbar-upper fixed-top hidden-md hidden-lg">
        <div class="container">
            <button class="navbar-toggler" type="button" aria-label="Toggle navigation">
                <span class="glyphicon glyphicon-menu-hamburger"></span>
            </button>
            <a class="navbar-brand" href="<?php echo home_url('/'); ?>">
              <img class="navbar-logo" src="<?php echo  get_theme_mod( 'gootheme_logo' )  ?>" alt="logo Trust & Market">
            </a>
            <div class="mobile-menu hidden" id="mobileNavbar">
                <div class="top-info">
                  <a class="navbar-brand" href="<?php echo home_url('/'); ?>">
                    <img class="navbar-logo" src="<?php echo  get_theme_mod( 'gootheme_logo' )  ?>" alt="logo Trust & Market">
                    <span class="navbar-brand-name"><?php bloginfo('name'); ?></span>
                  </a>
                  <?php
                      wp_nav_menu( array(
                          'theme_location'    => 'header-menu-right-cart',
                          'depth'             => 3,
                          'menu_class'        => 'nav navbar-nav navbar-right',
                          'fallback_cb'       => 'wp_bootstrap_navwalker::fallback',
                          'walker'            => new wp_bootstrap_navwalker())
                      );
                  ?>
                  <?php
                    if ( is_user_logged_in() ):
                        $current_user = wp_get_current_user();
            
                    if ( ($current_user instanceof WP_User) ) {
                        echo '<div class="custom-avatar"> <a href="'. get_site_url() .'/tableau-de-bord">' .get_avatar( $current_user->user_email, 32 );
                        echo '</a></div>';
                    }
                    endif;
                  ?>
                </div>
                <ul class="navbar-nav ml-auto">
                    <?php
                        wp_nav_menu( array(
                            'theme_location'    => is_user_logged_in() ?  'navbar-connected-upper-right' : 'navbar-upper-right',
                            'depth'             => 3,
                            'menu_class'        => 'mobile-menu-items',
                            'fallback_cb'       => 'wp_bootstrap_navwalker::fallback',
                            'walker'            => new wp_bootstrap_navwalker())
                        );
                    ?>
                </ul>
            </div>
            <div class="backdrop hidden" data-toggle="mobileNavbar"></div>
            <div class="user-info">
              <?php
                    wp_nav_menu( array(
                        'theme_location'    => 'header-menu-right-cart',
                        'depth'             => 3,
                        'menu_class'        => 'user-menu-cart',
                        'fallback_cb'       => 'wp_bootstrap_navwalker::fallback',
                        'walker'            => new wp_bootstrap_navwalker())
                    );
                ?>
                <?php get_template_part('includes/navbar-search'); ?>
            </div>
        </div>
    </nav>
    <!-- Script for backdrop logic -->
    <script async defer>
      $(document).ready(function(){$(".backdrop").each(function(){$(this).click(function(e){$("#"+e.currentTarget.dataset.toggle).toggleClass("hidden"),$(this).toggleClass("hidden")})}),$(".navbar-toggler").click(function(){$("#mobileNavbar").toggleClass("hidden"),$(".backdrop").toggleClass("hidden")})});
    </script>