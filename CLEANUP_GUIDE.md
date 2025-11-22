# Workspace Cleanup Guide

## Files & Folders to DELETE (SAFE TO REMOVE)

### ✅ Test/Debug Files (One-Time Use Only)
These were created for testing/verification during development. **No longer needed:**

**Root Level:**
- `check_schema.php` - Schema verification (one-time setup)
- `migrate_schema.php` - Database migration (already executed)
- `setup_encryption.php` - Encryption setup (already executed)
- `test_car_data.php` - CAR data test (temporary)
- `test_decrypt.php` - Decryption test (temporary)
- `test_outcomes.php` - Outcomes test (temporary)
- `verify_calculations.php` - Grade calculation test (temporary)

**AJAX Folder (`ajax/`):**
- `check_course_code.php` - Duplicate check logic
- `check_php.php` - PHP syntax test
- `check_student_email.php` - Duplicate check logic
- `check_student_id.php` - Duplicate check logic
- `test_class.php` - Class creation test
- `test_insert.php` - Data insertion test
- `test_phpspreadsheet.php` - Excel library test
- `test_section.php` - Section creation test

**Config Folder (`config/`):**
- `migrate_encryption.php` - Encryption migration (already executed)

**Faculty AJAX Folder (`faculty/ajax/`):**
- `test_car.php` - CAR generation test
- `debug_car.php` - CAR debugging
- `generate_car_test.php` - CAR test variant

**Test Folder (`test/`):**
- `test_email.php` - Email configuration test
- `test_email_process.php` - Email process test

---

### ✅ Documentation Files (For Reference Only)
These are documentation of changes. **Optional to keep:**

**Root Level:**
- `API_ENDPOINTS_UPDATED.md` - API update log (informational)
- `API_UPDATE_COMPARISON.md` - Comparison log (informational)
- `ENCRYPTION_BUGS_FIXED.md` - Bug fixes log (informational)
- `ENCRYPTION_USAGE_EXAMPLES.md` - Usage examples (informational)
- `ENCRYPTION_SETUP.md` - Setup documentation (keep for reference)
- `NEXT_STEPS.md` - Development roadmap (informational)
- `CLEANUP_GUIDE.md` - This file

---

### ✅ Temporary Files
- `temp/` - Folder with old CAR generation test files (.docx files)
- `ajax.zip` - Backup/archive file

---

## FILES TO KEEP (Production Code)

### Core System
- `config/` - Database, session, encryption config (KEEP ALL except `migrate_encryption.php`)
- `includes/` - StudentModel, GradesModel (KEEP ALL)
- `vendor/` - Composer dependencies (KEEP)

### Application
- `admin/` - Admin dashboard (KEEP)
- `auth/` - Login/authentication (KEEP)
- `dashboards/` - Faculty & student dashboards (KEEP)
- `faculty/` - Faculty portal (KEEP except test files)
- `student/` - Student portal (KEEP)
- `reports/` - Report generation (KEEP)
- `api/` - API endpoints (KEEP)
- `cron/` - Scheduled tasks (KEEP)
- `ajax/` - AJAX handlers (KEEP except test files)
- `assets/` - CSS, images, JS (KEEP)

### Root Files
- `.env` - Environment variables (KEEP)
- `.git/` - Git repository (KEEP)
- `composer.json` - Dependencies (KEEP)
- `composer.lock` - Dependency lock (KEEP)
- `admin_dashboard.php` - Admin interface (KEEP)
- `dbtest.php` - Database connection test (OPTIONAL - can delete if not used)

---

## Cleanup Commands

### Option 1: Delete Specific Test Files (Recommended)
```powershell
# Delete root-level test files
Remove-Item -Path C:\xampp\htdocs\automation_system\check_schema.php
Remove-Item -Path C:\xampp\htdocs\automation_system\migrate_schema.php
Remove-Item -Path C:\xampp\htdocs\automation_system\setup_encryption.php
Remove-Item -Path C:\xampp\htdocs\automation_system\test_*.php
Remove-Item -Path C:\xampp\htdocs\automation_system\verify_*.php
```

### Option 2: Delete AJAX Test Files
```powershell
Remove-Item -Path C:\xampp\htdocs\automation_system\ajax\check_*.php
Remove-Item -Path C:\xampp\htdocs\automation_system\ajax\test_*.php
```

### Option 3: Delete Entire Test Folder
```powershell
Remove-Item -Path C:\xampp\htdocs\automation_system\test -Recurse -Force
```

### Option 4: Delete Temp Folder
```powershell
Remove-Item -Path C:\xampp\htdocs\automation_system\temp -Recurse -Force
```

### Option 5: Delete Config Migration File
```powershell
Remove-Item -Path C:\xampp\htdocs\automation_system\config\migrate_encryption.php
```

### Option 6: Delete Old Archive
```powershell
Remove-Item -Path C:\xampp\htdocs\automation_system\ajax.zip
```

---

## Summary

**Total files to delete: ~30+ files**

**Safe Deletion Categories:**
- 7 root-level test files ✅
- 8 AJAX test/check files ✅
- 1 config migration file ✅
- 2 test folder files ✅
- 1 faculty AJAX test file ✅
- 36+ old CAR temp files (in `temp/` folder) ✅
- 1 archive file ✅
- 4-6 documentation files (optional) ✅

**⚠️ CRITICAL - DO NOT DELETE:**
- `config/encryption.php`
- `config/decryption_access.php`
- `includes/StudentModel.php`
- `includes/GradesModel.php`
- `config/db.php`
- `config/session.php`
- Any file in `faculty/`, `student/`, `admin/` (except tests)
- `.env` file
- `vendor/` folder

---

## Steps to Clean Up

1. **Backup** (Optional but recommended):
   ```powershell
   Copy-Item -Path C:\xampp\htdocs\automation_system -Destination C:\xampp\htdocs\automation_system_backup -Recurse
   ```

2. **Delete Test Files** (Run from the commands above)

3. **Verify System Still Works**:
   - Test faculty login
   - Check grade display
   - Verify CAR generation

4. **Final Clean**: Delete documentation files if no longer needed

---

## Recommendation

**Start with this deletion order (safest first):**

1. Delete `temp/` folder (36+ old CAR files) → ~5MB space freed
2. Delete root test files (7 files) → Clean root directory
3. Delete AJAX test files (8 files) → Clean AJAX handlers
4. Delete `config/migrate_encryption.php` → 1 file
5. Delete `test/` folder (2 files)
6. Delete `ajax.zip` → Frees space

**Final Result:** Clean production system, all test/debug code removed ✅
