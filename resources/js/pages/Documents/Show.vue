<script setup>
import HrLayout from '@/layouts/HrLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    document: Object,
    userCurrentTask: Object,
    organizationDetails: Object,
});

const page = usePage();
const currentUser = page.props.auth.user;

const canEditDraft = computed(() => {
    if (!props.document || !props.document.status || !currentUser) {
        return false;
    }
    return props.document.status === 'draft' && props.document.created_by_user_id === currentUser.id;
});

// دالة لتحديد المعتمد النهائي من مسار العمل
const finalApprover = computed(() => {
    // إذا لم تكن الوثيقة معتمدة، نعرض بيانات المنشئ كقيمة افتراضية
    if (props.document.status !== 'approved' && props.document.status !== 'sent') {
        return {
            user: props.document.creator,
            department: props.document.department
        };
    }
    
    // البحث عن آخر خطوة موافقة مكتملة في مسار العمل
    const approvalSteps = props.document.workflow_steps.filter(step => step.action === 'approve' && step.completed_at);
    
    if (approvalSteps.length > 0) {
        const lastApprovalStep = approvalSteps[approvalSteps.length - 1];
        return {
            user: lastApprovalStep.processed_by,
            department: lastApprovalStep.to_department
        };
    }

    // في حال لم توجد خطوات موافقة، نعود لعرض بيانات المنشئ
    return {
        user: props.document.creator,
        department: props.document.department
    };
});


const form = useForm({
    action: 'approve',
    notes: '',
});

const submitAction = () => {
    form.post(route('documents.workflow.action', props.document.id), {
        onSuccess: () => {
            // Controller will handle the redirect
        },
    });
};

const printDocument = () => {
    window.print();
};

const getStatusDetails = (status) => {
    const statuses = {
        draft: { text: 'مسودة', class: 'bg-gray-200 text-gray-800', icon: 'fas fa-pencil-alt' },
        in_review: { text: 'قيد المراجعة', class: 'bg-blue-100 text-blue-800', icon: 'fas fa-hourglass-half' },
        approved: { text: 'معتمد', class: 'bg-green-100 text-green-800', icon: 'fas fa-check-circle' },
        sent: { text: 'مرسل', class: 'bg-indigo-100 text-indigo-800', icon: 'fas fa-paper-plane' },
        executed: { text: 'منفذ', class: 'bg-purple-100 text-purple-800', icon: 'fas fa-stamp' },
        archived: { text: 'مؤرشف', class: 'bg-gray-100 text-gray-500', icon: 'fas fa-archive' },
        rejected: { text: 'مرفوض', class: 'bg-red-100 text-red-800', icon: 'fas fa-times-circle' },
    };
    return statuses[status] || statuses['draft'];
};
</script>

