<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm, router,usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    teacher: Object,
    grades: Array,
    departments: Array, // <-- استقبال قائمة الأقسام
    leaveTypes: Array,
    leaveBalances: Array,
    criteria: Array, // <-- استقبال معايير التقييم
    averageEvaluationScore: Number,
});


const page = usePage();
const authUser = computed(() => page.props.auth.user);
const canManage = computed(() => {
    return authUser.value.roles.includes('admin') || authUser.value.roles.includes('hr-manager');
});


// هنا يبدأ منطق التقييم 


// --- NEW Evaluation Details Modal State ---
const showEvaluationDetailsModal = ref(false);
const selectedEvaluationForDetails = ref(null);

const openEvaluationDetailsModal = (evaluation) => {
    selectedEvaluationForDetails.value = evaluation;
    showEvaluationDetailsModal.value = true;
};



// --- NEW Evaluation Modal State ---
const showEvaluationModal = ref(false);
const evaluationForm = useForm({
    title: `تقييم ${new Date().getFullYear()}`,
    evaluation_date: new Date().toISOString().split('T')[0],
    overall_notes: '',
    results: props.criteria.map(c => ({
        criterion_id: c.id,
        score: 0,
    })),
});

const totalMaxScore = computed(() => props.criteria.reduce((total, criterion) => total + criterion.max_score, 0));
const currentTotalScore = computed(() => evaluationForm.results.reduce((total, result) => total + Number(result.score), 0));
const finalPercentage = computed(() => {
    if (totalMaxScore.value === 0) return 0;
    return ((currentTotalScore.value / totalMaxScore.value) * 100).toFixed(2);
});
const getCriterionById = (id) => props.criteria.find(c => c.id === id);

const openEvaluationModal = () => {
    evaluationForm.reset();
    showEvaluationModal.value = true;
};
const submitEvaluation = () => {
    evaluationForm.post(route('school.evaluations.store', props.teacher.id), {
        onSuccess: () => { showEvaluationModal.value = false; }
    });
};

// هنا ينتهي منطق التقييم


// --- State for inline editing personal info ---
const isEditingPersonalInfo = ref(false);

// --- State for modals ---
const showAddAttachmentModal = ref(false);
const showAddLeaveModal = ref(false);
const showContractModal = ref(false);
const showExperienceModal = ref(false);
const showConfirmLeaveModal = ref(false);
const showConfirmContractModal = ref(false);
const showAssignmentsModal = ref(false);

// --- Forms ---
const personalInfoForm = useForm({
    user: { name: props.teacher.user.name, email: props.teacher.user.email },
    middle_name: props.teacher.middle_name,
    last_name: props.teacher.last_name,
    department_id: props.teacher.department_id,
    mother_name: props.teacher.mother_name,
    marital_status: props.teacher.marital_status,
    nationality: props.teacher.nationality,
    national_id_number: props.teacher.national_id_number,
    phone_number: props.teacher.phone_number,
    address: props.teacher.address,
    date_of_birth: props.teacher.date_of_birth,
    gender: props.teacher.gender,
});
const contractForm = useForm({ id: null, teacher_id: props.teacher.id, contract_type: 'monthly', start_date: '', end_date: '', salary_type: 'monthly', salary_amount: null, hourly_rate: null, working_hours_per_week: null, notes: '', status: 'active' });
const assignmentsForm = useForm({ assignments: props.teacher.assignments.map(a => ({ subject_id: a.subject_id, section_id: a.section_id })) });
const attachmentForm = useForm({ attachment_name: '', attachment_file: null });
const leaveForm = useForm({ leave_type_id: null, start_date: '', end_date: '', reason: '' });
const experienceForm = useForm({ id: null, company_name: '', job_title: '', start_date: '', end_date: '', description: '' });

// --- State helpers ---
const isEditingExperience = ref(false);
const isEditingContract = ref(false);
const isProcessingLeaveAction = ref(false);
const isUpdatingContractStatus = ref(false);
const leaveToUpdate = ref(null);
const newStatusToUpdate = ref('');
const leaveActionText = ref('');
const contractToUpdate = ref(null);
const newContractStatusToUpdate = ref('');
const contractActionText = ref('');
const selectedGradeIds = ref([]);



// --- Helper functions ---
const getStatusClass = (status) => {
    const classes = { active: 'bg-green-100 text-green-800', on_leave: 'bg-yellow-100 text-yellow-800', terminated: 'bg-red-100 text-red-800', pending: 'bg-yellow-100 text-yellow-800', approved: 'bg-green-100 text-green-800', rejected: 'bg-red-100 text-red-800', expired: 'bg-gray-100 text-gray-800' };
    return classes[status] || 'bg-gray-100 text-gray-800';
};
const getStatusText = (status) => {
    switch (status) { case 'approved': return 'مقبول'; case 'rejected': return 'مرفوض'; case 'pending': default: return 'قيد المراجعة'; }
};
const formatDateForInput = (dateString) => {
    if (!dateString) return null;
    return new Date(dateString).toISOString().split('T')[0];
};
const displayFormatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' });
};
const assignmentsByGrade = computed(() => {
    if (!props.teacher.assignments) return {};
    const grouped = {};
    props.teacher.assignments.forEach(assignment => {
        if (assignment.section && assignment.section.grade) {
            const gradeName = assignment.section.grade.name;
            if (!grouped[gradeName]) { grouped[gradeName] = []; }
            grouped[gradeName].push(assignment);
        }
    });
    return grouped;
});
const selectedGradesData = computed(() => {
    if (!props.grades) return [];
    return props.grades.filter(grade => selectedGradeIds.value.includes(grade.id));
});

