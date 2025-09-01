<script setup>
import HrLayout from '../layouts/HrLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    stats: Array,
    employeesPerDepartment: Array,
    attendanceLastWeek: Array,
});

const departmentChartCanvas = ref(null);
const attendanceChartCanvas = ref(null);

onMounted(() => {
    // --- Employees Per Department Chart (Doughnut) ---
    if (departmentChartCanvas.value) {
        new Chart(departmentChartCanvas.value, {
            type: 'doughnut',
            data: {
                labels: props.employeesPerDepartment.map(d => d.name),
                datasets: [{
                    label: 'الموظفين',
                    data: props.employeesPerDepartment.map(d => d.count),
                    backgroundColor: [
                        '#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#3B82F6',
                        '#8B5CF6', '#D946EF', '#EC4899'
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    title: {
                        display: true,
                        text: 'توزيع الموظفين على الأقسام'
                    }
                }
            }
        });
    }

    // --- Attendance Last Week Chart (Bar) ---
    if (attendanceChartCanvas.value) {
        new Chart(attendanceChartCanvas.value, {
            type: 'bar',
            data: {
                labels: props.attendanceLastWeek.map(a => a.date),
                datasets: [
                    {
                        label: 'حاضر',
                        data: props.attendanceLastWeek.map(a => a.present),
                        backgroundColor: 'rgba(16, 185, 129, 0.5)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'غائب',
                        data: props.attendanceLastWeek.map(a => a.absent),
                        backgroundColor: 'rgba(239, 68, 68, 0.5)',
                        borderColor: 'rgba(239, 68, 68, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                 responsive: true,
                 maintainAspectRatio: false,
                 plugins: {
                    title: {
                        display: true,
                        text: 'الحضور والغياب في آخر 7 أيام'
                    }
                 },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
</script>

<template>
    <Head title="لوحة التحكم" />

    <HrLayout>
        <template #header>
            لوحة التحكم الرئيسية
        </template>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div v-for="stat in stats" :key="stat.name" class="bg-white overflow-hidden shadow-lg rounded-xl p-6 flex items-center space-x-4 space-x-reverse">
                <div class="bg-indigo-100 p-4 rounded-full">
                     <i :class="stat.icon" class="text-3xl text-indigo-600"></i>
                </div>
                <div>
                    <p class="text-3xl font-bold text-gray-900">{{ stat.value }}</p>
                    <h3 class="text-md font-medium text-gray-500">{{ stat.name }}</h3>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <div class="lg:col-span-3 bg-white shadow-lg rounded-xl p-6">
                 <div class="h-96">
                    <canvas ref="attendanceChartCanvas"></canvas>
                </div>
            </div>
            <div class="lg:col-span-2 bg-white shadow-lg rounded-xl p-6">
                <div class="h-96">
                     <canvas ref="departmentChartCanvas"></canvas>
                </div>
            </div>
        </div>
    </HrLayout>
</template>

