-> Installation Instructions
--------------------------------------------------
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


// To Display Most Number Of Users Online

Use:
------------------------------------------------------------------
<?php if (function_exists('useronline')): ?>
	<p>Most Users Ever Online Is <?php get_most_useronline(); ?> On <?php get_most_useronline_date(); ?></p>
<?php endif; ?>
------------------------------------------------------------------

Note:
------------------------------------------------------------------
By default, it will be displayed in wp-useronline.php
------------------------------------------------------------------


// To Display Users Browsing A Page

Use:
------------------------------------------------------------------
<?php if (function_exists('useronline')): ?>
	<?php get_users_browsing_page(); ?>
<?php endif; ?>
------------------------------------------------------------------

Note:
------------------------------------------------------------------
Normally, you can place it in page.php or single.php
------------------------------------------------------------------