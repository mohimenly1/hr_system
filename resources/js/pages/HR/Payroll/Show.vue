<script setup lang="ts">
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    payslip: Object,
    schoolName: {
        type: String,
        default: 'Caledonian International School'
    },
    schoolLogo: {
        type: String,
        default: '/images/logo-school-one.png'
    },
    schoolAddress: {
        type: String,
        default: ''
    }
});

const getMonthName = (monthNumber) => {
    const date = new Date();
    date.setMonth(monthNumber - 1);
    return date.toLocaleString('ar', { month: 'long' });
};

const earnings = props.payslip.items.filter(item => item.type === 'earning');
const deductions = props.payslip.items.filter(item => item.type === 'deduction');

// Get full name
const getFullName = () => {
    const user = props.payslip.employee?.user || props.payslip.teacher?.user;
    const person = props.payslip.employee || props.payslip.teacher;
    if (user && person) {
        const firstName = user.name || '';
        const middleName = person.middle_name || '';
        const lastName = person.last_name || '';
        return trim(`${firstName} ${middleName} ${lastName}`) || firstName;
    }
    return user?.name || 'غير محدد';
};

const trim = (str) => {
    return str.replace(/\s+/g, ' ').trim();
};

// Get gender title
const getGenderTitle = () => {
    const person = props.payslip.employee || props.payslip.teacher;
    const gender = person?.gender;
    return gender === 'female' ? 'السيدة' : 'السيد';
};

const printCheck = () => {
    window.print();
};

</script>

