# Hide Grades Feature - Quick Reference Guide

## For Faculty

### How to Hide Grades from Students

1. **Go to Faculty Dashboard**
   - Click on your user menu (top right)
   - Select "Dashboard" or navigate to `faculty/faculty_dashboard.php`

2. **Select Your Class**
   - Go to "GRADING SYSTEM" section
   - Use the dropdown filters to select:
     - Academic Year
     - Term
     - Class

3. **Hide the Grades**
   - Click on the "SUMMARY" tab
   - Look for the "Grade Encryption" section (blue box with lock icon)
   - Status shows: "VISIBLE TO STUDENTS" (green) or "HIDDEN FROM STUDENTS" (yellow)
   - Click the button with eye icon to toggle:
     - "Hide Grades" button → Makes grades hidden
     - "Show Grades" button → Makes grades visible

4. **Confirm Action**
   - A warning dialog will appear
   - Read the message carefully
   - Click "Yes, Hide Grades" or "Yes, Show Grades" to confirm
   - Or click "Cancel" to abort

5. **Verify**
   - After action completes, status will update
   - All students in that class will immediately see or not see grades
   - You can ask a student to refresh their page to verify

### Important Notes for Faculty

- ✓ When you hide grades, ALL grades for the class are hidden from ALL students
- ✓ Each class has independent visibility control
- ✓ Grade status (Passed/Failed/INC/Dropped) remains visible to you as faculty
- ✓ Students cannot bypass the visibility restrictions
- ⚠️ Before hiding grades, inform your students if they should not have access
- ⚠️ No permanent record is deleted - grades are encrypted, not removed

---

## For Students

### How to View Your Grades

1. **Go to Student Dashboard**
   - Log in to your account
   - Click on "Dashboard" or navigate to `student/student_dashboard.php`

2. **Find Your Classes**
   - Scroll down to "My Enrolled Classes" section
   - You'll see a card for each class you're enrolled in

3. **Check Grade Status**
   - Each class card shows grade preview with three sections:
     - **MIDTERM** (40%)
     - **FINALS** (60%)
     - **TERM GRADE**

4. **If Grades Are Hidden**
   - You'll see 🔐 lock icons in all three sections
   - Text will say "Grades not yet released"
   - The "View Detailed Grades" button will be grayed out (disabled)
   - Your instructor has not yet released your grades

5. **If Grades Are Visible**
   - You'll see your actual grade numbers
   - Percentages will be displayed
   - The "View Detailed Grades" button is enabled (clickable)
   - Click it to see detailed breakdown of components

6. **View Detailed Breakdown**
   - Click "View Detailed Grades" button (if enabled)
   - A modal will open showing:
     - Midterm components and scores
     - Finals components and scores
     - Final grade calculation
     - Grade status (Passed/Failed/INC)

### What If Grades Are Still Locked?

- Wait a few seconds then refresh your browser (F5 or Ctrl+R)
- Try a hard refresh (Ctrl+Shift+R) to clear cache
- Contact your instructor to confirm grades have been released
- Check your email - instructor may have sent a notification

---

## Testing the Feature

### For System Administrators

#### Test Script 1: Quick Status Check
Visit: `verify_hide_grades.php`
- Shows database table status
- Displays encryption/visibility statistics
- Lists sample grade records with their visibility status

#### Test Script 2: Interactive Testing
Visit: `test_hide_grades.php`
- Shows all classes and student enrollment
- Displays current visibility status for each student
- Provides buttons to hide/show grades by class
- Shows system-wide summary

#### Manual Testing Steps

**Scenario A: Hide and Show Grades**
1. As faculty: Log in and select a class
2. Click "Hide Grades" and confirm
3. As student: Refresh dashboard, verify lock icons appear
4. As faculty: Click "Show Grades" and confirm
5. As student: Refresh dashboard, verify grades are visible

**Scenario B: Multiple Classes**
1. Select Class A and hide grades
2. Select Class B and leave grades visible
3. As student: Verify Class A is locked, Class B shows grades
4. Reverse the visibility
5. As student: Verify changes took effect

