-- Fix for co_id field not having a default value
-- This error occurs when inserting into course_outcomes table
-- Run this SQL on your Hostinger database

-- Make co_id AUTO_INCREMENT if it isn't already
ALTER TABLE `course_outcomes` 
MODIFY COLUMN `co_id` INT NOT NULL AUTO_INCREMENT;

-- Verify the change
SHOW CREATE TABLE `course_outcomes`;
