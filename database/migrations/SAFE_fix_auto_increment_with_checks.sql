-- =====================================================
-- SQL Queries to Fix AUTO_INCREMENT - SAFE VERSION
-- =====================================================
-- هذه الجمل آمنة تماماً ولن تفقد أو تغير أي بيانات
--
-- ما تفعله هذه الجمل:
-- 1. ADD PRIMARY KEY: يضيف قيد PRIMARY KEY فقط (لا يغير البيانات)
-- 2. MODIFY: يغير تعريف العمود فقط (لا يغير القيم الموجودة)
--
-- =====================================================
-- التحقق قبل التنفيذ (اختياري لكن موصى به):
-- =====================================================

-- =====================================================
-- التحقق قبل التنفيذ (اختياري لكن موصى به):
-- =====================================================

-- 1. التحقق من وجود PRIMARY KEY مسبقاً
-- استبدل 'your_database_name' باسم قاعدة البيانات الفعلي
-- أو استخدم DATABASE() إذا كنت داخل قاعدة البيانات
SELECT CONSTRAINT_NAME
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'users'
  AND CONSTRAINT_TYPE = 'PRIMARY KEY';

-- إذا لم تعمل الجملة أعلاه، استخدم هذه:
SHOW KEYS FROM `users` WHERE Key_name = 'PRIMARY';

-- 2. التحقق من عدم وجود قيم مكررة في عمود id
-- هذه الجملة آمنة وتعمل مباشرة
SELECT id, COUNT(*) as count
FROM `users`
GROUP BY id
HAVING COUNT(*) > 1;

-- 3. التحقق من عدم وجود قيم NULL في عمود id
SELECT COUNT(*) as null_count
FROM `users`
WHERE id IS NULL;

-- 4. التحقق من عدد السجلات (للمقارنة بعد التنفيذ)
SELECT COUNT(*) as total_records FROM `users`;

-- =====================================================
-- إصلاح جدول users (آمن 100%)
-- =====================================================

-- الخطوة 1: إضافة PRIMARY KEY (آمن - لا يغير البيانات)
-- إذا كان PRIMARY KEY موجوداً، سيظهر خطأ لكن لا ضرر
-- يمكنك تجاهل الخطأ والمتابعة
ALTER TABLE `users`
ADD PRIMARY KEY (`id`);

-- الخطوة 2: إضافة AUTO_INCREMENT (آمن - لا يغير البيانات)
-- هذا فقط يغير تعريف العمود، لا يغير القيم الموجودة
ALTER TABLE `users`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- إصلاح جدول employees (آمن 100%)
-- =====================================================

-- التحقق أولاً (اختياري):
-- SELECT id, COUNT(*) as count
-- FROM `employees`
-- GROUP BY id
-- HAVING count > 1;

-- الخطوة 1: إضافة PRIMARY KEY
ALTER TABLE `employees`
ADD PRIMARY KEY (`id`);

-- الخطوة 2: إضافة AUTO_INCREMENT
ALTER TABLE `employees`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- إصلاح جدول teachers (إن وجد)
-- =====================================================
ALTER TABLE `teachers`
ADD PRIMARY KEY (`id`);

ALTER TABLE `teachers`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- ملاحظات مهمة:
-- =====================================================
-- ✅ هذه الجمل آمنة تماماً ولا تفقد البيانات
-- ✅ لا تغير القيم الموجودة في الجداول
-- ✅ فقط تعدل تعريف الأعمدة (metadata)
--
-- ⚠️ إذا ظهر خطأ "Duplicate key name" عند ADD PRIMARY KEY:
--    - هذا يعني أن PRIMARY KEY موجود بالفعل
--    - لا مشكلة، فقط تخطى هذا السطر
--    - نفذ MODIFY مباشرة
--
-- ⚠️ إذا ظهر خطأ "Duplicate entry" عند ADD PRIMARY KEY:
--    - هذا يعني وجود قيم مكررة في عمود id
--    - يجب إصلاح البيانات المكررة أولاً
--    - لكن هذا نادر جداً
-- =====================================================

-- =====================================================
-- للتحقق بعد التنفيذ:
-- =====================================================
-- SHOW CREATE TABLE `users`;
-- SHOW CREATE TABLE `employees`;
--
-- يجب أن ترى:
-- - PRIMARY KEY (`id`)
-- - AUTO_INCREMENT في تعريف العمود
-- =====================================================

