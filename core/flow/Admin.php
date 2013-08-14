<?php
/**
 * Admin.php
 *
 * @author Jonathan Davis
 * @version 1.0
 * @copyright Ingenesis Limited, January, 2010
 * @package shopp
 * @subpackage admin
 **/

defined( 'WPINC' ) || header( 'HTTP/1.1 403' ) & exit; // Prevent direct access

/**
 * ShoppAdmin
 *
 * @author Jonathan Davis
 * @package admin
 * @since 1.1
 **/
class ShoppAdmin extends FlowController {

	private $pages = array();	// Defines a map of pages to create menus from
	private $menus = array();	// Map of page names to WP screen ids for initialized Shopp menus
	private $mainmenu = false;	// The hook name of the main menu (Orders)

	public $Ajax = array();		// List of AJAX controllers
	public $Page = false;		// The current Page
	public $menu = false;		// The current menu

	protected $tabs = array();
	protected $tab = false;

	/**
	 * @public $caps
	 **/
	public $caps = array(                          	            	// Initialize the capabilities, mapping to pages
		'main' => 'shopp_menu',                                  	//
		'orders' => 'shopp_orders',                              	// Capabilities						Role
		'customers' => 'shopp_customers',                        	// _______________________________________________
		'reports' => 'shopp_financials',                         	//
		'memberships' => 'shopp_products',                       	// shopp_settings					administrator
		'products' => 'shopp_products',                          	// shopp_settings_checkout
		'categories' => 'shopp_categories',                      	// shopp_settings_payments
		'discounts' => 'shopp_promotions',                      	// shopp_settings_shipping
		'system' => 'shopp_settings',        		           		// shopp_settings_taxes
		'system-payments' => 'shopp_settings_payments',      	  	// shopp_settings_system
		'system-shipping' => 'shopp_settings_shipping',      	  	// shopp_settings_update
		'system-taxes' => 'shopp_settings_taxes',            	  	// shopp_financials					shopp-merchant
		'system-advanced' => 'shopp_settings_system',              	// shopp_financials					shopp-merchant
		'system-storage' => 'shopp_settings_system',              	// shopp_financials					shopp-merchant
		'setup' => 'shopp_settings',                   		       	// shopp_settings_taxes
		'setup-core' => 'shopp_settings',                          	// shopp_settings_taxes
		'setup-management' => 'shopp_settings',            		  	// shopp_settings_presentation
		'setup-pages' => 'shopp_settings_presentation',       		// shopp_promotions
		'setup-presentation' => 'shopp_settings_presentation',		// shopp_products
		'setup-checkout' => 'shopp_settings_checkout',				// shopp_products
		'setup-downloads' => 'shopp_settings_checkout',				// shopp_products
		'setup-images' => 'shopp_settings_presentation',    	  	// shopp_categories
	);

