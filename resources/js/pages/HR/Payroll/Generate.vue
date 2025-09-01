<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - i);
const months = [
    { value: 1, name: 'يناير' }, { value: 2, name: 'فبراير' }, { value: 3, name: 'مارس' },
    { value: 4, name: 'أبريل' }, { value: 5, name: 'مايو' }, { value: 6, name: 'يونيو' },
    { value: 7, name: 'يوليو' }, { value: 8, name: 'أغسطس' }, { value: 9, name: 'سبتمبر' },
    { value: 10, name: 'أكتوبر' }, { value: 11, name: 'نوفمبر' }, { value: 12, name: 'ديسمبر' }
];

const form = useForm({
    month: new Date().getMonth() + 1,
    year: currentYear,
});

const submit = () => {
    form.post(route('hr.payroll.store'));
};

const monthName = computed(() => {
    const month = months.find(m => m.value === form.month);
    return month ? month.name : '';
});

</script>

<template>
    <Head title="إنشاء رواتب شهر جديد" />

    <HrLayout>
        <template #header>
            إنشاء رواتب شهر جديد
        </template>

        <div class="max-w-xl mx-auto bg-white shadow-md rounded-lg p-8 mt-10">
            <h2 class="text-2xl font-bold mb-6 text-center">تحديد الشهر والسنة</h2>
            <div class="text-center mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700">
                <p class="font-bold">ملاحظة هامة</p>
                <p>سيقوم النظام بالبحث عن جميع الموظفين الذين لديهم عقود سارية في شهر <span class="font-bold">{{ monthName }} {{ form.year }}</span> وإنشاء قسائم الرواتب لهم. سيتم تخطي أي موظف تم إنشاء راتب له مسبقاً.</p>
            </div>
            <form @submit.prevent="submit">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="month" class="block mb-2 text-sm font-medium text-gray-900">الشهر</label>
                        <select id="month" v-model="form.month" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <option v-for="month in months" :key="month.value" :value="month.value">{{ month.name }}</option>
                        </select>
                        <!-- مكان عرض الخطأ -->
                        <div v-if="form.errors.month" class="text-sm text-red-600 mt-1">{{ form.errors.month }}</div>
                    </div>
                     <div>
                        <label for="year" class="block mb-2 text-sm font-medium text-gray-900">السنة</label>
                        <select id="year" v-model="form.year" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                        </select>
                         <!-- مكان عرض الخطأ -->
                        <div v-if="form.errors.year" class="text-sm text-red-600 mt-1">{{ form.errors.year }}</div>
                    </div>
                </div>

                <div class="flex items-center justify-center mt-8">
                     <button type="submit" class="w-full bg-indigo-600 text-white px-6 py-3 rounded-md hover:bg-indigo-700 text-lg font-bold" :disabled="form.processing">
                        <i class="fas fa-cogs mr-2"></i> بدء عملية إنشاء الرواتب
                    </button>
                </div>
            </form>
        </div>
    </HrLayout>
</template>
