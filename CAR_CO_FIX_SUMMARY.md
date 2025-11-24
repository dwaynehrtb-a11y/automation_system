# CAR Course Outcomes Missing Fix

## Issue Identified
The Course Assessment Report (CAR) in the faculty portal was not displaying **Course Outcomes in the "Course Learning Outcomes Assessment"** section, while the COA (Course Outcomes Assessment Summary) report showed them correctly.

### Root Cause
The issue was a **JSON data format inconsistency** in the `grading_component_columns.co_mappings` field:
- Some records stored CO mappings as **quoted numbers**: `["1"]`, `["2"]`, `["3"]`
- Other records stored them as **unquoted numbers**: `[1]`, `[2]`, `[3]`

The CAR query used `JSON_QUOTE(CAST(co.co_number AS CHAR))` which only matched the quoted format, causing the JOIN to fail for records with unquoted numbers.

Example:
```
co.co_number = 1
JSON_QUOTE('1') = "1"
JSON_CONTAINS(["1"], "1") = TRUE ✓
JSON_CONTAINS([1], "1") = FALSE ✗
```

## Solution Implemented
Modified the `JSON_CONTAINS` condition to handle **both JSON formats** by using an OR clause:

```sql
AND (
  JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR))) 
  OR 
  JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS CHAR))
)
```

This ensures that:
- Quoted format `["1"]` matches with `JSON_QUOTE('1')` 
- Unquoted format `[1]` matches with plain `'1'`

## Files Modified

### 1. `faculty/ajax/generate_car_pdf_html.php` (Line ~175)
**Before:**
```php
LEFT JOIN course_outcomes co ON (
    gcc.co_mappings IS NOT NULL 
    AND JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR)))
)
```

**After:**
```php
LEFT JOIN course_outcomes co ON (
    gcc.co_mappings IS NOT NULL 
    AND (JSON_CONTAINS(gcc.co_mappings, JSON_QUOTE(CAST(co.co_number AS CHAR))) OR JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS CHAR)))
)
```

### 2. `faculty/ajax/generate_car_html.php` (Line ~127)
Updated the CO Performance query with the same fix.

### 3. `faculty/ajax/generate_coa_html.php` (Line ~136)
Updated the COA Performance query with the same fix.

## Verification
For class `25_T2_CTAPROJ1_INF223`, the query now correctly retrieves all Course Outcomes:
- **CO1**: Quiz - Target: 60%, Success: 25%
- **CO2**: Classwork - Target: 60%, Success: 0%
- **CO3**: Mock Defense - Target: 60%, Success: 25%

## Impact
✓ CAR reports now display all mapped Course Outcomes  
✓ Course Learning Outcomes Assessment section fully populated  
✓ Backward compatible with both JSON format variants  
✓ No database changes required  

## Recommendations for Future
To prevent similar issues:
1. **Standardize CO mappings format** - Always use quoted format `["1"]` when storing
2. **Add a data migration** - Normalize all existing CO mappings to quoted format:
   ```sql
   UPDATE grading_component_columns 
   SET co_mappings = JSON_ARRAY(JSON_EXTRACT(co_mappings, '$[*]'))
   WHERE co_mappings LIKE '[%';
   ```
3. **Add validation** - Ensure grading component creation always stores quoted format
