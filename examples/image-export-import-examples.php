<?php
/**
 * Example script demonstrating the new image URL export/import functionality
 * 
 * This script shows how to use the enhanced WooCommerce Product Import/Export
 * plugin with URL-based image handling and deduplication.
 */

// This would typically be included in a WordPress environment
if (!defined('ABSPATH')) {
    // For demo purposes - in real usage this would be defined by WordPress
    die('This script should be run within WordPress environment');
}

/**
 * Example 1: Export products with image URLs and metadata
 */
function example_export_products_with_images() {
    $exporter = new WC_PIE_Exporter();
    
    $filters = array(
        'product_status' => array('publish'),
        'product_types' => array('simple', 'variable'),
        'limit' => 10
    );
    
    $options = array(
        'include_images' => true,       // Export image URLs and metadata
        'include_variations' => true,   // Include variation images
        'include_attributes' => true,
        'include_meta' => true
    );
    
    $exported_products = $exporter->export_products($filters, $options);
    
    echo "Exported " . count($exported_products) . " products\n";
    
    // Example of what the new image data looks like
    if (!empty($exported_products[0]['image'])) {
        $image_data = $exported_products[0]['image'];
        echo "Sample image data:\n";
        echo "- URL: " . $image_data['url'] . "\n";
        echo "- Hash: " . $image_data['hash'] . "\n";
        echo "- Title: " . $image_data['title'] . "\n";
        echo "- Alt: " . $image_data['alt'] . "\n";
    }
    
    return $exported_products;
}

/**
 * Example 2: Import products with automatic image deduplication
 */
function example_import_products_with_images($product_data_array) {
    $importer = new WC_PIE_Importer();
    
    $options = array(
        'update_existing' => true,  // Update existing products
        'skip_images' => false,     // Process images (set to true to skip)
        'preserve_ids' => false
    );
    
    $results = array(
        'success' => 0,
        'errors' => 0,
        'skipped' => 0
    );
    
    foreach ($product_data_array as $product_data) {
        try {
            $result = $importer->import_single_product($product_data, $options);
            $results['success']++;
            
            echo "Imported product: " . ($product_data['name'] ?? 'Unknown') . "\n";
            
            // Log image import details
            if (!empty($product_data['image'])) {
                echo "  - Main image: " . $product_data['image']['url'] . "\n";
            }
            if (!empty($product_data['gallery_images'])) {
                echo "  - Gallery images: " . count($product_data['gallery_images']) . "\n";
            }
            
        } catch (Exception $e) {
            $results['errors']++;
            echo "Error importing product: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nImport Summary:\n";
    echo "- Success: " . $results['success'] . "\n";
    echo "- Errors: " . $results['errors'] . "\n";
    
    return $results;
}

/**
 * Example 3: Test image deduplication
 */
function example_test_image_deduplication() {
    // Simulate product data with same image URL
    $product_data_1 = array(
        'name' => 'Test Product 1',
        'sku' => 'TEST-001',
        'type' => 'simple',
        'image' => array(
            'url' => 'https://example.com/wp-content/uploads/2024/test-image.jpg',
            'hash' => 'abc123def456',
            'title' => 'Test Image',
            'alt' => 'Test Alt Text'
        )
    );
    
    $product_data_2 = array(
        'name' => 'Test Product 2',
        'sku' => 'TEST-002',
        'type' => 'simple',
        'image' => array(
            'url' => 'https://example.com/wp-content/uploads/2024/test-image.jpg',
            'hash' => 'abc123def456',  // Same hash - should be deduplicated
            'title' => 'Test Image Copy',
            'alt' => 'Different Alt Text'
        )
    );
    
    $importer = new WC_PIE_Importer();
    
    try {
        echo "Importing first product...\n";
        $importer->import_single_product($product_data_1, array('skip_images' => false));
        
        echo "Importing second product (should use existing image)...\n";
        $importer->import_single_product($product_data_2, array('skip_images' => false));
        
        echo "Deduplication test completed successfully!\n";
        
    } catch (Exception $e) {
        echo "Error during deduplication test: " . $e->getMessage() . "\n";
    }
}

/**
 * Example 4: Handle legacy format compatibility
 */
function example_legacy_format_compatibility() {
    // Legacy format data (ID-based)
    $legacy_product_data = array(
        'name' => 'Legacy Product',
        'sku' => 'LEGACY-001',
        'type' => 'simple',
        'image_id' => 123,              // Old format
        'gallery_image_ids' => array(124, 125)  // Old format
    );
    
    // New format data (URL-based)
    $new_product_data = array(
        'name' => 'New Product',
        'sku' => 'NEW-001',
        'type' => 'simple',
        'image' => array(               // New format
            'url' => 'https://example.com/image.jpg',
            'hash' => 'xyz789',
            'title' => 'New Image'
        ),
        'gallery_images' => array(      // New format
            array(
                'url' => 'https://example.com/gallery1.jpg',
                'hash' => 'gallery1hash',
                'title' => 'Gallery 1'
            )
        )
    );
    
    $importer = new WC_PIE_Importer();
    
    try {
        echo "Testing legacy format import...\n";
        $importer->import_single_product($legacy_product_data, array('skip_images' => false));
        
        echo "Testing new format import...\n";
        $importer->import_single_product($new_product_data, array('skip_images' => false));
        
        echo "Compatibility test completed!\n";
        
    } catch (Exception $e) {
        echo "Error during compatibility test: " . $e->getMessage() . "\n";
    }
}

/**
 * Example 5: Bulk export/import workflow
 */
function example_bulk_workflow() {
    echo "Starting bulk export/import workflow...\n";
    
    // Step 1: Export products
    echo "1. Exporting products...\n";
    $exported_products = example_export_products_with_images();
    
    if (empty($exported_products)) {
        echo "No products to export. Ending workflow.\n";
        return;
    }
    
    // Step 2: Simulate external processing (e.g., price updates)
    echo "2. Processing exported data...\n";
    foreach ($exported_products as &$product) {
        // Example: Add 10% to price
        if (!empty($product['regular_price'])) {
            $product['regular_price'] = $product['regular_price'] * 1.1;
        }
        // Modify SKU to avoid conflicts during re-import
        $product['sku'] = 'PROCESSED-' . $product['sku'];
    }
    
    // Step 3: Import modified products
    echo "3. Importing processed products...\n";
    $results = example_import_products_with_images($exported_products);
    
    echo "Bulk workflow completed!\n";
    echo "Final results: " . json_encode($results, JSON_PRETTY_PRINT) . "\n";
}

// Example usage (uncomment to run specific examples):

// Example 1: Basic export
// example_export_products_with_images();

// Example 2: Test deduplication
// example_test_image_deduplication();

// Example 3: Test compatibility
// example_legacy_format_compatibility();

// Example 4: Full workflow
// example_bulk_workflow();

?>