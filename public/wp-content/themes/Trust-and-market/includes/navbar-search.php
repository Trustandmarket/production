<?php
/*
Navbar search form
==================
If you don't want a search form in your navbar, then delete the link to this PHP page from within the navbar in header.php.
Then you can insert the Search Widget into the sidebar.
*/
?>

<!--<form class="navbar-form navbar-right" role="search" method="get" id="searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">-->
<!--	<div class="form-group">-->
<!--		<input class="form-control" type="text" value="<?php echo get_search_query(); ?>" placeholder="<?php echo esc_attr_x('Recherchez un musicien, un photographe, ...', 'bst-plus') ?>" name="s" id="s">-->
<!--	</div>-->
<!--	<button type="submit" id="searchsubmit" value="<?php esc_attr_x('Search', 'bst-plus') ?>" class="btn btn-default"><i class="glyphicon glyphicon-search"></i></button>-->
<!--</form>-->

<ul class="nav navbar-nav pull-right search-ul-link">
	<li class="dropdown">
		<a href="#" class="dropdown-toggle" data-toggle="dropdown"><span class="glyphicon glyphicon-search"></span></a>
		<ul class="dropdown-menu">
			<li>
              	<form class="navbar-form navbar-left" role="search" method="get" id="searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				  <div class="form-group">
						<input class="form-control" type="text" value="<?php echo get_search_query(); ?>" placeholder="<?php echo esc_attr_x('Rechercher ...', 'bst-plus') ?>" name="s" id="s">
				  </div>
				  <button type="submit" id="searchsubmit"  class="btn btn-default btn-block submitBtn"><?php echo esc_attr_x('Rechercher', 'bst-plus') ?></button>
				</form>
          	</li>
		</ul>
	</li>
</ul>