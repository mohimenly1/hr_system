<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    deductionRules: Array,
    penaltyTypes: Array,
});

const showModal = ref(false);
const isEditing = ref(false);

const form = useForm({
    id: null,
    name: '',
    description: '',
    penalty_type_id: null,
    deduction_type: null, // اختياري - سيستخدم القيمة من PenaltyType
    deduction_amount: null, // اختياري - سيستخدم القيمة من PenaltyType
    deduction_days: null, // عدد الأيام للخصم (لنوع daily_salary)
    deduction_hours: null, // عدد الساعات للخصم (لنوع hourly_salary)
    min_deduction: null,
    max_deduction: null,
    conditions: {
        event_type: null, // نوع الحدث: 'late', 'absent_without_permission', 'absent', 'early_leave'
        occurrence_type: null, // نوع التكرار: 'consecutive', 'non_consecutive', 'total'
        occurrence_count: null, // عدد المرات (مثلاً: 3 أيام)
        time_period: null, // الفترة الزمنية: 'daily', 'weekly', 'monthly', 'yearly'
        min_minutes_late: null, // الحد الأدنى لدقائق التأخير
        max_minutes_late: null, // الحد الأقصى لدقائق التأخير
        requires_permission: null, // يتطلب إذن: true/false/null
    },
    priority: 0,
    is_active: true,
});

// Computed property للحصول على نوع العقوبة المحدد
const selectedPenaltyType = computed(() => {
    if (!form.penalty_type_id) return null;
    return props.penaltyTypes.find(pt => pt.id === form.penalty_type_id);
});

const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    form.deduction_type = null; // استخدام القيمة الافتراضية من PenaltyType
    form.deduction_amount = null; // استخدام القيمة الافتراضية من PenaltyType
    form.deduction_days = null;
    form.deduction_hours = null;
    form.priority = 0;
    form.is_active = true;
    form.conditions = {};
    showModal.value = true;
};

const openEditModal = (rule) => {
    isEditing.value = true;
    form.id = rule.id;
    form.name = rule.name;
    form.description = rule.description || '';
    form.penalty_type_id = rule.penalty_type_id;
    form.deduction_type = rule.deduction_type;
    form.deduction_amount = rule.deduction_amount;
    form.deduction_days = rule.deduction_days || null;
    form.deduction_hours = rule.deduction_hours || null;
    form.min_deduction = rule.min_deduction;
    form.max_deduction = rule.max_deduction;
    // التأكد من أن conditions يحتوي على جميع الحقول المطلوبة
    form.conditions = {
        event_type: rule.conditions?.event_type || null,
        occurrence_type: rule.conditions?.occurrence_type || null,
        occurrence_count: rule.conditions?.occurrence_count || null,
        time_period: rule.conditions?.time_period || null,
        min_minutes_late: rule.conditions?.min_minutes_late || null,
        max_minutes_late: rule.conditions?.max_minutes_late || null,
        requires_permission: rule.conditions?.requires_permission || null,
    };
    form.priority = rule.priority || 0;
    form.is_active = rule.is_active;
    showModal.value = true;
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('hr.deduction-rules.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
            }
        });
    } else {
        form.post(route('hr.deduction-rules.store'), {
            onSuccess: () => {
                showModal.value = false;
            }
        });
    }
};

const deleteRule = (ruleId) => {
    if (confirm('هل أنت متأكد من حذف هذه المعادلة؟')) {
        router.delete(route('hr.deduction-rules.destroy', ruleId), {
            preserveScroll: true,
        });
    }
};

const getDeductionTypeLabel = (type) => {
    if (!type) return 'غير محدد';
    const labels = {
        'fixed': 'مبلغ ثابت',
        'percentage': 'نسبة مئوية',
        'daily_salary': 'يوم/أيام من المرتب',
        'hourly_salary': 'ساعات من المرتب'
    };
    return labels[type] || type;
};

