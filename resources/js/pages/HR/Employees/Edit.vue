<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    employee: Object,
    departments: Array,
});

const getLatestContract = () => {
    if (props.employee.contracts && props.employee.contracts.length > 0) {
        return props.employee.contracts[0];
    }
    return {
        contract_type: 'محدد المدة', start_date: '', end_date: null, probation_end_date: null,
        job_title: '', status: 'active', basic_salary: '', housing_allowance: 0,
        transportation_allowance: 0, other_allowances: 0, working_hours_per_day: 8,
        annual_leave_days: 21, notice_period_days: 30, notes: ''
    };
};

const latestContract = getLatestContract();
const currentStep = ref(1);
const stepErrors = ref({});

const form = useForm({
    personal: {
        phone_number: props.employee.phone_number || '',
        address: props.employee.address || '',
        date_of_birth: props.employee.date_of_birth || '',
        gender: props.employee.gender || '',
    },
    employment: {
        department_id: props.employee.department_id || '',
        employee_id: props.employee.employee_id || '',
        hire_date: props.employee.hire_date || '',
        employment_status: props.employee.employment_status || 'active',
        job_title: latestContract.job_title || props.employee.job_title,
        contract_type: latestContract.contract_type,
        start_date: latestContract.start_date,
        basic_salary: latestContract.basic_salary,
        housing_allowance: latestContract.housing_allowance,
        transportation_allowance: latestContract.transportation_allowance,
        other_allowances: latestContract.other_allowances,
        status: latestContract.status,
        notice_period_days: latestContract.notice_period_days,
        annual_leave_days: latestContract.annual_leave_days,
        notes: latestContract.notes,
    },
    account: {
        name: props.employee.user.name || '',
        email: props.employee.user.email || '',
        password: '',
        password_confirmation: '',
    },
});

const validateStep = () => {
    stepErrors.value = {};
    let isValid = true;
    if (currentStep.value === 2) {
        const emp = form.employment;
        // employee_id is auto-generated and read-only, no validation needed
        if (!emp.department_id) { stepErrors.value.department_id = 'القسم حقل مطلوب.'; isValid = false; }
        if (!emp.job_title) { stepErrors.value.job_title = 'المسمى الوظيفي حقل مطلوب.'; isValid = false; }
        if (!emp.basic_salary) { stepErrors.value.basic_salary = 'الراتب الأساسي حقل مطلوب.'; isValid = false; }
    }
    if (currentStep.value === 3) {
        const acc = form.account;
        if (!acc.name) { stepErrors.value.name = 'اسم المستخدم حقل مطلوب.'; isValid = false; }
        if (!acc.email) { stepErrors.value.email = 'البريد الإلكتروني حقل مطلوب.'; isValid = false; }
        if (acc.password && acc.password !== acc.password_confirmation) {
            stepErrors.value.password_confirmation = 'كلمتا المرور غير متطابقتين.';
            isValid = false;
        }
    }
    return isValid;
};

const nextStep = () => { if (validateStep()) { if (currentStep.value < 3) currentStep.value++; } };
const prevStep = () => { if (currentStep.value > 1) currentStep.value--; };
const submit = () => { if (validateStep()) { form.put(route('hr.employees.update', props.employee.id)); } };
</script>

