<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    employee: Object,
});

// State for modals
const showAddAttachmentModal = ref(false);
const showAddLeaveModal = ref(false);
const showContractModal = ref(false);
const showExperienceModal = ref(false);
const showConfirmLeaveModal = ref(false);


const isEditingPersonalInfo = ref(false);
// --- NEW form for personal info inline editing ---
const personalInfoForm = useForm({
    user: {
        name: props.employee.user.name,
        email: props.employee.user.email,
    },
    middle_name: props.employee.middle_name,
    last_name: props.employee.last_name,
    mother_name: props.employee.mother_name,
    marital_status: props.employee.marital_status,
    nationality: props.employee.nationality,
    national_id_number: props.employee.national_id_number,
    phone_number: props.employee.phone_number,
    address: props.employee.address,
    date_of_birth: props.employee.date_of_birth,
    gender: props.employee.gender,
});

// --- Submit Functions ---
const submitPersonalInfo = () => {
    personalInfoForm.put(route('hr.employees.personal-info.update', props.employee.id), {
        onSuccess: () => { isEditingPersonalInfo.value = false; },
        preserveScroll: true,
    });
};
const cancelPersonalInfoEdit = () => {
    personalInfoForm.reset();
    isEditingPersonalInfo.value = false;
};
// Forms for modals
const attachmentForm = useForm({ attachment_name: '', attachment_file: null });
const leaveForm = useForm({ leave_type: 'إجازة سنوية', start_date: '', end_date: '', reason: '' });
const contractForm = useForm({
    id: null,
    employee_id: props.employee.id,
    contract_type: 'محدد المدة',
    start_date: '',
    end_date: '',
    job_title: props.employee.job_title,
    basic_salary: '',
    housing_allowance: 0,
    transportation_allowance: 0,
    other_allowances: 0,
    status: 'active',
    notice_period_days: 30,
    annual_leave_days: 21,
    notes: '',
});
const experienceForm = useForm({
    company_name: '',
    job_title: '',
    start_date: '',
    end_date: '',
    description: '',
});

// State helpers for modals and actions
const isEditingExperience = ref(false);
const isEditingContract = ref(false);
const isProcessingLeaveAction = ref(false);
const isUpdatingContractStatus = ref(false);
const leaveToUpdate = ref(null);
const newStatusToUpdate = ref('');
const leaveActionText = ref('');

const leaveTypes = ['إجازة سنوية', 'إجازة مرضية', 'إجازة عارضة', 'إجازة بدون راتب', 'أخرى'];

