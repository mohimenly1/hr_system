<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    roles: Array,
});
</script>

<template>
    <Head title="الأدوار والصلاحيات" />

    <HrLayout>
        <template #header>
            إدارة الأدوار والصلاحيات
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">قائمة الأدوار</h2>
                <Link :href="route('hr.roles.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-plus mr-2"></i> إضافة دور جديد
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">اسم الدور</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الصلاحيات</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="role in roles" :key="role.id" class="border-b">
                            <td class="py-3 px-4 font-bold">{{ role.name }}</td>
                            <td class="py-3 px-4">
                               <div class="flex flex-wrap gap-2">
                                    <span v-for="permission in role.permissions" :key="permission.id" class="bg-blue-100 text-blue-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded">
                                        {{ permission.name }}
                                    </span>
                               </div>
                            </td>
                            <td class="py-3 px-4">
                                <Link href="#" class="text-indigo-600 hover:text-indigo-900">تعديل</Link>
                            </td>
                        </tr>
                        <tr v-if="roles.length === 0">
                            <td colspan="3" class="text-center py-4">لا يوجد أدوار لعرضها.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </HrLayout>
</template>