<template>
    <Head :title="`قسيمة راتب - ${getFullName()}`" />

    <!-- Print-only Check Design (Hidden on screen, visible only when printing) -->
    <div class="print-only bg-white p-12" style="min-height: 100vh; max-width: 100%;">
        <!-- School Header -->
        <div class="text-center mb-8 border-b-2 border-gray-400 pb-6">
            <div class="flex justify-center items-center gap-4 mb-4">
                <img
                    :src="schoolLogo"
                    alt="شعار المدرسة"
                    class="h-24 w-24 object-contain"
                />
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ schoolName }}</h1>
                    <p class="text-gray-600 text-lg">{{ schoolAddress }}</p>
                </div>
            </div>
        </div>

        <!-- Check Content -->
        <div class="mb-12">
            <div class="text-right mb-8">
                <p class="text-xl leading-relaxed text-gray-800 mb-4">
                    <span class="font-bold">تم دفع إلى</span> {{ getGenderTitle() }}
                    <span class="font-bold text-lg">{{ getFullName() }}</span>
                </p>
                <p class="text-xl leading-relaxed text-gray-800">
                    <span class="font-bold">مبلغ</span>
                    <span class="font-bold text-2xl text-gray-900 mx-2">{{ parseFloat(payslip.net_salary).toFixed(2) }}</span>
                    <span class="font-bold">دينار</span>
                </p>
                <p class="text-xl leading-relaxed text-gray-800 mt-4">
                    <span class="font-bold">مقابل</span> مرتب شهر
                    <span class="font-bold">{{ getMonthName(payslip.month) }}</span>
                    لسنة
                    <span class="font-bold">{{ payslip.year }}</span>
                </p>
            </div>

            <!-- Amount in Words -->
            <div class="border-t-2 border-gray-300 pt-4 mt-8">
                <p class="text-lg text-gray-700 text-right">
                    <span class="font-bold">مبلغ</span>
                    <span class="font-bold text-xl">{{ parseFloat(payslip.net_salary).toFixed(2) }}</span>
                    <span class="font-bold">دينار</span>
                    <span class="mx-4">|</span>
                    <span class="font-bold">صافي الراتب</span>
                </p>
            </div>
        </div>

        <!-- Signatures Section -->
        <div class="mt-16 grid grid-cols-2 gap-8">
            <div class="text-center">
                <div class="border-t-2 border-gray-400 pt-2 mt-16">
                    <p class="text-gray-700 font-semibold">توقيع المحاسب</p>
                </div>
            </div>
            <div class="text-center">
                <div class="border-t-2 border-gray-400 pt-2 mt-16">
                    <p class="text-gray-700 font-semibold">توقيع المستلم</p>
                    <p class="text-sm text-gray-500 mt-1">{{ getFullName() }}</p>
                </div>
            </div>
        </div>

        <!-- Date -->
        <div class="mt-8 text-left">
            <p class="text-gray-600">
                <span class="font-bold">تاريخ الإصدار:</span>
                {{ new Date(payslip.issue_date).toLocaleDateString('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' }) }}
            </p>
        </div>
    </div>

    <HrLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <span>تفاصيل قسيمة الراتب</span>
                <button
                    @click="printCheck"
                    class="no-print bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    طباعة شيك
                </button>
            </div>
        </template>

        <div class="no-print max-w-4xl mx-auto bg-white shadow-lg rounded-lg p-8">
            <!-- Header -->
            <div class="flex justify-between items-center border-b pb-4 mb-6">
                <div>
                    <h2 class="text-3xl font-bold text-gray-800">قسيمة راتب</h2>
                    <p class="text-gray-500">لشهر {{ getMonthName(payslip.month) }} {{ payslip.year }}</p>
                </div>
                <div class="text-right">
                    <div class="flex items-center justify-end gap-3">
                        <img
                            :src="schoolLogo"
                            alt="شعار المدرسة"
                            class="h-16 w-16 object-contain"
                        />
                        <div>
                            <p class="text-lg font-semibold text-gray-800">{{ schoolName }}</p>
                            <p class="text-gray-600">{{ schoolAddress }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee Details -->
            <div class="grid grid-cols-2 gap-6 mb-8">
                <div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">بيانات الموظف</h3>
                    <p class="text-gray-800"><span class="font-bold text-gray-600">الاسم:</span> {{ payslip.employee?.user?.name || payslip.teacher?.user?.name }}</p>
                    <p class="text-gray-800"><span class="font-bold text-gray-600">رقم الموظف:</span> {{ payslip.employee?.employee_id || payslip.teacher?.id }}</p>
                    <p class="text-gray-800"><span class="font-bold text-gray-600">القسم:</span> {{ payslip.employee?.department?.name || payslip.teacher?.department?.name }}</p>
                    <p class="text-gray-800"><span class="font-bold text-gray-600">المسمى الوظيفي:</span> {{ payslip.contract?.job_title || 'غير محدد' }}</p>
                </div>
                <div class="text-right">
                     <h3 class="text-lg font-semibold text-gray-700 mb-2">تفاصيل الدفع</h3>
                     <p class="text-gray-800"><span class="font-bold text-gray-600">تاريخ الإصدار:</span> {{ new Date(payslip.issue_date).toLocaleDateString('ar-EG') }}</p>
                     <p class="text-gray-800"><span class="font-bold text-gray-600">حالة الدفع:</span>
                        <span class="font-bold" :class="{ 'text-green-600': payslip.status === 'paid', 'text-yellow-600': payslip.status === 'pending'}">
                            {{ payslip.status === 'paid' ? 'مدفوع' : 'قيد الدفع' }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Salary Details -->
            <div class="grid grid-cols-2 gap-8 mb-8">
                <!-- Earnings -->
                <div>
                    <h4 class="text-xl font-bold text-green-700 bg-green-100 p-2 rounded-t-lg">الإيرادات</h4>
                    <table class="min-w-full">
                        <tbody class="text-gray-800">
                            <tr v-for="item in earnings" :key="item.id" class="border-b">
                                <td class="py-2 px-4">{{ item.description }}</td>
                                <td class="py-2 px-4 text-left font-mono">{{ parseFloat(item.amount).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 text-gray-900">
                            <tr>
                                <td class="py-2 px-4 font-bold">إجمالي الإيرادات</td>
                                <td class="py-2 px-4 text-left font-bold font-mono">{{ parseFloat(payslip.gross_salary).toFixed(2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Deductions -->
                <div>
                    <h4 class="text-xl font-bold text-red-700 bg-red-100 p-2 rounded-t-lg">الخصومات</h4>
                     <table class="min-w-full">
                        <tbody class="text-gray-800">
                            <tr v-for="item in deductions" :key="item.id" class="border-b">
                                <td class="py-2 px-4">{{ item.description }}</td>
                                <td class="py-2 px-4 text-left font-mono">{{ parseFloat(item.amount).toFixed(2) }}</td>
                            </tr>
                            <tr v-if="deductions.length === 0">
                                <td class="py-2 px-4 text-gray-500" colspan="2">لا يوجد خصومات</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 text-gray-900">
                            <tr>
                                <td class="py-2 px-4 font-bold">إجمالي الخصومات</td>
                                <td class="py-2 px-4 text-left font-bold font-mono text-red-600">{{ parseFloat(payslip.total_deductions).toFixed(2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Net Salary -->
            <div class="bg-indigo-600 text-white p-4 rounded-lg flex justify-between items-center">
                <h3 class="text-2xl font-bold">صافي الراتب المستحق</h3>
                <p class="text-3xl font-bold font-mono">{{ parseFloat(payslip.net_salary).toFixed(2) }}</p>
            </div>
        </div>
    </HrLayout>
</template>

<style>
/* Hide check on screen */
.print-only {
    display: none !important;
}

/* Print styles */
@media print {
    /* Hide everything by default */
    body * {
        visibility: hidden;
    }

    /* Show only the check */
    .print-only,
    .print-only * {
        visibility: visible !important;
    }

    .print-only {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        display: block !important;
        background: white !important;
    }

    /* Hide all other elements */
    .no-print,
    header,
    aside,
    nav,
    .sidebar {
        display: none !important;
        visibility: hidden !important;
    }

    body {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    @page {
        size: A4;
        margin: 15mm;
    }
}
</style>
