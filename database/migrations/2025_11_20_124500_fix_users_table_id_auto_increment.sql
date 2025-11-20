-- =====================================================
-- SQL Query to Fix AUTO_INCREMENT for users table
-- =====================================================
-- This query fixes the 'id' column in the 'users' table
-- to have AUTO_INCREMENT enabled and PRIMARY KEY without losing any data.
--
-- IMPORTANT: This is safe to run and will NOT delete any data.
-- It only modifies the column definition and adds PRIMARY KEY if missing.
-- =====================================================

-- Step 1: Check if PRIMARY KEY exists (run this first to verify)
-- SELECT CONSTRAINT_NAME
-- FROM information_schema.TABLE_CONSTRAINTS
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME = 'users'
--   AND CONSTRAINT_TYPE = 'PRIMARY KEY';

-- Step 2: Add PRIMARY KEY if it doesn't exist (safe to run even if exists)
-- This will add PRIMARY KEY constraint on the 'id' column
ALTER TABLE `users`
ADD PRIMARY KEY (`id`);

-- Step 3: Modify the column to have AUTO_INCREMENT
-- This must be done AFTER adding PRIMARY KEY
ALTER TABLE `users`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- Alternative: Single query that does both (if PRIMARY KEY doesn't exist)
-- =====================================================
-- If you're sure there's no PRIMARY KEY, you can use this single query:
-- ALTER TABLE `users`
-- MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
-- ADD PRIMARY KEY (`id`);
-- =====================================================

-- =====================================================
-- Verification Query (run this after to check):
-- =====================================================
-- SHOW CREATE TABLE `users`;
--
-- You should see:
-- - PRIMARY KEY (`id`)
-- - AUTO_INCREMENT in the column definition
-- =====================================================

-- =====================================================
-- Optional: Set AUTO_INCREMENT starting point
-- =====================================================
-- First, check the current max ID:
-- SELECT MAX(id) as max_id FROM `users`;
--
-- Then set AUTO_INCREMENT to start from max_id + 1:
-- ALTER TABLE `users` AUTO_INCREMENT = [max_id + 1];
--
-- Example: If max_id is 100, use:
-- ALTER TABLE `users` AUTO_INCREMENT = 101;
-- =====================================================

