<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    person: Object,
    comparison: Object,
    deductions: Object,
    filters: Object,
});

const form = useForm({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
});

const showDeductionAnalysis = ref(false);
const analyzingDeductions = ref(false);

const applyFilters = () => {
    form.get(route('hr.attendance-reports.personnel-details', {
        personType: props.person.type,
        personId: props.person.id,
    }), {
        preserveState: true,
        preserveScroll: true,
    });
};

const getStatusBadgeClass = (status) => {
    const classes = {
        'present': 'bg-green-100 text-green-800',
        'absent': 'bg-red-100 text-red-800',
        'late': 'bg-yellow-100 text-yellow-800',
        'on_leave': 'bg-blue-100 text-blue-800',
        'no_schedule': 'bg-gray-100 text-gray-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const getStatusLabel = (status) => {
    const labels = {
        'present': 'حضور',
        'absent': 'غياب',
        'late': 'تأخير',
        'on_leave': 'إجازة',
        'no_schedule': 'لا يوجد جدول',
    };
    return labels[status] || status;
};

// Convert 24-hour time to 12-hour format with AM/PM
const formatTime12Hour = (time24) => {
    if (!time24) return '-';

    // Handle time format (HH:mm:ss or HH:mm)
    const timeParts = time24.split(':');
    const hours = parseInt(timeParts[0], 10);
    const minutes = timeParts[1] || '00';

    if (isNaN(hours)) return time24;

    const period = hours >= 12 ? 'م' : 'ص';
    const hours12 = hours % 12 || 12;

    return `${hours12}:${minutes} ${period}`;
};

// Format minutes to readable format (hours and minutes if > 60)
const formatMinutes = (minutes) => {
    if (!minutes || minutes === null || minutes === undefined) return '-';

    const totalMinutes = Math.round(minutes);

    if (totalMinutes === 0) return '-';

    if (totalMinutes < 60) {
        return `${totalMinutes} دقيقة`;
    }

    const hours = Math.floor(totalMinutes / 60);
    const remainingMinutes = totalMinutes % 60;

    if (remainingMinutes === 0) {
        return `${hours} ساعة`;
    }

    return `${hours} ساعة و ${remainingMinutes} دقيقة`;
};

// Format hours to readable format
const formatHours = (hours) => {
    if (!hours || hours === null || hours === undefined) return '-';

    const roundedHours = Math.round(hours * 100) / 100; // Round to 2 decimal places

    if (roundedHours === 0) return '0 ساعة';

    // If it's a whole number, show without decimals
    if (roundedHours % 1 === 0) {
        return `${roundedHours} ساعة`;
    }

    // Convert decimal part to minutes for better readability
    const wholeHours = Math.floor(roundedHours);
    const decimalPart = roundedHours - wholeHours;
    const minutes = Math.round(decimalPart * 60);

    if (minutes === 0) {
        return `${wholeHours} ساعة`;
    }

    if (wholeHours === 0) {
        return `${minutes} دقيقة`;
    }

    return `${wholeHours} ساعة و ${minutes} دقيقة`;
};
</script>

<template>
    <Head :title="`تفاصيل الحضور - ${person.name}`" />
    <HrLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">{{ person.name }}</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ person.type_label }} - {{ person.job_title || '' }}
                        <span v-if="person.department" class="mr-2">- {{ person.department }}</span>
                    </p>
                </div>
                <button
                    @click="router.visit(route('hr.attendance-reports.index'))"
                    class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-all"
                >
                    <i class="fas fa-arrow-right mr-2"></i>
                    العودة للتقارير
                </button>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Filters -->
            <div class="bg-white shadow-lg rounded-xl p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">فلترة البيانات</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">من تاريخ</label>
                        <input
                            v-model="form.start_date"
                            type="date"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">إلى تاريخ</label>
                        <input
                            v-model="form.end_date"
                            type="date"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                        />
                    </div>
                    <div class="flex items-end">
                        <button
                            @click="applyFilters"
                            class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 text-white px-6 py-2.5 rounded-lg hover:from-indigo-700 hover:to-indigo-800 transition-all font-medium shadow-lg"
                        >
                            <i class="fas fa-filter mr-2"></i>
                            تطبيق الفلتر
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-green-50 to-green-100 border-2 border-green-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-green-800">أيام الحضور</p>
                            <p class="text-3xl font-bold text-green-900 mt-2">
                                {{ comparison.summary.present }}
                            </p>
                        </div>
                        <div class="bg-green-200 rounded-full p-4">
                            <i class="fas fa-check-circle text-green-700 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-red-50 to-red-100 border-2 border-red-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-red-800">أيام الغياب</p>
                            <p class="text-3xl font-bold text-red-900 mt-2">
                                {{ comparison.summary.absent }}
                            </p>
                        </div>
                        <div class="bg-red-200 rounded-full p-4">
                            <i class="fas fa-times-circle text-red-700 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 border-2 border-yellow-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-yellow-800">مرات التأخير</p>
                            <p class="text-3xl font-bold text-yellow-900 mt-2">
                                {{ comparison.summary.late_count }}
                            </p>
                            <p class="text-xs text-yellow-700 mt-1">
                                متوسط: {{ formatMinutes(comparison.summary.average_minutes_late) }}
                            </p>
                        </div>
                        <div class="bg-yellow-200 rounded-full p-4">
                            <i class="fas fa-clock text-yellow-700 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-blue-100 border-2 border-blue-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-blue-800">نسبة الحضور</p>
                            <p class="text-3xl font-bold text-blue-900 mt-2">
                                {{ comparison.summary.attendance_rate }}%
                            </p>
                        </div>
                        <div class="bg-blue-200 rounded-full p-4">
                            <i class="fas fa-percentage text-blue-700 text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hours Summary -->
            <div class="bg-white shadow-lg rounded-xl p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">ملخص الساعات</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center p-4 bg-indigo-50 rounded-lg border-2 border-indigo-200">
                        <p class="text-sm font-medium text-indigo-700 mb-2">الساعات المتوقعة</p>
                        <p class="text-2xl font-bold text-indigo-900 mt-2">
                            {{ formatHours(comparison.summary.total_expected_hours) }}
                        </p>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-lg border-2 border-green-200">
                        <p class="text-sm font-medium text-green-700 mb-2">الساعات الفعلية</p>
                        <p class="text-2xl font-bold text-green-900 mt-2">
                            {{ formatHours(comparison.summary.total_actual_hours) }}
                        </p>
                    </div>
                    <div class="text-center p-4 rounded-lg border-2" :class="comparison.summary.hours_difference >= 0 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
                        <p class="text-sm font-medium mb-2" :class="comparison.summary.hours_difference >= 0 ? 'text-green-700' : 'text-red-700'">الفرق</p>
                        <p class="text-2xl font-bold mt-2" :class="comparison.summary.hours_difference >= 0 ? 'text-green-900' : 'text-red-900'">
                            <span v-if="comparison.summary.hours_difference !== 0">
                                {{ comparison.summary.hours_difference >= 0 ? '+' : '' }}{{ formatHours(Math.abs(comparison.summary.hours_difference)) }}
                            </span>
                            <span v-else class="text-gray-600">متساوي</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Deduction Analysis Button -->
            <div class="bg-gradient-to-r from-purple-50 to-pink-50 border-2 border-purple-200 rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="bg-purple-500 rounded-full p-3 ml-4">
                            <i class="fas fa-search-dollar text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-purple-900">تحليل القواعد الخصمية</h3>
                            <p class="text-sm text-purple-700 mt-1">اكتشف القواعد التي ستطبق على الموظف/المعلم بناءً على الحضور والانصراف</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <a
                            :href="route('hr.attendance-reports.export-deductions', {
                                personType: person.type,
                                personId: person.id,
                                start_date: form.start_date,
                                end_date: form.end_date
                            })"
                            class="bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-3 rounded-lg hover:from-green-700 hover:to-emerald-700 transition-all font-semibold shadow-lg flex items-center"
                        >
                            <i class="fas fa-file-excel ml-2"></i>
                            تصدير Excel
                        </a>
                        <button
                            @click="showDeductionAnalysis = !showDeductionAnalysis"
                            class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-lg hover:from-purple-700 hover:to-pink-700 transition-all font-semibold shadow-lg flex items-center"
                        >
                            <i class="fas fa-calculator ml-2"></i>
                            {{ showDeductionAnalysis ? 'إخفاء التحليل' : 'تحليل القواعد' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Applied Deductions -->
            <div v-if="showDeductionAnalysis" class="bg-white shadow-lg rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-800">الخصومات المطبقة</h3>
                    <p class="text-sm text-gray-600 mt-1">الخصومات المطبقة بناءً على قواعد الخصميات</p>
                </div>
                <div class="p-6">
                    <div v-if="deductions && deductions.applied_deductions && deductions.applied_deductions.length > 0" class="space-y-4">
                        <div
                            v-for="(deduction, index) in deductions.applied_deductions"
                            :key="index"
                            class="bg-gradient-to-r from-red-50 to-orange-50 border-2 border-red-200 rounded-xl p-6"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-lg font-bold text-gray-800">{{ deduction.rule.name }}</h4>
                                        <span class="bg-red-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                                            أولوية: {{ deduction.rule.priority }}
                                        </span>
                                    </div>
                                    <p v-if="deduction.rule.description" class="text-sm text-gray-600 mb-3">{{ deduction.rule.description }}</p>

                                    <!-- Reason for application -->
                                    <div v-if="deduction.reason" class="bg-yellow-50 border-r-4 border-yellow-500 p-3 rounded mb-3">
                                        <p class="text-sm font-semibold text-yellow-900 mb-1">
                                            <i class="fas fa-info-circle ml-1"></i>
                                            سبب التطبيق:
                                        </p>
                                        <p class="text-sm text-yellow-800">{{ deduction.reason }}</p>
                                    </div>

                                    <div class="flex flex-wrap gap-4 mt-3">
                                        <div class="bg-white rounded-lg px-3 py-2">
                                            <span class="text-xs text-gray-600">نوع الخصم:</span>
                                            <span class="font-semibold text-gray-800 mr-2">
                                                {{ deduction.deduction_type === 'fixed' ? 'مبلغ ثابت' :
                                                  deduction.deduction_type === 'percentage' ? 'نسبة مئوية' :
                                                  deduction.deduction_type === 'daily_salary' ? 'يوم/أيام من المرتب' :
                                                  deduction.deduction_type === 'hourly_salary' ? 'ساعات من المرتب' : deduction.deduction_type }}
                                            </span>
                                        </div>
                                        <div class="bg-white rounded-lg px-3 py-2">
                                            <span class="text-xs text-gray-600">مبلغ الخصم:</span>
                                            <span class="font-bold text-red-600 mr-2">{{ deduction.deduction_amount }} دينار</span>
                                        </div>
                                        <div class="bg-white rounded-lg px-3 py-2">
                                            <span class="text-xs text-gray-600">عدد الأيام المكتشفة:</span>
                                            <span class="font-semibold text-gray-800 mr-2">{{ deduction.triggered_count || 0 }} يوم</span>
                                        </div>
                                    </div>

                                    <!-- Triggered Days -->
                                    <div v-if="deduction.triggered_days && deduction.triggered_days.length > 0" class="mt-4 bg-white rounded-lg p-4 border border-gray-200">
                                        <p class="text-sm font-semibold text-gray-700 mb-3">
                                            <i class="fas fa-calendar-alt ml-1"></i>
                                            الأيام التي أدت لتطبيق هذه القاعدة:
                                        </p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                            <div
                                                v-for="(day, dayIndex) in deduction.triggered_days"
                                                :key="dayIndex"
                                                class="bg-gray-50 border border-gray-200 rounded-lg p-2"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <p class="text-xs font-semibold text-gray-900">{{ day.date }}</p>
                                                        <p class="text-xs text-gray-600">{{ day.day_name }}</p>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ day.details }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="deduction.applied_conditions && Object.keys(deduction.applied_conditions).length > 0" class="mt-3">
                                        <p class="text-xs text-gray-500 mb-1">الشروط المطبقة:</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span
                                                v-for="(value, key) in deduction.applied_conditions"
                                                :key="key"
                                                class="bg-indigo-100 text-indigo-800 px-2 py-1 rounded text-xs"
                                            >
                                                {{ value }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl p-6 mt-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium opacity-90">إجمالي الخصومات</p>
                                    <p class="text-3xl font-bold mt-2">{{ deductions.total_deduction }} دينار</p>
                                </div>
                                <div class="bg-white/20 rounded-full p-4">
                                    <i class="fas fa-money-bill-wave text-2xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 text-gray-500">
                        <i class="fas fa-check-circle text-4xl mb-4 text-green-500"></i>
                        <p class="text-lg">لا توجد خصومات مطبقة</p>
                        <p class="text-sm mt-2">جميع قواعد الخصميات تم تقييمها ولم يتم تطبيق أي خصم</p>
                    </div>

                    <!-- Not Applied Rules -->
                    <div v-if="deductions && deductions.not_applied_rules && deductions.not_applied_rules.length > 0" class="mt-8 pt-8 border-t-2 border-gray-200">
                        <h4 class="text-lg font-bold text-gray-800 mb-4">
                            <i class="fas fa-info-circle ml-2 text-blue-600"></i>
                            القواعد غير المطبقة
                        </h4>
                        <div class="space-y-3">
                            <div
                                v-for="(rule, index) in deductions.not_applied_rules"
                                :key="index"
                                class="bg-gray-50 border border-gray-200 rounded-lg p-4"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h5 class="font-semibold text-gray-800 mb-1">{{ rule.rule.name }}</h5>
                                        <p v-if="rule.rule.description" class="text-sm text-gray-600 mb-2">{{ rule.rule.description }}</p>
                                        <div class="bg-yellow-50 border-r-4 border-yellow-400 p-2 rounded">
                                            <p class="text-xs font-semibold text-yellow-900">
                                                <i class="fas fa-exclamation-triangle ml-1"></i>
                                                سبب عدم التطبيق:
                                            </p>
                                            <p class="text-xs text-yellow-800 mt-1">{{ rule.reason }}</p>
                                            <div v-if="rule.found_events !== undefined && rule.required_events !== undefined" class="mt-2 space-y-1">
                                                <p class="text-xs text-yellow-700">
                                                    <span class="font-semibold">تم العثور على:</span> {{ rule.found_events }} / <span class="font-semibold">المطلوب:</span> {{ rule.required_events }}
                                                </p>
                                                <p v-if="rule.total_found !== undefined && rule.total_found !== rule.found_events" class="text-xs text-yellow-600">
                                                    <span class="font-semibold">إجمالي الأحداث:</span> {{ rule.total_found }} (تم استبعاد {{ rule.total_found - rule.found_events }} بسبب القيود)
                                                </p>
                                                <p v-if="rule.filtered_out_reason" class="text-xs text-yellow-600 mt-1 italic">
                                                    {{ rule.filtered_out_reason }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Comparison Table -->
            <div class="bg-white shadow-lg rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-800">مقارنة يومية</h3>
                    <p class="text-sm text-gray-600 mt-1">مقارنة بين البصمات الفعلية والجدول الزمني المعتمد</p>
                </div>

                <!-- Explanation Box for Hours Column -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-r-4 border-blue-500 mx-6 mt-6 mb-4 rounded-lg p-5 shadow-md">
                    <div class="flex items-start">
                        <div class="bg-blue-500 rounded-full p-2 ml-4 flex-shrink-0">
                            <i class="fas fa-clock text-white text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-lg font-bold text-blue-900 mb-3 flex items-center">
                                <span>شرح عمود "الساعات"</span>
                            </h4>
                            <div class="space-y-3 text-sm text-gray-700">
                                <div class="bg-white rounded-lg p-3 border border-blue-200">
                                    <div class="flex items-start">
                                        <i class="fas fa-info-circle text-blue-600 mt-1 ml-2"></i>
                                        <div>
                                            <p class="font-semibold text-gray-900 mb-1">الساعات المتوقعة / الساعات الفعلية:</p>
                                            <p class="text-gray-700">تعرض عدد الساعات المطلوبة حسب الجدول الزمني المعتمد مقابل الساعات الفعلية المحسوبة من أوقات البصمات (دخول/خروج).</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white rounded-lg p-3 border border-blue-200">
                                    <div class="flex items-start">
                                        <i class="fas fa-calculator text-blue-600 mt-1 ml-2"></i>
                                        <div>
                                            <p class="font-semibold text-gray-900 mb-1">الفرق:</p>
                                            <p class="text-gray-700">
                                                يوضح الفرق بين الساعات المتوقعة والفعلي:
                                                <span class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded mr-2 mt-1 text-xs font-semibold">+ (أخضر)</span> = زيادة في ساعات العمل
                                                <span class="inline-block bg-red-100 text-red-800 px-2 py-1 rounded mr-2 mt-1 text-xs font-semibold">- (أحمر)</span> = نقص في ساعات العمل
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-white rounded-lg p-3 border border-blue-200">
                                    <div class="flex items-start">
                                        <i class="fas fa-user-tie text-blue-600 mt-1 ml-2"></i>
                                        <div>
                                            <p class="font-semibold text-gray-900 mb-1">الاستفادة للمحاسب:</p>
                                            <ul class="list-disc list-inside space-y-1 text-gray-700">
                                                <li>حساب ساعات العمل الفعلية بدقة لتحديد الراتب النهائي</li>
                                                <li>خاصة مهم للموظفين الذين يعملون بنظام الساعة (hourly employees)</li>
                                                <li>مراجعة الالتزام بالجدول الزمني المعتمد</li>
                                                <li>تحديد أيام العمل الإضافية أو الناقصة</li>
                                                <li>استخدام البيانات في حساب الخصومات والمكافآت</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase">التاريخ</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">اليوم</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">الجدول المتوقع</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">البصمة الفعلية</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">الحالة</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">التأخير</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">الانصراف المبكر</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">
                                    <div class="flex flex-col items-center">
                                        <span>الساعات</span>
                                        <span class="text-xs font-normal opacity-75 mt-1">(متوقع / فعلي)</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="comp in comparison.comparisons"
                                :key="comp.date"
                                :class="comp.is_weekend ? 'bg-gray-50' : 'hover:bg-gray-50'"
                                class="transition-colors"
                            >
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ comp.date }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                    {{ comp.day_name }}
                                    <span v-if="comp.is_weekend" class="text-xs text-gray-400 block">عطلة</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                    <div v-if="comp.expected_schedule">
                                        <div class="font-medium text-gray-900 mb-1">
                                            {{ formatTime12Hour(comp.expected_schedule.start_time) }} - {{ formatTime12Hour(comp.expected_schedule.end_time) }}
                                        </div>
                                        <div class="text-xs text-gray-500 mb-1">
                                            {{ comp.expected_schedule.type === 'timetable' ? 'جدول' : 'دوام' }}
                                            <span v-if="comp.expected_schedule.shift_name" class="mr-1">
                                                ({{ comp.expected_schedule.shift_name }})
                                            </span>
                                        </div>
                                        <div v-if="comp.expected_schedule.scope_label" class="text-xs font-medium" :class="{
                                            'text-indigo-600': comp.expected_schedule.schedule_scope === 'department',
                                            'text-green-600': comp.expected_schedule.schedule_scope === 'institution',
                                            'text-gray-600': comp.expected_schedule.schedule_scope === 'personal'
                                        }">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            {{ comp.expected_schedule.scope_label }}
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                    <div v-if="comp.attendance">
                                        <div class="font-medium">
                                            {{ comp.attendance.check_in_time || '-' }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ comp.attendance.check_out_time || 'لم يتم الانصراف' }}
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="getStatusBadgeClass(comp.comparison_result.status)"
                                          class="px-2 py-1 rounded-full text-xs font-medium">
                                        {{ getStatusLabel(comp.comparison_result.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    <span v-if="comp.comparison_result.is_late" class="bg-yellow-100 text-yellow-800 px-3 py-1.5 rounded-lg text-sm font-semibold inline-block">
                                        {{ formatMinutes(comp.comparison_result.minutes_late) }}
                                    </span>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    <span v-if="comp.comparison_result.is_early_leave" class="bg-orange-100 text-orange-800 px-3 py-1.5 rounded-lg text-sm font-semibold inline-block">
                                        {{ formatMinutes(comp.comparison_result.minutes_early_leave) }}
                                    </span>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    <div class="space-y-2">
                                        <div class="font-medium text-gray-900">
                                            <div class="flex items-center justify-center gap-2">
                                                <span class="text-indigo-600 font-semibold" title="الساعات المتوقعة حسب الجدول">
                                                    {{ formatHours(comp.comparison_result.expected_hours) }}
                                                </span>
                                                <span class="text-gray-400">/</span>
                                                <span class="text-gray-700 font-semibold" title="الساعات الفعلية من البصمات">
                                                    {{ formatHours(comp.comparison_result.actual_hours) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-xs font-semibold px-2 py-1 rounded inline-block" :class="comp.comparison_result.hours_difference >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" :title="comp.comparison_result.hours_difference >= 0 ? 'زيادة في ساعات العمل' : 'نقص في ساعات العمل'">
                                            <span v-if="comp.comparison_result.hours_difference !== 0">
                                                <i :class="comp.comparison_result.hours_difference >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'" class="ml-1"></i>
                                                {{ comp.comparison_result.hours_difference >= 0 ? '+' : '' }}{{ formatHours(Math.abs(comp.comparison_result.hours_difference)) }}
                                            </span>
                                            <span v-else class="text-gray-600">
                                                <i class="fas fa-equals ml-1"></i>
                                                متساوي
                                            </span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </HrLayout>
</template>
