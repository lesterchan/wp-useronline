-> Upgrade Instructions For Version 2.00 To Version 2.01
------------------------------------------------------------------
// Open wp-admin folder

Put:
------------------------------------------------------------------
useronline-upgrade.php
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


// Run wp-admin/useronline-upgrade.php

Note:
------------------------------------------------------------------
Please remember to remove useronline-upgrade.php after installation.
------------------------------------------------------------------










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
