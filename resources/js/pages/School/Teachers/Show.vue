<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    teacher: Object,
});

const showAddAttachmentModal = ref(false);
const attachmentForm = useForm({
    attachment_name: '',
    attachment_file: null,
});

const submitAttachment = () => {
    attachmentForm.post(route('school.teachers.attachments.store', props.teacher.id), {
        onSuccess: () => {
            showAddAttachmentModal.value = false;
            attachmentForm.reset();
        },
        preserveScroll: true,
    });
};

const assignmentsByGrade = computed(() => {
    const grouped = {};
    if (props.teacher.assignments) {
        props.teacher.assignments.forEach(assignment => {
            const gradeName = assignment.section.grade.name;
            if (!grouped[gradeName]) {
                grouped[gradeName] = [];
            }
            grouped[gradeName].push(assignment);
        });
    }
    return grouped;
});

const latestContract = computed(() => {
    return props.teacher.contracts && props.teacher.contracts.length > 0 ? props.teacher.contracts[0] : null;
});
</script>

<template>
    <Head :title="`ملف المعلم - ${teacher.user.name}`" />

    <HrLayout>
        <template #header>
            ملف المعلم / {{ teacher.user.name }}
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Personal & Employment Info -->
            <div class="bg-white shadow-lg rounded-lg p-6">
                <div class="flex items-center space-x-4 rtl:space-x-reverse mb-6 border-b pb-4">
                    <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-500 text-3xl font-bold">
                        {{ teacher.user.name.charAt(0) }}
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">{{ teacher.user.name }}</h2>
                        <p class="text-gray-600">{{ teacher.specialization }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    <div>
                        <h3 class="font-bold text-gray-700 mb-2 border-b pb-1">المعلومات الشخصية</h3>
                        <div class="space-y-2">
                            <p><span class="font-semibold text-gray-600">البريد الإلكتروني:</span> <span class="text-gray-800">{{ teacher.user.email }}</span></p>
                            <p><span class="font-semibold text-gray-600">رقم الهاتف:</span> <span class="text-gray-800">{{ teacher.phone_number || '-' }}</span></p>
                            <p><span class="font-semibold text-gray-600">تاريخ الميلاد:</span> <span class="text-gray-800">{{ teacher.date_of_birth || '-' }}</span></p>
                            <p><span class="font-semibold text-gray-600">الجنس:</span> <span class="text-gray-800">{{ teacher.gender === 'male' ? 'ذكر' : 'أنثى' }}</span></p>
                             <p><span class="font-semibold text-gray-600">الحالة الاجتماعية:</span> <span class="text-gray-800">{{ teacher.marital_status || '-' }}</span></p>
                        </div>
                    </div>
                     <div>
                        <h3 class="font-bold text-gray-700 mb-2 border-b pb-1">المعلومات الوظيفية</h3>
                        <div class="space-y-2">
                           <p><span class="font-semibold text-gray-600">القسم:</span> <span class="text-gray-800">{{ teacher.department.name }}</span></p>
                           <p><span class="font-semibold text-gray-600">تاريخ التعيين:</span> <span class="text-gray-800">{{ teacher.hire_date }}</span></p>
                           <p><span class="font-semibold text-gray-600">حالة التوظيف:</span> <span class="text-gray-800">{{ teacher.employment_status === 'active' ? 'نشط' : 'غير نشط' }}</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attachments -->
            <div class="bg-white shadow-lg rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-800">المرفقات</h3>
                    <button @click="showAddAttachmentModal = true" class="bg-indigo-500 text-white px-3 py-1 rounded-md text-sm hover:bg-indigo-600">
                        <i class="fas fa-plus mr-1"></i> إضافة مرفق
                    </button>
                </div>
                <div v-if="teacher.attachments && teacher.attachments.length > 0">
                    <ul class="divide-y divide-gray-200">
                        <li v-for="attachment in teacher.attachments" :key="attachment.id" class="py-3 flex justify-between items-center">
                            <div>
                                <p class="font-medium text-gray-800">{{ attachment.file_name }}</p>
                                <p class="text-xs text-gray-500">{{ attachment.file_type }}</p>
                            </div>
                            <a :href="attachment.url" target="_blank" class="text-indigo-600 hover:underline text-sm font-medium">تحميل</a>
                        </li>
                    </ul>
                </div>
                <p v-else class="text-gray-500 text-center py-4">لا توجد مرفقات لهذا المعلم.</p>
            </div>

            <!-- Assignments -->
            <div class="bg-white shadow-lg rounded-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">المقررات المسندة</h3>
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
                <p v-else class="text-gray-500">لم يتم إسناد أي مقررات لهذا المعلم بعد.</p>
            </div>

             <!-- Contract -->
            <div v-if="latestContract" class="bg-white shadow-lg rounded-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">تفاصيل العقد الأخير</h3>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                     <p><span class="font-semibold text-gray-600">نوع العقد:</span> <span class="text-gray-800">{{ latestContract.contract_type }}</span></p>
                     <p><span class="font-semibold text-gray-600">تاريخ البدء:</span> <span class="text-gray-800">{{ latestContract.start_date }}</span></p>
                     <p><span class="font-semibold text-gray-600">نوع الراتب:</span> <span class="text-gray-800">{{ latestContract.salary_type === 'monthly' ? 'شهري' : 'بالساعة' }}</span></p>
                     <p v-if="latestContract.salary_type === 'monthly'"><span class="font-semibold text-gray-600">الراتب الشهري:</span> <span class="text-gray-800 font-mono">{{ latestContract.salary_amount }}</span></p>
                     <p v-if="latestContract.salary_type === 'hourly'"><span class="font-semibold text-gray-600">الأجر بالساعة:</span> <span class="text-gray-800 font-mono">{{ latestContract.hourly_rate }}</span></p>
                 </div>
            </div>

        </div>

        <!-- Add Attachment Modal -->
        <div v-if="showAddAttachmentModal" @click.self="showAddAttachmentModal = false" class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm z-50 flex justify-center items-center p-4 transition-opacity duration-300 ease-in-out">
             <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md transform transition-all duration-300 ease-in-out scale-95" :class="{'scale-100': showAddAttachmentModal}">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-xl font-bold text-gray-800">إضافة مرفق جديد</h3>
                    <button @click="showAddAttachmentModal = false" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
                </div>
                <form @submit.prevent="submitAttachment" class="mt-4 space-y-4">
                    <div>
                        <label for="attachment_name" class="block mb-2 text-sm font-medium text-gray-900">اسم المرفق (مثال: السيرة الذاتية)</label>
                        <input type="text" v-model="attachmentForm.attachment_name" id="attachment_name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                        <div v-if="attachmentForm.errors.attachment_name" class="text-sm text-red-600 mt-1">{{ attachmentForm.errors.attachment_name }}</div>
                    </div>
                    <div>
                        <label for="attachment_file" class="block mb-2 text-sm font-medium text-gray-900">اختر الملف</label>
                        <input type="file" @input="attachmentForm.attachment_file = $event.target.files[0]" id="attachment_file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required/>
                        <div v-if="attachmentForm.errors.attachment_file" class="text-sm text-red-600 mt-1">{{ attachmentForm.errors.attachment_file }}</div>
                    </div>
                    <div class="flex justify-end pt-4 border-t space-x-2 rtl:space-x-reverse">
                        <button type="button" @click="showAddAttachmentModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="attachmentForm.processing">حفظ المرفق</button>
                    </div>
                </form>
            </div>
        </div>

    </HrLayout>
</template>
