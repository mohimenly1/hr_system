<script setup>
import HrLayout from '../layouts/HrLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, computed, watch } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
    stats: Array,
    employeesPerDepartment: Array,
    attendanceLastWeek: Array,
    managedDepartments: Array,
    activeDepartment: Object,
    userRole: String,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// --- NEW State for Custom Department Filter ---
const isDeptFilterOpen = ref(false);
const selectedDepartment = ref(props.activeDepartment ? props.activeDepartment.id : null);

const selectDepartment = (deptId) => {
    selectedDepartment.value = deptId;
    isDeptFilterOpen.value = false; // Close dropdown on selection
};

watch(selectedDepartment, (newDeptId) => {
    if (newDeptId && newDeptId !== props.activeDepartment?.id) {
        router.get(route('dashboard'), { department_id: newDeptId }, { preserveState: true, replace: true });
    }
});

// --- Live Clock & Date Logic ---
const currentTime = ref('');
const currentDate = ref('');
let intervalId = null;

const updateDateTime = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('ar-LY', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    currentDate.value = now.toLocaleDateString('ar-LY', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
};

const welcomeMessage = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'صباح الخير';
    if (hour < 18) return 'مساء الخير';
    return 'مساء الخير';
});

// --- Chart Logic ---
const departmentChartCanvas = ref(null);
const attendanceChartCanvas = ref(null);
let attendanceChartInstance = null;

const updateAttendanceChart = () => {
    if (attendanceChartInstance) {
        attendanceChartInstance.destroy();
    }
    if (attendanceChartCanvas.value && props.attendanceLastWeek) {
        attendanceChartInstance = new Chart(attendanceChartCanvas.value, {
            type: 'bar',
            data: {
                labels: props.attendanceLastWeek.map(a => a.date),
                datasets: [
                    { label: 'حاضر', data: props.attendanceLastWeek.map(a => a.present), backgroundColor: 'rgba(16, 185, 129, 0.5)', borderColor: 'rgba(16, 185, 129, 1)', borderWidth: 1 },
                    { label: 'غائب', data: props.attendanceLastWeek.map(a => a.absent), backgroundColor: 'rgba(239, 68, 68, 0.5)', borderColor: 'rgba(239, 68, 68, 1)', borderWidth: 1 }
                ]
            },
            options: {
                 responsive: true, maintainAspectRatio: false,
                 plugins: { title: { display: true, text: 'الحضور والغياب في آخر 7 أيام' + (props.activeDepartment ? ` - قسم ${props.activeDepartment.name}` : '') } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
}

onMounted(() => {
    updateDateTime();
    intervalId = setInterval(updateDateTime, 1000);

    // Employees Per Department Chart
    if (departmentChartCanvas.value && props.userRole === 'admin') {
        new Chart(departmentChartCanvas.value, {
            type: 'doughnut', data: { labels: props.employeesPerDepartment.map(d => d.name), datasets: [{ data: props.employeesPerDepartment.map(d => d.count), backgroundColor: ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#3B82F6'], hoverOffset: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'توزيع الموظفين على الأقسام' } } }
        });
    }

    updateAttendanceChart();
});

watch(() => props.attendanceLastWeek, () => {
    updateAttendanceChart();
});

onUnmounted(() => {
    clearInterval(intervalId);
});
</script>

<template>
    <Head title="لوحة التحكم" />
    <HrLayout>
        <template #header>
            <span v-if="userRole === 'department-manager'  && activeDepartment">
                لوحة تحكم - قسم {{ activeDepartment.name }}
            </span>
            <span v-else>لوحة التحكم الرئيسية</span>
        </template>
        
        <!-- NEW App Bar -->
        <div class="bg-white shadow-md rounded-lg p-4 mb-8 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">{{ welcomeMessage }}، {{ user.name }}!</h2>
                <p class="text-gray-500">دورك الحالي: <span class="font-semibold text-indigo-600">{{ user.roles[0] }}</span></p>
            </div>
            
             <!-- NEW Custom Department Switcher -->
            <div v-if="userRole == 'department-manager' && managedDepartments.length > 1" class="relative">
                <button @click="isDeptFilterOpen = !isDeptFilterOpen" class="flex items-center space-x-2 rtl:space-x-reverse bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 font-semibold py-2 px-4 rounded-lg transition shadow-sm">
                    <i class="fas fa-building text-indigo-500"></i>
                    <span>عرض قسم: {{ activeDepartment.name }}</span>
                    <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': isDeptFilterOpen }"></i>
                </button>
                <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                    <div v-if="isDeptFilterOpen" @click.away="isDeptFilterOpen = false" class="absolute top-full right-0 mt-2 w-64 bg-white rounded-lg shadow-xl border z-10">
                        <ul>
                            <li v-for="dept in managedDepartments" :key="dept.id">
                                <button @click="selectDepartment(dept.id)" class="w-full text-right px-4 py-2 text-sm text-gray-800 hover:bg-gray-100 flex items-center justify-between">
                                    <span>{{ dept.name }}</span>
                                    <i v-if="dept.id === activeDepartment.id" class="fas fa-check text-indigo-600"></i>
                                </button>
                            </li>
                        </ul>
                    </div>
                </transition>
            </div>

            <div class="text-left rtl:text-right">
                <p class="text-2xl font-mono font-bold text-gray-800">{{ currentTime }}</p>
                <p class="text-sm text-gray-500">{{ currentDate }}</p>
            </div>
        </div>


        <!-- Stats Cards -->
        <div v-if="stats.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div v-for="stat in stats" :key="stat.name" class="bg-white overflow-hidden shadow-lg rounded-xl p-6 flex items-center space-x-4 rtl:space-x-reverse">
                <div class="bg-indigo-100 p-4 rounded-full">
                     <i :class="stat.icon" class="text-3xl text-indigo-600"></i>
                </div>
                <div>
                    <p class="text-3xl font-bold text-gray-900">{{ stat.value }}</p>
                    <h3 class="text-md font-medium text-gray-500">{{ stat.name }}</h3>
                </div>
            </div>
        </div>
        <div v-else class="text-center bg-white p-8 rounded-lg shadow-md mb-8">
            <h3 class="text-xl font-bold text-gray-800">مرحباً بك</h3>
            <p class="text-gray-500 mt-2">لا توجد بيانات لعرضها حالياً. قد تكون غير معين كمدير لأي قسم.</p>
        </div>


        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <!-- Admin View -->
            <template v-if="userRole === 'admin'">
                <div class="lg:col-span-3 bg-white shadow-lg rounded-xl p-6">
                    <div class="h-96"><canvas ref="attendanceChartCanvas"></canvas></div>
                </div>
                <div class="lg:col-span-2 bg-white shadow-lg rounded-xl p-6">
                    <div class="h-96"><canvas ref="departmentChartCanvas"></canvas></div>
                </div>
            </template>
            <!-- Department Manager View -->
            <template v-if="userRole === 'department-manager'">
                 <div class="lg:col-span-5 bg-white shadow-lg rounded-xl p-6">
                    <div class="h-96"><canvas ref="attendanceChartCanvas"></canvas></div>
                </div>
            </template>
        </div>
    </HrLayout>
</template>

