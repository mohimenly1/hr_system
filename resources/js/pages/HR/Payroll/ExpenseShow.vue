<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    expense: Object,
});

const months = [
    { value: 1, name: 'يناير' }, { value: 2, name: 'فبراير' }, { value: 3, name: 'مارس' },
    { value: 4, name: 'أبريل' }, { value: 5, name: 'مايو' }, { value: 6, name: 'يونيو' },
    { value: 7, name: 'يوليو' }, { value: 8, name: 'أغسطس' }, { value: 9, name: 'سبتمبر' },
    { value: 10, name: 'أكتوبر' }, { value: 11, name: 'نوفمبر' }, { value: 12, name: 'ديسمبر' }
];

const monthName = computed(() => {
    return months.find(m => m.value === props.expense.month)?.name || props.expense.month;
});

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
    <Head :title="`تفاصيل المصروفات - ${monthName} ${expense.year}`" />
    <HrLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">تفاصيل المصروفات</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ monthName }} {{ expense.year }}</p>
                </div>
                <Link
                    :href="route('hr.payroll.expenses')"
                    class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-all"
                >
                    <i class="fas fa-arrow-right mr-2"></i>
                    العودة للقائمة
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-6 shadow-lg">
                    <p class="text-sm font-medium text-green-800">إجمالي المبلغ</p>
                    <p class="text-3xl font-bold text-green-900 mt-2">
                        {{ (parseFloat(expense.total_amount) || 0).toFixed(2) }} دينار
                    </p>
                </div>
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-xl p-6 shadow-lg">
                    <p class="text-sm font-medium text-blue-800">عدد القسائم</p>
                    <p class="text-3xl font-bold text-blue-900 mt-2">
                        {{ expense.total_payslips }}
                    </p>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 border-2 border-purple-200 rounded-xl p-6 shadow-lg">
                    <p class="text-sm font-medium text-purple-800">عدد الموظفين</p>
                    <p class="text-3xl font-bold text-purple-900 mt-2">
                        {{ expense.employees_count }}
                    </p>
                </div>
                <div class="bg-gradient-to-br from-orange-50 to-red-50 border-2 border-orange-200 rounded-xl p-6 shadow-lg">
                    <p class="text-sm font-medium text-orange-800">عدد المعلمين</p>
                    <p class="text-3xl font-bold text-orange-900 mt-2">
                        {{ expense.teachers_count }}
                    </p>
                </div>
            </div>

            <!-- Payslips Table -->
            <div class="bg-white shadow-lg rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-800">قسائم الرواتب</h3>
                    <p class="text-sm text-gray-600 mt-1">جميع قسائم الرواتب المصروفة في هذه الدفعة</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase">الاسم</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">النوع</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">الراتب الإجمالي</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">الإضافات</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">الخصومات</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">صافي الراتب</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">الحالة</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="payslip in expense.payslips"
                                :key="payslip.id"
                                class="hover:bg-gray-50 transition-colors"
                            >
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ payslip.employee?.user?.full_name || payslip.employee?.user?.name || payslip.teacher?.user?.full_name || payslip.teacher?.user?.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                    {{ payslip.employee_id ? 'موظف' : 'معلم' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                    {{ (parseFloat(payslip.gross_salary) || 0).toFixed(2) }} دينار
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-green-600">
                                    {{ (parseFloat(payslip.total_earnings) || 0).toFixed(2) }} دينار
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-red-600">
                                    {{ (parseFloat(payslip.total_deductions) || 0).toFixed(2) }} دينار
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900">
                                    {{ (parseFloat(payslip.net_salary) || 0).toFixed(2) }} دينار
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium"
                                          :class="payslip.status === 'paid' ? 'bg-green-100 text-green-800' :
                                                  payslip.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                                  'bg-red-100 text-red-800'">
                                        {{ payslip.status === 'paid' ? 'مدفوع' : payslip.status === 'pending' ? 'قيد الدفع' : 'ملغي' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <Link
                                        :href="route('hr.payroll.show', payslip.id)"
                                        class="text-indigo-600 hover:text-indigo-900 font-semibold"
                                    >
                                        عرض التفاصيل
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="expense.payslips.length === 0">
                                <td colspan="8" class="text-center py-8 text-gray-500">لا توجد قسائم رواتب</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </HrLayout>
</template>














