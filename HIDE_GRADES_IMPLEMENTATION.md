# Hide Grades Feature - Complete Implementation Guide

## Overview
The Hide Grades feature allows faculty members to control when students can view their grades. When grades are hidden, students see lock icons and a "Grades not yet released" message instead of their actual grades.

## How It Works

### 1. **Faculty Side - Hide/Show Grades Button**
Located in the Faculty Dashboard (Summary tab), faculty can:
- Click **"Hide Grades"** to encrypt all grades for a class and hide them from students
- Click **"Show Grades"** to decrypt grades and make them visible to students

**File:** `dashboards/faculty_dashboard.php` (lines 646-677)

### 2. **Grade Encryption Process**

When faculty clicks "Hide Grades":
1. Frontend sends POST to `faculty/ajax/encrypt_decrypt_grades.php` with action='encrypt_all'
2. Backend encrypts grade fields (term_grade, midterm_percentage, finals_percentage, term_percentage)
3. Sets `is_encrypted = 1` flag in `grade_term` table
4. Updates `grade_visibility_status` table to 'hidden' for all students in class
5. Returns success message to frontend

**File:** `faculty/ajax/encrypt_decrypt_grades.php` (lines 42-73)

### 3. **Grade Decryption Process**

When faculty clicks "Show Grades":
1. Frontend sends POST to `faculty/ajax/encrypt_decrypt_grades.php` with action='decrypt_all'
2. Backend decrypts all encrypted grade fields
3. Sets `is_encrypted = 0` flag in `grade_term` table
4. Updates `grade_visibility_status` table to 'visible' for all students in class
5. Returns success message to frontend

**File:** `faculty/ajax/encrypt_decrypt_grades.php` (lines 75-110)

### 4. **Student Side - Grade Display**

When a student loads their dashboard:

#### Grade Summary (Card Preview)
1. JavaScript calls `loadGradePreview()` for each class
2. Sends POST to `student/ajax/get_grades.php` with action='get_student_grade_summary'
3. Backend checks TWO conditions:
   - Is `grade_visibility_status` set to 'hidden'? OR
   - Is `is_encrypted` flag = 1?
4. If either is true:
   - Returns `term_grade_hidden = true`
   - Returns 0 values for all grades
   - Returns message "Grades not yet released"
5. Frontend receives response and calls `renderGradePreview()`
6. If `term_grade_hidden = true`, displays lock icons instead of grades

**File:** `student/ajax/get_grades.php` (lines 280-310)

**File:** `student/assets/js/student_dashboard.js` (lines 108-140)

#### Detailed Grade Modal
1. When student clicks "View Detailed Grades" button:
   - First checks if `data-grades-hidden = 'true'` from preview
   - If hidden, shows alert "Grades have not been released yet"
   - Prevents modal from opening
2. If not hidden:
   - Fetches detailed grades with action='get_student_detailed_grades'
   - Backend checks `is_encrypted` flag again
   - If encrypted, returns empty data and `term_grade_hidden = true`
   - Frontend shows lock icon and message

**File:** `student/assets/js/student_dashboard.js` (lines 248-260)

**File:** `student/assets/js/student_dashboard.js` (lines 314-330)

### 5. **Database Tables Involved**

#### `grade_term` table:
- `id` - Primary key
- `student_id` - Student identifier
- `class_code` - Class identifier
- `term_grade` - Overall term grade (encrypted when hidden)
- `midterm_percentage` - Midterm percentage (encrypted when hidden)
- `finals_percentage` - Finals percentage (encrypted when hidden)
- `term_percentage` - Term percentage (encrypted when hidden)
- `grade_status` - Status (passed/failed/incomplete/dropped) - NOT encrypted
- `is_encrypted` - Flag (0=visible, 1=hidden)

#### `grade_visibility_status` table:
- `student_id` - Student identifier
- `class_code` - Class identifier
- `grade_visibility` - Status ('hidden' or 'visible')
- `changed_by` - Faculty ID who made the change
- `visibility_changed_at` - Timestamp of change

### 6. **Visual Indicators**

#### When Grades are Hidden (Student View):
- Grade preview cards show three lock icons (🔐)
- Text says "Grades not yet released"
- "View Detailed Grades" button becomes disabled (opacity 0.5, pointer-events none)
- Hovering shows tooltip "Grades have not been released yet"

#### When Grades are Visible (Student View):
- Grade preview cards show actual grades and percentages
- "View Detailed Grades" button is fully enabled
- Clicking button opens detailed grade breakdown modal

#### Faculty Status Indicator:
- Shows current status: "VISIBLE TO STUDENTS" (green) or "HIDDEN FROM STUDENTS" (yellow)
- Button text updates based on current state
- Icon changes: Eye (👁️) for show, Eye-slash (👁️‍🗨️) for hide

## Security Features

1. **Encryption**: Grade values are encrypted using AES-256 when hidden
2. **Status Isolation**: `grade_status` field is NOT encrypted (only grade values)
3. **Visibility Tracking**: Separate table tracks who hid grades and when
4. **CSRF Protection**: All grade operations require valid CSRF token
5. **Access Control**: Only faculty who teach the class can hide/show grades
6. **Transactional**: Hide/show operations use database transactions for consistency

## Test Cases

