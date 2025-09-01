<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    departments: Array,
    grades: Array,
});

const currentStep = ref(1);
const stepErrors = ref({});

const form = useForm({
    personal: {
        phone_number: '',
        address: '',
        date_of_birth: '',
        gender: '',
        marital_status: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
    },
    employment: {
        department_id: '',
        specialization: '',
        hire_date: '',
        employment_status: 'active',
        contract_type: 'محدد المدة',
        start_date: '',
        salary_type: 'fixed',
        salary_amount: 0,
        hourly_rate: 0,
        working_hours_per_week: null,
        notes: '',
        status: 'active',
    },
    subjects: [], // Holds the assigned subjects
    account: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    },
});

// For Subject Assignment Step
const selectedGrade = ref(null);
const selectedSection = ref(null);
const availableSubjects = ref([]);

watch(selectedSection, (newSection) => {
    if (newSection) {
        // Find the selected grade to get its sections
        const grade = props.grades.find(g => g.id === selectedGrade.value);
        if (grade) {
            // Find the selected section to get its subjects
            const section = grade.sections.find(s => s.id === newSection);
            if (section && section.subjects) {
                availableSubjects.value = section.subjects;
            } else {
                availableSubjects.value = [];
            }
        }
    } else {
        availableSubjects.value = [];
    }
});

const addSubjectToTeacher = (subject) => {
    // Check if the subject is already added to avoid duplicates
    if (!form.subjects.some(s => s.id === subject.id)) {
        form.subjects.push(subject);
    }
};

const removeSubjectFromTeacher = (subjectId) => {
    form.subjects = form.subjects.filter(s => s.id !== subjectId);
};

const validateStep = () => {
    stepErrors.value = {};
    let isValid = true;

    if (currentStep.value === 2) {
        const emp = form.employment;
        if (!emp.department_id) { stepErrors.value.department_id = 'القسم حقل مطلوب.'; isValid = false; }
        if (!emp.specialization) { stepErrors.value.specialization = 'التخصص حقل مطلوب.'; isValid = false; }
        if (!emp.hire_date) { stepErrors.value.hire_date = 'تاريخ التعيين حقل مطلوب.'; isValid = false; }
        if (!emp.start_date) { stepErrors.value.start_date = 'تاريخ بدء العقد حقل مطلوب.'; isValid = false; }
        if (emp.salary_type === 'fixed' && !emp.salary_amount) { stepErrors.value.salary_amount = 'مبلغ الراتب الأساسي حقل مطلوب.'; isValid = false; }
        if (emp.salary_type === 'hourly' && !emp.hourly_rate) { stepErrors.value.hourly_rate = 'مبلغ الأجر بالساعة حقل مطلوب.'; isValid = false; }
    }
    if (currentStep.value === 3) {
        if (!form.subjects.length) { stepErrors.value.subjects = 'يجب إسناد مقرر دراسي واحد على الأقل.'; isValid = false; }
    }
    if (currentStep.value === 4) {
        const acc = form.account;
        if (!acc.name) { stepErrors.value.name = 'اسم المستخدم حقل مطلوب.'; isValid = false; }
        if (!acc.email) { stepErrors.value.email = 'البريد الإلكتروني حقل مطلوب.'; isValid = false; }
        if (!acc.password) { stepErrors.value.password = 'كلمة المرور حقل مطلوب.'; isValid = false; }
        if (acc.password !== acc.password_confirmation) { stepErrors.value.password_confirmation = 'كلمتا المرور غير متطابقتين.'; isValid = false;}
    }

    return isValid;
};

const nextStep = () => {
    if (validateStep()) {
        if (currentStep.value < 4) {
            currentStep.value++;
        }
    }
};

