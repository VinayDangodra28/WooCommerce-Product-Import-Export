<?php
/**
 * Test Variable Products Import
 * 
 * This file demonstrates the enhanced variable product import functionality
 * with comprehensive attribute, image, price, and stock handling.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test 1: Import a simple variable product with size variations
 */
function test_variable_product_with_sizes() {
    echo "<h2>Test 1: Variable Product with Size Variations</h2>";
    
    $importer = new WC_PIE_Importer();
    
    $product_data = array(
        'type' => 'variable',
        'name' => 'Premium Cotton T-Shirt',
        'slug' => 'premium-cotton-tshirt',
        'sku' => 'TSHIRT-VAR-001',
        'description' => 'High-quality cotton t-shirt available in multiple sizes.',
        'short_description' => 'Premium cotton t-shirt',
        'status' => 'publish',
        'featured' => true,
        'catalog_visibility' => 'visible',
        'categories' => array(
            array(
                'id' => 0,
                'name' => 'Clothing',
                'slug' => 'clothing',
                'description' => 'Clothing category'
            )
        ),
        'tags' => array(
            array(
                'id' => 0,
                'name' => 'Cotton',
                'slug' => 'cotton'
            ),
            array(
                'id' => 0,
                'name' => 'Premium',
                'slug' => 'premium'
            )
        ),
        'image' => array(
            'url' => 'https://via.placeholder.com/800x800.png?text=T-Shirt',
            'title' => 'Premium T-Shirt',
            'alt' => 'Premium Cotton T-Shirt',
            'caption' => 'Main product image'
        ),
        'attributes' => array(
            array(
                'name' => 'pa_size',
                'options' => array('Small', 'Medium', 'Large', 'X-Large'),
                'position' => 0,
                'visible' => true,
                'variation' => true,
                'taxonomy' => true
            )
        ),
        'variations' => array(
            array(
                'sku' => 'TSHIRT-VAR-001-S',
                'attributes' => array(
                    array('name' => 'pa_size', 'option' => 'Small')
                ),
                'regular_price' => '19.99',
                'sale_price' => '14.99',
                'stock_quantity' => 50,
                'weight' => '0.2',
                'description' => 'Small size',
                'image' => array(
                    'url' => 'https://via.placeholder.com/800x800.png?text=Small',
                    'title' => 'Small T-Shirt',
                    'alt' => 'Small Size'
                )
            ),
            array(
                'sku' => 'TSHIRT-VAR-001-M',
                'attributes' => array(
                    array('name' => 'pa_size', 'option' => 'Medium')
                ),
                'regular_price' => '19.99',
                'sale_price' => '14.99',
                'stock_quantity' => 75,
                'weight' => '0.25',
                'description' => 'Medium size',
                'image' => array(
                    'url' => 'https://via.placeholder.com/800x800.png?text=Medium',
                    'title' => 'Medium T-Shirt',
                    'alt' => 'Medium Size'
                )
            ),
            array(
                'sku' => 'TSHIRT-VAR-001-L',
                'attributes' => array(
                    array('name' => 'pa_size', 'option' => 'Large')
                ),
                'regular_price' => '19.99',
                'sale_price' => '',
                'stock_quantity' => 60,
                'weight' => '0.3',
                'description' => 'Large size'
            ),
            array(
                'sku' => 'TSHIRT-VAR-001-XL',
                'attributes' => array(
                    array('name' => 'pa_size', 'option' => 'X-Large')
                ),
                'regular_price' => '21.99',
                'sale_price' => '',
                'stock_quantity' => 40,
                'weight' => '0.35',
                'description' => 'Extra large size'
            )
        )
    );
    
    try {
        $result = $importer->import_single_product($product_data, array(
            'update_existing' => true,
            'skip_images' => false
        ));
        
        echo "<div style='background: #dff0d8; padding: 10px; margin: 10px 0;'>";
        echo "<strong>✅ Success!</strong><br>";
        echo "Product ID: " . $result['id'] . "<br>";
        echo "Product Name: " . $result['name'] . "<br>";
        echo "SKU: " . $result['sku'] . "<br>";
        echo "Action: " . $result['action'] . "<br>";
        echo "</div>";
    } catch (Exception $e) {
        echo "<div style='background: #f2dede; padding: 10px; margin: 10px 0;'>";
        echo "<strong>❌ Error:</strong> " . $e->getMessage();
        echo "</div>";
    }
}

/**
 * Test 2: Import variable product with multiple attributes (size & color)
 */
