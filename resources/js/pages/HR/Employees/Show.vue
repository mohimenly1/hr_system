<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    employee: Object,
});

const showAddAttachmentModal = ref(false);

const attachmentForm = useForm({
    attachment_name: '',
    attachment_file: null,
});

const getStatusClass = (status) => {
    const classes = {
        active: 'bg-green-100 text-green-800',
        on_leave: 'bg-yellow-100 text-yellow-800',
        terminated: 'bg-red-100 text-red-800',
        pending: 'bg-blue-100 text-blue-800',
        expired: 'bg-gray-100 text-gray-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('ar-EG');
};

const submitAttachment = () => {
    attachmentForm.post(route('hr.employees.attachments.store', props.employee.id), {
        onSuccess: () => {
            showAddAttachmentModal.value = false;
            attachmentForm.reset();
        },
    });
};

</script>

<template>
    <Head :title="`ملف الموظف - ${employee.user.name}`" />

    <HrLayout>
        <template #header>
            الملف الشخصي للموظف
        </template>

        <div class="space-y-6">
            <!-- Employee Header -->
            <div class="bg-white shadow-md rounded-lg p-6 flex items-center space-x-6 rtl:space-x-reverse">
                <div class="flex-shrink-0">
                    <!-- Placeholder for profile picture -->
                    <div class="w-24 h-24 bg-indigo-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-4xl text-indigo-400"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">{{ employee.user.name }}</h2>
                    <p class="text-lg text-gray-600">{{ employee.job_title }}</p>
                    <p class="text-sm text-gray-500">{{ employee.department.name }}</p>
                    <span class="mt-2 inline-block px-3 py-1 text-sm font-semibold leading-5 rounded-full" :class="getStatusClass(employee.employment_status)">
                        {{ employee.employment_status === 'active' ? 'نشط' : (employee.employment_status === 'on_leave' ? 'في إجازة' : 'منتهية خدمته') }}
                    </span>
                </div>
            </div>

            <!-- Tabs/Sections -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Personal Info -->
                    <div class="bg-white shadow-md rounded-lg p-6">
                        <h3 class="text-lg font-bold border-b pb-2 mb-4 text-gray-800">المعلومات الشخصية</h3>
                        <div class="space-y-2 text-sm text-gray-700">
                            <p><strong class="font-semibold text-gray-900">البريد الإلكتروني:</strong> {{ employee.user.email }}</p>
                            <p><strong class="font-semibold text-gray-900">رقم الهاتف:</strong> {{ employee.phone_number || 'غير متوفر' }}</p>
                            <p><strong class="font-semibold text-gray-900">تاريخ الميلاد:</strong> {{ formatDate(employee.date_of_birth) }}</p>
                            <p><strong class="font-semibold text-gray-900">الجنس:</strong> {{ employee.gender === 'male' ? 'ذكر' : 'أنثى' }}</p>
                            <p><strong class="font-semibold text-gray-900">العنوان:</strong> {{ employee.address || 'غير متوفر' }}</p>
                        </div>
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
                            <p class="text-sm text-gray-500 mt-2">لا يوجد مرفقات. قم بإضافة أول مرفق.</p>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Contract Details -->
                    <div class="bg-white shadow-md rounded-lg p-6">
                        <h3 class="text-lg font-bold border-b pb-2 mb-4 text-gray-800">العقود</h3>
                        <div v-if="employee.contracts.length > 0" class="overflow-x-auto">
                           <table class="min-w-full text-sm">
                               <thead class="bg-gray-50">
                                   <tr>
                                       <th class="text-right p-2 font-semibold text-gray-600">نوع العقد</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">تاريخ البدء</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">الراتب الأساسي</th>
                                       <th class="text-right p-2 font-semibold text-gray-600">الحالة</th>
                                   </tr>
                               </thead>
                               <tbody class="text-gray-700">
                                   <tr v-for="contract in employee.contracts" :key="contract.id" class="border-b">
                                       <td class="p-2">{{ contract.contract_type }}</td>
                                       <td class="p-2">{{ formatDate(contract.start_date) }}</td>
                                       <td class="p-2 font-mono">{{ contract.basic_salary }}</td>
                                       <td class="p-2">
                                           <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(contract.status)">
                                               {{ contract.status }}
                                           </span>
                                       </td>
                                   </tr>
                               </tbody>
                           </table>
                        </div>
                         <p v-else class="text-sm text-gray-500">لا يوجد عقود مسجلة لهذا الموظف.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Attachment Modal with Glassy Background -->
        <div v-if="showAddAttachmentModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-xl font-bold text-gray-800">إضافة مرفق جديد</h3>
                    <button @click="showAddAttachmentModal = false" class="text-gray-500 hover:text-gray-800">&times;</button>
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