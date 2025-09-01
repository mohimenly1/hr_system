<script setup>
import HrLayout from '../../../../layouts/HrLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3'; // Import router
import { ref } from 'vue';

const props = defineProps({
    grades: Array,
    activeYear: Object,
});

const showAddModal = ref(false);
const selectedGradeForModal = ref(null);

const form = useForm({
    name: '',
    grade_id: '',
});

const openAddModal = (grade) => {
    selectedGradeForModal.value = grade;
    form.grade_id = grade.id;
    showAddModal.value = true;
};

const closeModal = () => {
    showAddModal.value = false;
    form.reset();
};

const submit = () => {
    form.post(route('school.sections.store'), {
        onSuccess: () => closeModal(),
        preserveScroll: true,
    });
};

// --- FIX: Use `router.delete` instead of the deprecated `$inertia.delete` ---
const deleteSection = (section) => {
    if (confirm(`هل أنت متأكد من حذف الشعبة "${section.name}"؟`)) {
        router.delete(route('school.sections.destroy', section.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="إدارة الشعب الدراسية" />

    <HrLayout>
        <template #header>
            الإعدادات الأكاديمية / الشعب الدراسية
        </template>

        <!-- Warning if no active year is set -->
        <div v-if="!activeYear" class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md mb-6" role="alert">
            <p class="font-bold">تنبيه!</p>
            <p>يجب عليك <Link :href="route('school.academic-years.index')" class="underline">تفعيل سنة دراسية</Link> أولاً.</p>
        </div>

        <div v-else>
            <div class="mb-4">
                <p class="text-lg text-gray-800">إدارة الشعب الدراسية للعام: <span class="font-bold text-indigo-600">{{ activeYear.name }}</span></p>
            </div>

            <!-- List of Grades with their sections -->
            <div class="space-y-6">
                <div v-if="grades.length === 0" class="bg-white shadow-md rounded-lg p-6 text-center text-gray-500">
                    لم يتم إضافة أي مراحل دراسية بعد. يرجى <Link :href="route('school.grades.index')" class="underline text-indigo-600">إضافة مراحل</Link> أولاً.
                </div>
                <div v-for="grade in grades" :key="grade.id" class="bg-white shadow-md rounded-lg">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h3 class="text-xl font-bold text-gray-800">{{ grade.name }}</h3>
                        <button @click="openAddModal(grade)" class="bg-indigo-500 text-white px-3 py-1 rounded-md text-sm hover:bg-indigo-600">
                            <i class="fas fa-plus mr-1"></i> إضافة شعبة
                        </button>
                    </div>
                    <div class="p-4">
                        <ul v-if="grade.sections.length > 0" class="flex flex-wrap gap-2">
                            <li v-for="section in grade.sections" :key="section.id" class="flex items-center bg-gray-100 text-gray-800 rounded-full px-3 py-1 text-sm">
                                <span>{{ section.name }}</span>
                                <button @click="deleteSection(section)" class="mr-2 text-red-400 hover:text-red-600 text-xs">&times;</button>
                            </li>
                        </ul>
                        <p v-else class="text-gray-500 text-sm">لا توجد شعب لهذه المرحلة.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Section Modal with Transition -->
        <Transition name="modal-fade">
            <div v-if="showAddModal" class="fixed inset-0  bg-opacity-75 backdrop-blur-md z-50 flex justify-center items-center p-4" @click.self="closeModal">
                <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-xl font-bold text-gray-900">إضافة شعبة جديدة لـ <span class="text-indigo-600">{{ selectedGradeForModal.name }}</span></h3>
                        <button @click="closeModal" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
                    </div>
                    <form @submit.prevent="submit" class="mt-4 space-y-4">
                        <div>
                            <label for="section_name" class="block mb-2 text-sm font-medium text-gray-900">اسم الشعبة (مثال: أ, ب, 1, 2)</label>
                            <input type="text" v-model="form.name" id="section_name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div class="flex justify-end pt-4 border-t space-x-2 rtl:space-x-reverse">
                            <button type="button" @click="closeModal" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="form.processing">حفظ الشعبة</button>
                        </div>
                    </form>
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
