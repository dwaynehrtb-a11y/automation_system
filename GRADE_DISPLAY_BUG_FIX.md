# Grade Display Bug Fix - Student Shows "Failed" Instead of Correct Grade

## Issue Summary
Student **Ramirez, Ivy A. (2025-276819)** was showing **"Failed"** on the student dashboard despite having:
- **Term Grade:** 1.5 (Passing grade, 1.0-4.0 scale)
- **Term Percentage:** 70.00% (Passing, ≥ 60%)
- **Database Status:** 'passed'

## Root Cause Analysis

The issue was caused by two problems in `student/ajax/get_grades.php`:

### Problem 1: Returning Actual Status for Hidden Grades (Line 324)
When `is_encrypted = 1` (indicating grades are marked as hidden), the code was returning:
```php
'grade_status' => $row['grade_status'] ?? 'pending',
```

This exposed the actual grade status (`'passed'`, `'failed'`, etc.) even when grades should be hidden. If the `grade_status` value was somehow corrupted or returned incorrectly, it would display the wrong status to students.

**Expected Behavior:** When grades are encrypted/hidden, always return `'grade_status' => 'pending'` to the frontend so the student sees "Grades not yet released" with lock icons, not the actual grade.

### Problem 2: Incorrect is_encrypted Flag Values
Many grade records in the `grade_term` table had `is_encrypted = 1` even though the faculty had released the grades. This caused:
- The frontend to receive `term_grade_hidden = true` in the API response
- The frontend displays "Grades not yet released" instead of actual grades
- Or display logic errors resulting in "Failed" status appearing

## Files Modified

### 1. student/ajax/get_grades.php (Line 324)
**Before:**
```php
'grade_status' => $row['grade_status'] ?? 'pending',
```

**After:**
```php
'grade_status' => 'pending',
```

**Reason:** When grades are encrypted/hidden, the status should always be 'pending' to prevent leaking actual grade status information to the frontend. The frontend should only show "Grades not yet released" when `term_grade_hidden = true`.

## Database Fix Required

Records with `is_encrypted = 1` should be updated to `is_encrypted = 0` if the grades have been released.

**To fix the database:**
```sql
UPDATE grade_term 
SET is_encrypted = 0 
WHERE is_encrypted = 1 
  AND grade_status IS NOT NULL 
  AND term_percentage IS NOT NULL;
```

**Or run the provided fix script:**
```bash
php fix_all_encrypted_records.php
```

## How the Grade Display Works

### Data Flow (Correct)
1. Faculty enters grades in flexible grading interface
2. System stores grades with `is_encrypted = 0` by default (visible to students)
3. Student views dashboard
4. Frontend calls `student/ajax/get_grades.php`
5. PHP returns `term_grade_hidden = false` and actual grade values
6. Frontend displays: **Term Grade: 1.5**, **Status: Passed** (in green)

### Data Flow (Bug Scenario)
1. Faculty enters grades in flexible grading interface
2. System somehow marks grades with `is_encrypted = 1` (hidden from students)
3. Student views dashboard
4. Frontend calls `student/ajax/get_grades.php`
5. PHP returns `term_grade_hidden = true` ~~and actual grade status~~ **and 'pending' status** (after fix)
6. Frontend displays: **Grades not yet released** (with lock icons)

## Verification Steps

1. **Check the fix in get_grades.php:**
   - Line 324 should now show: `'grade_status' => 'pending',`

2. **Clear browser cache** (important for JavaScript changes)
   - Hard refresh: `Ctrl+Shift+Delete` or `Cmd+Shift+Delete`

3. **Check database encryption flags:**
   ```sql
   SELECT COUNT(*) as encrypted_records 
   FROM grade_term 
   WHERE is_encrypted = 1;
   ```
   - Should be 0 or very low (only for intentionally hidden grades)

4. **Test with affected student:**
   - Student ID: 2025-276819
   - Class Code: 25_T2_CCPRGG1L_INF223
   - Should see grade `1.5` with `Passed` status (in green)

## Testing the Fix

### For Student Dashboard
1. Login as student 2025-276819
2. Navigate to "My Enrolled Classes"
3. Locate class 25_T2_CCPRGG1L_INF223
4. Verify grade displays as: **1.5** with **Passed** status (green)
5. Verify term percentage shows: **70.00%**

### For Faculty Dashboard  
1. Login as faculty member
2. Navigate to grading system
3. Select class and academic year/term
4. Verify summary shows correct status for all students
5. Verify "Failed" only appears for students with term percentage < 60%

## Additional Notes

- `grade_status` field is **NOT** in the `$encryptedFields` list in `GradesModel.php`, so it should never be encrypted
- The `is_encrypted` flag controls visibility of ALL grades for a student-class combination
- When `is_encrypted = 0`: All grade values are visible to the student
- When `is_encrypted = 1`: All grade values should be hidden (return 0s and show lock icon)
- The fix ensures that hidden grades don't leak actual status information via the API

## Related Files
- `includes/GradesModel.php` - Grade encryption/decryption logic
- `student/ajax/get_grades.php` - Grade API endpoint (FIXED)
- `student/assets/js/student_dashboard.js` - Frontend grade display logic
- `faculty/assets/js/flexible_grading.js` - Faculty grading interface
