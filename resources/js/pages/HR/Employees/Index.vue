<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    employees: Object,
});

const getStatusClass = (status) => {
    switch (status) {
        case 'active': return 'bg-green-100 text-green-800';
        case 'on_leave': return 'bg-yellow-100 text-yellow-800';
        case 'terminated': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

</script>

<template>
    <Head title="قائمة الموظفين" />

    <HrLayout>
        <template #header>
            إدارة الموظفين
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">قائمة الموظفين</h2>
                <Link :href="route('hr.employees.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-plus mr-2"></i> إضافة موظف جديد
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الاسم</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">القسم</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">المسمى الوظيفي</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">رقم الهاتف</th>
                             <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الحالة</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="employee in employees.data" :key="employee.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <div class="font-medium text-gray-900">{{ employee.user.name }}</div>
                                <div class="text-xs text-gray-500">{{ employee.user.email }}</div>
                            </td>
                            <td class="py-3 px-4">{{ employee.department.name }}</td>
                            <td class="py-3 px-4">{{ employee.job_title }}</td>
                            <td class="py-3 px-4">{{ employee.phone_number || '-' }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(employee.employment_status)">
                                    {{ employee.employment_status === 'active' ? 'نشط' : (employee.employment_status === 'on_leave' ? 'في إجازة' : 'منتهية خدمته') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <Link :href="route('hr.employees.show', employee.id)" class="text-indigo-600 hover:text-indigo-900 font-medium me-4">عرض</Link>
                                <Link :href="route('hr.employees.edit', employee.id)" class="text-blue-600 hover:text-blue-900 font-medium">تعديل</Link>
                            </td>
                        </tr>
                        <tr v-if="employees.data.length === 0">
                            <td colspan="6" class="text-center py-4">لا يوجد موظفين لعرضهم.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
             <!-- Pagination will be added later -->
        </div>

    </HrLayout>
</template>
