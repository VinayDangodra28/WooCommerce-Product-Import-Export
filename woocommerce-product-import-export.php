<?php
/**
 * Plugin Name: WooCommerce Product Import Export
 * Plugin URI: https://example.com/
 * Description: Export and Import WooCommerce products with support for all product types, variations, and custom meta.
 * Version: 1.2.3
 * Author: Your Name
 * Author URI: https://example.com/
 * Text Domain: wc-product-import-export
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 10.3.5
 */

if (!defined("ABSPATH")) {
    exit; // Exit if accessed directly
}

class WooCommerceProductImportExport {
    
    private $plugin_path;
    private $plugin_url;
    
    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once $this->plugin_path . "includes/class-wc-pie-logger.php";
        require_once $this->plugin_path . "includes/class-wc-pie-exporter.php";
        require_once $this->plugin_path . "includes/class-wc-pie-importer.php";
        require_once $this->plugin_path . "includes/class-wc-pie-admin.php";
        require_once $this->plugin_path . "includes/class-wc-pie-ajax.php";
    }

    private function init_hooks() {
        // Declare HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        
        // Initialize Admin
        new WC_PIE_Admin($this->plugin_url);
        
        // Initialize AJAX
        new WC_PIE_Ajax();
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, "activate"));
        register_deactivation_hook(__FILE__, array($this, "deactivate"));
    }
    
    public function declare_hpos_compatibility() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
    
    public function activate() {
        // Create upload directory if it doesn"t exist
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir["basedir"] . "/wc-product-import-export";
        
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }
    }
    
    public function deactivate() {
        // Clean up temporary files
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir["basedir"] . "/wc-product-import-export";
        
        if (file_exists($plugin_upload_dir)) {
            $files = glob($plugin_upload_dir . "/*");
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}

// Initialize the plugin
new WooCommerceProductImportExport();
