# Fix for Missing AJAX Files and Database Errors

## Issues Fixed

### 1. Missing AJAX Validation Files (404 Errors)
Created the following missing files in `/ajax/` directory:

- ✅ `check_student_id.php` - Validates if student ID already exists
- ✅ `check_student_email.php` - Validates if email already exists  
- ✅ `check_course_code.php` - Validates if course code already exists

These files are used for real-time validation when adding/editing students and subjects.

### 2. Database Error: Field 'co_id' doesn't have a default value

**Problem:** The `course_outcomes` table's `co_id` field is not set to AUTO_INCREMENT on Hostinger.

**Solution:** Run the SQL migration file `fix_co_id_auto_increment.sql` on your Hostinger database.

## How to Deploy to Hostinger

### Step 1: Upload AJAX Files
Upload these 3 new files to your Hostinger `/ajax/` directory:
- `ajax/check_student_id.php`
- `ajax/check_student_email.php`
- `ajax/check_course_code.php`

### Step 2: Fix Database Schema
1. Log into your Hostinger control panel
2. Go to phpMyAdmin
3. Select your database
4. Click the "SQL" tab
5. Copy and paste this SQL command:

```sql
ALTER TABLE `course_outcomes` 
MODIFY COLUMN `co_id` INT NOT NULL AUTO_INCREMENT;
```

6. Click "Go" to execute

### Step 3: Verify the Fix
After uploading and running SQL:

1. **Test Student Validation:**
   - Go to Admin Dashboard > Manage Students
   - Try adding a student
   - Type a student ID - it should validate in real-time
   - Type an email - it should validate in real-time

2. **Test Course Code Validation:**
   - Go to Admin Dashboard > Manage Subjects
   - Try adding a subject
   - Type a course code - it should validate in real-time

3. **Test Subject Creation:**
   - Try creating a new subject with course outcomes
   - Should no longer show "Field 'co_id' doesn't have a default value" error

## What Each File Does

### check_student_id.php
- Checks if a student ID already exists in the database
- Returns JSON: `{"exists": true}` or `{"exists": false}`
- Used for real-time validation while typing

### check_student_email.php
- Checks if an email already exists in the database
- Returns JSON: `{"exists": true}` or `{"exists": false}`
- Used for real-time validation while typing

### check_course_code.php
- Checks if a course code already exists in the database
- Returns JSON: `{"exists": true}` or `{"exists": false}`
- Used for real-time validation while typing

## Testing Commands
After deployment, you can test the endpoints directly:

```
https://your-domain.com/ajax/check_student_id.php?id=2024-123456
https://your-domain.com/ajax/check_student_email.php?email=test@example.com
https://your-domain.com/ajax/check_course_code.php?code=CCPRGG1L
```

Each should return a JSON response like: `{"exists":false}`

## Status: ✅ READY FOR DEPLOYMENT

All files have been created and tested locally. Ready to upload to Hostinger.
