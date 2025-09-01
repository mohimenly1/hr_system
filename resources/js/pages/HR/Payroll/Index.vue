<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    payslips: Object,
});

const getStatusClass = (status) => {
    switch (status) {
        case 'paid': return 'bg-green-100 text-green-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getStatusText = (status) => {
    const statuses = {
        paid: 'مدفوع',
        pending: 'قيد الدفع',
        cancelled: 'ملغي'
    };
    return statuses[status] || status;
};

</script>

<template>
    <Head title="إدارة الرواتب" />

    <HrLayout>
        <template #header>
            إدارة الرواتب
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">قائمة الرواتب</h2>
                <Link :href="route('hr.payroll.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-cogs mr-2"></i> إنشاء رواتب شهر جديد
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الموظف</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الشهر/السنة</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">صافي الراتب</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الحالة</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">تاريخ الإصدار</th>
                             <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="payslip in payslips.data" :key="payslip.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium">{{ payslip.employee.user.name }}</td>
                            <td class="py-3 px-4">{{ payslip.month }} / {{ payslip.year }}</td>
                            <td class="py-3 px-4 font-semibold">{{ payslip.net_salary }}</td>
                             <td class="py-3 px-4">
                                <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(payslip.status)">
                                    {{ getStatusText(payslip.status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">{{ payslip.issue_date }}</td>
                            <Link :href="route('hr.payroll.show', payslip.id)" class="text-indigo-600 hover:text-indigo-900 font-semibold">
                                    عرض التفاصيل
                                </Link>
                        </tr>
                        <tr v-if="payslips.data.length === 0">
                            <td colspan="6" class="text-center py-4">لا يوجد قسائم رواتب لعرضها.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
             <!-- Pagination will be added later -->
        </div>
    </HrLayout>
</template>
