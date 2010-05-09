<?php
if ( !class_exists('scbLoad3') ) :
class scbLoad3 {

	private static $candidates;
	private static $loaded;

	static function init($rev, $file, $classes) {
		$dir = dirname($file);

		self::$candidates[$rev] = $dir;

		self::load($dir . '/', $classes);

		add_filter('pre_update_option_active_plugins', array(__CLASS__, 'reorder'));
	}

	static function reorder($active_plugins) {
		krsort(self::$candidates);

		$dir = dirname(plugin_basename(reset(self::$candidates)));

		$found = false;
		foreach ( $active_plugins as $i => $plugin ) {
			$plugin_dir = dirname($plugin);

			if ( $plugin_dir == $dir ) {
				$found = true;
				break;
			}
		}

		if ( !$found || 0 == $i )
			return $active_plugins;

		unset($active_plugins[$i]);
		array_unshift($active_plugins, $plugin);

		return $active_plugins;
	}

	private static function load($path, $classes) {
		foreach ( $classes as $class_name ) {
			if ( class_exists($class_name) )
				continue;

			$fpath = $path . substr($class_name, 3) . '.php';

			if ( file_exists($fpath) ) {
				self::$loaded[$class_name] = $fpath;
				include $fpath;
			}
		}
	}

	static function get_info() {
		krsort(self::$candidates);

		return array(self::$loaded, self::$candidates);
	}
}
endif;

scbLoad3::init(13, __FILE__, array(
	'scbUtil', 'scbOptions', 'scbForms', 'scbTable', 'scbDebug',
	'scbWidget', 'scbAdminPage', 'scbBoxesPage',
	'scbQuery', 'scbRewrite', 'scbCron',
));