const getConditionSummary = (conditions) => {
    if (!conditions || !conditions.event_type) return 'لا توجد شروط';

    const eventLabels = {
        'late': 'تأخير',
        'absent_without_permission': 'غياب بدون إذن',
        'absent': 'غياب',
        'early_leave': 'انصراف مبكر',
        'administrative_violation': 'مخالفة إدارية',
        'misconduct': 'سوء سلوك',
        'policy_violation': 'مخالفة سياسة',
        'dress_code_violation': 'مخالفة قواعد اللباس',
        'workplace_violation': 'مخالفة مكان العمل',
        'other': 'أخرى'
    };

    const occurrenceLabels = {
        'consecutive': 'متتالية',
        'non_consecutive': 'غير متتالية',
        'total': 'إجمالي'
    };

    const periodLabels = {
        'daily': 'يومي',
        'weekly': 'أسبوعي',
        'monthly': 'شهري',
        'yearly': 'سنوي'
    };

    let summary = eventLabels[conditions.event_type] || conditions.event_type;

    if (conditions.occurrence_type && conditions.occurrence_count) {
        summary += ` - ${conditions.occurrence_count} ${occurrenceLabels[conditions.occurrence_type] || conditions.occurrence_type}`;
    }

    if (conditions.time_period) {
        summary += ` (${periodLabels[conditions.time_period] || conditions.time_period})`;
    }

    if (conditions.min_minutes_late || conditions.max_minutes_late) {
        summary += ` - `;
        if (conditions.min_minutes_late && conditions.max_minutes_late) {
            summary += `${conditions.min_minutes_late}-${conditions.max_minutes_late} دقيقة`;
        } else if (conditions.min_minutes_late) {
            summary += `أكثر من ${conditions.min_minutes_late} دقيقة`;
        } else if (conditions.max_minutes_late) {
            summary += `أقل من ${conditions.max_minutes_late} دقيقة`;
        }
    }

    return summary;
};
</script>

