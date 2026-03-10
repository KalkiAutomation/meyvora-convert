<?php
/**
 * Shared top nav tabs for CRO admin: Dashboard | Offers | Abandoned Carts | Cart | Checkout | Analytics | Settings.
 * Include this on Dashboard, Offers, Abandoned Carts, Cart, Checkout, Analytics, Settings.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

$nav_items = array(
	'meyvora-convert'         => array( 'label' => __( 'Dashboard', 'meyvora-convert' ), 'url' => admin_url( 'admin.php?page=meyvora-convert' ) ),
	'cro-offers'          => array( 'label' => __( 'Offers', 'meyvora-convert' ), 'url' => admin_url( 'admin.php?page=cro-offers' ) ),
	'cro-abandoned-carts' => array( 'label' => __( 'Abandoned Carts', 'meyvora-convert' ), 'url' => admin_url( 'admin.php?page=cro-abandoned-carts' ) ),
	'cro-abandoned-cart'  => array( 'label' => __( 'Abandoned Cart Emails', 'meyvora-convert' ), 'url' => admin_url( 'admin.php?page=cro-abandoned-cart' ) ),
	'cro-cart'            => array( 'label' => __( 'Cart Optimizer', 'meyvora-convert' ), 'url' => admin_url( 'admin.php?page=cro-cart' ) ),
	'cro-checkout'        => array( 'label' => __( 'Checkout Optimizer', 'meyvora-convert' ), 'url' => admin_url( 'admin.php?page=cro-checkout' ) ),
	'cro-analytics'       => array( 'label' => __( 'Analytics', 'meyvora-convert' ), 'url' => admin_url( 'admin.php?page=cro-analytics' ) ),
	'cro-settings'        => array( 'label' => __( 'Settings', 'meyvora-convert' ), 'url' => admin_url( 'admin.php?page=cro-settings' ) ),
);
?>
<nav class="cro-ui-nav" aria-label="<?php esc_attr_e( 'CRO sections', 'meyvora-convert' ); ?>">
	<ul class="cro-ui-nav__list" role="list">
		<?php foreach ( $nav_items as $page_slug => $item ) : ?>
			<li class="cro-ui-nav__item">
				<a href="<?php echo esc_url( $item['url'] ); ?>"
					class="cro-ui-nav__link <?php echo ( $current_page === $page_slug ) ? 'cro-ui-nav__link--active' : ''; ?>">
					<?php echo esc_html( $item['label'] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
