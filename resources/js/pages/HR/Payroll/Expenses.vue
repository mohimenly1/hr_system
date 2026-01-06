<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    expenses: Object,
    summary: Object,
    filters: Object,
});

const form = useForm({
    month: props.filters?.month || null,
    year: props.filters?.year || new Date().getFullYear(),
});

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - i);
const months = [
    { value: 1, name: 'يناير' }, { value: 2, name: 'فبراير' }, { value: 3, name: 'مارس' },
    { value: 4, name: 'أبريل' }, { value: 5, name: 'مايو' }, { value: 6, name: 'يونيو' },
    { value: 7, name: 'يوليو' }, { value: 8, name: 'أغسطس' }, { value: 9, name: 'سبتمبر' },
    { value: 10, name: 'أكتوبر' }, { value: 11, name: 'نوفمبر' }, { value: 12, name: 'ديسمبر' }
];

const applyFilters = () => {
    form.get(route('hr.payroll.expenses'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const getStatusClass = (status) => {
    const classes = {
        'draft': 'bg-gray-100 text-gray-800',
        'completed': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const getStatusLabel = (status) => {
    const labels = {
        'draft': 'مسودة',
        'completed': 'مكتمل',
        'cancelled': 'ملغي',
    };
    return labels[status] || status;
};
</script>

<template>
    <Head title="خزينة مصروفات الرواتب" />
    <HrLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-gray-800">خزينة مصروفات الرواتب</h2>
                <div class="flex gap-3">
                    <Link
                        :href="route('hr.payroll.process')"
                        class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-2 rounded-lg hover:from-indigo-700 hover:to-purple-700 transition-all font-semibold shadow-lg"
                    >
                        <i class="fas fa-money-bill-wave ml-2"></i>
                        صرف رواتب جديدة
                    </Link>
                    <Link
                        :href="route('hr.payroll.index')"
                        class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-all"
                    >
                        <i class="fas fa-arrow-right mr-2"></i>
                        العودة للقائمة
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-green-800">إجمالي المصروفات</p>
                            <p class="text-3xl font-bold text-green-900 mt-2">
                                {{ (parseFloat(summary?.total_amount) || 0).toFixed(2) }} دينار
                            </p>
                        </div>
                        <div class="bg-green-200 rounded-full p-4">
                            <i class="fas fa-money-bill-wave text-green-700 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-blue-800">عدد القسائم</p>
                            <p class="text-3xl font-bold text-blue-900 mt-2">
                                {{ summary?.total_payslips || 0 }}
                            </p>
                        </div>
                        <div class="bg-blue-200 rounded-full p-4">
                            <i class="fas fa-file-invoice text-blue-700 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-50 to-pink-50 border-2 border-purple-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-purple-800">عدد الموظفين</p>
                            <p class="text-3xl font-bold text-purple-900 mt-2">
                                {{ summary?.total_employees || 0 }}
                            </p>
                        </div>
                        <div class="bg-purple-200 rounded-full p-4">
                            <i class="fas fa-users text-purple-700 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-orange-50 to-red-50 border-2 border-orange-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-orange-800">عدد المعلمين</p>
                            <p class="text-3xl font-bold text-orange-900 mt-2">
                                {{ summary?.total_teachers || 0 }}
                            </p>
                        </div>
                        <div class="bg-orange-200 rounded-full p-4">
                            <i class="fas fa-chalkboard-teacher text-orange-700 text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white shadow-lg rounded-xl p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">فلترة البيانات</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الشهر (اختياري)</label>
                        <select
                            v-model="form.month"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                        >
                            <option :value="null">جميع الأشهر</option>
                            <option v-for="month in months" :key="month.value" :value="month.value">{{ month.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">السنة</label>
                        <select
                            v-model="form.year"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                        >
                            <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                        </select>
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

            <!-- Expenses Table -->
            <div class="bg-white shadow-lg rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-800">سجل المصروفات</h3>
                    <p class="text-sm text-gray-600 mt-1">سجل جميع عمليات صرف الرواتب الشهرية</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase">الشهر/السنة</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">إجمالي المبلغ</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">عدد القسائم</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">الموظفين</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">المعلمين</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">الحالة</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">تاريخ الإكمال</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="expense in expenses?.data || []"
                                :key="expense.id"
                                class="hover:bg-gray-50 transition-colors"
                            >
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ months.find(m => m.value === expense.month)?.name || expense.month }} / {{ expense.year }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900">
                                    {{ (parseFloat(expense.total_amount) || 0).toFixed(2) }} دينار
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                    {{ expense.total_payslips }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                    {{ expense.employees_count }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                    {{ expense.teachers_count }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="getStatusClass(expense.status)"
                                          class="px-2 py-1 rounded-full text-xs font-medium">
                                        {{ getStatusLabel(expense.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                    {{ expense.completed_at ? new Date(expense.completed_at).toLocaleDateString('ar-LY') : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <Link
                                        :href="route('hr.payroll.expenses.show', expense.id)"
                                        class="text-indigo-600 hover:text-indigo-900 font-semibold"
                                    >
                                        عرض التفاصيل
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!expenses || !expenses.data || expenses.data.length === 0">
                                <td colspan="8" class="text-center py-8 text-gray-500">لا توجد مصروفات لعرضها</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </HrLayout>
</template>














