<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    contracts: Object,
});

const getStatusClass = (status) => {
    switch (status) {
        case 'active': return 'bg-green-100 text-green-800';
        case 'pending': return 'bg-blue-100 text-blue-800';
        case 'expired': return 'bg-yellow-100 text-yellow-800';
        case 'terminated': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getStatusText = (status) => {
    const statuses = {
        active: 'ساري',
        pending: 'قيد المراجعة',
        expired: 'منتهي',
        terminated: 'ملغي'
    };
    return statuses[status] || status;
};

</script>

<template>
    <Head title="إدارة العقود" />

    <HrLayout>
        <template #header>
            إدارة العقود
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">قائمة العقود</h2>
                <!-- <Link :href="route('hr.contracts.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-plus mr-2"></i> إضافة عقد جديد
                </Link> -->
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الموظف</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الراتب الإجمالي</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الحالة</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">تاريخ البدء</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">تاريخ الانتهاء</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="contract in contracts.data" :key="contract.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <div class="font-medium">{{ contract.employee.user.name }}</div>
                                <div class="text-xs text-gray-500">{{ contract.job_title }}</div>
                            </td>
                            <td class="py-3 px-4 font-semibold">{{ contract.total_salary }}</td>
                             <td class="py-3 px-4">
                                <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(contract.status)">
                                    {{ getStatusText(contract.status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">{{ contract.start_date }}</td>
                            <td class="py-3 px-4">{{ contract.end_date || 'غير محدد' }}</td>
                        </tr>
                        <tr v-if="contracts.data.length === 0">
                            <td colspan="5" class="text-center py-4">لا يوجد عقود لعرضها.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
             <!-- Pagination will be added later -->
        </div>
    </HrLayout>
</template>