	/**
	 * Admin constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function __construct () {
		parent::__construct();

		$this->legacyupdate();

		// Dashboard widget support
		add_action('wp_dashboard_setup', array('ShoppAdminDashboard', 'init'));

		add_action('admin_init', array($this, 'tinymce'));
		add_action('load-plugins.php', array($this, 'pluginspage'));
		add_action('switch_theme', array($this, 'themepath'));
		add_filter('favorite_actions', array($this, 'favorites'));
		add_filter('shopp_admin_boxhelp', array($this, 'support'));
		add_action('load-update.php', array($this, 'styles'));
		add_action('admin_menu', array($this, 'taxonomies'), 100);

		// WordPress theme menus
		add_action('load-nav-menus.php',array($this, 'navmenus'));
		add_action('wp_update_nav_menu_item', array($this, 'navmenu_items'));
		add_action('wp_setup_nav_menu_item',array($this, 'navmenu_setup'));

		add_filter('wp_dropdown_pages', array($this, 'storefront_pages'));

		$this->pages();

		wp_enqueue_style('shopp.menu',SHOPP_ADMIN_URI.'/styles/menu.css',array(),SHOPP_VERSION,'screen');

		// Set the currently requested page and menu
		if ( isset($_GET['page']) && false !== strpos($_GET['page'], basename(SHOPP_PATH)) ) $page = $_GET['page'];
		else return;

		if ( isset($this->pages[ $page ]) ) $this->Page = $this->pages[ $page ];
		if ( isset($this->menus[ $page ]) ) $this->menu = $this->menus[ $page ];

	}

	/**
	 * Defines the Shopp pages used to create WordPress menus
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return void
	 **/
	private function pages () {

		// Orders menu
		$this->addpage('orders',    			Shopp::__('Orders'),		'ShoppAdminService');
		$this->addpage('customers', 			Shopp::__('Customers'),		'ShoppAdminAccount');
		$this->addpage('reports',  				Shopp::__('Reports'),		'ShoppAdminReport');

		// System tabs
		$this->addpage('system', 				Shopp::__('System'),		'ShoppAdminSystem');
		$this->addpage('system-payments',		Shopp::__('Payments'),		'ShoppAdminSystem',	'system');
		$this->addpage('system-shipping',		Shopp::__('Shipping'),		'ShoppAdminSystem',	'system');
		$this->addpage('system-taxes',			Shopp::__('Taxes'),			'ShoppAdminSystem',	'system');
		$this->addpage('system-storage',		Shopp::__('Storage'),		'ShoppAdminSystem',	'system');
		$this->addpage('system-advanced',		Shopp::__('Advanced'),		'ShoppAdminSystem',	'system');

		// Setup tabs
		$this->addpage('setup', 				Shopp::__('Setup'),			'ShoppAdminSetup');
		$this->addpage('setup-core',			Shopp::__('Shopp Setup'),	'ShoppAdminSetup', 'setup');
		$this->addpage('setup-management',		Shopp::__('Management'),	'ShoppAdminSetup', 'setup');
		$this->addpage('setup-presentation',	Shopp::__('Presentation'),	'ShoppAdminSetup', 'setup');
		$this->addpage('setup-checkout',		Shopp::__('Checkout'),		'ShoppAdminSetup', 'setup');
		$this->addpage('setup-downloads',		Shopp::__('Downloads'),		'ShoppAdminSetup', 'setup');
		$this->addpage('setup-pages',			Shopp::__('Pages'),			'ShoppAdminSetup', 'setup');
		$this->addpage('setup-images',			Shopp::__('Images'),		'ShoppAdminSetup', 'setup');

		// Catalog menu
		$this->addpage('products',   Shopp::__('Products'),   'ShoppAdminWarehouse',  'products');
		$this->addpage('categories', Shopp::__('Categories'), 'ShoppAdminCategorize', 'products');

		$taxonomies = get_object_taxonomies(Product::$posttype, 'object');
		foreach ( $taxonomies as $t ) {
			if ( 'shopp_category' == $t->name ) continue;
			$pagehook = str_replace('shopp_', '', $t->name);
			$this->addpage($pagehook, $t->labels->menu_name, 'ShoppAdminCategorize',  'products');
		}
		$this->addpage('discounts', Shopp::__('Discounts'), 'ShoppAdminDiscounter', 'products');

		// Filter hook for adding/modifying Shopp page definitions
		$this->pages = apply_filters('shopp_admin_pages', $this->pages);
		$this->caps = apply_filters('shopp_admin_caps', $this->caps);

		reset($this->pages);
		$this->mainmenu = key($this->pages);
	}

	/**
	 * Generates the Shopp admin menu
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function menus () {
		global $menu, $submenu, $plugin_page;

		$access = 'shopp_menu';
		if ( Shopp::maintenance() ) $access = 'manage_options';

		// Add main menus
		$position = shopp_admin_add_menu(Shopp::__('Shopp'), 'orders', 40, false, 'shopp_orders');
		shopp_admin_add_menu(Shopp::__('Catalog'), 'products', $position, false, 'shopp_products');

		// Add after the Shopp menus to avoid being purged by the duplicate separator check
		$menu[ $position - 1 ] = array( '', 'read', 'separator-shopp', '', 'wp-menu-separator' );

		// Add menus to WordPress admin
		foreach ($this->pages as $page) $this->submenus($page);

		$parent = get_admin_page_parent();

		if ( isset($this->menus[ $parent ]) && false === strpos($this->menus[ $parent ], 'toplevel') ) {
			$current_page = $plugin_page;
			$plugin_page = $parent;
			add_action('adminmenu', create_function('','global $plugin_page; $plugin_page = "' . $current_page. '";'));
		}

		// Add admin JavaScript & CSS
		add_action('admin_enqueue_scripts', array($this, 'behaviors'),50);

		if ( Shopp::maintenance() ) return;

		// Add contextual help menus
		foreach ($this->menus as $pagename => $screen)
			add_action("load-$screen", array($this, 'help'));

	}

	/**
	 * Registers a new page to the Shopp admin pages
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $name The internal reference name for the page
	 * @param string $label The label displayed in the WordPress admin menu
	 * @param string $controller The name of the controller to use for the page
	 * @param string $parent The internal reference for the parent page
	 * @return void
	 **/
	private function addpage ( string $name, string $label, string $controller, string $parent = null) {
		$page = $this->pagename($name);

		if ( isset($parent) ) $parent = $this->pagename($parent);
		$this->pages[ $page ] = new ShoppAdminPage($name, $page, $label, $controller, $parent);
	}

