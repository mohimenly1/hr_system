<script setup lang="ts">
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    payslips: Object,
});

const deletePayslip = (payslipId) => {
    if (confirm('هل أنت متأكد من حذف قسيمة الراتب؟ يمكن استرجاعها لاحقاً من سلة المحذوفات.')) {
        router.delete(route('hr.payroll.destroy', payslipId), {
            preserveScroll: true,
            onSuccess: () => {
                // Success message is handled by backend
            },
        });
    }
};

const restorePayslip = (payslipId) => {
    if (confirm('هل تريد استرجاع قسيمة الراتب؟')) {
        router.post(route('hr.payroll.restore', payslipId), {}, {
            preserveScroll: true,
            onSuccess: () => {
                // Success message is handled by backend
            },
        });
    }
};

const forceDeletePayslip = (payslipId) => {
    if (confirm('هل أنت متأكد من الحذف النهائي لقسيمة الراتب؟ لا يمكن التراجع عن هذه العملية.')) {
        router.delete(route('hr.payroll.force-delete', payslipId), {
            preserveScroll: true,
            onSuccess: () => {
                // Success message is handled by backend
            },
        });
    }
};

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
                <div class="flex gap-3">
                    <Link :href="route('hr.payroll.expenses')" class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-2 rounded-lg hover:from-purple-700 hover:to-pink-700 transition-all font-semibold shadow-lg">
                        <i class="fas fa-vault mr-2"></i> خزينة المصروفات
                    </Link>
                    <Link :href="route('hr.payroll.process')" class="bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-2 rounded-lg hover:from-green-700 hover:to-emerald-700 transition-all font-semibold shadow-lg">
                        <i class="fas fa-money-bill-wave mr-2"></i> صرف رواتب جديد
                    </Link>
                </div>
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
                        <tr v-for="payslip in payslips.data" :key="payslip.id" :class="['border-b hover:bg-gray-50', payslip.deleted_at ? 'bg-red-50 opacity-75' : '']">
                            <td class="py-3 px-4 font-medium">
                                {{ payslip.employee?.user?.full_name || payslip.employee?.user?.name || payslip.teacher?.user?.full_name || payslip.teacher?.user?.name }}
                            </td>
                            <td class="py-3 px-4">{{ payslip.month }} / {{ payslip.year }}</td>
                            <td class="py-3 px-4 font-semibold">{{ payslip.net_salary }}</td>
                             <td class="py-3 px-4">
                                <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(payslip.status)">
                                    {{ getStatusText(payslip.status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">{{ payslip.issue_date }}</td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <Link :href="route('hr.payroll.show', payslip.id)" class="text-indigo-600 hover:text-indigo-900 font-semibold">
                                        عرض التفاصيل
                                    </Link>
                                    <div v-if="payslip.deleted_at" class="flex items-center gap-2">
                                        <span class="text-xs text-red-600 bg-red-50 px-2 py-1 rounded">محذوف</span>
                                        <button
                                            @click="restorePayslip(payslip.id)"
                                            class="text-green-600 hover:text-green-900 text-sm font-semibold"
                                            title="استرجاع"
                                        >
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button
                                            @click="forceDeletePayslip(payslip.id)"
                                            class="text-red-600 hover:text-red-900 text-sm font-semibold"
                                            title="حذف نهائي"
                                        >
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                    <button
                                        v-else
                                        @click="deletePayslip(payslip.id)"
                                        class="text-red-600 hover:text-red-900 text-sm font-semibold"
                                        title="حذف"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
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
