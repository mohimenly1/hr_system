<script setup>
import EmployeeLayout from '../../../layouts/EmployeeLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    leave_type: 'annual', // Default value
    start_date: '',
    end_date: '',
    reason: '',
});

const submit = () => {
    form.post(route('employee.leaves.store'), {
        // You can add lifecycle hooks here if needed, e.g., onSuccess
    });
};
</script>

<template>
    <Head title="تقديم طلب إجازة" />

    <EmployeeLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                تقديم طلب إجازة جديد
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-8 bg-white border-b border-gray-200">
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 gap-6">
                                <!-- Leave Type -->
                                <div>
                                    <label for="leave_type" class="block mb-2 text-sm font-medium text-gray-900">نوع الإجازة</label>
                                    <select id="leave_type" v-model="form.leave_type" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full p-2.5" required>
                                        <option value="annual">سنوية</option>
                                        <option value="sick">مرضية</option>
                                        <option value="unpaid">بدون مرتب</option>
                                        <option value="emergency">طارئة</option>
                                    </select>
                                    <div v-if="form.errors.leave_type" class="text-sm text-red-600 mt-1">{{ form.errors.leave_type }}</div>
                                </div>

                                <!-- Start Date -->
                                <div>
                                    <label for="start_date" class="block mb-2 text-sm font-medium text-gray-900">تاريخ البدء</label>
                                    <input type="date" id="start_date" v-model="form.start_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full p-2.5" required>
                                    <div v-if="form.errors.start_date" class="text-sm text-red-600 mt-1">{{ form.errors.start_date }}</div>
                                </div>

                                <!-- End Date -->
                                <div>
                                    <label for="end_date" class="block mb-2 text-sm font-medium text-gray-900">تاريخ الانتهاء</label>
                                    <input type="date" id="end_date" v-model="form.end_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full p-2.5" required>
                                    <div v-if="form.errors.end_date" class="text-sm text-red-600 mt-1">{{ form.errors.end_date }}</div>
                                </div>

                                <!-- Reason -->
                                <div>
                                    <label for="reason" class="block mb-2 text-sm font-medium text-gray-900">السبب (اختياري)</label>
                                    <textarea id="reason" v-model="form.reason" rows="4" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                                    <div v-if="form.errors.reason" class="text-sm text-red-600 mt-1">{{ form.errors.reason }}</div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-6 border-t pt-6">
                                <Link :href="route('employee.leaves.index')" class="text-sm text-gray-600 hover:text-gray-900 mr-4">
                                    إلغاء
                                </Link>

                                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700" :disabled="form.processing">
                                    تقديم الطلب
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </EmployeeLayout>
</template>