<template>
    <Head title="معادلات الخصم" />
    <HrLayout>
        <template #header>
            معادلات وقواعد الخصم
        </template>

        <div class="bg-white shadow-lg rounded-xl p-6 border border-gray-200">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">معادلات وقواعد الخصم</h2>
                    <p class="text-gray-600 mt-2 text-sm">إدارة المعادلات والقيود المالية للخصومات المرتبطة بالعقوبات</p>
                </div>
                <button @click="openCreateModal" class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white px-6 py-3 rounded-lg hover:from-indigo-700 hover:to-indigo-800 flex items-center shadow-lg hover:shadow-xl transition-all">
                    <i class="fas fa-plus mr-2"></i> إضافة معادلة خصم
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                        <tr>
                            <th class="py-3 px-4 text-right font-semibold">اسم المعادلة</th>
                            <th class="py-3 px-4 text-right font-semibold">نوع العقوبة</th>
                            <th class="py-3 px-4 text-center font-semibold">نوع الخصم</th>
                            <th class="py-3 px-4 text-center font-semibold">قيمة الخصم</th>
                            <th class="py-3 px-4 text-center font-semibold">الأولوية</th>
                            <th class="py-3 px-4 text-center font-semibold">الحالة</th>
                            <th class="py-3 px-4 text-center font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 divide-y divide-gray-200">
                        <tr v-for="rule in deductionRules" :key="rule.id" class="hover:bg-indigo-50 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-semibold">{{ rule.name }}</div>
                                <div v-if="rule.description" class="text-sm text-gray-500">{{ rule.description }}</div>
                                <div v-if="rule.conditions && rule.conditions.event_type" class="text-xs text-gray-600 mt-1">
                                    <span class="bg-gray-100 px-2 py-0.5 rounded">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        {{ getConditionSummary(rule.conditions) }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">
                                    {{ rule.penalty_type?.name || 'غير محدد' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span :class="{
                                    'bg-green-100 text-green-800': (rule.deduction_type || rule.penalty_type?.deduction_type) === 'fixed',
                                    'bg-purple-100 text-purple-800': (rule.deduction_type || rule.penalty_type?.deduction_type) === 'percentage',
                                    'bg-orange-100 text-orange-800': (rule.deduction_type || rule.penalty_type?.deduction_type) === 'daily_salary',
                                    'bg-yellow-100 text-yellow-800': (rule.deduction_type || rule.penalty_type?.deduction_type) === 'hourly_salary',
                                    'bg-gray-100 text-gray-800': !rule.deduction_type && !rule.penalty_type?.deduction_type
                                }"
                                      class="px-2 py-1 rounded text-sm">
                                    {{ getDeductionTypeLabel(rule.deduction_type || rule.penalty_type?.deduction_type) }}
                                    <span v-if="!rule.deduction_type" class="text-xs text-gray-500 mr-1">(افتراضي)</span>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <!-- عرض القيمة حسب نوع الخصم -->
                                <span class="font-semibold" v-if="(rule.deduction_type || rule.penalty_type?.deduction_type) === 'daily_salary'">
                                    {{ rule.deduction_days || 1 }} {{ rule.deduction_days === 1 ? 'يوم' : 'أيام' }}
                                    <span class="text-xs text-gray-500 block mt-1">من المرتب</span>
                                </span>
                                <span class="font-semibold" v-else-if="(rule.deduction_type || rule.penalty_type?.deduction_type) === 'hourly_salary'">
                                    {{ rule.deduction_hours || 0 }} {{ rule.deduction_hours === 1 ? 'ساعة' : 'ساعات' }}
                                    <span class="text-xs text-gray-500 block mt-1">من المرتب</span>
                                </span>
                                <span class="font-semibold" v-else>
                                    {{ rule.deduction_amount || rule.penalty_type?.deduction_amount }}
                                    <span v-if="(rule.deduction_type || rule.penalty_type?.deduction_type) === 'percentage'">%</span>
                                    <span v-else-if="(rule.deduction_type || rule.penalty_type?.deduction_type) === 'fixed'">دينار</span>
                                </span>
                                <div v-if="!rule.deduction_amount && !rule.deduction_days && !rule.deduction_hours && rule.penalty_type?.deduction_amount && (rule.deduction_type || rule.penalty_type?.deduction_type) !== 'daily_salary' && (rule.deduction_type || rule.penalty_type?.deduction_type) !== 'hourly_salary'" class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-info-circle"></i> من نوع العقوبة
                                </div>
                                <div v-if="rule.min_deduction || rule.max_deduction" class="text-xs text-gray-500 mt-1">
                                    <span v-if="rule.min_deduction">الحد الأدنى: {{ rule.min_deduction }}</span>
                                    <span v-if="rule.min_deduction && rule.max_deduction"> - </span>
                                    <span v-if="rule.max_deduction">الحد الأقصى: {{ rule.max_deduction }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">
                                    {{ rule.priority }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span :class="rule.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                      class="px-2 py-1 rounded text-sm">
                                    {{ rule.is_active ? 'نشطة' : 'غير نشطة' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex justify-center items-center space-x-2 rtl:space-x-reverse">
                                    <button @click="openEditModal(rule)"
                                            class="text-blue-600 hover:text-blue-800"
                                            title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button @click="deleteRule(rule.id)"
                                            class="text-red-600 hover:text-red-800"
                                            title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="deductionRules.length === 0">
                            <td colspan="7" class="py-8 text-center text-gray-500">
                                لا توجد معادلات خصم مسجلة
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="showModal = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[95vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="text-xl font-bold">
                        {{ isEditing ? 'تعديل معادلة الخصم' : 'إضافة معادلة خصم جديدة' }}
                    </h3>
                    <button @click="showModal = false" class="text-white hover:text-gray-200 transition-colors p-1 rounded-full hover:bg-white/20">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-6">
                    <form @submit.prevent="submitForm" class="space-y-6">
                        <!-- Basic Information Section -->
                        <div class="grid grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">اسم المعادلة *</label>
                                <input v-model="form.name"
                                       type="text"
                                       class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                                       required>
                                <div v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</div>
                            </div>

                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">الوصف</label>
                                <textarea v-model="form.description"
                                          rows="2"
                                          class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                                          placeholder="وصف مختصر للمعادلة..."></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">نوع العقوبة *</label>
                                <select v-model="form.penalty_type_id"
                                        class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                                        required>
                                    <option :value="null" disabled>-- اختر نوع العقوبة --</option>
                                    <option v-for="penaltyType in penaltyTypes" :key="penaltyType.id" :value="penaltyType.id">
                                        {{ penaltyType.name }}
                                    </option>
                                </select>
                                <div v-if="form.errors.penalty_type_id" class="text-red-600 text-sm mt-1">{{ form.errors.penalty_type_id }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">الأولوية</label>
                                <input v-model.number="form.priority"
                                       type="number"
                                       min="0"
                                       class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                                <p class="text-xs text-gray-500 mt-1">كلما زاد الرقم زادت الأولوية</p>
                            </div>
                        </div>

                        <!-- Deduction Amount Section -->
                        <div class="bg-gradient-to-br from-indigo-50 to-blue-50 border-2 border-indigo-100 rounded-xl p-5">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-calculator text-indigo-600 mr-2"></i>
                                معلومات الخصم
                            </h4>

                            <div class="bg-blue-50 border-r-4 border-blue-400 rounded-lg p-3 mb-4">
                                <p class="text-sm text-blue-800 flex items-start">
                                    <i class="fas fa-info-circle mr-2 mt-0.5"></i>
                                    <span><strong>ملاحظة:</strong> إذا لم تقم بتحديد نوع الخصم وقيمة الخصم، سيتم استخدام القيم الافتراضية من نوع العقوبة المحدد.</span>
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        نوع الخصم
                                        <span class="text-gray-500 text-xs font-normal">(اختياري)</span>
                                    </label>
                                    <select v-model="form.deduction_type"
                                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                                        <option :value="null">استخدام القيمة من نوع العقوبة</option>
                                        <optgroup label="أنواع الخصم الأساسية">
                                            <option value="fixed">مبلغ ثابت</option>
                                            <option value="percentage">نسبة مئوية</option>
                                        </optgroup>
                                        <optgroup label="خصم من المرتب">
                                            <option value="daily_salary">يوم/أيام من المرتب</option>
                                            <option value="hourly_salary">ساعات من المرتب</option>
                                        </optgroup>
                                    </select>
                                    <p class="text-xs text-indigo-600 mt-1 font-medium" v-if="!form.deduction_type && selectedPenaltyType">
                                        <i class="fas fa-arrow-down mr-1"></i>
                                        القيمة الافتراضية: {{ selectedPenaltyType?.deduction_type === 'fixed' ? 'مبلغ ثابت' : selectedPenaltyType?.deduction_type === 'percentage' ? 'نسبة مئوية' : 'غير محدد' }}
                                    </p>
                                </div>

                                <!-- قيمة الخصم (لأنواع fixed و percentage) -->
                                <div v-if="form.deduction_type === 'fixed' || form.deduction_type === 'percentage' || !form.deduction_type">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        قيمة الخصم
                                        <span class="text-gray-500 text-xs font-normal">(اختياري)</span>
                                    </label>
                                    <input v-model.number="form.deduction_amount"
                                           type="number"
                                           step="0.01"
                                           min="0"
                                           class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                                    <div v-if="form.errors.deduction_amount" class="text-red-600 text-sm mt-1">{{ form.errors.deduction_amount }}</div>
                                    <p class="text-xs text-indigo-600 mt-1 font-medium" v-if="!form.deduction_amount && selectedPenaltyType && (!form.deduction_type || form.deduction_type === 'fixed' || form.deduction_type === 'percentage')">
                                        <i class="fas fa-arrow-down mr-1"></i>
                                        القيمة الافتراضية: {{ selectedPenaltyType?.deduction_amount || 'غير محدد' }}
                                    </p>
                                </div>

                                <!-- عدد الأيام (لنوع daily_salary) -->
                                <div v-if="form.deduction_type === 'daily_salary'">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        عدد الأيام للخصم *
                                    </label>
                                    <input v-model.number="form.deduction_days"
                                           type="number"
                                           min="1"
                                           step="1"
                                           class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                                           placeholder="مثلاً: 1 أو 2">
                                    <div v-if="form.errors.deduction_days" class="text-red-600 text-sm mt-1">{{ form.errors.deduction_days }}</div>
                                    <p class="text-xs text-indigo-600 mt-1 font-medium">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        سيتم خصم عدد الأيام المحدد من الراتب الشهري (يتم حساب الراتب اليومي تلقائياً)
                                    </p>
                                </div>

                                <!-- عدد الساعات (لنوع hourly_salary) -->
                                <div v-if="form.deduction_type === 'hourly_salary'">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        عدد الساعات للخصم *
                                    </label>
                                    <input v-model.number="form.deduction_hours"
                                           type="number"
                                           min="0"
                                           step="0.5"
                                           class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                                           placeholder="مثلاً: 2 أو 4.5">
                                    <div v-if="form.errors.deduction_hours" class="text-red-600 text-sm mt-1">{{ form.errors.deduction_hours }}</div>
                                    <p class="text-xs text-indigo-600 mt-1 font-medium">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        سيتم خصم عدد الساعات المحدد من الراتب (يتم حساب سعر الساعة تلقائياً)
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mt-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">الحد الأدنى للخصم</label>
                                    <input v-model.number="form.min_deduction"
                                           type="number"
                                           step="0.01"
                                           min="0"
                                           class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                                           placeholder="0.00">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">الحد الأقصى للخصم</label>
                                    <input v-model.number="form.max_deduction"
                                           type="number"
                                           step="0.01"
                                           min="0"
                                           class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                                           placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- Conditions Section -->
                        <div class="bg-gradient-to-br from-purple-50 to-pink-50 border-2 border-purple-100 rounded-xl p-5">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-filter text-purple-600 mr-2"></i>
                                الشروط والقواعد
                            </h4>

                            <div class="grid grid-cols-2 gap-6">
                                <div class="col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">نوع الحدث *</label>
                                    <select v-model="form.conditions.event_type"
                                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                            required>
                                        <option :value="null" disabled>-- اختر نوع الحدث --</option>
                                        <optgroup label="الحضور والانصراف">
                                            <option value="late">تأخير</option>
                                            <option value="absent_without_permission">غياب بدون إذن</option>
                                            <option value="absent">غياب</option>
                                            <option value="early_leave">انصراف مبكر</option>
                                        </optgroup>
                                        <optgroup label="المخالفات الإدارية">
                                            <option value="administrative_violation">مخالفة إدارية</option>
                                            <option value="misconduct">سوء سلوك</option>
                                            <option value="policy_violation">مخالفة سياسة</option>
                                            <option value="dress_code_violation">مخالفة قواعد اللباس</option>
                                            <option value="workplace_violation">مخالفة مكان العمل</option>
                                        </optgroup>
                                        <optgroup label="أخرى">
                                            <option value="other">أخرى</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <div v-if="form.conditions.event_type === 'late'" class="col-span-2 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">نوع التكرار</label>
                                        <select v-model="form.conditions.occurrence_type"
                                                class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                                            <option :value="null">-- اختر نوع التكرار --</option>
                                            <option value="consecutive">أيام متتالية</option>
                                            <option value="non_consecutive">أيام غير متتالية</option>
                                            <option value="total">إجمالي عدد المرات</option>
                                        </select>
                                    </div>

                                    <div v-if="form.conditions.occurrence_type">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">عدد المرات</label>
                                        <input v-model.number="form.conditions.occurrence_count"
                                               type="number"
                                               min="1"
                                               class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                               placeholder="مثلاً: 3">
                                        <p class="text-xs text-purple-600 mt-1 font-medium">
                                            <span v-if="form.conditions.occurrence_type === 'consecutive'">عدد الأيام المتتالية</span>
                                            <span v-else-if="form.conditions.occurrence_type === 'non_consecutive'">عدد الأيام غير المتتالية</span>
                                            <span v-else>إجمالي عدد المرات</span>
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">الحد الأدنى لدقائق التأخير</label>
                                        <input v-model.number="form.conditions.min_minutes_late"
                                               type="number"
                                               min="0"
                                               class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                               placeholder="مثلاً: 10">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">الحد الأقصى لدقائق التأخير</label>
                                        <input v-model.number="form.conditions.max_minutes_late"
                                               type="number"
                                               min="0"
                                               class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                               placeholder="مثلاً: 30">
                                    </div>
                                </div>

                                <!-- شروط التكرار للغياب -->
                                <div v-if="form.conditions.event_type === 'absent_without_permission' || form.conditions.event_type === 'absent'" class="col-span-2 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">نوع التكرار</label>
                                        <select v-model="form.conditions.occurrence_type"
                                                class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                                            <option :value="null">-- اختر نوع التكرار --</option>
                                            <option value="consecutive">أيام متتالية</option>
                                            <option value="non_consecutive">أيام غير متتالية</option>
                                            <option value="total">إجمالي عدد المرات</option>
                                        </select>
                                    </div>

                                    <div v-if="form.conditions.occurrence_type">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">عدد المرات</label>
                                        <input v-model.number="form.conditions.occurrence_count"
                                               type="number"
                                               min="1"
                                               class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                               placeholder="مثلاً: 3">
                                    </div>
                                </div>

                                <!-- شروط التكرار للمخالفات الإدارية والأحداث الأخرى -->
                                <div v-if="form.conditions.event_type && ['administrative_violation', 'misconduct', 'policy_violation', 'dress_code_violation', 'workplace_violation', 'other', 'early_leave'].includes(form.conditions.event_type)" class="col-span-2 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">نوع التكرار</label>
                                        <select v-model="form.conditions.occurrence_type"
                                                class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                                            <option :value="null">-- اختر نوع التكرار --</option>
                                            <option value="consecutive">مرات متتالية</option>
                                            <option value="non_consecutive">مرات غير متتالية</option>
                                            <option value="total">إجمالي عدد المرات</option>
                                        </select>
                                    </div>

                                    <div v-if="form.conditions.occurrence_type">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">عدد المرات</label>
                                        <input v-model.number="form.conditions.occurrence_count"
                                               type="number"
                                               min="1"
                                               class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all"
                                               placeholder="مثلاً: 3">
                                    </div>
                                </div>

                                <!-- الفترة الزمنية لجميع أنواع الأحداث -->
                                <div v-if="form.conditions.occurrence_type && form.conditions.event_type" class="col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        الفترة الزمنية
                                        <span class="text-gray-500 text-xs font-normal">(نافذة الحساب)</span>
                                    </label>
                                    <select v-model="form.conditions.time_period"
                                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all">
                                        <option :value="null">-- اختر الفترة --</option>
                                        <option value="daily">يومي</option>
                                        <option value="weekly">أسبوعي</option>
                                        <option value="monthly">شهري</option>
                                        <option value="yearly">سنوي</option>
                                    </select>
                                    <div class="mt-2 bg-purple-50 border-r-4 border-purple-400 rounded-lg p-3">
                                        <p class="text-xs text-purple-800 font-medium mb-2">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <strong>ما المقصود بالفترة الزمنية؟</strong>
                                        </p>
                                        <p class="text-xs text-purple-700 leading-relaxed">
                                            الفترة الزمنية هي <strong>النافذة الزمنية</strong> التي يتم خلالها حساب عدد مرات حدوث الحدث لتطبيق قاعدة الخصم.
                                        </p>
                                        <ul class="text-xs text-purple-700 mt-2 space-y-1 mr-4 list-disc">
                                            <li v-if="form.conditions.time_period === 'daily'">
                                                <strong>يومي:</strong> يتم حساب المرات خلال يوم واحد (24 ساعة)
                                            </li>
                                            <li v-if="form.conditions.time_period === 'weekly'">
                                                <strong>أسبوعي:</strong> يتم حساب المرات خلال أسبوع واحد (7 أيام)
                                            </li>
                                            <li v-if="form.conditions.time_period === 'monthly'">
                                                <strong>شهري:</strong> يتم حساب المرات خلال شهر واحد (30 يوم)
                                            </li>
                                            <li v-if="form.conditions.time_period === 'yearly'">
                                                <strong>سنوي:</strong> يتم حساب المرات خلال سنة واحدة (365 يوم)
                                            </li>
                                        </ul>
                                        <div class="mt-3 pt-2 border-t border-purple-200">
                                            <p class="text-xs text-purple-800 font-semibold mb-2">أمثلة توضيحية:</p>

                                            <!-- مثال للأيام المتتالية -->
                                            <div v-if="form.conditions.occurrence_type === 'consecutive' && form.conditions.occurrence_count" class="mb-2">
                                                <p class="text-xs text-purple-700 mb-1">
                                                    <strong>مثال للأيام المتتالية:</strong>
                                                </p>
                                                <p class="text-xs text-purple-600 mr-3">
                                                    إذا اخترت <strong>"{{ form.conditions.occurrence_count }} {{ form.conditions.time_period === 'monthly' ? 'أيام متتالية' : 'مرات متتالية' }}"</strong>
                                                    و<strong>"{{ form.conditions.time_period === 'monthly' ? 'شهري' : form.conditions.time_period === 'weekly' ? 'أسبوعي' : form.conditions.time_period === 'daily' ? 'يومي' : 'سنوي' }}"</strong>،
                                                    فسيتم تطبيق الخصم عندما يحدث الحدث <strong>{{ form.conditions.occurrence_count }} مرات متتالية خلال نفس الفترة</strong>.
                                                </p>
                                                <p class="text-xs text-purple-500 mr-4 mt-1 italic">
                                                    مثال: تأخير في اليوم 1، 2، 3 → يتم تطبيق الخصم ✓
                                                </p>
                                            </div>

                                            <!-- مثال للأيام غير المتتالية -->
                                            <div v-if="form.conditions.occurrence_type === 'non_consecutive' && form.conditions.occurrence_count" class="mb-2">
                                                <p class="text-xs text-purple-700 mb-1">
                                                    <strong>مثال للأيام غير المتتالية:</strong>
                                                </p>
                                                <p class="text-xs text-purple-600 mr-3">
                                                    إذا اخترت <strong>"{{ form.conditions.occurrence_count }} {{ form.conditions.time_period === 'monthly' ? 'أيام غير متتالية' : 'مرات غير متتالية' }}"</strong>
                                                    و<strong>"{{ form.conditions.time_period === 'monthly' ? 'شهري' : form.conditions.time_period === 'weekly' ? 'أسبوعي' : form.conditions.time_period === 'daily' ? 'يومي' : 'سنوي' }}"</strong>،
                                                    فسيتم تطبيق الخصم عندما يحدث الحدث <strong>{{ form.conditions.occurrence_count }} مرات خلال نفس الفترة، ولكن هذه المرات لا تكون متتالية</strong>.
                                                </p>
                                                <p class="text-xs text-purple-500 mr-4 mt-1 italic">
                                                    مثال: تأخير في اليوم 1، 5، 15 (أيام متفرقة) → يتم تطبيق الخصم ✓
                                                </p>
                                                <p class="text-xs text-red-600 mr-4 mt-1 italic">
                                                    مثال: تأخير في اليوم 1، 2، 10 (يومان متتاليان) → لا يتم تطبيق الخصم ✗
                                                </p>
                                            </div>

                                            <!-- مثال للإجمالي -->
                                            <div v-if="form.conditions.occurrence_type === 'total' && form.conditions.occurrence_count" class="mb-2">
                                                <p class="text-xs text-purple-700 mb-1">
                                                    <strong>مثال للإجمالي:</strong>
                                                </p>
                                                <p class="text-xs text-purple-600 mr-3">
                                                    إذا اخترت <strong>"{{ form.conditions.occurrence_count }} إجمالي عدد المرات"</strong>
                                                    و<strong>"{{ form.conditions.time_period === 'monthly' ? 'شهري' : form.conditions.time_period === 'weekly' ? 'أسبوعي' : form.conditions.time_period === 'daily' ? 'يومي' : 'سنوي' }}"</strong>،
                                                    فسيتم تطبيق الخصم عندما يصل <strong>إجمالي عدد مرات الحدث إلى {{ form.conditions.occurrence_count }} خلال نفس الفترة</strong> (سواء كانت متتالية أم لا).
                                                </p>
                                                <p class="text-xs text-purple-500 mr-4 mt-1 italic">
                                                    مثال: تأخير في اليوم 1، 2، 3، 5، 10 → عند الوصول للحد الأدنى ({{ form.conditions.occurrence_count }}) يتم تطبيق الخصم ✓
                                                </p>
                                            </div>

                                            <!-- مثال عام إذا لم يتم اختيار نوع التكرار -->
                                            <div v-if="!form.conditions.occurrence_type" class="mb-2">
                                                <p class="text-xs text-purple-700">
                                                    اختر نوع التكرار وعدد المرات لرؤية مثال توضيحي محدد.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Section -->
                        <div class="flex items-center justify-between bg-gray-50 rounded-lg p-4 border-2 border-gray-200">
                            <label class="flex items-center cursor-pointer">
                                <input v-model="form.is_active"
                                       type="checkbox"
                                       class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer">
                                <span class="mr-3 text-sm font-semibold text-gray-700">نشط</span>
                            </label>
                        </div>
                    </form>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end space-x-3 rtl:space-x-reverse">
                    <button type="button"
                            @click="showModal = false"
                            class="px-6 py-2.5 bg-white border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all font-medium">
                        إلغاء
                    </button>
                    <button type="button"
                            @click="submitForm"
                            :disabled="form.processing"
                            class="px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-lg hover:from-indigo-700 hover:to-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed transition-all font-medium shadow-lg">
                        <span v-if="form.processing">
                            <i class="fas fa-spinner fa-spin mr-2"></i>
                            جاري الحفظ...
                        </span>
                        <span v-else>
                            <i class="fas fa-save mr-2"></i>
                            {{ isEditing ? 'تحديث' : 'حفظ' }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </HrLayout>
</template>



