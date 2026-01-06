<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    department: Object,
    employees: Array,
    teachers: Array,
    filters: Object,
});

const form = useForm({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
});

const selectedPersonnel = ref(null);
const showPersonnelDetails = ref(false);

const allPersonnel = computed(() => {
    return [
        ...props.employees.map(e => ({ ...e, type_label: 'موظف' })),
        ...props.teachers.map(t => ({ ...t, type_label: 'معلم' })),
    ];
});

const applyFilters = () => {
    form.get(route('hr.attendance-reports.department', props.department.department.id), {
        preserveState: true,
        preserveScroll: true,
    });
};

const viewPersonnelDetails = (personnel) => {
    router.visit(route('hr.attendance-reports.personnel-details', {
        personType: personnel.type,
        personId: personnel.id,
        start_date: form.start_date,
        end_date: form.end_date,
    }));
};
</script>

<template>
    <Head :title="`تقارير الحضور - ${department.department.name}`" />
    <HrLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">{{ department.department.name }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ department.department.description || '' }}</p>
                </div>
                <button
                    @click="router.visit(route('hr.attendance-reports.index'))"
                    class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-all"
                >
                    <i class="fas fa-arrow-right mr-2"></i>
                    العودة للتقارير العامة
                </button>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Filters -->
            <div class="bg-white shadow-lg rounded-xl p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">فلترة البيانات</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">من تاريخ</label>
                        <input
                            v-model="form.start_date"
                            type="date"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">إلى تاريخ</label>
                        <input
                            v-model="form.end_date"
                            type="date"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                        />
                    </div>
                    <div class="flex items-end">
                        <button
                            @click="applyFilters"
                            class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 text-white px-6 py-2.5 rounded-lg hover:from-indigo-700 hover:to-indigo-800 transition-all font-medium shadow-lg"
                        >
                            <i class="fas fa-filter mr-2"></i>
                            تطبيق الفلتر
                        </button>
                    </div>
                </div>
            </div>

            <!-- Department Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-green-50 to-green-100 border-2 border-green-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-green-800">الحضور</p>
                            <p class="text-3xl font-bold text-green-900 mt-2">
                                {{ department.statistics.present }}
                            </p>
                        </div>
                        <div class="bg-green-200 rounded-full p-4">
                            <i class="fas fa-check-circle text-green-700 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-red-50 to-red-100 border-2 border-red-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-red-800">الغياب</p>
                            <p class="text-3xl font-bold text-red-900 mt-2">
                                {{ department.statistics.absent }}
                            </p>
                        </div>
                        <div class="bg-red-200 rounded-full p-4">
                            <i class="fas fa-times-circle text-red-700 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 border-2 border-yellow-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-yellow-800">التأخير</p>
                            <p class="text-3xl font-bold text-yellow-900 mt-2">
                                {{ department.statistics.late }}
                            </p>
                        </div>
                        <div class="bg-yellow-200 rounded-full p-4">
                            <i class="fas fa-clock text-yellow-700 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-blue-100 border-2 border-blue-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-blue-800">نسبة الحضور</p>
                            <p class="text-3xl font-bold text-blue-900 mt-2">
                                {{ department.statistics.attendance_rate }}%
                            </p>
                        </div>
                        <div class="bg-blue-200 rounded-full p-4">
                            <i class="fas fa-percentage text-blue-700 text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personnel List -->
            <div class="bg-white shadow-lg rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">الموظفين والمعلمين</h3>
                            <p class="text-sm text-gray-600 mt-1">
                                إجمالي: {{ allPersonnel.length }} ({{ employees.length }} موظف، {{ teachers.length }} معلم)
                            </p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                                <tr>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider">الاسم</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">النوع</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">الوظيفة</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">الدوام</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">حضور</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">غياب</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">تأخير</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">نسبة الحضور</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="person in allPersonnel"
                                    :key="`${person.type}-${person.id}`"
                                    class="hover:bg-gray-50 transition-colors"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-semibold text-gray-900">{{ person.name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span :class="person.type === 'employee' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'"
                                              class="px-2 py-1 rounded-full text-xs font-medium">
                                            {{ person.type_label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                        {{ person.job_title || '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                        <div v-if="person.shift">
                                            <div class="font-medium">{{ person.shift.name }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ person.shift.start_time }} - {{ person.shift.end_time }}
                                            </div>
                                        </div>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm font-semibold">
                                            {{ person.statistics.present }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm font-semibold">
                                            {{ person.statistics.absent }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm font-semibold">
                                            {{ person.statistics.late }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span :class="person.statistics.attendance_rate >= 90 ? 'bg-green-100 text-green-800' : person.statistics.attendance_rate >= 70 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'"
                                              class="px-2 py-1 rounded text-sm font-semibold">
                                            {{ person.statistics.attendance_rate }}%
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <button
                                            @click="viewPersonnelDetails(person)"
                                            class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-all text-sm"
                                        >
                                            <i class="fas fa-eye mr-1"></i>
                                            عرض التفاصيل
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="allPersonnel.length === 0">
                                    <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                                        لا يوجد موظفين أو معلمين في هذا القسم
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Personnel Details Modal (will be implemented in next phase) -->
        <div v-if="showPersonnelDetails && selectedPersonnel" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="text-xl font-bold">تفاصيل {{ selectedPersonnel.name }}</h3>
                    <button @click="showPersonnelDetails = false" class="text-white hover:text-gray-200 transition-colors p-1 rounded-full hover:bg-white/20">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-6">
                    <p class="text-gray-600">سيتم عرض تفاصيل المقارنة بين البصمات والجدول الزمني هنا في المرحلة التالية</p>
                </div>
            </div>
        </div>
    </HrLayout>
</template>
