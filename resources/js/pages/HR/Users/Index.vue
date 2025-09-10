<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import throttle from 'lodash/throttle';
import pickBy from 'lodash/pickBy';

const props = defineProps({
    users: Object,
    filters: Object,
    roles: Array,
});

const search = ref(props.filters.search);
const roleFilter = ref(props.filters.role || '');
const statusFilter = ref(props.filters.status || '');

watch([search, roleFilter, statusFilter], throttle(() => {
    router.get(route('hr.users.index'), pickBy({
        search: search.value,
        role: roleFilter.value,
        status: statusFilter.value,
    }), {
        preserveState: true,
        replace: true,
    });
}, 300));

const toggleUserStatus = (user) => {
    const newStatus = !user.is_active;
    router.put(route('hr.users.update.status', user.id), {
        is_active: newStatus
    }, {
        preserveScroll: true,
        onSuccess: () => {
            // Success message will be handled by flash props
        }
    });
};
</script>

<template>
    <Head title="إدارة المستخدمين" />
    <HrLayout>
        <template #header>
            إدارة المستخدمين
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">قائمة المستخدمين</h2>

            <!-- Filters -->
            <div class="flex items-center justify-between mb-4 bg-gray-50 p-3 rounded-md">
                <div class="flex items-center space-x-4 rtl:space-x-reverse">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-search text-gray-400"></i>
                        </span>
                        <input v-model="search" type="text" placeholder="ابحث بالاسم أو البريد..." class="w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-md text-gray-800 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <select v-model="roleFilter" class="border border-gray-300 rounded-md text-gray-800 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">كل الأدوار</option>
                            <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                        </select>
                    </div>
                     <div>
                        <select v-model="statusFilter" class="border border-gray-300 rounded-md text-gray-800 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">كل الحالات</option>
                            <option value="active">نشط</option>
                            <option value="inactive">غير نشط</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">المستخدم</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الأدوار</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الحالة</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-if="users.data.length === 0">
                            <td colspan="4" class="text-center py-6 text-gray-500">لا توجد بيانات تطابق البحث.</td>
                        </tr>
                        <tr v-for="user in users.data" :key="user.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <div class="font-medium text-gray-900">{{ user.name }}</div>
                                <div class="text-xs text-gray-500">{{ user.email }}</div>
                            </td>
                            <td class="py-3 px-4">
                               <div class="flex flex-wrap gap-2">
                                    <span v-for="role in user.roles" :key="role.id" class="bg-gray-200 text-gray-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                        {{ role.name }}
                                    </span>
                               </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-3 py-1 text-xs font-semibold leading-5 rounded-full" 
                                      :class="user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                    {{ user.is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center space-x-4 rtl:space-x-reverse">
                                    <label class="flex items-center cursor-pointer">
                                        <div class="relative">
                                            <input type="checkbox" :checked="user.is_active" @change="toggleUserStatus(user)" class="sr-only">
                                            <div class="block w-10 h-6 rounded-full" :class="user.is_active ? 'bg-green-500' : 'bg-gray-300'"></div>
                                            <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform" :class="{ 'translate-x-4': user.is_active }"></div>
                                        </div>
                                    </label>
                                    <Link :href="route('hr.users.edit', user.id)" class="text-indigo-600 hover:text-indigo-900">
                                        تعديل الأدوار
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div v-if="users.data.length > 0" class="mt-6 flex justify-between items-center">
                <div class="text-sm text-gray-700">
                    عرض {{ users.from }} إلى {{ users.to }} من {{ users.total }} نتيجة
                </div>
                <div class="flex">
                    <Link
                        v-for="(link, index) in users.links"
                        :key="index"
                        :href="link.url || '#'"
                        class="px-3 py-2 text-sm leading-4 rounded-md"
                        :class="{
                            'bg-indigo-600 text-white': link.active,
                            'text-gray-700 hover:bg-gray-200': !link.active && link.url,
                            'text-gray-400 cursor-not-allowed': !link.url
                        }"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </HrLayout>
</template>

<style scoped>
.dot {
    transform: translateX(0);
}
</style>
