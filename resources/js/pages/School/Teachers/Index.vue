<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

// Props received from the controller
const props = defineProps({
    teachers: Object, // The paginated list of teachers
});

const deleteTeacher = (teacher) => {
    if (confirm(`هل أنت متأكد من حذف المعلم "${teacher.user.name}"؟`)) {
        router.delete(route('school.teachers.destroy', teacher.id), {
            preserveScroll: true,
        });
    }
};

</script>
<template>
    <Head title="إدارة المعلمين" />

    <HrLayout>
        <template #header>
            إدارة المعلمين
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 sm:px-20 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-gray-800">قائمة المعلمين</h2>
                        <Link :href="route('school.teachers.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors duration-200">
                            إضافة معلم جديد
                        </Link>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الاسم</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">البريد الإلكتروني</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">القسم</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التخصص</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="teacher in teachers.data" :key="teacher.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ teacher.user.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ teacher.user.email }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ teacher.department.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ teacher.specialization }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <Link :href="route('school.teachers.show', teacher.id)" class="text-indigo-600 hover:text-indigo-900 ml-4">عرض</Link>
                                        <Link :href="route('school.teachers.edit', teacher.id)" class="text-yellow-600 hover:text-yellow-900 ml-4">تعديل</Link>
                                        <button @click="deleteTeacher(teacher)" class="text-red-600 hover:text-red-900">حذف</button>
                                    </td>
                                </tr>
                                <tr v-if="teachers.data.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                        لا يوجد معلمين حالياً.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <Component :is="link.url ? 'Link' : 'span'" v-for="link in teachers.links" :key="link.label"
                            :href="link.url"
                            v-html="link.label"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 leading-5 rounded-md hover:bg-gray-100 focus:outline-none focus:ring ring-gray-300 transition ease-in-out duration-150"
                            :class="{ 'bg-gray-200': link.active }"
                        />
                    </div>
                </div>
            </div>
        </div>
    </HrLayout>
</template>