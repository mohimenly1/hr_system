<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    permissions: Array,
});

const form = useForm({
    name: '',
    permissions: [],
});

const submit = () => {
    form.post(route('hr.roles.store'));
};
</script>

<template>
    <Head title="إضافة دور جديد" />

    <HrLayout>
        <template #header>
            إضافة دور جديد
        </template>

        <div class="max-w-2xl mx-auto bg-white shadow-md rounded-lg p-8">
            <h2 class="text-2xl font-bold mb-6">تفاصيل الدور</h2>
            <form @submit.prevent="submit">
                <div class="mb-6">
                    <label for="name" class="block mb-2 text-sm font-medium text-gray-900">اسم الدور (باللغة الإنجليزية)</label>
                    <input type="text" id="name" v-model="form.name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="e.g., admin, hr-manager" required>
                    <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
                </div>

                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4">الصلاحيات</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div v-for="permission in permissions" :key="permission" class="flex items-center">
                            <input :id="`permission-${permission}`" type="checkbox" :value="permission" v-model="form.permissions" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                            <label :for="`permission-${permission}`" class="ms-2 text-sm font-medium text-gray-900">{{ permission }}</label>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end mt-8">
                    <Link :href="route('hr.roles.index')" class="text-gray-600 hover:text-gray-900 mr-4">
                        إلغاء
                    </Link>
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700" :disabled="form.processing">
                        حفظ الدور
                    </button>
                </div>
            </form>
        </div>
    </HrLayout>
</template>
