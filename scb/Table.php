<?php

// Takes care of creating, updating and deleting database tables
class scbTable {
	protected $name;
	protected $columns;

	function __construct($name, $file, $columns) {
		global $wpdb;

		$this->name = $wpdb->$name = $wpdb->prefix . $name;
		$this->columns = $columns;

		register_activation_hook($file, array($this, 'install'));
		scbUtil::add_uninstall_hook($file, array($this, 'uninstall'));
	}

	function install() {
		global $wpdb;

		$charset_collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			if ( ! empty($wpdb->charset) )
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			if ( ! empty($wpdb->collate) )
				$charset_collate .= " COLLATE $wpdb->collate";
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta("CREATE TABLE $this->name ($this->columns) $charset_collate;");
	}

	function uninstall() {
		global $wpdb;

		$wpdb->query("DROP TABLE IF EXISTS $this->name");
	}
}