	/**
	 * Adds a ShoppAdminPage entry to the WordPress menus under the Shopp menus
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param mixed $page A ShoppAdminPage object
	 * @return void
	 **/
	private function submenus ( ShoppAdminPage $Page ) {

		$Shopp = Shopp::object();
		$name = $Page->name;
		$pagehook = $Page->page;

		// Set capability
		$capability = isset($this->caps[ $name ]) ? $this->caps[ $name ] : 'none';
		$taxonomies = get_object_taxonomies(Product::$posttype, 'names');
		if ( in_array("shopp_$name", $taxonomies) ) $capability = 'shopp_categories';

		// Set controller (callback handler)
		$controller = array($Shopp->Flow, 'admin');
		if ( shopp_setting_enabled('display_welcome') && empty($_POST['setup']) )
			$controller = array($this, 'welcome');
		if ( Shopp::maintenance() ) $controller = array($this, 'reactivate');

		$menu = $Page->parent ? $Page->parent : $this->mainmenu;

		shopp_admin_add_submenu(
			$Page->label,
			$pagehook,
			$menu,
			$controller,
			$capability
		);

	}

	/**
	 * Gets the Shopp-internal name of the main menu
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @return string The menu name
	 **/
	public function mainmenu () {
		return $this->mainmenu;
	}

	/**
	 * Gets or add a ShoppAdmin menu entry
	 *
	 * @author Jonathan Davis
	 * @since 1.3
	 *
	 * @param string $name The Shopp-internal name of the menu
	 * @param string $menu The WordPress screen ID
	 * @return string The screen id of the given menu name
	 **/
	public function menu ( string $name, string $menu = null ) {

		if ( isset($menu) ) $this->menus[ $name ] = $menu;
		if ( isset($this->menus[ $name ]) ) return $this->menus[ $name ];

		return false;

	}

	/**
	 * Provide admin support for custom Shopp taxonomies
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	public function taxonomies () {
		global $menu,$submenu;
		if (!is_array($submenu)) return;

		$taxonomies = get_object_taxonomies(Product::$posttype);
		foreach ($submenu['shopp-products'] as &$submenus) {
			$taxonomy_name = str_replace('-','_',$submenus[2]);
			if (!in_array($taxonomy_name,$taxonomies)) continue;
			$submenus[2] = 'edit-tags.php?taxonomy='.$taxonomy_name;
			add_filter('manage_edit-'.$taxonomy_name.'_columns', array($this,'taxonomy_cols'));
			add_filter('manage_'.$taxonomy_name.'_custom_column', array($this,'taxonomy_product_column'), 10, 3);
		}

		add_action('admin_print_styles-edit-tags.php',array($this, 'styles'));
		add_action('admin_head-edit-tags.php', array($this,'taxonomy_menu'));
	}

	public function taxonomy_menu () {
		global $parent_file,$taxonomy;
		$taxonomies = get_object_taxonomies(Product::$posttype);
		if (in_array($taxonomy,$taxonomies)) $parent_file = 'shopp-products';
	}

	public function taxonomy_cols ($cols) {
		return array(
			'cb' => '<input type="checkbox" />',
			'name' => __('Name'),
			'description' => __('Description'),
			'slug' => __('Slug'),
			'products' => __('Products','Shopp')
		);
	}

	public function taxonomy_product_column ($markup, $name, $term_id) {
		global $taxonomy;
		if ('products' != $name) return;
		$term = get_term($term_id,$taxonomy);
		return '<a href="admin.php?page=shopp-products&'.$taxonomy.'='.$term->slug.'">'.$term->count.'</a>';
	}

	/**
	 * Takes an internal page name reference and builds the full path name
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $page The internal reference name for the page
	 * @return string The fully qualified resource name for the admin page
	 **/
	public function pagename ($page) {
		$base = sanitize_key(SHOPP_DIR);
		return "$base-$page";
	}

