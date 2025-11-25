<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_PIE_Logger {
    private static $log_file;
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $plugin_dir = dirname(dirname(__FILE__));
        self::$log_file = $plugin_dir . '/debug.log';
        
        // Create log file if it doesn't exist
        if (!file_exists(self::$log_file)) {
            touch(self::$log_file);
        }
    }
    
    public static function log($message, $data = null) {
        $instance = self::getInstance();
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}";
        
        if ($data !== null) {
            $log_entry .= " | DATA: " . print_r($data, true);
        }
        
        $log_entry .= "\n" . str_repeat("-", 80) . "\n";
        
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    public static function clear() {
        $instance = self::getInstance();
        file_put_contents(self::$log_file, '');
    }
    
    public static function get_log_content() {
        $instance = self::getInstance();
        if (file_exists(self::$log_file)) {
            return file_get_contents(self::$log_file);
        }
        return '';
    }
    
    public static function get_log_file_path() {
        $instance = self::getInstance();
        return self::$log_file;
    }
}