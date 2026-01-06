<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    overallStatistics: Object,
    departmentsStatistics: Array,
    dailyTrend: Object,
    filters: Object,
});

const form = useForm({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
});

// Chart instances
let overallChartInstance = null;
let dailyTrendChartInstance = null;
let departmentsChartInstance = null;

const overallChartCanvas = ref(null);
const dailyTrendChartCanvas = ref(null);
const departmentsChartCanvas = ref(null);

const applyFilters = () => {
    form.get(route('hr.attendance-reports.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

// Initialize overall statistics chart
const initOverallChart = () => {
    if (!overallChartCanvas.value || !props.overallStatistics) return;

    if (overallChartInstance) {
        overallChartInstance.destroy();
    }

    const stats = props.overallStatistics.statistics;

    overallChartInstance = new Chart(overallChartCanvas.value, {
        type: 'doughnut',
        data: {
            labels: ['حضور', 'غياب', 'تأخير', 'إجازة'],
            datasets: [{
                data: [
                    stats.total_present,
                    stats.total_absent,
                    stats.total_late,
                    stats.total_on_leave,
                ],
                backgroundColor: [
                    '#10b981',
                    '#ef4444',
                    '#f59e0b',
                    '#3b82f6',
                ],
                borderWidth: 2,
                borderColor: '#fff',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    rtl: true,
                },
                title: {
                    display: true,
                    text: 'إحصائيات الحضور والغياب الإجمالية',
                    font: {
                        size: 16,
                        weight: 'bold',
                    },
                },
            },
        },
    });
};

// Initialize daily trend chart
const initDailyTrendChart = () => {
    if (!dailyTrendChartCanvas.value || !props.dailyTrend) return;

    if (dailyTrendChartInstance) {
        dailyTrendChartInstance.destroy();
    }

    dailyTrendChartInstance = new Chart(dailyTrendChartCanvas.value, {
        type: 'line',
        data: {
            labels: props.dailyTrend.labels,
            datasets: props.dailyTrend.datasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    rtl: true,
                },
                title: {
                    display: true,
                    text: 'اتجاه الحضور اليومي',
                    font: {
                        size: 16,
                        weight: 'bold',
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                    },
                },
            },
        },
    });
};

// Initialize departments comparison chart
const initDepartmentsChart = () => {
    if (!departmentsChartCanvas.value || !props.departmentsStatistics) return;

    if (departmentsChartInstance) {
        departmentsChartInstance.destroy();
    }

    const departments = props.departmentsStatistics;

    departmentsChartInstance = new Chart(departmentsChartCanvas.value, {
        type: 'bar',
        data: {
            labels: departments.map(d => d.department.name),
            datasets: [
                {
                    label: 'حضور',
                    data: departments.map(d => d.statistics.present),
                    backgroundColor: '#10b981',
                },
                {
                    label: 'غياب',
                    data: departments.map(d => d.statistics.absent),
                    backgroundColor: '#ef4444',
                },
                {
                    label: 'تأخير',
                    data: departments.map(d => d.statistics.late),
                    backgroundColor: '#f59e0b',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    rtl: true,
                },
                title: {
                    display: true,
                    text: 'مقارنة الأقسام',
                    font: {
                        size: 16,
                        weight: 'bold',
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                },
            },
        },
    });
};

onMounted(() => {
    initOverallChart();
    initDailyTrendChart();
    initDepartmentsChart();
});

watch(() => [props.overallStatistics, props.dailyTrend, props.departmentsStatistics], () => {
    initOverallChart();
    initDailyTrendChart();
    initDepartmentsChart();
}, { deep: true });
</script>

<template>
    <Head title="تقارير الحضور والغياب" />
    <HrLayout>
        <template #header>
            تقارير الحضور والغياب
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

            <!-- Overall Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-green-50 to-green-100 border-2 border-green-200 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-green-800">إجمالي الحضور</p>
                            <p class="text-3xl font-bold text-green-900 mt-2">
                                {{ overallStatistics?.statistics?.total_present || 0 }}
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
                            <p class="text-sm font-medium text-red-800">إجمالي الغياب</p>
                            <p class="text-3xl font-bold text-red-900 mt-2">
                                {{ overallStatistics?.statistics?.total_absent || 0 }}
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
                            <p class="text-sm font-medium text-yellow-800">إجمالي التأخير</p>
                            <p class="text-3xl font-bold text-yellow-900 mt-2">
                                {{ overallStatistics?.statistics?.total_late || 0 }}
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
                                {{ overallStatistics?.statistics?.attendance_rate || 0 }}%
                            </p>
                        </div>
                        <div class="bg-blue-200 rounded-full p-4">
                            <i class="fas fa-percentage text-blue-700 text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Overall Statistics Chart -->
                <div class="bg-white shadow-lg rounded-xl p-6 border border-gray-200">
                    <div class="h-80">
                        <canvas ref="overallChartCanvas"></canvas>
                    </div>
                </div>

                <!-- Daily Trend Chart -->
                <div class="bg-white shadow-lg rounded-xl p-6 border border-gray-200">
                    <div class="h-80">
                        <canvas ref="dailyTrendChartCanvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Departments Comparison Chart -->
            <div class="bg-white shadow-lg rounded-xl p-6 border border-gray-200">
                <div class="h-96">
                    <canvas ref="departmentsChartCanvas"></canvas>
                </div>
            </div>

            <!-- Departments List -->
            <div class="bg-white shadow-lg rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-800">الأقسام</h3>
                    <p class="text-sm text-gray-600 mt-1">انقر على قسم لعرض التفاصيل</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="dept in departmentsStatistics"
                            :key="dept.department.id"
                            @click="router.visit(route('hr.attendance-reports.department', dept.department.id))"
                            class="bg-gradient-to-br from-indigo-50 to-purple-50 border-2 border-indigo-200 rounded-xl p-6 cursor-pointer hover:shadow-xl transition-all hover:scale-105"
                        >
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-lg font-bold text-gray-800">{{ dept.department.name }}</h4>
                                <i class="fas fa-arrow-left text-indigo-600"></i>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">الموظفين:</span>
                                    <span class="font-semibold">{{ dept.personnel.employees }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">المعلمين:</span>
                                    <span class="font-semibold">{{ dept.personnel.teachers }}</span>
                                </div>
                                <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                                    <span class="text-sm text-gray-600">نسبة الحضور:</span>
                                    <span class="font-bold text-indigo-600">{{ dept.statistics.attendance_rate }}%</span>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <div class="bg-green-100 rounded-lg p-2 text-center">
                                    <p class="text-xs text-green-800">حضور</p>
                                    <p class="text-lg font-bold text-green-900">{{ dept.statistics.present }}</p>
                                </div>
                                <div class="bg-red-100 rounded-lg p-2 text-center">
                                    <p class="text-xs text-red-800">غياب</p>
                                    <p class="text-lg font-bold text-red-900">{{ dept.statistics.absent }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </HrLayout>
</template>
