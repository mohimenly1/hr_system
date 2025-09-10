<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import throttle from 'lodash/throttle';
import pickBy from 'lodash/pickBy';

const props = defineProps({
    shifts: Array,
    personnel: Object,
    filters: Object,
    deviceUsers: Array,
});

const activeTab = ref('manage_shifts');

// --- Logic for Managing Shifts Tab ---
const showShiftModal = ref(false);
const isEditingShift = ref(false);
const shiftForm = useForm({
    id: null, name: '', start_time: '', end_time: '', grace_period_minutes: 0,
});
const openCreateShiftModal = () => {
    isEditingShift.value = false;
    shiftForm.reset();
    showShiftModal.value = true;
};
const openEditShiftModal = (shift) => {
    isEditingShift.value = true;
    shiftForm.id = shift.id;
    shiftForm.name = shift.name;
    shiftForm.start_time = shift.start_time;
    shiftForm.end_time = shift.end_time;
    shiftForm.grace_period_minutes = shift.grace_period_minutes;
    showShiftModal.value = true;
};
const submitShiftForm = () => {
    if (isEditingShift.value) {
        shiftForm.put(route('hr.shifts.update', shiftForm.id), {
            onSuccess: () => showShiftModal.value = false,
        });
    } else {
        shiftForm.post(route('hr.shifts.store'), {
            onSuccess: () => showShiftModal.value = false,
        });
    }
};
const deleteShift = (shiftId) => {
    if (confirm('هل أنت متأكد من حذف هذا الدوام؟')) {
        router.delete(route('hr.shifts.destroy', shiftId));
    }
};

// --- Logic for Assigning Shifts Tab ---
const search = ref(props.filters.search || '');
const filterType = ref(props.filters.filter_type || 'all');

const deviceUserMap = computed(() => {
    const map = new Map();
    if (Array.isArray(props.deviceUsers)) {
        props.deviceUsers.forEach(user => {
            map.set(user.uid.toString(), user.name);
        });
    }
    return map;
});

watch([search, filterType], throttle(() => {
    router.get(route('hr.shifts.index'), pickBy({
        search: search.value,
        filter_type: filterType.value,
    }), {
        preserveState: true,
        replace: true,
    });
}, 300));