### Test 1: Basic Hide/Show
1. Faculty loads dashboard with a class selected
2. In Summary tab, clicks "Hide Grades"
3. Confirms action
4. Button changes to "Show Grades"
5. Student refreshes and sees lock icons
6. Faculty clicks "Show Grades"
7. Student refreshes and sees actual grades

### Test 2: Multiple Classes
1. Faculty hides grades for Class A
2. Faculty shows grades for Class B
3. Student sees:
   - Class A: locked (🔐)
   - Class B: grades visible

### Test 3: Existing Grades
1. Faculty enters grades for students
2. Hides grades
3. Students cannot see them
4. Faculty shows grades
5. Students see their grades

### Test 4: New Grade Entry After Hiding
1. Grades are hidden for a class
2. Faculty enters new grades
3. Grades remain hidden (new grades are also encrypted)
4. Faculty shows grades
5. Both old and new grades are visible

## User Flow Diagrams

### Faculty Flow:
```
Faculty Dashboard
    ↓
Summary Tab
    ↓
Select Class (if not selected, warning)
    ↓
Click "Hide Grades" / "Show Grades" button
    ↓
Confirm Action (alert)
    ↓
POST to encrypt_decrypt_grades.php
    ↓
Encrypt/Decrypt all grades
    ↓
Update grade_visibility_status
    ↓
Show success message
    ↓
Refresh button state
```

### Student Flow:
```
Student Dashboard (Initial Load)
    ↓
loadAllGradePreviews() called
    ↓
For each class:
    Send POST to get_grades.php
        ↓
    Check grade_visibility_status
    Check is_encrypted flag
        ↓
    If hidden: return term_grade_hidden=true
    If visible: return actual grades
        ↓
renderGradePreview() displays result
        ↓
If hidden: show lock icons
If visible: show grades
        ↓
"View Detailed Grades" button state updated
        ↓
If student clicks button:
    If hidden: show alert, don't open modal
    If visible: open modal with detailed breakdown
```

## Troubleshooting

### Issue: Grades still visible after hiding
**Solution:**
1. Check `grade_term` table for `is_encrypted` flag = 1
2. Check `grade_visibility_status` table for `grade_visibility` = 'hidden'
3. Hard refresh browser (Ctrl+Shift+R)
4. Check browser console for errors

### Issue: Students can see grades that should be hidden
**Solution:**
1. Faculty dashboard → Summary → Grade Encryption section
2. Verify status shows "HIDDEN FROM STUDENTS"
3. If showing "VISIBLE", click Hide button again
4. Check encryption_error.log for PHP errors

### Issue: Button doesn't respond
**Solution:**
1. Ensure class is selected (warning will show if not)
2. Check browser console for JavaScript errors
3. Verify CSRF token is set: `window.csrfToken` should have value
4. Check network tab for failed requests

## API Endpoints

### Hide/Show Grades (Faculty)
```
POST /faculty/ajax/encrypt_decrypt_grades.php
Parameters:
  - action: 'encrypt_all' or 'decrypt_all'
  - class_code: class code to hide/show
  - csrf_token: CSRF token

Response:
  {
    "success": true/false,
    "message": "Encrypted/Decrypted X grade row(s)",
    "count": number_of_rows_affected,
    "is_encrypted": true/false
  }
```

### Get Grade Summary (Student)
```
POST /student/ajax/get_grades.php
Parameters:
  - action: 'get_student_grade_summary'
  - class_code: class code
  - csrf_token: CSRF token

Response:
  {
    "success": true,
    "midterm_grade": 0 (if hidden) or grade_value,
    "finals_grade": 0 (if hidden) or grade_value,
    "term_grade": 0 (if hidden) or grade_value,
    "term_grade_hidden": true/false,
    "message": "Grades not yet released" or "Grades have been released"
  }
```

### Get Detailed Grades (Student)
```
POST /student/ajax/get_grades.php
Parameters:
  - action: 'get_student_detailed_grades'
  - class_code: class code
  - csrf_token: CSRF token

Response:
  {
    "success": true,
    "midterm": {...} (empty if hidden),
    "finals": {...} (empty if hidden),
    "term_grade": 0 (if hidden),
    "term_grade_hidden": true/false,
    "message": "Grades are not yet released" (if hidden)
  }
```

## Files Modified/Created

### Core Implementation:
- ✅ `faculty/ajax/encrypt_decrypt_grades.php` - Encryption/decryption logic
- ✅ `student/ajax/get_grades.php` - Grade retrieval with visibility check
- ✅ `dashboards/faculty_dashboard.php` - Hide/Show button UI
- ✅ `student/assets/js/student_dashboard.js` - Grade display logic
- ✅ `student/student_dashboard.php` - Student dashboard view

### Test/Debug:
- ✅ `test_hide_grades.php` - Testing tool for verifying functionality

## Version History

**v1.0** (Current)
- Basic hide/show grades functionality
- Grade encryption/decryption
- Grade visibility status tracking
- Student-side grade display control
- Faculty interface for managing visibility

## Future Enhancements

- [ ] Individual student grade visibility (hide for some, show for others)
- [ ] Scheduled grade release (auto-show at specific date/time)
- [ ] Audit log of grade visibility changes
- [ ] Email notification when grades are released
- [ ] Partial grade visibility (show midterm only, hide finals)
