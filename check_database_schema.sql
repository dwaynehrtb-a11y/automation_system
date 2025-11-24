-- Database Schema Check Script
-- Run this to verify all AUTO_INCREMENT fields are properly set

-- Check course_outcomes table structure
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_KEY,
    EXTRA,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'course_outcomes'
AND COLUMN_NAME = 'co_id';

-- Check co_so_mapping table structure
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_KEY,
    EXTRA,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'co_so_mapping'
AND COLUMN_NAME = 'mapping_id';

-- If EXTRA column shows 'auto_increment' for both, you're good!
-- If not, run fix_co_id_auto_increment.sql
