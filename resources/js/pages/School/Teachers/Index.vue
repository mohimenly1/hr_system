<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    teachers: Object,
});

const getStatusClass = (status) => {
    // ... (same as before)
    switch (status) {
        case 'active': return 'bg-green-100 text-green-800';
        case 'on_leave': return 'bg-yellow-100 text-yellow-800';
        case 'terminated': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head title="إدارة المعلمين" />
    <HrLayout>
        <template #header>
            إدارة المعلمين
        </template>
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">قائمة المعلمين</h2>
                <Link :href="route('school.teachers.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-plus mr-2"></i> إضافة معلم جديد
                </Link>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                     <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الاسم</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">التخصص</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">القسم</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الحالة</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="teacher in teachers.data" :key="teacher.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <div class="font-medium">{{ teacher.user.name }}</div>
                                <div class="text-xs text-gray-500">{{ teacher.user.email }}</div>
                            </td>
                            <td class="py-3 px-4">{{ teacher.specialization }}</td>
                            <td class="py-3 px-4">{{ teacher.department.name }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(teacher.employment_status)">
                                    {{ teacher.employment_status === 'active' ? 'نشط' : (teacher.employment_status === 'on_leave' ? 'في إجازة' : 'منتهية خدمته') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 space-x-4 rtl:space-x-reverse whitespace-nowrap">
                                <!-- THIS IS THE FIX: The link now works because the route exists -->
                                <Link :href="route('school.teachers.show', teacher.id)" class="text-indigo-600 hover:text-indigo-900 font-medium">عرض</Link>
                                <!-- We can add the edit link here in the future -->
                                <Link href="#" class="text-blue-600 hover:text-blue-900 font-medium">تعديل</Link>
                            </td>
                        </tr>
                        <tr v-if="teachers.data.length === 0">
                            <td colspan="5" class="text-center py-4">لا يوجد معلمين لعرضهم.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </HrLayout>
</template>