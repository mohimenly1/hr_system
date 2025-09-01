<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    departments: Array,
});

// State to manage the current step
const currentStep = ref(1);
// State to hold client-side validation errors for the current step
const stepErrors = ref({});

// A single form object to hold all data across steps
const form = useForm({
    // Step 1: Personal Information
    personal: {
        phone_number: '',
        address: '',
        date_of_birth: '',
        gender: '',
        attachments: [],
    },
    // Step 2: Employment & Contract Information
    employment: {
        department_id: '',
        employee_id: '',
        hire_date: '',
        employment_status: 'active',
        // --- ALL CONTRACT FIELDS ARE NOW INCLUDED ---
        contract_type: 'محدد المدة',
        start_date: '',
        end_date: null,
        probation_end_date: null,
        job_title: '',
        status: 'active',
        basic_salary: '',
        housing_allowance: 0,
        transportation_allowance: 0,
        other_allowances: 0,
        working_hours_per_day: 8,
        annual_leave_days: 21,
        notice_period_days: 30,
        notes: '',
    },
    // Step 3: User Account
    account: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    },
});

// --- UPDATED VALIDATION LOGIC ---
const validateStep = () => {
    stepErrors.value = {}; // Reset errors before validating
    let isValid = true;

    // Validate Step 2 - now includes contract fields
    if (currentStep.value === 2) {
        const emp = form.employment;
        if (!emp.employee_id) { stepErrors.value.employee_id = 'رقم الموظف حقل مطلوب.'; isValid = false; }
        if (!emp.department_id) { stepErrors.value.department_id = 'القسم حقل مطلوب.'; isValid = false; }
        if (!emp.job_title) { stepErrors.value.job_title = 'المسمى الوظيفي حقل مطلوب.'; isValid = false; }
        if (!emp.hire_date) { stepErrors.value.hire_date = 'تاريخ التعيين حقل مطلوب.'; isValid = false; }
        if (!emp.start_date) { stepErrors.value.start_date = 'تاريخ بدء العقد حقل مطلوب.'; isValid = false; }
        if (!emp.basic_salary) { stepErrors.value.basic_salary = 'الراتب الأساسي حقل مطلوب.'; isValid = false; }
    }
    
    // Validate Step 3
    if (currentStep.value === 3) {
        const acc = form.account;
        if (!acc.name) { stepErrors.value.name = 'اسم المستخدم حقل مطلوب.'; isValid = false; }
        if (!acc.email) { stepErrors.value.email = 'البريد الإلكتروني حقل مطلوب.'; isValid = false; }
        if (!acc.password) { stepErrors.value.password = 'كلمة المرور حقل مطلوب.'; isValid = false; }
        if (acc.password !== acc.password_confirmation) { stepErrors.value.password_confirmation = 'كلمتا المرور غير متطابقتين.'; isValid = false;}
    }
    
    return isValid;
};

// Functions to navigate between steps
const nextStep = () => {
    if (validateStep()) {
        if (currentStep.value < 3) {
            currentStep.value++;
        }
    }
};

