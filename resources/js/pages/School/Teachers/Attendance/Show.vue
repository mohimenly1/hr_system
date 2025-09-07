<script setup>
import HrLayout from '../../../../layouts/HrLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { ar } from 'date-fns/locale';

const props = defineProps({
    teacher: Object,
    attendances: Object,
});

const getStatusInfo = (status) => {
    const statuses = {
        present: { text: 'حاضر', class: 'bg-green-100 text-green-800' },
        absent: { text: 'غائب', class: 'bg-red-100 text-red-800' },
        late: { text: 'متأخر', class: 'bg-yellow-100 text-yellow-800' },
        on_leave: { text: 'إجازة', class: 'bg-blue-100 text-blue-800' },
        holiday: { text: 'عطلة', class: 'bg-indigo-100 text-indigo-800' },
    };
    return statuses[status] || { text: status, class: 'bg-gray-100 text-gray-800' };
};

const formatDate = (dateString) => {
    // التأكد من أن dateString ليس فارغاً قبل التنسيق
    if (!dateString) return '';
    return format(new Date(dateString), 'EEEE, d MMMM yyyy', { locale: ar });
};
</script>

<template>
    <Head :title="`سجل حضور - ${teacher.user.name}`" />

    <HrLayout>
        <template #header>
            سجل الحضور
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex items-center justify-between mb-6 pb-4 border-b">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        {{ teacher.user.name }}
                    </h2>
                    <p class="text-gray-500">التخصص: {{ teacher.specialization }}</p>
                </div>
                <Link :href="route('school.teachers.index')" class="text-indigo-600 hover:text-indigo-800 font-medium">
                    <i class="fas fa-arrow-left ml-2"></i>
                    العودة لقائمة المعلمين
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-right py-3 px-4 font-semibold text-sm text-gray-600">التاريخ</th>
                            <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600">وقت الدخول</th>
                            <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600">وقت الخروج</th>
                            <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600">الحالة</th>
                            <th class="text-right py-3 px-4 font-semibold text-sm text-gray-600">ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="record in attendances.data" :key="record.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium">{{ formatDate(record.attendance_date) }}</td>
                            <td class="py-3 px-4 text-center font-mono">{{ record.check_in_time || '---' }}</td>
                            <td class="py-3 px-4 text-center font-mono">{{ record.check_out_time || '---' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-3 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusInfo(record.status).class">
                                    {{ getStatusInfo(record.status).text }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-sm">{{ record.notes || 'لا يوجد' }}</td>
                        </tr>
                        <tr v-if="attendances.data.length === 0">
                            <td colspan="5" class="text-center py-8 text-gray-500">لا يوجد سجلات حضور لعرضها.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- يمكنك إضافة Pagination هنا إذا لزم الأمر -->
        </div>
    </HrLayout>
</template>
