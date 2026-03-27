<?php
/**
 * Multi-step onboarding wizard (goal-based auto-configuration).
 *
 * @package Meyvora_Convert
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$dashboard_url = admin_url( 'admin.php?page=meyvora-convert' );
$skip_url      = wp_nonce_url( admin_url( 'admin.php?page=meyvora-convert&cro_skip_onboarding=1' ), 'cro_skip_onboarding' );
?>

<div class="cro-onboarding-wizard" id="cro-onboarding-wizard" data-dashboard-url="<?php echo esc_url( $dashboard_url ); ?>">
	<div class="cro-ob-progress" aria-hidden="true">
		<div class="cro-ob-progress-track"><span class="cro-ob-progress-fill" style="width:25%"></span></div>
		<span class="cro-ob-progress-label"><span class="cro-ob-step-num">1</span> / 4</span>
	</div>

	<section class="cro-ob-step cro-ob-step--1 is-active" data-step="1">
		<h2 class="cro-ob-title"><?php esc_html_e( 'What is your #1 goal right now?', 'meyvora-convert' ); ?></h2>
		<p class="cro-ob-lead"><?php esc_html_e( 'We’ll tailor features and starter campaigns to match.', 'meyvora-convert' ); ?></p>
		<div class="cro-ob-cards" role="radiogroup" aria-label="<?php esc_attr_e( 'Primary goal', 'meyvora-convert' ); ?>">
			<label class="cro-ob-card">
				<input type="radio" name="cro_ob_goal" value="recover_abandoned" />
				<span class="cro-ob-card-inner">
					<span class="cro-ob-card-title"><?php esc_html_e( 'Recover abandoned carts', 'meyvora-convert' ); ?></span>
					<span class="cro-ob-card-desc"><?php esc_html_e( 'Reminders, exit offers, and cart nudges.', 'meyvora-convert' ); ?></span>
				</span>
			</label>
			<label class="cro-ob-card">
				<input type="radio" name="cro_ob_goal" value="grow_email" />
				<span class="cro-ob-card-inner">
					<span class="cro-ob-card-title"><?php esc_html_e( 'Grow my email list', 'meyvora-convert' ); ?></span>
					<span class="cro-ob-card-desc"><?php esc_html_e( 'Timed and exit popups with email capture.', 'meyvora-convert' ); ?></span>
				</span>
			</label>
			<label class="cro-ob-card">
				<input type="radio" name="cro_ob_goal" value="increase_aov" />
				<span class="cro-ob-card-inner">
					<span class="cro-ob-card-title"><?php esc_html_e( 'Increase average order value', 'meyvora-convert' ); ?></span>
					<span class="cro-ob-card-desc"><?php esc_html_e( 'Shipping bar, sticky cart, and cart messaging.', 'meyvora-convert' ); ?></span>
				</span>
			</label>
			<label class="cro-ob-card">
				<input type="radio" name="cro_ob_goal" value="reduce_checkout" />
				<span class="cro-ob-card-inner">
					<span class="cro-ob-card-title"><?php esc_html_e( 'Reduce checkout drop-off', 'meyvora-convert' ); ?></span>
					<span class="cro-ob-card-desc"><?php esc_html_e( 'Checkout optimizer + reassurance campaigns.', 'meyvora-convert' ); ?></span>
				</span>
			</label>
		</div>
		<div class="cro-ob-actions">
			<button type="button" class="button button-primary button-hero cro-ob-next" disabled><?php esc_html_e( 'Continue', 'meyvora-convert' ); ?></button>
		</div>
	</section>

	<section class="cro-ob-step cro-ob-step--2" data-step="2" hidden>
		<h2 class="cro-ob-title"><?php esc_html_e( 'Store profile', 'meyvora-convert' ); ?></h2>
		<p class="cro-ob-lead"><?php esc_html_e( 'Help us tune defaults — this stays private to your site.', 'meyvora-convert' ); ?></p>

		<div class="cro-ob-field">
			<label class="cro-ob-field-label"><?php esc_html_e( 'Store type', 'meyvora-convert' ); ?></label>
			<select class="cro-ob-select" name="store_type" id="cro_ob_store_type">
				<option value=""><?php esc_html_e( 'Select…', 'meyvora-convert' ); ?></option>
				<option value="fashion"><?php esc_html_e( 'Fashion', 'meyvora-convert' ); ?></option>
				<option value="electronics"><?php esc_html_e( 'Electronics', 'meyvora-convert' ); ?></option>
				<option value="beauty"><?php esc_html_e( 'Beauty', 'meyvora-convert' ); ?></option>
				<option value="food"><?php esc_html_e( 'Food & Drink', 'meyvora-convert' ); ?></option>
				<option value="home"><?php esc_html_e( 'Home & Garden', 'meyvora-convert' ); ?></option>
				<option value="other"><?php esc_html_e( 'Other', 'meyvora-convert' ); ?></option>
			</select>
		</div>
		<div class="cro-ob-field">
			<label class="cro-ob-field-label"><?php esc_html_e( 'Average order value (typical)', 'meyvora-convert' ); ?></label>
			<select class="cro-ob-select" name="aov_range" id="cro_ob_aov">
				<option value=""><?php esc_html_e( 'Select…', 'meyvora-convert' ); ?></option>
				<option value="0-50"><?php esc_html_e( '$0–$50', 'meyvora-convert' ); ?></option>
				<option value="51-150"><?php esc_html_e( '$51–$150', 'meyvora-convert' ); ?></option>
				<option value="151-500"><?php esc_html_e( '$151–$500', 'meyvora-convert' ); ?></option>
				<option value="500plus"><?php esc_html_e( '$500+', 'meyvora-convert' ); ?></option>
			</select>
		</div>
		<div class="cro-ob-field">
			<label class="cro-ob-field-label"><?php esc_html_e( 'Monthly visitors (estimate)', 'meyvora-convert' ); ?></label>
			<select class="cro-ob-select" name="monthly_visitors" id="cro_ob_visitors">
				<option value=""><?php esc_html_e( 'Select…', 'meyvora-convert' ); ?></option>
				<option value="lt1k"><?php esc_html_e( 'Under 1,000', 'meyvora-convert' ); ?></option>
				<option value="1k-10k"><?php esc_html_e( '1,000–10,000', 'meyvora-convert' ); ?></option>
				<option value="10k-50k"><?php esc_html_e( '10,000–50,000', 'meyvora-convert' ); ?></option>
				<option value="50kplus"><?php esc_html_e( '50,000+', 'meyvora-convert' ); ?></option>
			</select>
		</div>
		<div class="cro-ob-actions">
			<button type="button" class="button cro-ob-back"><?php esc_html_e( 'Back', 'meyvora-convert' ); ?></button>
			<button type="button" class="button button-primary cro-ob-next" disabled><?php esc_html_e( 'Continue', 'meyvora-convert' ); ?></button>
		</div>
	</section>

	<section class="cro-ob-step cro-ob-step--3" data-step="3" hidden>
		<h2 class="cro-ob-title"><?php esc_html_e( 'Auto-configure', 'meyvora-convert' ); ?></h2>
		<div class="cro-ob-configuring cro-is-hidden">
			<div class="cro-ob-spinner" aria-hidden="true"></div>
			<p class="cro-ob-configuring-text"><?php esc_html_e( 'Configuring your store…', 'meyvora-convert' ); ?></p>
		</div>
		<div class="cro-ob-summary cro-is-hidden">
			<h3 class="cro-ob-summary-title"><?php esc_html_e( 'We turned on:', 'meyvora-convert' ); ?></h3>
			<ul class="cro-ob-summary-list"></ul>
			<button type="button" class="button button-primary cro-ob-next"><?php esc_html_e( 'Continue', 'meyvora-convert' ); ?></button>
		</div>
		<div class="cro-ob-actions cro-ob-step3-start">
			<button type="button" class="button cro-ob-back"><?php esc_html_e( 'Back', 'meyvora-convert' ); ?></button>
			<button type="button" class="button button-primary button-hero cro-ob-run-config"><?php esc_html_e( 'Configure my store', 'meyvora-convert' ); ?></button>
		</div>
	</section>

	<section class="cro-ob-step cro-ob-step--4" data-step="4" hidden>
		<h2 class="cro-ob-title"><?php esc_html_e( 'Quick wins checklist', 'meyvora-convert' ); ?></h2>
		<p class="cro-ob-lead"><?php esc_html_e( 'You’re set — tick these off as you go.', 'meyvora-convert' ); ?></p>
		<ul class="cro-ob-checklist" id="cro-ob-checklist"></ul>
		<div class="cro-ob-actions">
			<button type="button" class="button button-primary button-hero cro-ob-view-dashboard"><?php esc_html_e( 'View your dashboard', 'meyvora-convert' ); ?></button>
		</div>
	</section>

	<p class="cro-ob-skip">
		<a href="<?php echo esc_url( $skip_url ); ?>"><?php esc_html_e( 'Skip and go to dashboard', 'meyvora-convert' ); ?></a>
	</p>
</div>
