<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.1.3
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_Admin_Menu_Manager {

		#region Properties

		/**
		 * @since 1.2.2
		 *
		 * @var string
		 */
		protected $_module_unique_affix;

		/**
		 * @since 1.2.2
		 *
		 * @var number
		 */
		protected $_module_id;

		/**
		 * @since 1.2.2
		 *
		 * @var string
		 */
		protected $_module_type;

		/**
		 * @since 1.0.6
		 *
		 * @var string
		 */
		private $_menu_slug;
		/**
		 * @since 1.1.3
		 *
		 * @var string
		 */
		private $_parent_slug;
		/**
		 * @since 1.1.3
		 *
		 * @var string
		 */
		private $_parent_type;
		/**
		 * @since 1.1.3
		 *
		 * @var string
		 */
		private $_type;
		/**
		 * @since 1.1.3
		 *
		 * @var bool
		 */
		private $_is_top_level;
		/**
		 * @since 1.1.3
		 *
		 * @var bool
		 */
		private $_is_override_exact;
		/**
		 * @since 1.1.3
		 *
		 * @var array<string,bool>
		 */
		private $_default_submenu_items;
		/**
		 * @since 1.1.3
		 *
		 * @var string
		 */
		private $_first_time_path;
		/**
		 * @since 1.2.2
		 *
		 * @var bool
		 */
		private $_menu_exists;

		#endregion Properties

		/**
		 * @var FS_Logger
		 */
		protected $_logger;

		#region Singleton

		/**
		 * @var FS_Admin_Menu_Manager[]
		 */
		private static $_instances = array();

		/**
		 * @param number $module_id
		 * @param string $module_type
		 * @param string $module_unique_affix
		 *
		 * @return FS_Admin_Notice_Manager
		 */
		static function instance( $module_id, $module_type, $module_unique_affix ) {
			$key = 'm_' . $module_id;

			if ( ! isset( self::$_instances[ $key ] ) ) {
				self::$_instances[ $key ] = new FS_Admin_Menu_Manager( $module_id, $module_type, $module_unique_affix );
			}

			return self::$_instances[ $key ];
		}

		protected function __construct( $module_id, $module_type, $module_unique_affix ) {
			$this->_logger = FS_Logger::get_logger( WP_FS__SLUG . '_' . $module_id . '_admin_menu', WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );

			$this->_module_id			= $module_id;
			$this->_module_type			= $module_type;
			$this->_module_unique_affix	= $module_unique_affix;
		}

		#endregion Singleton

		#region Helpers

		private function get_option( &$options, $key, $default = false ) {
			return ! empty( $options[ $key ] ) ? $options[ $key ] : $default;
		}

		private function get_bool_option( &$options, $key, $default = false ) {
			return isset( $options[ $key ] ) && is_bool( $options[ $key ] ) ? $options[ $key ] : $default;
		}

		#endregion Helpers

		/**
		 * @param array $menu
		 * @param bool  $is_addon
		 */
		function init( $menu, $is_addon = false ) {
			$this->_menu_exists = ( isset( $menu['slug'] ) && ! empty( $menu['slug'] ) );

			$this->_menu_slug = ( $this->_menu_exists ? $menu['slug'] : $this->_module_unique_affix );

			$this->_default_submenu_items = array();
			// @deprecated
			$this->_type              = 'page';
			$this->_is_top_level      = true;
			$this->_is_override_exact = false;
			$this->_parent_slug       = false;
			// @deprecated
			$this->_parent_type = 'page';

			if ( ! $is_addon && isset( $menu ) ) {
				$this->_default_submenu_items = array(
					'contact' => $this->get_bool_option( $menu, 'contact', true ),
					'support' => $this->get_bool_option( $menu, 'support', true ),
					'account' => $this->get_bool_option( $menu, 'account', true ),
					'pricing' => $this->get_bool_option( $menu, 'pricing', true ),
					'addons'  => $this->get_bool_option( $menu, 'addons', true ),
				);

				// @deprecated
				$this->_type              = $this->get_option( $menu, 'type', 'page' );
				$this->_is_override_exact = $this->get_bool_option( $menu, 'override_exact' );

				if ( isset( $menu['parent'] ) ) {
					$this->_parent_slug = $this->get_option( $menu['parent'], 'slug' );
					// @deprecated
					$this->_parent_type = $this->get_option( $menu['parent'], 'type', 'page' );

					// If parent's slug is different, then it's NOT a top level menu item.
					$this->_is_top_level = ( $this->_parent_slug === $this->_menu_slug );
				} else {
					/**
					 * If no parent then top level if:
					 *  - Has custom admin menu ('page')
					 *  - CPT menu type ('cpt')
					 */
//					$this->_is_top_level = in_array( $this->_type, array(
//						'cpt',
//						'page'
//					) );
				}

				$this->_first_time_path = $this->get_option( $menu, 'first-path', false );
				if ( ! empty( $this->_first_time_path ) && is_string( $this->_first_time_path ) ) {
					$this->_first_time_path = admin_url( $this->_first_time_path, 'admin' );
				}
			}
		}

		/**
		 * Check if top level menu.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return bool False if submenu item.
		 */
		function is_top_level() {
			return $this->_is_top_level;
		}

		/**
		 * Check if the page should be override on exact URL match.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return bool False if submenu item.
		 */
		function is_override_exact() {
			return $this->_is_override_exact;
		}


		/**
		 * Get the path of the page the user should be forwarded to after first activation.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_first_time_path() {
			return $this->_first_time_path;
		}

		/**
		 * Check if plugin's menu item is part of a custom top level menu.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return bool
		 */
		function has_custom_parent() {
			return ! $this->_is_top_level && is_string( $this->_parent_slug );
		}

		/**
		 * @author Leo Fajardo (@leorw)
		 * @since  1.2.2
		 *
		 * @return bool
		 */
		function has_menu() {
			return $this->_menu_exists;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
//		function slug(){
//			return $this->_menu_slug;
//		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @param string $id
		 * @param bool   $default
		 *
		 * @return bool
		 */
		function is_submenu_item_visible( $id, $default = true ) {
			return fs_apply_filter(
				$this->_module_unique_affix,
				'is_submenu_visible',
				$this->get_bool_option( $this->_default_submenu_items, $id, $default ),
				$id
			);
		}

		/**
		 * Calculates admin settings menu slug.
		 * If plugin's menu slug is a file (e.g. CPT), uses plugin's slug as the menu slug.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @param string $page
		 *
		 * @return string
		 */
		function get_slug( $page = '' ) {
			return ( ( false === strpos( $this->_menu_slug, '.php?' ) ) ?
				$this->_menu_slug :
				$this->_module_unique_affix ) . ( empty( $page ) ? '' : ( '-' . $page ) );
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_parent_slug() {
			return $this->_parent_slug;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_type() {
			return $this->_type;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return bool
		 */
		function is_cpt() {
			return ( 0 === strpos( $this->_menu_slug, 'edit.php?post_type=' ) ||
			         // Back compatibility.
			         'cpt' === $this->_type
			);
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_parent_type() {
			return $this->_parent_type;
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_raw_slug() {
			return $this->_menu_slug;
		}

		/**
		 * Get plugin's original menu slug.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_original_menu_slug() {
			if ( 'cpt' === $this->_type ) {
				return add_query_arg( array(
					'post_type' => $this->_menu_slug
				), 'edit.php' );
			}

			if ( false === strpos( $this->_menu_slug, '.php?' ) ) {
				return $this->_menu_slug;
			} else {
				return $this->_module_unique_affix;
			}
		}

		/**
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.3
		 *
		 * @return string
		 */
		function get_top_level_menu_slug() {
			return $this->has_custom_parent() ?
				$this->get_parent_slug() :
				$this->get_raw_slug();
		}

		/**
		 * Is user on plugin's admin activation page.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.8
		 *
		 * @return bool
		 */
		function is_activation_page() {
			if ( $this->_menu_exists &&
			   ( fs_is_plugin_page( $this->_menu_slug ) || fs_is_plugin_page( $this->_module_unique_affix ) )
			) {
				/**
				 * Module has a settings menu and the context page is the main settings page, so assume it's in
				 * activation (doesn't really check if already opted-in/skipped or not).
				 *
				 * @since 1.2.2
				 */
				return true;
			}

			global $pagenow;
			if ( ( WP_FS__MODULE_TYPE_THEME === $this->_module_type ) && 'themes.php' === $pagenow ) {
				/**
				 * In activation only when show_optin query string param is given.
				 *
				 * @since 1.2.2
				 */
				return fs_request_is_action( $this->_module_unique_affix . '_show_optin' );
			}

			return false;
		}

		#region Submenu Override

		/**
		 * Override submenu's action.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.0
		 *
		 * @param string   $parent_slug
		 * @param string   $menu_slug
		 * @param callable $function
		 *
		 * @return false|string If submenu exist, will return the hook name.
		 */
		function override_submenu_action( $parent_slug, $menu_slug, $function ) {
			global $submenu;

			$menu_slug   = plugin_basename( $menu_slug );
			$parent_slug = plugin_basename( $parent_slug );

			if ( ! isset( $submenu[ $parent_slug ] ) ) {
				// Parent menu not exist.
				return false;
			}

			$found_submenu_item = false;
			foreach ( $submenu[ $parent_slug ] as $submenu_item ) {
				if ( $menu_slug === $submenu_item[2] ) {
					$found_submenu_item = $submenu_item;
					break;
				}
			}

			if ( false === $found_submenu_item ) {
				// Submenu item not found.
				return false;
			}

			// Remove current function.
			$hookname = get_plugin_page_hookname( $menu_slug, $parent_slug );
			remove_all_actions( $hookname );

			// Attach new action.
			add_action( $hookname, $function );

			return $hookname;
		}

		#endregion Submenu Override

		#region Top level menu Override

		/**
		 * Find plugin's admin dashboard main menu item.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.2
		 *
		 * @return string[]|false
		 */
		private function find_top_level_menu() {
			global $menu;

			$position   = - 1;
			$found_menu = false;

			$menu_slug = $this->get_raw_slug();

			$hook_name = get_plugin_page_hookname( $menu_slug, '' );
			foreach ( $menu as $pos => $m ) {
				if ( $menu_slug === $m[2] ) {
					$position   = $pos;
					$found_menu = $m;
					break;
				}
			}

			if ( false === $found_menu ) {
				return false;
			}

			return array(
				'menu'      => $found_menu,
				'position'  => $position,
				'hook_name' => $hook_name
			);
		}

		/**
		 * Remove all sub-menu items.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.7
		 *
		 * @return bool If submenu with plugin's menu slug was found.
		 */
		private function remove_all_submenu_items() {
			global $submenu;

			$menu_slug = $this->get_raw_slug();

			if ( ! isset( $submenu[ $menu_slug ] ) ) {
				return false;
			}

			$submenu[ $menu_slug ] = array();

			return true;
		}

		/**
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.0.9
		 *
		 * @return array[string]mixed
		 */
		function remove_menu_item() {
			$this->_logger->entrance();

			// Find main menu item.
			$menu = $this->find_top_level_menu();

			if ( false === $menu ) {
				return false;
			}

			// Remove it with its actions.
			remove_all_actions( $menu['hook_name'] );

			// Remove all submenu items.
			$this->remove_all_submenu_items();

			return $menu;
		}

		/**
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since  1.1.4
		 *
		 * @param callable $function
		 *
		 * @return array[string]mixed
		 */
		function override_menu_item( $function ) {
			$found_menu = $this->remove_menu_item();

			if ( false === $found_menu ) {
				return false;
			}

			if ( ! $this->is_top_level() || ! $this->is_cpt() ) {
				$menu_slug = plugin_basename( $this->get_slug() );

				$hookname = get_plugin_page_hookname( $menu_slug, '' );

				// Override menu action.
				add_action( $hookname, $function );
			} else {
				global $menu;

				// Remove original CPT menu.
				unset( $menu[ $found_menu['position'] ] );

				// Create new top-level menu action.
				$hookname = add_menu_page(
					$found_menu['menu'][3],
					$found_menu['menu'][0],
					'manage_options',
					$this->get_slug(),
					$function,
					$found_menu['menu'][6],
					$found_menu['position']
				);
			}

			return $hookname;
		}

		#endregion Top level menu Override
	}
