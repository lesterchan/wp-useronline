-> Installation Instructions
--------------------------------------------------
// Open wp-admin folder

Put:
------------------------------------------------------------------
useronline-install.php
------------------------------------------------------------------


// Open wp-content/plugins folder

Put:
------------------------------------------------------------------
useronline.php
------------------------------------------------------------------


// Open root Wordpress folder

Put:
------------------------------------------------------------------
wp-useronline.php
------------------------------------------------------------------


// Activate useronline plugin


// Run wp-admin/useronline-install.php


// Open wp-content/themes/<YOUR THEME NAME>/sidebar.php 

Add:
------------------------------------------------------------------
<?php if (function_exists('useronline')): ?>
<li>
	<h2>UserOnline</h2>
	<ul>
		<li><a href="<?php echo get_settings('home'); ?>/wp-useronline.php"><?php get_useronline(); ?></a></li>
	</ul>
</li>
<?php endif; ?>
------------------------------------------------------------------