	public function storefront_pages ($menu) {
		$CatalogPage = ShoppPages()->get('catalog');
		$shoppid = ShoppCatalogPage::frontid(); // uses impossibly long number ("Shopp" in decimal)

		$id = "<select name='page_on_front' id='page_on_front'>\n";
		if ( false === strpos($menu,$id) ) return $menu;
		$token = '<option value="0">&mdash; Select &mdash;</option>';

		if ( $shoppid == get_option('page_on_front') ) $selected = ' selected="selected"';
		$storefront = '<optgroup label="' . __('Shopp','Shopp') . '"><option value="' . $shoppid . '"' . $selected . '>' . esc_html($CatalogPage->title()) . '</option></optgroup><optgroup label="' . __('WordPress') . '">';

		$newmenu = str_replace($token,$token.$storefront,$menu);

		$token = '</select>';
		$newmenu = str_replace($token,'</optgroup>'.$token,$newmenu);
		return $newmenu;
	}

	/**
	 * Gets the name of the controller for the current request or the specified page resource
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $page (optional) The fully qualified reference name for the page
	 * @return string|boolean The name of the controller or false if not available
	 **/
	public function controller ( $page = false ) {

		if ( ! $page && isset($this->Page->controller) )
			return $this->Page->controller;

		if ( isset($this->pages[ $page ]) && isset($this->pages[ $page ]->controller) )
			return $this->pages[ $page ]->controller;

		$screen = get_current_screen();

		return false;
	}

	/**
	 * Dynamically includes necessary JavaScript and stylesheets for the admin
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	public function behaviors () {
		global $wp_version,$hook_suffix;
		if ( ! in_array($hook_suffix, $this->menus)) return;
		$this->styles();

		shopp_enqueue_script('shopp');

		$settings = array_filter(array_keys($this->pages), array($this,'get_settings_pages'));
		if ( in_array($this->Page->page, $settings) ) shopp_enqueue_script('settings');

	}

	/**
	 * Queues the admin stylesheets
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void Description...
	 **/
	public function styles () {

		global $taxonomy;
		if ( isset($taxonomy) ) { // Prevent loading styles if not on Shopp taxonomy editor
			$taxonomies = get_object_taxonomies(Product::$posttype);
			if (!in_array($taxonomy, $taxonomies)) return;
		}

		wp_enqueue_style('shopp.colorbox',SHOPP_ADMIN_URI.'/styles/colorbox.css',array(),'20110801','screen');
		wp_enqueue_style('shopp.admin',SHOPP_ADMIN_URI.'/styles/admin.css',array(),'20110801','screen');
		wp_enqueue_style('shopp.icons',SHOPP_ADMIN_URI.'/styles/icons.css',array(),'20110801','screen');
		if ( 'rtl' == get_bloginfo('text_direction') )
			wp_enqueue_style('shopp.admin-rtl',SHOPP_ADMIN_URI.'/styles/rtl.css',array(),'20110801','all');

	}

	/**
	 * Adds contextually appropriate help information to interfaces
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void
	 **/
	public function help () {

		$screen = get_current_screen();
		$pagename = array_search($screen->id, $this->menus);

		$prefix = $this->pagename('');
		if ( false === strpos($pagename, $prefix) )
			$pagename = $this->pagename($pagename);

		if ( ! isset($this->pages[ $pagename ]) ) return;

		$page = $this->pages[ $pagename ];
		$screenname = $page->name;

		if ( file_exists(SHOPP_PATH . "/core/ui/help/$screenname.php") )
			return include SHOPP_PATH . "/core/ui/help/$screenname.php";

		get_current_screen()->add_help_tab(array(
			'id' => 'shopp-help',
			'title' => __('Help'),
			'content' => $content
		));

	}

	/**
	 * Returns a postbox help link to launch help screencasts
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $id The ID of the help resource
	 * @return string The anchor tag for the help link
	 **/
	public function boxhelp ($id) {
		$helpurl = add_query_arg(array('src'=>'help','id'=>$id),admin_url('admin.php'));
		return apply_filters('shopp_admin_boxhelp','<a href="'.esc_url($helpurl).'" class="shoppui-question"></a>');
	}

	/**
	 * Displays the welcome screen
	 *
	 * @return boolean
	 * @author Jonathan Davis
	 **/
	public function welcome () {
		$Shopp = Shopp::object();
		if (shopp_setting('display_welcome') == "on" && empty($_POST['setup'])) {
			include(SHOPP_ADMIN_PATH."/help/welcome.php");
			return true;
		}
		return false;
	}

	/**
	 * Displays the re-activate screen
	 *
	 * @return boolean
	 * @author Jonathan Davis
	 **/
	public function reactivate () {
		$Shopp = Shopp::object();
		include(SHOPP_ADMIN_PATH."/help/reactivate.php");
	}

