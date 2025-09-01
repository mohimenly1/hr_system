<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    employees: Array,
});

const form = useForm({
    employee_id: '',
    contract_type: 'محدد المدة',
    start_date: '',
    end_date: '',
    probation_end_date: '',
    job_title: '',
    status: 'pending',
    basic_salary: '',
    housing_allowance: 0,
    transportation_allowance: 0,
    other_allowances: 0,
    working_hours_per_day: 8,
    annual_leave_days: 21,
    notice_period_days: 30,
    notes: '',
});

const submit = () => {
    form.post(route('hr.contracts.store'));
};
</script>

<template>
    <Head title="إضافة عقد جديد" />

    <HrLayout>
        <template #header>
            إضافة عقد جديد
        </template>

        <div class="max-w-4xl mx-auto bg-white shadow-md rounded-lg p-8">
            <h2 class="text-2xl font-bold mb-6">تفاصيل العقد</h2>
            <form @submit.prevent="submit">
                <!-- Contract Details -->
                <div class="border-b pb-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">1. تفاصيل العقد الأساسية</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label for="employee_id" class="block mb-2 text-sm font-medium text-gray-900">الموظف</label>
                            <select id="employee_id" v-model="form.employee_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                                <option value="" disabled>اختر موظف</option>
                                <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                            </select>
                            <div v-if="form.errors.employee_id" class="text-sm text-red-600 mt-1">{{ form.errors.employee_id }}</div>
                        </div>
                        <div>
                            <label for="job_title" class="block mb-2 text-sm font-medium text-gray-900">المسمى الوظيفي</label>
                            <input type="text" id="job_title" v-model="form.job_title" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <div v-if="form.errors.job_title" class="text-sm text-red-600 mt-1">{{ form.errors.job_title }}</div>
                        </div>
                        <div>
                            <label for="contract_type" class="block mb-2 text-sm font-medium text-gray-900">نوع العقد</label>
                            <select id="contract_type" v-model="form.contract_type" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                                <option>محدد المدة</option>
                                <option>غير محدد المدة</option>
                                <option>دوام جزئي</option>
                                <option>تدريب</option>
                            </select>
                        </div>
                        <div>
                            <label for="start_date" class="block mb-2 text-sm font-medium text-gray-900">تاريخ البدء</label>
                            <input type="date" id="start_date" v-model="form.start_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <div v-if="form.errors.start_date" class="text-sm text-red-600 mt-1">{{ form.errors.start_date }}</div>
                        </div>
                        <div>
                            <label for="end_date" class="block mb-2 text-sm font-medium text-gray-900">تاريخ الانتهاء</label>
                            <input type="date" id="end_date" v-model="form.end_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                        <div>
                            <label for="probation_end_date" class="block mb-2 text-sm font-medium text-gray-900">نهاية الفترة التجريبية</label>
                            <input type="date" id="probation_end_date" v-model="form.probation_end_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                    </div>
                </div>

                <!-- Salary Details -->
                 <div class="border-b pb-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">2. تفاصيل الراتب</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                         <div>
                            <label for="basic_salary" class="block mb-2 text-sm font-medium text-gray-900">الراتب الأساسي</label>
                            <input type="number" step="0.01" id="basic_salary" v-model="form.basic_salary" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <div v-if="form.errors.basic_salary" class="text-sm text-red-600 mt-1">{{ form.errors.basic_salary }}</div>
                        </div>
                         <div>
                            <label for="housing_allowance" class="block mb-2 text-sm font-medium text-gray-900">بدل السكن</label>
                            <input type="number" step="0.01" id="housing_allowance" v-model="form.housing_allowance" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                         <div>
                            <label for="transportation_allowance" class="block mb-2 text-sm font-medium text-gray-900">بدل المواصلات</label>
                            <input type="number" step="0.01" id="transportation_allowance" v-model="form.transportation_allowance" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                         <div>
                            <label for="other_allowances" class="block mb-2 text-sm font-medium text-gray-900">بدلات أخرى</label>
                            <input type="number" step="0.01" id="other_allowances" v-model="form.other_allowances" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                    </div>
                 </div>

                <!-- Terms & Conditions -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">3. شروط العمل</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <label for="status" class="block mb-2 text-sm font-medium text-gray-900">حالة العقد</label>
                            <select id="status" v-model="form.status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                                <option value="pending">قيد المراجعة</option>
                                <option value="active">ساري</option>
                                <option value="expired">منتهي</option>
                                <option value="terminated">ملغي</option>
                            </select>
                        </div>
                        <div>
                            <label for="working_hours_per_day" class="block mb-2 text-sm font-medium text-gray-900">ساعات العمل/يوم</label>
                            <input type="number" id="working_hours_per_day" v-model="form.working_hours_per_day" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                        <div>
                            <label for="annual_leave_days" class="block mb-2 text-sm font-medium text-gray-900">أيام الإجازة السنوية</label>
                            <input type="number" id="annual_leave_days" v-model="form.annual_leave_days" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                        <div>
                            <label for="notice_period_days" class="block mb-2 text-sm font-medium text-gray-900">فترة الإشعار (يوم)</label>
                            <input type="number" id="notice_period_days" v-model="form.notice_period_days" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                        <div class="lg:col-span-4">
                            <label for="notes" class="block mb-2 text-sm font-medium text-gray-900">ملاحظات</label>
                            <textarea id="notes" rows="3" v-model="form.notes" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end mt-8">
                    <Link :href="route('hr.contracts.index')" class="text-gray-600 hover:text-gray-900 mr-4">
                        إلغاء
                    </Link>
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700" :disabled="form.processing">
                        حفظ العقد
                    </button>
                </div>
            </form>
        </div>
    </HrLayout>
</template>