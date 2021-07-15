<?php
/**
 * Plugin Name: WooCommerce Repeat Orders
 * Description: Allow your customers to repeat orders
 * Version: 1.0.0
 * Requires PHP: 7.0
 * Author: CRPlugins
 * Author URI: https://crplugins.com.ar
 * Text Domain: wc-repeat-orders
 * Domain Path: /i18n/languages/
 * WC requires at least: 4
 * WC tested up to: 5.4.1
 *
 * @package crplugins-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin main class
 */
class WooCommerce_Repeat_Orders {

	const COLUMN_TITLE            = 'Repetir orden';
	const COLUMN_BUTTON_LABEL     = 'Repetir';
	const VIEW_ORDER_TEXT         = 'Para repetir la orden hace click en el siguiente botón y serás redirigido al carrito';
	const VIEW_ORDER_BUTTON_LABEL = 'Repetir orden';

	/**
	 * Class Constructor
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Class' init logic
	 *
	 * @return void
	 */
	public function init() {
		if ( ! $this->check_system_requirements() ) {
			return;
		}
		add_filter( 'woocommerce_account_orders_columns', array( $this, 'add_repeat_order_column' ) );
		add_action( 'woocommerce_my_account_my_orders_column_order-repeat', array( $this, 'add_repeat_order_column_content' ) );
		add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'add_repeat_order_view_page' ) );
		add_action( 'wp_loaded', array( $this, 'repeat_order_action' ), 30 );
	}


	/**
	 * Checks the system requirements and adds a notice if applicable
	 *
	 * @return boolean
	 */
	protected function check_system_requirements():bool {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$system = $this->check_components_requirements();

		if ( $system['flag'] ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			?> 
				<div class="notice notice-error is-dismissible">
				<p>WooCommerce Repeat Orders Requires at least <?php echo esc_html( $system['flag'] ); ?> version <?php echo esc_html( $system['version'] ); ?> or greater.</p>
				</div>
			<?php
			return false;
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			?>
				<div class="notice notice-error is-dismissible">
				<p>WooCommerce must be active before using <strong>WooCommerce Repeat Orders</strong></p>
				</div>
			<?php
			return false;
		}

		return true;
	}

	/**
	 * Checks the components required for the plugin to work (PHP, WordPress and WooCommerce)
	 *
	 * @return array
	 */
	protected function check_components_requirements(): array {

		global $wp_version;
		$flag    = false;
		$version = false;

		if ( version_compare( PHP_VERSION, '7.0', '<' ) ) {
			$flag    = 'PHP';
			$version = '7.0';
		} elseif ( version_compare( $wp_version, '5.0', '<' ) ) {
			$flag    = 'WordPress';
			$version = '5.0';
		} elseif ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '4.0', '<' ) ) {
			$flag    = 'WooCommerce';
			$version = '4.0';
		}

		return array(
			'flag'    => $flag,
			'version' => $version,
		);
	}

	/**
	 * Adds the "Repeat order" column for the frontend "my orders" page
	 *
	 * @param array $columns page columns.
	 * @return array new page columns
	 */
	public function add_repeat_order_column( array $columns ):array {
		$columns['order-repeat'] = self::COLUMN_TITLE;
		return $columns;
	}

	/**
	 * Adds the "Repeat order" column content
	 *
	 * @param WC_Order $order customer's order.
	 * @return void;
	 */
	public function add_repeat_order_column_content( WC_Order $order ) {
		$url = add_query_arg( array( 'repeat-order' => $order->get_id() ) );
		?>
		<a href="<?php echo esc_url( $url ); ?>" class="woocommerce-button button view"><?php echo esc_html( self::COLUMN_BUTTON_LABEL ); ?></a> 
		<?php
	}

	/**
	 * Adds the "Repeat order" content to the "View order" page
	 *
	 * @param WC_Order $order customer's order.
	 * @return void;
	 */
	public function add_repeat_order_view_page( WC_Order $order ) {
		$url       = add_query_arg( array( 'repeat-order' => $order->get_id() ) );
		$p_content = self::VIEW_ORDER_TEXT;
		if ( ! empty( $p_content ) ) {
			?>
		<p><?php echo esc_html( $p_content ); ?></p>
			<?php
		}
		?>
		<a href="<?php echo esc_url( $url ); ?>" class="woocommerce-button button view"><?php echo esc_html( self::VIEW_ORDER_BUTTON_LABEL ); ?></a> 
		<?php
	}

	/**
	 * Add to cart action.
	 *
	 * Checks for a valid request, does validation (via hooks) and then redirects if valid.
	 */
	public static function repeat_order_action(): void {
		if ( ! isset( $_REQUEST['repeat-order'] ) || ! is_numeric( wp_unslash( $_REQUEST['repeat-order'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}

		wc_nocache_headers();

		$order_id = apply_filters( 'woocommerce_add_to_cart_product_id', absint( wp_unslash( $_REQUEST['repeat-order'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = wc_get_order( $order_id );
		if ( empty( $order ) ) {
			return;
		}

		$order_items = $order->get_items();
		if ( empty( $order_items ) ) {
			return;
		}
		WC()->cart->empty_cart();
		foreach ( $order_items as $order_item ) {
			if ( get_class( $order_item ) !== 'WC_Order_Item_Product' ) {
				continue;
			}
			$product = $order_item->get_product();
			if ( empty( $product ) ) {
				continue;
			}
			$product_id    = $order_item->get_product_id();
			$quantity      = $order_item->get_quantity();
			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $order_item->get_variation_id() );
			if ( empty( $cart_item_key ) ) {
				continue;
			}

			// Product meta.
			/* $product_meta = $order_item->get_meta_data();
			foreach ( $product_meta as $product_meta_object ) {
				$meta_array = $product_meta_object->get_data();
				if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['data'] ) ) {
					WC()->cart->cart_contents[ $cart_item_key ]['data']->update_meta_data( $meta_array['key'], $meta_array['value'] );
				}
			}
			if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['data'] ) ) {
				WC()->cart->cart_contents[ $cart_item_key ]['data']->save_meta_data();
				WC()->cart->cart_contents[ $cart_item_key ]['data']->save();
			} */

			wc_add_to_cart_message( array( $product_id => $quantity ), true );
		}
		wp_safe_redirect( wc_get_cart_url() );
	}
}

new WooCommerce_Repeat_Orders();
