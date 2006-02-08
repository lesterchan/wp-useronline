-> Upgrade Instructions For Version 2.0x To Version 2.02
------------------------------------------------------------------
// Open wp-content/plugins folder

Overwrite:
------------------------------------------------------------------
useronline.php
------------------------------------------------------------------


// Open root Wordpress folder

Overwrite:
------------------------------------------------------------------
wp-useronline.php
------------------------------------------------------------------


// Deavtivate And Activate Back WP-UserOnline










-> Upgrade Instructions For Version 1.0x To Version 2.00
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


// Deavtivate And Activate Back WP-UserOnline



// Remove Previous Traces Of UserOnline Code In Your Theme


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
