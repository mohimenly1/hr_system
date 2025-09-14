<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    deviceUsers: {
        type: Array,
        default: () => [],
    }
});

// Form for daily sync
const attendanceForm = useForm({
    date: new Date().toISOString().split('T')[0],
});

// Form for monthly sync
const monthlyForm = useForm({
    // Format: YYYY-MM
    month: new Date().toISOString().slice(0, 7),
});

const submitSyncAttendance = () => {
    attendanceForm.post(route('hr.fingerprint.sync.attendance'));
};

const submitSyncMonthly = () => {
    monthlyForm.post(route('hr.fingerprint.sync.monthly'));
};

const confirmClearUsers = () => {
    if (confirm('تحذير! هذا الإجراء سيحذف جميع المستخدمين من ذاكرة جهاز البصمة بشكل نهائي. هل أنت متأكد من المتابعة؟')) {
        router.delete(route('hr.fingerprint.clear.users'));
    }
};
</script>

<template>
    <Head title="إدارة جهاز البصمة" />

    <HrLayout>
        <template #header>
            إدارة جهاز البصمة
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left Column: Actions -->
            <div class="space-y-8">
                <!-- Test Connection -->
                <div class="bg-white shadow-md rounded-lg p-6">
                    <div class="flex items-center space-x-4 rtl:space-x-reverse">
                        <div class="bg-gray-100 p-3 rounded-full">
                            <i class="fas fa-network-wired text-2xl text-gray-500"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">1. اختبار الاتصال</h3>
                            <p class="text-gray-500 text-sm mt-1">التأكد من أن النظام قادر على التواصل مع الجهاز.</p>
                        </div>
                    </div>
                    <div class="mt-6">
                         <Link :href="route('hr.integrations.fingerprint.test')" method="get" as="button" class="w-full bg-gray-600 text-white py-2 rounded-md hover:bg-gray-700 font-semibold">
                            بدء اختبار الاتصال
                        </Link>
                    </div>
                </div>

                <!-- Sync Daily Attendance -->
                <div class="bg-white shadow-md rounded-lg p-6">
                    <div class="flex items-center space-x-4 rtl:space-x-reverse">
                        <div class="bg-indigo-100 p-3 rounded-full">
                            <i class="fas fa-calendar-day text-2xl text-indigo-500"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">2. سحب بيانات يوم محدد</h3>
                            <p class="text-gray-500 text-sm mt-1">سحب سجلات الحضور من الجهاز ليوم واحد.</p>
                        </div>
                    </div>
                    <form @submit.prevent="submitSyncAttendance" class="mt-6">
                        <div>
                            <label for="date" class="block mb-2 text-sm font-medium text-gray-900">اختر التاريخ</label>
                            <input type="date" v-model="attendanceForm.date" id="date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                        </div>
                        <div class="mt-4">
                             <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-md hover:bg-indigo-700 font-semibold flex items-center justify-center" :disabled="attendanceForm.processing">
                                <svg v-if="attendanceForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span>سحب سجلات اليوم</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- NEW: Sync Monthly Attendance -->
                <div class="bg-white shadow-md rounded-lg p-6">
                    <div class="flex items-center space-x-4 rtl:space-x-reverse">
                        <div class="bg-teal-100 p-3 rounded-full">
                            <i class="fas fa-calendar-alt text-2xl text-teal-500"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">3. سحب بيانات شهر كامل</h3>
                            <p class="text-gray-500 text-sm mt-1">سحب جميع سجلات الحضور لشهر محدد.</p>
                        </div>
                    </div>
                    <form @submit.prevent="submitSyncMonthly" class="mt-6">
                        <div>
                            <label for="month" class="block mb-2 text-sm font-medium text-gray-900">اختر الشهر</label>
                            <input type="month" v-model="monthlyForm.month" id="month" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                        </div>
                        <div class="mt-4">
                             <button type="submit" class="w-full bg-teal-600 text-white py-2 rounded-md hover:bg-teal-700 font-semibold flex items-center justify-center" :disabled="monthlyForm.processing">
                                <svg v-if="monthlyForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span>سحب سجلات الشهر</span>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Sync Users to Device -->
                 <div class="bg-white shadow-md rounded-lg p-6">
                    <div class="flex items-center space-x-4 rtl:space-x-reverse">
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-upload text-2xl text-blue-500"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">4. مزامنة المستخدمين إلى الجهاز</h3>
                            <p class="text-gray-500 text-sm mt-1">إضافة المستخدمين من النظام إلى جهاز البصمة.</p>
                        </div>
                    </div>
                    <div class="mt-6">
                         <Link :href="route('hr.integrations.fingerprint.sync.users')" method="post" as="button" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 font-semibold">
                            بدء مزامنة المستخدمين
                        </Link>
                    </div>
                </div>
                
                 <!-- Clear Device -->
                 <div class="bg-white shadow-md rounded-lg p-6 border-l-4 border-red-500">
                    <div class="flex items-center space-x-4 rtl:space-x-reverse">
                        <div class="bg-red-100 p-3 rounded-full">
                            <i class="fas fa-trash-alt text-2xl text-red-500"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">إجراءات متقدمة</h3>
                            <p class="text-gray-500 text-sm mt-1">مسح جميع المستخدمين من ذاكرة الجهاز.</p>
                        </div>
                    </div>
                    <div class="mt-6">
                         <button @click="confirmClearUsers" class="w-full bg-red-600 text-white py-2 rounded-md hover:bg-red-700 font-semibold">
                            مسح ذاكرة المستخدمين
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column: Device Users List -->
            <div class="bg-white shadow-md rounded-lg">
                <div class="p-6 border-b flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-800">المستخدمون المسجلون في الجهاز</h3>
                     <Link :href="route('hr.integrations.fingerprint.device.users')" method="get" as="button" class="text-sm bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 font-semibold">
                        تحديث القائمة
                    </Link>
                </div>
                <div class="max-h-[50rem] overflow-y-auto">
                    <div v-if="!deviceUsers || deviceUsers.length === 0" class="text-center p-8 text-gray-500">
                        <p>لم يتم عرض أي مستخدمين. اضغط على "تحديث القائمة".</p>
                    </div>
                    <table v-else class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-right py-2 px-4 font-semibold text-sm">ID</th>
                                <th class="text-right py-2 px-4 font-semibold text-sm">الاسم</th>
                                <th class="text-right py-2 px-4 font-semibold text-sm">الدور</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <tr v-for="user in deviceUsers" :key="user.uid" class="border-t">
                                <td class="py-2 px-4 font-mono">{{ user.uid }}</td>
                                <td class="py-2 px-4">{{ user.name }}</td>
                                <td class="py-2 px-4 text-xs">{{ user.role }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </HrLayout>
</template>
