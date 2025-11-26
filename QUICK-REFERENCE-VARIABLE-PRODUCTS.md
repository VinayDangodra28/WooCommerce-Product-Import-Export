# Variable Products - Quick Reference Guide

## Plugin Version: 1.2.0

### Key Features for Variable Products

✅ **Automatic Attribute Creation** - No need to pre-create attributes
✅ **Taxonomy Registration** - Auto-registers taxonomies like `pa_size`, `pa_color`
✅ **Term Management** - Creates attribute terms automatically
✅ **Image Deduplication** - Hash-based to prevent duplicates
✅ **Robust Price Handling** - With validation and type conversion
✅ **Stock Management** - Quantity-based or status-based
✅ **Parent-Variation Sync** - Keeps attributes in sync

---

## Basic Variable Product Structure

```php
array(
    'type' => 'variable',
    'name' => 'Product Name',
    'sku' => 'PARENT-SKU',
    
    // Attributes MUST be set for variable products
    'attributes' => array(
        array(
            'name' => 'pa_size',              // pa_ prefix for taxonomy
            'options' => array('S', 'M', 'L'),
            'visible' => true,
            'variation' => true,               // Must be true for variations
            'taxonomy' => true                 // True for global attributes
        )
    ),
    
    // Variations use the attributes defined above
    'variations' => array(
        array(
            'sku' => 'VAR-SKU-S',
            'attributes' => array(
                array('name' => 'pa_size', 'option' => 'S')
            ),
            'regular_price' => '10.00',
            'stock_quantity' => 50
        )
    )
)
```

---

## Attribute Types

### 1. Global/Taxonomy Attributes (Recommended)
- **Use when**: Attributes should be shared across products
- **Prefix**: `pa_` (e.g., `pa_size`, `pa_color`)
- **Benefit**: Site-wide consistency, better filtering

```php
'attributes' => array(
    array(
        'name' => 'pa_color',
        'options' => array('Red', 'Blue', 'Green'),
        'taxonomy' => true,
        'variation' => true
    )
)
```

### 2. Custom/Local Attributes
- **Use when**: Unique to this product
- **No prefix**: Just use plain name
- **Benefit**: Flexibility, product-specific

```php
'attributes' => array(
    array(
        'name' => 'Phone Model',
        'options' => array('iPhone 14', 'iPhone 15'),
        'taxonomy' => false,
        'variation' => true
    )
)
```

---

## Price Configuration

### Regular Price Only
```php
'variations' => array(
    array(
        'regular_price' => '19.99',
        'sale_price' => '',  // No sale
        // Active price will be 19.99
    )
)
```

### With Sale Price
```php
'variations' => array(
    array(
        'regular_price' => '19.99',
        'sale_price' => '14.99',  // On sale!
        // Active price will be 14.99
    )
)
```

---

## Stock Management Options

### Option 1: Manage by Quantity
```php
'stock_quantity' => 50,
// Plugin automatically sets:
// - manage_stock = true
// - stock_status = '' (calculated from quantity)
```

### Option 2: Manage by Status
```php
'stock_status' => 'instock',  // or 'outofstock', 'onbackorder'
// manage_stock = false
```

### Option 3: Don't Manage Stock
```php
// Simply omit both stock_quantity and stock_status
// manage_stock = false
// stock_status = 'instock' (default)
```

---

## Image Handling

### Parent Product Image
```php
'image' => array(
    'url' => 'https://example.com/image.jpg',      // URL (fallback)
    'local_path' => '/full/path/to/image.jpg',     // Local path (preferred)
    'title' => 'Image Title',
    'alt' => 'Alt Text',
    'caption' => 'Caption',
    'description' => 'Description'
)
```

### Variation Images
```php
'variations' => array(
    array(
        'sku' => 'VAR-001',
        'image' => array(
            'url' => 'https://example.com/red.jpg',
            'alt' => 'Red Variation'
        )
    )
)
```

**Note**: Images are automatically deduplicated using hash comparison!

---

## Complete Example: T-Shirt with Sizes

