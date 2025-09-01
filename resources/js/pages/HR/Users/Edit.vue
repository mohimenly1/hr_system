<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    user: Object,
    roles: Array,
});

// Initialize form with the user's current roles
const form = useForm({
    roles: props.user.roles.map(role => role.name),
});

const submit = () => {
    form.put(route('hr.users.update', props.user.id));
};

</script>

<template>
    <Head :title="`تعديل أدوار - ${user.name}`" />

    <HrLayout>
        <template #header>
            تعديل أدوار المستخدم
        </template>

        <div class="max-w-2xl mx-auto bg-white shadow-md rounded-lg p-8">
            <h2 class="text-2xl font-bold mb-6">تعديل أدوار: {{ user.name }}</h2>
            <p class="mb-6 text-gray-600">البريد الإلكتروني: {{ user.email }}</p>
            
            <form @submit.prevent="submit">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">الأدوار المتاحة</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div v-for="role in roles" :key="role.id" class="flex items-center p-2 rounded-md hover:bg-gray-50">
                            <input 
                                :id="`role-${role.id}`" 
                                type="checkbox" 
                                :value="role.name" 
                                v-model="form.roles" 
                                class="w-4 h-4 text-indigo-600 bg-gray-100 border-gray-300 rounded focus:ring-indigo-500"
                            >
                            <label :for="`role-${role.id}`" class="ms-2 text-sm font-medium text-gray-900">{{ role.name }}</label>
                        </div>
                    </div>
                     <div v-if="form.errors.roles" class="text-sm text-red-600 mt-2">{{ form.errors.roles }}</div>
                </div>

                <div class="flex items-center justify-end mt-8">
                    <Link :href="route('hr.users.index')" class="text-gray-600 hover:text-gray-900 mr-4">
                        إلغاء
                    </Link>
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700" :disabled="form.processing">
                        حفظ التغييرات
                    </button>
                </div>
            </form>
        </div>
    </HrLayout>
</template>
