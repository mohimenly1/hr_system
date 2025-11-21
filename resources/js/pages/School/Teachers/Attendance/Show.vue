<script setup lang="ts">
import HrLayout from '../../../../layouts/HrLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { format } from 'date-fns';
import { ar } from 'date-fns/locale';

const props = defineProps({
    teacher: Object,
    attendances: Object,
    availableMonths: Array,
    filterType: String,
    selectedMonth: String,
    startDate: String,
    endDate: String,
    statistics: Object,
    absentDaysList: Array,
    filters: Object,
});

const filterType = ref(props.filterType || 'month');
const selectedMonth = ref(props.selectedMonth || '');
const startDate = ref(props.startDate || '');
const endDate = ref(props.endDate || '');

const getStatusInfo = (status) => {
    const statuses = {
        present: { text: 'حاضر', class: 'bg-green-100 text-green-800' },
        absent: { text: 'غائب', class: 'bg-red-100 text-red-800' },
        late: { text: 'متأخر', class: 'bg-yellow-100 text-yellow-800' },
        on_leave: { text: 'إجازة', class: 'bg-blue-100 text-blue-800' },
        holiday: { text: 'عطلة', class: 'bg-indigo-100 text-indigo-800' },
    };
    return statuses[status] || { text: status, class: 'bg-gray-100 text-gray-800' };
};

const formatDate = (dateString) => {
    return format(new Date(dateString), 'EEEE, d MMMM yyyy', { locale: ar });
};

const isWeekend = (dateString) => {
    const date = new Date(dateString);
    const day = date.getDay();
    return day === 5 || day === 6; // 5 = Friday, 6 = Saturday
};

const getDayName = (dateString) => {
    const date = new Date(dateString);
    const dayNames = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    return dayNames[date.getDay()];
};

