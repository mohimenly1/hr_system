<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    attendances: Object,
});

const getStatusClass = (status) => {
    return {
        'bg-green-100 text-green-800': status === 'present',
        'bg-yellow-100 text-yellow-800': status === 'late',
        'bg-red-100 text-red-800': status === 'absent',
        'bg-blue-100 text-blue-800': status === 'on_leave',
        'bg-gray-100 text-gray-800': status === 'holiday',
    }[status] || 'bg-gray-100 text-gray-800';
};

const getStatusText = (status) => {
    return {
        'present': 'حاضر',
        'late': 'متأخر',
        'absent': 'غائب',
        'on_leave': 'إجازة',
        'holiday': 'عطلة رسمية',
    }[status] || status;
};
</script>

<template>
    <Head title="سجل الحضور والانصراف" />

    <HrLayout>
        <template #header>
            سجل الحضور والانصراف
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">سجل الحضور اليومي</h2>
                <Link :href="route('hr.attendances.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-plus mr-2"></i> تسجيل حضور جديد
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الموظف</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">التاريخ</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">وقت الدخول</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">وقت الخروج</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الحالة</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="attendance in attendances.data" :key="attendance.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium">{{ attendance.employee.user.name }}</td>
                            <td class="py-3 px-4">{{ attendance.attendance_date }}</td>
                            <td class="py-3 px-4">{{ attendance.check_in_time || '---' }}</td>
                            <td class="py-3 px-4">{{ attendance.check_out_time || '---' }}</td>
                             <td class="py-3 px-4">
                                <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(attendance.status)">
                                    {{ getStatusText(attendance.status) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="attendances.data.length === 0">
                            <td colspan="5" class="text-center py-4">لا يوجد سجلات حضور لعرضها.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </HrLayout>
</template>
