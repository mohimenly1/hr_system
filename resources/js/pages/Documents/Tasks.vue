<script setup>
import HrLayout from '@/layouts/HrLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    tasks: Object, // Paginator object from Laravel
});

// دالة مساعدة لتنسيق التاريخ
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('ar-LY', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head title="المهام المعلقة" />
    <HrLayout>
        <template #header>
            المهام المعلقة
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">المهام المطلوبة منك</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">رقم الوثيقة</th>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">الموضوع</th>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">مرسل من</th>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">تاريخ الإحالة</th>
                            <th class="py-3 px-4 text-center text-sm font-semibold text-gray-600">الإجراء</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="task in tasks.data" :key="task.id" class="border-b hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-4 font-mono">{{ task.document.serial_number }}</td>
                            <td class="py-3 px-4 font-medium">{{ task.document.subject }}</td>
                            <td class="py-3 px-4">
                                {{ task.from_department ? task.from_department.name : (task.document.creator ? task.document.creator.name : 'النظام') }}
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">{{ formatDate(task.created_at) }}</td>
                            <td class="py-3 px-4 text-center">
                                <Link :href="route('documents.show', task.document.id)" class="text-sm bg-indigo-100 text-indigo-700 hover:bg-indigo-200 px-4 py-2 rounded-full font-semibold transition-colors">
                                    <i class="fas fa-folder-open mr-1 rtl:ml-1"></i>
                                    فتح الوثيقة
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="tasks.data.length === 0">
                            <td colspan="5" class="text-center py-16 text-gray-500">
                                <i class="fas fa-check-circle text-4xl text-green-400 mb-3"></i>
                                <p class="font-semibold">لا توجد مهام معلقة لديك حالياً.</p>
                                <p class="text-sm mt-1">عمل رائع!</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <div v-if="tasks.links.length > 3" class="mt-6 flex justify-center">
                <div class="flex flex-wrap -mb-1">
                    <template v-for="(link, key) in tasks.links" :key="key">
                        <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded" v-html="link.label" />
                        <Link v-else 
                              class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500 transition-colors" 
                              :class="{ 'bg-indigo-500 text-white': link.active }" 
                              :href="link.url" 
                              v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </HrLayout>
</template>

