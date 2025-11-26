# Variable Products Import Enhancement - Version 1.2.0

## Overview
This update significantly enhances the plugin's ability to handle variable products and their variations during import. The solution is based on proven Stack Overflow methodology but enhanced with our robust image, price, category, and stock handling features.

## Key Enhancements

### 1. **Comprehensive Attribute Management**
- **Automatic Taxonomy Creation**: If a product attribute taxonomy (e.g., `pa_size`, `pa_color`) doesn't exist, it's automatically registered
- **Attribute Term Creation**: Missing attribute terms are automatically created when importing variations
- **Parent Product Sync**: Ensures all variation attribute terms are properly set on the parent variable product
- **Support for Both Global and Custom Attributes**: Handles taxonomy-based attributes and custom local attributes

### 2. **Enhanced Variation Import**
The new variation import system includes:

#### **Attribute Processing**
- Creates attribute terms if they don't exist
- Properly handles taxonomy slugs for variation attributes
- Syncs terms with parent variable product
- Validates attribute structure before import

#### **Robust Price Handling** (Same as Simple Products)
```php
// Validates and sets prices with proper type conversion
- Regular price handling with validation
- Sale price management
- Automatic active price calculation (uses sale price if available, otherwise regular price)
```

#### **Image Management** (Same as Simple Products)
```php
// Uses the same proven image import system
- Hash-based deduplication
- Local file path support
- URL fallback
- Metadata preservation (alt text, caption, description)
```

#### **Stock Management**
```php
// Comprehensive stock handling
- Manage stock toggle
- Stock quantity tracking
- Stock status (in stock, out of stock, on backorder)
- Automatic stock status clearing when using quantity management
```

#### **Additional Variation Properties**
- Description
- Weight, Length, Width, Height (shipping dimensions)
- Tax class
- Downloadable/Virtual flags
- SKU with duplicate detection
- Update existing vs. create new logic

### 3. **Import Process Flow**

```
1. Import Parent Variable Product
   ├── Set basic product data (name, description, etc.)
   ├── Import product attributes (creates taxonomies if needed)
   ├── Save parent product
   └── Import variations
   
2. For Each Variation
   ├── Check if variation exists (by SKU)
   ├── Create or update variation
   ├── Process attributes (create terms if needed)
   ├── Set prices with validation
   ├── Import images with deduplication
   ├── Configure stock management
   └── Save variation
```

## Usage Example

### Importing a Variable Product with Variations

```php
$variable_product_data = array(
    'type' => 'variable',
    'name' => 'T-Shirt',
    'sku' => 'TSHIRT-PARENT',
    'description' => 'Premium cotton t-shirt',
    'status' => 'publish',
    'regular_price' => '', // Parent product typically has no price
    'categories' => array(
        array(
            'id' => 123,
            'name' => 'Clothing',
            'slug' => 'clothing'
        )
    ),
    'image' => array(
        'url' => 'https://example.com/images/tshirt-main.jpg',
        'local_path' => '/path/to/local/tshirt.jpg',
        'alt' => 'Premium T-Shirt',
        'title' => 'T-Shirt Main Image'
    ),
    'attributes' => array(
        array(
            'name' => 'pa_size',
            'options' => array('S', 'M', 'L', 'XL'),
            'visible' => true,
            'variation' => true,
            'taxonomy' => true
        ),
        array(
            'name' => 'pa_color',
            'options' => array('Red', 'Blue', 'Green'),
            'visible' => true,
            'variation' => true,
            'taxonomy' => true
        )
    ),
    'variations' => array(
        array(
            'sku' => 'TSHIRT-S-RED',
            'attributes' => array(
                array('name' => 'pa_size', 'option' => 'S'),
                array('name' => 'pa_color', 'option' => 'Red')
            ),
            'regular_price' => '19.99',
            'sale_price' => '14.99',
            'stock_quantity' => 50,
            'image' => array(
                'url' => 'https://example.com/images/tshirt-s-red.jpg',
                'local_path' => '/path/to/local/tshirt-s-red.jpg',
                'alt' => 'Small Red T-Shirt'
            )
        ),
        array(
            'sku' => 'TSHIRT-M-BLUE',
            'attributes' => array(
                array('name' => 'pa_size', 'option' => 'M'),
                array('name' => 'pa_color', 'option' => 'Blue')
            ),
            'regular_price' => '19.99',
            'sale_price' => '',
            'stock_quantity' => 30,
            'image' => array(
                'url' => 'https://example.com/images/tshirt-m-blue.jpg',
                'alt' => 'Medium Blue T-Shirt'
            )
        )
        // ... more variations
    )
);

// Import with options
$importer = new WC_PIE_Importer();
$result = $importer->import_single_product($variable_product_data, array(
    'update_existing' => true,  // Update if SKU exists
    'skip_images' => false,     // Import images
    'preserve_ids' => false     // Don't preserve original IDs
));
```

