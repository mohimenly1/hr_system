<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import HrLayout from '../../../../layouts/HrLayout.vue';

const props = defineProps({
    grades: Array,
    activeYear: Object,
    allSubjects: Array,
});

// Form for adding a new grade
const addGradeForm = useForm({
    name: '',
    description: '',
});

// Modal state
const showAssignModal = ref(false);
const selectedGradeForModal = ref(null);

// Form for assigning subjects
const assignSubjectsForm = useForm({
    grade_id: null,
    subject_ids: [],
});

const openAssignModal = (grade) => {
    selectedGradeForModal.value = grade;
    assignSubjectsForm.grade_id = grade.id;
    // Set the initial selected subjects based on the grade's current subjects
    assignSubjectsForm.subject_ids = grade.subjects.map(subject => subject.id);
    showAssignModal.value = true;
};

const closeModal = () => {
    showAssignModal.value = false;
    assignSubjectsForm.reset();
    // Re-fetch the grades list to show updated subject counts
    router.reload({ only: ['grades'] });
};

const submitAddGradeForm = () => {
    addGradeForm.post(route('school.grades.store'), {
        onSuccess: () => addGradeForm.reset(),
        preserveScroll: true,
    });
};

const submitAssignSubjectsForm = () => {
    assignSubjectsForm.post(route('school.grades.assign.subjects'), {
        onSuccess: () => closeModal(),
        preserveScroll: true,
    });
};

const deleteGrade = (grade) => {
    if (confirm(`هل أنت متأكد من حذف المرحلة "${grade.name}"؟`)) {
        router.delete(route('school.grades.destroy', grade.id), {
            preserveScroll: true,
        });
    }
};

</script>

<template>
    <Head title="إدارة المراحل الدراسية" />

    <HrLayout>
        <template #header>
            الإعدادات الأكاديمية / المراحل الدراسية
        </template>

        <div v-if="!activeYear" class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md mb-6" role="alert">
            <p class="font-bold">تنبيه!</p>
            <p>يجب عليك <a :href="route('school.academic-years.index')" class="underline">تفعيل سنة دراسية</a> أولاً لتتمكن من إضافة أو عرض المراحل الدراسية.</p>
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h3 class="text-xl font-bold mb-4">إضافة مرحلة جديدة لـ <span class="text-indigo-600">{{ activeYear.name }}</span></h3>
                    <form @submit.prevent="submitAddGradeForm" class="space-y-4">
                        <div>
                            <label for="name" class="block mb-2 text-sm font-medium text-gray-900">اسم المرحلة (مثال: الصف الأول)</label>
                            <input type="text" v-model="addGradeForm.name" id="name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <div v-if="addGradeForm.errors.name" class="text-sm text-red-600 mt-1">{{ addGradeForm.errors.name }}</div>
                        </div>
                        <div>
                            <label for="description" class="block mb-2 text-sm font-medium text-gray-900">وصف (اختياري)</label>
                            <textarea v-model="addGradeForm.description" id="description" rows="3" class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300"></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="addGradeForm.processing">حفظ</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                 <div class="bg-white shadow-md rounded-lg">
                    <div class="p-6 border-b">
                         <h3 class="text-xl font-bold">المراحل الدراسية الحالية</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-right py-3 px-4 font-semibold text-sm">الاسم</th>
                                    <th class="text-right py-3 px-4 font-semibold text-sm">الوصف</th>
                                    <th class="text-right py-3 px-4 font-semibold text-sm">عدد المقررات</th>
                                    <th class="text-right py-3 px-4 font-semibold text-sm">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700">
                                <tr v-for="grade in grades" :key="grade.id" class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4 font-medium">{{ grade.name }}</td>
                                    <td class="py-3 px-4 text-sm text-gray-600">{{ grade.description || '-' }}</td>
                                    <td class="py-3 px-4 text-center font-medium">{{ grade.subjects_count }}</td>
                                    <td class="py-3 px-4 space-x-2 flex justify-start">
                                        <button @click="openAssignModal(grade)" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                            <i class="fa fa-book mr-1"></i> إسناد مقررات
                                        </button>
                                        <button @click="deleteGrade(grade)" class="text-red-600 hover:text-red-900 font-medium">
                                            <i class="fa fa-trash mr-1"></i> حذف
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="grades.length === 0">
                                    <td colspan="4" class="text-center py-4">لا توجد مراحل دراسية لهذه السنة.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <Transition name="modal-fade">
            <div v-if="showAssignModal" class="fixed inset-0  bg-opacity-75 backdrop-blur-md z-50 flex justify-center items-center p-4" @click.self="closeModal">
                <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl mx-4">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-800">إسناد المقررات للمرحلة: <span class="text-indigo-600">{{ selectedGradeForModal.name }}</span></h3>
                        <button @click="closeModal" class="text-gray-500 hover:text-gray-800">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="mt-4">
                        <form @submit.prevent="submitAssignSubjectsForm" class="space-y-4">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">اختر المقررات</label>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 max-h-80 overflow-y-auto p-2 border rounded-md">
                                    <div v-for="subject in allSubjects" :key="subject.id" class="flex items-center">
                                        <input type="checkbox" :id="`subject-${subject.id}`" :value="subject.id" v-model="assignSubjectsForm.subject_ids" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                        <label :for="`subject-${subject.id}`" class="mr-2 block text-sm text-gray-900">{{ subject.name }}</label>
                                    </div>
                                </div>
                            </div>
                            <div v-if="assignSubjectsForm.errors.subject_ids" class="text-sm text-red-600 mt-1">{{ assignSubjectsForm.errors.subject_ids }}</div>
                            
                            <div class="flex justify-end space-x-2 pt-4 border-t border-gray-200">
                                <button type="button" @click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">إلغاء</button>
                                <button type="submit" :disabled="assignSubjectsForm.processing" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                    {{ assignSubjectsForm.processing ? 'جاري الحفظ...' : 'حفظ التغييرات' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </HrLayout>
</template>

<style>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.4s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>