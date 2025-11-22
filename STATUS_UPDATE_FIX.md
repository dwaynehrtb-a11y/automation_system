# Grade Status Persistence Fix

## Problem
When faculty changed a student's grade status (e.g., from "Failed" to "INC"), the change would appear to save but would revert back to the original status after a hard page refresh.

## Root Cause
The `GradesModel::saveGrades()` method had a critical flaw:
- When faculty updates grades (midterm/finals percentages), the API sends grade data but typically does NOT include `grade_status`
- The `saveGrades()` method would execute an UPDATE query with all fields from `$gradeData`
- Since `grade_status` was not in the update request, it would be set to NULL or an empty encrypted value
- This overwrote the manually-set grade status (passed/failed/incomplete/dropped) that faculty had set separately

## Solution Implemented

### 1. Modified `GradesModel::saveGrades()` (includes/GradesModel.php)
**Key Change**: Before updating, the method now:
1. Queries the existing record to get the current `grade_status`
2. If `grade_status` is NOT provided in the update request, it preserves the existing value
3. Only overwrites `grade_status` if it's explicitly provided in the update data

```php
// If grade_status not provided in update, preserve the existing one
if ($exists && !isset($gradeData['grade_status'])) {
    $gradeData['grade_status'] = $existingGradeStatus;
}
```

### 2. Enhanced Student Grade Retrieval (student/ajax/get_grades.php)
**Key Change**: The fallback path (when GradesModel doesn't find a record) now:
1. Queries `term_grades` directly for `grade_status`
2. Returns `grade_status` in the response alongside the calculated term grade
3. Ensures consistency between main path and fallback path

```php
// Try to get grade_status from term_grades even in fallback
$status_stmt = $conn->prepare("SELECT grade_status FROM term_grades WHERE student_id = ? AND class_code = ? LIMIT 1");
```

## Impact on System

### Faculty Dashboard
✅ Status changes (Passed/Failed/INC/Dropped) now persist across page refreshes
✅ Grade calculations no longer overwrite manually-set statuses
✅ Status badge updates remain visible after hard refresh

### Student Dashboard
✅ Students see the correct grade status from `term_grades` table
✅ Status displays consistently whether through main path or fallback path

### CAR PDF Generation
✅ CAR PDF automatically reflects updated grade statuses
✅ No changes needed to CAR generation - it queries `grade_status` directly from database

### Grade Visibility
✅ Existing visibility fixes (Show/Hide Grades) continue to work
✅ Status persistence is independent of visibility status

## Testing Checklist

- [ ] Faculty changes status from "Failed" to "INC" → Hard refresh → Status remains "INC"
- [ ] Faculty changes status from "Passed" to "Incomplete" → Hard refresh → Status remains "Incomplete"
- [ ] Faculty updates grades (midterm/finals) while status is "INC" → Hard refresh → Status still "INC"
- [ ] Student dashboard shows correct grade status after faculty update
- [ ] CAR PDF displays correct grade status after faculty update
- [ ] Multiple status changes in sequence persist correctly

## Files Modified

1. **includes/GradesModel.php**
   - `saveGrades()` method now preserves `grade_status` if not provided in update
   - Added logic to retrieve existing `grade_status` before update

2. **student/ajax/get_grades.php**
   - Fallback path now retrieves `grade_status` from database
   - Returns `grade_status` in response for consistency

## Technical Details

### Database Structure Utilized
- `term_grades` table has UNIQUE KEY on (student_id, class_code)
- UPDATE statements use this key for INSERT ON DUPLICATE KEY UPDATE
- `grade_status` field stores: 'passed', 'failed', 'incomplete', 'dropped'

### Data Flow
1. Faculty clicks status dropdown → updateGradeStatus() in flexible_grading.js
2. Sends POST to save_term_grades.php with action='update_grade_status'
3. updateGradeStatusOnly() executes INSERT ON DUPLICATE KEY UPDATE
4. When faculty refreshes page, loadGradeStatuses() fetches all statuses via getGradeStatuses()
5. Faculty dashboard renders statuses from fresh database query

### Encryption Impact
- `grade_status` is NOT encrypted (only grade values are encrypted)
- Status changes persist whether grades are encrypted or not
- Visibility tracking is separate from status tracking

## Backward Compatibility
✅ All changes are additive
✅ No breaking changes to existing APIs
✅ Existing status-setting functionality preserved
✅ Works with existing encryption/visibility system
