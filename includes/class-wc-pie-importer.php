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
            return array(
                'success' => false,
                'error' => 'Failed to open ZIP file (Error code: ' . $result . ')'
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
        
        $update_existing = !empty($options['update_existing']) && ($options['update_existing'] === true || $options['update_existing'] === '1' || $options['update_existing'] === 1);
        $skip_images = !empty($options['skip_images']) && ($options['skip_images'] === true || $options['skip_images'] === '1' || $options['skip_images'] === 1);
        $preserve_ids = !empty($options['preserve_ids']) && ($options['preserve_ids'] === true || $options['preserve_ids'] === '1' || $options['preserve_ids'] === 1);

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
        
        // Set basic product data
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
        
        // Set images (if not skipped)
        $images_imported = 0;
        $images_deduplicated = 0;
        
        if (!$skip_images) {
            WC_PIE_Logger::log('IMPORT SINGLE PRODUCT - Processing images', array('skip_images' => $skip_images));
            
            // Handle featured image
            if (!empty($product_data['image']) && is_array($product_data['image'])) {
                $main_image_id = $this->import_image_from_data($product_data['image'], $options);
                if ($main_image_id) {
                    $product->set_image_id($main_image_id);
                    if (isset($product_data['image']['hash']) && $this->get_existing_image_by_hash($product_data['image']['hash'])) {
                        $images_deduplicated++;
                    } else {
                        $images_imported++;
                    }
                }
            }
            
            // Handle gallery images
            if (!empty($product_data['gallery_images']) && is_array($product_data['gallery_images'])) {
                $gallery_ids = array();
                foreach ($product_data['gallery_images'] as $gallery_image) {
                    $gallery_id = $this->import_image_from_data($gallery_image, $options);
                    if ($gallery_id) {
                        $gallery_ids[] = $gallery_id;
                        if (isset($gallery_image['hash']) && $this->get_existing_image_by_hash($gallery_image['hash'])) {
                            $images_deduplicated++;
                        } else {
                            $images_imported++;
                        }
                    }
                }
                if (!empty($gallery_ids)) {
                    $product->set_gallery_image_ids($gallery_ids);
                }
            }
        }
        
        // Handle categories - prefer categories array with full data, fallback to category_ids
        if (!empty($product_data['categories'])) {
            $category_ids = $this->process_categories_with_data($product_data['categories']);
            $product->set_category_ids($category_ids);
        } elseif (!empty($product_data['category_ids'])) {
            $category_ids = $this->process_categories($product_data['category_ids']);
            $product->set_category_ids($category_ids);
        }
        
        // Handle tags - prefer tags array with full data, fallback to tag_ids
        if (!empty($product_data['tags'])) {
            $tag_ids = $this->process_tags_with_data($product_data['tags']);
            $product->set_tag_ids($tag_ids);
        } elseif (!empty($product_data['tag_ids'])) {
            $tag_ids = $this->process_tags($product_data['tag_ids']);
            $product->set_tag_ids($tag_ids);
        }
        
        // Save product
        $product_id = $product->save();
        
        if (!$product_id) {
            throw new Exception('Failed to save product');
        }
        
        // Handle variations for variable products
        if ($product->is_type('variable') && !empty($product_data['variations'])) {
            $this->import_product_variations($product_id, $product_data['variations'], $options);
        }
        
        WC_PIE_Logger::log('IMPORT SINGLE PRODUCT - Success', array(
            'product_id' => $product_id,
            'action' => $action,
            'name' => $product->get_name()
        ));
        
        return array(
            'id' => $product_id,
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'action' => $action,
            'images_imported' => $images_imported,
            'images_deduplicated' => $images_deduplicated
        );
    }

    /**
     * Import image from image data (URL, metadata, etc.)
     * 
     * @param array $image_data Image data array
     * @param array $options Import options
     * @return int|null Attachment ID or null if failed
     */
    private function import_image_from_data($image_data, $options = array()) {
        if (empty($image_data) || !is_array($image_data)) {
            return null;
        }

        WC_PIE_Logger::log('IMPORT IMAGE FROM DATA - Start', array('image_data' => $image_data, 'options' => $options));

        // Check for deduplication
        if (!empty($options['dedupe_images']) && !empty($image_data['hash'])) {
            $existing_id = $this->get_existing_image_by_hash($image_data['hash']);
            if ($existing_id) {
                WC_PIE_Logger::log('IMPORT IMAGE - Deduplicated', array(
                    'hash' => $image_data['hash'],
                    'existing_id' => $existing_id
                ));
                return $existing_id;
            }
        }

        // Determine source - local file from ZIP or URL
        if (!empty($options['use_local_images'])) {
            // Check for local_path first (hash-based filename from ZIP), then fallback to filename
            $local_filename = null;
            if (!empty($image_data['local_path'])) {
                // local_path already includes 'images/' prefix
                $local_filename = basename($image_data['local_path']);
            } elseif (!empty($image_data['filename'])) {
                $local_filename = $image_data['filename'];
            }
            
            if ($local_filename) {
                $local_file = $options['local_images_dir'] . '/' . $local_filename;
                if (file_exists($local_file)) {
                    $attachment_id = $this->import_local_image($local_file, $image_data);
                } else {
                    WC_PIE_Logger::log('IMPORT IMAGE - Local file not found', array('file' => $local_file, 'tried_local_path' => !empty($image_data['local_path']), 'tried_filename' => !empty($image_data['filename'])));
                    $attachment_id = null;
                }
            } else {
                WC_PIE_Logger::log('IMPORT IMAGE FROM DATA - Error', array('error' => 'No local_path or filename provided for local image'));
                $attachment_id = null;
            }
        } else if (!empty($image_data['url'])) {
            $attachment_id = $this->import_image_from_url($image_data['url'], $image_data, $options);
        } else {
            WC_PIE_Logger::log('IMPORT IMAGE FROM DATA - Error', array('error' => 'No URL, filename, or local file provided'));
            return null;
        }
        
        // Add to hash cache if successful and hash available
        if ($attachment_id && !empty($image_data['hash'])) {
            update_post_meta($attachment_id, '_wc_pie_image_hash', $image_data['hash']);
            $this->image_hash_cache[$image_data['hash']] = $attachment_id;
        }

        return $attachment_id;
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
            'post_title' => !empty($image_data['title']) ? $image_data['title'] : preg_replace('/\.[^.]+$/', '', $upload_file),
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
    
    /**
     * Import image from URL (fallback)
     * 
     * @param string $url
     * @param array $image_data 
     * @param array $options
     * @return int|null
     */
    private function import_image_from_url($url, $image_data, $options = array()) {
        WC_PIE_Logger::log('IMPORT IMAGE FROM URL - Start', array('url' => $url));
        
        if (empty($url)) {
            return null;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Use WordPress media_sideload_image function
        $attachment_id = media_sideload_image($url, 0, $image_data['title'] ?? '', 'id');
        
        if (is_wp_error($attachment_id)) {
            WC_PIE_Logger::log('IMPORT IMAGE FROM URL - Error', array(
                'url' => $url, 
                'error' => $attachment_id->get_error_message()
            ));
            return null;
        }

        WC_PIE_Logger::log('IMPORT IMAGE FROM URL - Success', array(
            'url' => $url, 
            'attachment_id' => $attachment_id
        ));

        return $attachment_id;
    }
    
    /**
     * Process categories - create if they don't exist
     * 
     * @param array $category_ids Original category IDs from export
     * @return array New category IDs
     */
    private function process_categories($category_ids) {
        if (empty($category_ids) || !is_array($category_ids)) {
            return array();
        }
        
        $new_category_ids = array();
        
        foreach ($category_ids as $old_category_id) {
            // Get category term from original site
            $category = get_term($old_category_id, 'product_cat');
            
            if (is_wp_error($category) || !$category) {
                WC_PIE_Logger::log('PROCESS CATEGORIES - Category not found', array('id' => $old_category_id));
                continue;
            }
            
            // Check if category exists by slug
            $existing_term = get_term_by('slug', $category->slug, 'product_cat');
            
            if ($existing_term) {
                $new_category_ids[] = $existing_term->term_id;
                WC_PIE_Logger::log('PROCESS CATEGORIES - Found existing', array(
                    'slug' => $category->slug,
                    'id' => $existing_term->term_id
                ));
            } else {
                // Create new category
                $parent_id = 0;
                if ($category->parent > 0) {
                    // Try to find parent category
                    $parent_term = get_term($category->parent, 'product_cat');
                    if (!is_wp_error($parent_term) && $parent_term) {
                        $parent_exists = get_term_by('slug', $parent_term->slug, 'product_cat');
                        if ($parent_exists) {
                            $parent_id = $parent_exists->term_id;
                        }
                    }
                }
                
                $new_term = wp_insert_term(
                    $category->name,
                    'product_cat',
                    array(
                        'slug' => $category->slug,
                        'description' => $category->description,
                        'parent' => $parent_id
                    )
                );
                
                if (!is_wp_error($new_term)) {
                    $new_category_ids[] = $new_term['term_id'];
                    WC_PIE_Logger::log('PROCESS CATEGORIES - Created new', array(
                        'name' => $category->name,
                        'id' => $new_term['term_id']
                    ));
                } else {
                    WC_PIE_Logger::log('PROCESS CATEGORIES - Creation failed', array(
                        'name' => $category->name,
                        'error' => $new_term->get_error_message()
                    ));
                }
            }
        }
        
        return $new_category_ids;
    }
    
    /**
     * Process categories with full term data - more reliable than IDs
     * 
     * @param array $categories Array of category data with name, slug, etc.
     * @return array New category IDs
     */
    private function process_categories_with_data($categories) {
        if (empty($categories) || !is_array($categories)) {
            return array();
        }
        
        $new_category_ids = array();
        
        foreach ($categories as $category_data) {
            if (empty($category_data['slug'])) {
                continue;
            }
            
            // Check if category exists by slug
            $existing_term = get_term_by('slug', $category_data['slug'], 'product_cat');
            
            if ($existing_term) {
                $new_category_ids[] = $existing_term->term_id;
                WC_PIE_Logger::log('PROCESS CATEGORIES DATA - Found existing', array(
                    'slug' => $category_data['slug'],
                    'id' => $existing_term->term_id
                ));
            } else {
                // Create new category
                $parent_id = 0;
                if (!empty($category_data['parent'])) {
                    // Try to find parent by ID first, then by slug if available
                    $parent_term = get_term($category_data['parent'], 'product_cat');
                    if (!is_wp_error($parent_term) && $parent_term) {
                        $parent_exists = get_term_by('slug', $parent_term->slug, 'product_cat');
                        if ($parent_exists) {
                            $parent_id = $parent_exists->term_id;
                        }
                    }
                }
                
                $new_term = wp_insert_term(
                    $category_data['name'],
                    'product_cat',
                    array(
                        'slug' => $category_data['slug'],
                        'description' => $category_data['description'] ?? '',
                        'parent' => $parent_id
                    )
                );
                
                if (!is_wp_error($new_term)) {
                    $new_category_ids[] = $new_term['term_id'];
                    WC_PIE_Logger::log('PROCESS CATEGORIES DATA - Created new', array(
                        'name' => $category_data['name'],
                        'slug' => $category_data['slug'],
                        'id' => $new_term['term_id']
                    ));
                } else {
                    WC_PIE_Logger::log('PROCESS CATEGORIES DATA - Creation failed', array(
                        'name' => $category_data['name'],
                        'error' => $new_term->get_error_message()
                    ));
                }
            }
        }
        
        return $new_category_ids;
    }
    
    /**
     * Process tags - create if they don't exist
     * 
     * @param array $tag_ids Original tag IDs from export
     * @return array New tag IDs
     */
    private function process_tags($tag_ids) {
        if (empty($tag_ids) || !is_array($tag_ids)) {
            return array();
        }
        
        $new_tag_ids = array();
        
        foreach ($tag_ids as $old_tag_id) {
            $tag = get_term($old_tag_id, 'product_tag');
            
            if (is_wp_error($tag) || !$tag) {
                continue;
            }
            
            // Check if tag exists by slug
            $existing_term = get_term_by('slug', $tag->slug, 'product_tag');
            
            if ($existing_term) {
                $new_tag_ids[] = $existing_term->term_id;
            } else {
                // Create new tag
                $new_term = wp_insert_term(
                    $tag->name,
                    'product_tag',
                    array(
                        'slug' => $tag->slug,
                        'description' => $tag->description
                    )
                );
                
                if (!is_wp_error($new_term)) {
                    $new_tag_ids[] = $new_term['term_id'];
                }
            }
        }
        
        return $new_tag_ids;
    }
    
    /**
     * Process tags with full term data - more reliable than IDs
     * 
     * @param array $tags Array of tag data with name, slug, etc.
     * @return array New tag IDs
     */
    private function process_tags_with_data($tags) {
        if (empty($tags) || !is_array($tags)) {
            return array();
        }
        
        $new_tag_ids = array();
        
        foreach ($tags as $tag_data) {
            if (empty($tag_data['slug'])) {
                continue;
            }
            
            // Check if tag exists by slug
            $existing_term = get_term_by('slug', $tag_data['slug'], 'product_tag');
            
            if ($existing_term) {
                $new_tag_ids[] = $existing_term->term_id;
                WC_PIE_Logger::log('PROCESS TAGS DATA - Found existing', array(
                    'slug' => $tag_data['slug'],
                    'id' => $existing_term->term_id
                ));
            } else {
                // Create new tag
                $new_term = wp_insert_term(
                    $tag_data['name'],
                    'product_tag',
                    array(
                        'slug' => $tag_data['slug'],
                        'description' => $tag_data['description'] ?? ''
                    )
                );
                
                if (!is_wp_error($new_term)) {
                    $new_tag_ids[] = $new_term['term_id'];
                    WC_PIE_Logger::log('PROCESS TAGS DATA - Created new', array(
                        'name' => $tag_data['name'],
                        'slug' => $tag_data['slug'],
                        'id' => $new_term['term_id']
                    ));
                } else {
                    WC_PIE_Logger::log('PROCESS TAGS DATA - Creation failed', array(
                        'name' => $tag_data['name'],
                        'error' => $new_term->get_error_message()
                    ));
                }
            }
        }
        
        return $new_tag_ids;
    }
    
    /**
     * Import product variations
     * 
     * @param int $parent_id Parent product ID
     * @param array $variations Variations data
     * @param array $options Import options
     */
    private function import_product_variations($parent_id, $variations, $options = array()) {
        if (empty($variations) || !is_array($variations)) {
            return;
        }
        
        WC_PIE_Logger::log('IMPORT VARIATIONS - Start', array(
            'parent_id' => $parent_id,
            'count' => count($variations)
        ));
        
        foreach ($variations as $variation_data) {
            try {
                // Check if variation exists by SKU
                $existing_variation_id = null;
                if (!empty($variation_data['sku'])) {
                    $existing_variation_id = wc_get_product_id_by_sku($variation_data['sku']);
                }
                
                if ($existing_variation_id && !empty($options['update_existing'])) {
                    $variation = new WC_Product_Variation($existing_variation_id);
                } else {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($parent_id);
                }
                
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
                    $variation->set_stock_quantity($variation_data['stock_quantity']);
                }
                if (isset($variation_data['stock_status'])) {
                    $variation->set_stock_status($variation_data['stock_status']);
                }
                if (isset($variation_data['manage_stock'])) {
                    $variation->set_manage_stock($variation_data['manage_stock']);
                }
                if (isset($variation_data['description'])) {
                    $variation->set_description($variation_data['description']);
                }
                
                // Set variation attributes
                if (!empty($variation_data['attributes'])) {
                    $attributes = array();
                    foreach ($variation_data['attributes'] as $attr) {
                        if (isset($attr['name']) && isset($attr['option'])) {
                            $attributes[$attr['name']] = $attr['option'];
                        }
                    }
                    $variation->set_attributes($attributes);
                }
                
                // Handle variation image
                if (!empty($variation_data['image']) && empty($options['skip_images'])) {
                    $image_id = $this->import_image_from_data($variation_data['image'], $options);
                    if ($image_id) {
                        $variation->set_image_id($image_id);
                    }
                }
                
                $variation->save();
                
                WC_PIE_Logger::log('IMPORT VARIATIONS - Success', array(
                    'variation_id' => $variation->get_id(),
                    'sku' => $variation->get_sku()
                ));
                
            } catch (Exception $e) {
                WC_PIE_Logger::log('IMPORT VARIATIONS - Error', array(
                    'error' => $e->getMessage(),
                    'variation_data' => $variation_data
                ));
            }
        }
    }
}