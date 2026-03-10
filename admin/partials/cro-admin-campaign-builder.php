<?php
/**
 * Visual Campaign Builder
 * 
 * A modern, intuitive interface for creating campaigns
 */

$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : ( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
$campaign_data = null;

if ($campaign_id && class_exists('CRO_Campaign') && class_exists('CRO_Campaign_Model')) {
	$campaign_row = CRO_Campaign::get($campaign_id);
	if ($campaign_row && is_array($campaign_row)) {
		$campaign_data = CRO_Campaign_Model::from_db_row($campaign_row);
	}
}

// Default campaign data if no campaign found
if (!$campaign_data && class_exists('CRO_Campaign_Model')) {
	$campaign_data = CRO_Campaign_Model::create_new();
} elseif (!$campaign_data) {
	// Fallback: create a simple object with defaults if CRO_Campaign_Model doesn't exist yet
	$campaign_data = (object) array(
		'id' => null,
		'name' => '',
		'status' => 'draft',
		'template' => 'centered',
		'content' => array(),
	);
}

// Builder script/style are enqueued in CRO_Admin::enqueue_scripts when on campaign edit page.
do_action( 'cro_campaign_builder_before', $campaign_id );
?>
<div class="cro-builder-wrap">
    <!-- Builder Header -->
    <div class="cro-builder-header">
        <div class="cro-builder-header-left">
            <a href="<?php echo admin_url('admin.php?page=cro-campaigns'); ?>" class="cro-back-link">
                <?php echo CRO_Icons::svg( 'arrow-left', array( 'class' => 'cro-ico' ) ); ?>
                <?php esc_html_e('All Campaigns', 'meyvora-convert'); ?>
            </a>
            <input type="text" 
                   id="campaign-name" 
                   class="cro-campaign-name-input"
                   value="<?php echo esc_attr((string) ($campaign_data->name ?? __('Untitled Campaign', 'meyvora-convert'))); ?>"
                   placeholder="<?php esc_attr_e('Campaign Name', 'meyvora-convert'); ?>" />
        </div>
        
        <div class="cro-builder-header-right">
            <span class="cro-save-status" id="save-status"></span>
            
            <div class="cro-builder-actions">
                <button type="button" class="button" id="preview-btn">
                    <?php echo CRO_Icons::svg( 'eye', array( 'class' => 'cro-ico' ) ); ?>
                    <?php esc_html_e('Preview', 'meyvora-convert'); ?>
                </button>
                <button type="button" class="button" id="preview-new-tab-btn" title="<?php esc_attr_e('Open preview in a new tab', 'meyvora-convert'); ?>">
                    <?php echo CRO_Icons::svg( 'external-link', array( 'class' => 'cro-ico' ) ); ?>
                    <?php esc_html_e('Preview in new tab', 'meyvora-convert'); ?>
                </button>
                <button type="button" class="button" id="copy-preview-link-btn" title="<?php esc_attr_e('Copy a link that opens this campaign preview (expires in 30 minutes)', 'meyvora-convert'); ?>">
                    <?php echo CRO_Icons::svg( 'link', array( 'class' => 'cro-ico' ) ); ?>
                    <?php esc_html_e('Copy Preview Link', 'meyvora-convert'); ?>
                </button>
                
                <div class="cro-status-dropdown">
                    <select id="campaign-status" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Draft', 'meyvora-convert' ); ?>">
                        <option value="draft" <?php selected((string) ($campaign_data->status ?? 'draft'), 'draft'); ?>>
                            <?php esc_html_e('Draft', 'meyvora-convert'); ?>
                        </option>
                        <option value="active" <?php selected((string) ($campaign_data->status ?? 'draft'), 'active'); ?>>
                            <?php esc_html_e('Active', 'meyvora-convert'); ?>
                        </option>
                        <option value="paused" <?php selected((string) ($campaign_data->status ?? 'draft'), 'paused'); ?>>
                            <?php esc_html_e('Paused', 'meyvora-convert'); ?>
                        </option>
                    </select>
                </div>
                
                <button type="button" class="button button-primary" id="save-campaign-btn">
                    <?php echo CRO_Icons::svg( 'check', array( 'class' => 'cro-ico' ) ); ?>
                    <?php esc_html_e('Save', 'meyvora-convert'); ?>
                </button>
            </div>
        </div>
    </div>

    <div id="cro-preview-error" class="cro-preview-error notice notice-error" style="display:none; margin: 0 0 1rem 0;">
        <p class="cro-preview-error-message"></p>
        <button type="button" class="notice-dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'meyvora-convert' ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss', 'meyvora-convert' ); ?></span></button>
    </div>
    
    <!-- Builder Main Area -->
    <div class="cro-builder-main">
        
        <!-- Left Sidebar: Steps/Sections -->
        <div class="cro-builder-sidebar">
            <nav class="cro-builder-nav">
                <a href="#" class="cro-nav-item active" data-section="template">
                    <span class="cro-nav-icon"><?php echo CRO_Icons::svg( 'palette', array( 'class' => 'cro-ico' ) ); ?></span>
                    <span class="cro-nav-label"><?php esc_html_e('Template', 'meyvora-convert'); ?></span>
                </a>
                <a href="#" class="cro-nav-item" data-section="content">
                    <span class="cro-nav-icon"><?php echo CRO_Icons::svg( 'edit', array( 'class' => 'cro-ico' ) ); ?></span>
                    <span class="cro-nav-label"><?php esc_html_e('Content', 'meyvora-convert'); ?></span>
                </a>
                <a href="#" class="cro-nav-item" data-section="design">
<span class="cro-nav-icon"><?php echo CRO_Icons::svg( 'target', array( 'class' => 'cro-ico' ) ); ?></span>
					<span class="cro-nav-label"><?php esc_html_e('Design', 'meyvora-convert'); ?></span>
                </a>
                <a href="#" class="cro-nav-item" data-section="trigger">
                    <span class="cro-nav-icon"><?php echo CRO_Icons::svg( 'zap', array( 'class' => 'cro-ico' ) ); ?></span>
                    <span class="cro-nav-label"><?php esc_html_e('Trigger', 'meyvora-convert'); ?></span>
                </a>
                <a href="#" class="cro-nav-item" data-section="targeting">
<span class="cro-nav-icon"><?php echo CRO_Icons::svg( 'target', array( 'class' => 'cro-ico' ) ); ?></span>
					<span class="cro-nav-label"><?php esc_html_e('Targeting', 'meyvora-convert'); ?></span>
                </a>
                <a href="#" class="cro-nav-item" data-section="display">
                    <span class="cro-nav-icon"><?php echo CRO_Icons::svg( 'calendar', array( 'class' => 'cro-ico' ) ); ?></span>
                    <span class="cro-nav-label"><?php esc_html_e('Display Rules', 'meyvora-convert'); ?></span>
                </a>
            </nav>
        </div>
        
        <!-- Center: Section Content -->
        <div class="cro-builder-content">
            
            <!-- Section: Template Selection -->
            <div class="cro-section active" id="section-template">
                <h2><?php esc_html_e('Choose a Template', 'meyvora-convert'); ?></h2>
                <p class="cro-section-desc"><?php esc_html_e('Select a starting point for your campaign', 'meyvora-convert'); ?></p>
                
                <div class="cro-template-grid" id="cro-template-grid">
                    <?php
                    $templates = array();
                    if ( class_exists( 'CRO_Templates' ) && method_exists( 'CRO_Templates', 'get_available_for_builder' ) ) {
                        $templates = CRO_Templates::get_available_for_builder();
                    }
                    if ( empty( $templates ) ) {
                        // Fallback: only templates that have an existing popup file
                        $templates = array(
                            'centered' => array(
                                'name' => __( 'Centered Modal', 'meyvora-convert' ),
                                'description' => __( 'Classic centered popup with overlay', 'meyvora-convert' ),
                                'preview_image' => '',
                            ),
                            'centered-image-left' => array(
                                'name' => __( 'Image Left', 'meyvora-convert' ),
                                'description' => __( 'Two-column layout with image on left', 'meyvora-convert' ),
                                'preview_image' => '',
                            ),
                            'corner' => array(
                                'name' => __( 'Corner', 'meyvora-convert' ),
                                'description' => __( 'Corner popup', 'meyvora-convert' ),
                                'preview_image' => '',
                            ),
                            'slide-bottom' => array(
                                'name' => __( 'Bottom Slide', 'meyvora-convert' ),
                                'description' => __( 'Slides up from bottom of screen', 'meyvora-convert' ),
                                'preview_image' => '',
                            ),
                            'top-bar' => array(
                                'name' => __( 'Top Bar', 'meyvora-convert' ),
                                'description' => __( 'Sticky bar at top of page', 'meyvora-convert' ),
                                'preview_image' => '',
                            ),
                        );
                    }
                    $templates = apply_filters( 'cro_campaign_available_templates', $templates );
                    $current_template = (string) ( $campaign_data->template ?? 'centered' );
                    if ( ! isset( $templates[ $current_template ] ) ) {
                        $current_template = ! empty( $templates ) ? (string) array_key_first( $templates ) : 'centered';
                    }
                    if ( empty( $templates ) ) :
                        ?>
                        <div class="cro-template-empty-state">
                            <span class="cro-template-empty-state__icon"><?php echo CRO_Icons::svg( 'palette', array( 'class' => 'cro-ico' ) ); ?></span>
                            <p class="cro-template-empty-state__text"><?php esc_html_e( 'No templates available. Add templates via the cro_campaign_available_templates filter.', 'meyvora-convert' ); ?></p>
                        </div>
                        <?php
                    else :
                    foreach ( $templates as $key => $template ) :
                        $template_key = (string) ($key ?? '');
                        $template_name = (string) ($template['name'] ?? '');
                        $template_desc = (string) ($template['description'] ?? '');
                        $template_preview = (string) ($template['preview_image'] ?? '');
                        $is_selected = $current_template === $template_key;
                    ?>
                    <div class="cro-template-card <?php echo $is_selected ? 'selected' : ''; ?>" 
                         data-template="<?php echo esc_attr($template_key); ?>">
                        <div class="cro-template-preview">
                            <?php if ($template_preview !== '') : ?>
                                <img src="<?php echo esc_url($template_preview); ?>" 
                                     alt="<?php echo esc_attr($template_name); ?>" />
                            <?php else : ?>
                                <div class="cro-template-placeholder"><?php echo esc_html($template_name); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="cro-template-info">
                            <h4 class="cro-template-card__title"><?php echo esc_html($template_name); ?></h4>
                            <p class="cro-template-card__desc"><?php echo esc_html($template_desc); ?></p>
                            <button type="button" class="button button-small cro-template-preview-btn" data-template="<?php echo esc_attr($template_key); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Preview %s', 'meyvora-convert' ), $template_name ) ); ?>">
                                <?php echo CRO_Icons::svg( 'eye', array( 'class' => 'cro-ico' ) ); ?>
                                <?php esc_html_e( 'Preview', 'meyvora-convert' ); ?>
                            </button>
                        </div>
                        <span class="cro-template-check" aria-hidden="true"><?php echo CRO_Icons::svg( 'check', array( 'class' => 'cro-ico' ) ); ?></span>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            
            <!-- Section: Content -->
            <div class="cro-section" id="section-content">
                <h2><?php esc_html_e('Campaign Content', 'meyvora-convert'); ?></h2>
                <p class="cro-section-desc"><?php esc_html_e('Headline, body text, CTA, and optional coupon or email capture.', 'meyvora-convert'); ?></p>
                
                <div class="cro-content-editor">
                    
                    <!-- Image Upload -->
                    <div class="cro-field-group">
                        <label><?php esc_html_e('Image (Optional)', 'meyvora-convert'); ?></label>
                        <div class="cro-image-upload" id="campaign-image-upload">
                            <div class="cro-image-preview" id="image-preview">
                                <?php if (!empty($campaign_data->content['image_url'])) : ?>
                                    <img src="<?php echo esc_url($campaign_data->content['image_url']); ?>" alt="" />
                                    <button type="button" class="cro-remove-image" aria-label="<?php esc_attr_e('Remove image', 'meyvora-convert'); ?>"><?php echo CRO_Icons::svg( 'x', array( 'class' => 'cro-ico' ) ); ?></button>
                                <?php else : ?>
                                    <span class="cro-upload-placeholder">
                                        <?php echo CRO_Icons::svg( 'upload', array( 'class' => 'cro-ico' ) ); ?>
                                        <?php esc_html_e('Click to upload', 'meyvora-convert'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button cro-select-image-btn" id="cro-select-image-btn">
                                <?php echo CRO_Icons::svg( 'image', array( 'class' => 'cro-ico' ) ); ?>
                                <?php echo esc_html( ! empty( $campaign_data->content['image_url'] ) ? __( 'Change image', 'meyvora-convert' ) : __( 'Select image', 'meyvora-convert' ) ); ?>
                            </button>
                            <input type="hidden" id="content-image" value="<?php echo esc_url($campaign_data->content['image_url'] ?? ''); ?>" />
                        </div>
                    </div>
                    
                    <?php
                    $content_tone = isset( $campaign_data->content['tone'] ) ? $campaign_data->content['tone'] : 'neutral';
                    $exit_defaults = class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::get_map( 'exit_intent' ) : array();
                    $neutral_exit = isset( $exit_defaults['neutral'] ) ? $exit_defaults['neutral'] : array();
                    $ph_headline   = isset( $exit_defaults[ $content_tone ]['headline'] ) ? $exit_defaults[ $content_tone ]['headline'] : ( isset( $neutral_exit['headline'] ) ? $neutral_exit['headline'] : __( 'Before you go', 'meyvora-convert' ) );
                    $ph_subheadline = isset( $exit_defaults[ $content_tone ]['subheadline'] ) ? $exit_defaults[ $content_tone ]['subheadline'] : ( isset( $neutral_exit['subheadline'] ) ? $neutral_exit['subheadline'] : __( 'Here\'s a small thank-you for visiting', 'meyvora-convert' ) );
                    $ph_cta        = isset( $exit_defaults[ $content_tone ]['cta_text'] ) ? $exit_defaults[ $content_tone ]['cta_text'] : ( isset( $neutral_exit['cta_text'] ) ? $neutral_exit['cta_text'] : __( 'Claim offer', 'meyvora-convert' ) );
                    $ph_dismiss    = isset( $exit_defaults[ $content_tone ]['dismiss_text'] ) ? $exit_defaults[ $content_tone ]['dismiss_text'] : ( isset( $neutral_exit['dismiss_text'] ) ? $neutral_exit['dismiss_text'] : __( 'No thanks', 'meyvora-convert' ) );
                    $content_tones = class_exists( 'CRO_Default_Copy' ) ? CRO_Default_Copy::get_tones() : array( 'neutral' => __( 'Neutral', 'meyvora-convert' ), 'urgent' => __( 'Urgent', 'meyvora-convert' ), 'friendly' => __( 'Friendly', 'meyvora-convert' ) );
                    ?>
                    <!-- Tone -->
                    <div class="cro-field-group">
                        <label for="content-tone"><?php esc_html_e( 'Tone', 'meyvora-convert' ); ?></label>
                        <select id="content-tone" name="content-tone" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Tone', 'meyvora-convert' ); ?>">
                            <?php foreach ( $content_tones as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $content_tone, $value ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="cro-field-hint"><?php esc_html_e( 'Suggested default copy uses this tone. Placeholders below reflect it.', 'meyvora-convert' ); ?></p>
                    </div>

                    <!-- Headline -->
                    <div class="cro-field-group">
                        <label for="content-headline"><?php esc_html_e( 'Headline', 'meyvora-convert' ); ?></label>
                        <input type="text" 
                               id="content-headline" 
                               class="cro-input-large"
                               value="<?php echo esc_attr( $campaign_data->content['headline'] ?? '' ); ?>"
                               placeholder="<?php echo esc_attr( $ph_headline ); ?>" />
                        <div class="cro-field-hint">
                            <?php esc_html_e( 'Placeholders:', 'meyvora-convert' ); ?>
                            <code>{cart_total}</code> <code>{cart_items}</code> <code>{first_name}</code>
                        </div>
                    </div>
                    
                    <!-- Subheadline -->
                    <div class="cro-field-group">
                        <label for="content-subheadline"><?php esc_html_e( 'Subheadline', 'meyvora-convert' ); ?></label>
                        <input type="text" 
                               id="content-subheadline"
                               value="<?php echo esc_attr( $campaign_data->content['subheadline'] ?? '' ); ?>"
                               placeholder="<?php echo esc_attr( $ph_subheadline ); ?>" />
                    </div>
                    
                    <!-- Body Text -->
                    <div class="cro-field-group">
                        <label for="content-body"><?php esc_html_e('Body Text (Optional)', 'meyvora-convert'); ?></label>
                        <textarea id="content-body" 
                                  rows="3"
                                  placeholder="<?php esc_attr_e('Additional message...', 'meyvora-convert'); ?>"
                        ><?php echo esc_textarea($campaign_data->content['body'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- CTA Button -->
                    <div class="cro-field-group">
                        <label><?php esc_html_e('Call-to-Action Button', 'meyvora-convert'); ?></label>
                        <div class="cro-field-row">
                            <input type="text" 
                                   id="content-cta-text"
                                   value="<?php echo esc_attr( $campaign_data->content['cta_text'] ?? '' ); ?>"
                                   placeholder="<?php echo esc_attr( $ph_cta ); ?>" />
                            <select id="content-cta-action" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Close popup', 'meyvora-convert' ); ?>">
                                <option value="close" <?php selected($campaign_data->content['cta_action'] ?? '', 'close'); ?>>
                                    <?php esc_html_e('Close popup', 'meyvora-convert'); ?>
                                </option>
                                <option value="url" <?php selected($campaign_data->content['cta_action'] ?? '', 'url'); ?>>
                                    <?php esc_html_e('Go to URL', 'meyvora-convert'); ?>
                                </option>
                                <option value="cart" <?php selected($campaign_data->content['cta_action'] ?? '', 'cart'); ?>>
                                    <?php esc_html_e('Go to cart', 'meyvora-convert'); ?>
                                </option>
                                <option value="checkout" <?php selected($campaign_data->content['cta_action'] ?? '', 'checkout'); ?>>
                                    <?php esc_html_e('Go to checkout', 'meyvora-convert'); ?>
                                </option>
                            </select>
                        </div>
                        <input type="url" 
                               id="content-cta-url"
                               class="cro-conditional-field" 
                               data-show-when="content-cta-action=url"
                               value="<?php echo esc_url($campaign_data->content['cta_url'] ?? ''); ?>"
                               placeholder="https://" />
                    </div>
                    
                    <!-- Coupon Code -->
                    <div class="cro-field-group">
                        <label class="cro-label-with-checkbox">
                            <input type="checkbox" 
                                   id="content-show-coupon"
                                   <?php checked(!empty($campaign_data->content['coupon_code'])); ?> />
                            <?php esc_html_e('Show Coupon Code', 'meyvora-convert'); ?>
                        </label>
                        
                        <div class="cro-conditional-fields" data-show-when="content-show-coupon">
                            <div class="cro-field-row">
                                <div class="cro-field-col">
                                    <label for="content-coupon-code"><?php esc_html_e('Coupon Code', 'meyvora-convert'); ?></label>
                                    <input type="text" 
                                           id="content-coupon-code"
                                           value="<?php echo esc_attr($campaign_data->content['coupon_code'] ?? ''); ?>"
                                           placeholder="SAVE10" />
                                </div>
                                <div class="cro-field-col">
                                    <label for="content-coupon-text"><?php esc_html_e('Display Text', 'meyvora-convert'); ?></label>
                                    <input type="text" 
                                           id="content-coupon-text"
                                           value="<?php echo esc_attr($campaign_data->content['coupon_display_text'] ?? ''); ?>"
                                           placeholder="<?php esc_attr_e('Use code: SAVE10 for 10% off', 'meyvora-convert'); ?>" />
                                </div>
                            </div>
                            <label class="cro-checkbox-inline">
                                <input type="checkbox" 
                                       id="content-auto-apply-coupon"
                                       <?php checked(!empty($campaign_data->content['auto_apply_coupon'])); ?> />
                                <?php esc_html_e('Auto-apply coupon when CTA clicked', 'meyvora-convert'); ?>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Email Capture -->
                    <div class="cro-field-group">
                        <label class="cro-label-with-checkbox">
                            <input type="checkbox"
                                   id="content-show-email"
                                   <?php checked(!empty($campaign_data->content['show_email_field'])); ?> />
                            <?php esc_html_e('Capture Email Address', 'meyvora-convert'); ?>
                        </label>
                        
                        <div class="cro-conditional-fields" data-show-when="content-show-email">
                            <input type="text" 
                                   id="content-email-placeholder"
                                   value="<?php echo esc_attr($campaign_data->content['email_placeholder'] ?? __('Enter your email', 'meyvora-convert')); ?>"
                                   placeholder="<?php esc_attr_e('Placeholder text', 'meyvora-convert'); ?>" />
                        </div>
                    </div>
                    
                    <!-- Countdown Timer -->
                    <div class="cro-field-group">
                        <label class="cro-label-with-checkbox">
                            <input type="checkbox"
                                   id="content-show-countdown"
                                   <?php checked(!empty($campaign_data->content['show_countdown'])); ?> />
                            <?php esc_html_e('Show Countdown Timer', 'meyvora-convert'); ?>
                        </label>
                        
                        <div class="cro-conditional-fields" data-show-when="content-show-countdown">
                            <div class="cro-field-row">
                                <div class="cro-field-col">
                                    <label><?php esc_html_e('Duration (minutes)', 'meyvora-convert'); ?></label>
                                    <input type="number" 
                                           id="content-countdown-minutes"
                                           value="<?php echo esc_attr($campaign_data->content['countdown_minutes'] ?? 15); ?>"
                                           min="1" max="60" />
                                </div>
                                <div class="cro-field-col">
                                    <label><?php esc_html_e('Timer Type', 'meyvora-convert'); ?></label>
                                    <select id="content-countdown-type" class="cro-selectwoo" data-placeholder="<?php esc_attr_e( 'Session-based (honest)', 'meyvora-convert' ); ?>">
                                        <option value="session"><?php esc_html_e('Session-based (honest)', 'meyvora-convert'); ?></option>
                                        <option value="evergreen"><?php esc_html_e('Evergreen (resets)', 'meyvora-convert'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dismiss Link -->
                    <div class="cro-field-group">
                        <label class="cro-label-with-checkbox">
                            <input type="checkbox"
                                   id="content-show-dismiss"
                                   <?php checked($campaign_data->content['show_dismiss_link'] ?? true); ?> />
                            <?php esc_html_e('Show Dismiss Link', 'meyvora-convert'); ?>
                        </label>
                        
                        <div class="cro-conditional-fields" data-show-when="content-show-dismiss">
                            <input type="text" 
                                   id="content-dismiss-text"
                                   value="<?php echo esc_attr( $campaign_data->content['dismiss_text'] ?? '' ); ?>"
                                   placeholder="<?php echo esc_attr( $ph_dismiss ); ?>" />
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Section: Design -->
            <div class="cro-section" id="section-design">
                <h2><?php esc_html_e('Design & Styling', 'meyvora-convert'); ?></h2>
                <p class="cro-section-desc"><?php esc_html_e('Colors, popup size, animation, and position.', 'meyvora-convert'); ?></p>
                
                <!-- Include design controls - colors, fonts, spacing -->
                <?php include CRO_PLUGIN_DIR . 'admin/partials/builder/design-controls.php'; ?>
            </div>
            
            <!-- Section: Trigger -->
            <div class="cro-section" id="section-trigger">
                <h2><?php esc_html_e('When to Show', 'meyvora-convert'); ?></h2>
                <p class="cro-section-desc"><?php esc_html_e('Exit intent, scroll depth, time delay, or other triggers.', 'meyvora-convert'); ?></p>
                
                <!-- Include trigger controls -->
                <?php include CRO_PLUGIN_DIR . 'admin/partials/builder/trigger-controls.php'; ?>
            </div>
            
            <!-- Section: Targeting -->
            <div class="cro-section" id="section-targeting">
                <h2><?php esc_html_e('Who to Show', 'meyvora-convert'); ?></h2>
                <p class="cro-section-desc"><?php esc_html_e('Pages, visitor type, device, and cart conditions.', 'meyvora-convert'); ?></p>
                
                <!-- Include targeting controls -->
                <?php include CRO_PLUGIN_DIR . 'admin/partials/builder/targeting-controls.php'; ?>
            </div>
            
            <!-- Section: Display Rules -->
            <div class="cro-section" id="section-display">
                <h2><?php esc_html_e('Display Rules', 'meyvora-convert'); ?></h2>
                <p class="cro-section-desc"><?php esc_html_e('Frequency, cooldown, schedule, and conversion goals.', 'meyvora-convert'); ?></p>
                
                <!-- Include display rules controls -->
                <?php include CRO_PLUGIN_DIR . 'admin/partials/builder/display-controls.php'; ?>
            </div>
            
        </div>
        
        <!-- Right Sidebar: Live Preview -->
        <div class="cro-builder-preview">
            <div class="cro-preview-header">
                <span><?php esc_html_e('Live Preview', 'meyvora-convert'); ?></span>
                <div class="cro-preview-header-actions">
                    <button type="button" class="button button-small" id="preview-panel-new-tab-btn" title="<?php esc_attr_e('Open preview in a new tab', 'meyvora-convert'); ?>">
                        <?php echo CRO_Icons::svg( 'external-link', array( 'class' => 'cro-ico' ) ); ?>
                        <?php esc_html_e('Preview in new tab', 'meyvora-convert'); ?>
                    </button>
                    <div class="cro-preview-device-toggle">
                    <button type="button" data-device="desktop" title="<?php esc_attr_e( 'Desktop', 'meyvora-convert' ); ?>">
                        <?php echo CRO_Icons::svg( 'monitor', array( 'class' => 'cro-ico' ) ); ?>
                    </button>
                    <button type="button" data-device="tablet" title="<?php esc_attr_e( 'Tablet', 'meyvora-convert' ); ?>">
                        <?php echo CRO_Icons::svg( 'tablet', array( 'class' => 'cro-ico' ) ); ?>
                    </button>
                    <button type="button" data-device="mobile" title="<?php esc_attr_e( 'Mobile', 'meyvora-convert' ); ?>">
                        <?php echo CRO_Icons::svg( 'smartphone', array( 'class' => 'cro-ico' ) ); ?>
                    </button>
                </div>
                </div>
            </div>
            
            <div class="cro-preview-container cro-preview-container--desktop" id="preview-container">
                <?php
                $preview_frame_template = str_replace( array( ' ', '_' ), '-', $current_template );
                ?>
                <div class="cro-preview-frame desktop cro-preview-frame--<?php echo esc_attr( $preview_frame_template ); ?>" id="preview-frame">
                    <!-- Live preview renders here -->
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Hidden data -->
    <input type="hidden" id="campaign-id" value="<?php echo esc_attr($campaign_id); ?>" />
    <input type="hidden" id="campaign-data" value="<?php echo esc_attr(wp_json_encode(is_object($campaign_data) && method_exists($campaign_data, 'to_frontend_array') ? $campaign_data->to_frontend_array() : array())); ?>" />
    <div id="cro-builder-toast-container" class="cro-ui-toast-container" aria-live="polite" aria-label="<?php esc_attr_e( 'Notifications', 'meyvora-convert' ); ?>"></div>
<?php do_action( 'cro_campaign_builder_after', $campaign_id ); ?>
</div><!-- .cro-builder-wrap -->
