<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    employees: Array,
});

const leaveTypes = ['إجازة سنوية', 'إجازة مرضية', 'إجازة عارضة', 'إجازة بدون راتب', 'أخرى'];

const form = useForm({
    employee_id: '',
    leave_type: 'إجازة سنوية',
    start_date: '',
    end_date: '',
    reason: '',
    status: 'pending',
});

const submit = () => {
    form.post(route('hr.leaves.store'));
};
</script>

<template>
    <Head title="طلب إجازة جديد" />

    <HrLayout>
        <template #header>
            طلب إجازة جديد
        </template>

        <div class="max-w-2xl mx-auto bg-white shadow-md rounded-lg p-8">
            <h2 class="text-2xl font-bold mb-6">تفاصيل طلب الإجازة</h2>
            <form @submit.prevent="submit">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="employee_id" class="block mb-2 text-sm font-medium text-gray-900">الموظف</label>
                        <select id="employee_id" v-model="form.employee_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <option value="" disabled>اختر موظف</option>
                            <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                        </select>
                        <div v-if="form.errors.employee_id" class="text-sm text-red-600 mt-1">{{ form.errors.employee_id }}</div>
                    </div>

                    <div>
                        <label for="leave_type" class="block mb-2 text-sm font-medium text-gray-900">نوع الإجازة</label>
                        <select id="leave_type" v-model="form.leave_type" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <option v-for="type in leaveTypes" :key="type" :value="type">{{ type }}</option>
                        </select>
                    </div>
                     <div>
                        <label for="status" class="block mb-2 text-sm font-medium text-gray-900">حالة الطلب</label>
                        <select id="status" v-model="form.status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <option value="pending">قيد المراجعة</option>
                            <option value="approved">موافق عليها</option>
                            <option value="rejected">مرفوضة</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="start_date" class="block mb-2 text-sm font-medium text-gray-900">تاريخ البدء</label>
                        <input type="date" id="start_date" v-model="form.start_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                        <div v-if="form.errors.start_date" class="text-sm text-red-600 mt-1">{{ form.errors.start_date }}</div>
                    </div>
                    <div>
                        <label for="end_date" class="block mb-2 text-sm font-medium text-gray-900">تاريخ الانتهاء</label>
                        <input type="date" id="end_date" v-model="form.end_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                        <div v-if="form.errors.end_date" class="text-sm text-red-600 mt-1">{{ form.errors.end_date }}</div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="reason" class="block mb-2 text-sm font-medium text-gray-900">سبب الإجازة</label>
                        <textarea id="reason" rows="4" v-model="form.reason" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300" required></textarea>
                        <div v-if="form.errors.reason" class="text-sm text-red-600 mt-1">{{ form.errors.reason }}</div>
                    </div>
                </div>

                <div class="flex items-center justify-end mt-8">
                    <Link :href="route('hr.leaves.index')" class="text-gray-600 hover:text-gray-900 mr-4">
                        إلغاء
                    </Link>
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700" :disabled="form.processing">
                        حفظ الطلب
                    </button>
                </div>
            </form>
        </div>
    </HrLayout>
</template>
