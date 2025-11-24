-- Final Fix: Add PRIMARY KEY constraint first, then AUTO_INCREMENT
-- Run these commands ONE AT A TIME

-- For co_so_mapping table:
-- Step 1: Check if there's an existing primary key and what it is
SHOW KEYS FROM `co_so_mapping` WHERE Key_name = 'PRIMARY';

-- Step 2a: If mapping_id is NOT the primary key, make it one:
ALTER TABLE `co_so_mapping` 
ADD PRIMARY KEY (`mapping_id`);

-- Step 2b: Then add AUTO_INCREMENT:
ALTER TABLE `co_so_mapping` 
MODIFY COLUMN `mapping_id` INT NOT NULL AUTO_INCREMENT;

-- ========================================

-- For course_outcomes table:
-- Step 3: Check if there's an existing primary key
SHOW KEYS FROM `course_outcomes` WHERE Key_name = 'PRIMARY';

-- Step 4a: If co_id is NOT the primary key, make it one:
ALTER TABLE `course_outcomes` 
ADD PRIMARY KEY (`co_id`);

-- Step 4b: Then add AUTO_INCREMENT:
ALTER TABLE `course_outcomes` 
MODIFY COLUMN `co_id` INT NOT NULL AUTO_INCREMENT;

-- ========================================
-- Verify both tables now have AUTO_INCREMENT:
SHOW CREATE TABLE `course_outcomes`;
SHOW CREATE TABLE `co_so_mapping`;
