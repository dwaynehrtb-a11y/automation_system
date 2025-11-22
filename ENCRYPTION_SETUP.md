## 🔐 AES-256 ENCRYPTION SETUP GUIDE

### STEP 1: Generate Encryption Key

Run this command:
```bash
php -r "require 'config/encryption.php'; echo Encryption::generateKey();"
```

This will output a random 32-byte key in base64 format.

### STEP 2: Add to .env File

Create or edit `.env` file in project root:
```
APP_ENCRYPTION_KEY=your_generated_key_here
```

Example:
```
APP_ENCRYPTION_KEY=aBc1234XYZ+/AbC1234XYZ+/AbC1234XYZ+/AbC1234=
```

⚠️ **IMPORTANT:**
- Keep this key SECRET
- Never commit .env to version control
- Store backup in secure location
- If key is lost, encrypted data cannot be recovered

### STEP 3: Test Encryption

Create test file `test_encryption.php`:
```php
<?php
require_once 'config/encryption.php';
require_once 'config/db.php';

// Test encrypt/decrypt
$original = "Test Student Name";
$encrypted = Encryption::encrypt($original);
$decrypted = Encryption::decrypt($encrypted);

echo "Original: $original\n";
echo "Encrypted: $encrypted\n";
echo "Decrypted: $decrypted\n";
echo "Match: " . ($original === $decrypted ? "✅ YES" : "❌ NO") . "\n";
?>
```

Run: `php test_encryption.php`

### STEP 4: Migrate Existing Data

Run migration script to encrypt all existing student and grade data:
```bash
php config/migrate_encryption.php
```

This will:
- ✅ Encrypt all student names, emails, birthdays
- ✅ Encrypt all grade values and percentages
- ✅ Show progress and summary
- ✅ Log any errors for manual review

Output example:
```
=== ENCRYPTION MIGRATION SCRIPT ===

[1/2] Migrating Student Data...
...............................
✅ Migrated 31 student records

[2/2] Migrating Grade Data...
.....................................
✅ Migrated 347 grade records

=== MIGRATION SUMMARY ===
Students encrypted: 31
Grades encrypted: 347

✅ MIGRATION COMPLETED SUCCESSFULLY
```

### STEP 5: Update Existing Code

Update any code that fetches student or grade data to use the new models:

**Before (Plain):**
```php
$result = $conn->query("SELECT * FROM student WHERE student_id = '$id'");
$student = $result->fetch_assoc();
echo $student['first_name']; // Plain text
```

**After (Encrypted):**
```php
require_once 'includes/StudentModel.php';
$studentModel = new StudentModel($conn);
$student = $studentModel->getStudentById($id, $userId, $userRole);
echo $student['first_name']; // Auto-decrypted with access control
```

### STEP 6: Verify Implementation

Test decryption with access control:

**Faculty viewing their students:**
```php
$studentModel->getStudentById($studentId, $facultyId, 'faculty');
// ✅ Works if student is in faculty's class
```

**Student viewing themselves:**
```php
$studentModel->getStudentById($studentId, $studentId, 'student');
// ✅ Works if IDs match
```

**Student viewing another student:**
```php
$studentModel->getStudentById($otherId, $studentId, 'student');
// ❌ Error: Unauthorized
```

---

## 🔒 ENCRYPTION ARCHITECTURE

### Fields Encrypted:

**Student Table:**
- ✅ first_name
- ✅ last_name
- ✅ email
- ✅ birthday
- ⚠️ student_id (NOT encrypted - used for lookups)
- ⚠️ password (Already hashed - don't double-encrypt)

**Term_Grades Table:**
- ✅ term_grade
- ✅ midterm_percentage
- ✅ finals_percentage
- ✅ term_percentage
- ✅ lacking_requirements
- ⚠️ student_id (NOT encrypted - used for lookups)
- ⚠️ class_code (NOT encrypted - used for lookups)

### Access Control Levels:

| Role | Can Decrypt | Scope |
|------|------------|-------|
| **Admin** | ✅ YES | All student & grade data |
| **Faculty** | ✅ YES | Their own class students & grades |
| **Student** | ✅ YES | Only their own data |
| **Public** | ❌ NO | Cannot decrypt anything |

### Encryption Algorithm:

- **Type:** AES-256-GCM (Galois/Counter Mode)
- **Key Length:** 256 bits (32 bytes)
- **IV Length:** 96 bits (12 bytes)
- **Authentication Tag:** 128 bits (16 bytes)
- **Derivation:** PBKDF2 with 10,000 iterations

### Security Features:

1. **Authenticated Encryption:** GCM mode detects tampering
2. **Random IV:** Each encryption uses unique initialization vector
3. **Key Derivation:** APP_KEY hashed with PBKDF2 for 256-bit key
4. **Access Control:** Role-based decryption permissions
5. **Audit Logging:** All decryption access logged
6. **Error Handling:** Encryption errors don't expose sensitive data

---

## 📊 PERFORMANCE NOTES

- **Encryption:** ~1-2ms per field
- **Decryption:** ~1-2ms per field
- **Database Queries:** No impact (queries on unencrypted IDs/codes)
- **Memory Usage:** Minimal overhead

### Performance Tips:

1. Use indexed columns (student_id, class_code) for queries
2. Don't search on encrypted fields directly
3. Cache decrypted data if used multiple times
4. Use pagination for large result sets

---

## 🔑 KEY ROTATION (Advanced)

To rotate encryption key:

1. Generate new key: `php -r "require 'config/encryption.php'; echo Encryption::generateKey();"`
2. Create rotation script to re-encrypt all data with new key
3. Update .env with new key
4. Run rotation script
5. Keep old key in secure backup for recovery

---

## ⚠️ TROUBLESHOOTING

**"APP_ENCRYPTION_KEY not set"**
- Add APP_ENCRYPTION_KEY to .env
- Verify .env is in root directory
- Check .env syntax (no quotes around key)

**"Decryption failed - data may be corrupted"**
- Wrong APP_ENCRYPTION_KEY (data was encrypted with different key)
- Database corruption
- Check backup and restore if needed

**"Unauthorized access to decrypted data"**
- User role not authorized for data
- Student ID mismatch
- Faculty not assigned to class
- Check access control in DecryptionAccessControl

---

## 📋 CHECKLIST

- [ ] Generated encryption key
- [ ] Added APP_ENCRYPTION_KEY to .env
- [ ] Tested encryption/decryption
- [ ] Ran migration script
- [ ] Updated code to use StudentModel/GradesModel
- [ ] Tested access control (different user roles)
- [ ] Verified encrypted data in database
- [ ] Set up audit logging
- [ ] Backed up .env and encryption key
- [ ] Documented key storage location

---

**Questions?** Check logs: `tail -f storage/logs/error.log`
