<script setup lang="ts">
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    type: String,
    filterType: String,
    selectedMonth: String,
    startDate: String,
    endDate: String,
    availableMonths: Array,
    items: Array,
    filters: Object,
});

const selectedType = ref(props.type || 'employees');
const filterType = ref(props.filterType || 'month');
const selectedMonth = ref(props.selectedMonth || '');
const startDate = ref(props.startDate || '');
const endDate = ref(props.endDate || '');

const filterData = () => {
    const params = {
        type: selectedType.value,
        filter_type: filterType.value,
    };

    if (filterType.value === 'month') {
        params.month = selectedMonth.value;
    } else {
        params.start_date = startDate.value;
        params.end_date = endDate.value;
    }

    router.get(route('hr.general-attendance-report.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const exportReport = () => {
    if (filterType.value === 'month' && !selectedMonth.value) {
        alert('يرجى تحديد الشهر أولاً');
        return;
    }
    if (filterType.value === 'date_range' && (!startDate.value || !endDate.value)) {
        alert('يرجى تحديد تاريخ البداية والنهاية');
        return;
    }

    let url = route('hr.general-attendance-report.export') +
        `?type=${selectedType.value}&filter_type=${filterType.value}`;

    if (filterType.value === 'month') {
        url += `&month=${selectedMonth.value}`;
    } else {
        url += `&start_date=${startDate.value}&end_date=${endDate.value}`;
    }

    window.open(url, '_blank');
};

const totalStatistics = computed(() => {
    if (!props.items || props.items.length === 0) {
        return {
            totalPresent: 0,
            totalAbsent: 0,
            totalLate: 0,
            totalLeave: 0,
            avgRate: 0,
        };
    }

    const totals = props.items.reduce((acc, item) => {
        const stats = item.statistics;
        acc.totalPresent += stats.actual_present_days;
        acc.totalAbsent += stats.actual_absent_days;
        acc.totalLate += stats.late_days;
        acc.totalLeave += stats.leave_days;
        acc.totalRate += stats.attendance_rate;
        return acc;
    }, {
        totalPresent: 0,
        totalAbsent: 0,
        totalLate: 0,
        totalLeave: 0,
        totalRate: 0,
    });

    return {
        ...totals,
        avgRate: props.items.length > 0 ? (totals.totalRate / props.items.length).toFixed(2) : 0,
    };
});
</script>

<template>
    <Head title="التقارير العامة للحضور والغياب" />

    <HrLayout>
        <template #header>
            التقارير العامة للحضور والغياب
        </template>

        <div class="space-y-6">
            <!-- Filters Section -->
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">فلترة التقرير</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            نوع التقرير
                        </label>
                        <select
                            v-model="selectedType"
                            @change="filterData"
                            class="w-full border border-gray-300 rounded-md px-4 py-2 text-gray-800 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="employees">الموظفين</option>
                            <option value="teachers">المعلمين</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            نوع الفلترة
                        </label>
                        <select
                            v-model="filterType"
                            @change="filterData"
                            class="w-full border border-gray-300 rounded-md px-4 py-2 text-gray-800 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="month">حسب الشهر</option>
                            <option value="date_range">حسب نطاق التاريخ</option>
                        </select>
                    </div>
                    <div v-if="filterType === 'month'">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            الشهر
                        </label>
                        <select
                            v-model="selectedMonth"
                            @change="filterData"
                            class="w-full border border-gray-300 rounded-md px-4 py-2 text-gray-800 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">اختر الشهر</option>
                            <option v-for="month in availableMonths" :key="month.value" :value="month.value">
                                {{ month.label }}
                            </option>
                        </select>
                    </div>
                    <div v-if="filterType === 'date_range'" class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                من تاريخ
                            </label>
                            <input
                                type="date"
                                v-model="startDate"
                                @change="filterData"
                                class="w-full border border-gray-300 rounded-md px-4 py-2 text-gray-800 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                إلى تاريخ
                            </label>
                            <input
                                type="date"
                                v-model="endDate"
                                @change="filterData"
                                class="w-full border border-gray-300 rounded-md px-4 py-2 text-gray-800 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button
                        @click="exportReport"
                        :disabled="(filterType === 'month' && !selectedMonth) || (filterType === 'date_range' && (!startDate || !endDate))"
                        class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 flex items-center justify-center disabled:bg-gray-400 disabled:cursor-not-allowed"
                    >
                        <i class="fas fa-file-excel ml-2"></i>
                        تصدير التقرير
                    </button>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div v-if="items && items.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white shadow-md rounded-lg p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">إجمالي العدد</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ items.length }}</p>
                        </div>
                        <div class="bg-blue-100 rounded-full p-4">
                            <i class="fas fa-users text-blue-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-md rounded-lg p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">إجمالي أيام الحضور</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ totalStatistics.totalPresent }}</p>
                        </div>
                        <div class="bg-green-100 rounded-full p-4">
                            <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-md rounded-lg p-6 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">إجمالي أيام الغياب</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ totalStatistics.totalAbsent }}</p>
                        </div>
                        <div class="bg-red-100 rounded-full p-4">
                            <i class="fas fa-times-circle text-red-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-md rounded-lg p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">إجمالي أيام التأخر</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ totalStatistics.totalLate }}</p>
                        </div>
                        <div class="bg-yellow-100 rounded-full p-4">
                            <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-md rounded-lg p-6 border-l-4 border-indigo-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">متوسط نسبة الحضور</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ totalStatistics.avgRate }}%</p>
                        </div>
                        <div class="bg-indigo-100 rounded-full p-4">
                            <i class="fas fa-chart-line text-indigo-600 text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div v-if="items && items.length > 0" class="bg-white shadow-md rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    {{ selectedType === 'employees' ? 'قائمة الموظفين' : 'قائمة المعلمين' }}
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-50">
                            <tr>
                                <th v-if="selectedType === 'employees'" class="text-right py-3 px-4 font-semibold text-sm text-gray-600">رقم الموظف</th>
                                <th class="text-right py-3 px-4 font-semibold text-sm text-gray-600">
                                    {{ selectedType === 'employees' ? 'اسم الموظف' : 'اسم المعلم' }}
                                </th>
                                <th class="text-right py-3 px-4 font-semibold text-sm text-gray-600">القسم</th>
                                <th v-if="selectedType === 'employees'" class="text-right py-3 px-4 font-semibold text-sm text-gray-600">المسمى الوظيفي</th>
                                <th v-if="selectedType === 'teachers'" class="text-right py-3 px-4 font-semibold text-sm text-gray-600">التخصص</th>
                                <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600">أيام الحضور</th>
                                <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600">أيام الغياب</th>
                                <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600">أيام التأخر</th>
                                <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600">أيام الإجازة</th>
                                <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600">نسبة الحضور</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <tr
                                v-for="item in items"
                                :key="item.id"
                                class="border-b hover:bg-gray-50"
                                :class="{
                                    'bg-red-50': item.statistics.attendance_rate < 80 && item.statistics.attendance_rate > 0
                                }"
                            >
                                <td v-if="selectedType === 'employees'" class="py-3 px-4 font-medium">{{ item.employee_id }}</td>
                                <td class="py-3 px-4 font-medium">{{ item.name }}</td>
                                <td class="py-3 px-4">{{ item.department }}</td>
                                <td v-if="selectedType === 'employees'" class="py-3 px-4">{{ item.job_title }}</td>
                                <td v-if="selectedType === 'teachers'" class="py-3 px-4">{{ item.specialization }}</td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        {{ item.statistics.actual_present_days }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                        {{ item.statistics.actual_absent_days }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        {{ item.statistics.late_days }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ item.statistics.leave_days }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span
                                        class="px-3 py-1 text-xs font-semibold rounded-full"
                                        :class="{
                                            'bg-green-100 text-green-800': item.statistics.attendance_rate >= 90,
                                            'bg-yellow-100 text-yellow-800': item.statistics.attendance_rate >= 80 && item.statistics.attendance_rate < 90,
                                            'bg-red-100 text-red-800': item.statistics.attendance_rate < 80 && item.statistics.attendance_rate > 0,
                                            'bg-gray-100 text-gray-800': item.statistics.attendance_rate === 0
                                        }"
                                    >
                                        {{ item.statistics.attendance_rate }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white shadow-md rounded-lg p-12 text-center">
                <i class="fas fa-inbox text-gray-400 text-6xl mb-4"></i>
                <h3 class="text-xl font-bold text-gray-600 mb-2">لا توجد بيانات</h3>
                <p class="text-gray-500">يرجى تحديد الشهر لعرض البيانات</p>
            </div>
        </div>
    </HrLayout>
</template>

