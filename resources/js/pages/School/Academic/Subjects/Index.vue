<script setup>
import HrLayout from '../../../../layouts/HrLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    subjects: Object, // The paginated subjects object from the controller
});

// A ref to track which subject is currently being edited
const editingSubjectId = ref(null);

// Form for creating a new subject
const createForm = useForm({
    name: '',
    code: '',
});

// Form for updating an existing subject
const updateForm = useForm({
    name: '',
    code: '',
});

// Function to handle the creation of a new subject
const submitCreate = () => {
    createForm.post(route('school.subjects.store'), {
        onSuccess: () => createForm.reset(),
        preserveScroll: true,
    });
};

// Function to enter edit mode for a subject
const startEdit = (subject) => {
    editingSubjectId.value = subject.id;
    updateForm.name = subject.name;
    updateForm.code = subject.code;
};

// Function to cancel the edit mode
const cancelEdit = () => {
    editingSubjectId.value = null;
    updateForm.reset();
};

// Function to handle the update of a subject
const submitUpdate = (subjectId) => {
    updateForm.put(route('school.subjects.update', subjectId), {
        onSuccess: () => cancelEdit(),
        preserveScroll: true,
    });
};

// Function to delete a subject
const deleteSubject = (subject) => {
    if (confirm(`هل أنت متأكد من حذف المقرر "${subject.name}"؟`)) {
        router.delete(route('school.subjects.destroy', subject.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="إدارة المقررات الدراسية" />

    <HrLayout>
        <template #header>
            الإعدادات الأكاديمية / المقررات الدراسية
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Add New Subject Form -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h3 class="text-xl font-bold mb-4 text-gray-800">إضافة مقرر جديد</h3>
                    <form @submit.prevent="submitCreate" class="space-y-4">
                        <div>
                            <label for="name" class="block mb-2 text-sm font-medium text-gray-900">اسم المقرر (مثال: الرياضيات)</label>
                            <input type="text" v-model="createForm.name" id="name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <div v-if="createForm.errors.name" class="text-sm text-red-600 mt-1">{{ createForm.errors.name }}</div>
                        </div>
                        <div>
                            <label for="code" class="block mb-2 text-sm font-medium text-gray-900">كود المقرر (اختياري)</label>
                            <input type="text" v-model="createForm.code" id="code" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                            <div v-if="createForm.errors.code" class="text-sm text-red-600 mt-1">{{ createForm.errors.code }}</div>
                        </div>
                        <div class="flex justify-end pt-2">
                            <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="createForm.processing">
                                <i class="fas fa-plus mr-2"></i> حفظ المقرر
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- List of Subjects with inline editing -->
            <div class="lg:col-span-2">
                 <div class="bg-white shadow-md rounded-lg">
                    <div class="p-6 border-b">
                         <h3 class="text-xl font-bold text-gray-800">قائمة المقررات الدراسية</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-right py-3 px-4 font-semibold text-sm text-gray-600 uppercase">اسم المقرر</th>
                                    <th class="text-right py-3 px-4 font-semibold text-sm text-gray-600 uppercase">الكود</th>
                                    <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600 uppercase">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 divide-y">
                                <tr v-for="subject in subjects.data" :key="subject.id" class="hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <div v-if="editingSubjectId !== subject.id">{{ subject.name }}</div>
                                        <div v-else>
                                            <input type="text" v-model="updateForm.name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2">
                                            <div v-if="updateForm.errors.name" class="text-sm text-red-600 mt-1">{{ updateForm.errors.name }}</div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div v-if="editingSubjectId !== subject.id">{{ subject.code || '-' }}</div>
                                        <div v-else>
                                            <input type="text" v-model="updateForm.code" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2">
                                             <div v-if="updateForm.errors.code" class="text-sm text-red-600 mt-1">{{ updateForm.errors.code }}</div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <div v-if="editingSubjectId !== subject.id">
                                            <button @click="startEdit(subject)" class="text-blue-600 hover:text-blue-900 font-medium text-sm">تعديل</button>
                                            <span class="mx-1 text-gray-300">|</span>
                                            <button @click="deleteSubject(subject)" class="text-red-600 hover:text-red-900 font-medium text-sm">حذف</button>
                                        </div>
                                        <div v-else class="flex items-center justify-center space-x-2 rtl:space-x-reverse">
                                            <button @click="submitUpdate(subject.id)" class="text-green-600 hover:text-green-900 font-medium text-sm" :disabled="updateForm.processing">حفظ</button>
                                            <span class="mx-1 text-gray-300">|</span>
                                            <button @click="cancelEdit" class="text-gray-600 hover:text-gray-900 font-medium text-sm">إلغاء</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="subjects.data.length === 0">
                                    <td colspan="3" class="text-center py-6 text-gray-500">لم يتم إضافة أي مقررات دراسية بعد.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div v-if="subjects.links.length > 3" class="p-4 border-t">
                        <div class="flex flex-wrap -mb-1">
                            <template v-for="(link, key) in subjects.links" :key="key">
                                <div v-if="link.url === null" class="mr-1 mb-1 px-3 py-2 text-sm leading-4 text-gray-400 border rounded" v-html="link.label" />
                                <Link v-else class="mr-1 mb-1 px-3 py-2 text-sm leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500" :class="{ 'bg-indigo-100 text-indigo-700': link.active }" :href="link.url" v-html="link.label" />
                            </template>
                        </div>
                    </div>
                 </div>
            </div>
        </div>
    </HrLayout>
</template>

