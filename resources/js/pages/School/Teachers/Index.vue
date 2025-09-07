<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    teachers: Object,
});

// --- STATE & LOGIC FOR FINGERPRINT MODAL ---
const showFingerprintModal = ref(false);
const selectedTeacher = ref(null);
const fingerprintForm = useForm({
    fingerprint_id: '',
});

const openFingerprintModal = (teacher) => {
    selectedTeacher.value = teacher;
    fingerprintForm.fingerprint_id = teacher.fingerprint_id || '';
    showFingerprintModal.value = true;
};

const submitFingerprintId = () => {
    fingerprintForm.put(route('school.teachers.fingerprint.update', selectedTeacher.value.id), {
        onSuccess: () => {
            showFingerprintModal.value = false;
            fingerprintForm.reset();
        },
        preserveScroll: true,
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
    <Head title="إدارة المعلمين" />
    <HrLayout>
        <template #header>
            إدارة المعلمين
        </template>
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">قائمة المعلمين</h2>
                <Link :href="route('school.teachers.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-plus mr-2"></i> إضافة معلم جديد
                </Link>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                     <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الاسم</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">التخصص</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">رقم البصمة</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الحالة</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="teacher in teachers.data" :key="teacher.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <div class="font-medium">{{ teacher.user.name }}</div>
                                <div class="text-xs text-gray-500">{{ teacher.user.email }}</div>
                            </td>
                            <td class="py-3 px-4">{{ teacher.specialization }}</td>
                            <td class="py-3 px-4 text-center">
                                <span v-if="teacher.fingerprint_id" class="font-mono bg-gray-100 px-2 py-1 rounded text-gray-800">
                                    {{ teacher.fingerprint_id }}
                                </span>
                                <button v-else @click="openFingerprintModal(teacher)" class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded hover:bg-gray-300">
                                    إضافة
                                </button>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(teacher.employment_status)">
                                    {{ teacher.employment_status === 'active' ? 'نشط' : (teacher.employment_status === 'on_leave' ? 'في إجازة' : 'منتهية خدمته') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center space-x-4 rtl:space-x-reverse">
                                    <Link :href="route('school.teachers.attendance.show', teacher.id)" class="text-gray-500 hover:text-green-600" title="عرض سجل الحضور">
                                        <i class="fas fa-calendar-alt"></i>
                                    </Link>
                                    <Link :href="route('school.teachers.attendance.sync', teacher.id)" method="post" as="button" class="text-gray-500 hover:text-blue-600" title="مزامنة حضور اليوم" preserve-scroll>
                                        <i class="fas fa-sync-alt"></i>
                                    </Link>
                                    <button @click="openFingerprintModal(teacher)" class="text-gray-500 hover:text-indigo-600" title="تعديل رقم البصمة">
                                        <i class="fas fa-fingerprint"></i>
                                    </button>
                                    <Link :href="route('school.teachers.show', teacher.id)" class="text-gray-500 hover:text-yellow-600" title="عرض التفاصيل">
                                        <i class="fas fa-eye"></i>
                                    </Link>
                                    <Link :href="route('school.teachers.edit', teacher.id)" class="text-gray-500 hover:text-purple-600" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="teachers.data.length === 0">
                            <td colspan="5" class="text-center py-4">لا يوجد معلمين لعرضهم.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Fingerprint ID Modal -->
        <div v-if="showFingerprintModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-xl font-bold text-gray-800">تحديث رقم البصمة</h3>
                <p v-if="selectedTeacher" class="mt-2 text-gray-600">للمعلم: <span class="font-bold">{{ selectedTeacher.user.name }}</span></p>
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