// --- Submit Functions ---
const submitPersonalInfo = () => personalInfoForm.put(route('school.teachers.personal-info.update', props.teacher.id), { onSuccess: () => { isEditingPersonalInfo.value = false; }, preserveScroll: true });
const cancelPersonalInfoEdit = () => { personalInfoForm.reset(); isEditingPersonalInfo.value = false; };
const submitAttachment = () => { attachmentForm.post(route('school.teachers.attachments.store', props.teacher.id), { onSuccess: () => { showAddAttachmentModal.value = false; attachmentForm.reset(); } }); };
const submitLeave = () => { leaveForm.post(route('school.teachers.leaves.store', props.teacher.id), { onSuccess: () => { showAddLeaveModal.value = false; leaveForm.reset(); } }); };
const submitExperience = () => {
    const action = isEditingExperience.value ? route('school.teachers.experiences.update', { teacher: props.teacher.id, experience: experienceForm.id }) : route('school.teachers.experiences.store', props.teacher.id);
    const method = isEditingExperience.value ? 'put' : 'post';
    experienceForm.submit(method, action, { onSuccess: () => { showExperienceModal.value = false; experienceForm.reset(); } });
};
const submitContract = () => {
    const action = isEditingContract.value ? route('school.teacher-contracts.update', contractForm.id) : route('school.teacher-contracts.store');
    const method = isEditingContract.value ? 'put' : 'post';
    contractForm.submit(method, action, { onSuccess: () => { showContractModal.value = false; }});
};
const submitAssignments = () => {
    assignmentsForm.put(route('school.teachers.assignments.update', props.teacher.id), {
        onSuccess: () => { showAssignmentsModal.value = false; },
        preserveScroll: true,
    });
};

