<script setup>
import HrLayout from '../../../../layouts/HrLayout.vue';
import { Head, Link, useForm,router } from '@inertiajs/vue3';

defineProps({
    academic_years: Array,
});

const form = useForm({
    name: '',
    start_date: '',
    end_date: '',
});

const submit = () => {
    form.post(route('school.academic-years.store'), {
        onSuccess: () => form.reset(),
    });
};

// --- FIX: Use `router.put` instead of the deprecated `$inertia.put` ---
const setActive = (year) => {
    if (confirm(`هل أنت متأكد من تفعيل السنة الدراسية "${year.name}"؟ سيتم إلغاء تفعيل أي سنة أخرى.`)) {
        router.put(route('school.academic-years.set-active', year.id), {}, {
            preserveScroll: true,
        });
    }
};

// --- FIX: Use `router.delete` instead of the deprecated `$inertia.delete` ---
const deleteYear = (year) => {
    if (confirm(`هل أنت متأكد من حذف السنة الدراسية "${year.name}"؟`)) {
        router.delete(route('school.academic-years.destroy', year.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="إدارة السنوات الدراسية" />

    <HrLayout>
        <template #header>
            الإعدادات الأكاديمية / السنوات الدراسية
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Add New Year Form -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h3 class="text-xl font-bold mb-4">إضافة سنة دراسية جديدة</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label for="name" class="block mb-2 text-sm font-medium text-gray-900">اسم السنة (مثال: 2024-2025)</label>
                            <input type="text" v-model="form.name" id="name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div>
                            <label for="start_date" class="block mb-2 text-sm font-medium text-gray-900">تاريخ البدء</label>
                            <input type="date" v-model="form.start_date" id="start_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <div v-if="form.errors.start_date" class="text-sm text-red-600 mt-1">{{ form.errors.start_date }}</div>
                        </div>
                         <div>
                            <label for="end_date" class="block mb-2 text-sm font-medium text-gray-900">تاريخ الانتهاء</label>
                            <input type="date" v-model="form.end_date" id="end_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                            <div v-if="form.errors.end_date" class="text-sm text-red-600 mt-1">{{ form.errors.end_date }}</div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="form.processing">حفظ</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- List of Years -->
            <div class="lg:col-span-2">
                 <div class="bg-white shadow-md rounded-lg">
                    <div class="p-6 border-b">
                         <h3 class="text-xl font-bold">قائمة السنوات الدراسية</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-right py-3 px-4 font-semibold text-sm">الاسم</th>
                                    <th class="text-right py-3 px-4 font-semibold text-sm">تاريخ البدء</th>
                                    <th class="text-right py-3 px-4 font-semibold text-sm">تاريخ الانتهاء</th>
                                    <th class="text-right py-3 px-4 font-semibold text-sm">الحالة</th>
                                    <th class="text-right py-3 px-4 font-semibold text-sm">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700">
                                <tr v-for="year in academic_years" :key="year.id" class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4 font-medium">{{ year.name }}</td>
                                    <td class="py-3 px-4">{{ new Date(year.start_date).toLocaleDateString('ar-EG') }}</td>
                                    <td class="py-3 px-4">{{ new Date(year.end_date).toLocaleDateString('ar-EG') }}</td>
                                    <td class="py-3 px-4">
                                        <span v-if="year.is_active" class="px-2 py-1 text-xs font-semibold leading-5 rounded-full bg-green-100 text-green-800">نشط</span>
                                        <span v-else class="px-2 py-1 text-xs font-semibold leading-5 rounded-full bg-gray-100 text-gray-800">غير نشط</span>
                                    </td>
                                    <td class="py-3 px-4 space-x-2 rtl:space-x-reverse whitespace-nowrap">
                                        <button @click="setActive(year)" v-if="!year.is_active" class="text-green-600 hover:text-green-900 font-medium">تفعيل</button>
                                        <button @click="deleteYear(year)" class="text-red-600 hover:text-red-900 font-medium">حذف</button>
                                    </td>
                                </tr>
                                <tr v-if="academic_years.length === 0">
                                    <td colspan="5" class="text-center py-4">لم يتم إضافة أي سنوات دراسية بعد.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </HrLayout>
</template>
