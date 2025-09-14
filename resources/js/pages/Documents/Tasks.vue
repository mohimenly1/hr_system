<script setup>
import HrLayout from '@/layouts/HrLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    tasks: Object,
});
</script>

<template>
    <Head title="المهام المعلقة" />
    <HrLayout>
        <template #header>المهام المعلقة</template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">المهام المطلوبة منك</h2>

            <div v-if="tasks.data.length > 0" class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">الرقم</th>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">الموضوع</th>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">مرسل من</th>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">الإجراء المطلوب</th>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">تاريخ الإحالة</th>
                            <th class="py-3 px-4 text-center text-sm font-semibold text-gray-600">التفاصيل</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="task in tasks.data" :key="task.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4 font-mono">{{ task.document.serial_number }}</td>
                            <td class="py-3 px-4 font-medium">{{ task.document.subject }}</td>
                            <td class="py-3 px-4">{{ task.from_user.name }}</td>
                            <td class="py-3 px-4">{{ task.action === 'review' ? 'المراجعة والاعتماد' : task.action }}</td>
                            <td class="py-3 px-4">{{ new Date(task.created_at).toLocaleDateString('ar-LY') }}</td>
                            <td class="py-3 px-4 text-center">
                                <Link :href="route('documents.show', task.document.id)" class="bg-indigo-600 text-white px-3 py-1 rounded-md text-xs hover:bg-indigo-700">
                                    فتح الوثيقة
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="text-center py-16 text-gray-500">
                <i class="fas fa-check-circle text-4xl mb-3 text-green-500"></i>
                <p class="font-bold">لا توجد مهام معلقة لديك.</p>
                <p class="text-sm mt-1">لقد أنجزت كل مهامك!</p>
            </div>
        </div>
    </HrLayout>
</template>