## Technical Details

### New Methods Added

#### `import_product_attributes($product, $attributes_data)`
- Imports and sets attributes on variable products
- Handles both taxonomy and custom attributes
- Creates WC_Product_Attribute objects
- Sets proper variation flags

#### `register_product_attribute_taxonomy($taxonomy, $attribute_name)`
- Registers a new product attribute taxonomy if it doesn't exist
- Sets proper taxonomy arguments for WooCommerce compatibility
- Logs registration for debugging

#### `import_product_variations($parent_id, $variations, $options)`
- **Enhanced version** of variation import
- Processes attributes with term creation
- Applies robust image, price, and stock handling
- Handles update vs. create logic
- Comprehensive error handling and logging

#### `process_variation_attributes($parent_id, $attributes, $parent_product)`
- Processes variation attributes before setting on variation
- Creates taxonomy terms if they don't exist
- Syncs terms with parent product
- Returns properly formatted attributes array

## Benefits Over Previous Implementation

### Before (v1.1.1)
- ❌ No attribute term creation
- ❌ Basic attribute handling
- ❌ Limited price validation
- ❌ Simple image import
- ❌ Basic stock management

### After (v1.2.0)
- ✅ Automatic attribute term creation
- ✅ Taxonomy registration on-the-fly
- ✅ Robust price handling with validation
- ✅ Hash-based image deduplication
- ✅ Comprehensive stock management
- ✅ Parent-variation attribute sync
- ✅ Support for all variation properties
- ✅ Detailed logging for debugging

## Compatibility

- **WooCommerce**: 3.0.0+
- **WordPress**: 5.0+
- **PHP**: 7.0+
- **HPOS**: Fully compatible

## Error Handling

The enhanced system includes comprehensive error handling:
- Validates all input data
- Logs all operations for debugging
- Graceful failure (continues with other variations on error)
- Detailed error messages with context

## Logging

All operations are logged via `WC_PIE_Logger`:
- Attribute creation
- Term creation
- Taxonomy registration
- Variation import
- Image import
- Price setting
- Stock updates

## Performance Considerations

- **Image Deduplication**: Hash-based caching prevents duplicate imports
- **Taxonomy Caching**: Checks existing taxonomies before registration
- **Term Existence Checks**: Validates terms before creation
- **Batch Processing**: Handles large variation sets efficiently

## Known Limitations

1. **Large Variation Sets**: Very large variation sets (1000+) may require increased PHP memory
2. **External Images**: URL-based image imports depend on remote server availability
3. **Attribute Limits**: WooCommerce has practical limits on number of attributes per product

## Troubleshooting

### Variations Not Appearing
- Check that parent product has attributes set correctly
- Verify attribute names match between parent and variations
- Ensure terms are created in the correct taxonomy

### Images Not Importing
- Check file paths are accessible
- Verify URL is publicly accessible
- Check PHP memory limits for large images

### Price Issues
- Ensure prices are numeric (not strings with symbols)
- Verify sale price is less than regular price
- Check currency formatting

## Future Enhancements

Potential future improvements:
- Bulk variation creation UI
- CSV import/export for variations
- Variation template system
- Advanced attribute mapping
- Performance optimization for mega variation sets

## Credits

Based on Stack Overflow solution with significant enhancements for production use in WooCommerce import/export scenarios.

## Version History

- **1.2.0**: Enhanced variable product and variation import with comprehensive attribute, image, price, and stock handling
- **1.1.1**: Initial variable product support
- **1.0.0**: Basic product import/export

---

**Last Updated**: November 26, 2025
