<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    employees: Object,
});

// --- Modal for Fingerprint ID ---
const showFingerprintModal = ref(false);
const selectedEmployee = ref(null);
const fingerprintForm = useForm({
    fingerprint_id: '',
});

const openFingerprintModal = (employee) => {
    selectedEmployee.value = employee;
    fingerprintForm.fingerprint_id = employee.fingerprint_id || '';
    showFingerprintModal.value = true;
};

const submitFingerprintId = () => {
    fingerprintForm.put(route('hr.employees.fingerprint.update', selectedEmployee.value.id), {
        onSuccess: () => {
            showFingerprintModal.value = false;
            fingerprintForm.reset();
        },
        preserveScroll: true,
    });
};

// --- NEW: Logic for syncing single employee attendance ---
const syncingEmployeeId = ref(null);

const syncEmployeeAttendance = (employeeId) => {
    // Prevent multiple clicks
    if (syncingEmployeeId.value) return;
    
    syncingEmployeeId.value = employeeId;
    router.post(route('hr.employees.attendance.sync', employeeId), {}, {
        preserveScroll: true,
        onFinish: () => {
            syncingEmployeeId.value = null; // Reset on completion or error
        }
    });
};


const getStatusClass = (status) => {
    switch (status) {
        case 'active': return 'bg-green-100 text-green-800';
        case 'on_leave': return 'bg-yellow-100 text-yellow-800';
        case 'terminated': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

</script>

<template>
    <Head title="قائمة الموظفين" />

    <HrLayout>
        <template #header>
            إدارة الموظفين
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">قائمة الموظفين</h2>
                <Link :href="route('hr.employees.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-plus mr-2"></i> إضافة موظف جديد
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الاسم</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">القسم</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">المسمى الوظيفي</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">رقم البصمة</th>
                             <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الحالة</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="employee in employees.data" :key="employee.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <div class="font-medium text-gray-900">{{ employee.user.name }}</div>
                                <div class="text-xs text-gray-500">{{ employee.user.email }}</div>
                            </td>
                            <td class="py-3 px-4">{{ employee.department.name }}</td>
                            <td class="py-3 px-4">{{ employee.job_title }}</td>
                            <td class="py-3 px-4 text-center">
                                <span v-if="employee.fingerprint_id" class="font-mono bg-gray-100 px-2 py-1 rounded text-gray-800">
                                    {{ employee.fingerprint_id }}
                                </span>
                                <button v-else @click="openFingerprintModal(employee)" class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded hover:bg-gray-300">
                                    إضافة
                                </button>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(employee.employment_status)">
                                    {{ employee.employment_status === 'active' ? 'نشط' : (employee.employment_status === 'on_leave' ? 'في إجازة' : 'منتهية خدمته') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center space-x-4 rtl:space-x-reverse">
                                    <!-- NEW: Attendance Actions -->
                                    <Link :href="route('hr.employees.attendance.show', employee.id)" class="text-gray-500 hover:text-green-600" title="عرض سجل الحضور">
                                        <i class="fas fa-calendar-alt text-lg"></i>
                                    </Link>
                                    <button @click="syncEmployeeAttendance(employee.id)" 
                                            class="text-gray-500 hover:text-blue-600" 
                                            :disabled="syncingEmployeeId === employee.id" 
                                            title="مزامنة حضور اليوم">
                                        <i class="fas text-lg" :class="{'fa-sync-alt': syncingEmployeeId !== employee.id, 'fa-spinner fa-spin': syncingEmployeeId === employee.id}"></i>
                                    </button>
                                     <button @click="openFingerprintModal(employee)" class="text-gray-500 hover:text-purple-600" title="تعديل رقم البصمة">
                                        <i class="fas fa-fingerprint text-lg"></i>
                                    </button>
                                    
                                    <!-- Existing Links -->
                                    <span class="border-r border-gray-300 h-6"></span>
                                    <Link :href="route('hr.employees.show', employee.id)" class="text-indigo-600 hover:text-indigo-900 font-medium">عرض</Link>
                                    <Link :href="route('hr.employees.edit', employee.id)" class="text-blue-600 hover:text-blue-900 font-medium">تعديل</Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="employees.data.length === 0">
                            <td colspan="6" class="text-center py-4">لا يوجد موظفين لعرضهم.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
             <!-- Pagination will be added later -->
        </div>

        <!-- Fingerprint ID Modal -->
        <div v-if="showFingerprintModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-xl font-bold text-gray-800">تحديث رقم البصمة</h3>
                <p v-if="selectedEmployee" class="mt-2 text-gray-600">للموظف: <span class="font-bold">{{ selectedEmployee.user.name }}</span></p>
                <form @submit.prevent="submitFingerprintId" class="mt-4 space-y-4">
                    <div>
                        <label for="fingerprint_id" class="block mb-2 text-sm font-medium text-gray-900">رقم البصمة (UID)</label>
                        <input type="number" v-model="fingerprintForm.fingerprint_id" id="fingerprint_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                        <div v-if="fingerprintForm.errors.fingerprint_id" class="text-sm text-red-600 mt-1">{{ fingerprintForm.errors.fingerprint_id }}</div>
                    </div>
                    <div class="flex justify-end pt-4 border-t space-x-2 rtl:space-x-reverse">
                        <button type="button" @click="showFingerprintModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="fingerprintForm.processing">حفظ</button>
                    </div>
                </form>
            </div>
        </div>

    </HrLayout>
</template>
