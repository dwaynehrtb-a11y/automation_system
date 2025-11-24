# Quick Fix Guide - Hostinger Deployment

## 🚨 Errors Fixed

1. ❌ `Failed to load resource: 404` (check_student_id.php, check_student_email.php, check_course_code.php)
2. ❌ `Field 'co_id' doesn't have a default value`
3. ❌ `Field 'mapping_id' doesn't have a default value`

## ✅ Solution (3 Steps)

### Step 1: Upload AJAX Files to Hostinger
Upload these 3 files to `/public_html/ajax/` (or your ajax directory):
- ✅ `ajax/check_student_id.php`
- ✅ `ajax/check_student_email.php`
- ✅ `ajax/check_course_code.php`

### Step 2: Run SQL Fix in phpMyAdmin
**First, run this to check current structure:**
```sql
SHOW CREATE TABLE `course_outcomes`;
SHOW CREATE TABLE `co_so_mapping`;
```

**Then, run this fix (since they're already PRIMARY KEYS):**
```sql
ALTER TABLE `course_outcomes` 
MODIFY COLUMN `co_id` INT NOT NULL AUTO_INCREMENT;

ALTER TABLE `co_so_mapping` 
MODIFY COLUMN `mapping_id` INT NOT NULL AUTO_INCREMENT;
```

**Note:** If you get "Multiple primary key defined" error, the tables already have the keys set correctly. The issue might be elsewhere.

### Step 3: Test
- Add a student → Real-time validation should work
- Add a subject with course outcomes → Should save without errors

## 📁 Files Created
- `ajax/check_student_id.php` - Student ID validation
- `ajax/check_student_email.php` - Email validation
- `ajax/check_course_code.php` - Course code validation
- `fix_co_id_auto_increment.sql` - Database schema fix
- `check_database_schema.sql` - Verify database structure

## 🎉 Done!
All errors should be resolved after completing steps 1 & 2.
