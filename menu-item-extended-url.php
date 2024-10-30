<?php
/*
Plugin Name: Menu Item Extended URL
Plugin URI:  https://developer.wordpress.org/plugins/menu-item-extended-url/
Description: Allows to add additional parameters and/or fragment identifier (hashtag) to the menu item url.
Version:     0.1
Author:      Pavel Revenkov
Author URI:  http://panych.net
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: menu-item-extended-url
Domain Path: /languages
*/

if ( ! class_exists( 'Menu_Item_Extended_URL' ) ) :

	class Menu_Item_Extended_URL
	{
		/**
		 * Static property to hold our singleton instance
		 *
		 */
		static $instance = false;
		static $field_key = 'menu-item-url-extension';

		/**
		 * This is our constructor
		 *
		 * @since 0.1
		 *
		 * @return void
		 */
		private function __construct() {
			add_action( 'plugins_loaded', array( $this, 'get_textdomain' ) );

			add_filter( 'wp_edit_nav_menu_walker', array( __CLASS__, 'custom_walker' ), 99 );
			add_action( 'wp_nav_menu_item_custom_fields', array( __CLASS__, 'print_field' ), 10, 4 );
			add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'save_field' ), 10, 3 );
			add_filter( 'nav_menu_link_attributes', array( __CLASS__, 'add_extension' ), 10, 4 );
		}


		/**
		 * If an instance exists, this returns it.  If not, it creates one and
		 * retuns it.
		 *
		 * @since 0.1
		 *
		 * @return Menu_Item_Extended_URL
		 */
		public static function getInstance() {
			if ( !self::$instance )
				self::$instance = new self;
			return self::$instance;
		}


		/**
		 * load textdomain
		 *
		 * @since 0.1
		 *
		 * @return void
		 */
		public function get_textdomain() {
			load_plugin_textdomain( 'menu-item-extended-url', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}


		/**
		 * Replace default menu editor walker with ours
		 *
		 * We don't actually replace the default walker. We're still using it and
		 * only injecting some HTMLs.
		 *
		 * @since   0.1
		 * @access  private
		 * @wp_hook filter wp_edit_nav_menu_walker
		 * @param   string $walker Walker class name
		 * @return  string Walker class name
		 */
		public static function custom_walker( $walker ) {
			$walker = 'Menu_Item_Extended_URL_Walker';
			if ( ! class_exists( $walker ) ) {
				require_once dirname( __FILE__ ) . '/includes/class-walker-nav-menu-edit.php';
			}

			return $walker;
		}


		/**
		 * Print field
		 *
		 * @since 0.1
		 *
		 * @wp_hook action wp_nav_menu_item_custom_fields
		 *
		 * @param object $item  Menu item data object.
		 * @param int    $depth  Depth of menu item. Used for padding.
		 * @param array  $args  Menu item args.
		 * @param int    $id    Nav menu ID.
		 *
		 * @return string Form fields
		 */
		public static function print_field( $id, $item, $depth, $args ) {
			$item_id = esc_attr( $item->ID );
			$value = get_post_meta( $item->ID, self::$field_key, true );

			//Do not show field for Custom Link menu items
			if ( $item->type == 'custom' )
				return false;
			?>
			<p class="field-url-extension description description-wide">
				<label for="edit-menu-item-url-extension-<?php echo $item_id; ?>">
					<?php _e( 'URL Extension', 'menu-item-extended-url' ); ?><br />
					<input type="text" id="edit-menu-item-url-extension-<?php echo $item_id; ?>" class="widefat code edit-menu-item-url-extension" name="menu-item-url-extension[<?php echo $item_id; ?>]" value="<?php echo esc_attr( $value ); ?>" />
				</label>
			</p>
			<?php
		}


		/**
		 * Save field value
		 *
		 * @since 0.1
		 *
		 * @wp_hook action wp_update_nav_menu_item
		 *
		 * @param int   $menu_id         Nav menu ID
		 * @param int   $menu_item_db_id Menu item ID
		 * @param array $menu_item_args  Menu item data
		 */
		public static function save_field( $menu_id, $menu_item_db_id, $menu_item_args ) {
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				return;
			}

			check_admin_referer( 'update-nav_menu', 'update-nav-menu-nonce' );

			// Sanitize
			if ( ! empty( $_POST[ self::$field_key ][ $menu_item_db_id ] ) ) {
				// Do some checks here...
				$value = $_POST[ self::$field_key ][ $menu_item_db_id ];
				$value = ltrim($value, "/");
			} else {
				$value = false;
			}

			// Update
			if ( !empty( $value ) ) {
				update_post_meta( $menu_item_db_id, self::$field_key, $value );
			} else {
				delete_post_meta( $menu_item_db_id, self::$field_key );
			}
		}


		/**
		 * Filters the HTML attributes applied to a menu item's anchor element.
		 *
		 * @wp_hook filter nav_menu_link_attributes
		 *
		 * @since 0.1
		 *
		 * @param array $atts {
		 *     The HTML attributes applied to the menu item's `<a>` element, empty strings are ignored.
		 *
		 *     @type string $title  Title attribute.
		 *     @type string $target Target attribute.
		 *     @type string $rel    The rel attribute.
		 *     @type string $href   The href attribute.
		 * }
		 * @param WP_Post  $item  The current menu item.
		 * @param stdClass $args  An object of wp_nav_menu() arguments.
		 * @param int      $depth Depth of menu item. Used for padding.
		 *
		 * @return array   The HTML attributes applied to the menu item's `<a>` element, empty strings are ignored.
		 */
		public static function add_extension( $atts, $item, $args, $depth ) {
			$url = $atts['href'];
			$url_extension = get_post_meta( $item->ID, self::$field_key, true );

			if ( !empty( $url_extension ) ) {
				$url_params = array();
				$url_parts = parse_url($url);
				if ( !empty( $url_parts['query'] ) ) :
					parse_str($url_parts['query'], $url_params);
				endif;

				$new_url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];

				$extension_parts = parse_url( 'http://domain.com/' . $url_extension );
				if ( !empty( $extension_parts['path'] ) ) :
					$new_url .= ltrim($extension_parts['path'], "/");
				endif;

				if ( !empty( $extension_parts['query'] ) ) :
					parse_str($extension_parts['query'], $extension_params);
					$new_url .= '?' . http_build_query( array_merge( $url_params, $extension_params ) );
				endif;

				if ( !empty( $extension_parts['fragment'] ) ) :
					$new_url .= '#'.$extension_parts['fragment'];
				endif;

				$atts['href'] = $new_url;
			}

			return $atts;
		}
	}
endif;

// Instantiate class
$Menu_Item_Extended_URL = Menu_Item_Extended_URL::getInstance();
?>