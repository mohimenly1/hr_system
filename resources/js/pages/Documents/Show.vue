<script setup>
import HrLayout from '@/layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    document: Object,
    userCurrentTask: Object,
    organizationDetails: Object, // Prop جديد لبيانات المؤسسة
});

const form = useForm({
    action: 'approve',
    notes: '',
    signature: null, // سيتم التعامل معه لاحقاً
});

const submitAction = () => {
    form.post(route('documents.workflow.action', props.document.id), {
        onSuccess: () => {
            // سيتم التعامل مع إعادة التوجيه بواسطة المتحكم
        },
    });
};

// --- دالة جديدة لتشغيل الطباعة ---
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
                <!-- زر الطباعة -->
                <button @click="printDocument" class="bg-gray-700 text-white px-4 py-2 rounded-md hover:bg-gray-800 flex items-center text-sm font-medium transition-colors no-print">
                    <i class="fas fa-print mr-2 rtl:ml-2"></i>
                    <span>طباعة</span>
                </button>
            </div>
        </template>

        <!-- ### واجهة العرض على الشاشة ### -->
        <div class="screen-view grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- العمود الأيسر: الإجراءات والتفاصيل -->
            <div class="lg:col-span-1 flex flex-col gap-6">
                <!-- لوحة الإجراءات -->
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

                <!-- لوحة التفاصيل -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="font-bold text-lg mb-4 border-b pb-3 text-gray-800">تفاصيل الوثيقة</h3>
                    <div class="space-y-3 text-sm">
                        <p><strong>الموضوع:</strong> <span class="text-gray-700">{{ document.subject }}</span></p>
                        <p><strong>المنشئ:</strong> <span class="text-gray-700">{{ document.creator.name }}</span></p>
                        <p><strong>القسم:</strong> <span class="text-gray-700">{{ document.department.name }}</span></p>
                        <p><strong>النوع:</strong> <span class="text-gray-700">{{ document.document_type.name }}</span></p>
                        <p><strong>الأولوية:</strong> <span class="text-gray-700">{{ document.priority.value }}</span></p>
                        <p><strong>الحالة الحالية:</strong> <span class="px-2 py-0.5 text-xs font-semibold rounded-full" :class="getStatusDetails(document.status.value).class">{{ getStatusDetails(document.status.value).text }}</span></p>
                    </div>
                </div>

                <!-- لوحة المرفقات -->
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

                 <!-- سجل الإجراءات -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="font-bold text-lg mb-4 border-b pb-3 text-gray-800">سجل الإجراءات</h3>
                    <ul class="space-y-4">
                        <li v-for="step in document.workflow_steps" :key="step.id" class="flex items-start">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white shrink-0" :class="{'bg-green-500': step.action === 'approve', 'bg-red-500': step.action === 'reject', 'bg-blue-500': step.action === 'review' || step.action === 'create'}">
                                <i class="fas" :class="{'fa-check': step.action === 'approve', 'fa-times': step.action === 'reject', 'fa-user-clock': step.action === 'review', 'fa-pencil-alt': step.action === 'create'}"></i>
                            </div>
                            <div class="ml-3 rtl:mr-3">
                                <p class="text-sm font-semibold">{{ step.from_user?.name }} <span class="text-gray-500 font-normal">{{ step.action === 'create' ? 'أنشأ الوثيقة وأحالها إلى' : 'أحال إلى' }}</span> {{ step.to_user?.name }}</p>
                                <p v-if="step.notes" class="text-xs text-gray-600 bg-gray-100 p-2 rounded-md mt-1">{{ step.notes }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ new Date(step.created_at).toLocaleString('ar-LY') }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- العمود الأيمن: محتوى الوثيقة -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-4">
                <div class="border rounded-lg p-8 document-preview prose max-w-none">
                    <div v-html="document.content"></div>
                </div>
            </div>
        </div>

        <!-- ### واجهة الطباعة (مخفية في العرض العادي) ### -->
        <div class="print-view">
            <header class="print-header">
                <div class="header-right">
                    <img :src="organizationDetails.logoUrl" alt="شعار المؤسسة" class="logo">
                </div>
                <div class="header-center">
                    <h1 class="org-name">{{ organizationDetails.name }}</h1>
                    <p class="department-name">{{ document.department.name }}</p>
                </div>
                <div class="header-left">
                    <p><strong>الرقم:</strong> {{ document.serial_number }}</p>
                    <p><strong>التاريخ:</strong> {{ new Date(document.created_at).toLocaleDateString('ar-LY') }}</p>
                </div>
            </header>

            <main class="print-main">
                <h2 class="print-subject"><u>الموضوع: {{ document.subject }}</u></h2>
                <div class="print-content prose max-w-none" v-html="document.content"></div>
            </main>

            <footer class="print-footer">
                <div class="signature-area">
                    <p class="signature-title">الاعتماد النهائي</p>
                    <p class="signature-name">{{ document.creator.name }}</p>
                    <p class="signature-role">{{ document.creator.employee ? document.creator.employee.job_title : 'مسؤول' }}</p>
                    <img v-if="document.status.value === 'approved' || document.status.value === 'sent'" :src="organizationDetails.stampUrl" alt="ختم إلكتروني" class="stamp">
                </div>
                 <div class="footer-info">
                    <p>{{ organizationDetails.address }}</p>
                    <p>هاتف: 1234567-021 | بريد إلكتروني: info@example.com</p>
                </div>
            </footer>
        </div>
    </HrLayout>
</template>

<style>
/* --- أنماط خاصة بالطباعة --- */
@media print {
    /* إخفاء كل العناصر غير المرغوب فيها في الطباعة */
    .screen-view, aside, header.no-print {
        display: none !important;
    }

    /* إظهار وتنسيق الجزء المخصص للطباعة */
    .print-view {
        display: block !important;
        font-family: 'Times New Roman', Times, serif; /* استخدام خط رسمي للطباعة */
    }

    @page {
        size: A4;
        margin: 20mm;
    }

    body {
        background-color: white !important;
        -webkit-print-color-adjust: exact; /* لضمان طباعة الألوان والخلفيات */
        print-color-adjust: exact;
    }
    
    .print-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
        margin-bottom: 40px;
    }
    .header-center { text-align: center; }
    .org-name { font-size: 18pt; font-weight: bold; }
    .department-name { font-size: 14pt; }
    .logo { width: 80px; height: 80px; }
    .header-left, .header-right { flex-basis: 25%; }
    .header-left { text-align: left; font-size: 10pt; direction: ltr; }
    .header-right { text-align: right; }

    .print-main { min-height: 180mm; } /* ضمان أن المحتوى يملأ الصفحة */
    .print-subject { font-size: 14pt; font-weight: bold; text-align: center; margin-bottom: 30px; }
    .print-content { font-size: 12pt; line-height: 1.8; }
    /* لضمان محاذاة النص داخل المحتوى للغة العربية */
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
    .signature-name { margin-top: 40px; }
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

/* إخفاء الجزء المخصص للطباعة في العرض العادي على الشاشة */
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

