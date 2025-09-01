<script setup>
import EmployeeLayout from '../../../layouts/EmployeeLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    leaves: Object, // The paginated leaves object from the controller
});

const getStatusClass = (status) => {
    switch (status) {
        case 'approved':
            return 'bg-green-100 text-green-800';
        case 'rejected':
            return 'bg-red-100 text-red-800';
        case 'pending':
        default:
            return 'bg-yellow-100 text-yellow-800';
    }
};

const getStatusText = (status) => {
     switch (status) {
        case 'approved':
            return 'مقبول';
        case 'rejected':
            return 'مرفوض';
        case 'pending':
        default:
            return 'قيد المراجعة';
    }
}
</script>

<template>
    <Head title="طلبات الإجازة" />

    <EmployeeLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    سجل طلبات الإجازة
                </h2>
                <Link :href="route('employee.leaves.create')" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    تقديم طلب جديد
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">نوع الإجازة</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">تاريخ البدء</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">تاريخ الانتهاء</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الحالة</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">السبب</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="leave in leaves.data" :key="leave.id">
                                        <td class="px-6 py-4 whitespace-nowrap">{{ leave.leave_type }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ new Date(leave.start_date).toLocaleDateString('ar-EG') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ new Date(leave.end_date).toLocaleDateString('ar-EG') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="getStatusClass(leave.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                {{ getStatusText(leave.status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ leave.reason || '-' }}</td>
                                    </tr>
                                    <tr v-if="leaves.data.length === 0">
                                        <td colspan="5" class="text-center py-4 text-gray-500">
                                            لم تقم بتقديم أي طلبات إجازة بعد.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                         <!-- Pagination can be added here if needed -->
                    </div>
                </div>
            </div>
        </div>
    </EmployeeLayout>
</template>

