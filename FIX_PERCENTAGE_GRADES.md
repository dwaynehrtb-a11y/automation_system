# Fix: Grade Input Showing Percentages Instead of Raw Scores

## Problem
When inputting grades in the faculty dashboard, the system was displaying percentage values (e.g., "60.00%", "58.73%") instead of the raw numeric scores that were entered (e.g., "10", "8.5").

## Root Cause
The issue had multiple potential causes:
1. **Legacy Data**: Older grades may have been stored as percentage values in the `raw_score` column
2. **Display Formatting**: The JavaScript was not stripping percentage symbols from displayed values
3. **Input Validation**: The system wasn't sanitizing percentage symbols if they were pasted into input fields

## Solutions Implemented

### 1. Frontend JavaScript Fixes (`flexible_grading.js`)

#### Enhanced Value Display (Lines 447-469)
- Added logic to strip percentage symbols (`%`) from raw values before display
- Improved number formatting to show integers without decimals, and decimals with 2 decimal places
- Added `step="0.01"` to input fields to allow precise decimal entry

```javascript
// Strip % symbol if present
if (typeof rawVal === 'string' && rawVal.includes('%')) {
    rawVal = rawVal.replace('%', '').trim();
}

// Format display value properly
const displayVal = rawVal !== '' ? 
    (parseFloat(rawVal) % 1 === 0 ? parseInt(rawVal) : parseFloat(rawVal).toFixed(2)) : '';
```

#### Enhanced Input Validation (Lines 593-604)
- Modified `validateInput()` function to strip percentage symbols on input/paste
- Ensures only numeric values are processed

```javascript
function validateInput(input, max) {
    // Remove any % symbols that might have been pasted
    if (input.value.includes('%')) {
        input.value = input.value.replace('%', '').trim();
    }
    // ... rest of validation
}
```

### 2. Backend PHP Fixes (`update_grade.php`)

#### Grade Value Sanitization (Lines 30-35)
- Added percentage symbol stripping before converting to float
- Ensures only numeric values are saved to database

```php
// Clean grade value - remove % symbol if present
$grade_raw = $_POST['grade'] ?? 0;
if (is_string($grade_raw)) {
    $grade_raw = str_replace('%', '', trim($grade_raw));
}
$grade = floatval($grade_raw);
```

### 3. Data Cleanup Utility (`fix_percentage_grades.php`)

Created a one-time utility script to identify and fix existing grades stored as percentages:

**Features:**
- Scans `student_flexible_grades` table for suspicious values
- Identifies grades where `raw_score > max_score` (indicating percentage storage)
- Provides visual report with suggested fixes
- Allows one-click fix for individual grades or bulk fix for all

**How to Use:**
1. Access: `http://localhost/automation_system/fix_percentage_grades.php`
2. Review the list of potentially incorrect grades
3. Click "Fix" for individual grades or "Fix All Grades" for bulk correction
4. The script converts percentages to raw scores using: `(percentage / 100) × max_score`

**Example Conversion:**
- **Column**: Quiz 1 (max_score: 10)
- **Current Value**: 60 (stored as percentage)
- **Converted To**: 6.0 (raw score)
- **Calculation**: (60 / 100) × 10 = 6.0

## Testing Steps

1. **Clear Browser Cache** to ensure new JavaScript loads
2. **Navigate to Faculty Dashboard** → Grading System
3. **Select a class** and load students
4. **Input Test Values**:
   - Enter whole numbers (e.g., "10") - should display as "10"
   - Enter decimals (e.g., "8.5") - should display as "8.50"
   - Try pasting "75%" - should strip to "75"
5. **Save and Reload** - values should persist as raw scores
6. **Check TOTAL % column** - should show calculated percentage (e.g., "85.0%")

## Expected Behavior After Fix

### Input Fields (Individual Grade Cells)
- ✓ Display raw numeric scores only (e.g., "10", "8.5", "7.25")
- ✓ No percentage symbols in input fields
- ✓ Decimals formatted to 2 places when present
- ✓ Integers display without decimal point

### TOTAL % Column
- ✓ Shows calculated percentage (e.g., "85.0%")
- ✓ Formula: (total_earned / total_possible) × 100

### Summary Tab
- ✓ Midterm %, Finals %, Term % show as percentages
- ✓ Discrete Grade shows letter grade (e.g., "1.25", "2.50")

## Files Modified

1. **`faculty/assets/js/flexible_grading.js`**
   - Enhanced value display logic
   - Improved input validation
   
2. **`ajax/update_grade.php`**
   - Added grade value sanitization

3. **`fix_percentage_grades.php`** (NEW)
   - Data cleanup utility

## Migration Notes

If you have existing data showing percentages in grade input fields:

1. **Run the cleanup utility first**: `fix_percentage_grades.php`
2. **Clear browser cache** on all devices
3. **Test with a sample class** before rolling out to all faculty
4. **Backup your database** before running bulk fixes

## Prevention Measures

The following safeguards are now in place:

- ✅ Frontend validation strips % symbols
- ✅ Backend validation strips % symbols
- ✅ Input fields enforce numeric-only entry with `type="number"`
- ✅ Step attribute allows precise decimal entry
- ✅ Display logic ensures consistent formatting

## Need Help?

If you still see percentages after applying these fixes:

1. **Check browser console** for JavaScript errors (F12 → Console tab)
2. **Verify cache is cleared** (Ctrl+Shift+R for hard reload)
3. **Run the cleanup utility** to fix database values
4. **Check database directly** using phpMyAdmin:
   ```sql
   SELECT * FROM student_flexible_grades 
   WHERE raw_score > 100 OR raw_score LIKE '%\%%';
   ```

## Summary

The issue was caused by percentage values being stored/displayed instead of raw scores. The fix involves:
- **JavaScript**: Strip % and format correctly
- **PHP**: Sanitize input values
- **Database**: Use cleanup utility for existing data

All new grades will now be stored and displayed as raw numeric scores, with percentages only shown in calculated summary columns.
