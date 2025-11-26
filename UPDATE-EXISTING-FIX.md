# Update Existing Products - Fixed ✅

## Changes Made

### 1. Default Checkbox State
**File:** `includes/class-wc-pie-admin.php`

Changed the "Update Existing Products" checkbox to be **checked by default**:
```html
<!-- Before -->
<input type="checkbox" name="update_existing" id="update_existing" value="1" />

<!-- After -->
<input type="checkbox" name="update_existing" id="update_existing" value="1" checked />
```

Added "(Recommended)" to the description to make it clear this is the preferred option.

---

### 2. Fixed Option Value Parsing
**File:** `includes/class-wc-pie-importer.php`

Fixed the boolean checking logic to properly handle string values from JavaScript:

```php
// Before (BROKEN - string '0' evaluates to true!)
$update_existing = isset($options['update_existing']) ? $options['update_existing'] : false;

// After (FIXED - properly checks for truthy values)
$update_existing = !empty($options['update_existing']) && 
                   ($options['update_existing'] === true || 
                    $options['update_existing'] === '1' || 
                    $options['update_existing'] === 1);
```

This ensures that:
- ✅ `true` → true
- ✅ `'1'` → true (from checked checkbox)
- ✅ `1` → true
- ❌ `'0'` → false (from unchecked checkbox)
- ❌ `false` → false
- ❌ `null` → false
- ❌ `''` → false

---

## What This Fixes

### Before
❌ Import would fail with errors like:
```
Product with SKU LTC-PCRC already exists (ID: 728)
Product with SKU LTC-PWEK already exists (ID: 729)
```

### After
✅ Products are **automatically updated** instead of throwing errors
✅ Checkbox is **checked by default** for convenience
✅ Options are **properly parsed** from form submission

---

## How to Use

### Default Behavior (Recommended)
1. Go to **WooCommerce > Product Import/Export > Import**
2. Upload your export file
3. The "Update Existing Products" option is **already checked** ✅
4. Click **Import Products**
5. Existing products will be updated, not skipped

### Manual Control
If you want to skip existing products instead:
1. **Uncheck** the "Update Existing Products" option
2. Import will skip products with existing SKUs

---

## Testing

### Test Case 1: Update Existing Products (Default)
```
1. Export products from site A
2. Import to site B (creates new products)
3. Modify products on site A
4. Re-export from site A
5. Import to site B again with checkbox CHECKED
   ✅ Products should be updated, not duplicated
```

### Test Case 2: Skip Existing Products
```
1. Export products
2. Import once (creates products)
3. Try importing again with checkbox UNCHECKED
   ✅ Should get "already exists" errors
```

---

## Related Files

- ✅ `includes/class-wc-pie-admin.php` - Checkbox now checked by default
- ✅ `includes/class-wc-pie-importer.php` - Fixed option parsing
- ✅ `assets/js/script.js` - Already sends options correctly
- ✅ `includes/class-wc-pie-ajax.php` - Already passes options correctly

---

## Plugin Version

Updated to **1.1.1** with this fix.

---

## Recommendation

**Always leave "Update Existing Products" checked** unless you specifically want to:
- Create duplicate products with new IDs
- Track import errors for existing SKUs
- Test import without modifying existing data

For normal use (re-importing/syncing products), keep it checked! ✅
