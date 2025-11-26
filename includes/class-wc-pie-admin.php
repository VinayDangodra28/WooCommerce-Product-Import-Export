<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_PIE_Admin {

    private $plugin_url;

    public function __construct($plugin_url) {
        $this->plugin_url = $plugin_url;
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Product Import Export',
            'Product I/E',
            'manage_woocommerce',
            'product-import-export',
            array($this, 'admin_page'),
            'dashicons-database-export',
            56
        );
        
        add_submenu_page(
            'product-import-export',
            'Export Products',
            'Export',
            'manage_woocommerce',
            'product-export',
            array($this, 'export_page')
        );
        
        add_submenu_page(
            'product-import-export',
            'Import Products',
            'Import',
            'manage_woocommerce',
            'product-import',
            array($this, 'import_page')
        );
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'product-import-export') !== false || strpos($hook, 'product-export') !== false || strpos($hook, 'product-import') !== false) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('product-ie-script', $this->plugin_url . 'assets/js/script.js', array('jquery'), '1.0.0', true);
            wp_enqueue_style('product-ie-style', $this->plugin_url . 'assets/css/style.css', array(), '1.0.0');
            wp_localize_script('product-ie-script', 'productIE', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('product_ie_nonce')
            ));
            
            // Enqueue Select2 if available or bundle it (assuming it might be available in WC)
            if (class_exists('WooCommerce')) {
                wp_enqueue_style('woocommerce_admin_styles');
                wp_enqueue_script('wc-admin-meta-boxes');
            }
        }
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>WooCommerce Product Import Export</h1>
            <div class="product-ie-dashboard">
                <div class="card">
                    <h2>Export Products</h2>
                    <p>Export your WooCommerce products to JSON format with all meta data.</p>
                    <a href="<?php echo admin_url('admin.php?page=product-export'); ?>" class="button button-primary">Go to Export</a>
                </div>
                <div class="card">
                    <h2>Import Products</h2>
                    <p>Import WooCommerce products from JSON format with all meta data.</p>
                    <a href="<?php echo admin_url('admin.php?page=product-import'); ?>" class="button button-primary">Go to Import</a>
                </div>
            </div>
        </div>
        <?php
    }

    public function export_page() {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));
        
        $tags = get_terms(array(
            'taxonomy' => 'product_tag',
            'hide_empty' => false,
        ));
        
        $shipping_classes = get_terms(array(
            'taxonomy' => 'product_shipping_class',
            'hide_empty' => false,
        ));
        
        $product_count = wp_count_posts('product');
        $total_products = $product_count->publish + $product_count->draft + $product_count->private;
        ?>
        <div class="wrap pie-export-wrap">
            <div class="pie-header">
                <h1><span class="dashicons dashicons-database-export"></span> Export Products</h1>
                <p class="pie-subtitle">Export your WooCommerce products with advanced filtering options</p>
            </div>
            <div class="pie-stats-grid">
                <div class="pie-stat-card">
                    <div class="pie-stat-icon"><span class="dashicons dashicons-products"></span></div>
                    <div class="pie-stat-content">
                        <span class="pie-stat-number"><?php echo $total_products; ?></span>
                        <span class="pie-stat-label">Total Products</span>
                    </div>
                </div>
                <div class="pie-stat-card">
                    <div class="pie-stat-icon pie-stat-published"><span class="dashicons dashicons-yes-alt"></span></div>
                    <div class="pie-stat-content">
                        <span class="pie-stat-number"><?php echo $product_count->publish; ?></span>
                        <span class="pie-stat-label">Published</span>
                    </div>
                </div>
                <div class="pie-stat-card">
                    <div class="pie-stat-icon pie-stat-categories"><span class="dashicons dashicons-category"></span></div>
                    <div class="pie-stat-content">
                        <span class="pie-stat-number"><?php echo count($categories); ?></span>
                        <span class="pie-stat-label">Categories</span>
                    </div>
                </div>
                <div class="pie-stat-card">
                    <div class="pie-stat-icon pie-stat-tags"><span class="dashicons dashicons-tag"></span></div>
                    <div class="pie-stat-content">
                        <span class="pie-stat-number"><?php echo count($tags); ?></span>
                        <span class="pie-stat-label">Tags</span>
                    </div>
                </div>
            </div>

            <?php if ($total_products === 0): ?>
            <div class="pie-empty-state">
                <div class="pie-empty-icon"><span class="dashicons dashicons-admin-post"></span></div>
                <h3>No Products Found</h3>
                <p>You don't have any products in your store yet. <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>">Create your first product</a> to get started with exports.</p>
            </div>
            <?php else: ?>
            
            <form id="export-form">
                <div class="pie-form-container">
                    <div class="pie-form-sidebar">
                        <div class="pie-quick-actions">
                            <h3>Quick Actions</h3>
                            <button type="button" class="button button-secondary pie-btn-select-all">Select All Types</button>
                            <button type="button" class="button button-secondary pie-btn-select-none">Deselect All</button>
                            <button type="button" class="button button-secondary pie-btn-reset">Reset Filters</button>
                        </div>
                        
                        <div class="pie-export-summary">
                            <h3>Export Preview</h3>
                            <div id="pie-live-count">
                                <span class="pie-count-number">0</span>
                                <span class="pie-count-label">products will be exported</span>
                            </div>
                            <button type="button" id="preview-btn" class="button button-secondary pie-preview-btn">
                                <span class="dashicons dashicons-visibility"></span> Preview Selection
                            </button>
                        </div>
                    </div>
                    
                    <div class="pie-form-main">
                        <div class="pie-filter-section pie-filter-basic">
                            <div class="pie-filter-header">
                                <h3><span class="dashicons dashicons-admin-settings"></span> Basic Filters</h3>
                                <span class="pie-filter-desc">Choose what types of products to export</span>
                            </div>
                            <div class="pie-filter-grid">
                                <div class="pie-filter-group">
                                    <label class="pie-filter-label"><strong>Product Status</strong></label>
                                    <div class="pie-checkbox-grid">
                                        <label class="pie-checkbox-item">
                                            <input type="checkbox" name="product_status[]" value="publish" checked>
                                            <span class="pie-checkbox-custom"></span>
                                            <span class="pie-status-badge pie-status-published">Published (<?php echo $product_count->publish; ?>)</span>
                                        </label>
                                        <label class="pie-checkbox-item">
                                            <input type="checkbox" name="product_status[]" value="draft">
                                            <span class="pie-checkbox-custom"></span>
                                            <span class="pie-status-badge pie-status-draft">Draft (<?php echo $product_count->draft; ?>)</span>
                                        </label>
                                        <label class="pie-checkbox-item">
                                            <input type="checkbox" name="product_status[]" value="private">
                                            <span class="pie-checkbox-custom"></span>
                                            <span class="pie-status-badge pie-status-private">Private (<?php echo $product_count->private; ?>)</span>
                                        </label>
                                        <label class="pie-checkbox-item">
                                            <input type="checkbox" name="product_status[]" value="trash">
                                            <span class="pie-checkbox-custom"></span>
                                            <span class="pie-status-badge pie-status-trash">Trash (<?php echo $product_count->trash; ?>)</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="pie-filter-group">
                                    <label class="pie-filter-label"><strong>Product Types</strong></label>
                                    <div class="pie-checkbox-grid">
                                        <label class="pie-checkbox-item">
                                            <input type="checkbox" name="product_types[]" value="simple" checked>
                                            <span class="pie-checkbox-custom"></span>
                                            <span class="pie-type-badge pie-type-simple">Simple Products</span>
                                        </label>
                                        <label class="pie-checkbox-item">
                                            <input type="checkbox" name="product_types[]" value="variable" checked>
                                            <span class="pie-checkbox-custom"></span>
                                            <span class="pie-type-badge pie-type-variable">Variable Products</span>
                                        </label>
                                        <label class="pie-checkbox-item">
                                            <input type="checkbox" name="product_types[]" value="grouped" checked>
                                            <span class="pie-checkbox-custom"></span>
                                            <span class="pie-type-badge pie-type-grouped">Grouped Products</span>
                                        </label>
                                        <label class="pie-checkbox-item">
                                            <input type="checkbox" name="product_types[]" value="external" checked>
                                            <span class="pie-checkbox-custom"></span>
                                            <span class="pie-type-badge pie-type-external">External Products</span>
                                        </label>
                                    </div>
                                </div>
                        </div>
                    </div>
                        </div>
                        
                        <div class="pie-filter-section pie-filter-date">
                            <div class="pie-filter-header pie-filter-toggle">
                                <div class="pie-toggle-wrapper">
                                    <input type="checkbox" id="enable-date-filter" class="pie-toggle-switch">
                                    <label for="enable-date-filter" class="pie-toggle-label">
                                        <span class="pie-toggle-slider"></span>
                                    </label>
                                    <h3><span class="dashicons dashicons-calendar-alt"></span> Filter by Date Range</h3>
                                </div>
                                <span class="pie-filter-desc">Only export products created within specific dates</span>
                            </div>
                            <div class="pie-date-controls" style="display: none;">
                                <div class="pie-date-range-group">
                                    <div class="pie-date-input-group">
                                        <label for="date_from">From Date</label>
                                        <input type="date" id="date_from" name="date_from" disabled />
                                    </div>
                                    <div class="pie-date-separator">
                                        <span class="dashicons dashicons-arrow-right-alt"></span>
                                    </div>
                                    <div class="pie-date-input-group">
                                        <label for="date_to">To Date</label>
                                        <input type="date" id="date_to" name="date_to" disabled />
                                    </div>
                                </div>
                                <div class="pie-date-presets">
                                    <button type="button" class="button button-small pie-date-preset" data-days="7">Last 7 days</button>
                                    <button type="button" class="button button-small pie-date-preset" data-days="30">Last 30 days</button>
                                    <button type="button" class="button button-small pie-date-preset" data-days="90">Last 3 months</button>
                                    <button type="button" class="button button-small pie-date-preset" data-days="365">Last year</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pie-filter-section pie-filter-taxonomy">
                            <div class="pie-filter-header pie-filter-toggle">
                                <div class="pie-toggle-wrapper">
                                    <input type="checkbox" id="enable-taxonomy-filter" class="pie-toggle-switch">
                                    <label for="enable-taxonomy-filter" class="pie-toggle-label">
                                        <span class="pie-toggle-slider"></span>
                                    </label>
                                    <h3><span class="dashicons dashicons-category"></span> Filter by Categories & Tags</h3>
                                </div>
                                <span class="pie-filter-desc">Only export products from specific categories or tags</span>
                            </div>
                            <div class="pie-taxonomy-controls" style="display: none;">
                                <div class="pie-filter-grid">
                                    <div class="pie-filter-group">
                                        <label for="product_categories" class="pie-filter-label"><strong>Categories</strong></label>
                                        <div class="pie-select-wrapper">
                                            <select name="product_categories[]" id="product_categories" multiple class="enhanced-select" disabled>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category->term_id; ?>">
                                                        <?php echo esc_html($category->name); ?> (<?php echo $category->count; ?> products)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <p class="pie-field-desc">Choose specific categories (leave empty for all)</p>
                                    </div>
                                    
                                    <div class="pie-filter-group">
                                        <label for="product_tags" class="pie-filter-label"><strong>Tags</strong></label>
                                        <div class="pie-select-wrapper">
                                            <select name="product_tags[]" id="product_tags" multiple class="enhanced-select" disabled>
                                                <?php foreach ($tags as $tag): ?>
                                                    <option value="<?php echo $tag->term_id; ?>">
                                                        <?php echo esc_html($tag->name); ?> (<?php echo $tag->count; ?> products)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <p class="pie-field-desc">Choose specific tags (leave empty for all)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pie-filter-section pie-filter-stock">
                            <div class="pie-filter-header">
                                <h3><span class="dashicons dashicons-products"></span> Stock & Shipping</h3>
                                <span class="pie-filter-desc">Filter by stock status and shipping options</span>
                            </div>
                            <div class="pie-filter-grid">
                                <div class="pie-filter-group">
                                    <label class="pie-filter-label"><strong>Stock Status</strong></label>
                                    <div class="pie-checkbox-grid">
                                        <label class="pie-checkbox-item">
                                            <input type="checkbox" name="stock_status[]" value="instock" checked>
                                            <span class="pie-checkbox-custom"></span>
                                            <span class="pie-stock-badge pie-stock-instock">In Stock</span>
                                        </label>
                                        <label class="pie-checkbox-item">
                                            <input type="checkbox" name="stock_status[]" value="outofstock" checked>
                                            <span class="pie-checkbox-custom"></span>
                                            <span class="pie-stock-badge pie-stock-outofstock">Out of Stock</span>
                                        </label>
                                        <label class="pie-checkbox-item">
                                            <input type="checkbox" name="stock_status[]" value="onbackorder" checked>
                                            <span class="pie-checkbox-custom"></span>
                                            <span class="pie-stock-badge pie-stock-backorder">On Backorder</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <?php if (!empty($shipping_classes)): ?>
                                <div class="pie-filter-group">
                                    <label for="shipping_classes" class="pie-filter-label"><strong>Shipping Classes</strong></label>
                                    <div class="pie-select-wrapper">
                                        <select name="shipping_classes[]" id="shipping_classes" multiple class="enhanced-select">
                                            <?php foreach ($shipping_classes as $class): ?>
                                                <option value="<?php echo $class->term_id; ?>">
                                                    <?php echo esc_html($class->name); ?> (<?php echo $class->count; ?> products)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <p class="pie-field-desc">Filter by shipping classes (optional)</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="pie-filter-section pie-filter-options">
                            <div class="pie-filter-header">
                                <h3><span class="dashicons dashicons-admin-generic"></span> Export Options</h3>
                                <span class="pie-filter-desc">Choose what data to include in your export</span>
                            </div>
                            <div class="pie-filter-grid">
                                <div class="pie-filter-group pie-options-group">
                                    <label class="pie-filter-label"><strong>Include Data</strong></label>
                                    <div class="pie-options-grid">
                                        <label class="pie-option-item">
                                            <input type="checkbox" name="include_images" value="1" checked>
                                            <span class="pie-option-card">
                                                <span class="pie-option-icon"><span class="dashicons dashicons-format-image"></span></span>
                                                <span class="pie-option-label">Product Images</span>
                                                <span class="pie-option-desc">Include featured and gallery images</span>
                                            </span>
                                        </label>
                                        <label class="pie-option-item">
                                            <input type="checkbox" name="include_variations" value="1" checked>
                                            <span class="pie-option-card">
                                                <span class="pie-option-icon"><span class="dashicons dashicons-networking"></span></span>
                                                <span class="pie-option-label">Product Variations</span>
                                                <span class="pie-option-desc">Include all product variations</span>
                                            </span>
                                        </label>
                                        <label class="pie-option-item">
                                            <input type="checkbox" name="include_meta" value="1" checked>
                                            <span class="pie-option-card">
                                                <span class="pie-option-icon"><span class="dashicons dashicons-admin-settings"></span></span>
                                                <span class="pie-option-label">Meta Data</span>
                                                <span class="pie-option-desc">Include custom fields and meta</span>
                                            </span>
                                        </label>
                                        <label class="pie-option-item">
                                            <input type="checkbox" name="include_attributes" value="1" checked>
                                            <span class="pie-option-card">
                                                <span class="pie-option-icon"><span class="dashicons dashicons-tag"></span></span>
                                                <span class="pie-option-label">Attributes</span>
                                                <span class="pie-option-desc">Include product attributes</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="pie-filter-group">
                                    <label for="export_format" class="pie-filter-label"><strong>Export Format</strong></label>
                                    <div class="pie-format-selector">
                                        <label class="pie-format-option">
                                            <input type="radio" name="export_format" value="json" checked>
                                            <span class="pie-format-card">
                                                <span class="pie-format-icon">{}</span>
                                                <span class="pie-format-label">Compact JSON</span>
                                                <span class="pie-format-desc">Smaller file size, faster processing</span>
                                            </span>
                                        </label>
                                        <label class="pie-format-option">
                                            <input type="radio" name="export_format" value="json_pretty">
                                            <span class="pie-format-card">
                                                <span class="pie-format-icon">{ }</span>
                                                <span class="pie-format-label">Pretty JSON</span>
                                                <span class="pie-format-desc">Human-readable format</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="export-actions">
                    <button type="button" id="preview-btn" class="button button-secondary">
                        <span class="dashicons dashicons-visibility"></span> Preview Selection
                    </button>
                    <button type="button" id="export-btn" class="button button-primary">
                        <span class="dashicons dashicons-download"></span> Export Products
                    </button>
                </div>
            </form>
            
            <!-- Debug Section -->
            <div class="pie-debug-section" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">
                <h3 style="margin: 0 0 15px 0; color: #6c757d;">
                    <span class="dashicons dashicons-bug"></span> Debug Tools
                </h3>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" id="view-logs-btn" class="button button-secondary">
                        <span class="dashicons dashicons-text-page"></span> View Debug Logs
                    </button>
                    <button type="button" id="clear-logs-btn" class="button button-secondary">
                        <span class="dashicons dashicons-trash"></span> Clear Logs
                    </button>
                    <span style="color: #6c757d; font-size: 12px;">Use these tools to debug export issues</span>
                </div>
            </div>
            
            <div id="preview-results" class="preview-section" style="display: none;">
                <h3>Export Preview</h3>
                <div id="preview-content"></div>
            </div>

            <div id="export-progress" style="display: none;">
                <h3>Export Progress</h3>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div id="export-status"></div>
                <div id="export-stats" style="margin-top: 10px; font-size: 12px; color: #666;"></div>
            </div>
            <div id="export-result" style="display: none;">
                <h3>Export Complete</h3>
                <p id="export-message"></p>
                <p>
                    <a id="download-link" href="#" class="button button-primary" style="display: none;" download>
                        <span class="dashicons dashicons-download"></span> Download Export File
                    </a>
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function import_page() {
        $max_upload_size = wp_max_upload_size();
        $max_upload_mb = round($max_upload_size / (1024 * 1024), 1);
        ?>
        <div class="wrap pie-import-wrap">
            <div class="pie-header">
                <h1><span class="dashicons dashicons-database-import"></span> Import Products</h1>
                <p class="pie-subtitle">Import WooCommerce products from ZIP exports with full media support</p>
            </div>
            
            <div class="pie-import-container">
                <div class="pie-import-main">
                    <div class="pie-upload-section">
                        <h3><span class="dashicons dashicons-upload"></span> Upload Import File</h3>
                        
                        <form id="import-form" enctype="multipart/form-data">
                            <div class="pie-upload-area" id="upload-area">
                                <div class="pie-upload-content">
                                    <div class="pie-upload-icon">
                                        <span class="dashicons dashicons-media-archive"></span>
                                    </div>
                                    <div class="pie-upload-text">
                                        <h4>Drag & Drop or Click to Upload</h4>
                                        <p>Upload a ZIP file containing exported products and images</p>
                                        <p class="pie-upload-limit">Maximum file size: <?php echo $max_upload_mb; ?>MB</p>
                                    </div>
                                    <input type="file" name="import_file" id="import_file" accept=".zip,.json" required />
                                    <button type="button" class="button button-secondary pie-browse-btn">Browse Files</button>
                                </div>
                            </div>
                            
                            <div class="pie-file-info" id="file-info" style="display: none;">
                                <div class="pie-file-preview">
                                    <span class="dashicons dashicons-media-archive"></span>
                                    <div class="pie-file-details">
                                        <div class="pie-file-name" id="file-name"></div>
                                        <div class="pie-file-size" id="file-size"></div>
                                        <div class="pie-file-type" id="file-type"></div>
                                    </div>
                                    <button type="button" class="pie-remove-file" id="remove-file">
                                        <span class="dashicons dashicons-no"></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="pie-import-options">
                        <h3><span class="dashicons dashicons-admin-settings"></span> Import Options</h3>
                        
                        <div class="pie-options-grid">
                            <div class="pie-option-group">
                                <h4>Product Management</h4>
                                <label class="pie-option-item">
                                    <input type="checkbox" name="update_existing" id="update_existing" value="1" checked />
                                    <span class="pie-option-content">
                                        <span class="pie-option-title">Update Existing Products</span>
                                        <span class="pie-option-desc">Match products by SKU and update if found (Recommended)</span>
                                    </span>
                                </label>
                                <label class="pie-option-item">
                                    <input type="checkbox" name="preserve_ids" id="preserve_ids" value="1" />
                                    <span class="pie-option-content">
                                        <span class="pie-option-title">Preserve Product IDs</span>
                                        <span class="pie-option-desc">Keep original product IDs (may cause conflicts)</span>
                                    </span>
                                </label>
                            </div>
                            
                            <div class="pie-option-group">
                                <h4>Image Handling</h4>
                                <label class="pie-option-item">
                                    <input type="checkbox" name="skip_images" id="skip_images" value="1" />
                                    <span class="pie-option-content">
                                        <span class="pie-option-title">Skip Image Import</span>
                                        <span class="pie-option-desc">Don't import product images</span>
                                    </span>
                                </label>
                                <label class="pie-option-item">
                                    <input type="checkbox" name="dedupe_images" id="dedupe_images" value="1" checked />
                                    <span class="pie-option-content">
                                        <span class="pie-option-title">Smart Image Deduplication</span>
                                        <span class="pie-option-desc">Avoid uploading duplicate images based on file hash</span>
                                    </span>
                                </label>
                                <label class="pie-option-item">
                                    <input type="checkbox" name="optimize_images" id="optimize_images" value="1" checked />
                                    <span class="pie-option-content">
                                        <span class="pie-option-title">Optimize Images</span>
                                        <span class="pie-option-desc">Generate WordPress image sizes and metadata</span>
                                    </span>
                                </label>
                            </div>
                            
                            <div class="pie-option-group">
                                <h4>Import Mode</h4>
                                <div class="pie-radio-group">
                                    <label class="pie-radio-item">
                                        <input type="radio" name="import_mode" value="standard" checked />
                                        <span class="pie-radio-content">
                                            <span class="pie-radio-title">Standard Import</span>
                                            <span class="pie-radio-desc">Full import with validation</span>
                                        </span>
                                    </label>
                                    <label class="pie-radio-item">
                                        <input type="radio" name="import_mode" value="quick" />
                                        <span class="pie-radio-content">
                                            <span class="pie-radio-title">Quick Import</span>
                                            <span class="pie-radio-desc">Faster import with minimal validation</span>
                                        </span>
                                    </label>
                                    <label class="pie-radio-item">
                                        <input type="radio" name="import_mode" value="preview" />
                                        <span class="pie-radio-content">
                                            <span class="pie-radio-title">Preview Only</span>
                                            <span class="pie-radio-desc">Analyze the file without importing</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pie-import-actions">
                        <button type="button" id="import-btn" class="button button-primary button-hero" disabled>
                            <span class="dashicons dashicons-upload"></span> Start Import
                        </button>
                        <button type="button" id="analyze-btn" class="button button-secondary" disabled>
                            <span class="dashicons dashicons-analytics"></span> Analyze File
                        </button>
                    </div>
                </div>
                
                <div class="pie-import-sidebar">
                    <div class="pie-import-info">
                        <h3>Import Information</h3>
                        <div class="pie-info-item">
                            <span class="pie-info-label">Supported Formats:</span>
                            <span class="pie-info-value">ZIP (with JSON + images), JSON only</span>
                        </div>
                        <div class="pie-info-item">
                            <span class="pie-info-label">Max File Size:</span>
                            <span class="pie-info-value"><?php echo $max_upload_mb; ?>MB</span>
                        </div>
                        <div class="pie-info-item">
                            <span class="pie-info-label">Deduplication:</span>
                            <span class="pie-info-value">MD5 hash comparison</span>
                        </div>
                    </div>
                    
                    <div class="pie-quick-tips">
                        <h3>Quick Tips</h3>
                        <ul>
                            <li>ZIP files should contain a JSON export and an 'images' folder</li>
                            <li>Enable deduplication to avoid duplicate images</li>
                            <li>Use preview mode to check your data first</li>
                            <li>Large imports may take several minutes</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div id="import-analysis" class="pie-analysis-section" style="display: none;">
                <h3><span class="dashicons dashicons-analytics"></span> Import Analysis</h3>
                <div id="analysis-content"></div>
            </div>
            
            <div id="import-progress" class="pie-progress-section" style="display: none;">
                <h3><span class="dashicons dashicons-update"></span> Import Progress</h3>
                <div class="pie-progress-container">
                    <div class="pie-progress-bar">
                        <div class="pie-progress-fill"></div>
                        <div class="pie-progress-text">0%</div>
                    </div>
                    <div class="pie-progress-details">
                        <div id="import-status">Preparing import...</div>
                        <div class="pie-progress-stats" id="import-stats">
                            <span class="pie-stat">Products: <span id="products-processed">0</span>/<span id="products-total">0</span></span>
                            <span class="pie-stat">Images: <span id="images-processed">0</span>/<span id="images-total">0</span></span>
                            <span class="pie-stat">Time: <span id="import-time">00:00</span></span>
                        </div>
                    </div>
                </div>
                <div class="pie-progress-log" id="progress-log"></div>
            </div>
            
            <div id="import-result" class="pie-result-section" style="display: none;">
                <h3><span class="dashicons dashicons-yes"></span> Import Complete</h3>
                <div class="pie-result-summary" id="import-summary"></div>
                <div class="pie-result-actions">
                    <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-products"></span> View Products
                    </a>
                    <button type="button" id="new-import-btn" class="button button-primary">
                        <span class="dashicons dashicons-plus"></span> Start New Import
                    </button>
                </div>
                <div class="pie-result-details" id="import-details"></div>
            </div>
            
            <!-- Debug Section -->
            <div class="pie-debug-section" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">
                <h3 style="margin: 0 0 15px 0; color: #6c757d;">
                    <span class="dashicons dashicons-bug"></span> Debug Tools
                </h3>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" id="view-import-logs-btn" class="button button-secondary">
                        <span class="dashicons dashicons-text-page"></span> View Import Logs
                    </button>
                    <button type="button" id="clear-import-logs-btn" class="button button-secondary">
                        <span class="dashicons dashicons-trash"></span> Clear Logs
                    </button>
                    <button type="button" id="test-zip-btn" class="button button-secondary">
                        <span class="dashicons dashicons-archive"></span> Test ZIP Extraction
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}