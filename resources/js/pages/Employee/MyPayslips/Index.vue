<script setup>
import EmployeeLayout from '../../../layouts/EmployeeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    payslips: Object,
});

const getMonthName = (monthNumber) => {
    const date = new Date();
    date.setMonth(monthNumber - 1);
    return date.toLocaleString('ar', { month: 'long' });
};
</script>

<template>
    <Head title="قسائم الرواتب" />

    <EmployeeLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                قسائم الرواتب الخاصة بي
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الشهر</th>
                                        <th class="text-right py-3 px-4 uppercase font-semibold text-sm">تاريخ الإصدار</th>
                                        <th class="text-right py-3 px-4 uppercase font-semibold text-sm">صافي الراتب</th>
                                        <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الحالة</th>
                                        <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700">
                                    <tr v-for="payslip in payslips.data" :key="payslip.id" class="border-b hover:bg-gray-50">
                                        <td class="py-3 px-4">{{ getMonthName(payslip.month) }} {{ payslip.year }}</td>
                                        <td class="py-3 px-4">{{ new Date(payslip.issue_date).toLocaleDateString('ar-EG') }}</td>
                                        <td class="py-3 px-4 font-mono">{{ parseFloat(payslip.net_salary).toFixed(2) }}</td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-1 text-xs rounded-full" :class="{ 'bg-green-100 text-green-800': payslip.status === 'paid', 'bg-yellow-100 text-yellow-800': payslip.status === 'pending' }">
                                                {{ payslip.status === 'paid' ? 'مدفوع' : 'قيد الدفع' }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <!-- The link now points to the correct route -->
                                            <Link :href="route('employee.payslips.show', payslip.id)" class="text-indigo-600 hover:text-indigo-900 font-semibold">
                                                عرض التفاصيل
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="payslips.data.length === 0">
                                        <td colspan="5" class="text-center py-4">لا يوجد قسائم رواتب لعرضها.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination will go here -->
                    </div>
                </div>
            </div>
        </div>
    </EmployeeLayout>
</template>

