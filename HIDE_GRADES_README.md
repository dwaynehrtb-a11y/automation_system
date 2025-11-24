# 🔐 Hide Grades Feature - Complete Documentation

## 📋 Table of Contents
1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [How It Works](#how-it-works)
4. [For Faculty](#for-faculty)
5. [For Students](#for-students)
6. [For Administrators](#for-administrators)
7. [Technical Details](#technical-details)
8. [Troubleshooting](#troubleshooting)

---

## Overview

The **Hide Grades** feature allows National University faculty to control when students can access their grades. This is essential for:

- ✅ Managing grade release timing
- ✅ Preventing accidental early access
- ✅ Creating structured grade release schedules
- ✅ Maintaining grade privacy until official release

### Key Benefits

| Feature | Benefit |
|---------|---------|
| **One-Button Control** | Faculty simply click to hide/show all grades |
| **Secure** | Multi-layer protection ensures students cannot bypass restrictions |
| **Immediate** | Changes take effect instantly |
| **Non-Destructive** | Grades are encrypted, not deleted |
| **Audited** | Track who changed visibility and when |
| **User-Friendly** | Clear visual feedback for both faculty and students |

---

## Quick Start

### For Faculty
```
1. Log in → Faculty Dashboard
2. Select Academic Year, Term, Class
3. Go to SUMMARY tab
4. Find "Grade Encryption" section
5. Click "Hide Grades" or "Show Grades"
6. Confirm action
7. Done! ✓
```

### For Students
```
1. Log in → Student Dashboard
2. Look at class cards
3. If grades are hidden: See 🔐 lock icons
4. If grades are visible: See grade values
5. Click "View Detailed Grades" if enabled
```

---

## How It Works

### Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                     FACULTY DASHBOARD                    │
│  [Select Class] → [SUMMARY Tab] → [Hide/Show Button]   │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│           ENCRYPT_DECRYPT_GRADES.PHP                    │
│  - Encrypt all grade values                             │
│  - Update is_encrypted flag in grade_term              │
│  - Update grade_visibility_status to 'hidden'          │
│  - Create audit trail                                   │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│           DATABASE ENCRYPTION COMPLETE                  │
│  - All grades now encrypted with AES-256               │
│  - Visibility status recorded                           │
│  - Change logged with timestamp                         │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│              STUDENT REFRESHES DASHBOARD                │
│  GET_GRADES.PHP checks:                                │
│  1. Is grade_visibility_status = 'hidden'? → Yes ✓      │
│  2. Is is_encrypted = 1? → Yes ✓                        │
│  Returns: term_grade_hidden = true                      │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│        STUDENT DASHBOARD RENDERS PREVIEW                │
│  - Checks: data.term_grade_hidden = true               │
│  - Shows: 🔐 lock icon in each grade section           │
│  - Shows: "Grades not yet released"                    │
│  - Disables: "View Detailed Grades" button             │
└─────────────────────────────────────────────────────────┘
```

### Data Flow - Hiding Grades

1. **Faculty Action**
   - Clicks "Hide Grades" button
   - Confirms action dialog

2. **Server Processing** (`encrypt_decrypt_grades.php`)
   ```php
   FOR EACH student in class:
       - Encrypt: term_grade, midterm_percentage, finals_percentage, term_percentage
       - SET is_encrypted = 1 in grade_term
       - INSERT/UPDATE grade_visibility_status = 'hidden'
       - Record faculty_id and timestamp
   COMMIT all changes (transaction)
   ```

3. **Database State**
   ```
   grade_term table:
   - is_encrypted = 1 (flag set)
   - term_grade = "ae4f2d..." (encrypted)
   - grade_status = "passed" (NOT encrypted)
   
   grade_visibility_status table:
   - student_id = "S001"
   - class_code = "CCPRGG101-1"
   - grade_visibility = "hidden"
   - changed_by = 42 (faculty_id)
   - visibility_changed_at = "2024-11-25 14:30:00"
   ```

4. **Student View**
   - Requests grades from `get_grades.php`
   - API checks both flags (encrypted AND visibility)
   - Returns: `term_grade_hidden = true` with 0 values
   - Frontend displays lock icons

---

## For Faculty

### Accessing the Feature

**Location:** Faculty Dashboard → SUMMARY Tab → Grade Encryption Section

```
┌─────────────────────────────────────────────┐
│           GRADE ENCRYPTION SECTION          │
│                                             │
│  Status: VISIBLE TO STUDENTS (green) ✓     │
│                                             │
│  ⚠️ CAUTION: This will make ALL grades    │
│     for this class VISIBLE to students    │
│                                             │
│  [👁️‍🗨️ Show Grades] [Manage] [More options] │
└─────────────────────────────────────────────┘
```

### Step-by-Step Guide

#### **Step 1: Navigate to Dashboard**
- Log in with faculty credentials
- Click "Dashboard" in menu
- Or visit: `dashboards/faculty_dashboard.php`

#### **Step 2: Select Your Class**
- In "GRADING SYSTEM" section
- Choose **Academic Year** from dropdown
- Choose **Term** from dropdown
- Choose **Class** from dropdown
- Wait for grading interface to load

#### **Step 3: Go to Summary Tab**
- Click "SUMMARY" tab at top
- Wait for grade summary to load
- Scroll down to find "Grade Encryption Section"

#### **Step 4: Check Current Status**
Look for status indicator:
- 🟢 **VISIBLE TO STUDENTS** (green) → Grades are showing
- 🟡 **HIDDEN FROM STUDENTS** (yellow) → Grades are hidden

#### **Step 5: Toggle Visibility**
- If want to hide: Click **Hide Grades** button
- If want to show: Click **Show Grades** button

#### **Step 6: Confirm Action**
A dialog appears asking to confirm:

**To Hide:**
```
Title: Hide Grades from Students?

⚠️ IMPORTANT:
This will make ALL grades for this class 
HIDDEN from your students.

Students will NOT be able to see their grades 
until you show them again.

[Yes, Hide Grades] [Cancel]
```

**To Show:**
```
Title: Show Grades to Students?

✓ This will make ALL grades for this class 
VISIBLE to your students immediately.

Use this only when ready to release grades 
(e.g., after finals).

[Yes, Show Grades] [Cancel]
```

#### **Step 7: Process Completion**
- Loading indicator appears
- Button becomes inactive
- Processing message displayed
- After ~2-5 seconds:
  - Success message appears
  - Status updates
  - Button becomes active again

### Important Faculty Notes

✅ **What happens when you hide grades:**
- All student grade entries are encrypted with AES-256
- `is_encrypted` flag set to 1 in database
- Visibility status recorded as 'hidden'
- Grade status (Passed/Failed/INC) remains visible to you
- You can still view grades in dashboard
- New grades entered will also be encrypted

❌ **What students CANNOT do:**
- See grade numbers or percentages
- Access grades through direct API calls
- View detailed grade breakdown
- Manipulate JavaScript to show hidden grades

⚠️ **Important considerations:**
- All students in class are affected (bulk operation)
- Cannot selectively hide for some students only
- Action is immediate (no scheduling)
- Reversible - just click "Show Grades" to release

### Best Practices for Faculty

1. **Plan Your Release Schedule**
   - Hide grades immediately after entering
   - Review for accuracy
   - Set specific time/date to show
   - Communicate with students

2. **Before Hiding**
   - Ensure all grades are entered
   - Verify calculations are correct
   - Save any reports needed

3. **Before Showing**
   - Double-check final grades
   - Verify grade status (passed/failed)
   - Prepare for student questions

4. **After Showing**
   - Monitor for student grade questions
   - Be available for explanations
   - Document grade release date

### Verification Steps

After hiding/showing, verify by:

1. **Check Dashboard Button**
   - Status should change immediately
   - Color should match new state

2. **Have Student Verify**
   - Ask a student to refresh dashboard
   - Confirm they see lock icons (if hidden)
   - Confirm they see grades (if visible)

3. **Check Database (Admin Only)**
   - Visit `test_hide_grades.php`
   - Look for your class in the list
   - Verify visibility status

---

## For Students

### Where to Find Your Grades

**Location:** Student Dashboard → My Enrolled Classes

```
┌────────────────────────────────────────────────────┐
│         MY ENROLLED CLASSES                       │
│                                                   │
│  ┌──────────────────────────────────────────────┐ │
│  │ CCPRGG101                      3 units      │ │
│  │                                             │ │
│  │ Faculty: Prof. Smith                       │ │
│  │ Section: 1                                 │ │
│  │ Room: Lab 5B                               │ │
│  │                                             │ │
│  │ ┌───────────┬───────────┬───────────┐      │ │
│  │ │ MIDTERM   │ FINALS    │ TERM GRD  │      │ │
│  │ │ (40%)     │ (60%)     │           │      │ │
│  │ │           │           │           │      │ │
│  │ │ 85.50%    │ 92.25%    │ 89.67%    │      │ │
│  │ │ 3.5       │ 3.8       │ 3.7       │      │ │
│  │ └───────────┴───────────┴───────────┘      │ │
│  │                                             │ │
│  │ [View Detailed Grades]                     │ │
│  └──────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────┘
```

### When Grades Are Hidden

You'll see:
```
┌────────────────────────────────────────────────────┐
│         MY ENROLLED CLASSES                       │
│                                                   │
│  ┌──────────────────────────────────────────────┐ │
│  │ CCPRGG101                      3 units      │ │
│  │                                             │ │
│  │ ┌───────────┬───────────┬───────────┐      │ │
│  │ │ 🔐        │ 🔐        │ 🔐        │      │ │
│  │ │ LOCKED    │ LOCKED    │ LOCKED    │      │ │
│  │ │           │           │           │      │ │
│  │ │ Grades    │ Grades    │ Grades    │      │ │
│  │ │ not yet   │ not yet   │ not yet   │      │ │
│  │ │ released  │ released  │ released  │      │ │
│  │ └───────────┴───────────┴───────────┘      │ │
│  │                                             │ │
│  │ [View Detailed Grades] (disabled/grayed)   │ │
│  └──────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────┘
```

### Interpreting the Grade Display

**When Visible - You'll see:**
- **MIDTERM (40%)** - Your midterm grade and percentage
- **FINALS (60%)** - Your finals grade and percentage
- **TERM GRADE** - Final grade calculation and status

**Color Coding:**
- 🟢 **Green background** - Passing grade
- 🔴 **Red background** - Failing grade
- 🟠 **Orange background** - Incomplete
- 🔴 **Gray background** - Dropped
- 🟡 **Gold/Yellow** - Numeric grade

### What If Grades Are Still Locked After Release?

**Try these steps:**

1. **Refresh Your Browser**
   - Press F5 (normal refresh)
   - Should update within 10 seconds
   - Auto-refresh happens every 10 seconds

2. **Hard Refresh (Clear Cache)**
   - Press Ctrl+Shift+R (Windows)
   - Press Cmd+Shift+R (Mac)
   - Forces browser to get latest data

3. **Log Out and Back In**
   - Click user menu → Logout
   - Log in again
   - Reload dashboard

4. **Check with Instructor**
   - Confirm grades have been released
   - May be system delay (up to 1 minute)
   - Check email for release notification

### Viewing Detailed Grades

When grades are visible:

1. **Click "View Detailed Grades"** button
2. Modal opens showing:
   - Individual component scores
   - Component weights/percentages
   - Midterm breakdown
   - Finals breakdown
   - Final grade calculation
   - Grade status

3. **Understand the Breakdown**
   - Each component is weighted
   - Total = weighted average
   - Grade status shows: Passed/Failed/INC
   - Can see which areas need improvement

---

## For Administrators

### System Verification

#### Quick Verification
Visit: `verify_hide_grades.php`

Shows:
- ✓ Database table structure OK
- ✓ Required columns present
- ✓ Encryption status of all grades
- ✓ Sample grade records with visibility

#### Interactive Testing
Visit: `test_hide_grades.php`

Allows:
- View all classes and students
- Check visibility status per student
- Manually hide/show grades by class
- See system-wide summary
- Test bulk operations

### Database Queries

#### Check Encryption Status
```sql
-- See how many grades are encrypted vs decrypted
SELECT 
    is_encrypted,
    COUNT(*) as count
FROM grade_term
GROUP BY is_encrypted;

-- Results:
-- is_encrypted | count
-- 0            | 150    (visible)
-- 1            | 45     (hidden)
```

#### Check Visibility Status
```sql
-- See visibility distribution
SELECT 
    grade_visibility,
    COUNT(*) as count
FROM grade_visibility_status
GROUP BY grade_visibility;

-- Results:
-- grade_visibility | count
-- hidden           | 45
-- visible          | 105
```

#### Track Changes
```sql
-- Who changed what and when?
SELECT 
    student_id,
    class_code,
    grade_visibility,
    changed_by,
    visibility_changed_at
FROM grade_visibility_status
ORDER BY visibility_changed_at DESC
LIMIT 20;
```

#### Check Specific Class
```sql
-- All visibility info for a class
SELECT 
    gvs.student_id,
    gvs.grade_visibility,
    gt.is_encrypted,
    u.name as changed_by_name,
    gvs.visibility_changed_at
FROM grade_visibility_status gvs
LEFT JOIN grade_term gt ON gvs.student_id = gt.student_id AND gvs.class_code = gt.class_code
LEFT JOIN users u ON gvs.changed_by = u.id
WHERE gvs.class_code = 'CCPRGG101-1'
ORDER BY gvs.student_id;
```

### Common Administrative Tasks

#### Hide Grades for All Classes in Term
```sql
-- First, identify all classes in term
SELECT DISTINCT class_code 
FROM class 
WHERE academic_year = '2024-2025' 
AND term = 'Midterm';

-- Then visit test_hide_grades.php and hide each individually
-- (Bulk operation via script would require caution)
```

#### Audit Trail - Last 30 Days
```sql
SELECT 
    gvs.*,
    u.name as faculty_name,
    c.course_code,
    COUNT(DISTINCT gvs.student_id) as students_affected
FROM grade_visibility_status gvs
LEFT JOIN users u ON gvs.changed_by = u.id
LEFT JOIN class c ON gvs.class_code = c.class_code
WHERE gvs.visibility_changed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY gvs.class_code, gvs.grade_visibility
ORDER BY gvs.visibility_changed_at DESC;
```

#### Resolve Stuck Hidden Status
```sql
-- If grades are stuck hidden and need emergency release
-- (use with caution - only if absolutely necessary)

UPDATE grade_term 
SET is_encrypted = 0 
WHERE class_code = 'CCPRGG101-1' AND is_encrypted = 1;

UPDATE grade_visibility_status 
SET grade_visibility = 'visible' 
WHERE class_code = 'CCPRGG101-1' AND grade_visibility = 'hidden';
```

---

## Technical Details

### Database Structure

#### `grade_term` Table
```
Column                  Type        Purpose
─────────────────────────────────────────────────
id                      INT         Primary key
student_id              VARCHAR(50) Student identifier
class_code              VARCHAR(50) Class identifier
term_grade              LONGTEXT    Encrypted grade value
midterm_percentage      LONGTEXT    Encrypted percentage
finals_percentage       LONGTEXT    Encrypted percentage
term_percentage         LONGTEXT    Encrypted percentage
grade_status            VARCHAR(50) Status (NOT encrypted)
is_encrypted            TINYINT(1)  Flag: 0=visible, 1=hidden
created_at              TIMESTAMP   Record creation
updated_at              TIMESTAMP   Last update
```

#### `grade_visibility_status` Table
```
Column                  Type        Purpose
─────────────────────────────────────────────────
student_id              VARCHAR(50) Student identifier
class_code              VARCHAR(50) Class identifier
grade_visibility        VARCHAR(20) 'hidden' or 'visible'
changed_by              INT         Faculty ID who made change
visibility_changed_at   TIMESTAMP   When change was made
```

### Encryption Method

**Algorithm:** AES-256 (Advanced Encryption Standard)
**Mode:** ECB (Electronic Codebook)
**Key Storage:** `config/encryption.php`
**Key Size:** 256-bit

**Process:**
```php
// Encrypt
$encrypted = Encryption::encrypt($grade_value);

// Decrypt (automatic when student requests grades)
$decrypted = Encryption::decrypt($encrypted_value);
```

### API Endpoints

#### Hide Grades (Faculty)
```
POST /faculty/ajax/encrypt_decrypt_grades.php

Parameters:
  action: 'encrypt_all'
  class_code: 'CCPRGG101-1'
  csrf_token: '...'

Response:
  {
    "success": true,
    "message": "Encrypted 45 grade row(s).",
    "count": 45,
    "errors": []
  }
```

#### Show Grades (Faculty)
```
POST /faculty/ajax/encrypt_decrypt_grades.php

Parameters:
  action: 'decrypt_all'
  class_code: 'CCPRGG101-1'
  csrf_token: '...'

Response:
  {
    "success": true,
    "message": "Decrypted 45 grade row(s).",
    "count": 45,
    "errors": []
  }
```

#### Get Grade Summary (Student)
```
POST /student/ajax/get_grades.php

Parameters:
  action: 'get_student_grade_summary'
  class_code: 'CCPRGG101-1'
  csrf_token: '...'

Response:
  {
    "success": true,
    "midterm_grade": 3.5,
    "finals_grade": 3.8,
    "term_grade": 3.7,
    "term_grade_hidden": false,
    "grade_status": "passed",
    "message": "Grades have been released"
  }
  
  OR if hidden:
  {
    "success": true,
    "midterm_grade": 0,
    "finals_grade": 0,
    "term_grade": 0,
    "term_grade_hidden": true,
    "grade_status": "pending",
    "message": "Grades not yet released"
  }
```

### Security Implementation

**Layer 1: Application Level**
- CSRF token validation on all requests
- Role-based access control (faculty only)
- Faculty ownership verification

**Layer 2: Data Level**
- Grade values encrypted with AES-256
- Only grade values encrypted (not status)
- Encryption flags in database

**Layer 3: API Level**
- Server always checks encryption flag
- Server always checks visibility status
- Returns 0 values if hidden (not actual data)

**Layer 4: Frontend Level**
- JavaScript checks `term_grade_hidden` flag
- Buttons disabled if hidden
- Lock icons displayed if hidden
- Cannot be bypassed by client-side code

---

## Troubleshooting

### Issue: Button doesn't respond
**Solution:**
```
1. Ensure class is selected (warning shows if not)
2. Refresh page (F5)
3. Check browser console (F12) for errors
4. Try hard refresh (Ctrl+Shift+R)
```

### Issue: Grades still visible after hiding
**Solution:**
```
1. Student does hard refresh (Ctrl+Shift+R)
2. Verify in test_hide_grades.php that flag is set
3. Check database: SELECT is_encrypted FROM grade_term...
4. Check database: SELECT grade_visibility FROM grade_visibility_status...
5. If both show 'hidden', browser cache issue - student needs hard refresh
```

### Issue: Cannot hide/show button greyed out
**Solution:**
```
1. Select a class first (must be selected)
2. Wait for grading interface to fully load
3. Go to SUMMARY tab
4. Scroll to find Grade Encryption section
5. If still not working, try browser refresh
```

### Issue: Action times out or shows error
**Solution:**
```
1. Check server error logs
2. Verify database connection is working
3. Check if large number of students (>1000) - may take longer
4. Try again - transient network issue
5. Contact administrator if persists
```

### Issue: Students can still see grades despite hiding
**This should not happen. If it does:**
```
1. Check is_encrypted flag: SELECT is_encrypted FROM grade_term WHERE class_code='...'
2. Check visibility: SELECT grade_visibility FROM grade_visibility_status...
3. If flags are set correctly but still visible:
   a. Clear browser cache completely
   b. Hard refresh (Ctrl+Shift+R)
   c. Log out and back in
   d. Try different browser (check if browser-specific)
4. If still not working, check for JavaScript errors (F12 console)
5. Contact system administrator
```

---

## FAQs

**Q: Can I hide grades for specific students only?**
A: Not currently. Feature hides/shows grades for entire class. Per-student visibility is planned for future.

**Q: What happens if I enter new grades after hiding?**
A: New grades are automatically encrypted and hidden along with existing grades.

**Q: Can students override hiding by accessing the database?**
A: No. Students don't have database access, and grades are encrypted even if they could.

**Q: Is there an audit log of visibility changes?**
A: Yes! Check `grade_visibility_status` table - `changed_by` and `visibility_changed_at` fields.

**Q: Can I schedule automatic grade release?**
A: Not currently. Must manually click button. Scheduled release is planned feature.

**Q: Do students get notified when grades are released?**
A: Not automatically. Consider sending email or announcement separately.

**Q: How long does hide/show operation take?**
A: Usually 2-5 seconds. Larger classes with many students may take 10-30 seconds.

**Q: What if operation fails halfway?**
A: Uses database transactions - either all grades hidden or all visible. No partial states.

**Q: Can faculty see their own grades while hidden?**
A: Yes, faculty can always see and manage grades. Hiding only affects students.

---

## Quick Links

- **Test Verification:** `verify_hide_grades.php`
- **Interactive Testing:** `test_hide_grades.php`
- **Full Technical Docs:** `HIDE_GRADES_IMPLEMENTATION.md`
- **User Quick Reference:** `HIDE_GRADES_QUICK_REFERENCE.md`
- **Feature Summary:** `HIDE_GRADES_SUMMARY.md`

---

**Version:** 1.0  
**Last Updated:** November 25, 2024  
**Status:** ✅ Production Ready
