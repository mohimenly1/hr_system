-- =====================================================
-- SQL Queries to Fix AUTO_INCREMENT for All Tables
-- =====================================================
-- This file contains SQL queries to fix AUTO_INCREMENT
-- and PRIMARY KEY issues for all tables that might be affected.
--
-- IMPORTANT: These queries are safe to run and will NOT delete any data.
-- They only modify column definitions.
-- =====================================================

-- =====================================================
-- 1. Fix users table
-- =====================================================
-- Add PRIMARY KEY if it doesn't exist
ALTER TABLE `users`
ADD PRIMARY KEY (`id`);

-- Add AUTO_INCREMENT
ALTER TABLE `users`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- 2. Fix employees table
-- =====================================================
-- Add PRIMARY KEY if it doesn't exist
ALTER TABLE `employees`
ADD PRIMARY KEY (`id`);

-- Add AUTO_INCREMENT
ALTER TABLE `employees`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- 3. Fix teachers table (if exists)
-- =====================================================
-- Add PRIMARY KEY if it doesn't exist
ALTER TABLE `teachers`
ADD PRIMARY KEY (`id`);

-- Add AUTO_INCREMENT
ALTER TABLE `teachers`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- 4. Fix departments table
-- =====================================================
ALTER TABLE `departments`
ADD PRIMARY KEY (`id`);

ALTER TABLE `departments`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- 5. Fix contracts table
-- =====================================================
ALTER TABLE `contracts`
ADD PRIMARY KEY (`id`);

ALTER TABLE `contracts`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- 6. Fix work_experiences table
-- =====================================================
ALTER TABLE `work_experiences`
ADD PRIMARY KEY (`id`);

ALTER TABLE `work_experiences`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- 7. Fix attachments table
-- =====================================================
ALTER TABLE `attachments`
ADD PRIMARY KEY (`id`);

ALTER TABLE `attachments`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- 8. Fix leaves table
-- =====================================================
ALTER TABLE `leaves`
ADD PRIMARY KEY (`id`);

ALTER TABLE `leaves`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- 9. Fix attendances table
-- =====================================================
ALTER TABLE `attendances`
ADD PRIMARY KEY (`id`);

ALTER TABLE `attendances`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- 10. Fix contracts table (if not already fixed)
-- =====================================================
-- This is a duplicate check - safe to run even if already executed

-- =====================================================
-- Verification Queries (run these to check):
-- =====================================================
-- Check users table:
-- SHOW CREATE TABLE `users`;
--
-- Check employees table:
-- SHOW CREATE TABLE `employees`;
--
-- Check if PRIMARY KEY exists:
-- SELECT CONSTRAINT_NAME
-- FROM information_schema.TABLE_CONSTRAINTS
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME = 'users'
--   AND CONSTRAINT_TYPE = 'PRIMARY KEY';
-- =====================================================

-- =====================================================
-- Optional: Set AUTO_INCREMENT starting points
-- =====================================================
-- For users table:
-- SELECT MAX(id) as max_id FROM `users`;
-- ALTER TABLE `users` AUTO_INCREMENT = [max_id + 1];
--
-- For employees table:
-- SELECT MAX(id) as max_id FROM `employees`;
-- ALTER TABLE `employees` AUTO_INCREMENT = [max_id + 1];
-- =====================================================

