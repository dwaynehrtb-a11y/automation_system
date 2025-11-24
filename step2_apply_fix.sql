-- Step 2: Apply the fix based on what you found in Step 1
-- Copy the appropriate section below based on your table structure

-- ========================================
-- OPTION A: If co_id/mapping_id are NOT primary keys
-- ========================================
-- Uncomment and run these if the IDs are not currently primary keys:

-- ALTER TABLE `course_outcomes` ADD PRIMARY KEY (`co_id`);
-- ALTER TABLE `course_outcomes` MODIFY COLUMN `co_id` INT NOT NULL AUTO_INCREMENT;

-- ALTER TABLE `co_so_mapping` ADD PRIMARY KEY (`mapping_id`);
-- ALTER TABLE `co_so_mapping` MODIFY COLUMN `mapping_id` INT NOT NULL AUTO_INCREMENT;


-- ========================================
-- OPTION B: If co_id/mapping_id ARE already primary keys (most likely)
-- ========================================
-- Just add AUTO_INCREMENT to existing primary keys:

ALTER TABLE `course_outcomes` 
MODIFY COLUMN `co_id` INT NOT NULL AUTO_INCREMENT;

ALTER TABLE `co_so_mapping` 
MODIFY COLUMN `mapping_id` INT NOT NULL AUTO_INCREMENT;


-- ========================================
-- OPTION C: If there's a different primary key
-- ========================================
-- If the table has a DIFFERENT primary key, you need to:
-- 1. Remove the existing primary key
-- 2. Add co_id/mapping_id as the new primary key
-- 3. Add AUTO_INCREMENT

-- Example for course_outcomes (ONLY if needed):
-- ALTER TABLE `course_outcomes` DROP PRIMARY KEY;
-- ALTER TABLE `course_outcomes` ADD PRIMARY KEY (`co_id`);
-- ALTER TABLE `course_outcomes` MODIFY COLUMN `co_id` INT NOT NULL AUTO_INCREMENT;

-- Example for co_so_mapping (ONLY if needed):
-- ALTER TABLE `co_so_mapping` DROP PRIMARY KEY;
-- ALTER TABLE `co_so_mapping` ADD PRIMARY KEY (`mapping_id`);
-- ALTER TABLE `co_so_mapping` MODIFY COLUMN `mapping_id` INT NOT NULL AUTO_INCREMENT;
