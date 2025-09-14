<script setup>
import HrLayout from '@/layouts/HrLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    externalParties: Array,
});

const showModal = ref(false);
const isEditing = ref(false);

const form = useForm({
    id: null,
    name: '',
    type: '',
    contact_info: '',
});

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    showModal.value = true;
};

const openEditModal = (party) => {
    isEditing.value = true;
    form.id = party.id;
    form.name = party.name;
    form.type = party.type;
    form.contact_info = party.contact_info;
    showModal.value = true;
};

const submitForm = () => {
    const action = isEditing.value
        ? form.put(route('documents.settings.external-parties.update', form.id))
        : form.post(route('documents.settings.external-parties.store'));
    
    action.then(() => {
        if (!form.hasErrors) {
            showModal.value = false;
        }
    });
};

const deleteItem = (id) => {
    if (confirm('هل أنت متأكد من حذف هذه الجهة؟')) {
        router.delete(route('documents.settings.external-parties.destroy', id));
    }
};
</script>

<template>
    <Head title="إعدادات الجهات الخارجية" />
    <HrLayout>
        <template #header>إعدادات الجهات الخارجية</template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">الجهات الخارجية</h2>
                <button @click="openCreateModal" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 flex items-center">
                    <i class="fas fa-plus mr-2"></i> إضافة جهة جديدة
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">الاسم</th>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">النوع</th>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">معلومات الاتصال</th>
                            <th class="py-3 px-4 text-center text-sm font-semibold text-gray-600">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="party in externalParties" :key="party.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium">{{ party.name }}</td>
                            <td class="py-3 px-4">{{ party.type }}</td>
                            <td class="py-3 px-4">{{ party.contact_info }}</td>
                            <td class="py-3 px-4 text-center space-x-2 rtl:space-x-reverse">
                                <button @click="openEditModal(party)" class="text-blue-600 hover:text-blue-800">تعديل</button>
                                <button @click="deleteItem(party.id)" class="text-red-600 hover:text-red-800">حذف</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex justify-center items-center p-4">
             <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
                <h3 class="text-xl font-bold text-gray-800 mb-4">{{ isEditing ? 'تعديل الجهة' : 'إضافة جهة جديدة' }}</h3>
                <form @submit.prevent="submitForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-800">الاسم</label>
                        <input type="text" v-model="form.name" class="mt-1 block w-full rounded-md" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-800">النوع (مثال: وزارة, شركة, ...)</label>
                        <input type="text" v-model="form.type" class="mt-1 block w-full rounded-md">
                    </div>
                     <div>
                        <label class="block text-sm font-medium text-gray-800">معلومات الاتصال</label>
                        <textarea v-model="form.contact_info" rows="3" class="mt-1 block w-full rounded-md"></textarea>
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
