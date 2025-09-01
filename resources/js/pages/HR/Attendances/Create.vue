<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    employees: Array,
    today: String,
});

const form = useForm({
    employee_id: '',
    attendance_date: props.today,
    check_in_time: '',
    check_out_time: '',
    status: 'present',
    notes: '',
});

const submit = () => {
    form.post(route('hr.attendances.store'));
};
</script>

<template>
    <Head title="تسجيل حضور جديد" />

    <HrLayout>
        <template #header>
            تسجيل حضور جديد
        </template>

        <div class="max-w-2xl mx-auto bg-white shadow-md rounded-lg p-8">
            <h2 class="text-2xl font-bold mb-6">تسجيل الحضور اليومي</h2>
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
                        <label for="attendance_date" class="block mb-2 text-sm font-medium text-gray-900">التاريخ</label>
                        <input type="date" id="attendance_date" v-model="form.attendance_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                         <div v-if="form.errors.attendance_date" class="text-sm text-red-600 mt-1">{{ form.errors.attendance_date }}</div>
                    </div>

                     <div>
                        <label for="status" class="block mb-2 text-sm font-medium text-gray-900">الحالة</label>
                        <select id="status" v-model="form.status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                           <option value="present">حاضر</option>
                           <option value="late">متأخر</option>
                           <option value="absent">غائب</option>
                           <option value="on_leave">إجازة</option>
                           <option value="holiday">عطلة رسمية</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="check_in_time" class="block mb-2 text-sm font-medium text-gray-900">وقت الدخول</label>
                        <input type="time" id="check_in_time" v-model="form.check_in_time" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        <div v-if="form.errors.check_in_time" class="text-sm text-red-600 mt-1">{{ form.errors.check_in_time }}</div>
                    </div>
                    <div>
                        <label for="check_out_time" class="block mb-2 text-sm font-medium text-gray-900">وقت الخروج</label>
                        <input type="time" id="check_out_time" v-model="form.check_out_time" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        <div v-if="form.errors.check_out_time" class="text-sm text-red-600 mt-1">{{ form.errors.check_out_time }}</div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="notes" class="block mb-2 text-sm font-medium text-gray-900">ملاحظات</label>
                        <textarea id="notes" rows="3" v-model="form.notes" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end mt-8">
                    <Link :href="route('hr.attendances.index')" class="text-gray-600 hover:text-gray-900 mr-4">
                        إلغاء
                    </Link>
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700" :disabled="form.processing">
                        حفظ السجل
                    </button>
                </div>
            </form>
        </div>
    </HrLayout>
</template>