	/**
	 * Adds a 'New Product' shortcut to the WordPress admin favorites menu
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param array $actions List of actions in the menu
	 * @return array Modified actions list
	 **/
	public function favorites ($actions) {
		$key = esc_url(add_query_arg(array('page' => $this->pagename('products'), 'id' => 'new'), 'admin.php'));
	    $actions[$key] = array(Shopp::__('New Product'), 8);
		return $actions;
	}

	/**
	 * Update the stored path to the activated theme
	 *
	 * Automatically updates the Shopp theme path setting when the
	 * a new theme is activated.
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function themepath () {
		shopp_set_setting('theme_templates',addslashes(sanitize_path(STYLESHEETPATH.'/'."shopp")));
	}

	/**
	 * Report the current status of Shopp support
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return boolean
	 **/
	public function support ( $status = true ) {
		if ( ! ShoppSupport::activated() ) return false;
		return $status;
	}

	/**
	 * Helper callback filter to identify editor-related pages in the pages list
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $pagename The full page reference name
	 * @return boolean True if the page is identified as an editor-related page
	 **/
	public function get_editor_pages ($pagenames) {
		$filter = '-edit';
		if (substr($pagenames,strlen($filter)*-1) == $filter) return true;
		else return false;
	}

	/**
	 * Helper callback filter to identify settings pages in the pages list
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @param string $pagename The page's full reference name
	 * @return boolean True if the page is identified as a settings page
	 **/
	public function get_settings_pages ($pagenames) {
		$filter = '-settings';
		if (strpos($pagenames,$filter) !== false) return true;
		else return false;
	}

	/**
	 * Initializes the Shopp TinyMCE plugin
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @return void Description...
	 **/
	public function tinymce () {
		if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) return;

		$len = strlen(ABSPATH); $p = '';
		for($i = 0; $i < $len; $i++) $p .= 'x'.dechex(ord(substr(ABSPATH,$i,1))+$len);

		// Add TinyMCE buttons when using rich editor
		if ('true' == get_user_option('rich_editing')) {
			global $pagenow,$plugin_page;
			$pages = array('post.php', 'post-new.php', 'page.php', 'page-new.php');
			$editors = array('shopp-products','shopp-categories');
			if(!(in_array($pagenow, $pages) || (in_array($plugin_page, $editors) && !empty($_GET['id']))))
				return false;

			wp_enqueue_script('shopp-tinymce',admin_url('admin-ajax.php').'?action=shopp_tinymce',array());
			wp_localize_script('shopp-tinymce', 'ShoppDialog', array(
				'title' => __('Insert Product Category or Product', 'Shopp'),
				'desc' => __('Insert a product or category from Shopp...', 'Shopp'),
				'p' => $p
			));

			add_filter('mce_external_plugins', array($this,'mceplugin'),5);
			add_filter('mce_buttons', array($this,'mcebutton'),5);
		}
	}

	/**
	 * Adds the Shopp TinyMCE plugin to the list of loaded plugins
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param array $plugins The current list of plugins to load
	 * @return array The updated list of plugins to laod
	 **/
	public function mceplugin ($plugins) {
		// Add a changing query string to keep the TinyMCE plugin from being cached & breaking TinyMCE in Safari/Chrome
		$plugins['Shopp'] = SHOPP_ADMIN_URI.'/behaviors/tinymce/tinyshopp.js?ver='.time();
		return $plugins;
	}

	/**
	 * Adds the Shopp button to the TinyMCE editor
	 *
	 * @author Jonathan Davis
	 * @since 1.0
	 *
	 * @param array $buttons The current list of buttons in the editor
	 * @return array The updated list of buttons in the editor
	 **/
	public function mcebutton ($buttons) {
		array_push($buttons, "|", "Shopp");
		return $buttons;
	}

	/**
	 * Handle auto-updates from Shopp 1.0
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function legacyupdate () {
		global $plugin_page;

		if ($plugin_page == 'shopp-settings-update'
			&& isset($_GET['updated']) && $_GET['updated'] == 'true') {
				wp_redirect(add_query_arg('page',$this->pagename('orders'),admin_url('admin.php')));
				exit();
		}
	}

	/**
	 * Suppress the standard WordPress plugin update message for the Shopp listing on the plugin page
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	public function pluginspage () {
		remove_action('after_plugin_row_'.SHOPP_PLUGINFILE,'wp_plugin_update_row');
	}

	/**
	 * Adds ShoppPages and SmartCollection support to WordPress theme menus system
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void
	 **/
	public function navmenus () {
		if (isset($_REQUEST['add-shopp-menu-item']) && isset($_REQUEST['menu-item'])) {
			// $pages = Storefront::pages_settings();

			$nav_menu_selected_id = isset( $_REQUEST['menu'] ) ? (int) $_REQUEST['menu'] : 0;

			foreach ((array)$_REQUEST['menu-item'] as $key => $item) {
				if (!isset($item['menu-item-shopp-page'])) continue;

				$requested = $item['menu-item-shopp-page'];

				$Page = ShoppPages()->get($requested);

				$menuitem = &$_REQUEST['menu-item'][$key];
				$menuitem['menu-item-db-id'] = 0;
				$menuitem['menu-item-object-id'] = $requested;
				$menuitem['menu-item-object'] = $requested;
				$menuitem['menu-item-type'] = ShoppPages::QUERYVAR;
				$menuitem['menu-item-title'] = $Page->title();
			}

		}
		add_meta_box( 'add-shopp-pages', __('Catalog Pages'), array('ShoppUI','shoppage_meta_box'), 'nav-menus', 'side', 'low' );
		add_meta_box( 'add-shopp-collections', __('Catalog Collections'), array('ShoppUI','shopp_collections_meta_box'), 'nav-menus', 'side', 'low' );
	}

	/**
	 * Filters menu items to set the type labels shown for WordPress theme menus
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @param object $menuitem The menu item object
	 * @return object The menu item object
	 **/
	public function navmenu_setup ($menuitem) {

		switch ( $menuitem->type ) {
			case 'shopp_page':       $menuitem->type_label = 'Shopp'; break;
			case 'shopp_collection': $menuitem->type_label = 'Collection'; break;

		}

		return $menuitem;
	}

	static function screen () {
		return get_current_screen()->id;
	}

	public function tabs ( $page ) {
		if ( ! isset($this->pages[ $page ]) ) return false;
		$tabs = array_filter($this->pages, create_function('$submenu','return ( "' . $page . '" == $submenu->parent );') );
		return $tabs;
	}

} // END class ShoppAdmin

