<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    leaves: Object,
});

const form = useForm({
    status: '',
});

const updateLeaveStatus = (leaveId, newStatus) => {
    if (confirm('هل أنت متأكد من تغيير حالة هذا الطلب؟')) {
        form.status = newStatus;
        form.put(route('hr.leaves.update', leaveId), {
            preserveScroll: true, // Keep the user at the same scroll position
        });
    }
};

const getStatusClass = (status) => {
    switch (status) {
        case 'approved': return 'bg-green-100 text-green-800';
        case 'rejected': return 'bg-red-100 text-red-800';
        case 'pending': default: return 'bg-yellow-100 text-yellow-800';
    }
};

const getStatusText = (status) => {
     switch (status) {
        case 'approved': return 'مقبول';
        case 'rejected': return 'مرفوض';
        case 'pending': default: return 'قيد المراجعة';
    }
};
</script>

<template>
    <Head title="إدارة طلبات الإجازة" />

    <HrLayout>
        <template #header>
            إدارة طلبات الإجازة
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-bold mb-4">قائمة الطلبات</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">اسم الموظف</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">نوع الإجازة</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">من تاريخ</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">إلى تاريخ</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الحالة</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="leave in leaves.data" :key="leave.id" class="border-b">
                            <td class="py-3 px-4">{{ leave.employee.user.name }}</td>
                            <td class="py-3 px-4">{{ leave.leave_type }}</td>
                            <td class="py-3 px-4">{{ new Date(leave.start_date).toLocaleDateString('ar-EG') }}</td>
                            <td class="py-3 px-4">{{ new Date(leave.end_date).toLocaleDateString('ar-EG') }}</td>
                            <td class="py-3 px-4">
                               <span :class="getStatusClass(leave.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                   {{ getStatusText(leave.status) }}
                               </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div v-if="leave.status === 'pending'" class="flex justify-center items-center space-x-2">
                                    <button @click="updateLeaveStatus(leave.id, 'approved')" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 text-sm">
                                        موافقة
                                    </button>
                                    <button @click="updateLeaveStatus(leave.id, 'rejected')" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 text-sm">
                                        رفض
                                    </button>
                                </div>
                                <span v-else class="text-gray-400 text-sm">تم اتخاذ إجراء</span>
                            </td>
                        </tr>
                        <tr v-if="leaves.data.length === 0">
                            <td colspan="6" class="text-center py-4">لا يوجد طلبات إجازة لعرضها.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
             <!-- Pagination -->
        </div>
    </HrLayout>
</template>