const updateShiftAssignment = (person, event) => {
    const shiftId = event.target.value;
    router.post(route('hr.shift-assignments.store'), {
        shift_id: shiftId,
        shiftable_id: person.id,
        shiftable_type: person.model_class,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="إدارة الورديات" />
    <HrLayout>
        <template #header>
            إدارة الورديات وتعيينها
        </template>
        <div class="bg-white shadow-md rounded-lg">
            <!-- Tabs Navigation -->
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-6 rtl:space-x-reverse px-6">
                    <button @click="activeTab = 'manage_shifts'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'manage_shifts' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                        <i class="fas fa-business-time mr-2"></i>
                        إدارة الورديات
                    </button>
                    <button @click="activeTab = 'assign_shifts'" :class="['py-4 px-1 border-b-2 font-medium text-sm', activeTab === 'assign_shifts' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                        <i class="fas fa-user-clock mr-2"></i>
                        تعيين الورديات
                    </button>
                </nav>
            </div>
            <!-- Tab Content -->
            <div class="p-6">
                <!-- Manage Shifts Tab -->
                <div v-show="activeTab === 'manage_shifts'">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-800">قائمة الورديات</h3>
                        <button @click="openCreateShiftModal" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                            <i class="fas fa-plus mr-2"></i> إضافة دوام جديد
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white text-gray-700">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-right py-2 px-4">اسم الدوام</th>
                                    <th class="text-center py-2 px-4">وقت البدء</th>
                                    <th class="text-center py-2 px-4">وقت الانتهاء</th>
                                    <th class="text-center py-2 px-4">فترة السماح (دقائق)</th>
                                    <th class="text-center py-2 px-4">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="shifts.length === 0">
                                    <td colspan="5" class="text-center py-6">لم يتم إضافة أي دوامات بعد.</td>
                                </tr>
                                <tr v-for="shift in shifts" :key="shift.id" class="border-b">
                                    <td class="py-3 px-4">{{ shift.name }}</td>
                                    <td class="py-3 px-4 text-center font-mono">{{ shift.start_time }}</td>
                                    <td class="py-3 px-4 text-center font-mono">{{ shift.end_time }}</td>
                                    <td class="py-3 px-4 text-center">{{ shift.grace_period_minutes }}</td>
                                    <td class="py-3 px-4 text-center">
                                        <button @click="openEditShiftModal(shift)" class="text-blue-600 hover:text-blue-900 mr-4">تعديل</button>
                                        <button @click="deleteShift(shift.id)" class="text-red-600 hover:text-red-900">حذف</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Assign Shifts Tab -->
                <div v-show="activeTab === 'assign_shifts'">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">تعيين الورديات</h3>
                     <div class="flex items-center justify-between mb-4 bg-white shadow-sm p-4 rounded-lg border border-gray-200">
                        <div class="flex items-center space-x-4 rtl:space-x-reverse">
                            <!-- مربع البحث -->
                            <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                <i class="fas fa-search text-gray-500"></i>
                            </span>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="ابحث بالاسم..."
                                class="w-72 pl-10 pr-4 py-2 text-black border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                            </div>

                            <!-- قائمة منسدلة -->
                            <div>
                            <select
                                v-model="filterType"
                                class="w-48 py-2 text-black border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="all">الكل</option>
                                <option value="employees">الموظفين فقط</option>
                                <option value="teachers">المعلمين فقط</option>
                            </select>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-800 text-white">
                                <tr>
                                    <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الاسم بالنظام</th>
                                    <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الدور</th>
                                    <th class="text-center py-3 px-4 uppercase font-semibold text-sm">رقم البصمة</th>
                                    <th class="text-center py-3 px-4 uppercase font-semibold text-sm">مطابقة المزامنة</th>
                                    <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الدوام الحالي</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700">
                                <tr v-if="personnel.data.length === 0">
                                    <td colspan="5" class="text-center py-6 text-gray-500">لا توجد بيانات تطابق البحث.</td>
                                </tr>
                                <tr v-for="person in personnel.data" :key="`${person.model_class}-${person.id}`" class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4 font-medium">{{ person.user.name }}</td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="person.personnel_type === 'موظف' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                                            {{ person.personnel_type }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono">
                                        {{ person.fingerprint_id || 'غير مسجل' }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div v-if="person.fingerprint_id">
                                            <div v-if="deviceUserMap.has(person.fingerprint_id.toString())">
                                                <span :class="{ 'text-green-600 font-bold': deviceUserMap.get(person.fingerprint_id.toString()) === person.user.name }">
                                                    <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                                    {{ deviceUserMap.get(person.fingerprint_id.toString()) }}
                                                </span>
                                            </div>
                                            <div v-else class="text-red-500 text-xs" title="هذا المستخدم غير موجود في ذاكرة النظام المؤقتة. يرجى تحديث قائمة المستخدمين من صفحة جهاز البصمة.">
                                                <i class="fas fa-sync-alt mr-1"></i>
                                                يتطلب مزامنة
                                            </div>
                                        </div>
                                        <span v-else class="text-gray-400 text-xs">--</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <select @change="updateShiftAssignment(person, $event)" class="border-gray-300 rounded-md shadow-sm w-48 text-sm">
                                            <option :value="null">-- بلا دوام --</option>
                                            <option v-for="shift in shifts" :key="shift.id" :value="shift.id" :selected="person.shift_assignment && person.shift_assignment.shift_id === shift.id">
                                                {{ shift.name }}
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div v-if="personnel.data.length > 0" class="mt-6 flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            عرض {{ personnel.from }} إلى {{ personnel.to }} من {{ personnel.total }} نتيجة
                        </div>
                        <div class="flex">
                            <Link
                                v-for="(link, index) in personnel.links"
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
        </div>

        <!-- Shift Create/Edit Modal -->
         <div v-if="showShiftModal" class="fixed inset-0 bg-black/40 z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
                <h3 class="text-xl font-bold mb-4">{{ isEditingShift ? 'تعديل الدوام' : 'إضافة دوام جديد' }}</h3>
                <form @submit.prevent="submitShiftForm">
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block mb-1 text-sm font-medium text-gray-700">اسم الدوام</label>
                            <input type="text" v-model="shiftForm.name" id="name" class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="start_time" class="block mb-1 text-sm font-medium text-gray-700">وقت البدء</label>
                                <input type="time" v-model="shiftForm.start_time" id="start_time" class="w-full border-gray-300 rounded-md shadow-sm" required>
                            </div>
                            <div>
                                <label for="end_time" class="block mb-1 text-sm font-medium text-gray-700">وقت الانتهاء</label>
                                <input type="time" v-model="shiftForm.end_time" id="end_time" class="w-full border-gray-300 rounded-md shadow-sm" required>
                            </div>
                        </div>
                        <div>
                            <label for="grace_period_minutes" class="block mb-1 text-sm font-medium text-gray-700">فترة السماح (بالدقائق)</label>
                            <input type="number" v-model="shiftForm.grace_period_minutes" id="grace_period_minutes" class="w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-2 rtl:space-x-reverse border-t pt-4">
                        <button type="button" @click="showShiftModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="shiftForm.processing">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </HrLayout>
</template>

<style scoped>
input{
    color: #000 !important;
}
</style>