/**
 * ShoppAdminPage class
 *
 * A property container for Shopp's admin page meta
 *
 * @author Jonathan Davis
 * @since 1.1
 * @package admin
 **/
class ShoppAdminPage {

	public $name = '';
	public $page = '';
	public $label = '';
	public $controller = '';
	public $parent = false;

	public function __construct ( string $name, string $page, string $label, string $controller, string $parent = null ) {
		$this->name = $name;
		$this->page = $page;
		$this->label = $label;
		$this->controller = $controller;
		$this->parent = $parent;
	}

	public function hook () {
		global $admin_page_hooks;
		if ( isset($admin_page_hooks[ $this->parent ]) ) return $admin_page_hooks[ $this->parent ];
		return 'shopp';
	}

} // END class ShoppAdminPage


class ShoppUI {

	static function button ( string $button, string $name, array $options = array()) {
		$buttons = array(
			'add' => array('class' => 'add', 'title' => Shopp::__('Add'), 'icon' => 'shoppui-plus', 'type' => 'submit'),
			'delete' => array('class' => 'delete', 'title' => Shopp::__('Delete'), 'icon' => 'shoppui-minus', 'type' => 'submit')
		);

		if ( isset($buttons[ $button ]) )
			$options = array_merge($buttons[ $button ], $options);

		$types = array('submit','button');
		if ( ! in_array($options['type'], $types))
			$options['type'] = 'submit';

		extract($options, EXTR_SKIP);

		return '<button type="' . $type . '" name="' . $name . '"' . inputattrs($options) . '><span class="' . $icon . '"><span class="hidden">' . $title . '</span></span></button>';
	}

	static function template ($ui,$data=array()) {
		$ui = str_replace(array_keys($data),$data,$ui);
		return preg_replace('/\${[-\w]+}/','',$ui);
	}


	/**
	 * Register column headers for a particular screen.
	 *
	 * Compatibility function for Shopp list table views
	 *
	 * @since 1.2
	 *
	 * @param string $screen The handle for the screen to add help to. This is usually the hook name returned by the add_*_page() functions.
	 * @param array $columns An array of columns with column IDs as the keys and translated column names as the values
	 * @see get_column_headers(), print_column_headers(), get_hidden_columns()
	 */
	static function register_column_headers($screen, $columns) {
		$wp_list_table = new ShoppAdminListTable($screen, $columns);
	}

	/**
	 * Prints column headers for a particular screen.
	 *
	 * @since 1.2
	 */
	static function print_column_headers($screen, $id = true) {
		$wp_list_table = new ShoppAdminListTable($screen);

		$wp_list_table->print_column_headers($id);
	}