const prevStep = () => {
    stepErrors.value = {}; // Clear errors when going back
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const handleFileUpload = (event) => {
    form.personal.attachments = Array.from(event.target.files);
};


const submit = () => {
    // Final validation before submitting
    if (validateStep()){
        form.post(route('hr.employees.store'), {
            onFinish: () => form.reset('account.password', 'account.password_confirmation'),
        });
    }
};
</script>

<template>
    <Head title="إضافة موظف جديد" />

    <HrLayout>
        <template #header>
            إضافة موظف جديد
        </template>

        <div class="max-w-4xl mx-auto bg-white shadow-xl rounded-lg">
            <!-- Step Indicator -->
            <div class="p-6 border-b rounded-t-lg bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center text-center">
                        <div :class="currentStep >= 1 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600'" class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg">1</div>
                        <span :class="currentStep >= 1 ? 'text-indigo-600' : 'text-gray-500'" class="mx-2 font-semibold">المعلومات الشخصية</span>
                    </div>
                    <div class="flex-1 h-1 rounded-full mx-4" :class="currentStep > 1 ? 'bg-indigo-600' : 'bg-gray-200'"></div>
                    <div class="flex items-center text-center">
                        <div :class="currentStep >= 2 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600'" class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg">2</div>
                        <span :class="currentStep >= 2 ? 'text-indigo-600' : 'text-gray-500'" class="mx-2 font-semibold">المعلومات الوظيفية</span>
                    </div>
                    <div class="flex-1 h-1 rounded-full mx-4" :class="currentStep > 2 ? 'bg-indigo-600' : 'bg-gray-200'"></div>
                    <div class="flex items-center text-center">
                        <div :class="currentStep === 3 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600'" class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg">3</div>
                        <span :class="currentStep === 3 ? 'text-indigo-600' : 'text-gray-500'" class="mx-2 font-semibold">حساب المستخدم</span>
                    </div>
                </div>
            </div>
             
            <!-- Form Content -->
            <form @submit.prevent="submit" class="p-8">

                <!-- Step 1: Personal Information -->
                <div v-if="currentStep === 1">
                    <h3 class="text-xl font-semibold mb-6 text-gray-800">1. المعلومات الشخصية (اختياري)</h3>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="phone_number" class="block mb-2 text-sm font-medium text-gray-900">رقم الهاتف</label>
                            <input type="tel" id="phone_number" v-model="form.personal.phone_number" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                        <div>
                            <label for="date_of_birth" class="block mb-2 text-sm font-medium text-gray-900">تاريخ الميلاد</label>
                            <input type="date" id="date_of_birth" v-model="form.personal.date_of_birth" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                         <div>
                            <label for="gender" class="block mb-2 text-sm font-medium text-gray-900">الجنس</label>
                            <select id="gender" v-model="form.personal.gender" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <option value="" disabled>اختر..</option>
                                <option value="male">ذكر</option>
                                <option value="female">أنثى</option>
                            </select>
                        </div>
                         <div class="md:col-span-2">
                            <label for="address" class="block mb-2 text-sm font-medium text-gray-900">العنوان</label>
                            <textarea id="address" rows="3" v-model="form.personal.address" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300"></textarea>
                        </div>
                        <div class="md:col-span-2">
                             <label for="attachments" class="block mb-2 text-sm font-medium text-gray-900">المرفقات (CV، صورة شخصية، ...)</label>
                             <input type="file" @change="handleFileUpload" multiple class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                        </div>
                     </div>
                </div>

                <!-- Step 2: Employment & Contract Information -->
                <div v-if="currentStep === 2">
                     <h3 class="text-xl font-semibold mb-6 text-gray-800">2. المعلومات الوظيفية والعقد</h3>
                     <!-- General -->
                     <div class="border-b pb-6 mb-6">
                        <h4 class="text-lg font-medium mb-4 text-gray-700">معلومات عامة</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="employee_id" class="block mb-2 text-sm font-medium text-gray-900">رقم الموظف*</label>
                                <input type="text" v-model="form.employment.employee_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <div v-if="stepErrors.employee_id" class="text-sm text-red-600 mt-1">{{ stepErrors.employee_id }}</div>
                            </div>
                            <div>
                                <label for="department_id" class="block mb-2 text-sm font-medium text-gray-900">القسم*</label>
                                <select v-model="form.employment.department_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    <option value="" disabled>اختر قسماً</option>
                                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                </select>
                                <div v-if="stepErrors.department_id" class="text-sm text-red-600 mt-1">{{ stepErrors.department_id }}</div>
                            </div>
                             <div>
                                <label for="job_title" class="block mb-2 text-sm font-medium text-gray-900">المسمى الوظيفي*</label>
                                <input type="text" v-model="form.employment.job_title" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <div v-if="stepErrors.job_title" class="text-sm text-red-600 mt-1">{{ stepErrors.job_title }}</div>
                            </div>
                            <div>
                                <label for="hire_date" class="block mb-2 text-sm font-medium text-gray-900">تاريخ التعيين*</label>
                                <input type="date" v-model="form.employment.hire_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <div v-if="stepErrors.hire_date" class="text-sm text-red-600 mt-1">{{ stepErrors.hire_date }}</div>
                            </div>
                             <div>
                                <label for="start_date" class="block mb-2 text-sm font-medium text-gray-900">تاريخ بدء العقد*</label>
                                <input type="date" v-model="form.employment.start_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <div v-if="stepErrors.start_date" class="text-sm text-red-600 mt-1">{{ stepErrors.start_date }}</div>
                            </div>
                            <div>
                                <label for="employment_status" class="block mb-2 text-sm font-medium text-gray-900">حالة التوظيف*</label>
                                <select v-model="form.employment.employment_status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    <option value="active">نشط</option>
                                    <option value="on_leave">في إجازة</option>
                                    <option value="terminated">منتهية خدمته</option>
                                </select>
                            </div>
                        </div>
                     </div>
                     <!-- Salary -->
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
                     <!-- Terms -->
                     <div>
                         <h4 class="text-lg font-medium mb-4 text-gray-700">شروط العقد</h4>
                         <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">نوع العقد*</label>
                                <select v-model="form.employment.contract_type" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    <option>محدد المدة</option>
                                    <option>غير محدد المدة</option>
                                    <option>دوام جزئي</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">حالة العقد*</label>
                                <select v-model="form.employment.status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    <option value="active">ساري</option>
                                    <option value="pending">قيد المراجعة</option>
                                    <option value="expired">منتهي</option>
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

                <!-- Step 3: User Account -->
                <div v-if="currentStep === 3">
                     <h3 class="text-xl font-semibold mb-6 text-gray-800">3. حساب المستخدم للخدمات الذاتية</h3>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block mb-2 text-sm font-medium text-gray-900">اسم المستخدم*</label>
                            <input type="text" id="name" v-model="form.account.name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                            <div v-if="stepErrors.name" class="text-sm text-red-600 mt-1">{{ stepErrors.name }}</div>
                        </div>
                        <div>
                            <label for="email" class="block mb-2 text-sm font-medium text-gray-900">البريد الإلكتروني*</label>
                            <input type="email" id="email" v-model="form.account.email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                            <div v-if="stepErrors.email" class="text-sm text-red-600 mt-1">{{ stepErrors.email }}</div>
                        </div>
                        <div>
                            <label for="password" class="block mb-2 text-sm font-medium text-gray-900">كلمة المرور*</label>
                            <input type="password" id="password" v-model="form.account.password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                             <div v-if="stepErrors.password" class="text-sm text-red-600 mt-1">{{ stepErrors.password }}</div>
                        </div>
                        <div>
                            <label for="password_confirmation" class="block mb-2 text-sm font-medium text-gray-900">تأكيد كلمة المرور*</label>
                            <input type="password" id="password_confirmation" v-model="form.account.password_confirmation" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                             <div v-if="stepErrors.password_confirmation" class="text-sm text-red-600 mt-1">{{ stepErrors.password_confirmation }}</div>
                        </div>
                     </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex items-center justify-between mt-8 pt-6 border-t">
                    <Link :href="route('hr.employees.index')" class="text-gray-600 hover:text-gray-900">
                        إلغاء
                    </Link>

                    <div class="flex items-center space-x-4">
                         <button type="button" @click="prevStep" v-if="currentStep > 1" class="bg-gray-200 text-gray-800 px-6 py-2 rounded-md hover:bg-gray-300">
                            السابق
                        </button>
                        <button type="button" @click="nextStep" v-if="currentStep < 3" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700">
                            التالي
                        </button>
                        <button type="submit" v-if="currentStep === 3" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700" :disabled="form.processing">
                            حفظ الموظف
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </HrLayout>
</template>

