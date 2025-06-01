<?php tha_footer_before(); ?>
<footer class="container-fluid site-footer">
    <?php tha_footer_top(); ?>
    <div class="container">
    <?php dynamic_sidebar('footer-widget-area'); ?>
    </div>
    <div class="row">
        <div class="col-lg-12 site-sub-footer">
            <p>© Trust & Market <?php echo date('Y'); ?>, <!--<a href="<?php echo home_url('/'); ?>"><?php bloginfo('name'); ?></a>-->tous droits réservés</p>
        </div>
    </div>
    <?php tha_footer_bottom(); ?>
</footer>
<!-- Push the footer to the bottom workaround -->
<script>
    function pushFooter(){$("body").css("min-height",window.screen.availHeight-$("footer").outerHeight()+"px"),$("footer").css("bottom","-"+$("footer").outerHeight()+"px")}$(document).ready(function(){pushFooter()});
</script>
<input type="hidden" value="<?php echo admin_url( "admin-ajax.php" ); ?>" id="urladmin">
<?php tha_footer_after(); ?>
<?php wp_footer(); ?>
<?php tha_body_bottom(); ?>
</div>
<a href="/votre-projet/" id="yourproject-iconed-link">votre-projet</a>
</body>
</html>