	static function table_set_pagination ($screen, $total_items, $total_pages, $per_page ) {
		$wp_list_table = new ShoppAdminListTable($screen);

		$wp_list_table->set_pagination_args( array(
			'total_items' => $total_items,
			'total_pages' => $total_pages,
			'per_page' => $per_page
		) );

		return $wp_list_table;
	}


	/**
	 * Registers the Shopp Collections meta box in the WordPress theme menus screen
	 *
	 * @author Jonathan Davis
	 * @since 1.2
	 *
	 * @return void Description...
	 **/
	public static function shopp_collections_meta_box () {
		global $_nav_menu_placeholder, $nav_menu_selected_id;
		$Shopp = Shopp::object();

		$removed_args = array(
			'action',
			'customlink-tab',
			'edit-menu-item',
			'menu-item',
			'page-tab',
			'_wpnonce',
		);

		?>
		<br />
		<div class="shopp-collections-menu-item customlinkdiv" id="shopp-collections-menu-item">
			<div id="tabs-panel-shopp-collections" class="tabs-panel tabs-panel-active">

				<ul class="categorychecklist form-no-clear">

				<?php
					$collections = $Shopp->Collections;
					foreach ($collections as $slug => $CollectionClass):
						$menu = get_class_property($CollectionClass,'_menu');
						if ( ! $menu ) continue;
						$Collection = new $CollectionClass();
						$Collection->smart();
						$_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;
				?>
					<li>
						<label class="menu-item-title">
						<input type="checkbox" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-shopp-collection]" value="<?php echo $slug; ?>" class="menu-item-checkbox" /> <?php
							echo esc_html( $Collection->name );
						?></label>
						<input type="hidden" class="menu-item-db-id" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-db-id]" value="0" />
						<input type="hidden" class="menu-item-object-id" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-object-id]" value="<?php echo $slug; ?>" />
						<input type="hidden" class="menu-item-object" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-object]" value="<?php echo $slug; ?>" />
						<input type="hidden" class="menu-item-parent-id" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-parent-id]" value="0">
						<input type="hidden" class="menu-item-type" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-type]" value="<?php echo SmartCollection::$taxon; ?>" />
						<input type="hidden" class="menu-item-title" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-title]" value="<?php echo $Collection->name; ?>" />

					</li>
				<?php endforeach; ?>
				<?php
					// Promo Collections
					$select = DB::select(array(
						'table' => DatabaseObject::tablename(Promotion::$table),
						'columns' => 'SQL_CALC_FOUND_ROWS id,name',
						'where' => array("target='Catalog'","status='enabled'"),
						'orderby' => 'created DESC'
					));

					$Promotions = DB::query($select,'array');
					foreach ($Promotions as $promo):
						$slug = sanitize_title_with_dashes($promo->name);
				?>
					<li>
						<label class="menu-item-title">
						<input type="checkbox" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-shopp-collection]" value="<?php echo $slug; ?>" class="menu-item-checkbox" /> <?php
							echo esc_html( $promo->name );
						?></label>
						<input type="hidden" class="menu-item-db-id" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-db-id]" value="0" />
						<input type="hidden" class="menu-item-object-id" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-object-id]" value="<?php echo $slug; ?>" />
						<input type="hidden" class="menu-item-object" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-object]" value="<?php echo $slug; ?>" />
						<input type="hidden" class="menu-item-parent-id" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-parent-id]" value="0">
						<input type="hidden" class="menu-item-type" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-type]" value="<?php echo SmartCollection::$taxon; ?>" />
						<input type="hidden" class="menu-item-title" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-title]" value="<?php echo $promo->name; ?>" />

					</li>
				<?php endforeach; ?>
				</ul>

			</div>

			<p class="button-controls">
				<span class="list-controls">
					<a href="<?php
						echo esc_url(add_query_arg(
							array(
								'shopp-pages-menu-item' => 'all',
								'selectall' => 1,
							),
							remove_query_arg($removed_args)
						));
					?>#shopp-collections-menu-item" class="select-all"><?php _e('Select All'); ?></a>
				</span>

				<span class="add-to-menu">
					<span class="spinner"></span>
					<input type="submit"<?php disabled( $nav_menu_selected_id, 0 ); ?> class="button-secondary submit-add-to-menu" value="<?php esc_attr_e('Add to Menu'); ?>" name="add-shopp-menu-item" id="submit-shopp-collections-menu-item" />
				</span>
			</p>

		</div><!-- /.customlinkdiv -->
		<?php

	}

	public static function shoppage_meta_box () {
		global $_nav_menu_placeholder, $nav_menu_selected_id;

		$removed_args = array(
			'action',
			'customlink-tab',
			'edit-menu-item',
			'menu-item',
			'page-tab',
			'_wpnonce',
		);

		?>
		<br />
		<div class="shopp-pages-menu-item customlinkdiv" id="shopp-pages-menu-item">
			<div id="tabs-panel-shopp-pages" class="tabs-panel tabs-panel-active">

				<ul class="categorychecklist form-no-clear">

				<?php
					foreach (ShoppPages() as $name => $Page):
						$_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;
				?>
					<li>
						<label class="menu-item-title">
						<input type="checkbox" name="menu-item[<?php esc_html_e( $_nav_menu_placeholder ) ?>][menu-item-shopp-page]" value="<?php esc_attr_e( $pagetype ) ?>" class="menu-item-checkbox" /> <?php
							echo esc_html( $Page->title() );
						?></label>
						<input type="hidden" class="menu-item-db-id" name="menu-item[<?php esc_attr_e( $_nav_menu_placeholder ) ?>][menu-item-db-id]" value="0" />
						<input type="hidden" class="menu-item-object-id" name="menu-item[<?php esc_attr_e( $_nav_menu_placeholder ) ?>][menu-item-object-id]" value="<?php esc_attr_e( $name ) ?>" />
						<input type="hidden" class="menu-item-object" name="menu-item[<?php esc_attr_e( $_nav_menu_placeholder ) ?>][menu-item-object]" value="<?php esc_attr_e( $name ) ?>" />
						<input type="hidden" class="menu-item-parent-id" name="menu-item[<?php esc_attr_e( $_nav_menu_placeholder ) ?>][menu-item-parent-id]" value="0">
						<input type="hidden" class="menu-item-type" name="menu-item[<?php esc_attr_e( $_nav_menu_placeholder ) ?>][menu-item-type]" value="<?php esc_attr_e( ShoppPages::QUERYVAR ) ?>" />
						<input type="hidden" class="menu-item-title" name="menu-item[<?php esc_attr_e( $_nav_menu_placeholder ) ?>][menu-item-title]" value="<?php esc_attr_e( $Page->title() ) ?>" />

					</li>
				<?php endforeach; ?>
				</ul>

			</div>

			<p class="button-controls">
				<span class="list-controls">
					<a href="<?php
						echo esc_url(add_query_arg(
							array(
								'shopp-pages-menu-item' => 'all',
								'selectall' => 1,
							),
							remove_query_arg($removed_args)
						));
					?>#shopp-pages-menu-item" class="select-all"><?php _e('Select All'); ?></a>
				</span>

				<span class="add-to-menu">
					<span class="spinner"></span>
					<input type="submit"<?php disabled( $nav_menu_selected_id, 0 ); ?> class="button-secondary submit-add-to-menu" value="<?php esc_attr_e('Add to Menu'); ?>" name="add-shopp-menu-item" id="submit-shopp-pages-menu-item" />
				</span>
			</p>

		</div><!-- /.customlinkdiv -->
		<?php

	}


} // END class ShoppUI