<template>
    <Head :title="`عرض الوثيقة - ${document.subject}`" />
    <HrLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <span>تفاصيل الوثيقة: {{ document.serial_number }}</span>
                <div class="flex items-center space-x-2 rtl:space-x-reverse">
                    <Link v-if="canEditDraft" :href="route('documents.edit', document.id)" class="bg-yellow-500 text-white px-4 py-2 rounded-md hover:bg-yellow-600 flex items-center text-sm font-medium transition-colors no-print">
                        <i class="fas fa-edit mr-2 rtl:ml-2"></i>
                        <span>تعديل المسودة</span>
                    </Link>
                    <button @click="printDocument" class="bg-gray-700 text-white px-4 py-2 rounded-md hover:bg-gray-800 flex items-center text-sm font-medium transition-colors no-print">
                        <i class="fas fa-print mr-2 rtl:ml-2"></i>
                        <span>طباعة</span>
                    </button>
                </div>
            </div>
        </template>

        <!-- Screen View -->
        <div class="screen-view grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Actions & Details -->
            <div class="lg:col-span-1 flex flex-col gap-6">
                <!-- Action Panel -->
                <div v-if="userCurrentTask" class="bg-white p-6 rounded-lg shadow-md animate-fade-in">
                    <h3 class="font-bold text-lg mb-4 border-b pb-3 text-gray-800">الإجراء المطلوب منك</h3>
                    <form @submit.prevent="submitAction" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ملاحظاتك (اختياري)</label>
                            <textarea v-model="form.notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-200"></textarea>
                        </div>
                        <div class="flex items-center justify-end space-x-2 rtl:space-x-reverse">
                            <button @click="form.action = 'reject'" type="submit" :disabled="form.processing" class="px-4 py-2 rounded-md text-white bg-red-600 hover:bg-red-700 transition-colors">رفض</button>
                            <button @click="form.action = 'approve'" type="submit" :disabled="form.processing" class="px-4 py-2 rounded-md text-white bg-green-600 hover:bg-green-700 transition-colors">موافقة</button>
                        </div>
                    </form>
                </div>

                <!-- Details Panel -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="font-bold text-lg mb-4 border-b pb-3 text-gray-800">تفاصيل الوثيقة</h3>
                    <div class="space-y-3 text-sm">
                        <p><strong>الموضوع:</strong> <span class="text-gray-700">{{ document.subject }}</span></p>
                        <p><strong>المنشئ:</strong> <span class="text-gray-700">{{ document.creator.name }}</span></p>
                        <p><strong>القسم:</strong> <span class="text-gray-700">{{ document.department.name }}</span></p>
                        <p><strong>النوع:</strong> <span class="text-gray-700">{{ document.document_type.name }}</span></p>
                        <p><strong>الأولوية:</strong> <span class="text-gray-700">{{ document.priority }}</span></p>
                        <p><strong>الحالة الحالية:</strong> <span class="px-2 py-0.5 text-xs font-semibold rounded-full" :class="getStatusDetails(document.status).class">{{ getStatusDetails(document.status).text }}</span></p>
                    </div>
                </div>

                <!-- Attachments Panel -->
                <div v-if="document.attachments.length > 0" class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="font-bold text-lg mb-4 border-b pb-3 text-gray-800">المرفقات ({{ document.attachments.length }})</h3>
                    <ul class="space-y-2">
                        <li v-for="file in document.attachments" :key="file.id">
                            <a :href="file.url" target="_blank" class="flex items-center text-indigo-600 hover:underline text-sm p-1 rounded">
                                <i class="fas fa-paperclip mr-2 rtl:ml-2"></i> {{ file.file_name }}
                            </a>
                        </li>
                    </ul>
                </div>

                 <!-- Workflow History -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="font-bold text-lg mb-4 border-b pb-3 text-gray-800">سجل الإجراءات</h3>
                    <ul class="space-y-4">
                        <li v-for="step in document.workflow_steps" :key="step.id" class="flex items-start">
                             <div class="w-8 h-8 rounded-full flex items-center justify-center text-white shrink-0" :class="{'bg-green-500': step.action === 'approve', 'bg-red-500': step.action === 'reject', 'bg-blue-500': step.action === 'review' || step.action === 'create'}">
                                <i class="fas" :class="{'fa-check': step.action === 'approve', 'fa-times': step.action === 'reject', 'fa-user-clock': step.action === 'review', 'fa-pencil-alt': step.action === 'create'}"></i>
                            </div>
                            <div class="ml-3 rtl:mr-3">
                                <p class="text-sm font-semibold">
                                    <span v-if="step.from_department">{{ step.from_department.name }}</span>
                                    <span v-else>{{ document.creator.name }}</span>
                                    <span class="text-gray-500 font-normal"> أحال إلى </span> 
                                    <span>{{ step.to_department.name }}</span>
                                </p>
                                <p v-if="step.notes" class="text-xs text-gray-600 bg-gray-100 p-2 rounded-md mt-1">{{ step.notes }}</p>
                                <p v-if="step.completed_at" class="text-xs text-green-600 font-semibold mt-1">
                                    تم الإجراء بواسطة: {{ step.processed_by.name }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">{{ new Date(step.created_at).toLocaleString('ar-LY') }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right Column: Document Content -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-4">
                <div class="border rounded-lg p-8 document-preview prose max-w-none">
                    <div v-html="document.content"></div>
                </div>
            </div>
        </div>

        <!-- Print View (Hidden on screen) -->
        <div class="print-view">
            <header class="print-header">
                <div class="header-right">
                    <img :src="organizationDetails.logoUrl" alt="شعار المؤسسة" class="logo">
                </div>
                <div class="header-center">
                    <h1 class="org-name">{{ organizationDetails.name }}</h1>
                </div>
                <div class="header-left">
                    <!-- Intentionally left empty for alignment -->
                </div>
            </header>

            <main class="print-main">
                <div class="doc-info">
                    <div class="info-line">
                        <span><strong>الرقم الإشاري:</strong> {{ document.serial_number }}</span>
                        <span><strong>التاريخ:</strong> {{ new Date(document.created_at).toLocaleDateString('ar-LY') }}</span>
                    </div>
                    <hr class="dotted-line">
                </div>

                <h2 class="print-subject"><u>الموضوع: {{ document.subject }}</u></h2>
                <div class="print-content prose max-w-none" v-html="document.content"></div>
            </main>

            <footer class="print-footer">
                <div v-if="finalApprover" class="signature-area">
                    <p class="signature-title">الاعتماد النهائي</p>
                    <p class="signature-name">{{ finalApprover.department.name }}</p>
                    <p class="signature-role">المدير: {{ finalApprover.user.name }}</p>
                    <img v-if="document.status === 'approved' || document.status === 'sent'" :src="organizationDetails.stampUrl" alt="ختم إلكتروني" class="stamp">
                </div>
                 <div class="footer-info">
                    <p>{{ organizationDetails.address }}</p>
                </div>
            </footer>
        </div>
    </HrLayout>
</template>

<style>
/* Print-Specific Styles */
@media print {
    .screen-view, aside, header.no-print {
        display: none !important;
    }
    .print-view {
        display: block !important;
        font-family: 'Times New Roman', Times, serif;
    }
    @page {
        size: A4;
        margin: 20mm;
    }
    body {
        background-color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .print-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .header-center { text-align: center; }
    .org-name { font-size: 18pt; font-weight: bold; }
    .logo { width: 80px; height: 80px; }
    .header-left, .header-right { flex-basis: 25%; }
    .header-left { text-align: left; }
    .header-right { text-align: right; }
    .doc-info {
        margin-bottom: 30px;
        font-size: 11pt;
    }
    .info-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 5px;
    }
    .dotted-line {
        border: none;
        border-top: 2px dotted #000;
    }
    .print-main { min-height: 180mm; }
    .print-subject { font-size: 14pt; font-weight: bold; text-align: center; margin-bottom: 20px; }
    .print-content { font-size: 12pt; line-height: 1.8; }
    .print-content * { text-align: right !important; }
    .print-footer {
        position: fixed;
        bottom: 20mm;
        left: 20mm;
        right: 20mm;
    }
    .signature-area {
        position: absolute;
        bottom: 30px;
        left: 0;
        text-align: center;
    }
    .signature-title { font-weight: bold; }
    .signature-name { margin-top: 40px; font-weight: bold; }
    .signature-role { font-size: 10pt; color: #333; }
    .stamp {
        position: absolute;
        width: 100px;
        height: 100px;
        left: 50%;
        top: 20px;
        transform: translateX(-50%);
        opacity: 0.8;
    }
    .footer-info {
        text-align: center;
        font-size: 9pt;
        color: #555;
        width: 100%;
        border-top: 1px solid #ccc;
        padding-top: 5px;
        position: absolute;
        bottom: 0;
    }
}

/* Screen-Specific Styles */
.print-view {
    display: none;
}
.document-preview {
    font-family: 'Times New Roman', serif;
}
@keyframes fade-in {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
    animation: fade-in 0.5s ease-out forwards;
}
</style>

