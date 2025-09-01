<script setup>
import EmployeeLayout from '../../../layouts/EmployeeLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    payslip: Object,
});

const getMonthName = (monthNumber) => {
    const date = new Date();
    date.setMonth(monthNumber - 1);
    return date.toLocaleString('ar', { month: 'long' });
};

const earnings = props.payslip.items.filter(item => item.type === 'earning');
const deductions = props.payslip.items.filter(item => item.type === 'deduction');

</script>

<template>
    <Head :title="`تفاصيل راتب - ${getMonthName(payslip.month)} ${payslip.year}`" />

    <EmployeeLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                تفاصيل قسيمة راتب لشهر {{ getMonthName(payslip.month) }} {{ payslip.year }}
            </h2>
        </template>
        
        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                 <div class="bg-white shadow-lg rounded-lg p-8">
                    <!-- Header -->
                    <div class="flex justify-between items-center border-b pb-4 mb-6">
                        <div>
                            <h2 class="text-3xl font-bold text-gray-800">قسيمة راتب</h2>
                            <p class="text-gray-500">لشهر {{ getMonthName(payslip.month) }} {{ payslip.year }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-semibold text-gray-800">اسم الشركة</p>
                            <p class="text-gray-600">عنوان الشركة</p>
                        </div>
                    </div>

                    <!-- Employee Details -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">بيانات الموظف</h3>
                        <div class="bg-gray-50 p-4 rounded-lg grid grid-cols-2 gap-4">
                            <p class="text-gray-800"><span class="font-bold">الاسم:</span> {{ payslip.employee.user.name }}</p>
                            <p class="text-gray-800"><span class="font-bold">رقم الموظف:</span> {{ payslip.employee.employee_id }}</p>
                            <p class="text-gray-800"><span class="font-bold">القسم:</span> {{ payslip.employee.department.name }}</p>
                            <p class="text-gray-800"><span class="font-bold">المسمى الوظيفي:</span> {{ payslip.contract.job_title }}</p>
                        </div>
                    </div>
                    
                    <!-- Salary Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <!-- Earnings -->
                        <div>
                            <h4 class="text-xl font-bold text-green-700 bg-green-100 p-2 rounded-t-lg">الإيرادات</h4>
                            <table class="min-w-full">
                                <tbody>
                                    <tr v-for="item in earnings" :key="item.id" class="border-b">
                                        <td class="py-2 px-4 text-gray-800">{{ item.description }}</td>
                                        <td class="py-2 px-4 text-left font-mono text-gray-800">{{ parseFloat(item.amount).toFixed(2) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td class="py-2 px-4 font-bold text-gray-800">إجمالي الإيرادات</td>
                                        <td class="py-2 px-4 text-left font-bold font-mono text-gray-800">{{ parseFloat(payslip.gross_salary).toFixed(2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Deductions -->
                        <div>
                            <h4 class="text-xl font-bold text-red-700 bg-red-100 p-2 rounded-t-lg">الخصومات</h4>
                            <table class="min-w-full">
                                <tbody>
                                    <tr v-for="item in deductions" :key="item.id" class="border-b">
                                        <td class="py-2 px-4 text-gray-800">{{ item.description }}</td>
                                        <td class="py-2 px-4 text-left font-mono text-gray-800">{{ parseFloat(item.amount).toFixed(2) }}</td>
                                    </tr>
                                    <tr v-if="deductions.length === 0">
                                        <td class="py-2 px-4 text-gray-500" colspan="2">لا يوجد خصومات</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td class="py-2 px-4 font-bold text-gray-800">إجمالي الخصومات</td>
                                        <td class="py-2 px-4 text-left font-bold font-mono text-red-600">{{ parseFloat(payslip.total_deductions).toFixed(2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Net Salary -->
                    <div class="bg-indigo-600 text-white p-4 rounded-lg flex justify-between items-center mt-6">
                        <h3 class="text-2xl font-bold">صافي الراتب المستحق</h3>
                        <p class="text-3xl font-bold font-mono">{{ parseFloat(payslip.net_salary).toFixed(2) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </EmployeeLayout>
</template>