class ShoppAdminListTable extends WP_List_Table {

	public $_screen;
	public $_columns;
	public $_sortable;

	public function __construct ( $screen, $columns = array()) {
		if ( is_string( $screen ) )
			$screen = convert_to_screen( $screen );

		$this->_screen = $screen;

		if ( !empty( $columns ) ) {
			$this->_columns = $columns;
			add_filter( 'manage_' . $screen->id . '_columns', array( &$this, 'get_columns' ), 0 );
		}

	}

	public function get_column_info() {
		$columns = get_column_headers( $this->_screen );
		$hidden = get_hidden_columns( $this->_screen );
		$screen = get_current_screen();

		$_sortable = apply_filters( "manage_{$screen->id}_sortable_columns", $this->get_sortable_columns() );

		$sortable = array();
		foreach ( $_sortable as $id => $data ) {
			if ( empty( $data ) )
				continue;

			$data = (array) $data;
			if ( !isset( $data[1] ) )
				$data[1] = false;

			$sortable[$id] = $data;
		}


		return array( $columns, $hidden, $sortable );
	}

	public function get_columns() {
		return $this->_columns;
	}

	public function get_sortable_columns () {
		$screen = get_current_screen();
		$sortables = array(
			'toplevel_page_shopp-products' => array(
				'name'=>'name',
				'price'=>'price',
				'sold'=>'sold',
				'gross'=>'gross',
				'inventory'=>'inventory',
				'sku'=>'sku',
				'date'=>array('date',true)
			)
		);
		if (isset($sortables[ $screen->id ])) return $sortables[ $screen->id ];

		return array();
	}

}