<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_PIE_Ajax {

    private $exporter;
    private $importer;

    public function __construct() {
        require_once plugin_dir_path(__FILE__) . 'class-wc-pie-exporter.php';
        require_once plugin_dir_path(__FILE__) . 'class-wc-pie-importer.php';
        
        $this->exporter = new WC_PIE_Exporter();
        $this->importer = new WC_PIE_Importer();

        add_action('wp_ajax_pie_init_export', array($this, 'init_export'));
        add_action('wp_ajax_pie_process_export_batch', array($this, 'process_export_batch'));
        add_action('wp_ajax_pie_finish_export', array($this, 'finish_export'));
        
        add_action('wp_ajax_pie_init_import', array($this, 'init_import'));
        add_action('wp_ajax_pie_process_import_batch', array($this, 'process_import_batch'));
        add_action('wp_ajax_pie_analyze_import', array($this, 'analyze_import'));
        add_action('wp_ajax_pie_process_zip_import', array($this, 'process_zip_import'));
        
        add_action('wp_ajax_pie_preview_export', array($this, 'preview_export'));
        add_action('wp_ajax_preview_export', array($this, 'preview_export')); // Legacy support
        add_action('wp_ajax_pie_view_logs', array($this, 'view_logs'));
        add_action('wp_ajax_pie_clear_logs', array($this, 'clear_logs'));
    }

    public function preview_export() {
        WC_PIE_Logger::log('PREVIEW EXPORT STARTED');
        check_ajax_referer('product_ie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            WC_PIE_Logger::log('PREVIEW EXPORT - Permission denied');
            wp_send_json_error('Insufficient permissions');
        }
        
        $filters = $_POST; // Sanitize in exporter
        WC_PIE_Logger::log('PREVIEW EXPORT - Raw POST data', $_POST);
        
        $args = $this->exporter->build_export_query($filters);
        $args['posts_per_page'] = 5; // Limit for preview
        $args['fields'] = 'ids';
        
        WC_PIE_Logger::log('PREVIEW EXPORT - Final query args', $args);
        
        $product_ids = get_posts($args);
        WC_PIE_Logger::log('PREVIEW EXPORT - Product IDs found', $product_ids);
        
        $sample_products = array();
        
        foreach ($product_ids as $id) {
            $product = wc_get_product($id);
            if (!$product) continue;
            $sample_products[] = $product->get_name() . ' (ID: ' . $product->get_id() . ', SKU: ' . $product->get_sku() . ')';
        }
        
        // Get total count
        $args['posts_per_page'] = -1;
        $all_ids = get_posts($args);
        
        $response_data = array(
            'count' => count($all_ids),
            'sample' => $sample_products,
            'categories' => isset($_POST['product_categories']) ? implode(', ', (array)$_POST['product_categories']) : 'All',
            'types' => isset($_POST['product_types']) ? implode(', ', (array)$_POST['product_types']) : 'All',
            'status' => isset($_POST['product_status']) ? implode(', ', (array)$_POST['product_status']) : 'All'
        );
        
        wp_send_json_success($response_data);
    }

    public function init_export() {
        WC_PIE_Logger::log('INIT EXPORT STARTED');
        check_ajax_referer('product_ie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            WC_PIE_Logger::log('INIT EXPORT - Permission denied');
            wp_send_json_error('Insufficient permissions');
        }

        // Use the same approach as preview_export to ensure consistency
        $filters = $_POST; // Sanitize in exporter
        WC_PIE_Logger::log('INIT EXPORT - Raw POST data', $_POST);
        
        // First, let's test with a simple basic query like preview does
        $preview_args = $this->exporter->build_export_query($filters);
        $preview_args['posts_per_page'] = 5;
        $preview_args['fields'] = 'ids';
        WC_PIE_Logger::log('INIT EXPORT - Preview args', $preview_args);
        $preview_ids = get_posts($preview_args);
        WC_PIE_Logger::log('INIT EXPORT - Preview IDs', $preview_ids);
        
        // Now build the full export query
        $args = $this->exporter->build_export_query($filters);
        $args['fields'] = 'ids';
        WC_PIE_Logger::log('INIT EXPORT - Full export args', $args);
        $product_ids = get_posts($args);
        WC_PIE_Logger::log('INIT EXPORT - Full export IDs', $product_ids);
        $total_products = count($product_ids);
        
        // Test if ANY products exist at all
        $basic_test = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 5
        ));
        WC_PIE_Logger::log('INIT EXPORT - Basic product test (any status)', $basic_test);

        // Debug logging to identify the issue
        error_log('WC PIE Init Export - Preview IDs found: ' . count($preview_ids));
        error_log('WC PIE Init Export - Full Export IDs found: ' . count($product_ids));
        error_log('WC PIE Init Export - Filters: ' . print_r($filters, true));

        if ($total_products === 0) {
            WC_PIE_Logger::log('INIT EXPORT - No products found, attempting fallbacks');
            // If preview found products but export didn't, there's a query difference
            if (count($preview_ids) > 0) {
                WC_PIE_Logger::log('INIT EXPORT - Preview had products, trying fallback');
                // Use preview logic for export as a fallback
                $fallback_args = $this->exporter->build_export_query($filters);
                $fallback_args['fields'] = 'ids';
                $fallback_args['posts_per_page'] = -1; // Get all instead of limiting
                $product_ids = get_posts($fallback_args);
                $total_products = count($product_ids);
                WC_PIE_Logger::log('INIT EXPORT - Fallback result', $product_ids);
                
                if ($total_products > 0) {
                    error_log('WC PIE Init Export - Fallback query successful, found: ' . $total_products);
                } else {
                    WC_PIE_Logger::log('INIT EXPORT - Fallback failed, trying ultimate fallback');
                    // Ultimate fallback - just get all published products
                    $ultimate_fallback = array(
                        'post_type' => 'product',
                        'post_status' => 'publish',
                        'fields' => 'ids',
                        'posts_per_page' => -1
                    );
                    $product_ids = get_posts($ultimate_fallback);
                    $total_products = count($product_ids);
                    WC_PIE_Logger::log('INIT EXPORT - Ultimate fallback result', $product_ids);
                    if ($total_products > 0) {
                        error_log('WC PIE Init Export - Ultimate fallback successful, found: ' . $total_products);
                    }
                }
            }
        }

        if ($total_products === 0) {
            $debug_msg = 'No products found to export. Preview found: ' . count($preview_ids) . ' products, Export found: ' . $total_products . ' products.';
            WC_PIE_Logger::log('INIT EXPORT - FINAL ERROR', array(
                'message' => $debug_msg,
                'preview_count' => count($preview_ids),
                'export_count' => $total_products,
                'basic_test_count' => count($basic_test)
            ));
            error_log('WC PIE Export Error: ' . $debug_msg);
            error_log('WC PIE Export Filters: ' . json_encode($filters));
            wp_send_json_error($debug_msg);
        }

        // Create temp file
        $upload_dir = wp_upload_dir();
        $filename = 'wc-export-' . uniqid() . '.json';
        $file_path = $upload_dir['basedir'] . '/wc-product-import-export/' . $filename;
        
        if (!file_exists(dirname($file_path))) {
            wp_mkdir_p(dirname($file_path));
        }

        // Initialize JSON file
        $header = array(
            'version' => '1.0.0',
            'export_date' => current_time('mysql'),
            'site_url' => get_site_url(),
            'products' => array() // We will append to this array structure manually
        );
        
        // Write header, but leave the array open
        // We write: {"version": "...", ..., "products": [
        $json_header = json_encode($header, JSON_PRETTY_PRINT);
        // Remove the last "]" and "}" to keep it open
        $json_header = substr(trim($json_header), 0, -2); // Remove ]}
        // Actually json_encode with empty array gives "products": []
        // So we remove the last ] and }
        // "products": [] } -> remove ] } -> "products": [
        
        // Let's do it manually to be safe
        $header_data = array(
            'version' => '1.0.0',
            'export_date' => current_time('mysql'),
            'site_url' => get_site_url(),
        );
        $json_start = json_encode($header_data, JSON_PRETTY_PRINT);
        $json_start = substr(trim($json_start), 0, -1); // Remove last }
        $json_start .= ',"products": [';

        file_put_contents($file_path, $json_start);

        // Store product IDs and filters in transients to avoid requerying
        set_transient('wc_pie_export_ids_' . $filename, $product_ids, HOUR_IN_SECONDS);
        set_transient('wc_pie_export_filters_' . $filename, $filters, HOUR_IN_SECONDS);
        
        // Also store the current export session for the user
        $export_session = array(
            'filename' => $filename,
            'total' => $total_products,
            'product_ids' => $product_ids,
            'filters' => $filters
        );
        set_transient('wc_pie_current_export_' . get_current_user_id(), $export_session, HOUR_IN_SECONDS);
        
        WC_PIE_Logger::log('INIT EXPORT - Session stored', array(
            'filename' => $filename,
            'total_products' => $total_products,
            'user_id' => get_current_user_id()
        ));

        wp_send_json_success(array(
            'total' => $total_products,
            'filename' => $filename,
            'batch_size' => 5
        ));
    }

    public function process_export_batch() {
        // Increase limits for batch processing
        ini_set('memory_limit', '256M');
        set_time_limit(120);
        
        WC_PIE_Logger::log('PROCESS EXPORT BATCH STARTED');
        check_ajax_referer('product_ie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            WC_PIE_Logger::log('PROCESS EXPORT BATCH - Permission denied');
            wp_send_json_error('Insufficient permissions');
        }
        
        $page = intval($_POST['page']);
        $batch_size = 5; // Fixed batch size
        
        WC_PIE_Logger::log('PROCESS EXPORT BATCH - Request data', array(
            'page' => $page,
            'batch_size' => $batch_size,
            'user_id' => get_current_user_id()
        ));
        
        // Get the latest export session
        $current_exports = get_transient('wc_pie_current_export_' . get_current_user_id());
        WC_PIE_Logger::log('PROCESS EXPORT BATCH - Current export session', $current_exports);
        
        $filename = $current_exports ? $current_exports['filename'] : null;
        
        if (!$filename || !$current_exports) {
            WC_PIE_Logger::log('PROCESS EXPORT BATCH - No session found');
            wp_send_json_error('Export session not found. Please restart the export.');
        }
        
        // Get stored product IDs from the session first, fallback to transient
        $product_ids = $current_exports['product_ids'] ?? get_transient('wc_pie_export_ids_' . $filename);
        $filters = $current_exports['filters'] ?? get_transient('wc_pie_export_filters_' . $filename);
        
        WC_PIE_Logger::log('PROCESS EXPORT BATCH - Retrieved data', array(
            'product_ids_count' => count($product_ids ?? []),
            'filters' => $filters
        ));
        
        if (!$product_ids) {
            WC_PIE_Logger::log('PROCESS EXPORT BATCH - Missing product IDs');
            wp_send_json_error('Export session expired. Please try again.');
        }

        $offset = ($page - 1) * $batch_size;
        $batch_ids = array_slice($product_ids, $offset, $batch_size);
        
        WC_PIE_Logger::log('PROCESS EXPORT BATCH - Batch calculation', array(
            'page' => $page,
            'offset' => $offset,
            'batch_size' => $batch_size,
            'batch_ids' => $batch_ids,
            'total_products' => count($product_ids)
        ));
        
        if (empty($batch_ids)) {
            WC_PIE_Logger::log('PROCESS EXPORT BATCH - No products in batch');
            wp_send_json_error('No products in this batch.');
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/wc-product-import-export/' . $filename;

        $options = array(
            'include_images' => isset($filters['include_images']) && $filters['include_images'] == '1',
            'include_variations' => isset($filters['include_variations']) && $filters['include_variations'] == '1',
            'include_meta' => isset($filters['include_meta']) && $filters['include_meta'] == '1',
            'include_attributes' => isset($filters['include_attributes']) && $filters['include_attributes'] == '1'
        );

        $json_chunk = '';
        $processed_products = 0;
        $errors = array();
        
        WC_PIE_Logger::log('PROCESS EXPORT BATCH - Starting product loop', array(
            'batch_ids' => $batch_ids,
            'options' => $options
        ));
        
        foreach ($batch_ids as $index => $id) {
            try {
                WC_PIE_Logger::log('PROCESS EXPORT BATCH - Processing product', array('id' => $id, 'index' => $index));
                
                $product_data = $this->exporter->export_single_product($id, $options);
                
                if ($product_data) {
                    // Add comma if not the very first product of the whole export
                    if ($offset + $index > 0) {
                        $json_chunk .= ",\n";
                    }
                    $json_chunk .= json_encode($product_data, JSON_PRETTY_PRINT);
                    $processed_products++;
                    WC_PIE_Logger::log('PROCESS EXPORT BATCH - Product exported successfully', array('id' => $id));
                } else {
                    $errors[] = "Product ID {$id} could not be exported";
                    WC_PIE_Logger::log('PROCESS EXPORT BATCH - Product export failed', array('id' => $id));
                }
            } catch (Exception $e) {
                $error_msg = "Product ID {$id} caused error: " . $e->getMessage();
                $errors[] = $error_msg;
                WC_PIE_Logger::log('PROCESS EXPORT BATCH - Exception caught', array(
                    'id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ));
            } catch (Throwable $t) {
                $error_msg = "Product ID {$id} caused fatal error: " . $t->getMessage();
                $errors[] = $error_msg;
                WC_PIE_Logger::log('PROCESS EXPORT BATCH - Fatal error caught', array(
                    'id' => $id,
                    'error' => $t->getMessage(),
                    'trace' => $t->getTraceAsString()
                ));
            }
        }

        WC_PIE_Logger::log('PROCESS EXPORT BATCH - Writing to file', array(
            'file_path' => $file_path,
            'json_chunk_length' => strlen($json_chunk),
            'processed_products' => $processed_products,
            'errors' => $errors,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ));
        
        // Only write if we have data
        if (!empty($json_chunk)) {
            $write_result = file_put_contents($file_path, $json_chunk, FILE_APPEND | LOCK_EX);
            if ($write_result === false) {
                WC_PIE_Logger::log('PROCESS EXPORT BATCH - File write failed', array('file_path' => $file_path));
                wp_send_json_error('Failed to write export data to file.');
            }
        }

        $processed_count = $offset + $processed_products;
        $percentage = min(100, round(($processed_count / count($product_ids)) * 100));
        $is_done = $processed_count >= count($product_ids);
        
        // Clean up memory
        unset($json_chunk);
        unset($product_data);
        
        WC_PIE_Logger::log('PROCESS EXPORT BATCH - Completed', array(
            'processed_count' => $processed_count,
            'total_products' => count($product_ids),
            'percentage' => $percentage,
            'is_done' => $is_done,
            'errors' => $errors,
            'memory_usage_after' => memory_get_usage(true)
        ));

        wp_send_json_success(array(
            'processed_count' => $processed_count,
            'percentage' => $percentage,
            'done' => $is_done,
            'next_page' => $is_done ? null : ($page + 1),
            'errors' => $errors,
            'batch_processed' => $processed_products
        ));
    }

    public function finish_export() {
        WC_PIE_Logger::log('FINISH EXPORT STARTED');
        check_ajax_referer('product_ie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            WC_PIE_Logger::log('FINISH EXPORT - Permission denied');
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get the current export session
        $current_exports = get_transient('wc_pie_current_export_' . get_current_user_id());
        $filename = $current_exports ? $current_exports['filename'] : null;
        
        WC_PIE_Logger::log('FINISH EXPORT - Session data', array(
            'current_exports' => $current_exports,
            'filename' => $filename
        ));
        
        if (!$filename) {
            WC_PIE_Logger::log('FINISH EXPORT - No filename found');
            wp_send_json_error('Export session not found.');
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/wc-product-import-export/' . $filename;
        
        WC_PIE_Logger::log('FINISH EXPORT - File info', array(
            'file_path' => $file_path,
            'file_exists' => file_exists($file_path),
            'file_size' => file_exists($file_path) ? filesize($file_path) : 0
        ));
        
        // Check if file exists
        if (!file_exists($file_path)) {
            WC_PIE_Logger::log('FINISH EXPORT - File does not exist');
            wp_send_json_error('Export file not found.');
        }
        
        // Close the JSON array and object
        $close_result = file_put_contents($file_path, "\n]}", FILE_APPEND);
        if ($close_result === false) {
            WC_PIE_Logger::log('FINISH EXPORT - Failed to close JSON');
            wp_send_json_error('Failed to finalize export file.');
        }
        
        // Create ZIP file with images
        $zip_result = $this->create_export_zip($file_path, $filename);
        
        if (is_wp_error($zip_result)) {
            WC_PIE_Logger::log('FINISH EXPORT - ZIP creation failed', array(
                'error' => $zip_result->get_error_message()
            ));
            wp_send_json_error('Failed to create ZIP file: ' . $zip_result->get_error_message());
        }
        
        // Clean up transients and JSON file
        delete_transient('wc_pie_export_ids_' . $filename);
        delete_transient('wc_pie_export_filters_' . $filename);
        delete_transient('wc_pie_current_export_' . get_current_user_id());
        
        // Remove the original JSON file
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        WC_PIE_Logger::log('FINISH EXPORT - Success', array(
            'zip_file' => $zip_result['filename'],
            'zip_url' => $zip_result['url'],
            'images_count' => $zip_result['images_count']
        ));
        
        wp_send_json_success(array(
            'download_url' => $zip_result['url'],
            'filename' => $zip_result['filename'],
            'message' => 'Export completed successfully! ZIP contains ' . $zip_result['images_count'] . ' images.',
            'file_size' => $zip_result['file_size'],
            'images_count' => $zip_result['images_count']
        ));
    }

    /**
     * Create a ZIP file containing the export JSON and downloaded images
     * 
     * @param string $json_file_path Path to the JSON export file
     * @param string $original_filename Original filename
     * @return array|WP_Error Success data or error
     */
    private function create_export_zip($json_file_path, $original_filename) {
        // Check if ZipArchive class exists
        if (!class_exists('ZipArchive')) {
            return new WP_Error('zip_not_supported', 'ZipArchive class not available on this server.');
        }
        
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/wc-product-import-export';
        
        // Create temporary directory for images
        $temp_images_dir = $export_dir . '/temp_images_' . uniqid();
        if (!wp_mkdir_p($temp_images_dir)) {
            return new WP_Error('temp_dir_failed', 'Could not create temporary images directory.');
        }
        
        // Read and parse JSON file
        $json_content = file_get_contents($json_file_path);
        if (!$json_content) {
            return new WP_Error('json_read_failed', 'Could not read JSON export file.');
        }
        
        $export_data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_parse_failed', 'Could not parse JSON export file: ' . json_last_error_msg());
        }
        
        // Extract image URLs and download images
        $images_count = 0;
        $downloaded_images = array();
        
        if (isset($export_data['products']) && is_array($export_data['products'])) {
            WC_PIE_Logger::log('CREATE EXPORT ZIP - Processing products', array(
                'products_count' => count($export_data['products'])
            ));
            
            foreach ($export_data['products'] as $product_index => &$product) {
                // Process main product image
                if (isset($product['image']) && is_array($product['image'])) {
                    $image_result = $this->download_and_store_image($product['image'], $temp_images_dir, $downloaded_images);
                    if ($image_result) {
                        $product['image']['local_path'] = $image_result['local_path'];
                        $images_count++;
                    }
                }
                
                // Process gallery images
                if (isset($product['gallery_images']) && is_array($product['gallery_images'])) {
                    foreach ($product['gallery_images'] as $gallery_index => &$gallery_image) {
                        $image_result = $this->download_and_store_image($gallery_image, $temp_images_dir, $downloaded_images);
                        if ($image_result) {
                            $gallery_image['local_path'] = $image_result['local_path'];
                            $images_count++;
                        }
                    }
                }
                
                // Process variation images
                if (isset($product['variations']) && is_array($product['variations'])) {
                    foreach ($product['variations'] as $variation_index => &$variation) {
                        if (isset($variation['image']) && is_array($variation['image'])) {
                            $image_result = $this->download_and_store_image($variation['image'], $temp_images_dir, $downloaded_images);
                            if ($image_result) {
                                $variation['image']['local_path'] = $image_result['local_path'];
                                $images_count++;
                            }
                        }
                    }
                }
            }
        }
        
        // Update JSON with local paths and save
        $updated_json = json_encode($export_data, JSON_PRETTY_PRINT);
        file_put_contents($json_file_path, $updated_json);
        
        // Create ZIP file
        $zip_filename = str_replace('.json', '.zip', $original_filename);
        $zip_path = $export_dir . '/' . $zip_filename;
        
        $zip = new ZipArchive();
        $zip_result = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        if ($zip_result !== TRUE) {
            $this->cleanup_temp_directory($temp_images_dir);
            return new WP_Error('zip_create_failed', 'Could not create ZIP file. Error code: ' . $zip_result);
        }
        
        // Add JSON file to ZIP
        $zip->addFile($json_file_path, 'products.json');
        
        // Add images to ZIP
        if ($images_count > 0) {
            $zip->addEmptyDir('images');
            foreach (glob($temp_images_dir . '/*') as $image_file) {
                if (is_file($image_file)) {
                    $zip->addFile($image_file, 'images/' . basename($image_file));
                }
            }
        }
        
        // Add README file
        $readme_content = $this->generate_zip_readme($images_count);
        $zip->addFromString('README.txt', $readme_content);
        
        $zip->close();
        
        // Cleanup temporary directory
        $this->cleanup_temp_directory($temp_images_dir);
        
        // Return ZIP file info
        $zip_url = $upload_dir['baseurl'] . '/wc-product-import-export/' . $zip_filename;
        $zip_size = file_exists($zip_path) ? filesize($zip_path) : 0;
        
        WC_PIE_Logger::log('CREATE EXPORT ZIP - Success', array(
            'zip_filename' => $zip_filename,
            'zip_size' => $zip_size,
            'images_count' => $images_count
        ));
        
        return array(
            'filename' => $zip_filename,
            'url' => $zip_url,
            'file_size' => $zip_size,
            'images_count' => $images_count
        );
    }
    
    /**
     * Download and store an image for ZIP export
     * 
     * @param array $image_data Image data from export
     * @param string $temp_dir Temporary directory path
     * @param array &$downloaded_images Reference to downloaded images array
     * @return array|null Image info or null on failure
     */
    private function download_and_store_image($image_data, $temp_dir, &$downloaded_images) {
        if (empty($image_data['url'])) {
            return null;
        }
        
        $image_url = $image_data['url'];
        $image_hash = !empty($image_data['hash']) ? $image_data['hash'] : md5($image_url);
        
        // Check if already downloaded (deduplication)
        if (isset($downloaded_images[$image_hash])) {
            WC_PIE_Logger::log('CREATE EXPORT ZIP - Image already downloaded', array(
                'url' => $image_url,
                'hash' => $image_hash
            ));
            return $downloaded_images[$image_hash];
        }
        
        // Determine file extension
        $extension = pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = 'jpg'; // Default extension
        }
        
        // Create unique filename
        $filename = $image_hash . '.' . $extension;
        $local_path = $temp_dir . '/' . $filename;
        
        // Download image
        $image_content = wp_remote_get($image_url);
        if (is_wp_error($image_content)) {
            WC_PIE_Logger::log('CREATE EXPORT ZIP - Image download failed', array(
                'url' => $image_url,
                'error' => $image_content->get_error_message()
            ));
            return null;
        }
        
        $image_body = wp_remote_retrieve_body($image_content);
        if (empty($image_body)) {
            WC_PIE_Logger::log('CREATE EXPORT ZIP - Empty image response', array('url' => $image_url));
            return null;
        }
        
        // Save image to temporary directory
        $save_result = file_put_contents($local_path, $image_body);
        if ($save_result === false) {
            WC_PIE_Logger::log('CREATE EXPORT ZIP - Failed to save image', array(
                'url' => $image_url,
                'local_path' => $local_path
            ));
            return null;
        }
        
        $image_info = array(
            'local_path' => 'images/' . $filename,
            'hash' => $image_hash,
            'original_url' => $image_url,
            'file_size' => $save_result
        );
        
        // Store in downloaded images array
        $downloaded_images[$image_hash] = $image_info;
        
        WC_PIE_Logger::log('CREATE EXPORT ZIP - Image downloaded successfully', array(
            'url' => $image_url,
            'local_path' => $local_path,
            'file_size' => $save_result
        ));
        
        return $image_info;
    }
    
    /**
     * Generate README content for the ZIP file
     * 
     * @param int $images_count Number of images included
     * @return string README content
     */
    private function generate_zip_readme($images_count) {
        $readme = "WooCommerce Product Export\n";
        $readme .= "=========================\n\n";
        $readme .= "Export Date: " . current_time('Y-m-d H:i:s') . "\n";
        $readme .= "Site URL: " . get_site_url() . "\n";
        $readme .= "Images Included: " . $images_count . "\n\n";
        $readme .= "Contents:\n";
        $readme .= "- products.json: Product data in JSON format\n";
        if ($images_count > 0) {
            $readme .= "- images/: Product images (" . $images_count . " files)\n";
        }
        $readme .= "\nUsage:\n";
        $readme .= "1. Use the WooCommerce Product Import/Export plugin to import products.json\n";
        $readme .= "2. Images will be automatically downloaded and assigned to products\n";
        $readme .= "3. Image deduplication is handled automatically using file hashes\n\n";
        $readme .= "Note: Images in the export maintain their original URLs in the JSON file,\n";
        $readme .= "but local copies are included for reference and backup purposes.\n";
        
        return $readme;
    }
    
    /**
     * Extract ZIP file for import
     * 
     * @param array $file Uploaded file data
     * @param string $target_dir Target directory
     * @return array|WP_Error Success data or error
     */
    private function extract_import_zip($file, $target_dir) {
        // Check if ZipArchive class exists
        if (!class_exists('ZipArchive')) {
            return new WP_Error('zip_not_supported', 'ZipArchive class not available on this server.');
        }
        
        // Create temporary ZIP file
        $zip_filename = 'import-' . uniqid() . '.zip';
        $zip_path = $target_dir . '/' . $zip_filename;
        
        if (!move_uploaded_file($file['tmp_name'], $zip_path)) {
            return new WP_Error('zip_move_failed', 'Failed to save uploaded ZIP file.');
        }
        
        // Create extraction directory
        $extract_dir = $target_dir . '/extract_' . uniqid();
        if (!wp_mkdir_p($extract_dir)) {
            unlink($zip_path);
            return new WP_Error('extract_dir_failed', 'Could not create extraction directory.');
        }
        
        // Open and extract ZIP
        $zip = new ZipArchive();
        $zip_result = $zip->open($zip_path);
        
        if ($zip_result !== TRUE) {
            unlink($zip_path);
            rmdir($extract_dir);
            return new WP_Error('zip_open_failed', 'Could not open ZIP file. Error code: ' . $zip_result);
        }
        
        // Extract all files
        if (!$zip->extractTo($extract_dir)) {
            $zip->close();
            unlink($zip_path);
            $this->cleanup_temp_directory($extract_dir);
            return new WP_Error('zip_extract_failed', 'Could not extract ZIP file contents.');
        }
        
        $zip->close();
        unlink($zip_path); // Remove temporary ZIP file
        
        // Find the JSON file (should be products.json or similar)
        $json_path = null;
        $possible_json_files = array('products.json', 'export.json', 'data.json');
        
        foreach ($possible_json_files as $json_file) {
            $test_path = $extract_dir . '/' . $json_file;
            if (file_exists($test_path)) {
                $json_path = $test_path;
                break;
            }
        }
        
        // If no standard JSON file found, look for any JSON file
        if (!$json_path) {
            $json_files = glob($extract_dir . '/*.json');
            if (!empty($json_files)) {
                $json_path = $json_files[0];
            }
        }
        
        if (!$json_path) {
            $this->cleanup_temp_directory($extract_dir);
            return new WP_Error('no_json_found', 'No JSON file found in the ZIP archive.');
        }
        
        // Check for images directory
        $images_dir = null;
        $images_path = $extract_dir . '/images';
        if (is_dir($images_path)) {
            $images_dir = $images_path;
        }
        
        WC_PIE_Logger::log('EXTRACT IMPORT ZIP - Success', array(
            'extract_dir' => $extract_dir,
            'json_path' => $json_path,
            'images_dir' => $images_dir,
            'images_count' => $images_dir ? count(glob($images_dir . '/*')) : 0
        ));
        
        return array(
            'json_path' => $json_path,
            'images_dir' => $images_dir,
            'extract_dir' => $extract_dir
        );
    }
    
    /**
     * Clean up temporary directory
     * 
     * @param string $temp_dir Temporary directory path
     */
    private function cleanup_temp_directory($temp_dir) {
        if (!is_dir($temp_dir)) {
            return;
        }
        
        $files = glob($temp_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($temp_dir);
    }

    public function init_import() {
        check_ajax_referer('product_ie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!isset($_FILES['import_file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['import_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload error');
        }
        
        // Move to temp location
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/wc-product-import-export';
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $is_zip_file = ($file_extension === 'zip');
        
        if ($is_zip_file) {
            // Handle ZIP file
            $zip_result = $this->extract_import_zip($file, $target_dir);
            if (is_wp_error($zip_result)) {
                wp_send_json_error('ZIP extraction failed: ' . $zip_result->get_error_message());
            }
            $target_path = $zip_result['json_path'];
            $images_dir = $zip_result['images_dir'];
        } else {
            // Handle regular JSON file
            $filename = 'import-' . uniqid() . '.json';
            $target_path = $target_dir . '/' . $filename;
            $images_dir = null;
            
            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                wp_send_json_error('Failed to save uploaded file');
            }
        }
        
        // Read and validate the JSON structure
        $json_content = file_get_contents($target_path);
        $data = json_decode($json_content, true);
        
        WC_PIE_Logger::log('INIT IMPORT - File validation', array(
            'file_type' => $is_zip_file ? 'ZIP' : 'JSON',
            'file_size' => filesize($target_path),
            'json_valid' => !is_null($data),
            'has_products' => isset($data['products']),
            'has_images_dir' => !empty($images_dir)
        ));
        
        if (!$data) {
            unlink($target_path);
            if ($images_dir && is_dir($images_dir)) {
                $this->cleanup_temp_directory($images_dir);
            }
            wp_send_json_error('Invalid JSON file - file appears to be corrupted');
        }
        
        // Validate export format - should have version, export_date, and products
        if (!isset($data['products'])) {
            // Try to detect if it's an old format or different structure
            if (is_array($data) && !empty($data)) {
                // Assume it's a simple array of products
                $data = array('products' => $data, 'version' => 'unknown');
            } else {
                unlink($target_path);
                if ($images_dir && is_dir($images_dir)) {
                    $this->cleanup_temp_directory($images_dir);
                }
                wp_send_json_error('Invalid export file format - missing products array');
            }
        }
        
        $total_products = count($data['products']);
        
        if ($total_products === 0) {
            unlink($target_path);
            wp_send_json_error('No products found in the export file');
        }
        
        // Log export metadata
        WC_PIE_Logger::log('INIT IMPORT - Export metadata', array(
            'version' => $data['version'] ?? 'unknown',
            'export_date' => $data['export_date'] ?? 'unknown',
            'site_url' => $data['site_url'] ?? 'unknown',
            'total_products' => $total_products
        ));
        
        // We don't want to keep the full JSON in memory or transient.
        // We will re-read the file in batches.
        // Store images directory information for ZIP imports
        $session_data = array(
            'total' => $total_products,
            'filename' => basename($target_path),
            'file_path' => $target_path, // Store full path for accurate file access
            'batch_size' => 5,
            'is_zip_import' => $is_zip_file
        );
        
        if ($is_zip_file && !empty($images_dir)) {
            $session_data['images_dir'] = $images_dir;
            $session_data['extract_dir'] = $zip_result['extract_dir']; // Store extract directory for cleanup
        }
        
        // Store session data in transient for later use
        set_transient('wc_pie_import_session_' . get_current_user_id(), $session_data, HOUR_IN_SECONDS);
        
        wp_send_json_success($session_data);
    }

    public function process_import_batch() {
        // Increase limits for import processing
        ini_set('memory_limit', '256M');
        set_time_limit(120);
        
        WC_PIE_Logger::log('PROCESS IMPORT BATCH STARTED');
        check_ajax_referer('product_ie_nonce', 'nonce');
        
        $filename = sanitize_text_field($_POST['filename']);
        $page = intval($_POST['page']);
        $batch_size = intval($_POST['batch_size']);
        
        WC_PIE_Logger::log('PROCESS IMPORT BATCH - Parameters', array(
            'filename' => $filename,
            'page' => $page,
            'batch_size' => $batch_size
        ));
        
        // Get session data to retrieve the correct file path
        $session_data = get_transient('wc_pie_import_session_' . get_current_user_id());
        if (!$session_data || !isset($session_data['file_path'])) {
            wp_send_json_error('Import session not found or expired');
        }
        
        $file_path = $session_data['file_path'];
        
        if (!file_exists($file_path)) {
            WC_PIE_Logger::log('PROCESS IMPORT BATCH - File not found', array(
                'file_path' => $file_path,
                'session_data' => $session_data
            ));
            wp_send_json_error('Import file not found');
        }
        
        // Read file
        $json_content = file_get_contents($file_path);
        $data = json_decode($json_content, true);
        $products = $data['products'];
        
        $offset = ($page - 1) * $batch_size;
        $batch_products = array_slice($products, $offset, $batch_size);
        
        $options = array(
            'update_existing' => isset($_POST['update_existing']) && $_POST['update_existing'] == '1',
            'skip_images' => isset($_POST['skip_images']) && $_POST['skip_images'] == '1',
            'preserve_ids' => isset($_POST['preserve_ids']) && $_POST['preserve_ids'] == '1',
            'dedupe_images' => isset($_POST['dedupe_images']) && $_POST['dedupe_images'] == '1',
        );
        
        // Add images directory for ZIP imports
        if ($session_data && !empty($session_data['images_dir'])) {
            $options['use_local_images'] = true;
            $options['local_images_dir'] = $session_data['images_dir'];
        }
        
        WC_PIE_Logger::log('PROCESS IMPORT BATCH - Import options', $options);
        
        $results = array(
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => array()
        );
        
        foreach ($batch_products as $product_data) {
            try {
                $result = $this->importer->import_single_product($product_data, $options);
                if ($result['action'] === 'created') {
                    $results['imported']++;
                } elseif ($result['action'] === 'updated') {
                    $results['updated']++;
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = $product_data['name'] . ': ' . $e->getMessage();
            }
        }
        
        // Calculate progress more accurately
        $processed_count = $offset + count($batch_products);
        $total_count = count($products);
        $percentage = min(100, round(($processed_count / $total_count) * 100, 2));
        
        // If this is the last batch, clean up
        if ($processed_count >= $total_count) {
            unlink($file_path);
            
            // Clean up ZIP extraction directory if it exists
            if ($session_data && !empty($session_data['extract_dir'])) {
                $extract_dir = $session_data['extract_dir'];
                if (is_dir($extract_dir)) {
                    $this->cleanup_temp_directory($extract_dir);
                }
            }
            
            // Clean up session data
            delete_transient('wc_pie_import_session_' . get_current_user_id());
        }
        
        wp_send_json_success(array(
            'results' => $results,
            'percentage' => $percentage,
            'processed' => $processed_count,
            'total' => $total_count,
            'is_complete' => $processed_count >= $total_count
        ));
    }
    
    public function view_logs() {
        check_ajax_referer('product_ie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $log_content = WC_PIE_Logger::get_log_content();
        wp_send_json_success(array(
            'logs' => $log_content,
            'log_file' => WC_PIE_Logger::get_log_file_path()
        ));
    }
    
    public function clear_logs() {
        check_ajax_referer('product_ie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        WC_PIE_Logger::clear();
        wp_send_json_success('Logs cleared successfully');
    }
    
    /**
     * Analyze import file (ZIP or JSON)
     */
    public function analyze_import() {
        check_ajax_referer('product_ie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (empty($_FILES['import_file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $uploaded_file = $_FILES['import_file'];
        
        // Validate file
        if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload error: ' . $uploaded_file['error']);
        }
        
        $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, array('zip', 'json'))) {
            wp_send_json_error('Invalid file type. Only ZIP and JSON files are allowed.');
        }
        
        try {
            $analysis_data = array(
                'file_name' => $uploaded_file['name'],
                'file_size' => $uploaded_file['size'],
                'file_type' => $file_extension,
                'upload_time' => current_time('mysql')
            );
            
            if ($file_extension === 'zip') {
                // Analyze ZIP file
                $zip_analysis = $this->analyze_zip_file($uploaded_file['tmp_name']);
                $analysis_data = array_merge($analysis_data, $zip_analysis);
            } else {
                // Analyze JSON file
                $json_analysis = $this->analyze_json_file($uploaded_file['tmp_name']);
                $analysis_data = array_merge($analysis_data, $json_analysis);
            }
            
            wp_send_json_success($analysis_data);
            
        } catch (Exception $e) {
            wp_send_json_error('Analysis failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Process ZIP import
     */
    public function process_zip_import() {
        check_ajax_referer('product_ie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (empty($_FILES['import_file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $uploaded_file = $_FILES['import_file'];
        
        // Validate file
        if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload error: ' . $uploaded_file['error']);
        }
        
        $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, array('zip', 'json'))) {
            wp_send_json_error('Invalid file type. Only ZIP and JSON files are allowed.');
        }
        
        // Get import options from POST data
        $options = array(
            'update_existing' => !empty($_POST['update_existing']),
            'skip_images' => !empty($_POST['skip_images']),
            'preserve_ids' => !empty($_POST['preserve_ids']),
            'dedupe_images' => !empty($_POST['dedupe_images']),
            'optimize_images' => !empty($_POST['optimize_images']),
            'import_mode' => sanitize_text_field($_POST['import_mode'] ?? 'standard')
        );
        
        try {
            if ($file_extension === 'zip') {
                $result = $this->importer->process_zip_import($uploaded_file['tmp_name'], $options);
            } else {
                // Process JSON file
                $json_content = file_get_contents($uploaded_file['tmp_name']);
                $import_data = json_decode($json_content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON format: ' . json_last_error_msg());
                }
                
                $result = $this->importer->import_products($import_data, $options);
            }
            
            wp_send_json_success(array(
                'message' => 'Import completed successfully!',
                'result' => $result
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Import failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Analyze ZIP file contents
     * 
     * @param string $zip_file_path
     * @return array Analysis data
     */
    private function analyze_zip_file($zip_file_path) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive class not available');
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($zip_file_path);
        
        if ($result !== true) {
            throw new Exception('Failed to open ZIP file');
        }
        
        $analysis = array(
            'contains_json' => false,
            'contains_images' => false,
            'json_files' => array(),
            'image_files' => array(),
            'total_files' => $zip->numFiles,
            'estimated_products' => 0
        );
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $file_info = $zip->statIndex($i);
            $filename = $file_info['name'];
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if ($extension === 'json') {
                $analysis['contains_json'] = true;
                $analysis['json_files'][] = $filename;
                
                // Try to estimate product count from JSON
                $json_content = $zip->getFromIndex($i);
                if ($json_content) {
                    $json_data = json_decode($json_content, true);
                    if (isset($json_data['products']) && is_array($json_data['products'])) {
                        $analysis['estimated_products'] = count($json_data['products']);
                    }
                }
            } elseif (in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
                $analysis['contains_images'] = true;
                $analysis['image_files'][] = $filename;
            }
        }
        
        $zip->close();
        
        return $analysis;
    }
    
    /**
     * Analyze JSON file contents
     * 
     * @param string $json_file_path
     * @return array Analysis data
     */
    private function analyze_json_file($json_file_path) {
        $json_content = file_get_contents($json_file_path);
        $json_data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format: ' . json_last_error_msg());
        }
        
        $analysis = array(
            'contains_json' => true,
            'contains_images' => false,
            'estimated_products' => 0,
            'export_info' => array()
        );
        
        if (isset($json_data['products']) && is_array($json_data['products'])) {
            $analysis['estimated_products'] = count($json_data['products']);
        }
        
        if (isset($json_data['version'])) {
            $analysis['export_info']['version'] = $json_data['version'];
        }
        
        if (isset($json_data['export_date'])) {
            $analysis['export_info']['export_date'] = $json_data['export_date'];
        }
        
        if (isset($json_data['site_url'])) {
            $analysis['export_info']['site_url'] = $json_data['site_url'];
        }
        
        return $analysis;
    }
}