const prevStep = () => {
    stepErrors.value = {};
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const submit = () => {
    if (validateStep()) {
        form.post(route('school.teachers.store'), {
            onFinish: () => form.reset('account.password', 'account.password_confirmation'),
        });
    }
};
</script>

<template>
    <Head title="إضافة معلم جديد" />

    <HrLayout>
        <template #header>
            إضافة معلم جديد
        </template>

        <div class="max-w-4xl mx-auto bg-white shadow-xl rounded-lg">
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
                        <div :class="currentStep >= 3 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600'" class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg">3</div>
                        <span :class="currentStep >= 3 ? 'text-indigo-600' : 'text-gray-500'" class="mx-2 font-semibold">المقررات الدراسية</span>
                    </div>
                    <div class="flex-1 h-1 rounded-full mx-4" :class="currentStep > 3 ? 'bg-indigo-600' : 'bg-gray-200'"></div>
                    <div class="flex items-center text-center">
                        <div :class="currentStep === 4 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600'" class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg">4</div>
                        <span :class="currentStep === 4 ? 'text-indigo-600' : 'text-gray-500'" class="mx-2 font-semibold">حساب المستخدم</span>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-8">

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
                        <div>
                            <label for="marital_status" class="block mb-2 text-sm font-medium text-gray-900">الحالة الاجتماعية</label>
                            <input type="text" id="marital_status" v-model="form.personal.marital_status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                        </div>
                        <div class="md:col-span-2">
                            <label for="address" class="block mb-2 text-sm font-medium text-gray-900">العنوان</label>
                            <textarea id="address" rows="3" v-model="form.personal.address" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <h4 class="text-lg font-medium mb-2 text-gray-700">جهة اتصال للطوارئ</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="emergency_contact_name" class="block mb-2 text-sm font-medium text-gray-900">اسم الشخص</label>
                                    <input type="text" id="emergency_contact_name" v-model="form.personal.emergency_contact_name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                </div>
                                <div>
                                    <label for="emergency_contact_phone" class="block mb-2 text-sm font-medium text-gray-900">رقم الهاتف</label>
                                    <input type="tel" id="emergency_contact_phone" v-model="form.personal.emergency_contact_phone" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="currentStep === 2">
                    <h3 class="text-xl font-semibold mb-6 text-gray-800">2. المعلومات الوظيفية والعقد</h3>
                    <div class="border-b pb-6 mb-6">
                        <h4 class="text-lg font-medium mb-4 text-gray-700">معلومات عامة</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="department_id" class="block mb-2 text-sm font-medium text-gray-900">القسم*</label>
                                <select v-model="form.employment.department_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    <option value="" disabled>اختر قسماً</option>
                                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                </select>
                                <div v-if="stepErrors.department_id" class="text-sm text-red-600 mt-1">{{ stepErrors.department_id }}</div>
                            </div>
                            <div>
                                <label for="specialization" class="block mb-2 text-sm font-medium text-gray-900">التخصص*</label>
                                <input type="text" v-model="form.employment.specialization" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <div v-if="stepErrors.specialization" class="text-sm text-red-600 mt-1">{{ stepErrors.specialization }}</div>
                            </div>
                            <div>
                                <label for="hire_date" class="block mb-2 text-sm font-medium text-gray-900">تاريخ التعيين*</label>
                                <input type="date" v-model="form.employment.hire_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <div v-if="stepErrors.hire_date" class="text-sm text-red-600 mt-1">{{ stepErrors.hire_date }}</div>
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
                    <div class="border-b pb-6 mb-6">
                        <h4 class="text-lg font-medium mb-4 text-gray-700">تفاصيل الراتب والعقد</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">نوع العقد*</label>
                                <select v-model="form.employment.contract_type" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    <option>محدد المدة</option>
                                    <option>غير محدد المدة</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">تاريخ بدء العقد*</label>
                                <input type="date" v-model="form.employment.start_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <div v-if="stepErrors.start_date" class="text-sm text-red-600 mt-1">{{ stepErrors.start_date }}</div>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">نوع الراتب*</label>
                                <select v-model="form.employment.salary_type" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                    <option value="fixed">راتب ثابت</option>
                                    <option value="hourly">أجر بالساعة</option>
                                </select>
                            </div>
                            <div v-if="form.employment.salary_type === 'fixed'">
                                <label class="block mb-2 text-sm font-medium text-gray-900">مبلغ الراتب الأساسي*</label>
                                <input type="number" step="0.01" v-model="form.employment.salary_amount" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <div v-if="stepErrors.salary_amount" class="text-sm text-red-600 mt-1">{{ stepErrors.salary_amount }}</div>
                            </div>
                            <div v-if="form.employment.salary_type === 'hourly'">
                                <label class="block mb-2 text-sm font-medium text-gray-900">الأجر بالساعة*</label>
                                <input type="number" step="0.01" v-model="form.employment.hourly_rate" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <div v-if="stepErrors.hourly_rate" class="text-sm text-red-600 mt-1">{{ stepErrors.hourly_rate }}</div>
                            </div>
                            <div v-if="form.employment.salary_type === 'hourly'">
                                <label class="block mb-2 text-sm font-medium text-gray-900">ساعات العمل الأسبوعية</label>
                                <input type="number" v-model="form.employment.working_hours_per_week" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block mb-2 text-sm font-medium text-gray-900">ملاحظات العقد</label>
                                <textarea rows="3" v-model="form.employment.notes" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="currentStep === 3">
                    <h3 class="text-xl font-semibold mb-6 text-gray-800">3. إسناد المقررات الدراسية</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="grade" class="block mb-2 text-sm font-medium text-gray-900">المرحلة الدراسية*</label>
                            <select id="grade" v-model="selectedGrade" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <option :value="null" disabled>اختر مرحلة</option>
                                <option v-for="grade in grades" :key="grade.id" :value="grade.id">{{ grade.name }}</option>
                            </select>
                        </div>
                        <div v-if="selectedGrade">
                            <label for="section" class="block mb-2 text-sm font-medium text-gray-900">الشعبة*</label>
                            <select id="section" v-model="selectedSection" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <option :value="null" disabled>اختر شعبة</option>
                                <option v-for="section in grades.find(g => g.id === selectedGrade).sections" :key="section.id" :value="section.id">{{ section.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="availableSubjects.length">
                        <h4 class="text-lg font-medium mb-4 text-gray-700">المقررات المتاحة</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 max-h-40 overflow-y-auto border p-4 rounded-md">
                            <div v-for="subject in availableSubjects" :key="subject.id" @click="addSubjectToTeacher(subject)"
                                :class="{'bg-indigo-600 text-white': form.subjects.some(s => s.id === subject.id), 'bg-gray-100 text-gray-800 hover:bg-indigo-100': !form.subjects.some(s => s.id === subject.id)}"
                                class="cursor-pointer select-none rounded-md px-3 py-1 text-center text-sm font-medium transition-colors duration-200">
                                {{ subject.name }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8">
                        <h4 class="text-lg font-medium mb-4 text-gray-700">المقررات المسندة للمعلم</h4>
                        <div v-if="form.subjects.length" class="flex flex-wrap gap-2 p-2 border rounded-md">
                            <span v-for="subject in form.subjects" :key="subject.id" class="inline-flex items-center rounded-full bg-blue-100 px-3 py-0.5 text-sm font-medium text-blue-800">
                                {{ subject.name }}
                                <button type="button" @click="removeSubjectFromTeacher(subject.id)" class="ml-1 -mr-0.5 h-4 w-4 rounded-full text-blue-500 hover:text-blue-700 transition-colors">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 8.586L15.293 3.293a1 1 0 011.414 1.414L11.414 10l5.293 5.293a1 1 0 01-1.414 1.414L10 11.414l-5.293 5.293a1 1 0 01-1.414-1.414L8.586 10 3.293 4.707a1 1 0 011.414-1.414L10 8.586z" />
                                    </svg>
                                </button>
                            </span>
                        </div>
                        <p v-else class="text-gray-500 text-sm">لم يتم إسناد أي مقرر دراسي بعد.</p>
                        <div v-if="stepErrors.subjects" class="text-sm text-red-600 mt-1">{{ stepErrors.subjects }}</div>
                    </div>
                </div>

                <div v-if="currentStep === 4">
                    <h3 class="text-xl font-semibold mb-6 text-gray-800">4. حساب المستخدم للخدمات الذاتية</h3>
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

                <div class="flex items-center justify-between mt-8 pt-6 border-t">
                    <Link :href="route('school.teachers.index')" class="text-gray-600 hover:text-gray-900">
                        إلغاء
                    </Link>

                    <div class="flex items-center space-x-4">
                        <button type="button" @click="prevStep" v-if="currentStep > 1" class="bg-gray-200 text-gray-800 px-6 py-2 rounded-md hover:bg-gray-300">
                            السابق
                        </button>
                        <button type="button" @click="nextStep" v-if="currentStep < 4" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700">
                            التالي
                        </button>
                        <button type="submit" v-if="currentStep === 4" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700" :disabled="form.processing">
                            حفظ المعلم
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </HrLayout>
</template>