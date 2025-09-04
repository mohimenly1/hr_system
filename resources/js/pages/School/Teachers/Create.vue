<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    departments: Array,
    grades: Array,
});

const currentStep = ref(1);
const stepErrors = ref({});

const form = useForm({
    personal: {
        phone_number: '', address: '', date_of_birth: '', gender: '', marital_status: '',
        emergency_contact_name: '', emergency_contact_phone: '',
        attachments: [], // For handling file uploads
    },
    employment: {
        department_id: '', specialization: '', hire_date: '', employment_status: 'active',
        contract_type: 'monthly', start_date: '', salary_type: 'monthly',
        salary_amount: 0, hourly_rate: 0, working_hours_per_week: null,
        notes: '', status: 'active',
    },
    assignments: [], // Will hold objects like { subject_id: 1, section_id: 2 }
    account: {
        name: '', email: '', password: '', password_confirmation: '',
    },
});

const selectedGradeIds = ref([]);

// This computed property filters the full grade objects based on selected IDs.
const selectedGradesData = computed(() => {
    return props.grades.filter(grade => selectedGradeIds.value.includes(grade.id));
});


// --- UPDATED FUNCTION FOR BETTER REACTIVITY ---
const toggleAssignment = (subjectId, sectionId) => {
    const assignmentIndex = form.assignments.findIndex(
        a => a.subject_id === subjectId && a.section_id === sectionId
    );

    if (assignmentIndex > -1) {
        // To ensure reactivity, we filter and create a new array
        form.assignments = form.assignments.filter(
            (_, index) => index !== assignmentIndex
        );
    } else {
        // We add the new item by creating a new array
        form.assignments = [
            ...form.assignments,
            { subject_id: subjectId, section_id: sectionId }
        ];
    }
};

const assignmentSummary = computed(() => {
    return form.assignments.map(assignment => {
        for (const grade of props.grades) {
            const section = grade.sections.find(s => s.id === assignment.section_id);
            if (section) {
                const subject = grade.subjects.find(sub => sub.id === assignment.subject_id);
                if (subject) {
                    return {
                        id: `${section.id}-${subject.id}`,
                        text: `${subject.name} - ${grade.name} (${section.name})`
                    };
                }
            }
        }
        return null;
    }).filter(Boolean);
});