// Helper functions for display formatting
const getStatusClass = (status) => {
    const classes = {
        active: 'bg-green-100 text-green-800', on_leave: 'bg-yellow-100 text-yellow-800',
        terminated: 'bg-red-100 text-red-800', pending: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-green-100 text-green-800', rejected: 'bg-red-100 text-red-800',
        expired: 'bg-gray-100 text-gray-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};
const getStatusText = (status) => {
    switch (status) {
        case 'approved': return 'مقبول';
        case 'rejected': return 'مرفوض';
        case 'pending': default: return 'قيد المراجعة';
    }
};
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' });
};

// --- Submit Functions ---
const submitAttachment = () => { attachmentForm.post(route('hr.employees.attachments.store', props.employee.id), { onSuccess: () => { showAddAttachmentModal.value = false; attachmentForm.reset(); } }); };
const submitLeave = () => { leaveForm.post(route('hr.employees.leaves.store', props.employee.id), { onSuccess: () => { showAddLeaveModal.value = false; leaveForm.reset(); } }); };
const submitExperience = () => {
    const action = isEditingExperience.value
        ? route('hr.employees.experiences.update', { employee: props.employee.id, experience: experienceForm.id })
        : route('hr.employees.experiences.store', props.employee.id);
    const method = isEditingExperience.value ? 'put' : 'post';
    
    experienceForm.submit(method, action, {
        onSuccess: () => {
            showExperienceModal.value = false;
            experienceForm.reset();
        }
    });
};
const submitContract = () => {
    const action = isEditingContract.value ? route('hr.contracts.update', contractForm.id) : route('hr.contracts.store');
    const method = isEditingContract.value ? 'put' : 'post';
    contractForm.submit(method, action, { onSuccess: () => { showContractModal.value = false; }});
};

// --- Action Functions ---
const openAddContractModal = () => {
    isEditingContract.value = false;
    contractForm.reset();
    contractForm.employee_id = props.employee.id;
    contractForm.job_title = props.employee.job_title;
    showContractModal.value = true;
};
const openEditContractModal = (contract) => {
    isEditingContract.value = true;
    contractForm.id = contract.id;
    contractForm.contract_type = contract.contract_type;
    contractForm.start_date = contract.start_date.split('T')[0];
    contractForm.end_date = contract.end_date ? contract.end_date.split('T')[0] : '';
    contractForm.job_title = contract.job_title;
    contractForm.basic_salary = contract.basic_salary;
    contractForm.housing_allowance = contract.housing_allowance;
    contractForm.transportation_allowance = contract.transportation_allowance;
    contractForm.other_allowances = contract.other_allowances;
    contractForm.status = contract.status;
    showContractModal.value = true;
};
const toggleContractStatus = (contract) => {
    const newStatus = contract.status === 'active' ? 'expired' : 'active';
    const action = newStatus === 'active' ? 'تفعيل' : 'إنهاء';
    if (confirm(`هل أنت متأكد من ${action} هذا العقد؟`)) {
        isUpdatingContractStatus.value = true;
        router.put(route('hr.contracts.status.update', contract.id), { status: newStatus }, {
            preserveScroll: true,
            onFinish: () => { isUpdatingContractStatus.value = false; }
        });
    }
};
const openLeaveActionModal = (leave, newStatus) => {
    leaveToUpdate.value = leave;
    newStatusToUpdate.value = newStatus;
    leaveActionText.value = newStatus === 'approved' ? 'الموافقة على' : 'رفض';
    showConfirmLeaveModal.value = true;
};
const confirmLeaveStatusUpdate = () => {
    isProcessingLeaveAction.value = true;
    router.put(route('hr.leaves.update', leaveToUpdate.value.id), { status: newStatusToUpdate.value }, {
        preserveScroll: true,
        onSuccess: () => { showConfirmLeaveModal.value = false; },
        onFinish: () => { isProcessingLeaveAction.value = false; }
    });
};

const displayFormatDate = (dateString) => {
    if (!dateString) return 'N/A';
     return new Date(dateString).toLocaleDateString('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' });
}


// --- Action Functions ---
const openAddExperienceModal = () => {
    isEditingExperience.value = false;
    experienceForm.reset();
    showExperienceModal.value = true;
};


const openEditExperienceModal = (experience) => {
    isEditingExperience.value = true;
    experienceForm.id = experience.id;
    experienceForm.company_name = experience.company_name;
    experienceForm.job_title = experience.job_title;
    experienceForm.start_date = experience.start_date ? new Date(experience.start_date).toISOString().split('T')[0] : '';
    experienceForm.end_date = experience.end_date ? new Date(experience.end_date).toISOString().split('T')[0] : '';
    experienceForm.description = experience.description;
    showExperienceModal.value = true;
};