function test_variable_product_with_multiple_attributes() {
    echo "<h2>Test 2: Variable Product with Size and Color</h2>";
    
    $importer = new WC_PIE_Importer();
    
    $product_data = array(
        'type' => 'variable',
        'name' => 'Designer Hoodie',
        'slug' => 'designer-hoodie',
        'sku' => 'HOODIE-VAR-001',
        'description' => 'Stylish designer hoodie available in multiple sizes and colors.',
        'short_description' => 'Designer hoodie',
        'status' => 'publish',
        'categories' => array(
            array(
                'id' => 0,
                'name' => 'Clothing',
                'slug' => 'clothing'
            )
        ),
        'image' => array(
            'url' => 'https://via.placeholder.com/800x800.png?text=Hoodie',
            'title' => 'Designer Hoodie',
            'alt' => 'Designer Hoodie'
        ),
        'attributes' => array(
            array(
                'name' => 'pa_size',
                'options' => array('Small', 'Medium', 'Large'),
                'position' => 0,
                'visible' => true,
                'variation' => true,
                'taxonomy' => true
            ),
            array(
                'name' => 'pa_color',
                'options' => array('Black', 'Navy', 'Gray'),
                'position' => 1,
                'visible' => true,
                'variation' => true,
                'taxonomy' => true
            )
        ),
        'variations' => array(
            array(
                'sku' => 'HOODIE-VAR-001-S-BLACK',
                'attributes' => array(
                    array('name' => 'pa_size', 'option' => 'Small'),
                    array('name' => 'pa_color', 'option' => 'Black')
                ),
                'regular_price' => '49.99',
                'sale_price' => '39.99',
                'stock_quantity' => 25,
                'image' => array(
                    'url' => 'https://via.placeholder.com/800x800.png?text=Small+Black',
                    'alt' => 'Small Black Hoodie'
                )
            ),
            array(
                'sku' => 'HOODIE-VAR-001-M-NAVY',
                'attributes' => array(
                    array('name' => 'pa_size', 'option' => 'Medium'),
                    array('name' => 'pa_color', 'option' => 'Navy')
                ),
                'regular_price' => '49.99',
                'sale_price' => '39.99',
                'stock_quantity' => 30,
                'image' => array(
                    'url' => 'https://via.placeholder.com/800x800.png?text=Medium+Navy',
                    'alt' => 'Medium Navy Hoodie'
                )
            ),
            array(
                'sku' => 'HOODIE-VAR-001-L-GRAY',
                'attributes' => array(
                    array('name' => 'pa_size', 'option' => 'Large'),
                    array('name' => 'pa_color', 'option' => 'Gray')
                ),
                'regular_price' => '49.99',
                'sale_price' => '',
                'stock_quantity' => 20
            )
        )
    );
    
    try {
        $result = $importer->import_single_product($product_data, array(
            'update_existing' => true,
            'skip_images' => false
        ));
        
        echo "<div style='background: #dff0d8; padding: 10px; margin: 10px 0;'>";
        echo "<strong>✅ Success!</strong><br>";
        echo "Product ID: " . $result['id'] . "<br>";
        echo "Product Name: " . $result['name'] . "<br>";
        echo "SKU: " . $result['sku'] . "<br>";
        echo "Action: " . $result['action'] . "<br>";
        echo "</div>";
    } catch (Exception $e) {
        echo "<div style='background: #f2dede; padding: 10px; margin: 10px 0;'>";
        echo "<strong>❌ Error:</strong> " . $e->getMessage();
        echo "</div>";
    }
}

/**
 * Test 3: Import variable product with custom (non-taxonomy) attributes
 */
