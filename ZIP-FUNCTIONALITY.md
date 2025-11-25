# WooCommerce Product Import/Export - ZIP File Support

## Overview

The WooCommerce Product Import/Export plugin now supports ZIP file exports and imports, automatically including product images with hash-based deduplication to prevent duplicate uploads.

## Key Features

### 🔄 **ZIP Export**
- Exports products as a ZIP file containing:
  - `products.json` - Complete product data with image URLs and metadata
  - `images/` folder - All product images organized by hash
  - `README.txt` - Information about the export

### 📦 **ZIP Import** 
- Supports both JSON and ZIP file imports
- Automatically detects file type and extracts ZIP contents
- Uses local images from ZIP when available
- Falls back to downloading from URLs if local images aren't found

### 🔍 **Image Deduplication**
- Uses MD5 file hashes to prevent duplicate image imports
- Checks for existing images by hash before downloading
- Stores hash metadata for future deduplication

## Export Process

### What Gets Exported

**Product Data:**
```json
{
  "image": {
    "id": 123,
    "url": "https://site.com/image.jpg",
    "filename": "product-image.jpg",
    "hash": "a1b2c3d4e5f6...",
    "title": "Product Image",
    "alt": "Alt text",
    "caption": "Image caption",
    "description": "Image description",
    "metadata": {...},
    "local_path": "images/a1b2c3d4e5f6.jpg"
  },
  "gallery_images": [...],
  "variations": [
    {
      "image": {...}
    }
  ]
}
```

**ZIP Structure:**
```
export-12345.zip
├── products.json          # Complete product data
├── images/               # Product images folder
│   ├── a1b2c3d4e5f6.jpg # Main product image
│   ├── x9y8z7w6v5u4.png # Gallery image 1
│   └── m5n4o3p2q1r0.jpg # Variation image
└── README.txt           # Export information
```

### Export Benefits

1. **Offline Backup**: Complete standalone backup with images
2. **Faster Import**: No need to download images from URLs
3. **Reliable**: Images guaranteed to be available during import
4. **Organized**: Images properly organized and named by hash

## Import Process

### Supported Formats

1. **ZIP Files (.zip)**:
   - Automatically extracts and processes
   - Uses local images when available
   - Downloads from URLs as fallback

2. **JSON Files (.json)**:
   - Legacy format still supported
   - Downloads all images from URLs

### Import Priority

For each image, the system checks in this order:

1. **Existing by Hash**: Check if image already exists in WordPress
2. **Local from ZIP**: Use image from extracted ZIP folder
3. **Download from URL**: Download from original URL as fallback

### Import Options

- **Update Existing Products**: Update products with matching SKUs
- **Skip Images**: Skip all image processing
- **Preserve IDs**: Attempt to maintain product IDs from export

## Technical Implementation

### File Processing

**Export Process:**
1. Generate JSON with image URLs and metadata
2. Download images and store by hash in temp directory
3. Create ZIP archive with JSON and images
4. Add local_path references to JSON
5. Return ZIP file for download

**Import Process:**
1. Detect file type (JSON vs ZIP)
2. Extract ZIP contents if needed
3. Parse products.json file
4. Process images using local files or URLs
5. Import products with images
6. Clean up temporary files

### Hash-Based Deduplication

```php
// Generate hash for deduplication
$image_hash = md5_file($image_path);

// Store hash in post meta
update_post_meta($attachment_id, '_wc_pie_image_hash', $image_hash);

// Check for existing images
$existing_id = $this->find_image_by_hash($image_hash);
```

### Error Handling

- Validates ZIP file integrity
- Checks image file types
- Handles missing local images
- Falls back to URL download
- Comprehensive logging for troubleshooting

## Usage Examples

### Basic Export
1. Go to WooCommerce → Product Import/Export
2. Select export options and products
3. Click "Export Products"
4. Download the generated ZIP file

### Basic Import
1. Go to WooCommerce → Product Import/Export  
2. Upload ZIP file or JSON file
3. Configure import options
4. Process import in batches
5. Review import results

### Advanced Scenarios

**Migrating Between Sites:**
1. Export from source site (ZIP format)
2. Download ZIP file
3. Upload to destination site
4. Import with "Update Existing" enabled

**Backup and Restore:**
1. Regular ZIP exports for backup
2. Store ZIP files securely
3. Restore from ZIP when needed

## File Management

### Cleanup Process
- Temporary files automatically cleaned after import/export
- ZIP extraction folders removed when import completes
- Original images remain in WordPress media library

### Storage Locations
- **Exports**: `/wp-content/uploads/wc-product-import-export/`
- **Temp Extraction**: `/wp-content/uploads/wc-product-import-export/extract_*/`
- **Images**: Standard WordPress uploads directory

## Troubleshooting

### Common Issues

**ZIP Creation Fails:**
- Check if ZipArchive PHP extension is installed
- Verify write permissions on uploads directory
- Ensure sufficient disk space

**Import Shows No Images:**
- Check that ZIP contains `images/` folder
- Verify image file formats (JPG, PNG, GIF, WebP)
- Review logs for download errors

**Large File Limits:**
- Increase PHP upload_max_filesize
- Adjust max_execution_time for large imports
- Use batch processing for better performance

### Debug Information

Enable logging to troubleshoot issues:
- Check plugin logs for detailed error messages
- Review WordPress error logs
- Monitor PHP memory usage during processing

## Compatibility

- **WordPress**: 5.0+
- **WooCommerce**: 3.0+
- **PHP**: 7.4+
- **Required Extensions**: ZipArchive, GD/ImageMagick

## Security Notes

- All files validated before processing
- Temporary files cleaned automatically
- Image types restricted to safe formats
- User permissions checked for all operations

---

For support and updates, visit the plugin documentation or contact support.