-- Step 1: Check current table structure
-- Run this FIRST to see what primary keys exist

SHOW CREATE TABLE `course_outcomes`;
SHOW CREATE TABLE `co_so_mapping`;

-- Step 2: Check column details
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_KEY,
    EXTRA,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('course_outcomes', 'co_so_mapping')
AND COLUMN_NAME IN ('co_id', 'mapping_id');

-- If EXTRA column shows 'auto_increment', you're already good!
-- If COLUMN_KEY shows 'PRI', it's already a primary key
