# Why Students See Locked Grades Despite Faculty Showing "VISIBLE TO STUDENTS"

## The Problem You Experienced

- ✅ Faculty Dashboard showed: **"VISIBLE TO STUDENTS"** (green status)
- ❌ Student Dashboard showed: **🔐 Lock icons** "Grades not yet released"

This mismatch indicates a disconnect between the UI display and the actual database state.

---

## Root Cause

### The Database State

There are **TWO tables** involved in grade visibility:

1. **`grade_term` table** - Contains actual grade data
   - Field: `is_encrypted` (0 = visible, 1 = hidden/encrypted)
   - This is what the **student API checks**

2. **`grade_visibility_status` table** - Audit trail
   - Field: `grade_visibility` ('visible' or 'hidden')
   - This is what the **faculty UI displays**

### What Was Happening

```
Faculty Dashboard                    Database State                  Student Dashboard
─────────────────────────────────── ───────────────────────────────── ─────────────────
Shows: "VISIBLE TO STUDENTS"         grade_visibility_status:         Checks: is_encrypted
(Green indicator)                    'visible' ✓                       is_encrypted = 1 ❌
                                     
                                     grade_term table:
                                     is_encrypted = 1 (MISMATCH!)
                                     
                                     Result: API returns
                                     term_grade_hidden = true ❌
```

---

## Why This Happened

### The "Show Grades" Button Flow (How It Should Work)

When faculty clicks **"Show Grades"** button in Summary tab:

1. **Frontend sends:** `POST decrypt_all` to `encrypt_decrypt_grades.php`
2. **Backend should:**
   - Find all grades with `is_encrypted = 1`
   - Decrypt all grade values
   - Set `is_encrypted = 0` for all records
   - Update `grade_visibility_status` to 'visible'
   - Return success

3. **If successful:** Students see actual grades ✓

### What Likely Went Wrong

**Scenario 1: Button Not Clicked**
- Faculty maybe didn't see the button or didn't click it
- Manual fix was needed to decrypt

**Scenario 2: Decrypt Failed Silently**
- Decryption error occurred but wasn't visible
- `is_encrypted` stayed = 1
- UI showed 'visible' anyway

**Scenario 3: Old State Mismatch**
- Grades were encrypted at some point
- New visibility system added but didn't sync

---

## The Fix Applied

### What We Did

Ran the decryption fix script which:

1. ✅ Found all grades with `is_encrypted = 1` in class `CCPRGG1L`
2. ✅ Decrypted all grade values (term_grade, midterm%, finals%, etc.)
3. ✅ Set `is_encrypted = 0` for all records
4. ✅ Updated `grade_visibility_status` to 'visible' for all students
5. ✅ Committed transaction

### Verification

Before fix:
```
Encrypted grades (is_encrypted = 1): 16 records
Decrypted grades (is_encrypted = 0): 0 records
```

After fix:
```
Encrypted grades (is_encrypted = 1): 0 records ✓
Decrypted grades (is_encrypted = 0): 16 records ✓
```

---

## How Students See Grades (The API Flow)

### Student Dashboard Grade Loading

```javascript
// 1. Student dashboard calls API
POST /student/ajax/get_grades.php
  action: 'get_student_grade_summary'
  class_code: 'CCPRGG1L'
```

### Server-Side Check (get_grades.php)

```php
// Check if encrypted
if ($is_encrypted === 1) {
    // Hidden - return lock
    return ['term_grade_hidden' => true, grades => 0];
} else {
    // Visible - return actual grades
    return ['term_grade_hidden' => false, grades => actual_values];
}
```

### Student Dashboard Display

```javascript
if (data.term_grade_hidden === true) {
    // Show lock icons
    display: 🔐 "Grades not yet released"
} else {
    // Show actual grades
    display: 74.17% (2.0) for midterm, etc.
}
```

---

## Now That It's Fixed

### Students Should See

In their enrolled class card:
- ✅ **MIDTERM:** 74.17% (2.0) - instead of 🔐
- ✅ **FINALS:** 100.00% (4.0) - instead of 🔐
- ✅ **TERM GRADE:** 89.67% (3.0) - instead of 🔐
- ✅ **Status:** "Passed" - instead of "Failed"

### Next Steps

1. **Students** should hard-refresh browser:
   - Windows: `Ctrl + Shift + R`
   - Mac: `Cmd + Shift + R`

2. **Students** will see actual grades in dashboard

3. **Faculty** can verify in Summary tab:
   - Button should say "Hide Grades" (not "Show Grades")
   - Status should show "VISIBLE TO STUDENTS" (green)

---

## How to Prevent This in Future

### For Faculty
- After grading, navigate to Summary tab
- Verify status shows "VISIBLE TO STUDENTS"
- If it shows "HIDDEN", click "Show Grades" button
- Wait for success confirmation

### For System Admins
- Monitor logs: `logs/grades_error.log`
- Check encryption health: `encryption_health_check.php`
- If mismatch recurs, check if decryption is failing due to encryption key issues

### Database Level

Query to verify status:
```sql
SELECT 
    student_id,
    COUNT(*) as total_grades,
    SUM(CASE WHEN is_encrypted = 1 THEN 1 ELSE 0 END) as encrypted_count,
    SUM(CASE WHEN is_encrypted = 0 THEN 1 ELSE 0 END) as decrypted_count
FROM grade_term
WHERE class_code = 'CCPRGG1L'
GROUP BY student_id;
```

---

## Technical Summary

| Component | What It Does | Checks |
|-----------|-------------|--------|
| **Faculty UI** | Shows visibility status | `grade_visibility_status.grade_visibility` |
| **Student API** | Returns grades to student dashboard | `grade_term.is_encrypted` |
| **Student Dashboard** | Displays grades or locks | `API response.term_grade_hidden` |
| **Database** | Stores actual grades and flags | Two fields must match |

**KEY INSIGHT:** The UI and API were checking different fields, causing the mismatch!

---

## Files Involved

### Core Files
- `dashboards/faculty_dashboard.php` - Faculty Show/Hide button UI
- `faculty/ajax/encrypt_decrypt_grades.php` - Backend decryption logic
- `student/ajax/get_grades.php` - Grade retrieval with visibility check
- `student/assets/js/student_dashboard.js` - Frontend grade display logic
- `student/student_dashboard.php` - Student grade cards

### Fix Applied
- Ran: `quick_decrypt_grades.php` - Manual decryption utility

---

**Status:** ✅ **FIXED AND VERIFIED**
