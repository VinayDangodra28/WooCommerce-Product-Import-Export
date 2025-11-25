# WooCommerce Product Import/Export - Image URL Export/Import with Deduplication

## Overview

This plugin has been enhanced to export and import image URLs instead of just image IDs, with built-in image deduplication using hash-based detection to prevent duplicate images in the WordPress media library.

## Key Features

1. **Image URL Export**: Exports full image URLs along with metadata
2. **Hash-based Deduplication**: Prevents duplicate images using MD5 file hashes
3. **Metadata Preservation**: Maintains image titles, alt text, captions, and descriptions
4. **Backward Compatibility**: Still supports legacy ID-based format
5. **Error Handling**: Robust error handling for missing or inaccessible images

## Export Format

### New Export Format
Images are now exported with the following structure:

```json
{
  "image": {
    "id": 123,
    "url": "https://example.com/wp-content/uploads/2024/image.jpg",
    "filename": "image.jpg",
    "hash": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
    "title": "Product Image",
    "alt": "Alternative text",
    "caption": "Image caption",
    "description": "Image description",
    "metadata": { /* WordPress attachment metadata */ },
    "mime_type": "image/jpeg"
  },
  "gallery_images": [
    {
      "id": 124,
      "url": "https://example.com/wp-content/uploads/2024/gallery1.jpg",
      "filename": "gallery1.jpg",
      "hash": "b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7",
      "title": "Gallery Image 1",
      "alt": "Gallery alt text",
      "caption": "",
      "description": "",
      "metadata": { /* WordPress attachment metadata */ },
      "mime_type": "image/jpeg"
    }
  ]
}
```

### Legacy Support
For backward compatibility, the old format is still included:

```json
{
  "image_id": 123,
  "gallery_image_ids": [124, 125]
}
```

## Import Process

### Image Deduplication Flow

1. **Hash Check**: First checks if an image with the same hash already exists
2. **URL Check**: If no hash match, checks if image URL already exists
3. **Download**: If no existing image found, downloads from URL
4. **Hash Generation**: Generates hash for new images if not provided
5. **Metadata Assignment**: Sets title, alt text, caption, and description

### Example Usage

```php
// Export products with new image format
$exporter = new WC_PIE_Exporter();
$products = $exporter->export_products($filters, [
    'include_images' => true,
    'include_variations' => true
]);

// Import products with automatic image handling
$importer = new WC_PIE_Importer();
foreach ($products as $product_data) {
    $importer->import_single_product($product_data, [
        'update_existing' => true,
        'skip_images' => false // Set to true to skip image import
    ]);
}
```

## Technical Details

### Hash Generation
- Uses MD5 hash of actual file content for local images
- Falls back to URL-based hash for inaccessible files
- Stores hash in `_wc_pie_image_hash` post meta

### Deduplication Logic
1. Query existing attachments by `_wc_pie_image_hash` meta
2. If no hash match, query by filename in `_wp_attached_file` meta
3. Only download if no existing image found

### Error Handling
- Validates URLs before attempting download
- Checks remote accessibility with HEAD request
- Graceful fallback to legacy format if new format fails
- Comprehensive logging of all operations

## Configuration Options

### Export Options
- `include_images`: Enable/disable image export (default: true)

### Import Options
- `skip_images`: Skip image import entirely (default: false)
- `update_existing`: Allow updating existing products (default: false)

## Migration from Legacy Format

The plugin automatically handles both old and new formats:

- **Export**: Always exports both new URL-based and legacy ID-based formats
- **Import**: Attempts new format first, falls back to legacy format
- **Variations**: Full support for variation images in both formats

## Performance Considerations

1. **Network Requests**: Downloads images only when necessary
2. **Database Queries**: Efficient hash-based lookup prevents duplicate queries
3. **Memory Usage**: Processes images one at a time
4. **Error Recovery**: Continues processing even if individual images fail

## Troubleshooting

### Common Issues

1. **Image Download Fails**
   - Check URL accessibility
   - Verify WordPress media upload permissions
   - Check available disk space

2. **Duplicate Images**
   - Ensure hash generation is working
   - Check `_wc_pie_image_hash` meta values
   - Verify deduplication queries

3. **Missing Metadata**
   - Confirm image data structure in export
   - Check WordPress attachment post type
   - Verify meta data permissions

### Logging

All operations are logged using `WC_PIE_Logger`:
- Image hash generation
- Deduplication checks
- Download attempts
- Metadata assignment
- Error conditions

## Future Enhancements

Potential improvements for future versions:
- Alternative hash algorithms (SHA256)
- Image optimization during import
- Batch download for better performance
- CDN URL handling
- Image format conversion