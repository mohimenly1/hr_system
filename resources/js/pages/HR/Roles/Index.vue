<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    roles: Array,
    allPermissions: Array,
});

const showModal = ref(false);
const isEditing = ref(false);

const form = useForm({
    id: null,
    name: '',
    permissions: [],
});

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    showModal.value = true;
};

const openEditModal = (role) => {
    isEditing.value = true;
    form.id = role.id;
    form.name = role.name;
    form.permissions = role.permissions.map(p => p.name); // Extract permission names
    showModal.value = true;
};

const submitForm = () => {
    const action = isEditing.value
        ? form.put(route('hr.roles.update', form.id))
        : form.post(route('hr.roles.store'));
    
    action.then(() => {
        if (!form.hasErrors) {
            showModal.value = false;
        }
    });
};
</script>

<template>
    <Head title="إدارة الأدوار والصلاحيات" />
    <HrLayout>
        <template #header>
            إدارة الأدوار والصلاحيات
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">قائمة الأدوار</h2>
                <button @click="openCreateModal" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-plus mr-2"></i> إضافة دور جديد
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">اسم الدور</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الصلاحيات الممنوحة</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="role in roles" :key="role.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4 font-mono font-bold">{{ role.name }}</td>
                            <td class="py-3 px-4">
                                <div class="flex flex-wrap gap-2">
                                    <span v-for="permission in role.permissions" :key="permission.id" 
                                          class="bg-gray-200 text-gray-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                        {{ permission.name }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <button @click="openEditModal(role)" class="text-blue-600 hover:text-blue-900">
                                    تعديل
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl">
                <h3 class="text-xl font-bold text-gray-800 mb-4">{{ isEditing ? 'تعديل الدور' : 'إضافة دور جديد' }}</h3>
                <form @submit.prevent="submitForm">
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-800">اسم الدور (باللغة الإنجليزية)</label>
                        <input type="text" v-model="form.name" id="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-gray-800" required>
                        <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-800 mb-2">الصلاحيات</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 border p-4 rounded-lg bg-gray-50 max-h-64 overflow-y-auto">
                            <label v-for="permission in allPermissions" :key="permission" class="flex items-center">
                                <input type="checkbox" :value="permission" v-model="form.permissions" class="rounded border-gray-300 text-indigo-600 shadow-sm">
                                <span class="ml-2 rtl:mr-2 text-sm text-gray-800">{{ permission }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t space-x-2 rtl:space-x-reverse">
                        <button type="button" @click="showModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="form.processing">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </HrLayout>
</template>