**Scenario C: New Grades After Hiding**
1. Hide grades for a class
2. As faculty: Enter new grades in grading system
3. As student: Refresh and verify new grades are still hidden
4. As faculty: Show grades
5. As student: Refresh and verify all grades (old and new) are visible

---

## Database Records

### Grade Visibility Status
When grades are hidden, two things happen:

1. **grade_term table**
   - `is_encrypted` field set to `1`
   - Grade values encrypted using AES-256

2. **grade_visibility_status table**
   - New record created for each student
   - `grade_visibility` field set to `'hidden'` or `'visible'`
   - `changed_by` field records which faculty made the change
   - `visibility_changed_at` timestamp recorded

### How Students Are Protected

1. When student requests grades via API:
   - Server checks `grade_visibility_status` table first
   - Then checks `is_encrypted` flag
   - If either indicates hidden, returns `term_grade_hidden = true`
   - Returns 0 values for all grades

2. JavaScript on student dashboard:
   - Checks returned `term_grade_hidden` flag
   - If true, displays lock icons instead of grades
   - If true, disables "View Detailed Grades" button

3. Students cannot:
   - Access grades through API directly (server-side check)
   - See grades in page source (values are 0)
   - Manipulate JavaScript to show grades (server controls visibility)

---

## Common Questions

**Q: Can I hide grades for just one student?**
A: Currently, the feature hides grades for all students in a class. Per-student visibility is a planned enhancement.

**Q: What happens to grades I entered before hiding?**
A: They are encrypted and hidden. When you show grades, they are decrypted and visible again.

**Q: Can students see their grade status (Passed/Failed/INC)?**
A: No, the entire grade preview is hidden with lock icons when grades are not released.

**Q: Is there an audit trail of who changed visibility?**
A: Yes, the `changed_by` field in `grade_visibility_status` records which faculty member made each change.

**Q: Can I schedule grades to be automatically released?**
A: This is a planned enhancement. Currently you must manually click the Show Grades button.

**Q: What if I accidentally hide grades?**
A: Simply click "Show Grades" to immediately release them again.

**Q: Do students get notified when grades are released?**
A: Email notifications can be configured. Currently students must check their dashboard.

---

## Troubleshooting Guide

### Problem: Button doesn't respond when clicked
- **Solution 1:** Make sure you have selected a class first (warning appears if not)
- **Solution 2:** Check that class actually has enrolled students with grades
- **Solution 3:** Hard refresh your browser (Ctrl+Shift+R)
- **Solution 4:** Check browser console (F12) for JavaScript errors

### Problem: Students still see grades after hiding
- **Solution 1:** Ask student to hard refresh (Ctrl+Shift+R)
- **Solution 2:** Clear browser cache and reload
- **Solution 3:** Check `verify_hide_grades.php` to confirm grades are actually encrypted
- **Solution 4:** Try hiding again - use "Hide Grades" button

### Problem: Cannot find the hide/show button
- **Check:** Are you in the SUMMARY tab?
- **Check:** Do you have a class selected?
- **Check:** Look for blue box labeled "Grade Encryption Section"
- **Check:** Status should show next to button

### Problem: Error message appears when hiding
- **Check:** Confirm you have permission to modify this class's grades
- **Check:** Verify class has enrolled students
- **Check:** Check browser console for error details
- **Contact:** Contact system administrator if error persists

---

## Feature Status

✅ **Implemented and Working**
- Hide all grades for a class
- Show all grades for a class
- Student-side protection (cannot bypass)
- Database encryption of grade values
- Audit trail of visibility changes

🔄 **Planned Enhancements**
- Per-student visibility control
- Scheduled auto-release
- Email notifications
- Visibility dashboard/history
- Partial visibility (e.g., midterm only)

---

## Support

For technical issues or questions:
1. Visit `test_hide_grades.php` to verify system status
2. Visit `verify_hide_grades.php` to check database
3. Contact system administrator
4. Check error logs in `logs/` directory

---

**Last Updated:** November 2024
**Version:** 1.0
