<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    departments: Array,
    managers: Array,
});

const showModal = ref(false);
const isEditing = ref(false);

const form = useForm({
    id: null,
    name: '',
    description: '',
    manager_id: null,
});

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    showModal.value = true;
};

const openEditModal = (department) => {
    isEditing.value = true;
    form.id = department.id;
    form.name = department.name;
    form.description = department.description;
    form.manager_id = department.manager_id;
    showModal.value = true;
};

const submitForm = () => {
    const action = isEditing.value
        ? form.put(route('hr.departments.update', form.id))
        : form.post(route('hr.departments.store'));
    
    action.then(() => {
        if (!form.hasErrors) {
            showModal.value = false;
        }
    });
};

const deleteDepartment = (departmentId) => {
    if (confirm('هل أنت متأكد من حذف هذا القسم؟ سيتم إلغاء ارتباط الموظفين والمعلمين به.')) {
        router.delete(route('hr.departments.destroy', departmentId));
    }
};
</script>

<template>
    <Head title="إدارة الأقسام" />
    <HrLayout>
        <template #header>
            إدارة الأقسام الإدارية
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">الأقسام الإدارية</h2>
                <button @click="openCreateModal" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 flex items-center">
                    <i class="fas fa-plus mr-2"></i> إضافة قسم جديد
                </button>
            </div>

            <div v-if="departments.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="dept in departments" :key="dept.id" 
                     class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-lg transition-shadow duration-300 flex flex-col">
                    
                    <div class="p-5">
                        <h3 class="text-xl font-bold text-gray-900">{{ dept.name }}</h3>
                        <p class="text-sm text-gray-600 mt-2 min-h-[40px]">{{ dept.description || 'لا يوجد وصف لهذا القسم.' }}</p>
                    </div>

                    <div class="mt-auto p-5 space-y-3">
                        <div class="bg-indigo-50 border-l-4 border-indigo-500 p-3 rounded-r-md">
                            <p class="text-xs font-semibold text-indigo-800">مدير القسم</p>
                            <div class="flex items-center mt-1">
                                <i class="fas fa-crown text-indigo-500 mr-2"></i>
                                <Link v-if="dept.manager && dept.manager.employee"
                                      :href="route('hr.employees.show', dept.manager.employee.id)"
                                      class="text-sm font-bold text-gray-800 hover:text-indigo-600 hover:underline">
                                    {{ dept.manager.full_name }}
                                </Link>
                                <Link v-else-if="dept.manager && dept.manager.teacher"
                                      :href="route('school.teachers.show', dept.manager.teacher.id)"
                                      class="text-sm font-bold text-gray-800 hover:text-indigo-600 hover:underline">
                                    {{ dept.manager.full_name }}
                                </Link>
                                <span v-else class="text-sm font-bold text-gray-800">
                                    {{ dept.manager ? dept.manager.full_name : 'لم يحدد' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex justify-around text-center pt-3">
                            <div>
                                <p class="font-bold text-xl text-gray-800">{{ dept.employees_count }}</p>
                                <p class="text-xs text-gray-500">موظف</p>
                            </div>
                             <div>
                                <p class="font-bold text-xl text-gray-800">{{ dept.teachers_count }}</p>
                                <p class="text-xs text-gray-500">معلم</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-3 flex justify-end space-x-2 rtl:space-x-reverse border-t">
                        <button @click="openEditModal(dept)" class="text-sm font-medium text-blue-600 hover:text-blue-800">تعديل</button>
                        <span class="text-gray-300">|</span>
                        <button @click="deleteDepartment(dept.id)" class="text-sm font-medium text-red-600 hover:text-red-800">حذف</button>
                    </div>
                </div>
            </div>
             <div v-else class="text-center py-16 text-gray-500">
                <i class="fas fa-building text-4xl mb-3"></i>
                <p>لم يتم إضافة أي أقسام بعد.</p>
                <p class="text-sm mt-1">ابدأ بإضافة قسم جديد لإدارة موظفيك ومعلميك.</p>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex justify-center items-center p-4">
             <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
                <h3 class="text-xl font-bold text-gray-800 mb-4">{{ isEditing ? 'تعديل القسم' : 'إضافة قسم جديد' }}</h3>
                <form @submit.prevent="submitForm" class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-800">اسم القسم</label>
                        <input type="text" v-model="form.name" class="mt-1 block w-full rounded-md text-gray-800" required>
                         <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
                    </div>
                     <div>
                        <label class="block text-sm font-medium text-gray-800">مدير القسم</label>
                        <select v-model="form.manager_id" class="mt-1 block w-full rounded-md text-gray-800">
                            <option :value="null">-- اختر مديراً --</option>
                            <option v-for="manager in managers" :key="manager.id" :value="manager.id">{{ manager.full_name }}</option>
                        </select>
                         <div v-if="form.errors.manager_id" class="text-sm text-red-600 mt-1">{{ form.errors.manager_id }}</div>
                    </div>
                     <div>
                        <label class="block text-sm font-medium text-gray-800">الوصف</label>
                        <textarea v-model="form.description" rows="3" class="mt-1 block w-full rounded-md text-gray-800"></textarea>
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

