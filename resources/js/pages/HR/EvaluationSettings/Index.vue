<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    criteria: Array,
});

const showModal = ref(false);
const isEditing = ref(false);

const form = useForm({
    id: null,
    name: '',
    description: '',
    max_score: 10,
    affects_salary: false,
    is_active: true,
});

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    showModal.value = true;
};

const openEditModal = (criterion) => {
    isEditing.value = true;
    form.id = criterion.id;
    form.name = criterion.name;
    form.description = criterion.description;
    form.max_score = criterion.max_score;
    form.affects_salary = criterion.affects_salary;
    form.is_active = criterion.is_active;
    showModal.value = true;
};

const submitForm = () => {
    const action = isEditing.value
        ? form.put(route('hr.evaluation-settings.update', form.id))
        : form.post(route('hr.evaluation-settings.store'));
    
    action.then(() => {
        if (!form.hasErrors) {
            showModal.value = false;
        }
    });
};
</script>

<template>
    <Head title="إعدادات التقييمات" />
    <HrLayout>
        <template #header>
            إدارة معايير التقييم
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">معايير التقييم</h2>
                <button @click="openCreateModal" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 flex items-center">
                    <i class="fas fa-plus mr-2"></i> إضافة معيار جديد
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4">اسم المعيار</th>
                            <th class="text-center py-3 px-4">الدرجة القصوى</th>
                            <th class="text-center py-3 px-4">يؤثر على الراتب</th>
                            <th class="text-center py-3 px-4">الحالة</th>
                            <th class="text-center py-3 px-4">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="criterion in criteria" :key="criterion.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium">{{ criterion.name }}</td>
                            <td class="py-3 px-4 text-center font-mono">{{ criterion.max_score }}</td>
                            <td class="py-3 px-4 text-center">
                                <i class="fas" :class="criterion.affects_salary ? 'fa-check-circle text-green-500' : 'fa-times-circle text-red-500'"></i>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full" :class="criterion.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                                    {{ criterion.is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <button @click="openEditModal(criterion)" class="text-blue-600 hover:text-blue-800">تعديل</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex justify-center items-center p-4">
             <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
                <h3 class="text-xl font-bold text-gray-800 mb-4">{{ isEditing ? 'تعديل معيار التقييم' : 'إضافة معيار جديد' }}</h3>
                <form @submit.prevent="submitForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-800">اسم المعيار</label>
                        <input type="text" v-model="form.name" class="mt-1 block w-full rounded-md text-gray-800" required>
                    </div>
                     <div>
                        <label class="block text-sm font-medium text-gray-800">الوصف (اختياري)</label>
                        <textarea v-model="form.description" rows="2" class="mt-1 block w-full rounded-md text-gray-800"></textarea>
                    </div>
                     <div>
                        <label class="block text-sm font-medium text-gray-800">الدرجة القصوى</label>
                        <input type="number" v-model="form.max_score" class="mt-1 block w-full rounded-md text-gray-800" required>
                    </div>
                    <div class="flex items-center space-x-4 rtl:space-x-reverse">
                        <label class="flex items-center"><input type="checkbox" v-model="form.affects_salary" class="rounded"><span class="ml-2 rtl:mr-2 text-gray-800">يؤثر على الراتب</span></label>
                        <label class="flex items-center"><input type="checkbox" v-model="form.is_active" class="rounded"><span class="ml-2 rtl:mr-2 text-gray-800">نشط</span></label>
                    </div>
                    <div class="pt-4 flex justify-end space-x-2 rtl:space-x-reverse border-t">
                        <button type="button" @click="showModal = false" class="bg-gray-200 px-4 py-2 rounded-md">إلغاء</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md" :disabled="form.processing">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </HrLayout>
</template>