```php
$product_data = array(
    'type' => 'variable',
    'name' => 'Premium Cotton T-Shirt',
    'sku' => 'TSHIRT-001',
    'description' => 'Soft cotton t-shirt',
    'status' => 'publish',
    
    // Categories
    'categories' => array(
        array('name' => 'Clothing', 'slug' => 'clothing')
    ),
    
    // Main image
    'image' => array(
        'url' => 'https://example.com/tshirt.jpg',
        'alt' => 'T-Shirt'
    ),
    
    // Define size attribute
    'attributes' => array(
        array(
            'name' => 'pa_size',
            'options' => array('S', 'M', 'L', 'XL'),
            'visible' => true,
            'variation' => true,
            'taxonomy' => true
        )
    ),
    
    // Create variations
    'variations' => array(
        // Small - On Sale
        array(
            'sku' => 'TSHIRT-001-S',
            'attributes' => array(
                array('name' => 'pa_size', 'option' => 'S')
            ),
            'regular_price' => '19.99',
            'sale_price' => '14.99',
            'stock_quantity' => 50
        ),
        
        // Medium - Regular Price
        array(
            'sku' => 'TSHIRT-001-M',
            'attributes' => array(
                array('name' => 'pa_size', 'option' => 'M')
            ),
            'regular_price' => '19.99',
            'stock_quantity' => 75
        ),
        
        // Large - Out of Stock
        array(
            'sku' => 'TSHIRT-001-L',
            'attributes' => array(
                array('name' => 'pa_size', 'option' => 'L')
            ),
            'regular_price' => '19.99',
            'stock_status' => 'outofstock'
        ),
        
        // XL - Premium Price
        array(
            'sku' => 'TSHIRT-001-XL',
            'attributes' => array(
                array('name' => 'pa_size', 'option' => 'XL')
            ),
            'regular_price' => '21.99',
            'stock_quantity' => 30
        )
    )
);

// Import the product
$importer = new WC_PIE_Importer();
$result = $importer->import_single_product($product_data, array(
    'update_existing' => true,
    'skip_images' => false
));
```

---

## Multiple Attributes Example: Size + Color

```php
$product_data = array(
    'type' => 'variable',
    'name' => 'Designer Hoodie',
    'sku' => 'HOODIE-001',
    
    // TWO attributes
    'attributes' => array(
        array(
            'name' => 'pa_size',
            'options' => array('S', 'M', 'L'),
            'variation' => true,
            'taxonomy' => true
        ),
        array(
            'name' => 'pa_color',
            'options' => array('Black', 'Navy', 'Gray'),
            'variation' => true,
            'taxonomy' => true
        )
    ),
    
    // Variations with BOTH attributes
    'variations' => array(
        array(
            'sku' => 'HOODIE-001-S-BLACK',
            'attributes' => array(
                array('name' => 'pa_size', 'option' => 'S'),
                array('name' => 'pa_color', 'option' => 'Black')
            ),
            'regular_price' => '49.99',
            'sale_price' => '39.99',
            'stock_quantity' => 25
        ),
        array(
            'sku' => 'HOODIE-001-M-NAVY',
            'attributes' => array(
                array('name' => 'pa_size', 'option' => 'M'),
                array('name' => 'pa_color', 'option' => 'Navy')
            ),
            'regular_price' => '49.99',
            'stock_quantity' => 30
        )
        // Add more combinations as needed
    )
);
```

---

## Import Options

```php
$options = array(
    'update_existing' => true,   // Update products with same SKU
    'skip_images' => false,      // Set true to skip image import
    'preserve_ids' => false      // Preserve original product IDs
);

$result = $importer->import_single_product($product_data, $options);
```

---

## Troubleshooting

### Problem: Variations not showing
**Solution**: Ensure parent product has `attributes` array with all variation attributes defined

### Problem: Attributes not created
**Solution**: Check attribute name format - use `pa_` prefix for taxonomy attributes

### Problem: Terms not appearing
**Solution**: Plugin creates them automatically, but check logs if issues persist

### Problem: Images not importing
**Solution**: 
1. Check file path is accessible
2. Verify URL is publicly available
3. Check PHP memory limit for large images

### Problem: Prices incorrect
**Solution**: Ensure prices are numeric strings (e.g., '19.99', not '$19.99')

---

## Best Practices

1. ✅ **Use taxonomy attributes** (`pa_*`) for attributes shared across products
2. ✅ **Provide all attribute options** in parent product's `attributes` array
3. ✅ **Use unique SKUs** for each variation
4. ✅ **Validate prices** - ensure they're numeric before import
5. ✅ **Use local paths** for images when possible (faster)
6. ✅ **Test with small batch** first before bulk import
7. ✅ **Enable logging** to debug issues
8. ✅ **Back up database** before large imports

---

## Logging

All operations are logged. Check logs at:
- WooCommerce → Status → Logs → `wc-product-import-export-{date}.log`

---

## Performance Tips

- **Large variation sets**: Increase PHP memory limit
- **Many images**: Use local paths instead of URLs
- **Bulk imports**: Process in batches of 50-100 products
- **External images**: Ensure good network connection

---

## Need Help?

1. Check the full documentation: `VARIABLE-PRODUCTS-ENHANCEMENT.md`
2. Review test examples: `test-variable-products.php`
3. Enable debug logging
4. Check WooCommerce system status

---

**Plugin Version**: 1.2.0  
**Last Updated**: November 26, 2025