const deleteExperience = (experience) => {
    if (confirm(`هل أنت متأكد من حذف خبرة "${experience.job_title}"؟`)) {
        router.delete(route('hr.employees.experiences.destroy', { employee: props.employee.id, experience: experience.id }), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="`ملف الموظف - ${employee.user.name}`" />

    <HrLayout>
        <template #header>الملف الشخصي للموظف</template>

        <div class="space-y-6">
            <!-- Employee Header with Full Name -->
            <div class="bg-white shadow-md rounded-lg p-6 flex items-center space-x-6 rtl:space-x-reverse">
                <div class="flex-shrink-0">
                    <div class="w-24 h-24 bg-indigo-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-4xl text-indigo-400"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">{{ employee.user.name }} {{ employee.middle_name }} {{ employee.last_name }}</h2>
                    <p class="text-lg text-gray-600">{{ employee.job_title }}</p>
                    <p class="text-sm text-gray-500">{{ employee.department.name }}</p>
                    <span class="mt-2 inline-block px-3 py-1 text-sm font-semibold leading-5 rounded-full" :class="getStatusClass(employee.employment_status)">
                        {{ employee.employment_status === 'active' ? 'نشط' : (employee.employment_status === 'on_leave' ? 'في إجازة' : 'منتهية خدمته') }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Detailed Personal Info -->
                    <div class="bg-white shadow-md rounded-lg p-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-bold text-gray-800">المعلومات الشخصية</h3>
                            <div v-if="!isEditingPersonalInfo">
                                <button @click="isEditingPersonalInfo = true" class="text-blue-600 hover:text-blue-800" title="تعديل البيانات">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Display Mode -->
                        <div v-if="!isEditingPersonalInfo" class="space-y-2 text-sm text-gray-700">
                             <p><strong class="font-semibold text-gray-900">الاسم الكامل:</strong> {{ employee.user.name }} {{ employee.middle_name }} {{ employee.last_name }}</p>
                             <p><strong class="font-semibold text-gray-900">اسم الأم:</strong> {{ employee.mother_name || '-' }}</p>
                             <p><strong class="font-semibold text-gray-900">الجنسية:</strong> {{ employee.nationality || '-' }}</p>
                             <p><strong class="font-semibold text-gray-900">رقم البصمة:</strong> {{ employee.fingerprint_id || '-' }}</p>
                             <p><strong class="font-semibold text-gray-900">رقم الهوية:</strong> {{ employee.national_id_number || '-' }}</p>
                             <p><strong class="font-semibold text-gray-900">تاريخ الميلاد:</strong> {{ displayFormatDate(employee.date_of_birth) }}</p>
                             <p><strong class="font-semibold text-gray-900">الحالة الاجتماعية:</strong> {{ employee.marital_status || '-' }}</p>
                             <p><strong class="font-semibold text-gray-900">الجنس:</strong> {{ employee.gender === 'male' ? 'ذكر' : (employee.gender === 'female' ? 'أنثى' : '-') }}</p>
                             <p><strong class="font-semibold text-gray-900">البريد الإلكتروني:</strong> {{ employee.user.email }}</p>
                             <p><strong class="font-semibold text-gray-900">رقم الهاتف:</strong> {{ employee.phone_number || '-' }}</p>
                             <p><strong class="font-semibold text-gray-900">العنوان:</strong> {{ employee.address || '-' }}</p>
                        </div>

                        <!-- Edit Mode -->
                        <form v-else @submit.prevent="submitPersonalInfo" class="space-y-4 text-sm text-gray-700">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="font-semibold text-gray-900">الاسم الأول*</label>
                                    <input type="text" v-model="personalInfoForm.user.name" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2">
                                </div>
                                <div>
                                    <label class="font-semibold text-gray-900">الاسم الأوسط</label>
                                    <input type="text" v-model="personalInfoForm.middle_name" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2">
                                </div>
                                <div>
                                    <label class="font-semibold text-gray-900">الاسم الأخير</label>
                                    <input type="text" v-model="personalInfoForm.last_name" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2">
                                </div>
                                <div>
                                    <label class="font-semibold text-gray-900">اسم الأم</label>
                                    <input type="text" v-model="personalInfoForm.mother_name" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2">
                                </div>
                                <div>
                                    <label class="font-semibold text-gray-900">الجنسية</label>
                                    <input type="text" v-model="personalInfoForm.nationality" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2">
                                </div>
                                <div>
                                    <label class="font-semibold text-gray-900">رقم الهوية</label>
                                    <input type="text" v-model="personalInfoForm.national_id_number" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2">
                                </div>
                                <div>
                                    <label class="font-semibold text-gray-900">تاريخ الميلاد</label>
                                    <input type="date" v-model="personalInfoForm.date_of_birth" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2">
                                </div>
                                <div>
                                    <label class="font-semibold text-gray-900">الحالة الاجتماعية</label>
                                    <select v-model="personalInfoForm.marital_status" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2">
                                        <option value="" disabled>اختر...</option>
                                        <option value="أعزب">أعزب</option>
                                        <option value="متزوج">متزوج</option>
                                        <option value="مطلق">مطلق</option>
                                        <option value="أرمل">أرمل</option>
                                    </select>
                                </div>
                                 <div>
                                    <label class="font-semibold text-gray-900">الجنس</label>
                                     <select v-model="personalInfoForm.gender" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2">
                                        <option value="" disabled>اختر...</option>
                                        <option value="male">ذكر</option>
                                        <option value="female">أنثى</option>
                                    </select>
                                </div>
                                 <div>
                                    <label class="font-semibold text-gray-900">البريد الإلكتروني*</label>
                                    <input type="email" v-model="personalInfoForm.user.email" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2">
                                </div>
                                 <div class="sm:col-span-2">
                                    <label class="font-semibold text-gray-900">رقم الهاتف</label>
                                    <input type="tel" v-model="personalInfoForm.phone_number" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="font-semibold text-gray-900">العنوان</label>
                                    <textarea v-model="personalInfoForm.address" rows="3" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"></textarea>
                                </div>
                            </div>

                            <!-- Action Buttons for Edit Mode -->
                            <div class="flex justify-end space-x-2 rtl:space-x-reverse border-t pt-4 mt-4">
                                <button type="button" @click="cancelPersonalInfoEdit" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="personalInfoForm.processing">حفظ التعديلات</button>
                            </div>
                        </form>
                    </div>
                    <!-- Attachments -->
                    <div class="bg-white shadow-md rounded-lg p-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-bold text-gray-800">المرفقات</h3>
                            <button @click="showAddAttachmentModal = true" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold hover:bg-indigo-200">
                                <i class="fas fa-plus"></i> إضافة
                            </button>
                        </div>
                         <ul v-if="employee.attachments.length > 0" class="space-y-2">
                           <li v-for="file in employee.attachments" :key="file.id" class="flex items-center justify-between p-2 rounded-md hover:bg-gray-50">
                               <div>
                                   <i class="fas fa-file-alt text-gray-500 mr-2"></i>
                                   <span class="text-sm font-medium text-gray-800">{{ file.file_name }}</span>
                               </div>
                               <a :href="file.url" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-sm font-bold">
                                   <i class="fas fa-download"></i> تحميل
                               </a>
                           </li>
                        </ul>
                        <div v-else class="text-center py-4">
                            <i class="fas fa-folder-open text-4xl text-gray-300"></i>
                            <p class="text-sm text-gray-500 mt-2">لا يوجد مرفقات.</p>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Work Experience Section (UPDATED) -->
                    <div class="bg-white shadow-md rounded-lg p-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-bold text-gray-800">الخبرات العملية</h3>
                            <button @click="openAddExperienceModal" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold hover:bg-indigo-200">
                                <i class="fas fa-plus"></i> إضافة خبرة
                            </button>
                        </div>
                        <div v-if="employee.work_experiences && employee.work_experiences.length > 0" class="space-y-4">
                           <div v-for="exp in employee.work_experiences" :key="exp.id" class="border-b pb-3 last:border-b-0 last:pb-0">
                               <div class="flex justify-between items-start">
                                   <div>
                                       <h4 class="font-bold text-gray-800">{{ exp.job_title }}</h4>
                                       <p class="text-sm font-medium text-indigo-600">{{ exp.company_name }}</p>
                                       <p class="text-xs text-gray-500">{{ formatDate(exp.start_date) }} - {{ exp.end_date ? formatDate(exp.end_date) : 'الحاضر' }}</p>
                                   </div>
                                   <div class="flex space-x-2 rtl:space-x-reverse text-gray-500">
                                       <button @click="openEditExperienceModal(exp)" class="hover:text-blue-600"><i class="fas fa-edit"></i></button>
                                       <button @click="deleteExperience(exp)" class="hover:text-red-600"><i class="fas fa-trash"></i></button>
                                   </div>
                               </div>
                               <p class="text-sm mt-2 text-gray-600">{{ exp.description }}</p>
                           </div>
                        </div>
                         <p v-else class="text-sm text-gray-500 text-center py-4">لا يوجد خبرات عملية مسجلة.</p>
                    </div>

                    <!-- Contracts Section -->
                    <div class="bg-white shadow-md rounded-lg p-6">
                         <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-bold text-gray-800">العقود</h3>
                            <button @click="openAddContractModal" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold hover:bg-indigo-200">
                                <i class="fas fa-plus"></i> إضافة عقد
                            </button>
                        </div>
                        <div v-if="employee.contracts.length > 0" class="overflow-x-auto">
                           <table class="min-w-full text-sm">
                               <thead class="bg-gray-50">
                                   <tr>
                                       <th class="text-right p-2 font-semibold text-gray-600">نوع العقد</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">تاريخ البدء</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">الراتب الأساسي</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">الحالة</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">الإجراءات</th>
                                   </tr>
                               </thead>
                               <tbody class="text-gray-700">
                                   <tr v-for="contract in employee.contracts" :key="contract.id" class="border-b">
                                       <td class="p-2">{{ contract.contract_type }}</td>
                                       <td class="p-2">{{ formatDate(contract.start_date) }}</td>
                                       <td class="p-2 font-mono">{{ contract.basic_salary }}</td>
                                       <td class="p-2">
                                           <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(contract.status)">
                                               {{ contract.status === 'active' ? 'ساري' : 'منتهي' }}
                                           </span>
                                       </td>
                                       <td class="p-2 space-x-2 rtl:space-x-reverse">
                                            <button @click="openEditContractModal(contract)" class="text-blue-600 hover:text-blue-800"><i class="fas fa-edit"></i></button>
                                            <button @click="toggleContractStatus(contract)" :disabled="isUpdatingContractStatus"
                                                class="text-xs font-bold py-1 px-2 rounded-full"
                                                :class="contract.status === 'active' ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200'">
                                                {{ contract.status === 'active' ? 'إنهاء' : 'تفعيل' }}
                                            </button>
                                       </td>
                                   </tr>
                               </tbody>
                           </table>
                        </div>
                         <p v-else class="text-sm text-gray-500">لا يوجد عقود مسجلة لهذا الموظف.</p>
                    </div>

                    <!-- Leave History Section -->
                    <div class="bg-white shadow-md rounded-lg p-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-bold text-gray-800">سجل الإجازات</h3>
                            <button @click="showAddLeaveModal = true" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold hover:bg-indigo-200">
                                <i class="fas fa-plus"></i> إضافة إجازة
                            </button>
                        </div>
                        <div v-if="employee.leaves.length > 0" class="overflow-x-auto">
                           <table class="min-w-full text-sm">
                               <thead class="bg-gray-50">
                                   <tr>
                                       <th class="text-right p-2 font-semibold text-gray-600">النوع</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">من</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">إلى</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">الحالة</th>
                                       <th class="text-center p-2 font-semibold text-gray-600">الإجراءات</th>
                                   </tr>
                               </thead>
                               <tbody class="text-gray-700">
                                   <tr v-for="leave in employee.leaves" :key="leave.id" class="border-b">
                                       <td class="p-2">{{ leave.leave_type }}</td>
                                       <td class="p-2">{{ formatDate(leave.start_date) }}</td>
                                       <td class="p-2">{{ formatDate(leave.end_date) }}</td>
                                       <td class="p-2">
                                           <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(leave.status)">
                                               {{ getStatusText(leave.status) }}
                                           </span>
                                       </td>
                                       <td class="p-2 text-center">
                                           <div v-if="leave.status === 'pending'" class="flex justify-center items-center space-x-2 rtl:space-x-reverse">
                                                <button @click="openLeaveActionModal(leave, 'approved')" :disabled="isProcessingLeaveAction" class="text-green-600 hover:text-green-800" title="موافقة">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                <button @click="openLeaveActionModal(leave, 'rejected')" :disabled="isProcessingLeaveAction" class="text-red-600 hover:text-red-800" title="رفض">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            </div>
                                            <span v-else class="text-gray-400 text-xs">تم اتخاذ إجراء</span>
                                       </td>
                                   </tr>
                               </tbody>
                           </table>
                        </div>
                         <p v-else class="text-sm text-gray-500">لا يوجد طلبات إجازة مسجلة لهذا الموظف.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Modals are now included below -->

        <!-- Add Work Experience Modal -->
        <div v-if="showExperienceModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-xl font-bold text-gray-800">{{ isEditingExperience ? 'تعديل الخبرة العملية' : 'إضافة خبرة عملية' }}</h3>
                    <button @click="showExperienceModal = false" class="text-gray-500 hover:text-gray-800">&times;</button>
                </div>
                <form @submit.prevent="submitExperience" class="mt-4 space-y-4 max-h-[70vh] overflow-y-auto p-2 text-black">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium">اسم الشركة*</label>
                            <input type="text" v-model="experienceForm.company_name" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                        </div>
                         <div>
                            <label class="block mb-2 text-sm font-medium">المسمى الوظيفي*</label>
                            <input type="text" v-model="experienceForm.job_title" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                        </div>
                         <div>
                            <label class="block mb-2 text-sm font-medium">تاريخ البدء</label>
                            <input type="date" v-model="experienceForm.start_date" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                        </div>
                         <div>
                            <label class="block mb-2 text-sm font-medium">تاريخ الانتهاء (اختياري)</label>
                            <input type="date" v-model="experienceForm.end_date" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                        </div>
                    </div>
                     <div>
                         <label class="block mb-2 text-sm font-medium">الوصف</label>
                        <textarea rows="3" v-model="experienceForm.description" class="block p-2.5 w-full text-sm bg-gray-50 rounded-lg border border-gray-300"></textarea>
                    </div>
                    <div class="flex justify-end pt-4 border-t space-x-2 rtl:space-x-reverse">
                        <button type="button" @click="showExperienceModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="experienceForm.processing">
                            {{ isEditingExperience ? 'حفظ التعديلات' : 'حفظ' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

             <!-- NEW: Leave Action Confirmation Modal -->
             <div v-if="showConfirmLeaveModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                 <h3 class="text-xl font-bold text-gray-800">تأكيد الإجراء</h3>
                <p class="mt-2 text-gray-600">هل أنت متأكد من <span class="font-bold">{{ leaveActionText }}</span> طلب الإجازة؟</p>
                <div class="flex justify-end mt-6 space-x-2 rtl:space-x-reverse">
                    <button @click="showConfirmLeaveModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">
                        إلغاء
                    </button>
                    <button @click="confirmLeaveStatusUpdate" :disabled="isProcessingLeaveAction" class="px-4 py-2 rounded-md text-white"
                            :class="newStatusToUpdate === 'approved' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'">
                        نعم، تأكيد
                    </button>
                </div>
            </div>
        </div>

        <!-- Add/Edit Contract Modal -->
        <div v-if="showContractModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-xl font-bold text-gray-800">{{ isEditingContract ? 'تعديل العقد' : 'إضافة عقد جديد' }}</h3>
                    <button @click="showContractModal = false" class="text-gray-500 hover:text-gray-800">&times;</button>
                </div>
                <form @submit.prevent="submitContract" class="mt-4 space-y-4 max-h-[70vh] overflow-y-auto p-2 text-black">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium">المسمى الوظيفي</label>
                            <input type="text" v-model="contractForm.job_title" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5" required>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium">نوع العقد</label>
                            <select v-model="contractForm.contract_type" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5" required>
                                <option>محدد المدة</option>
                                <option>غير محدد المدة</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium">تاريخ البدء</label>
                            <input type="date" v-model="contractForm.start_date" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5" required>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium">تاريخ الانتهاء (اختياري)</label>
                            <input type="date" v-model="contractForm.end_date" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                        </div>
                         <div>
                            <label class="block mb-2 text-sm font-medium">الراتب الأساسي</label>
                            <input type="number" step="0.01" v-model="contractForm.basic_salary" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5" required>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium">بدل السكن</label>
                            <input type="number" step="0.01" v-model="contractForm.housing_allowance" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium">بدل المواصلات</label>
                            <input type="number" step="0.01" v-model="contractForm.transportation_allowance" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium">بدلات أخرى</label>
                            <input type="number" step="0.01" v-model="contractForm.other_allowances" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium">حالة العقد</label>
                             <select v-model="contractForm.status" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5" required>
                                <option value="active">ساري</option>
                                <option value="pending">قيد المراجعة</option>
                                <option value="expired">منتهي</option>
                                <option value="terminated">ملغي</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end pt-4 border-t space-x-2 rtl:space-x-reverse">
                        <button type="button" @click="showContractModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="contractForm.processing">
                            {{ isEditingContract ? 'حفظ التعديلات' : 'حفظ العقد' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Attachment Modal and Add Leave Modal remain the same -->
        <div v-if="showAddAttachmentModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                 <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-xl font-bold text-gray-800">إضافة مرفق جديد</h3>
                    <button @click="showAddAttachmentModal = false" class="text-gray-500 hover:text-gray-800">&times;</button>
                </div>
                <form @submit.prevent="submitAttachment" class="mt-4 space-y-4">
                    <div>
                        <label for="attachment_name" class="block mb-2 text-sm font-medium text-gray-900">اسم المرفق</label>
                        <input type="text" v-model="attachmentForm.attachment_name" id="attachment_name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="attachment_file" class="block mb-2 text-sm font-medium text-gray-900">اختر الملف</label>
                        <input type="file" @input="attachmentForm.attachment_file = $event.target.files[0]" id="attachment_file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required/>
                    </div>
                    <div class="flex justify-end pt-4 border-t space-x-2 rtl:space-x-reverse">
                        <button type="button" @click="showAddAttachmentModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="attachmentForm.processing">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
        <div v-if="showAddLeaveModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                 <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-xl font-bold text-gray-800">إضافة طلب إجازة</h3>
                    <button @click="showAddLeaveModal = false" class="text-gray-500 hover:text-gray-800">&times;</button>
                </div>
                <form @submit.prevent="submitLeave" class="mt-4 space-y-4">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900">نوع الإجازة</label>
                        <select v-model="leaveForm.leave_type" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <option v-for="type in leaveTypes" :key="type" :value="type">{{ type }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900">تاريخ البدء</label>
                        <input type="date" v-model="leaveForm.start_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-900">تاريخ الانتهاء</label>
                        <input type="date" v-model="leaveForm.end_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                    </div>
                    <div>
                         <label class="block mb-2 text-sm font-medium text-gray-900">السبب</label>
                        <textarea rows="3" v-model="leaveForm.reason" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300" required></textarea>
                    </div>
                    <div class="flex justify-end pt-4 border-t space-x-2 rtl:space-x-reverse">
                        <button type="button" @click="showAddLeaveModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="leaveForm.processing">حفظ</button>
                    </div>
                </form>
            </div>
        </div>

    </HrLayout>
</template>

