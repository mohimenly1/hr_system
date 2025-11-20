-- =====================================================
-- SQL Query to Fix AUTO_INCREMENT for employees table
-- =====================================================
-- This query fixes the 'id' column in the 'employees' table
-- to have AUTO_INCREMENT enabled and PRIMARY KEY without losing any data.
-- 
-- IMPORTANT: This is safe to run and will NOT delete any data.
-- It only modifies the column definition and adds PRIMARY KEY if missing.
-- =====================================================

-- Step 1: Add PRIMARY KEY if it doesn't exist (safe to run even if exists)
ALTER TABLE `employees` 
ADD PRIMARY KEY (`id`);

-- Step 2: Modify the column to have AUTO_INCREMENT
-- This must be done AFTER adding PRIMARY KEY
ALTER TABLE `employees` 
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- Verification Query (run this after to check):
-- =====================================================
-- SHOW CREATE TABLE `employees`;
-- 
-- You should see:
-- - PRIMARY KEY (`id`)
-- - AUTO_INCREMENT in the column definition
-- =====================================================

-- =====================================================
-- Optional: Set AUTO_INCREMENT starting point
-- =====================================================
-- First, check the current max ID:
-- SELECT MAX(id) as max_id FROM `employees`;
-- 
-- Then set AUTO_INCREMENT to start from max_id + 1:
-- ALTER TABLE `employees` AUTO_INCREMENT = [max_id + 1];
-- 
-- Example: If max_id is 50, use:
-- ALTER TABLE `employees` AUTO_INCREMENT = 51;
-- =====================================================