<template>
    <Head :title="`تعديل الموظف - ${employee.user.name}`" />

    <HrLayout>
        <template #header>
            تعديل بيانات الموظف: {{ employee.user.name }}
        </template>

        <div class="max-w-4xl mx-auto bg-white shadow-xl rounded-lg">
            <div class="p-6 border-b rounded-t-lg bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center text-center cursor-pointer" @click="currentStep = 1">
                        <div :class="currentStep >= 1 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600'" class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg">1</div>
                        <span :class="currentStep >= 1 ? 'text-indigo-600' : 'text-gray-500'" class="mx-2 font-semibold">المعلومات الشخصية</span>
                    </div>
                    <div class="flex-1 h-1 rounded-full mx-4" :class="currentStep > 1 ? 'bg-indigo-600' : 'bg-gray-200'"></div>
                    <div class="flex items-center text-center cursor-pointer" @click="currentStep = 2">
                        <div :class="currentStep >= 2 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600'" class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg">2</div>
                        <span :class="currentStep >= 2 ? 'text-indigo-600' : 'text-gray-500'" class="mx-2 font-semibold">المعلومات الوظيفية</span>
                    </div>
                    <div class="flex-1 h-1 rounded-full mx-4" :class="currentStep > 2 ? 'bg-indigo-600' : 'bg-gray-200'"></div>
                    <div class="flex items-center text-center cursor-pointer" @click="currentStep = 3">
                        <div :class="currentStep === 3 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600'" class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg">3</div>
                        <span :class="currentStep === 3 ? 'text-indigo-600' : 'text-gray-500'" class="mx-2 font-semibold">حساب المستخدم</span>
                    </div>
                </div>
            </div>
             
            <form @submit.prevent="submit" class="p-8">
                <div v-show="currentStep === 1">
                    <h3 class="text-xl font-semibold mb-6 text-gray-800">1. تعديل المعلومات الشخصية</h3>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">رقم الهاتف</label>
                            <input type="tel" v-model="form.personal.phone_number" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">تاريخ الميلاد</label>
                            <input type="date" v-model="form.personal.date_of_birth" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                         <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">الجنس</label>
                            <select v-model="form.personal.gender" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <option value="" disabled>اختر..</option> <option value="male">ذكر</option> <option value="female">أنثى</option>
                            </select>
                        </div>
                         <div class="md:col-span-2">
                            <label class="block mb-2 text-sm font-medium text-gray-900">العنوان</label>
                            <textarea rows="3" v-model="form.personal.address" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300"></textarea>
                        </div>
                     </div>
                </div>

                <div v-show="currentStep === 2">
                     <h3 class="text-xl font-semibold mb-6 text-gray-800">2. تعديل المعلومات الوظيفية والعقد</h3>
                     <div class="border-b pb-6 mb-6">
                        <h4 class="text-lg font-medium mb-4 text-gray-700">معلومات عامة</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                           <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">رقم الموظف</label>
                                <input type="text" v-model="form.employment.employee_id" readonly disabled class="bg-gray-100 border border-gray-300 text-gray-600 text-sm rounded-lg block w-full p-2.5 cursor-not-allowed">
                                <p class="text-xs text-gray-500 mt-1">رقم الموظف يتم إنشاؤه تلقائياً ولا يمكن تعديله</p>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">القسم*</label>
                                <select v-model="form.employment.department_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                </select>
                                <div v-if="stepErrors.department_id" class="text-sm text-red-600 mt-1">{{ stepErrors.department_id }}</div>
                            </div>
                             <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">المسمى الوظيفي*</label>
                                <input type="text" v-model="form.employment.job_title" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <div v-if="stepErrors.job_title" class="text-sm text-red-600 mt-1">{{ stepErrors.job_title }}</div>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">تاريخ التعيين*</label>
                                <input type="date" v-model="form.employment.hire_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                            </div>
                             <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">تاريخ بدء العقد*</label>
                                <input type="date" v-model="form.employment.start_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">حالة التوظيف*</label>
                                <select v-model="form.employment.employment_status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    <option value="active">نشط</option> <option value="on_leave">في إجازة</option> <option value="terminated">منتهية خدمته</option>
                                </select>
                            </div>
                        </div>
                     </div>
                     <div class="border-b pb-6 mb-6">
                         <h4 class="text-lg font-medium mb-4 text-gray-700">تفاصيل الراتب</h4>
                         <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">الراتب الأساسي*</label>
                                <input type="number" step="0.01" v-model="form.employment.basic_salary" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <div v-if="stepErrors.basic_salary" class="text-sm text-red-600 mt-1">{{ stepErrors.basic_salary }}</div>
                            </div>
                             <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">بدل السكن</label>
                                <input type="number" step="0.01" v-model="form.employment.housing_allowance" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                            </div>
                             <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">بدل المواصلات</label>
                                <input type="number" step="0.01" v-model="form.employment.transportation_allowance" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                            </div>
                             <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">بدلات أخرى</label>
                                <input type="number" step="0.01" v-model="form.employment.other_allowances" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                            </div>
                         </div>
                     </div>
                     <div>
                         <h4 class="text-lg font-medium mb-4 text-gray-700">شروط العقد</h4>
                         <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">نوع العقد*</label>
                                <select v-model="form.employment.contract_type" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    <option>محدد المدة</option> <option>غير محدد المدة</option> <option>دوام جزئي</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">حالة العقد*</label>
                                <select v-model="form.employment.status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    <option value="active">ساري</option> <option value="pending">قيد المراجعة</option> <option value="expired">منتهي</option>
                                </select>
                            </div>
                             <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">فترة الإشعار (يوم)</label>
                                <input type="number" v-model="form.employment.notice_period_days" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">أيام الإجازة السنوية</label>
                                <input type="number" v-model="form.employment.annual_leave_days" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                            </div>
                             <div class="md:col-span-3">
                                <label class="block mb-2 text-sm font-medium text-gray-900">ملاحظات العقد</label>
                                <textarea rows="3" v-model="form.employment.notes" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300"></textarea>
                            </div>
                         </div>
                     </div>
                </div>

                <div v-show="currentStep === 3">
                     <h3 class="text-xl font-semibold mb-6 text-gray-800">3. تعديل حساب المستخدم</h3>
                      <p class="text-sm text-gray-500 mb-4">اترك حقول كلمة المرور فارغة إذا كنت لا ترغب في تغييرها.</p>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">اسم المستخدم*</label>
                            <input type="text" v-model="form.account.name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                            <div v-if="stepErrors.name" class="text-sm text-red-600 mt-1">{{ stepErrors.name }}</div>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">البريد الإلكتروني*</label>
                            <input type="email" v-model="form.account.email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                             <div v-if="stepErrors.email" class="text-sm text-red-600 mt-1">{{ stepErrors.email }}</div>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">كلمة المرور الجديدة</label>
                            <input type="password" v-model="form.account.password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">تأكيد كلمة المرور</label>
                            <input type="password" v-model="form.account.password_confirmation" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                             <div v-if="stepErrors.password_confirmation" class="text-sm text-red-600 mt-1">{{ stepErrors.password_confirmation }}</div>
                        </div>
                     </div>
                </div>

                <div class="flex items-center justify-between mt-8 pt-6 border-t">
                    <Link :href="route('hr.employees.index')" class="text-gray-600 hover:text-gray-900">
                        العودة للقائمة
                    </Link>
                    <div class="flex items-center space-x-4 rtl:space-x-reverse">
                         <button type="button" @click="prevStep" v-if="currentStep > 1" class="bg-gray-200 text-gray-800 px-6 py-2 rounded-md hover:bg-gray-300">السابق</button>
                        <button type="button" @click="nextStep" v-if="currentStep < 3" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700">التالي</button>
                        <button type="submit" v-if="currentStep === 3" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700" :disabled="form.processing">حفظ التعديلات</button>
                    </div>
                </div>
            </form>
        </div>
    </HrLayout>
</template>