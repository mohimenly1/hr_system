<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    departments: Array,
    grades: Array,
});

const activeTab = ref('personal');

const form = useForm({
    personal: {
        middle_name: '',
        last_name: '',
        mother_name: '',
        marital_status: '',
        nationality: '',
        national_id_number: '',
        phone_number: '',
        address: '',
        date_of_birth: '',
        gender: '',
        attachments: [],
    },
    work_experiences: [],
    employment: {
        department_id: '',
        fingerprint_id: '', // --- FIELD ADDED ---
        hire_date: '',
        employment_status: 'active',
        specialization: '',
        contract_type: 'monthly',
        start_date: '',
        salary_type: 'monthly',
        salary_amount: null,
        hourly_rate: null,
        working_hours_per_week: null,
        notes: '',
        status: 'active',
    },
    assignments: [],
    account: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    },
});

// --- Computed properties to check tab completion ---
const isPersonalTabComplete = computed(() => !!form.account.name);
const isExperienceTabComplete = computed(() => true); // Optional tab
const isEmploymentTabComplete = computed(() => {
    const emp = form.employment;
    const salaryValid = emp.salary_type === 'monthly' ? (emp.salary_amount && emp.salary_amount > 0) : (emp.hourly_rate && emp.hourly_rate > 0);
    return !!emp.department_id && !!emp.hire_date && !!emp.specialization && !!emp.start_date && salaryValid;
});
const isAssignmentsTabComplete = computed(() => form.assignments.length > 0);
const isAccountTabComplete = computed(() => {
    const acc = form.account;
    return !!acc.email && !!acc.password && !!acc.password_confirmation && (acc.password === acc.password_confirmation);
});

const clientErrors = ref({});

// --- Work Experience Logic ---
const newExperience = ref({ company_name: '', job_title: '', start_date: '', end_date: '', description: '' });
const addExperience = () => {
    if (newExperience.value.company_name && newExperience.value.job_title) {
        form.work_experiences.push({ ...newExperience.value });
        newExperience.value = { company_name: '', job_title: '', start_date: '', end_date: '', description: '' };
    }
};
const removeExperience = (index) => {
    form.work_experiences.splice(index, 1);
};

// --- Assignments Logic ---
const selectedGradeIds = ref([]);
const selectedGradesData = computed(() => {
    return props.grades.filter(grade => selectedGradeIds.value.includes(grade.id));
});
const toggleAssignment = (subjectId, sectionId) => {
    const assignmentIndex = form.assignments.findIndex(a => a.subject_id === subjectId && a.section_id === sectionId);
    if (assignmentIndex > -1) {
        form.assignments = form.assignments.filter((_, index) => index !== assignmentIndex);
    } else {
        form.assignments = [...form.assignments, { subject_id: subjectId, section_id: sectionId }];
    }
};
const assignmentSummary = computed(() => {
    return form.assignments.map(assignment => {
        for (const grade of props.grades) {
            const section = grade.sections.find(s => s.id === assignment.section_id);
            if (section) {
                const subject = grade.subjects.find(sub => sub.id === assignment.subject_id);
                if (subject) {
                    return { id: `${section.id}-${subject.id}`, text: `${subject.name} - ${grade.name} (${section.name})` };
                }
            }
        }
        return null;
    }).filter(Boolean);
});


const handleFileUpload = (event) => {
    form.personal.attachments = Array.from(event.target.files);
};

// --- Submit Logic with Validation ---
const submit = () => {
    clientErrors.value = {};
    let firstErrorTab = null;

    if (!isPersonalTabComplete.value) { clientErrors.value.personal = 'تبويب "المعلومات الشخصية" غير مكتمل.'; if (!firstErrorTab) firstErrorTab = 'personal'; }
    if (!isEmploymentTabComplete.value) { clientErrors.value.employment = 'تبويب "الوظيفة والعقد" غير مكتمل.'; if (!firstErrorTab) firstErrorTab = 'employment'; }
    if (!isAssignmentsTabComplete.value) { clientErrors.value.assignments = 'تبويب "المقررات الدراسية" غير مكتمل.'; if (!firstErrorTab) firstErrorTab = 'assignments'; }
    if (!isAccountTabComplete.value) { clientErrors.value.account = 'تبويب "حساب المستخدم" غير مكتمل.'; if (!firstErrorTab) firstErrorTab = 'account'; }

    if (firstErrorTab) {
        activeTab.value = firstErrorTab;
        return;
    }

    form.post(route('school.teachers.store'), {
        onError: (serverErrors) => {
            const errorKeys = Object.keys(serverErrors);
            if (errorKeys.some(k => k.startsWith('personal.') || k.startsWith('account.name'))) { activeTab.value = 'personal'; }
            else if (errorKeys.some(k => k.startsWith('employment.'))) { activeTab.value = 'employment'; }
            else if (errorKeys.some(k => k.startsWith('assignments.'))) { activeTab.value = 'assignments'; }
            else if (errorKeys.some(k => k.startsWith('account.'))) { activeTab.value = 'account'; }
        }
    });
};
</script>

