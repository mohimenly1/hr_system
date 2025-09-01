<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    users: Object, // Inertia pagination object
});
</script>

<template>
    <Head title="إدارة المستخدمين" />

    <HrLayout>
        <template #header>
            إدارة المستخدمين
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-bold mb-4">قائمة المستخدمين</h2>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">اسم المستخدم</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">البريد الإلكتروني</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الأدوار</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="user in users.data" :key="user.id" class="border-b">
                            <td class="py-3 px-4">{{ user.name }}</td>
                            <td class="py-3 px-4">{{ user.email }}</td>
                            <td class="py-3 px-4">
                               <div class="flex flex-wrap gap-2">
                                    <span v-for="role in user.roles" :key="role.id" class="bg-green-100 text-green-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded">
                                        {{ role.name }}
                                    </span>
                                    <span v-if="user.roles.length === 0" class="text-gray-500">لا يوجد أدوار</span>
                               </div>
                            </td>
                            <td class="py-3 px-4">
                                <Link :href="route('hr.users.edit', user.id)" class="text-indigo-600 hover:text-indigo-900">
                                    تعديل الأدوار
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="4" class="text-center py-4">لا يوجد مستخدمين لعرضهم.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
             <!-- Pagination will go here if needed -->
        </div>
    </HrLayout>
</template>
