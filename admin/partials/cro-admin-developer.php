<?php
/**
 * Developer tab: hooks reference and template overrides.
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$doc = class_exists( 'CRO_Hooks' ) ? CRO_Hooks::get_hooks_documentation() : array( 'actions' => array(), 'filters' => array() );
$actions = isset( $doc['actions'] ) && is_array( $doc['actions'] ) ? $doc['actions'] : array();
$filters = isset( $doc['filters'] ) && is_array( $doc['filters'] ) ? $doc['filters'] : array();
?>

<!-- Template overrides -->
<div class="cro-card cro-developer-section">
		<header class="cro-card__header cro-developer-section__header">
			<span class="cro-section-icon"><?php echo CRO_Icons::svg( 'file', array( 'class' => 'cro-ico' ) ); ?></span>
			<h2 class="cro-card__title"><?php esc_html_e( 'Template overrides', 'meyvora-convert' ); ?></h2>
		</header>
		<div class="cro-card__body">
			<p class="cro-section-desc"><?php esc_html_e( 'Campaign popup templates can be overridden by copying them into your theme.', 'meyvora-convert' ); ?></p>
			<div class="cro-developer-code-block cro-mb-2">
				<code>get_stylesheet_directory() . '/meyvora-convert/templates/<var>template-key</var>.php'</code>
			</div>
			<p><?php esc_html_e( 'Example: to override the centered popup, create this file in your theme:', 'meyvora-convert' ); ?></p>
			<div class="cro-developer-code-block cro-mb-2">
				<code>wp-content/themes/your-theme/meyvora-convert/templates/centered.php</code>
			</div>
			<p><?php esc_html_e( 'Available template keys match the popup names in the campaign builder (e.g. centered, corner, slide-bottom, top-bar). The plugin falls back to the built-in template if the file is missing.', 'meyvora-convert' ); ?></p>
			<p class="cro-mt-2">
				<strong><?php esc_html_e( 'Filter:', 'meyvora-convert' ); ?></strong>
				<code>cro_popup_template</code> — <?php esc_html_e( 'Change the template file path programmatically. Params: $template_path, $template_type.', 'meyvora-convert' ); ?>
			</p>
		</div>
	</div>

	<!-- Actions -->
	<div class="cro-card cro-developer-section">
		<header class="cro-card__header cro-developer-section__header">
			<span class="cro-section-icon"><?php echo CRO_Icons::svg( 'zap', array( 'class' => 'cro-ico' ) ); ?></span>
			<h2 class="cro-card__title"><?php esc_html_e( 'Actions', 'meyvora-convert' ); ?></h2>
		</header>
		<div class="cro-card__body">
			<p class="cro-section-desc"><?php esc_html_e( 'Fire your code at key points. Use add_action( \'hook_name\', $callback, $priority, $accepted_args ).', 'meyvora-convert' ); ?></p>
			<div class="cro-developer-hooks-list">
				<?php foreach ( $actions as $hook_name => $info ) : ?>
					<?php
					$desc   = isset( $info['description'] ) ? $info['description'] : '';
					$params = isset( $info['params'] ) ? $info['params'] : array();
					$example = isset( $info['example'] ) ? $info['example'] : '';
					?>
					<div class="cro-developer-hook-item">
						<code class="cro-developer-hook-name"><?php echo esc_html( $hook_name ); ?></code>
						<?php
						if ( ! empty( $params ) ) {
							$param_names = array_map( function ( $v, $k ) {
								return is_int( $k ) ? $v : $k;
							}, $params, array_keys( $params ) );
							?>
							<span class="cro-developer-hook-params">( <?php echo esc_html( implode( ', ', $param_names ) ); ?> )</span>
						<?php } ?>
						<?php if ( $desc !== '' ) : ?>
							<p class="cro-developer-hook-desc"><?php echo esc_html( $desc ); ?></p>
						<?php endif; ?>
						<?php if ( $example !== '' ) : ?>
							<pre class="cro-developer-snippet"><code><?php echo esc_html( $example ); ?></code></pre>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- Filters -->
	<div class="cro-card cro-developer-section">
		<header class="cro-card__header cro-developer-section__header">
			<span class="cro-section-icon"><?php echo CRO_Icons::svg( 'settings', array( 'class' => 'cro-ico' ) ); ?></span>
			<h2 class="cro-card__title"><?php esc_html_e( 'Filters', 'meyvora-convert' ); ?></h2>
		</header>
		<div class="cro-card__body">
			<p class="cro-section-desc"><?php esc_html_e( 'Modify data before it is used. Use add_filter( \'hook_name\', $callback, $priority, $accepted_args ). Return the modified value.', 'meyvora-convert' ); ?></p>
			<div class="cro-developer-hooks-list">
				<?php foreach ( $filters as $hook_name => $info ) : ?>
					<?php
					$desc   = isset( $info['description'] ) ? $info['description'] : '';
					$params = isset( $info['params'] ) ? $info['params'] : array();
					$return = isset( $info['return'] ) ? $info['return'] : '';
					$example = isset( $info['example'] ) ? $info['example'] : '';
					?>
					<div class="cro-developer-hook-item">
						<code class="cro-developer-hook-name"><?php echo esc_html( $hook_name ); ?></code>
						<?php
						if ( ! empty( $params ) ) {
							$param_names = array_map( function ( $v, $k ) {
								return is_int( $k ) ? $v : $k;
							}, $params, array_keys( $params ) );
							?>
							<span class="cro-developer-hook-params">( <?php echo esc_html( implode( ', ', $param_names ) ); ?> )</span>
						<?php } ?>
						<?php if ( $return !== '' ) : ?>
							<span class="cro-developer-hook-return">→ <?php echo esc_html( $return ); ?></span>
						<?php endif; ?>
						<?php if ( $desc !== '' ) : ?>
							<p class="cro-developer-hook-desc"><?php echo esc_html( $desc ); ?></p>
						<?php endif; ?>
						<?php if ( $example !== '' ) : ?>
							<pre class="cro-developer-snippet"><code><?php echo esc_html( $example ); ?></code></pre>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- Example snippets -->
	<div class="cro-card cro-developer-section">
		<header class="cro-card__header cro-developer-section__header">
			<span class="cro-section-icon"><?php echo CRO_Icons::svg( 'edit', array( 'class' => 'cro-ico' ) ); ?></span>
			<h2 class="cro-card__title"><?php esc_html_e( 'Example snippets', 'meyvora-convert' ); ?></h2>
		</header>
		<div class="cro-card__body">
			<div class="cro-developer-snippets-grid">
				<div class="cro-developer-snippet-card">
					<h4><?php esc_html_e( 'Add custom admin tab', 'meyvora-convert' ); ?></h4>
					<pre class="cro-developer-snippet"><code>add_filter( 'cro_admin_tabs', function( $tabs ) {
	$tabs['my-tab'] = array(
		'label' => 'My Tab',
		'url'   => admin_url( 'admin.php?page=my-tab' )
	);
	return $tabs;
});</code></pre>
				</div>
				<div class="cro-developer-snippet-card">
					<h4><?php esc_html_e( 'Track when campaign is shown', 'meyvora-convert' ); ?></h4>
					<pre class="cro-developer-snippet"><code>add_action( 'cro_campaign_shown', function( $campaign_id, $visitor_id ) {
	// Send to analytics
	gtag( 'event', 'cro_popup_shown', array( 'campaign_id' => $campaign_id ) );
}, 10, 2);</code></pre>
				</div>
				<div class="cro-developer-snippet-card">
					<h4><?php esc_html_e( 'Modify offer context', 'meyvora-convert' ); ?></h4>
					<pre class="cro-developer-snippet"><code>add_filter( 'cro_offer_context', function( $context ) {
	$context['custom_field'] = get_user_meta( get_current_user_id(), 'my_meta', true );
	return $context;
});</code></pre>
				</div>
				<div class="cro-developer-snippet-card">
					<h4><?php esc_html_e( 'Override popup template path', 'meyvora-convert' ); ?></h4>
					<pre class="cro-developer-snippet"><code>add_filter( 'cro_popup_template', function( $path, $template_type ) {
	if ( $template_type === 'centered' ) {
		return get_stylesheet_directory() . '/my-popup.php';
	}
	return $path;
}, 10, 2);</code></pre>
				</div>
			</div>
		</div>
	</div>