<template>
    <Head title="إضافة معلم جديد" />
    <HrLayout>
        <template #header>إضافة معلم جديد</template>
        <form @submit.prevent="submit" class="max-w-7xl mx-auto space-y-6 text-black">
            <div class="bg-white shadow-xl rounded-lg">
                <!-- Tabs Navigation -->
                <div class="border-b">
                    <div class="flex space-x-4 rtl:space-x-reverse px-6 -mb-px">
                        <button type="button" @click="activeTab = 'personal'" :class="{'border-indigo-500 text-indigo-600': activeTab === 'personal', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'personal'}" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                            <i class="fas fa-user-circle mr-2"></i> المعلومات الشخصية
                            <span v-if="isPersonalTabComplete" class="ml-2 text-green-500" title="مكتمل">✓</span>
                        </button>
                        <button type="button" @click="activeTab = 'experience'" :class="{'border-indigo-500 text-indigo-600': activeTab === 'experience', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'experience'}" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                           <i class="fas fa-briefcase mr-2"></i> الخبرات العملية
                           <span v-if="isExperienceTabComplete" class="ml-2 text-green-500" title="مكتمل (اختياري)">✓</span>
                        </button>
                         <button type="button" @click="activeTab = 'employment'" :class="{'border-indigo-500 text-indigo-600': activeTab === 'employment', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'employment'}" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                            <i class="fas fa-id-card-alt mr-2"></i> الوظيفة والعقد
                             <span v-if="isEmploymentTabComplete" class="ml-2 text-green-500" title="مكتمل">✓</span>
                        </button>
                         <button type="button" @click="activeTab = 'assignments'" :class="{'border-indigo-500 text-indigo-600': activeTab === 'assignments', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'assignments'}" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                            <i class="fas fa-book-reader mr-2"></i> المقررات الدراسية
                             <span v-if="isAssignmentsTabComplete" class="ml-2 text-green-500" title="مكتمل">✓</span>
                        </button>
                         <button type="button" @click="activeTab = 'account'" :class="{'border-indigo-500 text-indigo-600': activeTab === 'account', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'account'}" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                            <i class="fas fa-user-shield mr-2"></i> حساب المستخدم
                             <span v-if="isAccountTabComplete" class="ml-2 text-green-500" title="مكتمل">✓</span>
                        </button>
                    </div>
                </div>

                <!-- Tabs Content -->
                <div class="p-6 min-h-[400px]">
                     <div v-if="Object.keys(clientErrors).length > 0" class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded-md">
                        <h4 class="font-bold text-red-800">الرجاء مراجعة الأخطاء التالية:</h4>
                        <ul class="list-disc list-inside mt-2 text-sm text-red-700">
                            <li v-for="(error, key) in clientErrors" :key="key">{{ error }}</li>
                        </ul>
                    </div>

                    <!-- Personal Info Tab -->
                    <div v-show="activeTab === 'personal'">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block mb-2 text-sm font-medium">الاسم الأول*</label>
                                <input type="text" v-model="form.account.name" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium">الاسم الأوسط</label>
                                <input type="text" v-model="form.personal.middle_name" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium">الاسم الأخير</label>
                                <input type="text" v-model="form.personal.last_name" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                            </div>
                             <div>
                                <label class="block mb-2 text-sm font-medium">اسم الأم</label>
                                <input type="text" v-model="form.personal.mother_name" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium">الجنسية</label>
                                <input type="text" v-model="form.personal.nationality" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                            </div>
                             <div>
                                <label class="block mb-2 text-sm font-medium">رقم الهوية</label>
                                <input type="text" v-model="form.personal.national_id_number" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium">تاريخ الميلاد</label>
                                <input type="date" v-model="form.personal.date_of_birth" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium">الجنس</label>
                                <select v-model="form.personal.gender" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                    <option value="" disabled>اختر..</option>
                                    <option value="male">ذكر</option>
                                    <option value="female">أنثى</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium">الحالة الاجتماعية</label>
                                <select v-model="form.personal.marital_status" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                    <option value="" disabled>اختر..</option>
                                    <option value="أعزب">أعزب</option>
                                    <option value="متزوج">متزوج</option>
                                    <option value="مطلق">مطلق</option>
                                    <option value="أرمل">أرمل</option>
                                </select>
                            </div>
                             <div class="md:col-span-3">
                                <label class="block mb-2 text-sm font-medium">العنوان</label>
                                <textarea v-model="form.personal.address" rows="3" class="block p-2.5 w-full text-sm bg-gray-50 rounded-lg border border-gray-300"></textarea>
                            </div>
                             <div class="md:col-span-3">
                                <label class="block mb-2 text-sm font-medium">المرفقات</label>
                                <input type="file" @change="handleFileUpload" multiple class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                            </div>
                        </div>
                    </div>
                    <!-- Work Experience Tab -->
                    <div v-show="activeTab === 'experience'">
                        <div class="p-4 border rounded-lg space-y-4 mb-6">
                            <h4 class="font-bold text-gray-700">إضافة خبرة جديدة</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium">اسم الشركة*</label>
                                    <input type="text" v-model="newExperience.company_name" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">المسمى الوظيفي*</label>
                                    <input type="text" v-model="newExperience.job_title" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">تاريخ البدء</label>
                                    <input type="date" v-model="newExperience.start_date" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">تاريخ الانتهاء (اختياري)</label>
                                    <input type="date" v-model="newExperience.end_date" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium">الوصف</label>
                                    <textarea v-model="newExperience.description" rows="3" class="mt-1 block p-2.5 w-full text-sm bg-gray-50 rounded-lg border border-gray-300"></textarea>
                                </div>
                            </div>
                            <button type="button" @click="addExperience" class="w-full bg-indigo-50 text-indigo-700 font-semibold py-2 px-4 rounded-md hover:bg-indigo-100">إضافة خبرة</button>
                        </div>
                        <div v-if="form.work_experiences.length > 0">
                            <h4 class="font-semibold mb-2">الخبرات المضافة</h4>
                            <ul class="space-y-2">
                                <li v-for="(exp, index) in form.work_experiences" :key="index" class="flex justify-between items-center p-3 bg-gray-50 rounded-md border">
                                    <div>
                                        <p class="font-bold">{{ exp.job_title }}</p>
                                        <p class="text-sm text-gray-600">{{ exp.company_name }}</p>
                                    </div>
                                    <button type="button" @click="removeExperience(index)" class="text-red-500 hover:text-red-700 font-bold text-xl">&times;</button>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <!-- Employment Tab -->
                    <div v-show="activeTab === 'employment'">
                       <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                             <div>
                                <label class="block mb-2 text-sm font-medium">التخصص*</label>
                                <input type="text" v-model="form.employment.specialization" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium">القسم*</label>
                                <select v-model="form.employment.department_id" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                    <option value="" disabled>اختر...</option>
                                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                </select>
                            </div>
                             <div>
                                <label class="block mb-2 text-sm font-medium">تاريخ التعيين*</label>
                                <input type="date" v-model="form.employment.hire_date" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                            </div>
                             <div>
                                <label class="block mb-2 text-sm font-medium">حالة التوظيف*</label>
                                <select v-model="form.employment.employment_status" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                    <option value="active">نشط</option>
                                    <option value="on_leave">في إجازة</option>
                                    <option value="terminated">منتهية خدمته</option>
                                </select>
                            </div>
                       </div>
                        <div class="mt-6 pt-6 border-t">
                            <h4 class="font-bold text-gray-700 mb-4">تفاصيل العقد</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                               <div>
                                    <label class="block mb-2 text-sm font-medium">نوع العقد*</label>
                                    <select v-model="form.employment.contract_type" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                        <option value="monthly">شهري</option>
                                        <option value="hourly">بالساعة</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium">تاريخ بدء العقد*</label>
                                    <input type="date" v-model="form.employment.start_date" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium">نوع الراتب*</label>
                                    <select v-model="form.employment.salary_type" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                        <option value="monthly">راتب شهري</option>
                                        <option value="hourly">أجر بالساعة</option>
                                    </select>
                                </div>
                                <div v-if="form.employment.salary_type === 'monthly'">
                                    <label class="block mb-2 text-sm font-medium">مبلغ الراتب الأساسي*</label>
                                    <input type="number" step="0.01" v-model="form.employment.salary_amount" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                </div>
                                <div v-if="form.employment.salary_type === 'hourly'">
                                    <label class="block mb-2 text-sm font-medium">الأجر بالساعة*</label>
                                    <input type="number" step="0.01" v-model="form.employment.hourly_rate" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                </div>
                                <div v-if="form.employment.salary_type === 'hourly'">
                                    <label class="block mb-2 text-sm font-medium">ساعات العمل الأسبوعية</label>
                                    <input type="number" v-model="form.employment.working_hours_per_week" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                                </div>
                                 <div class="lg:col-span-3">
                                    <label class="block mb-2 text-sm font-medium">ملاحظات العقد</label>
                                    <textarea rows="3" v-model="form.employment.notes" class="block p-2.5 w-full text-sm bg-gray-50 rounded-lg border border-gray-300"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Assignments Tab -->
                    <div v-show="activeTab === 'assignments'">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-1 border-r pr-4 rtl:border-r-0 rtl:border-l rtl:pr-0 rtl:pl-4">
                                <h4 class="font-bold mb-2 text-gray-700">1. اختر المراحل</h4>
                                <div class="space-y-2 max-h-96 overflow-y-auto">
                                    <label v-for="grade in grades" :key="grade.id" class="flex items-center p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" :value="grade.id" v-model="selectedGradeIds" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                        <span class="ml-3 rtl:ml-0 rtl:mr-3 text-sm font-medium text-gray-800">{{ grade.name }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <h4 class="font-bold mb-2 text-gray-700">2. اختر المقررات والشعب</h4>
                                <div class="space-y-6 max-h-96 overflow-y-auto pr-2">
                                    <div v-if="!selectedGradesData.length" class="text-center text-gray-500 p-8">الرجاء اختيار مرحلة دراسية.</div>
                                    <div v-for="grade in selectedGradesData" :key="grade.id">
                                        <h5 class="font-semibold text-indigo-700 border-b pb-2 mb-3">{{ grade.name }}</h5>
                                        <div v-if="!grade.sections || grade.sections.length === 0" class="text-sm text-red-500 p-2 bg-red-50 rounded-md">
                                            لا توجد شعب معرفة. <Link :href="route('school.sections.index')" class="font-bold underline">إضافة شعب</Link>.
                                        </div>
                                        <div v-else class="space-y-4">
                                            <div v-for="subject in grade.subjects" :key="subject.id" class="pl-2 rtl:pl-0 rtl:pr-2">
                                                <p class="font-medium text-gray-800">{{ subject.name }}</p>
                                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                                                    <label v-for="section in grade.sections" :key="section.id" class="flex items-center text-sm cursor-pointer">
                                                        <input type="checkbox" @change="toggleAssignment(subject.id, section.id)" :checked="form.assignments.some(a => a.subject_id === subject.id && a.section_id === section.id)" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
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
                        </div>
                    </div>
                    <!-- Account Tab -->
                    <div v-show="activeTab === 'account'">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                           <div>
                                <label class="block mb-2 text-sm font-medium">البريد الإلكتروني*</label>
                                <input type="email" v-model="form.account.email" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                           </div>
                           <div></div>
                            <div>
                                <label class="block mb-2 text-sm font-medium">كلمة المرور*</label>
                                <input type="password" v-model="form.account.password" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium">تأكيد كلمة المرور*</label>
                                <input type="password" v-model="form.account.password_confirmation" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t rounded-b-lg flex justify-end items-center space-x-4 rtl:space-x-reverse">
                    <Link :href="route('school.teachers.index')" class="text-gray-600 hover:text-gray-900">إلغاء</Link>
                    <button v-if="activeTab !== 'account'" type="button" @click="activeTab = 'account'" class="bg-gray-200 text-gray-800 px-6 py-2 rounded-md hover:bg-gray-300">
                        الانتقال إلى الحفظ
                    </button>
                    <button v-if="activeTab === 'account'" type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700" :disabled="form.processing">
                        حفظ المعلم
                    </button>
                </div>
            </div>
        </form>
    </HrLayout>
</template>

