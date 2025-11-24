-- Fix for AUTO_INCREMENT fields not having default values
-- These errors occur when inserting into course_outcomes and co_so_mapping tables
-- Run this SQL on your Hostinger database

-- Fix 1: Make co_id AUTO_INCREMENT (it should already be PRIMARY KEY)
ALTER TABLE `course_outcomes` 
MODIFY COLUMN `co_id` INT NOT NULL AUTO_INCREMENT;

-- Fix 2: Make mapping_id AUTO_INCREMENT (it should already be PRIMARY KEY)
ALTER TABLE `co_so_mapping` 
MODIFY COLUMN `mapping_id` INT NOT NULL AUTO_INCREMENT;

-- If you get error "there can be only one auto column and it must be defined as a key",
-- then run these instead:

-- For course_outcomes:
-- First, check if co_id is already PRIMARY KEY, if not:
-- ALTER TABLE `course_outcomes` DROP PRIMARY KEY;
-- ALTER TABLE `course_outcomes` ADD PRIMARY KEY (`co_id`);
-- ALTER TABLE `course_outcomes` MODIFY COLUMN `co_id` INT NOT NULL AUTO_INCREMENT;

-- For co_so_mapping:
-- First, check if mapping_id is already PRIMARY KEY, if not:
-- ALTER TABLE `co_so_mapping` DROP PRIMARY KEY;
-- ALTER TABLE `co_so_mapping` ADD PRIMARY KEY (`mapping_id`);
-- ALTER TABLE `co_so_mapping` MODIFY COLUMN `mapping_id` INT NOT NULL AUTO_INCREMENT;

-- Verify the changes
SHOW CREATE TABLE `course_outcomes`;
SHOW CREATE TABLE `co_so_mapping`;
