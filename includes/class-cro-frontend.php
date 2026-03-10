<?php
/**
 * Frontend Asset Loading
 * 
 * Handles conditional loading of scripts and styles
 */
class CRO_Frontend {
    
    /**
     * Initialize
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'output_config'), 5);
    }
    
    /**
     * Check if we should load assets. Only on Woo-relevant or campaign-targeted pages; respects cro_should_enqueue_assets.
     */
    private function should_load() {
        if (is_admin()) return false;
        if (function_exists('is_checkout') && is_checkout()) return false;
        if (!function_exists('cro_settings')) return false;

        $settings = cro_settings();
        if (!$settings || !$settings->get('general', 'enabled', true)) return false;
        if (!$this->has_active_campaigns()) return false;

        // Conditional asset loading: only on Woo pages or pages where a campaign is active.
        if (class_exists('CRO_Public') && !CRO_Public::should_enqueue_assets('campaigns')) return false;

        return true;
    }
    
    /**
     * Check for active campaigns
     */
    private function has_active_campaigns() {
        global $wpdb;
        $table = $wpdb->prefix . 'cro_campaigns';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));
        
        if (!$table_exists) {
            return false;
        }
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'active'"
        );
        
        return $count > 0;
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        if (!$this->should_load()) return;
        
        // Google Fonts (DM Sans) for popup typography
        wp_enqueue_style(
            'cro-google-fonts',
            'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap',
            array(),
            null
        );

        // Styles
        wp_enqueue_style(
            'cro-popup',
            CRO_PLUGIN_URL . 'public/css/cro-popup.css',
            array(),
            CRO_VERSION
        );
        
        wp_enqueue_style(
            'cro-animations',
            CRO_PLUGIN_URL . 'public/css/cro-animations.css',
            array('cro-popup'),
            CRO_VERSION
        );
        
        // Scripts
        wp_enqueue_script(
            'cro-signals',
            CRO_PLUGIN_URL . 'public/js/cro-signals.js',
            array(),
            CRO_VERSION,
            true
        );
        
        wp_enqueue_script(
            'cro-animations',
            CRO_PLUGIN_URL . 'public/js/cro-animations.js',
            array(),
            CRO_VERSION,
            true
        );
        
        wp_enqueue_script(
            'cro-popup',
            CRO_PLUGIN_URL . 'public/js/cro-popup.js',
            array('cro-animations'),
            CRO_VERSION,
            true
        );
        
        wp_enqueue_script(
            'cro-controller',
            CRO_PLUGIN_URL . 'public/js/cro-controller.js',
            array('cro-popup', 'cro-signals'),
            CRO_VERSION,
            true
        );
    }
    
    /**
     * Output configuration in footer
     */
    public function output_config() {
        if (!$this->should_load()) return;
        
        // Build context (cached per request for fast rule evaluation)
        $context = function_exists( 'cro_get_request_context' ) ? cro_get_request_context() : ( class_exists( 'CRO_Context' ) ? new CRO_Context() : null );
        
        // Build visitor state
        $visitor = class_exists('CRO_Visitor_State') ? CRO_Visitor_State::get_instance() : null;
        
        $config = array(
            'restUrl' => rest_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'siteUrl' => home_url(),
            'cartUrl' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '/cart',
            'checkoutUrl' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '/checkout',
            'debug' => current_user_can('manage_meyvora_convert') && cro_settings()->get('general', 'debug_mode', false),
            'context' => $context && method_exists($context, 'to_frontend_array') ? $context->to_frontend_array() : array(),
            'visitor' => $visitor && method_exists($visitor, 'to_frontend_array') ? $visitor->to_frontend_array() : array(),
        );
        
        // Template configurations
        $templates = array();
        if (class_exists('CRO_Templates') && method_exists('CRO_Templates', 'get_all')) {
            foreach (CRO_Templates::get_all() as $key => $template) {
                $templates[$key] = array(
                    'supports' => isset($template['supports']) ? $template['supports'] : array(),
                    'type' => isset($template['type']) ? $template['type'] : 'popup',
                );
            }
        }
        
        ?>
        <script>
            window.croConfig = <?php echo wp_json_encode($config); ?>;
            window.croTemplates = <?php echo wp_json_encode($templates); ?>;
        </script>
        <?php
    }
}

// Initialize
new CRO_Frontend();