// --- Action Functions ---
const openAddExperienceModal = () => { isEditingExperience.value = false; experienceForm.reset(); showExperienceModal.value = true; };
const openEditExperienceModal = (experience) => {
    isEditingExperience.value = true;
    experienceForm.id = experience.id;
    experienceForm.company_name = experience.company_name;
    experienceForm.job_title = experience.job_title;
    experienceForm.start_date = formatDateForInput(experience.start_date);
    experienceForm.end_date = formatDateForInput(experience.end_date);
    experienceForm.description = experience.description;
    showExperienceModal.value = true;
};
const deleteExperience = (experience) => {
    if (confirm(`هل أنت متأكد من حذف خبرة "${experience.job_title}"؟`)) {
        router.delete(route('school.teachers.experiences.destroy', { teacher: props.teacher.id, experience: experience.id }), { preserveScroll: true });
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
const openAddContractModal = () => {
    isEditingContract.value = false;
    contractForm.reset();
    contractForm.teacher_id = props.teacher.id;
    showContractModal.value = true;
};
const openEditContractModal = (contract) => {
    isEditingContract.value = true;
    contractForm.id = contract.id;
    contractForm.contract_type = contract.contract_type;
    contractForm.start_date = formatDateForInput(contract.start_date);
    contractForm.end_date = formatDateForInput(contract.end_date);
    contractForm.salary_type = contract.salary_type;
    contractForm.salary_amount = contract.salary_amount;
    contractForm.hourly_rate = contract.hourly_rate;
    contractForm.working_hours_per_week = contract.working_hours_per_week;
    contractForm.notes = contract.notes;
    contractForm.status = contract.status;
    showContractModal.value = true;
};
const openContractStatusModal = (contract) => {
    contractToUpdate.value = contract;
    newContractStatusToUpdate.value = contract.status === 'active' ? 'expired' : 'active';
    contractActionText.value = newContractStatusToUpdate.value === 'active' ? 'تفعيل' : 'إنهاء';
    showConfirmContractModal.value = true;
};
const confirmContractStatusUpdate = () => {
    isUpdatingContractStatus.value = true;
    router.put(route('school.teacher-contracts.status.update', {teacherContract: contractToUpdate.value.id}), { status: newContractStatusToUpdate.value }, {
        preserveScroll: true,
        onSuccess: () => { showConfirmContractModal.value = false; },
        onFinish: () => { isUpdatingContractStatus.value = false; }
    });
};
const toggleAssignmentInModal = (subjectId, sectionId) => {
    const assignmentIndex = assignmentsForm.assignments.findIndex(a => a.subject_id === subjectId && a.section_id === sectionId);
    if (assignmentIndex > -1) {
        assignmentsForm.assignments = assignmentsForm.assignments.filter((_, index) => index !== assignmentIndex);
    } else {
        assignmentsForm.assignments = [...assignmentsForm.assignments, { subject_id: subjectId, section_id: sectionId }];
    }
};
</script>

<template>
    <Head :title="`ملف المعلم - ${teacher.user.name}`" />
    <HrLayout>
        <template #header>الملف الشخصي للمعلم</template>
        <div class="space-y-6">
            <!-- Teacher Header -->
            <div class="bg-white shadow-md rounded-lg p-6 flex items-center space-x-6 rtl:space-x-reverse">
                <div class="flex-shrink-0">
                    <div class="w-24 h-24 bg-indigo-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-chalkboard-teacher text-4xl text-indigo-400"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">{{ teacher.user.full_name }}</h2>
                    <p class="text-lg text-gray-600">{{ teacher.specialization }}</p>
                    <p class="text-sm text-gray-500">{{ teacher.department ? teacher.department.name : 'غير محدد' }}</p>
                    <span class="mt-2 inline-block px-3 py-1 text-sm font-semibold leading-5 rounded-full" :class="getStatusClass(teacher.employment_status)">
                        {{ teacher.employment_status === 'active' ? 'نشط' : 'غير نشط' }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column -->
                <div class="lg:col-span-1 space-y-6">

                       <!-- Personal Info (Editable) -->
                       <div class="bg-white shadow-md rounded-lg p-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-bold text-gray-800">المعلومات الشخصية</h3>
                            <div v-if="!isEditingPersonalInfo && canManage">
                                <button @click="isEditingPersonalInfo = true" class="text-blue-600 hover:text-blue-800" title="تعديل"><i class="fas fa-edit"></i></button>
                            </div>
                        </div>
                        <div v-if="!isEditingPersonalInfo" class="space-y-2 text-sm text-gray-700">
                             <p><strong class="font-semibold text-gray-900">الاسم الكامل:</strong> {{ teacher.user.full_name }}</p>
                             <p><strong class="font-semibold text-gray-900">القسم:</strong> {{ teacher.department ? teacher.department.name : '-' }}</p>
                             <p><strong class="font-semibold text-gray-900">اسم الأم:</strong> {{ teacher.mother_name || '-' }}</p>
                             <p><strong class="font-semibold text-gray-900">الجنسية:</strong> {{ teacher.nationality || '-' }}</p>
                             <p><strong class="font-semibold text-gray-900">رقم الهوية:</strong> {{ teacher.national_id_number || '-' }}</p>
                             <p><strong class="font-semibold text-gray-900">تاريخ الميلاد:</strong> {{ displayFormatDate(teacher.date_of_birth) }}</p>
                             <p><strong class="font-semibold text-gray-900">الحالة الاجتماعية:</strong> {{ teacher.marital_status || '-' }}</p>
                             <p><strong class="font-semibold text-gray-900">الجنس:</strong> {{ teacher.gender === 'male' ? 'ذكر' : (teacher.gender === 'female' ? 'أنثى' : '-') }}</p>
                             <p><strong class="font-semibold text-gray-900">البريد الإلكتروني:</strong> {{ teacher.user.email }}</p>
                             <p><strong class="font-semibold text-gray-900">رقم الهاتف:</strong> {{ teacher.phone_number || '-' }}</p>
                             <p><strong class="font-semibold text-gray-900">العنوان:</strong> {{ teacher.address || '-' }}</p>
                        </div>
                        <form v-else @submit.prevent="submitPersonalInfo" class="space-y-4 text-sm text-gray-700">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div><label class="font-semibold">الاسم الأول*</label><input type="text" v-model="personalInfoForm.user.name" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"></div>
                                <div><label class="font-semibold">الاسم الأوسط</label><input type="text" v-model="personalInfoForm.middle_name" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"></div>
                                <div><label class="font-semibold">الاسم الأخير</label><input type="text" v-model="personalInfoForm.last_name" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"></div>
                                <div><label class="font-semibold">القسم*</label><select v-model="personalInfoForm.department_id" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"><option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option></select></div>
                                <div><label class="font-semibold">اسم الأم</label><input type="text" v-model="personalInfoForm.mother_name" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"></div>
                                <div><label class="font-semibold">الجنسية</label><input type="text" v-model="personalInfoForm.nationality" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"></div>
                                <div><label class="font-semibold">رقم الهوية</label><input type="text" v-model="personalInfoForm.national_id_number" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"></div>
                                <div><label class="font-semibold">تاريخ الميلاد</label><input type="date" v-model="personalInfoForm.date_of_birth" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"></div>
                                <div><label class="font-semibold">الحالة الاجتماعية</label><select v-model="personalInfoForm.marital_status" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"><option value="أعزب">أعزب</option> <option value="متزوج">متزوج</option> <option value="مطلق">مطلق</option> <option value="أرمل">أرمل</option></select></div>
                                <div><label class="font-semibold">الجنس</label><select v-model="personalInfoForm.gender" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"><option value="male">ذكر</option> <option value="female">أنثى</option></select></div>
                                <div class="sm:col-span-2"><label class="font-semibold">البريد الإلكتروني*</label><input type="email" v-model="personalInfoForm.user.email" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"></div>
                                <div class="sm:col-span-2"><label class="font-semibold">رقم الهاتف</label><input type="tel" v-model="personalInfoForm.phone_number" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"></div>
                                <div class="sm:col-span-2"><label class="font-semibold">العنوان</label><textarea v-model="personalInfoForm.address" rows="3" class="mt-1 bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2"></textarea></div>
                            </div>
                            <div class="flex justify-end space-x-2 rtl:space-x-reverse border-t pt-4 mt-4">
                                <button type="button" @click="cancelPersonalInfoEdit" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="personalInfoForm.processing">حفظ</button>
                            </div>
                        </form>
                    </div>
                    <div class="bg-white shadow-md rounded-lg p-6">
                        <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">أرصدة الإجازات</h3>
                        <div v-if="leaveBalances.length > 0" class="space-y-4">
                            <div v-for="balance in leaveBalances" :key="balance.name">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm font-medium text-gray-700">{{ balance.name }}</span>
                                    <span class="text-sm font-bold text-gray-800">{{ balance.available }} / {{ balance.total }} يوم</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-indigo-600 h-2.5 rounded-full" :style="{ width: (balance.used / balance.total * 100) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-center text-gray-500 py-4">لم يتم تعريف أنواع إجازات بعد.</p>
                    </div>

                      <!-- NEW Evaluation History Card -->
                      <div class="bg-white shadow-md rounded-lg p-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-bold text-gray-800">سجل التقييمات</h3>
                            <button v-if="canManage" @click="openEvaluationModal" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold hover:bg-indigo-200">
                                <i class="fas fa-plus"></i> بدء تقييم
                            </button>
                        </div>
                        <div v-if="teacher.evaluations && teacher.evaluations.length > 0">
                            <div class="text-center mb-4">
                                <p class="text-sm text-gray-600">متوسط التقييم العام</p>
                                <p class="text-4xl font-bold text-indigo-600">{{ averageEvaluationScore || 0 }}%</p>
                            </div>
                            <div class="space-y-3">
                               <div v-for="evalItem in teacher.evaluations.slice(0, 5)" :key="evalItem.id" class="flex justify-between items-center p-2 rounded-md hover:bg-gray-50">
                                   <div>
                                       <p class="font-semibold text-gray-800">{{ evalItem.title }}</p>
                                       <p class="text-xs text-gray-500">{{ displayFormatDate(evalItem.evaluation_date) }}</p>
                                   </div>
                                   <div class="flex items-center space-x-2 rtl:space-x-reverse">
                                       <span class="text-lg font-bold text-gray-700">{{ evalItem.final_score_percentage }}%</span>
                                       <button @click="openEvaluationDetailsModal(evalItem)" class="text-blue-600 hover:text-blue-800" title="عرض التفاصيل">
                                           <i class="fas fa-eye"></i>
                                       </button>
                                   </div>
                               </div>
                            </div>
                        </div>
                         <p v-else class="text-sm text-gray-500 text-center py-4">لا يوجد تقييمات سابقة.</p>
                    </div>
                 
                    <!-- Attachments -->
                    <div class="bg-white shadow-md rounded-lg p-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-bold text-gray-800">المرفقات</h3>
                            <button @click="showAddAttachmentModal = true" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold hover:bg-indigo-200">
                                <i class="fas fa-plus"></i> إضافة
                            </button>
                        </div>
                         <ul v-if="teacher.attachments.length > 0" class="space-y-2">
                           <li v-for="file in teacher.attachments" :key="file.id" class="flex items-center justify-between p-2 rounded-md hover:bg-gray-50">
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
                    <!-- Work Experience -->
                    <div class="bg-white shadow-md rounded-lg p-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-bold text-gray-800">الخبرات العملية</h3>
                            <button @click="openAddExperienceModal" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold hover:bg-indigo-200">
                                <i class="fas fa-plus"></i> إضافة خبرة
                            </button>
                        </div>
                        <div v-if="teacher.work_experiences && teacher.work_experiences.length > 0" class="space-y-4">
                           <div v-for="exp in teacher.work_experiences" :key="exp.id" class="border-b pb-3 last:border-b-0 last:pb-0">
                               <div class="flex justify-between items-start">
                                   <div>
                                       <h4 class="font-bold text-gray-800">{{ exp.job_title }}</h4>
                                       <p class="text-sm font-medium text-indigo-600">{{ exp.company_name }}</p>
                                       <p class="text-xs text-gray-500">{{ displayFormatDate(exp.start_date) }} - {{ exp.end_date ? displayFormatDate(exp.end_date) : 'الحاضر' }}</p>
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

                    <!-- Assignments -->
                    <div class="bg-white shadow-md rounded-lg p-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-xl font-bold text-gray-800">المقررات المسندة</h3>
                             <button @click="showAssignmentsModal = true" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold hover:bg-indigo-200">
                                <i class="fas fa-edit"></i> تعديل الإسناد
                            </button>
                        </div>
                        <div v-if="Object.keys(assignmentsByGrade).length > 0" class="space-y-4">
                            <div v-for="(assignments, gradeName) in assignmentsByGrade" :key="gradeName">
                                <h4 class="font-semibold text-indigo-700">{{ gradeName }}</h4>
                                <ul class="list-disc list-inside mt-2 space-y-1 text-gray-700">
                                   <li v-for="assignment in assignments" :key="assignment.id">
                                        {{ assignment.subject.name }} (شعبة {{ assignment.section.name }})
                                   </li>
                                </ul>
                            </div>
                        </div>
                        <p v-else class="text-gray-500 text-center py-4">لم يتم إسناد أي مقررات لهذا المعلم بعد.</p>
                    </div>

                    <!-- Contracts Section -->
                    <div class="bg-white shadow-md rounded-lg p-6">
                         <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-bold text-gray-800">العقود</h3>
                            <button @click="openAddContractModal" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold hover:bg-indigo-200">
                                <i class="fas fa-plus"></i> إضافة عقد
                            </button>
                        </div>
                        <div v-if="teacher.contracts && teacher.contracts.length > 0" class="overflow-x-auto">
                           <table class="min-w-full text-sm">
                               <thead class="bg-gray-50">
                                   <tr>
                                       <th class="text-right p-2 font-semibold text-gray-600">نوع العقد</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">تاريخ البدء</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">الراتب</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">الحالة</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">الإجراءات</th>
                                   </tr>
                               </thead>
                               <tbody class="text-gray-700">
                                   <tr v-for="contract in teacher.contracts" :key="contract.id" class="border-b">
                                       <td class="p-2">{{ contract.contract_type }}</td>
                                       <td class="p-2">{{ displayFormatDate(contract.start_date) }}</td>
                                       <td class="p-2 font-mono">{{ contract.salary_type === 'monthly' ? contract.salary_amount : contract.hourly_rate + ' / ساعة' }}</td>
                                       <td class="p-2">
                                           <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(contract.status)">
                                               {{ contract.status === 'active' ? 'ساري' : 'منتهي' }}
                                           </span>
                                       </td>
                                       <td class="p-2 space-x-2 rtl:space-x-reverse">
                                            <button @click="openEditContractModal(contract)" class="text-blue-600 hover:text-blue-800" title="تعديل العقد"><i class="fas fa-edit"></i></button>
                                            <button @click="openContractStatusModal(contract)" class="text-xs font-bold py-1 px-2 rounded-full"
                                                :class="contract.status === 'active' ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200'">
                                                {{ contract.status === 'active' ? 'إنهاء' : 'تفعيل' }}
                                            </button>
                                       </td>
                                   </tr>
                               </tbody>
                           </table>
                        </div>
                         <p v-else class="text-sm text-gray-500 text-center py-4">لا يوجد عقود مسجلة.</p>
                    </div>

                    <!-- Leave History Section -->
                    <div class="bg-white shadow-md rounded-lg p-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-bold text-gray-800">سجل الإجازات</h3>
                            <button v-if="canManage" @click="showAddLeaveModal = true" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold hover:bg-indigo-200"><i class="fas fa-plus"></i> إضافة إجازة</button>
                        </div>
                        <div v-if="teacher.leaves && teacher.leaves.length > 0" class="overflow-x-auto">
                           <table class="min-w-full text-sm">
                               <thead class="bg-gray-50">
                                   <tr>
                                       <th class="text-right p-2 font-semibold text-gray-600">النوع</th><th class="text-right p-2 font-semibold text-gray-600">من</th><th class="text-right p-2 font-semibold text-gray-600">إلى</th><th class="text-right p-2 font-semibold text-gray-600">الحالة</th><th class="text-center p-2 font-semibold text-gray-600">الإجراءات</th>
                                   </tr>
                               </thead>
                               <tbody class="text-gray-700">
                                   <tr v-for="leave in teacher.leaves" :key="leave.id" class="border-b">
                                       <td class="p-2">{{ leave.leave_type ? leave.leave_type.name : leave.leave_type }}</td><td class="p-2">{{ displayFormatDate(leave.start_date) }}</td><td class="p-2">{{ displayFormatDate(leave.end_date) }}</td>
                                       <td class="p-2"><span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(leave.status)">{{ getStatusText(leave.status) }}</span></td>
                                       <td class="p-2 text-center">
                                           <div v-if="leave.status === 'pending' && canManage" class="flex justify-center items-center space-x-2 rtl:space-x-reverse">
                                                <button @click="openLeaveActionModal(leave, 'approved')" :disabled="isProcessingLeaveAction" class="text-green-600 hover:text-green-800" title="موافقة"><i class="fas fa-check-circle"></i></button>
                                                <button @click="openLeaveActionModal(leave, 'rejected')" :disabled="isProcessingLeaveAction" class="text-red-600 hover:text-red-800" title="رفض"><i class="fas fa-times-circle"></i></button>
                                            </div>
                                            <span v-else class="text-gray-400 text-xs">تم اتخاذ إجراء</span>
                                       </td>
                                   </tr>
                               </tbody>
                           </table>
                        </div>
                         <p v-else class="text-sm text-gray-500 text-center py-4">لا يوجد طلبات إجازة مسجلة.</p>
                    </div>

                </div>
            </div>
        </div>

        <!-- All Modals are now included below -->
 
        <div v-if="showAssignmentsModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-4xl">
                 <div class="flex justify-between items-center border-b pb-3 mb-4"><h3 class="text-xl font-bold text-gray-800">تعديل إسناد المقررات</h3><button @click="showAssignmentsModal = false" class="text-gray-500 hover:text-gray-800">&times;</button></div>
                <form @submit.prevent="submitAssignments" class="text-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-h-[70vh] overflow-y-auto p-2">
                        <!-- Grade Selector -->
                        <div class="md:col-span-1 border-r pr-4 rtl:border-r-0 rtl:border-l rtl:pr-0 rtl:pl-4">
                            <h4 class="font-bold mb-2 text-gray-700">1. اختر المراحل</h4>
                            <div class="space-y-2">
                                <label v-for="grade in grades" :key="grade.id" class="flex items-center p-2 rounded-lg hover:bg-gray-50 cursor-pointer"><input type="checkbox" :value="grade.id" v-model="selectedGradeIds" class="h-4 w-4 text-indigo-600 rounded"><span class="mr-3 text-sm font-medium">{{ grade.name }}</span></label>
                            </div>
                        </div>
                        <!-- Subject & Section Selector -->
                        <div class="md:col-span-2">
                            <h4 class="font-bold mb-2 text-gray-700">2. اختر المقررات والشعب</h4>
                            <div class="space-y-6">
                                <div v-if="!selectedGradesData.length" class="text-center text-gray-500 p-8">اختر مرحلة لعرض مقرراتها.</div>
                                <div v-for="grade in selectedGradesData" :key="grade.id">
                                    <h5 class="font-semibold text-indigo-700 border-b pb-2 mb-3">{{ grade.name }}</h5>
                                    <div class="space-y-4">
                                        <div v-for="subject in grade.subjects" :key="subject.id" class="pl-2 rtl:pl-0 rtl:pr-2">
                                            <p class="font-medium text-gray-800">{{ subject.name }}</p>
                                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                                                <label v-for="section in grade.sections" :key="section.id" class="flex items-center text-sm cursor-pointer">
                                                    <input type="checkbox" @change="toggleAssignmentInModal(subject.id, section.id)" :checked="assignmentsForm.assignments.some(a => a.subject_id === subject.id && a.section_id === section.id)" class="h-4 w-4 text-indigo-600 rounded">
                                                    <span class="mr-2 text-gray-700">شعبة {{ section.name }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end pt-4 border-t mt-4"><button type="button" @click="showAssignmentsModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button><button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md mr-2" :disabled="assignmentsForm.processing">حفظ التعديلات</button></div>
                </form>
            </div>
        </div>

        <!-- Add/Edit Contract Modal -->
<div v-if="showContractModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl">
        <div class="flex justify-between items-center border-b pb-3">
            <h3 class="text-xl font-bold text-gray-800">{{ isEditingContract ? 'تعديل العقد' : 'إضافة عقد جديد' }}</h3>
            <button @click="showContractModal = false" class="text-gray-500 hover:text-gray-800">&times;</button>
        </div>
        <form @submit.prevent="submitContract" class="mt-4 space-y-4 max-h-[70vh] overflow-y-auto p-2 text-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block mb-2 text-sm font-medium">نوع العقد*</label>
                    <select v-model="contractForm.contract_type" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5" required>
                        <option value="monthly">شهري</option>
                        <option value="hourly">بالساعة</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium">تاريخ بدء العقد*</label>
                    <input type="date" v-model="contractForm.start_date" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5" required>
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium">تاريخ انتهاء العقد (اختياري)</label>
                    <input type="date" v-model="contractForm.end_date" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium">نوع الراتب*</label>
                    <select v-model="contractForm.salary_type" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5" required>
                        <option value="monthly">راتب شهري</option>
                        <option value="hourly">أجر بالساعة</option>
                    </select>
                </div>
                <div v-if="contractForm.salary_type === 'monthly'">
                    <label class="block mb-2 text-sm font-medium">مبلغ الراتب الأساسي*</label>
                    <input type="number" step="0.01" v-model="contractForm.salary_amount" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                </div>
                <div v-if="contractForm.salary_type === 'hourly'">
                    <label class="block mb-2 text-sm font-medium">الأجر بالساعة*</label>
                    <input type="number" step="0.01" v-model="contractForm.hourly_rate" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                </div>
                <div v-if="contractForm.salary_type === 'hourly'">
                    <label class="block mb-2 text-sm font-medium">ساعات العمل الأسبوعية</label>
                    <input type="number" v-model="contractForm.working_hours_per_week" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium">حالة العقد*</label>
                     <select v-model="contractForm.status" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5" required>
                        <option value="active">ساري</option>
                        <option value="pending">قيد المراجعة</option>
                        <option value="expired">منتهي</option>
                        <option value="terminated">ملغي</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block mb-2 text-sm font-medium">ملاحظات</label>
                    <textarea rows="3" v-model="contractForm.notes" class="block p-2.5 w-full text-sm bg-gray-50 rounded-lg border border-gray-300"></textarea>
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

        <!-- Add/Edit Work Experience Modal -->
        <div v-if="showExperienceModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-xl font-bold text-gray-800">{{ isEditingExperience ? 'تعديل الخبرة العملية' : 'إضافة خبرة عملية' }}</h3>
                    <button @click="showExperienceModal = false" class="text-gray-500 hover:text-gray-800">&times;</button>
                </div>
                <form @submit.prevent="submitExperience" class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="block mb-2 text-sm font-medium">اسم الشركة*</label><input type="text" v-model="experienceForm.company_name" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5"></div>
                        <div><label class="block mb-2 text-sm font-medium">المسمى الوظيفي*</label><input type="text" v-model="experienceForm.job_title" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5"></div>
                        <div><label class="block mb-2 text-sm font-medium">تاريخ البدء</label><input type="date" v-model="experienceForm.start_date" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5"></div>
                        <div><label class="block mb-2 text-sm font-medium">تاريخ الانتهاء (اختياري)</label><input type="date" v-model="experienceForm.end_date" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5"></div>
                    </div>
                    <div><label class="block mb-2 text-sm font-medium">الوصف</label><textarea rows="3" v-model="experienceForm.description" class="block p-2.5 w-full text-sm bg-gray-50 rounded-lg border border-gray-300"></textarea></div>
                    <div class="flex justify-end pt-4 border-t space-x-2 rtl:space-x-reverse"><button type="button" @click="showExperienceModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button><button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="experienceForm.processing">{{ isEditingExperience ? 'حفظ التعديلات' : 'حفظ' }}</button></div>
                </form>
            </div>
        </div>
        <!-- Add Attachment Modal -->
         <div v-if="showAddAttachmentModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                 <div class="flex justify-between items-center border-b pb-3"><h3 class="text-xl font-bold text-gray-800">إضافة مرفق جديد</h3><button @click="showAddAttachmentModal = false" class="text-gray-500 hover:text-gray-800">&times;</button></div>
                <form @submit.prevent="submitAttachment" class="mt-4 space-y-4">
                    <div><label for="attachment_name" class="block mb-2 text-sm font-medium text-gray-900">اسم المرفق</label><input type="text" v-model="attachmentForm.attachment_name" id="attachment_name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required></div>
                    <div><label for="attachment_file" class="block mb-2 text-sm font-medium text-gray-900">اختر الملف</label><input type="file" @input="attachmentForm.attachment_file = $event.target.files[0]" id="attachment_file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required/></div>
                    <div class="flex justify-end pt-4 border-t space-x-2 rtl:space-x-reverse"><button type="button" @click="showAddAttachmentModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button><button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="attachmentForm.processing">حفظ</button></div>
                </form>
            </div>
        </div>
        <!-- Add Leave Modal -->
     <!-- Add Leave Modal -->
     <div v-if="showAddLeaveModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <div class="flex justify-between items-center border-b pb-3"><h3 class="text-xl font-bold text-gray-800">إضافة طلب إجازة</h3><button @click="showAddLeaveModal = false" class="text-gray-500 hover:text-gray-800">&times;</button></div>
                <form @submit.prevent="submitLeave" class="mt-4 space-y-4">
                    <div>
                        
                        <label class="block mb-2 text-sm font-medium text-gray-900">نوع الإجازة*</label>
                        <select v-model="leaveForm.leave_type_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <option :value="null" disabled>-- اختر نوع الإجازة --</option>
                            <option v-for="type in leaveTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                        <div v-if="leaveForm.errors.leave_type_id" class="text-sm text-red-600 mt-1">{{ leaveForm.errors.leave_type_id }}</div>
                    </div>
                    <div><label class="block mb-2 text-sm font-medium text-gray-900">تاريخ البدء*</label><input type="date" v-model="leaveForm.start_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required></div>
                    <div><label class="block mb-2 text-sm font-medium text-gray-900">تاريخ الانتهاء*</label><input type="date" v-model="leaveForm.end_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required></div>
                    <div v-if="leaveForm.errors.end_date" class="text-sm text-red-600 -mt-2 mb-2">{{ leaveForm.errors.end_date }}</div>
                    <div><label class="block mb-2 text-sm font-medium text-gray-900">السبب*</label><textarea rows="3" v-model="leaveForm.reason" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300" required></textarea></div>
                    <div class="flex justify-end pt-4 border-t space-x-2 rtl:space-x-reverse"><button type="button" @click="showAddLeaveModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button><button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="leaveForm.processing">حفظ</button></div>
                </form>
            </div>
        </div>
        <!-- Leave Action Confirmation Modal -->
        <div v-if="showConfirmLeaveModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-xl font-bold text-gray-800">تأكيد الإجراء</h3>
                <p class="mt-2 text-gray-600">هل أنت متأكد من <span class="font-bold">{{ leaveActionText }}</span> طلب الإجازة؟</p>
                <div class="flex justify-end mt-6 space-x-2 rtl:space-x-reverse">
                    <button @click="showConfirmLeaveModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                    <button @click="confirmLeaveStatusUpdate" :disabled="isProcessingLeaveAction" class="px-4 py-2 rounded-md text-white" :class="newStatusToUpdate === 'approved' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'">نعم، تأكيد</button>
                </div>
            </div>
        </div>
        <!-- Contract Status Confirmation Modal -->
        <div v-if="showConfirmContractModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                 <h3 class="text-xl font-bold text-gray-800">تأكيد الإجراء</h3>
                <p class="mt-2 text-gray-600">هل أنت متأكد من <span class="font-bold">{{ contractActionText }}</span> هذا العقد؟</p>
                <div class="flex justify-end mt-6 space-x-2 rtl:space-x-reverse">
                    <button @click="showConfirmContractModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                    <button @click="confirmContractStatusUpdate" :disabled="isUpdatingContractStatus" class="px-4 py-2 rounded-md text-white" :class="newContractStatusToUpdate === 'active' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'">نعم، تأكيد</button>
                </div>
            </div>
        </div>


          <!-- NEW Evaluation Modal -->
          <div v-if="showEvaluationModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-0 w-full max-w-4xl">
                <form @submit.prevent="submitEvaluation">
                    <div class="p-6 border-b">
                        <h2 class="text-2xl font-bold text-gray-800">تقييم: {{ teacher.user.full_name }}</h2>
                        <p class="text-gray-600">{{ teacher.specialization }} - {{ teacher.department.name }}</p>
                    </div>
                    <div class="p-6 space-y-6 max-h-[60vh] overflow-y-auto">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-800">عنوان التقييم</label>
                                <input type="text" v-model="evaluationForm.title" class="mt-1 block w-full rounded-md" required>
                            </div>
                             <div>
                                <label class="block text-sm font-medium text-gray-800">تاريخ التقييم</label>
                                <input type="date" v-model="evaluationForm.evaluation_date" class="mt-1 block w-full rounded-md" required>
                            </div>
                        </div>
                        <hr>
                        <h3 class="text-lg font-semibold text-gray-800">بنود التقييم</h3>
                        <div class="space-y-4">
                            <div v-for="result in evaluationForm.results" :key="result.criterion_id" class="p-4 bg-gray-50 rounded-lg">
                                <label class="block font-medium text-gray-800">{{ getCriterionById(result.criterion_id).name }}</label>
                                <p class="text-xs text-gray-500 mb-2">{{ getCriterionById(result.criterion_id).description }}</p>
                                <div class="flex items-center space-x-4 rtl:space-x-reverse">
                                    <input type="range" v-model="result.score" :max="getCriterionById(result.criterion_id).max_score" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                    <span class="font-bold text-indigo-600 w-16 text-center">{{ result.score }} / {{ getCriterionById(result.criterion_id).max_score }}</span>
                                </div>
                            </div>
                        </div>
                         <hr>
                        <div>
                            <label class="block text-sm font-medium text-gray-800">الملاحظات العامة</label>
                            <textarea style="border: black solid 1px" v-model="evaluationForm.overall_notes" rows="4" class="mt-1 block w-full rounded-md"></textarea>
                        </div>
                        <div class="p-4 bg-indigo-50 rounded-lg text-center">
                            <p class="text-sm font-medium text-indigo-800">النتيجة النهائية للتقييم</p>
                            <p class="text-3xl font-bold text-indigo-600">{{ finalPercentage }}%</p>
                        </div>
                    </div>
                    <div class="p-6 bg-gray-50 rounded-b-lg flex justify-end space-x-2 rtl:space-x-reverse">
                         <button type="button" @click="showEvaluationModal = false" class="bg-gray-200 px-4 py-2 rounded-md">إلغاء</button>
                         <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md" :disabled="evaluationForm.processing">حفظ التقييم</button>
                    </div>
                </form>
            </div>
        </div>



          <!-- NEW Evaluation Details Modal -->
          <div v-if="showEvaluationDetailsModal" @click.self="showEvaluationDetailsModal = false" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div v-if="selectedEvaluationForDetails" class="bg-white rounded-lg shadow-xl p-0 w-full max-w-3xl">
                <div class="p-6 border-b">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">{{ selectedEvaluationForDetails.title }}</h3>
                            <p class="text-sm text-gray-500">تاريخ التقييم: {{ displayFormatDate(selectedEvaluationForDetails.evaluation_date) }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-sm text-gray-600">النتيجة النهائية</p>
                            <p class="text-3xl font-bold text-indigo-600">{{ selectedEvaluationForDetails.final_score_percentage }}%</p>
                        </div>
                    </div>
                </div>
                <div class="p-6 max-h-[60vh] overflow-y-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50  text-gray-700">
                            <tr>
                                <th class="py-2 px-3 text-right font-semibold text-sm">المعيار</th>
                                <th class="py-2 px-3 text-center font-semibold text-sm">تقييم المدير</th>
                                <th class="py-2 px-3 text-center font-semibold text-sm">تقييم المسؤول</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="result in selectedEvaluationForDetails.results" :key="result.id">
                                <td class="py-3 px-3">
                                    <p class="font-medium text-gray-800">{{ result.criterion.name }}</p>
                                    <p class="text-xs text-gray-500">الدرجة القصوى: {{ result.criterion.max_score }}</p>
                                </td>
                                <td class="py-3 px-3 text-center font-mono text-lg text-gray-700">{{ result.manager_score ?? '-' }}</td>
                                <td class="py-3 px-3 text-center font-mono text-lg text-gray-700">{{ result.admin_score ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                     <div v-if="selectedEvaluationForDetails.overall_notes" class="mt-6 border-t pt-4">
                        <h4 class="font-semibold text-gray-800">الملاحظات العامة:</h4>
                        <p class="text-sm text-gray-600 mt-2 whitespace-pre-wrap">{{ selectedEvaluationForDetails.overall_notes }}</p>
                    </div>
                </div>
                <div class="p-4 bg-gray-50 rounded-b-lg flex justify-end">
                    <button @click="showEvaluationDetailsModal = false" class="bg-gray-200 px-4 py-2 rounded-md">إغلاق</button>
                </div>
            </div>
        </div>

    </HrLayout>
</template>
<style>
input{
    color: black !important;
}
textarea{
    color: black !important;
}
</style>