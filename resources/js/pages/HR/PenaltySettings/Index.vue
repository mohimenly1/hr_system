<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    penaltyTypes: Array,
    evaluationCriteria: Array,
});

const showModal = ref(false);
const isEditing = ref(false);

const form = useForm({
    id: null,
    name: '',
    description: '',
    affects_evaluation: false,
    affects_salary: false,
    deduction_type: null,
    deduction_amount: null,
    is_active: true,
    criteria_deductions: [],
});

watch(() => form.affects_evaluation, (newValue) => {
    if (newValue && form.criteria_deductions.length === 0) {
        form.criteria_deductions = props.evaluationCriteria.map(c => ({ id: c.id, name: c.name, max_score: c.max_score, points: 0 }));
    }
});

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    showModal.value = true;
};

const openEditModal = (penaltyType) => {
    isEditing.value = true;
    form.id = penaltyType.id;
    form.name = penaltyType.name;
    form.description = penaltyType.description;
    form.affects_evaluation = penaltyType.affects_evaluation;
    form.affects_salary = penaltyType.affects_salary;
    form.deduction_type = penaltyType.deduction_type;
    form.deduction_amount = penaltyType.deduction_amount;
    form.is_active = penaltyType.is_active;
    
    form.criteria_deductions = props.evaluationCriteria.map(c => {
        const existing = penaltyType.criteria.find(pc => pc.id === c.id);
        return {
            id: c.id,
            name: c.name,
            max_score: c.max_score,
            points: existing ? existing.pivot.deduction_points : 0
        };
    });

    showModal.value = true;
};

const submitForm = () => {
    const action = isEditing.value
        ? form.put(route('hr.penalty-settings.update', form.id))
        : form.post(route('hr.penalty-settings.store'));
    
    action.then(() => {
        if (!form.hasErrors) {
            showModal.value = false;
        }
    });
};
</script>

<template>
    <Head title="إعدادات العقوبات" />
    <HrLayout>
        <template #header>
            إدارة أنواع العقوبات
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">أنواع العقوبات والجزاءات</h2>
                <button @click="openCreateModal" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 flex items-center">
                    <i class="fas fa-plus mr-2"></i> إضافة نوع عقوبة
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="py-3 px-4 text-right">اسم العقوبة</th>
                            <th class="py-3 px-4 text-center">التأثير</th>
                            <th class="py-3 px-4 text-center">الخصم</th>
                            <th class="py-3 px-4 text-center">الحالة</th>
                            <th class="py-3 px-4 text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="pt in penaltyTypes" :key="pt.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium">{{ pt.name }}</td>
                            <td class="py-3 px-4 text-center text-xs space-x-2">
                                <span v-if="pt.affects_evaluation" class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">تقييم</span>
                                <span v-if="pt.affects_salary" class="px-2 py-1 bg-red-100 text-red-800 rounded-full">راتب</span>
                            </td>
                            <td class="py-3 px-4 text-center font-mono">{{ pt.affects_salary ? `${pt.deduction_amount} ${pt.deduction_type === 'fixed' ? 'مبلغ ثابت' : '%'}` : '-' }}</td>
                            <td class="py-3 px-4 text-center"><span class="px-3 py-1 text-xs font-semibold rounded-full" :class="pt.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">{{ pt.is_active ? 'نشط' : 'غير نشط' }}</span></td>
                            <td class="py-3 px-4 text-center"><button @click="openEditModal(pt)" class="text-blue-600 hover:text-blue-800">تعديل</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex justify-center items-center p-4">
             <div class="bg-white rounded-lg shadow-xl p-0 w-full max-w-2xl">
                 <form @submit.prevent="submitForm">
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">{{ isEditing ? 'تعديل نوع العقوبة' : 'إضافة نوع عقوبة' }}</h3>
                        <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
                            <div><label class="block text-sm font-medium text-gray-800">اسم العقوبة</label><input style="border: black solid 1px !important;" type="text" v-model="form.name" class="mt-1 block w-full rounded-md text-gray-800" required></div>
                            <div><label class="block text-sm font-medium text-gray-800">الوصف (اختياري)</label><textarea style="border: black solid 1px !important;" v-model="form.description" rows="2" class="mt-1 block w-full rounded-md text-gray-800"></textarea></div>
                            <div class="flex items-center space-x-4 rtl:space-x-reverse pt-2">
                                <label class="flex items-center"><input type="checkbox" v-model="form.affects_salary" class="rounded"><span class="ml-2 rtl:mr-2 text-gray-800">تؤثر على الراتب</span></label>
                                <label class="flex items-center"><input type="checkbox" v-model="form.affects_evaluation" class="rounded"><span class="ml-2 rtl:mr-2 text-gray-800">تؤثر على التقييم</span></label>
                                <label class="flex items-center"><input type="checkbox" v-model="form.is_active" class="rounded"><span class="ml-2 rtl:mr-2 text-gray-800">نشطة</span></label>
                            </div>
                            <div  v-if="form.affects_salary" class="grid grid-cols-2 gap-4 pt-2 border-t mt-4">
                                <div><label class="block text-sm font-medium text-gray-800">نوع الخصم</label><select style="border: black solid 1px !important;" v-model="form.deduction_type" class="mt-1 block w-full rounded-md text-gray-800"><option style="border: black solid 1px !important;" value="fixed">مبلغ ثابت</option><option value="percentage">نسبة مئوية</option></select></div>
                                <div><label class="block text-sm font-medium text-gray-800">قيمة الخصم</label><input style="border: black solid 1px !important;" type="number" step="0.01" v-model="form.deduction_amount" class="mt-1 block w-full rounded-md text-gray-800"></div>
                            </div>
                            <div v-if="form.affects_evaluation" class="pt-4 border-t mt-4">
                                <h4 class="font-semibold text-gray-800 mb-2">تأثير العقوبة على بنود التقييم</h4>
                                <div class="space-y-2">
                                    <div v-for="criterion in form.criteria_deductions" :key="criterion.id" class="grid grid-cols-3 items-center gap-4">
                                        <label class="col-span-1 text-black">{{ criterion.name }}</label>
                                        <div class="col-span-2 flex items-center space-x-2 rtl:space-x-reverse text-gray-800">
                                            <input type="range" v-model="criterion.points" :max="criterion.max_score" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                            <span class="font-mono text-indigo-600 w-16 text-center">{{ criterion.points }} / {{ criterion.max_score }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 bg-gray-50 flex justify-end space-x-2 rtl:space-x-reverse border-t">
                        <button type="button" @click="showModal = false" class="bg-gray-200 px-4 py-2 rounded-md">إلغاء</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md" :disabled="form.processing">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </HrLayout>
</template>
