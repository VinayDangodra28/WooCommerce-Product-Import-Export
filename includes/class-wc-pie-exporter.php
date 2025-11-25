<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_PIE_Exporter {

    public function build_export_query($filters) {
        global $wpdb;
        WC_PIE_Logger::log('BUILD EXPORT QUERY - Start', $filters);
        
        // Clean and normalize filter arrays - handle empty strings and ensure proper arrays
        $product_status = array();
        if (isset($filters['product_status']) && !empty($filters['product_status'])) {
            if (is_array($filters['product_status'])) {
                $product_status = array_filter($filters['product_status'], function($val) {
                    return !empty($val);
                });
            } elseif (!empty($filters['product_status'])) {
                $product_status = array($filters['product_status']);
            }
        }
        // Default to published if no status specified
        if (empty($product_status)) {
            $product_status = array('publish');
        }
        WC_PIE_Logger::log('BUILD EXPORT QUERY - Product status processed', $product_status);
        
        $product_types = array();
        if (isset($filters['product_types']) && !empty($filters['product_types'])) {
            if (is_array($filters['product_types'])) {
                $product_types = array_filter($filters['product_types'], function($val) {
                    return !empty($val);
                });
            } elseif (!empty($filters['product_types'])) {
                $product_types = array($filters['product_types']);
            }
        }
        
        $stock_status = array();
        if (isset($filters['stock_status']) && !empty($filters['stock_status'])) {
            if (is_array($filters['stock_status'])) {
                $stock_status = array_filter($filters['stock_status'], function($val) {
                    return !empty($val);
                });
            } elseif (!empty($filters['stock_status'])) {
                $stock_status = array($filters['stock_status']);
            }
        }
        
        WC_PIE_Logger::log('BUILD EXPORT QUERY - Product types processed', $product_types);
        WC_PIE_Logger::log('BUILD EXPORT QUERY - Stock status processed', $stock_status);
        
        // Test what product types actually exist in the database
        $existing_types = $wpdb->get_results("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_type' AND meta_value != ''", ARRAY_A);
        WC_PIE_Logger::log('BUILD EXPORT QUERY - Existing product types in DB', $existing_types);
        
        // Also check how many products have no _product_type meta
        $no_type_count = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_product_type' WHERE p.post_type = 'product' AND p.post_status = 'publish' AND (pm.meta_value IS NULL OR pm.meta_value = '')");
        WC_PIE_Logger::log('BUILD EXPORT QUERY - Products with no type meta', $no_type_count);
        
        $product_categories = isset($filters['product_categories']) ? (array)$filters['product_categories'] : array();
        $product_tags = isset($filters['product_tags']) ? (array)$filters['product_tags'] : array();
        $shipping_classes = isset($filters['shipping_classes']) ? (array)$filters['shipping_classes'] : array();
        $date_from = isset($filters['date_from']) ? sanitize_text_field($filters['date_from']) : '';
        $date_to = isset($filters['date_to']) ? sanitize_text_field($filters['date_to']) : '';
        
        $args = array(
            'post_type' => 'product',
            'post_status' => $product_status,
            'posts_per_page' => -1,
            'fields' => 'ids', // Only get IDs for the initial query
            'meta_query' => array('relation' => 'AND'),
            'tax_query' => array('relation' => 'AND')
        );
        
        // Filter by product types - only add filter if specific types selected
        if (!empty($product_types)) {
            // Check if all common types are selected (means user wants all)
            $all_types = array('simple', 'variable', 'grouped', 'external');
            $is_all_types = (count($product_types) >= 4 && 
                           in_array('simple', $product_types) && 
                           in_array('variable', $product_types) && 
                           in_array('grouped', $product_types) && 
                           in_array('external', $product_types));
            
            // Only apply filter if not selecting all types
            if (!$is_all_types) {
                // WooCommerce stores product types differently:
                // - Simple products often have no _product_type meta or it's empty
                // - Other types have explicit meta values
                if (in_array('simple', $product_types)) {
                    // For simple products, we need to include products with no _product_type or _product_type = 'simple'
                    $args['meta_query'][] = array(
                        'relation' => 'OR',
                        array(
                            'key' => '_product_type',
                            'value' => $product_types,
                            'compare' => 'IN'
                        ),
                        array(
                            'key' => '_product_type',
                            'compare' => 'NOT EXISTS'
                        ),
                        array(
                            'key' => '_product_type',
                            'value' => '',
                            'compare' => '='
                        )
                    );
                } else {
                    // For non-simple products, use standard meta query
                    $args['meta_query'][] = array(
                        'key' => '_product_type',
                        'value' => $product_types,
                        'compare' => 'IN'
                    );
                }
            }
        }
        
        // Filter by stock status - only add filter if specific statuses selected
        if (!empty($stock_status)) {
            // Check if all common statuses are selected (means user wants all)
            $all_statuses = array('instock', 'outofstock', 'onbackorder');
            $is_all_statuses = (count($stock_status) >= 3 && 
                              in_array('instock', $stock_status) && 
                              in_array('outofstock', $stock_status) && 
                              in_array('onbackorder', $stock_status));
            
            // Only apply filter if not selecting all statuses
            if (!$is_all_statuses) {
                $args['meta_query'][] = array(
                    'key' => '_stock_status',
                    'value' => $stock_status,
                    'compare' => 'IN'
                );
            }
        }
        
        // Filter by categories
        if (!empty($product_categories)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $product_categories,
                'operator' => 'IN'
            );
        }
        
        // Filter by tags
        if (!empty($product_tags)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_tag',
                'field' => 'term_id',
                'terms' => $product_tags,
                'operator' => 'IN'
            );
        }
        
        // Filter by shipping classes
        if (!empty($shipping_classes)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_shipping_class',
                'field' => 'term_id',
                'terms' => $shipping_classes,
                'operator' => 'IN'
            );
        }
        
        // Filter by date range
        if (!empty($date_from) || !empty($date_to)) {
            $date_query = array();
            if (!empty($date_from)) {
                $date_query['after'] = $date_from;
            }
            if (!empty($date_to)) {
                $date_query['before'] = $date_to;
            }
            $args['date_query'] = array($date_query);
        }
        
        // Clean up meta_query if it's empty (only has relation)
        if (count($args['meta_query']) <= 1) {
            unset($args['meta_query']);
        }
        
        // Clean up tax_query if it's empty (only has relation)  
        if (count($args['tax_query']) <= 1) {
            unset($args['tax_query']);
        }
        
        WC_PIE_Logger::log('BUILD EXPORT QUERY - Final args', $args);
        return $args;
    }

    /**
     * Export multiple products based on filters
     * 
     * @param array $filters
     * @param array $options
     * @return array
     */
    public function export_products($filters = array(), $options = array()) {
        $query_args = $this->build_export_query($filters);
        $exported_products = array();
        
        // Default options
        $default_options = array(
            'include_images' => true,
            'include_variations' => true,
            'include_attributes' => true,
            'include_meta' => true
        );
        
        $options = array_merge($default_options, $options);
        
        // Run the query to get product IDs
        $query_args['fields'] = 'ids';
        $query = new WP_Query($query_args);
        $product_ids = $query->posts;
        
        WC_PIE_Logger::log('EXPORT PRODUCTS - Start', array(
            'filters' => $filters,
            'options' => $options,
            'found_products' => count($product_ids)
        ));
        
        foreach ($product_ids as $product_id) {
            try {
                $product_data = $this->export_single_product($product_id, $options);
                if ($product_data) {
                    $exported_products[] = $product_data;
                }
            } catch (Exception $e) {
                WC_PIE_Logger::log('EXPORT PRODUCTS - Error exporting product', array(
                    'product_id' => $product_id,
                    'error' => $e->getMessage()
                ));
            }
        }
        
        WC_PIE_Logger::log('EXPORT PRODUCTS - Completed', array(
            'exported_count' => count($exported_products)
        ));
        
        return $exported_products;
    }

    public function export_single_product($product_id, $options = array()) {
        try {
            WC_PIE_Logger::log('EXPORT SINGLE PRODUCT - Start', array('id' => $product_id, 'options' => $options));
            
            $product = wc_get_product($product_id);
            if (!$product) {
                WC_PIE_Logger::log('EXPORT SINGLE PRODUCT - Product not found', array('id' => $product_id));
                return null;
            }
            
            WC_PIE_Logger::log('EXPORT SINGLE PRODUCT - Product loaded', array(
                'id' => $product_id,
                'type' => $product->get_type(),
                'name' => $product->get_name()
            ));

        // Set default options
        $default_options = array(
            'include_images' => true,
            'include_variations' => true,
            'include_meta' => true,
            'include_attributes' => true
        );
        $options = array_merge($default_options, $options);
        
        $data = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'type' => $product->get_type(),
            'status' => $product->get_status(),
            'featured' => $product->is_featured(),
            'catalog_visibility' => $product->get_catalog_visibility(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'date_on_sale_from' => $product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->format('Y-m-d H:i:s') : null,
            'date_on_sale_to' => $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->format('Y-m-d H:i:s') : null,
            'total_sales' => $product->get_total_sales(),
            'tax_status' => $product->get_tax_status(),
            'tax_class' => $product->get_tax_class(),
            'manage_stock' => $product->get_manage_stock(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'backorders' => $product->get_backorders(),
            'low_stock_amount' => $product->get_low_stock_amount(),
            'sold_individually' => $product->get_sold_individually(),
            'weight' => $product->get_weight(),
            'length' => $product->get_length(),
            'width' => $product->get_width(),
            'height' => $product->get_height(),
            'upsell_ids' => $product->get_upsell_ids(),
            'cross_sell_ids' => $product->get_cross_sell_ids(),
            'parent_id' => $product->get_parent_id(),
            'reviews_allowed' => $product->get_reviews_allowed(),
            'purchase_note' => $product->get_purchase_note(),
            'menu_order' => $product->get_menu_order(),
            'virtual' => $product->is_virtual(),
            'downloadable' => $product->is_downloadable(),
            'category_ids' => $product->get_category_ids(),
            'tag_ids' => $product->get_tag_ids(),
            'shipping_class_id' => $product->get_shipping_class_id(),
            'downloads' => $product->get_downloads(),
            'download_expiry' => $product->get_download_expiry(),
            'download_limit' => $product->get_download_limit(),
            'variations' => array()
        );
        
        // Conditionally add images
        if ($options['include_images']) {
            // Export main product image with metadata
            $main_image_id = $product->get_image_id();
            $data['image'] = $this->get_image_export_data($main_image_id);
            
            // Export gallery images with metadata
            $gallery_ids = $product->get_gallery_image_ids();
            $data['gallery_images'] = $this->get_gallery_export_data($gallery_ids);
            
            // Keep legacy fields for backward compatibility
            $data['image_id'] = $main_image_id;
            $data['gallery_image_ids'] = $gallery_ids;
        }
        
        // Conditionally add attributes
        if ($options['include_attributes']) {
            $data['attributes'] = $this->export_product_attributes($product);
            $data['default_attributes'] = $product->get_default_attributes();
        }
        
        // Conditionally add meta data
        if ($options['include_meta']) {
            $data['meta_data'] = $this->export_meta_data($product->get_id());
            $data['custom_fields'] = get_post_custom($product->get_id());
        }
        
        // Export variations for variable products
        if ($product->is_type('variable') && $options['include_variations']) {
            $variations = $product->get_children();
            foreach ($variations as $variation_id) {
                // Pass same options to variations
                $variation_data = $this->export_single_product($variation_id, $options);
                if ($variation_data) {
                    $data['variations'][] = $variation_data;
                }
            }
        }
        
        WC_PIE_Logger::log('EXPORT SINGLE PRODUCT - Completed', array(
            'id' => $product_id,
            'data_size' => strlen(json_encode($data))
        ));
        
        return $data;
        
        } catch (Exception $e) {
            WC_PIE_Logger::log('EXPORT SINGLE PRODUCT - Exception', array(
                'id' => $product_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return null;
        } catch (Throwable $t) {
            WC_PIE_Logger::log('EXPORT SINGLE PRODUCT - Fatal error', array(
                'id' => $product_id,
                'error' => $t->getMessage(),
                'trace' => $t->getTraceAsString()
            ));
            return null;
        }
    }
    
    private function export_product_attributes($product) {
        $attributes = array();
        foreach ($product->get_attributes() as $attribute_name => $attribute) {
            // Handle different attribute types - objects for global attributes, strings for variation attributes
            if (is_object($attribute) && method_exists($attribute, 'get_id')) {
                // Global/taxonomy attribute object
                $attributes[] = array(
                    'id' => $attribute->get_id(),
                    'name' => $attribute->get_name(),
                    'options' => $attribute->get_options(),
                    'position' => $attribute->get_position(),
                    'visible' => $attribute->get_visible(),
                    'variation' => $attribute->get_variation(),
                    'taxonomy' => $attribute->is_taxonomy() ? $attribute->get_taxonomy() : false,
                );
            } else {
                // Variation attribute (string value) or local attribute
                $attributes[] = array(
                    'id' => 0,
                    'name' => $attribute_name,
                    'options' => is_string($attribute) ? array($attribute) : (is_array($attribute) ? $attribute : array()),
                    'position' => 0,
                    'visible' => true,
                    'variation' => true,
                    'taxonomy' => false,
                );
            }
        }
        return $attributes;
    }
    
    private function export_meta_data($product_id) {
        global $wpdb;
        $meta_data = array();
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
            $product_id
        ));
        
        foreach ($results as $meta) {
            $meta_data[] = array(
                'key' => $meta->meta_key,
                'value' => maybe_unserialize($meta->meta_value)
            );
        }
        
        return $meta_data;
    }

    /**
     * Get image data with URL, metadata and hash for deduplication
     * 
     * @param int $image_id
     * @return array|null
     */
    private function get_image_export_data($image_id) {
        if (empty($image_id)) {
            return null;
        }

        $image_url = wp_get_attachment_url($image_id);
        if (!$image_url) {
            return null;
        }

        $image_path = get_attached_file($image_id);
        $image_hash = null;
        
        // Generate hash for deduplication
        if ($image_path && file_exists($image_path)) {
            $image_hash = md5_file($image_path);
        } else {
            // Fallback: use URL-based hash if file is not accessible
            $image_hash = md5($image_url);
        }

        $attachment = get_post($image_id);
        $image_meta = wp_get_attachment_metadata($image_id);

        return array(
            'id' => $image_id,
            'url' => $image_url,
            'filename' => $image_path ? basename($image_path) : basename(parse_url($image_url, PHP_URL_PATH)),
            'hash' => $image_hash,
            'title' => $attachment ? $attachment->post_title : '',
            'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
            'caption' => $attachment ? $attachment->post_excerpt : '',
            'description' => $attachment ? $attachment->post_content : '',
            'metadata' => $image_meta,
            'mime_type' => get_post_mime_type($image_id)
        );
    }

    /**
     * Get gallery images data with URLs, metadata and hashes
     * 
     * @param array $gallery_ids
     * @return array
     */
    private function get_gallery_export_data($gallery_ids) {
        if (empty($gallery_ids) || !is_array($gallery_ids)) {
            return array();
        }

        $gallery_data = array();
        foreach ($gallery_ids as $image_id) {
            $image_data = $this->get_image_export_data($image_id);
            if ($image_data) {
                $gallery_data[] = $image_data;
            }
        }

        return $gallery_data;
    }
}