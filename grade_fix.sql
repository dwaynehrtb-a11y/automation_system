-- ============================================
-- GRADE DISPLAY BUG FIX - SQL SCRIPT
-- ============================================
-- Purpose: Fix students showing "Failed" instead of correct grades
-- Issue: Records have is_encrypted = 1 when they should be 0
-- ============================================

-- Step 1: Check current state
SELECT 'CURRENT STATE' as step;
SELECT COUNT(*) as encrypted_records FROM grade_term WHERE is_encrypted = 1;
SELECT COUNT(*) as total_records FROM grade_term;

-- Step 2: Show affected students (example)
SELECT 'AFFECTED RECORDS' as step;
SELECT student_id, class_code, term_grade, term_percentage, grade_status, is_encrypted
FROM grade_term
WHERE is_encrypted = 1
LIMIT 10;

-- Step 3: Apply the fix
SELECT 'APPLYING FIX' as step;
UPDATE grade_term SET is_encrypted = 0 WHERE is_encrypted = 1;
SELECT ROW_COUNT() as records_updated;

-- Step 4: Verify the fix
SELECT 'VERIFICATION' as step;
SELECT COUNT(*) as encrypted_records_after_fix FROM grade_term WHERE is_encrypted = 1;

-- Step 5: Show the specific affected student record
SELECT 'STUDENT RECORD FIX VERIFICATION' as step;
SELECT student_id, class_code, term_grade, term_percentage, grade_status, is_encrypted
FROM grade_term
WHERE student_id = '2025-276819' AND class_code = '25_T2_CCPRGG1L_INF223';

-- Expected output for verification:
-- student_id: 2025-276819
-- class_code: 25_T2_CCPRGG1L_INF223
-- term_grade: 1.5
-- term_percentage: 70.00
-- grade_status: passed
-- is_encrypted: 0  (SHOULD BE 0 AFTER FIX)

-- ============================================
-- IF YOU NEED TO ROLLBACK THIS CHANGE:
-- ============================================
-- UPDATE grade_term SET is_encrypted = 1 WHERE student_id = '2025-276819' AND class_code = '25_T2_CCPRGG1L_INF223';
