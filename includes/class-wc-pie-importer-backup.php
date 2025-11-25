<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_PIE_Importer {
    
    private $temp_dir;
    private $extracted_images_dir;
    private $image_hash_cache = array();
    
    public function __construct() {
        $this->temp_dir = wp_upload_dir()['basedir'] . '/wc-product-import-export/temp';
        $this->extracted_images_dir = $this->temp_dir . '/extracted_images';
        
        // Ensure temp directories exist
        if (!file_exists($this->temp_dir)) {
            wp_mkdir_p($this->temp_dir);
        }
    }
    
    /**
     * Process ZIP import file
     * 
     * @param string $zip_file_path Path to the ZIP file
     * @param array $options Import options
     * @return array Import results
     */
    public function process_zip_import($zip_file_path, $options = array()) {
        WC_PIE_Logger::log('ZIP IMPORT - Start processing', array('file' => $zip_file_path, 'options' => $options));
        
        if (!file_exists($zip_file_path)) {
            throw new Exception('ZIP file not found: ' . $zip_file_path);
        }
        
        // Extract ZIP file
        $extract_result = $this->extract_zip_file($zip_file_path);
        if (!$extract_result['success']) {
            throw new Exception('Failed to extract ZIP file: ' . $extract_result['error']);
        }
        
        $extraction_dir = $extract_result['directory'];
        
        // Find JSON file in extraction
        $json_files = glob($extraction_dir . '/*.json');
        if (empty($json_files)) {
            throw new Exception('No JSON export file found in ZIP archive');
        }
        
        $json_file = $json_files[0]; // Use first JSON file found
        WC_PIE_Logger::log('ZIP IMPORT - Found JSON file', array('file' => $json_file));
        
        // Load and parse JSON data
        $json_content = file_get_contents($json_file);
        $import_data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format: ' . json_last_error_msg());
        }
        
        // Set extraction directory for image processing
        $this->extracted_images_dir = $extraction_dir . '/images';
        
        // Process import with local images
        $options['use_local_images'] = true;
        $options['local_images_dir'] = $this->extracted_images_dir;
        
        // Build image hash cache for deduplication
        if (!empty($options['dedupe_images'])) {
            $this->build_image_hash_cache();
        }
        
        $result = $this->import_products($import_data, $options);
        
        // Cleanup extraction directory
        $this->cleanup_temp_directory($extraction_dir);
        
        return $result;
    }
    
    /**
     * Process ZIP import file
     * 
     * @param string $zip_file_path Path to the ZIP file
     * @param array $options Import options
     * @return array Import results
     */
    public function process_zip_import($zip_file_path, $options = array()) {
        WC_PIE_Logger::log('ZIP IMPORT - Start processing', array('file' => $zip_file_path, 'options' => $options));
        
        if (!file_exists($zip_file_path)) {
            throw new Exception('ZIP file not found: ' . $zip_file_path);
        }
        
        // Extract ZIP file
        $extract_result = $this->extract_zip_file($zip_file_path);
        if (!$extract_result['success']) {
            throw new Exception('Failed to extract ZIP file: ' . $extract_result['error']);
        }
        
        $extraction_dir = $extract_result['directory'];
        
        // Find JSON file in extraction
        $json_files = glob($extraction_dir . '/*.json');
        if (empty($json_files)) {
            throw new Exception('No JSON export file found in ZIP archive');
        }
        
        $json_file = $json_files[0]; // Use first JSON file found
        WC_PIE_Logger::log('ZIP IMPORT - Found JSON file', array('file' => $json_file));
        
        // Load and parse JSON data
        $json_content = file_get_contents($json_file);
        $import_data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format: ' . json_last_error_msg());
        }
        
        // Set extraction directory for image processing
        $this->extracted_images_dir = $extraction_dir . '/images';
        
        // Process import with local images
        $options['use_local_images'] = true;
        $options['local_images_dir'] = $this->extracted_images_dir;
        
        // Build image hash cache for deduplication
        if (!empty($options['dedupe_images'])) {
            $this->build_image_hash_cache();
        }
        
        $result = $this->import_products($import_data, $options);
        
        // Cleanup extraction directory
        $this->cleanup_temp_directory($extraction_dir);
        
        return $result;
    }
    
    /**
     * Extract ZIP file to temp directory
     * 
     * @param string $zip_file_path
     * @return array Result with success status and directory or error
     */
    private function extract_zip_file($zip_file_path) {
        if (!class_exists('ZipArchive')) {
            return array(
                'success' => false,
                'error' => 'ZipArchive class not available. Please enable ZIP extension in PHP.'
            );
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($zip_file_path);
        
        if ($result !== true) {
            $error_messages = array(
                ZipArchive::ER_OK => 'No error',
                ZipArchive::ER_MULTIDISK => 'Multi-disk zip archives not supported',
                ZipArchive::ER_RENAME => 'Renaming temporary file failed',
                ZipArchive::ER_CLOSE => 'Closing zip archive failed',
                ZipArchive::ER_SEEK => 'Seek error',
                ZipArchive::ER_READ => 'Read error',
                ZipArchive::ER_WRITE => 'Write error',
                ZipArchive::ER_CRC => 'CRC error',
                ZipArchive::ER_ZIPCLOSED => 'Containing zip archive was closed',
                ZipArchive::ER_NOENT => 'No such file',
                ZipArchive::ER_EXISTS => 'File already exists',
                ZipArchive::ER_OPEN => 'Can not open file',
                ZipArchive::ER_TMPOPEN => 'Failure to create temporary file',
                ZipArchive::ER_ZLIB => 'Zlib error',
                ZipArchive::ER_MEMORY => 'Memory allocation failure',
                ZipArchive::ER_CHANGED => 'Entry has been changed',
                ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
                ZipArchive::ER_EOF => 'Premature EOF',
                ZipArchive::ER_INVAL => 'Invalid argument',
                ZipArchive::ER_NOZIP => 'Not a zip archive',
                ZipArchive::ER_INTERNAL => 'Internal error',
                ZipArchive::ER_INCONS => 'Zip archive inconsistent',
                ZipArchive::ER_REMOVE => 'Can not remove file',
                ZipArchive::ER_DELETED => 'Entry has been deleted'
            );
            
            $error_msg = isset($error_messages[$result]) ? $error_messages[$result] : 'Unknown error code: ' . $result;
            return array(
                'success' => false,
                'error' => 'Failed to open ZIP file: ' . $error_msg
            );
        }
        
        // Create unique extraction directory
        $extract_dir = $this->temp_dir . '/extract_' . uniqid();
        if (!wp_mkdir_p($extract_dir)) {
            $zip->close();
            return array(
                'success' => false,
                'error' => 'Failed to create extraction directory: ' . $extract_dir
            );
        }
        
        // Extract all files
        $extract_result = $zip->extractTo($extract_dir);
        $zip->close();
        
        if (!$extract_result) {
            return array(
                'success' => false,
                'error' => 'Failed to extract ZIP contents'
            );
        }
        
        WC_PIE_Logger::log('ZIP EXTRACT - Success', array('directory' => $extract_dir));
        
        return array(
            'success' => true,
            'directory' => $extract_dir
        );
    }
    
    /**
     * Build image hash cache for existing WordPress media
     */
    private function build_image_hash_cache() {
        WC_PIE_Logger::log('HASH CACHE - Building image hash cache');
        
        global $wpdb;
        
        // Get all attachment IDs with hash metadata
        $results = $wpdb->get_results(
            "SELECT post_id, meta_value as hash 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_wc_pie_image_hash'"
        );
        
        foreach ($results as $result) {
            $this->image_hash_cache[$result->hash] = $result->post_id;
        }
        
        WC_PIE_Logger::log('HASH CACHE - Built cache', array('count' => count($this->image_hash_cache)));
    }
    
    /**
     * Check if image with hash already exists
     * 
     * @param string $hash
     * @return int|null Attachment ID if exists, null if not found
     */
    private function get_existing_image_by_hash($hash) {
        return isset($this->image_hash_cache[$hash]) ? $this->image_hash_cache[$hash] : null;
    }
    
    /**
     * Import products from data array
     * 
     * @param array $import_data Full import data structure
     * @param array $options Import options
     * @return array Import results
     */
    public function import_products($import_data, $options = array()) {
        WC_PIE_Logger::log('IMPORT PRODUCTS - Start', array('options' => $options));
        
        if (!isset($import_data['products']) || !is_array($import_data['products'])) {
            throw new Exception('Invalid import data structure - missing products array');
        }
        
        $products = $import_data['products'];
        $total_products = count($products);
        
        if ($total_products === 0) {
            throw new Exception('No products found in import data');
        }
        
        $results = array(
            'success' => true,
            'total_products' => $total_products,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array(),
            'imported_images' => 0,
            'deduplicated_images' => 0,
            'products_created' => array(),
            'products_updated' => array(),
            'processing_time' => 0,
            'start_time' => time()
        );
        
        foreach ($products as $index => $product_data) {
            try {
                $import_result = $this->import_single_product($product_data, $options);
                
                if ($import_result['action'] === 'created') {
                    $results['imported']++;
                    $results['products_created'][] = $import_result;
                } else if ($import_result['action'] === 'updated') {
                    $results['updated']++;
                    $results['products_updated'][] = $import_result;
                }
                
                // Track image statistics
                if (isset($import_result['images_imported'])) {
                    $results['imported_images'] += $import_result['images_imported'];
                }
                if (isset($import_result['images_deduplicated'])) {
                    $results['deduplicated_images'] += $import_result['images_deduplicated'];
                }
                
            } catch (Exception $e) {
                $results['skipped']++;
                $results['errors'][] = array(
                    'product_index' => $index,
                    'product_name' => isset($product_data['name']) ? $product_data['name'] : 'Unknown',
                    'product_sku' => isset($product_data['sku']) ? $product_data['sku'] : 'No SKU',
                    'error' => $e->getMessage()
                );
                
                WC_PIE_Logger::log('IMPORT PRODUCTS - Product error', array(
                    'index' => $index,
                    'name' => isset($product_data['name']) ? $product_data['name'] : 'Unknown',
                    'error' => $e->getMessage()
                ));
            }
        }
        
        $results['processing_time'] = time() - $results['start_time'];
        
        WC_PIE_Logger::log('IMPORT PRODUCTS - Complete', $results);
        
        return $results;
    }
    
    /**
     * Cleanup temporary directory
     * 
     * @param string $directory
     */
    private function cleanup_temp_directory($directory) {
        if (file_exists($directory)) {
            $this->recursive_rmdir($directory);
            WC_PIE_Logger::log('CLEANUP - Removed temp directory', array('directory' => $directory));
        }
    }
    
    /**
     * Recursively remove directory and contents
     * 
     * @param string $dir
     */
    private function recursive_rmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (is_dir($dir . '/' . $object) && !is_link($dir . '/' . $object)) {
                        $this->recursive_rmdir($dir . '/' . $object);
                    } else {
                        unlink($dir . '/' . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    public function import_single_product($product_data, $options = array()) {
        WC_PIE_Logger::log('IMPORT SINGLE PRODUCT - Start', array('data' => $product_data, 'options' => $options));
        
        $update_existing = isset($options['update_existing']) ? $options['update_existing'] : false;
        $skip_images = isset($options['skip_images']) ? $options['skip_images'] : false;
        $preserve_ids = isset($options['preserve_ids']) ? $options['preserve_ids'] : false;

        // Check if product exists by SKU or ID
        $existing_product_id = null;
        if (!empty($product_data['sku'])) {
            $existing_product_id = wc_get_product_id_by_sku($product_data['sku']);
        }
        
        // If preserving IDs and product has ID, check if that ID exists
        if ($preserve_ids && !empty($product_data['id'])) {
            $existing_by_id = wc_get_product($product_data['id']);
            if ($existing_by_id && !$existing_product_id) {
                $existing_product_id = $product_data['id'];
            }
        }
        
        if ($existing_product_id && !$update_existing) {
            throw new Exception('Product with SKU ' . ($product_data['sku'] ?? 'N/A') . ' already exists (ID: ' . $existing_product_id . ')');
        }
        
        // Create or update product
        if ($existing_product_id && $update_existing) {
            $product = wc_get_product($existing_product_id);
            $action = 'updated';
            WC_PIE_Logger::log('IMPORT SINGLE PRODUCT - Updating existing', array('id' => $existing_product_id));
        } else {
            $product_type = $product_data['type'] ?? 'simple';
            
            // Create product based on type
            switch ($product_type) {
                case 'variable':
                    $product = new WC_Product_Variable();
                    break;
                case 'grouped':
                    $product = new WC_Product_Grouped();
                    break;
                case 'external':
                    $product = new WC_Product_External();
                    break;
                default:
                    $product = new WC_Product_Simple();
            }
            
            $action = 'created';
            WC_PIE_Logger::log('IMPORT SINGLE PRODUCT - Creating new', array('type' => $product_type));
        }
        
        // Set basic product data - match export format exactly
        $product->set_name($product_data['name'] ?? '');
        if (!empty($product_data['slug'])) {
            $product->set_slug($product_data['slug']);
        }
        $product->set_status($product_data['status'] ?? 'publish');
        $product->set_featured($product_data['featured'] ?? false);
        $product->set_catalog_visibility($product_data['catalog_visibility'] ?? 'visible');
        $product->set_description($product_data['description'] ?? '');
        $product->set_short_description($product_data['short_description'] ?? '');
        $product->set_sku($product_data['sku'] ?? '');
        
        // Pricing
        if (isset($product_data['regular_price']) && $product_data['regular_price'] !== '') {
            $product->set_regular_price($product_data['regular_price']);
        }
        if (isset($product_data['sale_price']) && $product_data['sale_price'] !== '') {
            $product->set_sale_price($product_data['sale_price']);
        }
        
        // Sale dates
        if (!empty($product_data['date_on_sale_from'])) {
            $product->set_date_on_sale_from($product_data['date_on_sale_from']);
        }
        if (!empty($product_data['date_on_sale_to'])) {
            $product->set_date_on_sale_to($product_data['date_on_sale_to']);
        }
        
        // Tax settings
        $product->set_tax_status($product_data['tax_status'] ?? 'taxable');
        $product->set_tax_class($product_data['tax_class'] ?? '');
        
        // Stock management
        $product->set_manage_stock($product_data['manage_stock'] ?? false);
        if ($product_data['manage_stock'] && isset($product_data['stock_quantity'])) {
            $product->set_stock_quantity($product_data['stock_quantity']);
        }
        $product->set_stock_status($product_data['stock_status'] ?? 'instock');
        $product->set_backorders($product_data['backorders'] ?? 'no');
        if (isset($product_data['low_stock_amount'])) {
            $product->set_low_stock_amount($product_data['low_stock_amount']);
        }
        $product->set_sold_individually($product_data['sold_individually'] ?? false);
        
        // Physical properties
        if (isset($product_data['weight'])) {
            $product->set_weight($product_data['weight']);
        }
        if (isset($product_data['length'])) {
            $product->set_length($product_data['length']);
        }
        if (isset($product_data['width'])) {
            $product->set_width($product_data['width']);
        }
        if (isset($product_data['height'])) {
            $product->set_height($product_data['height']);
        }
        
        // Additional settings
        $product->set_virtual($product_data['virtual'] ?? false);
        $product->set_downloadable($product_data['downloadable'] ?? false);
        $product->set_reviews_allowed($product_data['reviews_allowed'] ?? true);
        if (!empty($product_data['purchase_note'])) {
            $product->set_purchase_note($product_data['purchase_note']);
        }
        if (isset($product_data['menu_order'])) {
            $product->set_menu_order($product_data['menu_order']);
        }
        
        // Set product relationships
        $product->set_upsell_ids($product_data['upsell_ids'] ?? array());
        $product->set_cross_sell_ids($product_data['cross_sell_ids'] ?? array());
        $product->set_parent_id($product_data['parent_id'] ?? 0);
        $product->set_virtual($product_data['virtual'] ?? false);
        $product->set_downloadable($product_data['downloadable'] ?? false);
        $product->set_category_ids($product_data['category_ids'] ?? array());
        $product->set_tag_ids($product_data['tag_ids'] ?? array());
        $product->set_shipping_class_id($product_data['shipping_class_id'] ?? 0);
        
        // Set downloads
        if (!empty($product_data['downloads'])) {
            $product->set_downloads($product_data['downloads']);
        }
        $product->set_download_expiry($product_data['download_expiry'] ?? -1);
        $product->set_download_limit($product_data['download_limit'] ?? -1);
        
        // Set images (if not skipped)
        if (!$skip_images) {
            WC_PIE_Logger::log('IMPORT SINGLE PRODUCT - Processing images', array('skip_images' => $skip_images));
            
            // Handle new image format with URLs and metadata
            if (!empty($product_data['image']) && is_array($product_data['image'])) {
                $main_image_id = $this->import_image_from_data($product_data['image'], $options);
                if ($main_image_id) {
                    $product->set_image_id($main_image_id);
                }
            }
            // Fallback to legacy format
            elseif (!empty($product_data['image_id'])) {
                $product->set_image_id($product_data['image_id']);
            }

            // Handle new gallery format with URLs and metadata
            if (!empty($product_data['gallery_images']) && is_array($product_data['gallery_images'])) {
                $gallery_ids = $this->import_gallery_images($product_data['gallery_images'], $options);
                if (!empty($gallery_ids)) {
                    $product->set_gallery_image_ids($gallery_ids);
                }
            }
            // Fallback to legacy format
            elseif (!empty($product_data['gallery_image_ids'])) {
                $product->set_gallery_image_ids($product_data['gallery_image_ids']);
            }
        } else {
            WC_PIE_Logger::log('IMPORT SINGLE PRODUCT - Skipping images', array('skip_images' => $skip_images));
        }
        
        // Set attributes
        if (!empty($product_data['attributes'])) {
            $attributes = array();
            foreach ($product_data['attributes'] as $attr_data) {
                $attribute = new WC_Product_Attribute();
                $attribute->set_id($attr_data['id'] ?? 0);
                $attribute->set_name($attr_data['name'] ?? '');
                $attribute->set_options($attr_data['options'] ?? array());
                $attribute->set_position($attr_data['position'] ?? 0);
                $attribute->set_visible($attr_data['visible'] ?? true);
                $attribute->set_variation($attr_data['variation'] ?? false);
                
                $attributes[] = $attribute;
            }
            $product->set_attributes($attributes);
        }
        
        // Set default attributes
        if (!empty($product_data['default_attributes'])) {
            $product->set_default_attributes($product_data['default_attributes']);
        }
        
        // Save product
        $product_id = $product->save();
        
        // Import meta data
        if (!empty($product_data['meta_data'])) {
            foreach ($product_data['meta_data'] as $meta) {
                if (!empty($meta['key'])) {
                    update_post_meta($product_id, $meta['key'], $meta['value']);
                }
            }
        }
        
        // Import custom fields
        if (!empty($product_data['custom_fields'])) {
            foreach ($product_data['custom_fields'] as $key => $values) {
                foreach ($values as $value) {
                    add_post_meta($product_id, $key, maybe_unserialize($value));
                }
            }
        }
        
        // Import variations for variable products
        if (!empty($product_data['variations']) && $product->is_type('variable')) {
            foreach ($product_data['variations'] as $variation_data) {
                // Recursive call for variations
                $variation_data['parent_id'] = $product_id; // Ensure parent ID is set
                $this->import_single_product($variation_data, $options);
            }
        }
        
        return array('action' => $action, 'product_id' => $product_id);
    }
    
    /**
     * Import product attributes
     */
    private function import_product_attributes($product_id, $attributes_data) {
        WC_PIE_Logger::log('IMPORT ATTRIBUTES - Start', array('product_id' => $product_id, 'attributes' => $attributes_data));
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $attributes = array();
        foreach ($attributes_data as $attr_data) {
            $attribute = new WC_Product_Attribute();
            
            if (!empty($attr_data['id'])) {
                $attribute->set_id($attr_data['id']);
            }
            
            $attribute->set_name($attr_data['name'] ?? '');
            $attribute->set_options($attr_data['options'] ?? array());
            $attribute->set_position($attr_data['position'] ?? 0);
            $attribute->set_visible($attr_data['visible'] ?? true);
            $attribute->set_variation($attr_data['variation'] ?? false);
            
            $attributes[] = $attribute;
        }
        
        $product->set_attributes($attributes);
        $product->save();
        
        return true;
    }
    
    /**
     * Import product variations
     */
    private function import_product_variations($parent_id, $variations_data, $options = array()) {
        WC_PIE_Logger::log('IMPORT VARIATIONS - Start', array('parent_id' => $parent_id, 'count' => count($variations_data)));
        
        foreach ($variations_data as $variation_data) {
            try {
                // Set parent ID
                $variation_data['parent_id'] = $parent_id;
                $variation_data['type'] = 'variation';
                
                // Create variation
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($parent_id);
                
                // Set variation data
                if (!empty($variation_data['sku'])) {
                    $variation->set_sku($variation_data['sku']);
                }
                
                if (isset($variation_data['regular_price'])) {
                    $variation->set_regular_price($variation_data['regular_price']);
                }
                
                if (isset($variation_data['sale_price'])) {
                    $variation->set_sale_price($variation_data['sale_price']);
                }
                
                if (isset($variation_data['stock_quantity'])) {
                    $variation->set_manage_stock(true);
                    $variation->set_stock_quantity($variation_data['stock_quantity']);
                }
                
                if (isset($variation_data['stock_status'])) {
                    $variation->set_stock_status($variation_data['stock_status']);
                }
                
                // Set variation attributes
                if (!empty($variation_data['attributes'])) {
                    $variation->set_attributes($variation_data['attributes']);
                }
                
                // Set variation image
                if (!$options['skip_images']) {
                    // Handle new image format with URLs and metadata
                    if (!empty($variation_data['image']) && is_array($variation_data['image'])) {
                        $variation_image_id = $this->import_image_from_data($variation_data['image'], $options);
                        if ($variation_image_id) {
                            $variation->set_image_id($variation_image_id);
                        }
                    }
                    // Fallback to legacy format
                    elseif (!empty($variation_data['image_id'])) {
                        $variation->set_image_id($variation_data['image_id']);
                    }
                }
                
                // Save variation
                $variation_id = $variation->save();
                
                // Import variation meta data
                if (!empty($variation_data['meta_data'])) {
                    foreach ($variation_data['meta_data'] as $meta) {
                        if (!empty($meta['key'])) {
                            $variation->update_meta_data($meta['key'], $meta['value']);
                        }
                    }
                    $variation->save();
                }
                
                WC_PIE_Logger::log('IMPORT VARIATIONS - Variation imported', array('variation_id' => $variation_id));
                
            } catch (Exception $e) {
                WC_PIE_Logger::log('IMPORT VARIATIONS - Error', array(
                    'parent_id' => $parent_id,
                    'error' => $e->getMessage()
                ));
            }
        }
        
        return true;
    }

    /**
     * Import image from URL with deduplication using hash
     * 
     * @param array $image_data
     * @param array $options Import options including images_dir for ZIP imports
     * @return int|null Attachment ID or null if failed
     */
    private function import_image_from_data($image_data, $options = array()) {
        if (empty($image_data) || empty($image_data['url'])) {
            return null;
        }

        // Check if image already exists by hash
        if (!empty($image_data['hash'])) {
            $existing_id = $this->find_image_by_hash($image_data['hash']);
            if ($existing_id) {
                WC_PIE_Logger::log('IMPORT IMAGE - Found existing by hash', array('hash' => $image_data['hash'], 'id' => $existing_id));
                return $existing_id;
            }
        }

        // Check if image already exists by URL
        $existing_id = $this->find_image_by_url($image_data['url']);
        if ($existing_id) {
            WC_PIE_Logger::log('IMPORT IMAGE - Found existing by URL', array('url' => $image_data['url'], 'id' => $existing_id));
            return $existing_id;
        }

        try {
            // Check for local image from ZIP import first
            if (!empty($options['images_dir']) && !empty($image_data['local_path'])) {
                $local_image_path = $options['images_dir'] . '/' . basename($image_data['local_path']);
                if (file_exists($local_image_path)) {
                    $attachment_id = $this->import_local_image($local_image_path, $image_data);
                    if ($attachment_id) {
                        WC_PIE_Logger::log('IMPORT IMAGE - Imported from local ZIP file', array(
                            'local_path' => $local_image_path, 
                            'attachment_id' => $attachment_id
                        ));
                        return $attachment_id;
                    }
                }
            }
            
            // Fallback to downloading from URL
            $attachment_id = $this->download_and_import_image($image_data);
            
            if ($attachment_id) {
                // Store hash for future deduplication
                if (!empty($image_data['hash'])) {
                    update_post_meta($attachment_id, '_wc_pie_image_hash', $image_data['hash']);
                } else {
                    // Generate and store hash if not provided
                    $file_path = get_attached_file($attachment_id);
                    if ($file_path && file_exists($file_path)) {
                        $generated_hash = md5_file($file_path);
                        update_post_meta($attachment_id, '_wc_pie_image_hash', $generated_hash);
                    }
                }
                
                // Set image metadata
                $this->set_image_metadata($attachment_id, $image_data);
                
                WC_PIE_Logger::log('IMPORT IMAGE - Successfully imported', array('url' => $image_data['url'], 'id' => $attachment_id));
            }
            
            return $attachment_id;
            
        } catch (Exception $e) {
            WC_PIE_Logger::log('IMPORT IMAGE - Error', array('url' => $image_data['url'], 'error' => $e->getMessage()));
            return null;
        }
    }

    /**
     * Find existing image by hash
     * 
     * @param string $hash
     * @return int|null
     */
    private function find_image_by_hash($hash) {
        if (empty($hash)) {
            return null;
        }

        $query = new WP_Query(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => '_wc_pie_image_hash',
                    'value' => $hash,
                    'compare' => '='
                )
            ),
            'fields' => 'ids'
        ));

        return !empty($query->posts) ? $query->posts[0] : null;
    }

    /**
     * Find existing image by URL
     * 
     * @param string $url
     * @return int|null
     */
    private function find_image_by_url($url) {
        global $wpdb;
        
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
            '%' . basename(parse_url($url, PHP_URL_PATH))
        ));
        
        return $attachment_id ? (int) $attachment_id : null;
    }

    /**
     * Download and import image from URL
     * 
     * @param array $image_data
     * @return int|null
     */
    private function download_and_import_image($image_data) {
        if (empty($image_data['url'])) {
            return null;
        }

        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $url = $image_data['url'];

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            WC_PIE_Logger::log('IMPORT IMAGE - Error', array('url' => $url, 'error' => 'Invalid URL format'));
            return null;
        }

        // Development mode: Allow local URLs for testing
        // In production, you should set WP_DEBUG to false and this will skip local URLs
        $allow_local_urls = defined('WP_DEBUG') && WP_DEBUG;
        
        if (!$allow_local_urls) {
            // Check for local URLs that won't work on external sites (production mode)
            $parsed_url = parse_url($url);
            $hostname = $parsed_url['host'] ?? '';
            
            if (strpos($hostname, 'localhost') !== false || 
                strpos($hostname, '.local') !== false || 
                strpos($hostname, '127.0.0.1') !== false ||
                strpos($hostname, '192.168.') === 0 ||
                strpos($hostname, '10.') === 0) {
                
                WC_PIE_Logger::log('IMPORT IMAGE - Error', array(
                    'url' => $url, 
                    'error' => 'Local URL detected - skipped in production mode (set WP_DEBUG = true for development)'
                ));
                return null;
            }
        } else {
            WC_PIE_Logger::log('IMPORT IMAGE - Development Mode', array(
                'url' => $url, 
                'message' => 'Allowing local URL in development mode (WP_DEBUG = true)'
            ));
        }

        // Check if URL is accessible
        $response = wp_remote_head($url, array('timeout' => 30, 'sslverify' => false));
        if (is_wp_error($response)) {
            WC_PIE_Logger::log('IMPORT IMAGE - Error', array(
                'url' => $url, 
                'error' => 'Unable to access image URL: ' . $response->get_error_message()
            ));
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            WC_PIE_Logger::log('IMPORT IMAGE - Error', array(
                'url' => $url, 
                'error' => 'Image URL returned HTTP status: ' . $response_code
            ));
            return null;
        }

        // Use WordPress media_sideload_image function
        $attachment_id = media_sideload_image($url, 0, $image_data['title'] ?? '', 'id');
        
        if (is_wp_error($attachment_id)) {
            WC_PIE_Logger::log('IMPORT IMAGE - Error', array(
                'url' => $url, 
                'error' => 'Failed to download image: ' . $attachment_id->get_error_message()
            ));
            return null;
        }

        WC_PIE_Logger::log('IMPORT IMAGE - Success', array(
            'url' => $url, 
            'attachment_id' => $attachment_id,
            'mode' => $allow_local_urls ? 'development' : 'production'
        ));

        return $attachment_id;
    }

    /**
     * Set image metadata
     * 
     * @param int $attachment_id
     * @param array $image_data
     */
    private function set_image_metadata($attachment_id, $image_data) {
        // Update post data
        $update_data = array('ID' => $attachment_id);
        
        if (!empty($image_data['title'])) {
            $update_data['post_title'] = $image_data['title'];
        }
        
        if (!empty($image_data['caption'])) {
            $update_data['post_excerpt'] = $image_data['caption'];
        }
        
        if (!empty($image_data['description'])) {
            $update_data['post_content'] = $image_data['description'];
        }
        
        if (count($update_data) > 1) {
            wp_update_post($update_data);
        }

        // Set alt text
        if (!empty($image_data['alt'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $image_data['alt']);
        }

        // Update metadata if provided
        if (!empty($image_data['metadata']) && is_array($image_data['metadata'])) {
            wp_update_attachment_metadata($attachment_id, $image_data['metadata']);
        }
    }

    /**
     * Import gallery images from image data array
     * 
     * @param array $gallery_data
     * @param array $options Import options
     * @return array Array of imported attachment IDs
     */
    private function import_gallery_images($gallery_data, $options = array()) {
        if (empty($gallery_data) || !is_array($gallery_data)) {
            return array();
        }

        $gallery_ids = array();
        foreach ($gallery_data as $image_data) {
            $attachment_id = $this->import_image_from_data($image_data, $options);
            if ($attachment_id) {
                $gallery_ids[] = $attachment_id;
            }
        }

        return $gallery_ids;
    }

    /**
     * Import image from local file (ZIP extraction)
     * 
     * @param string $local_file_path Path to local image file
     * @param array $image_data Image metadata
     * @return int|null Attachment ID or null if failed
     */
    private function import_local_image($local_file_path, $image_data) {
        if (!file_exists($local_file_path)) {
            WC_PIE_Logger::log('IMPORT LOCAL IMAGE - File not found', array('file' => $local_file_path));
            return null;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Generate hash if not provided
        $file_hash = !empty($image_data['hash']) ? $image_data['hash'] : md5_file($local_file_path);
        
        // Check for existing image with same hash
        $existing_id = $this->get_existing_image_by_hash($file_hash);
        if ($existing_id) {
            WC_PIE_Logger::log('IMPORT LOCAL IMAGE - Found duplicate by hash', array(
                'file' => $local_file_path,
                'hash' => $file_hash,
                'existing_id' => $existing_id
            ));
            return $existing_id;
        }
        
        // Get file info
        $file_name = basename($local_file_path);
        $file_type = wp_check_filetype($file_name);
        $mime_type = $file_type['type'];
        
        if (!$mime_type || !in_array($mime_type, array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'))) {
            WC_PIE_Logger::log('IMPORT LOCAL IMAGE - Invalid file type', array(
                'file' => $local_file_path,
                'mime_type' => $mime_type
            ));
            return null;
        }
        
        // Copy file to WordPress uploads directory
        $upload_dir = wp_upload_dir();
        $upload_file = wp_unique_filename($upload_dir['path'], $file_name);
        $upload_path = $upload_dir['path'] . '/' . $upload_file;
        
        if (!copy($local_file_path, $upload_path)) {
            WC_PIE_Logger::log('IMPORT LOCAL IMAGE - Copy failed', array(
                'source' => $local_file_path,
                'destination' => $upload_path
            ));
            return null;
        }
        
        // Create attachment
        $attachment_data = array(
            'guid' => $upload_dir['url'] . '/' . $upload_file,
            'post_mime_type' => $mime_type,
            'post_title' => !empty($image_data['title']) ? $image_data['title'] : preg_replace('/\\.[^.]+$/', '', $upload_file),
            'post_content' => !empty($image_data['description']) ? $image_data['description'] : '',
            'post_excerpt' => !empty($image_data['caption']) ? $image_data['caption'] : '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment_data, $upload_path);
        
        if (is_wp_error($attachment_id)) {
            unlink($upload_path);
            WC_PIE_Logger::log('IMPORT LOCAL IMAGE - Insert attachment failed', array(
                'file' => $local_file_path,
                'error' => $attachment_id->get_error_message()
            ));
            return null;
        }
        
        // Generate attachment metadata
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $upload_path);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);
        
        // Set alt text
        if (!empty($image_data['alt'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $image_data['alt']);
        }
        
        // Store hash for deduplication
        update_post_meta($attachment_id, '_wc_pie_image_hash', $file_hash);
        $this->image_hash_cache[$file_hash] = $attachment_id;
        
        WC_PIE_Logger::log('IMPORT LOCAL IMAGE - Success', array(
            'file' => $local_file_path,
            'attachment_id' => $attachment_id,
            'hash' => $file_hash
        ));
        
        return $attachment_id;
    }
}