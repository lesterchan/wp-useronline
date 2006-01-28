<?php
/*
+----------------------------------------------------------------+
|																							|
|	WordPress 2.0 Plugin: WP-UserOnline 2.01								|
|	Copyright (c) 2005 Lester "GaMerZ" Chan									|
|																							|
|	File Written By:																	|
|	- Lester "GaMerZ" Chan															|
|	- http://www.lesterchan.net													|
|																							|
|	File Information:																	|
|	- Upgrade WP-UserOnline From 2.00 To 2.01								|
|	- wp-admin/useronline-upgrade.php											|
|																							|
+----------------------------------------------------------------+
*/


### Require Config
require('../wp-config.php');

### Variables, Variables, Variables
$current_timestamp = current_time('timestamp');
$alter_table = array();
$insert_options = array();
$error = '';

### Alter Tables (1 Table, 2 Times)
$alter_table[] = "ALTER TABLE $wpdb->useronline DROP PRIMARY KEY";
$alter_table[] = "ALTER TABLE $wpdb->useronline ADD UNIQUE (timestamp)";

### Insert Options  (2 Rows)
$insert_options[] ="INSERT INTO $wpdb->options VALUES (0, 0, 'useronline_most_users', 'Y', 1, '1', 20, 8, 'Most Users Ever Online Count', 1, 'yes');";
$insert_options[] ="INSERT INTO $wpdb->options VALUES (0, 0, 'useronline_most_timestamp', 'Y', 1, '$current_timestamp', 20, 8, 'Most Users Ever Online Date', 1, 'yes');";

### Check Whether There Is Any Pre Errors
$wpdb->show_errors = false;
$check_upgrade = $wpdb->get_var("SELECT option_value FROM $wpdb->options WHERE option_name = 'useronline_most_users'");
if($check_upgrade) {
	$error = __('You Had Already Installed WP-UserOnline.');
}
if(empty($wpdb->useronline)) {
	$error = __('Please Define The Useronline Table In wp-settings.php.');
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>WordPress &rsaquo; <?php _e('Upgrading'); ?> &rsaquo; <?php _e('WP-UserOnline 2.01'); ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<style type="text/css" media="screen">
		@import url( wp-admin.css );
	</style>
</head>
<body>
	<div class="wrap"> 
		<h2><?php _e('Upgrading WP-UserOnline 2.01'); ?></h2>
		<p><?php _e('This upgrade script will upgrade WP-UserOnline to version 2.01 for your Wordpress.'); ?></p>
		<p>
			<?php _e('This upgrade script will be doing the following:'); ?><br />
			<b>&raquo;</b> <b>1</b> <?php _e('table will be altered namely <b>useronline</b>.'); ?><br />
			<b>&raquo;</b> <b>2</b> <?php _e('options will be inserted into the <b>options</b> table.'); ?><br />
			<b>&raquo;</b> <b>2</b> <?php _e('tables will be optimized namely <b>useronline</b> and <b>options</b>.'); ?><br />
		</p>
		<?php
			if(empty($error)) {
				if(!empty($_POST['upgrade'])) {
					// Alter Table
					$alter_table_count = 0;
					echo "<p><b>".__('Altering Tables:')."</b>";
					foreach($alter_table as $altertable) {
						$wpdb->query($altertable);
					}
					$keys = $wpdb->get_row("SHOW INDEX FROM $wpdb->useronline");
					if($keys->Key_name == 'timestamp') { 
						echo "<br /><b>&raquo;</b> Table (<b>$wpdb->useronline</b>) altered. Primary Key(timestamp) changed to Unique Key(timestamp).";
						$alter_table_count++; 
					} else {
						echo "<br /><b>&raquo;</b> <font color=\"red\">Table (<b>$wpdb->useronline</b>) table NOT altered. Primary Key(timestamp) NOT changed to Unique Key(timestamp).</font>";
					}
					echo "<br /><b>&raquo;</b> <b>$alter_table_count / 1</b> Table Altered.</p>";
					// Insert Options
					$insert_options_count = 0;
					echo "<p><b>".__('Inserting Options:')."</b>";
					foreach($insert_options as $insertoptions) {
						$temp_options = $wpdb->query($insertoptions);
						$temp_option = explode(" ", $insertoptions);
						$temp_option = $temp_option[6];
						$temp_option = substr($temp_option, 1, -2);
						if($temp_options) {
								echo "<br /><b>&raquo;</b> Option (<b>$temp_option</b>) inserted.";
								$insert_options_count ++;
						} else {
							echo "<br /><b>&raquo;</b> <font color=\"red\">Option (<b>$temp_option</b>) NOT inserted.</font>";
						}
					}
					echo "<br /><b>&raquo;</b> <b>$insert_options_count / 2</b> Options Inserted.</p>";
					// Optimize Tables
					$optimize_table_count = 0;
					echo "<p><b>".__('Optimizing Tables:')."</b>";
					$optimize_tables = $wpdb->query("OPTIMIZE TABLE $wpdb->useronline, $wpdb->options");
					if($optimize_tables) {
						echo "<br /><b>&raquo;</b> Tables (<b>$wpdb->useronline</b>, <b>$wpdb->options</b>) optimized.";
						$optimize_table_count = 2;
					} else {
						echo "<br /><b>&raquo;</b> <font color=\"red\">Tables (<b>$wpdb->useronline</b>, <b>$wpdb->options</b>) NOT optimized.</font>";
					}
					echo "<br /><b>&raquo;</b> <b>$optimize_table_count / 2</b> Tables Optimized.</p>";
					// Check Whether Install Is Successful
					if($alter_table_count == 1 && $insert_options_count == 2) {
						echo '<p align="center"><b>'.__('WP-UserOnline Upgraded Successfully To Version 2.01.').'</b><br />'.__('Please remember to delete this file before proceeding on.').'</p>';
					}
				} else {
		?>
				<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
					<div align="center"><input type="submit" name="upgrade" value="<?php _e('Click Here To Upgrade WP-UserOnline 2.01'); ?>" class="button"></div>
				</form>
		<?php
				}
			} else {
				echo "<p align=\"center\"><font color=\"red\"><b>$error</b></font></p>\n";
			}
		?>
	</div>
</body>
</html>