function test_variable_product_with_custom_attributes() {
    echo "<h2>Test 3: Variable Product with Custom Attributes</h2>";
    
    $importer = new WC_PIE_Importer();
    
    $product_data = array(
        'type' => 'variable',
        'name' => 'Custom Phone Case',
        'slug' => 'custom-phone-case',
        'sku' => 'CASE-VAR-001',
        'description' => 'Customizable phone case with various options.',
        'status' => 'publish',
        'attributes' => array(
            array(
                'name' => 'Phone Model',
                'options' => array('iPhone 14', 'iPhone 15', 'Samsung S23'),
                'position' => 0,
                'visible' => true,
                'variation' => true,
                'taxonomy' => false  // Custom attribute
            ),
            array(
                'name' => 'Material',
                'options' => array('Plastic', 'Silicone', 'Leather'),
                'position' => 1,
                'visible' => true,
                'variation' => true,
                'taxonomy' => false  // Custom attribute
            )
        ),
        'variations' => array(
            array(
                'sku' => 'CASE-VAR-001-IP14-PLASTIC',
                'attributes' => array(
                    array('name' => 'Phone Model', 'option' => 'iPhone 14'),
                    array('name' => 'Material', 'option' => 'Plastic')
                ),
                'regular_price' => '12.99',
                'stock_quantity' => 100
            ),
            array(
                'sku' => 'CASE-VAR-001-IP15-LEATHER',
                'attributes' => array(
                    array('name' => 'Phone Model', 'option' => 'iPhone 15'),
                    array('name' => 'Material', 'option' => 'Leather')
                ),
                'regular_price' => '24.99',
                'sale_price' => '19.99',
                'stock_quantity' => 50
            )
        )
    );
    
    try {
        $result = $importer->import_single_product($product_data, array(
            'update_existing' => true,
            'skip_images' => true  // Skip images for this test
        ));
        
        echo "<div style='background: #dff0d8; padding: 10px; margin: 10px 0;'>";
        echo "<strong>✅ Success!</strong><br>";
        echo "Product ID: " . $result['id'] . "<br>";
        echo "Product Name: " . $result['name'] . "<br>";
        echo "SKU: " . $result['sku'] . "<br>";
        echo "</div>";
    } catch (Exception $e) {
        echo "<div style='background: #f2dede; padding: 10px; margin: 10px 0;'>";
        echo "<strong>❌ Error:</strong> " . $e->getMessage();
        echo "</div>";
    }
}

/**
 * Test 4: Update existing variable product
 */
function test_update_existing_variable_product() {
    echo "<h2>Test 4: Update Existing Variable Product</h2>";
    
    $importer = new WC_PIE_Importer();
    
    // First, create a product
    $product_data = array(
        'type' => 'variable',
        'name' => 'Update Test Product',
        'sku' => 'UPDATE-TEST-001',
        'status' => 'publish',
        'attributes' => array(
            array(
                'name' => 'pa_size',
                'options' => array('Small', 'Medium'),
                'visible' => true,
                'variation' => true,
                'taxonomy' => true
            )
        ),
        'variations' => array(
            array(
                'sku' => 'UPDATE-TEST-001-S',
                'attributes' => array(
                    array('name' => 'pa_size', 'option' => 'Small')
                ),
                'regular_price' => '10.00',
                'stock_quantity' => 10
            )
        )
    );
    
    try {
        // First import
        $result1 = $importer->import_single_product($product_data, array(
            'update_existing' => false,
            'skip_images' => true
        ));
        
        echo "<div style='background: #d9edf7; padding: 10px; margin: 10px 0;'>";
        echo "<strong>Step 1 - Created:</strong> Product ID " . $result1['id'];
        echo "</div>";
        
        // Now update with new price and add a variation
        $product_data['name'] = 'Update Test Product (UPDATED)';
        $product_data['variations'][] = array(
            'sku' => 'UPDATE-TEST-001-M',
            'attributes' => array(
                array('name' => 'pa_size', 'option' => 'Medium')
            ),
            'regular_price' => '12.00',
            'stock_quantity' => 15
        );
        
        $result2 = $importer->import_single_product($product_data, array(
            'update_existing' => true,
            'skip_images' => true
        ));
        
        echo "<div style='background: #dff0d8; padding: 10px; margin: 10px 0;'>";
        echo "<strong>Step 2 - Updated:</strong><br>";
        echo "Product ID: " . $result2['id'] . "<br>";
        echo "Product Name: " . $result2['name'] . "<br>";
        echo "Action: " . $result2['action'] . "<br>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div style='background: #f2dede; padding: 10px; margin: 10px 0;'>";
        echo "<strong>❌ Error:</strong> " . $e->getMessage();
        echo "</div>";
    }
}

// Run tests if accessed via browser with test parameter
if (isset($_GET['run_variable_tests'])) {
    echo "<h1>Variable Products Import Tests</h1>";
    echo "<p>Testing enhanced variable product import functionality (v1.2.0)</p>";
    echo "<hr>";
    
    test_variable_product_with_sizes();
    echo "<hr>";
    
    test_variable_product_with_multiple_attributes();
    echo "<hr>";
    
    test_variable_product_with_custom_attributes();
    echo "<hr>";
    
    test_update_existing_variable_product();
    echo "<hr>";
    
    echo "<h3>All tests completed!</h3>";
    echo "<p>Check WooCommerce → Products to verify imported products.</p>";
}