const filterData = () => {
    const params = {
        filter_type: filterType.value,
    };

    if (filterType.value === 'month') {
        params.month = selectedMonth.value;
    } else {
        params.start_date = startDate.value;
        params.end_date = endDate.value;
    }

    router.get(route('school.teachers.attendance.show', props.teacher.id), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const exportReport = () => {
    let url = route('school.teachers.attendance.export', props.teacher.id);
    url += `?filter_type=${filterType.value}`;
    if (filterType.value === 'month') {
        url += `&month=${selectedMonth.value}`;
    } else {
        url += `&start_date=${startDate.value}&end_date=${endDate.value}`;
    }
    window.open(url, '_blank');
};

const exportAbsentDays = () => {
    let url = route('school.teachers.attendance.export-absent', props.teacher.id);
    url += `?filter_type=${filterType.value}`;
    if (filterType.value === 'month') {
        url += `&month=${selectedMonth.value}`;
    } else {
        url += `&start_date=${startDate.value}&end_date=${endDate.value}`;
    }
    window.open(url, '_blank');
};
</script>

<template>
    <Head :title="`سجل حضور - ${teacher.user.name}`" />

    <HrLayout>
        <template #header>
            سجل الحضور
        </template>

        <div class="space-y-6">
            <!-- Header Section -->
            <div class="bg-white shadow-md rounded-lg p-6">
                <div class="flex items-center justify-between mb-6 pb-4 border-b">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">
                            {{ teacher.user.name }}
                        </h2>
                        <p class="text-gray-500">{{ teacher.specialization }} - {{ teacher.department?.name }}</p>
                    </div>
                    <Link :href="route('school.teachers.index')" class="text-indigo-600 hover:text-indigo-800 font-medium">
                        <i class="fas fa-arrow-left ml-2"></i>
                        العودة لقائمة المعلمين
                    </Link>
                </div>

                <!-- Filter Section -->
                <div class="mb-6">
                    <div class="flex items-center space-x-4 rtl:space-x-reverse mb-4">
                        <label class="text-sm font-medium text-gray-700">نوع الفلترة:</label>
                        <select
                            v-model="filterType"
                            @change="filterData"
                            class="border border-gray-300 rounded-md px-4 py-2 text-gray-800 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="month">حسب الشهر</option>
                            <option value="date_range">حسب نطاق التاريخ</option>
                        </select>
                    </div>
                    <div v-if="filterType === 'month'" class="flex items-center justify-between">
                        <div class="flex items-center space-x-4 rtl:space-x-reverse">
                            <label class="text-sm font-medium text-gray-700">فلترة حسب الشهر:</label>
                            <select
                                v-model="selectedMonth"
                                @change="filterData"
                                class="border border-gray-300 rounded-md px-4 py-2 text-gray-800 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">اختر الشهر</option>
                                <option v-for="month in availableMonths" :key="month.value" :value="month.value">
                                    {{ month.label }}
                                </option>
                            </select>
                        </div>
                        <button
                            @click="exportReport"
                            class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 flex items-center"
                            :disabled="!selectedMonth"
                        >
                            <i class="fas fa-file-excel ml-2"></i>
                            تصدير تقرير Excel
                        </button>
                    </div>
                    <div v-else class="flex items-center justify-between">
                        <div class="flex items-center space-x-4 rtl:space-x-reverse">
                            <label class="text-sm font-medium text-gray-700">من تاريخ:</label>
                            <input
                                type="date"
                                v-model="startDate"
                                @change="filterData"
                                class="border border-gray-300 rounded-md px-4 py-2 text-gray-800 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <label class="text-sm font-medium text-gray-700">إلى تاريخ:</label>
                            <input
                                type="date"
                                v-model="endDate"
                                @change="filterData"
                                class="border border-gray-300 rounded-md px-4 py-2 text-gray-800 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <button
                            @click="exportReport"
                            class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 flex items-center"
                            :disabled="!startDate || !endDate"
                        >
                            <i class="fas fa-file-excel ml-2"></i>
                            تصدير تقرير Excel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div v-if="statistics" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Present Days Card -->
                <div class="bg-white shadow-md rounded-lg p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">أيام الحضور</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ statistics.actual_present_days }}</p>
                            <p class="text-xs text-gray-500 mt-1">من {{ statistics.working_days }} يوم عمل</p>
                        </div>
                        <div class="bg-green-100 rounded-full p-4">
                            <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Absent Days Card -->
                <div class="bg-white shadow-md rounded-lg p-6 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">أيام الغياب</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ statistics.actual_absent_days }}</p>
                            <p class="text-xs text-gray-500 mt-1">استثناء عطلة نهاية الأسبوع</p>
                        </div>
                        <div class="bg-red-100 rounded-full p-4">
                            <i class="fas fa-times-circle text-red-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Attendance Rate Card -->
                <div class="bg-white shadow-md rounded-lg p-6 border-l-4 border-indigo-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">نسبة الحضور</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ statistics.attendance_rate }}%</p>
                            <p class="text-xs text-gray-500 mt-1">من إجمالي أيام العمل</p>
                        </div>
                        <div class="bg-indigo-100 rounded-full p-4">
                            <i class="fas fa-chart-line text-indigo-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Weekend Days Card -->
                <div class="bg-white shadow-md rounded-lg p-6 border-l-4 border-gray-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">عطلة نهاية الأسبوع</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2">{{ statistics.weekend_days }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ statistics.fridays }} جمعة + {{ statistics.saturdays }} سبت</p>
                        </div>
                        <div class="bg-gray-100 rounded-full p-4">
                            <i class="fas fa-calendar-week text-gray-600 text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Absent Days Section -->
            <div v-if="absentDaysList && absentDaysList.length > 0" class="bg-white shadow-md rounded-lg p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3 rtl:space-x-reverse">
                        <div class="bg-red-100 rounded-full p-3">
                            <i class="fas fa-calendar-times text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">أيام الغياب</h3>
                            <p class="text-sm text-gray-500">إجمالي {{ absentDaysList.length }} يوم غياب (استثناء عطلة نهاية الأسبوع)</p>
                        </div>
                    </div>
                    <button
                        @click="exportAbsentDays"
                        class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 flex items-center"
                        :disabled="!selectedMonth"
                    >
                        <i class="fas fa-file-excel ml-2"></i>
                        تصدير أيام الغياب
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 mt-4">
                    <div
                        v-for="(day, index) in absentDaysList"
                        :key="index"
                        class="bg-red-50 border border-red-200 rounded-lg p-4 hover:bg-red-100 transition-colors"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-gray-600">{{ day.day_name }}</div>
                                <div class="text-lg font-bold text-red-700 mt-1">{{ day.day_number }}</div>
                            </div>
                            <div class="bg-red-200 rounded-full p-2">
                                <i class="fas fa-times-circle text-red-600"></i>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">{{ day.date_formatted }}</div>
                    </div>
                </div>
            </div>

            <!-- No Absent Days Message -->
            <div v-else-if="statistics && statistics.actual_absent_days === 0" class="bg-green-50 border-l-4 border-green-500 rounded-lg p-6">
                <div class="flex items-center space-x-3 rtl:space-x-reverse">
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">لا توجد أيام غياب</h3>
                        <p class="text-sm text-gray-600">جميع أيام العمل في هذا الشهر كانت أيام حضور</p>
                    </div>
                </div>
            </div>

            <!-- Chart Section -->
            <div v-if="statistics" class="bg-white shadow-md rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">إحصائيات الحضور</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">حضور</span>
                            <span class="text-2xl font-bold text-green-700">{{ statistics.actual_present_days }}</span>
                        </div>
                        <div class="mt-2 bg-green-200 rounded-full h-2">
                            <div
                                class="bg-green-600 h-2 rounded-full"
                                :style="{ width: statistics.working_days > 0 ? (statistics.actual_present_days / statistics.working_days * 100) + '%' : '0%' }"
                            ></div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">غياب</span>
                            <span class="text-2xl font-bold text-red-700">{{ statistics.actual_absent_days }}</span>
                        </div>
                        <div class="mt-2 bg-red-200 rounded-full h-2">
                            <div
                                class="bg-red-600 h-2 rounded-full"
                                :style="{ width: statistics.working_days > 0 ? (statistics.actual_absent_days / statistics.working_days * 100) + '%' : '0%' }"
                            ></div>
                        </div>
                    </div>
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">إجازة</span>
                            <span class="text-2xl font-bold text-blue-700">{{ statistics.leave_days }}</span>
                        </div>
                        <div class="mt-2 bg-blue-200 rounded-full h-2">
                            <div
                                class="bg-blue-600 h-2 rounded-full"
                                :style="{ width: statistics.working_days > 0 ? (statistics.leave_days / statistics.working_days * 100) + '%' : '0%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Records Table -->
            <div class="bg-white shadow-md rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">سجلات الحضور التفصيلية</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-right py-3 px-4 font-semibold text-sm text-gray-600">التاريخ</th>
                                <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600">وقت الدخول</th>
                                <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600">وقت الخروج</th>
                                <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600">الحالة</th>
                                <th class="text-right py-3 px-4 font-semibold text-sm text-gray-600">ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <tr
                                v-for="record in attendances.data"
                                :key="record.id"
                                class="border-b hover:bg-gray-50"
                                :class="{
                                    'bg-blue-50': isWeekend(record.attendance_date),
                                }"
                            >
                                <td class="py-3 px-4 font-medium">
                                    <div>{{ formatDate(record.attendance_date) }}</div>
                                    <div v-if="isWeekend(record.attendance_date)" class="text-xs text-blue-600 font-semibold mt-1">
                                        <i class="fas fa-calendar-day ml-1"></i>
                                        {{ getDayName(record.attendance_date) === 'الجمعة' ? 'يوم جمعة' : 'يوم سبت' }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center font-mono">{{ record.check_in_time || '---' }}</td>
                                <td class="py-3 px-4 text-center font-mono">{{ record.check_out_time || '---' }}</td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-3 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusInfo(record.status).class">
                                        {{ getStatusInfo(record.status).text }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm">{{ record.notes || 'لا يوجد' }}</td>
                            </tr>
                            <tr v-if="attendances.data.length === 0">
                                <td colspan="5" class="text-center py-8 text-gray-500">لا يوجد سجلات حضور لعرضها.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="attendances.data.length > 0" class="mt-6 flex justify-between items-center">
                    <div class="text-sm text-gray-700">
                        عرض {{ attendances.from }} إلى {{ attendances.to }} من {{ attendances.total }} نتيجة
                    </div>
                    <div class="flex">
                        <a
                            v-for="(link, index) in attendances.links"
                            :key="index"
                            :href="link.url || '#'"
                            class="px-3 py-2 text-sm leading-4 rounded-md"
                            :class="{
                                'bg-indigo-600 text-white': link.active,
                                'text-gray-700 hover:bg-gray-200': !link.active && link.url,
                                'text-gray-400 cursor-not-allowed': !link.url
                            }"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </HrLayout>
</template>