// New method to handle file uploads
const handleFileUpload = (event) => {
    form.personal.attachments = Array.from(event.target.files);
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
        if (emp.salary_type === 'monthly' && (!emp.salary_amount || emp.salary_amount <= 0)) { stepErrors.value.salary_amount = 'مبلغ الراتب الأساسي حقل مطلوب.'; isValid = false; }
        if (emp.salary_type === 'hourly' && (!emp.hourly_rate || emp.hourly_rate <= 0)) { stepErrors.value.hourly_rate = 'مبلغ الأجر بالساعة حقل مطلوب.'; isValid = false; }
    }
    if (currentStep.value === 3) {
        if (form.assignments.length === 0) {
            stepErrors.value.assignments = 'يجب إسناد مقرر دراسي واحد على الأقل.';
            isValid = false;
        }
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

const nextStep = () => { if (validateStep() && currentStep.value < 4) currentStep.value++; };
const prevStep = () => { if (currentStep.value > 1) currentStep.value--; };

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
        <template #header>إضافة معلم جديد</template>

        <div class="max-w-5xl mx-auto bg-white shadow-xl rounded-lg">
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
                <!-- Step 1: Personal Information -->
                <div v-show="currentStep === 1">
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
                             <select id="marital_status" v-model="form.personal.marital_status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                                <option value="" disabled>اختر..</option>
                                <option value="single">أعزب/عزباء</option>
                                <option value="married">متزوج/متزوجة</option>
                                <option value="divorced">مطلق/مطلقة</option>
                                <option value="widowed">أرمل/أرملة</option>
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
                        <div class="md:col-span-2 p-4 border rounded-lg">
                            <h4 class="text-md font-semibold mb-2 text-gray-700">جهة اتصال للطوارئ</h4>
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

                <!-- Step 2: Employment Information -->
                <div v-show="currentStep === 2">
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
                                    <option value="monthly">شهري</option>
                                    <option value="hourly">بالساعة</option>
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
                                    <option value="monthly">راتب شهري</option>
                                    <option value="hourly">أجر بالساعة</option>
                                </select>
                            </div>
                            <div v-if="form.employment.salary_type === 'monthly'">
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

                <!-- Step 3: Subject Assignment (NEW UI) -->
                <div v-show="currentStep === 3">
                    <h3 class="text-xl font-semibold mb-6 text-gray-800">3. إسناد المقررات الدراسية</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Grade Selector -->
                        <div class="md:col-span-1 border-r pr-4 rtl:border-r-0 rtl:border-l rtl:pr-0 rtl:pl-4">
                            <h4 class="font-bold mb-2 text-gray-700">1. اختر المراحل</h4>
                            <div class="space-y-2 max-h-96 overflow-y-auto">
                                <label v-for="grade in grades" :key="grade.id" class="flex items-center p-2 rounded-lg hover:bg-gray-50">
                                    <input type="checkbox" :value="grade.id" v-model="selectedGradeIds" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                    <span class="ml-3 rtl:ml-0 rtl:mr-3 text-sm font-medium text-gray-800">{{ grade.name }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Subject & Section Selector -->
                        <div class="md:col-span-2">
                             <h4 class="font-bold mb-2 text-gray-700">2. اختر المقررات والشعب</h4>
                             <div class="space-y-6 max-h-96 overflow-y-auto pr-2">
                                <div v-if="!selectedGradesData.length" class="text-center text-gray-500 p-8">
                                    الرجاء اختيار مرحلة دراسية من القائمة لعرض مقرراتها.
                                </div>
                                <div v-for="grade in selectedGradesData" :key="grade.id">
                                    <h5 class="font-semibold text-indigo-700 border-b pb-2 mb-3">{{ grade.name }}</h5>
                                     <!-- NEW: Smart message if sections are missing -->
                                    <div v-if="!grade.sections || grade.sections.length === 0" class="text-sm text-red-500 p-2 bg-red-50 rounded-md">
                                        لا توجد شعب معرفة لهذه المرحلة. يرجى <Link :href="route('school.sections.index')" class="font-bold underline">إضافة شعب</Link> أولاً.
                                    </div>
                                    <div v-else class="space-y-4">
                                        <div v-for="subject in grade.subjects" :key="subject.id" class="pl-2 rtl:pl-0 rtl:pr-2">
                                            <p class="font-medium text-gray-800">{{ subject.name }}</p>
                                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                                                <label v-for="section in grade.sections" :key="section.id" class="flex items-center text-sm">
                                                    <input type="checkbox" @change="toggleAssignment(subject.id, section.id)"
                                                           :checked="form.assignments.some(a => a.subject_id === subject.id && a.section_id === section.id)"
                                                           class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                                    <span class="ml-2 rtl:ml-0 rtl:mr-2 text-gray-700">شعبة {{ section.name }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                             </div>
                        </div>
                    </div>
                     <div class="mt-8">
                        <h4 class="text-lg font-medium mb-4 text-gray-700">ملخص الإسناد</h4>
                        <div v-if="assignmentSummary.length" class="flex flex-wrap gap-2 p-2 border rounded-md bg-gray-50">
                            <span v-for="summary in assignmentSummary" :key="summary.id" class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">
                                {{ summary.text }}
                            </span>
                        </div>
                        <p v-else class="text-gray-500 text-sm">لم يتم إسناد أي مقرر دراسي بعد.</p>
                        <div v-if="stepErrors.assignments" class="text-sm text-red-600 mt-1">{{ stepErrors.assignments }}</div>
                    </div>
                </div>

                <!-- Step 4: User Account -->
                <div v-show="currentStep === 4">
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

                <!-- Navigation Buttons -->
                 <div class="flex items-center justify-between mt-8 pt-6 border-t">
                    <Link :href="route('school.teachers.index')" class="text-gray-600 hover:text-gray-900">إلغاء</Link>
                    <div class="flex items-center space-x-4 rtl:space-x-reverse">
                        <button type="button" @click="prevStep" v-if="currentStep > 1" class="bg-gray-200 text-gray-800 px-6 py-2 rounded-md hover:bg-gray-300">السابق</button>
                        <button type="button" @click="nextStep" v-if="currentStep < 4" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700">التالي</button>
                        <button type="submit" v-if="currentStep === 4" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700" :disabled="form.processing">حفظ المعلم</button>
                    </div>
                </div>
            </form>
        </div>
    </HrLayout>
</template>
