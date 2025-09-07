<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    leaves: Object,
});

const isProcessing = ref(false);
const showConfirmModal = ref(false);
const leaveToUpdate = ref(null);
const newStatusToUpdate = ref('');
const actionText = ref('');

const openConfirmationModal = (leave, newStatus) => {
    leaveToUpdate.value = leave;
    newStatusToUpdate.value = newStatus;
    actionText.value = newStatus === 'approved' ? 'الموافقة على' : 'رفض';
    showConfirmModal.value = true;
};

const confirmUpdateStatus = () => {
    isProcessing.value = true;
    router.put(route('hr.leaves.update', leaveToUpdate.value.id), {
        status: newStatusToUpdate.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showConfirmModal.value = false;
        },
        onFinish: () => {
            isProcessing.value = false;
        }
    });
};


const getStatusClass = (status) => {
    switch (status) {
        case 'approved': return 'bg-green-100 text-green-800';
        case 'rejected': return 'bg-red-100 text-red-800';
        case 'pending': default: return 'bg-yellow-100 text-yellow-800';
    }
};

const getStatusText = (status) => {
     switch (status) {
        case 'approved': return 'مقبول';
        case 'rejected': return 'مرفوض';
        case 'pending': default: return 'قيد المراجعة';
    }
};
</script>

<template>
    <Head title="إدارة طلبات الإجازة" />

    <HrLayout>
        <template #header>
            إدارة طلبات الإجازة
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">قائمة الطلبات</h2>
                <Link :href="route('hr.leaves.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-plus mr-2"></i> إضافة طلب إجازة
                </Link>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">اسم الموظف</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">نوع الإجازة</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">من تاريخ</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">إلى تاريخ</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الحالة</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="leave in leaves.data" :key="leave.id" class="border-b">
                            <td class="py-3 px-4">{{ leave.employee.user.name }}</td>
                            <td class="py-3 px-4">{{ leave.leave_type }}</td>
                            <td class="py-3 px-4">{{ new Date(leave.start_date).toLocaleDateString('ar-EG') }}</td>
                            <td class="py-3 px-4">{{ new Date(leave.end_date).toLocaleDateString('ar-EG') }}</td>
                            <td class="py-3 px-4">
                               <span :class="getStatusClass(leave.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                   {{ getStatusText(leave.status) }}
                               </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div v-if="leave.status === 'pending'" class="flex justify-center items-center space-x-2 rtl:space-x-reverse">
                                    <button @click="openConfirmationModal(leave, 'approved')" :disabled="isProcessing" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 text-sm disabled:opacity-50">
                                        موافقة
                                    </button>
                                    <button @click="openConfirmationModal(leave, 'rejected')" :disabled="isProcessing" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 text-sm disabled:opacity-50">
                                        رفض
                                    </button>
                                </div>
                                <span v-else class="text-gray-400 text-sm">تم اتخاذ إجراء</span>
                            </td>
                        </tr>
                        <tr v-if="leaves.data.length === 0">
                            <td colspan="6" class="text-center py-4">لا يوجد طلبات إجازة لعرضها.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
             <!-- Pagination -->
        </div>

        <!-- Confirmation Modal with Glassy Background and Transition -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="transform opacity-0"
            enter-to-class="transform opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="transform opacity-100"
            leave-to-class="transform opacity-0"
        >
            <div v-if="showConfirmModal" @click.self="showConfirmModal = false" class="fixed inset-0 bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center p-4">
                <Transition
                    enter-active-class="transition ease-out duration-300"
                    enter-from-class="transform opacity-0 scale-95"
                    enter-to-class="transform opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-200"
                    leave-from-class="transform opacity-100 scale-100"
                    leave-to-class="transform opacity-0 scale-95"
                >
                    <div v-if="showConfirmModal" class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                        <h3 class="text-xl font-bold text-gray-800">تأكيد الإجراء</h3>
                        <p class="mt-2 text-gray-600">هل أنت متأكد من <span class="font-bold">{{ actionText }}</span> طلب الإجازة للموظف <span class="font-bold">{{ leaveToUpdate.employee.user.name }}</span>؟</p>
                        <div class="flex justify-end mt-6 space-x-2 rtl:space-x-reverse">
                            <button @click="showConfirmModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">
                                إلغاء
                            </button>
                            <button @click="confirmUpdateStatus" :disabled="isProcessing" class="px-4 py-2 rounded-md text-white"
                                    :class="newStatusToUpdate === 'approved' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'">
                                نعم، تأكيد
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>

    </HrLayout>
</template>

