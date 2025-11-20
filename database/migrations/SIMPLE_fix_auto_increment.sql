-- =====================================================
-- إصلاح AUTO_INCREMENT - نسخة مبسطة وآمنة
-- =====================================================
-- هذه الجمل آمنة تماماً ولا تفقد البيانات
-- =====================================================

-- =====================================================
-- 1. إصلاح جدول users
-- =====================================================

-- إضافة PRIMARY KEY (إذا لم يكن موجوداً)
-- إذا ظهر خطأ "Duplicate key name"، تخطاه (يعني موجود بالفعل)
ALTER TABLE `users`
ADD PRIMARY KEY (`id`);

-- إضافة AUTO_INCREMENT
ALTER TABLE `users`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- 2. إصلاح جدول employees
-- =====================================================

-- إضافة PRIMARY KEY (إذا لم يكن موجوداً)
ALTER TABLE `employees`
ADD PRIMARY KEY (`id`);

-- إضافة AUTO_INCREMENT
ALTER TABLE `employees`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- 3. إصلاح جدول teachers (إن وجد)
-- =====================================================

ALTER TABLE `teachers`
ADD PRIMARY KEY (`id`);

ALTER TABLE `teachers`
MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- =====================================================
-- للتحقق بعد التنفيذ:
-- =====================================================
-- SHOW CREATE TABLE `users`;
-- SHOW CREATE TABLE `employees`;
-- =====================================================

