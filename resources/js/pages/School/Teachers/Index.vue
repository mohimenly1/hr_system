<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import throttle from 'lodash/throttle';
import pickBy from 'lodash/pickBy';

const props = defineProps({
    teachers: Object,
    departments: Array,
    filters: Object,
});

const page = usePage();
const authUser = computed(() => page.props.auth.user);

// --- Filter State ---
const search = ref(props.filters.search || '');
const departmentFilter = ref(props.filters.department_id || '');

watch([search, departmentFilter], throttle(() => {
    router.get(route('school.teachers.index'), pickBy({
        search: search.value,
        department_id: departmentFilter.value,
    }), {
        preserveState: true,
        replace: true,
    });
}, 300));


// --- Modal for Fingerprint ID ---
const showFingerprintModal = ref(false);
const selectedTeacher = ref(null);
const fingerprintForm = useForm({
    fingerprint_id: '',
});

const openFingerprintModal = (teacher) => {
    selectedTeacher.value = teacher;
    fingerprintForm.fingerprint_id = teacher.fingerprint_id || '';
    showFingerprintModal.value = true;
};

const submitFingerprintId = () => {
    fingerprintForm.put(route('school.teachers.fingerprint.update', selectedTeacher.value.id), {
        onSuccess: () => {
            showFingerprintModal.value = false;
            fingerprintForm.reset();
        },
        preserveScroll: true,
    });
};

// --- Modal for User Details ---
const showUserModal = ref(false);
const selectedUserForModal = ref(null);
const openUserModal = (user) => {
    selectedUserForModal.value = user;
    showUserModal.value = true;
};

// --- Action Logic ---
const syncingTeacherId = ref(null);
const syncTeacherAttendance = (teacherId) => {
    if (syncingTeacherId.value) return;
    syncingTeacherId.value = teacherId;
    router.post(route('school.teachers.attendance.sync', teacherId), {}, {
        preserveScroll: true,
        onFinish: () => { syncingTeacherId.value = null; }
    });
};

// --- Helper Functions ---
const getStatusClass = (status) => {
    switch (status) {
        case 'active': return 'bg-green-100 text-green-800';
        case 'on_leave': return 'bg-yellow-100 text-yellow-800';
        case 'terminated': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head title="إدارة المعلمين" />
    <HrLayout>
        <template #header>
            إدارة المعلمين
        </template>
        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">قائمة المعلمين</h2>
                <Link :href="route('school.teachers.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    <i class="fas fa-plus mr-2"></i> إضافة معلم جديد
                </Link>
            </div>
            
            <!-- Filters -->
            <div class="flex items-center justify-between mb-4 bg-gray-50 p-3 rounded-md">
                <div class="flex items-center space-x-4 rtl:space-x-reverse">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-search text-gray-400"></i>
                        </span>
                        <input v-model="search" type="text" placeholder="ابحث بالاسم أو البريد..." class="w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-md text-gray-800 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div v-if="!authUser.roles.includes('department-manager')">
                        <select v-model="departmentFilter" class="border border-gray-300 rounded-md text-gray-800 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">كل الأقسام</option>
                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                     <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الاسم</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">القسم</th>
                            <th class="text-right py-3 px-4 uppercase font-semibold text-sm">الأدوار</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الحالة</th>
                            <th class="text-center py-3 px-4 uppercase font-semibold text-sm">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-if="teachers.data.length === 0">
                            <td colspan="5" class="text-center py-6 text-gray-500">لا توجد بيانات تطابق البحث.</td>
                        </tr>
                        <tr v-for="teacher in teachers.data" :key="teacher.id" class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <div class="font-medium text-gray-900">{{ teacher.user.full_name }}</div>
                                <div v-if="teacher.managed_departments.length > 0" class="text-xs text-indigo-600 font-semibold mt-1">
                                    <i class="fas fa-crown mr-1"></i> مدير: {{ teacher.managed_departments.map(d => d.name).join(', ') }}
                                </div>
                                <div class="text-xs text-gray-500">{{ teacher.user.email }}</div>
                            </td>
                            <td class="py-3 px-4">{{ teacher.department ? teacher.department.name : 'غير محدد' }}</td>
                            <td class="py-3 px-4">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="role in teacher.user.roles" :key="role.id" class="bg-gray-200 text-gray-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                        {{ role.name }}
                                    </span>
                               </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-1 text-xs font-semibold leading-5 rounded-full" :class="getStatusClass(teacher.employment_status)">
                                    {{ teacher.employment_status === 'active' ? 'نشط' : (teacher.employment_status === 'on_leave' ? 'في إجازة' : 'منتهية خدمته') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center space-x-4 rtl:space-x-reverse">
                                    <button @click="openUserModal(teacher.user)" class="text-gray-500 hover:text-cyan-600" title="عرض بيانات المستخدم">
                                        <i class="fas fa-user-check text-lg"></i>
                                    </button>
                                    <Link :href="route('school.teachers.attendance.show', teacher.id)" class="text-gray-500 hover:text-green-600" title="عرض سجل الحضور">
                                        <i class="fas fa-calendar-alt text-lg"></i>
                                    </Link>
                                    <button @click="syncTeacherAttendance(teacher.id)" 
                                            class="text-gray-500 hover:text-blue-600" 
                                            :disabled="syncingTeacherId === teacher.id" 
                                            title="مزامنة حضور اليوم">
                                        <i class="fas text-lg" :class="{'fa-sync-alt': syncingTeacherId !== teacher.id, 'fa-spinner fa-spin': syncingTeacherId === teacher.id}"></i>
                                    </button>
                                    <button v-if="authUser.roles.includes('admin') || authUser.roles.includes('hr-manager')"
                                            @click="openFingerprintModal(teacher)" 
                                            class="text-gray-500 hover:text-purple-600" title="تعديل رقم البصمة">
                                        <i class="fas fa-fingerprint text-lg"></i>
                                    </button>
                                    <span class="border-r border-gray-300 h-6"></span>
                                    <Link :href="route('school.teachers.show', teacher.id)" class="text-indigo-600 hover:text-indigo-900 font-medium">عرض</Link>
                                    <Link :href="route('school.teachers.edit', teacher.id)" class="text-blue-600 hover:text-blue-900 font-medium">تعديل</Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="teachers.data.length > 0" class="mt-6 flex justify-between items-center">
                <div class="text-sm text-gray-700">
                    عرض {{ teachers.from }} إلى {{ teachers.to }} من {{ teachers.total }} نتيجة
                </div>
                <div class="flex">
                    <Link
                        v-for="(link, index) in teachers.links"
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

        <!-- Fingerprint ID Modal -->
        <div v-if="showFingerprintModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-xl font-bold text-gray-800">تحديث رقم البصمة</h3>
                <p v-if="selectedTeacher" class="mt-2 text-gray-600">للمعلم: <span class="font-bold">{{ selectedTeacher.user.full_name }}</span></p>
                <form @submit.prevent="submitFingerprintId" class="mt-4 space-y-4">
                    <div>
                        <label for="fingerprint_id" class="block mb-2 text-sm font-medium text-gray-900">رقم البصمة (UID)</label>
                        <input type="number" v-model="fingerprintForm.fingerprint_id" id="fingerprint_id" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" required>
                        <div v-if="fingerprintForm.errors.fingerprint_id" class="text-sm text-red-600 mt-1">{{ fingerprintForm.errors.fingerprint_id }}</div>
                    </div>
                    <div class="flex justify-end pt-4 border-t space-x-2 rtl:space-x-reverse">
                        <button type="button" @click="showFingerprintModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إلغاء</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700" :disabled="fingerprintForm.processing">حفظ</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- User Details Modal -->
        <div v-if="showUserModal" @click.self="showUserModal = false" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex justify-center items-center p-4">
            <div v-if="selectedUserForModal" class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <div class="flex justify-between items-center border-b pb-3 mb-4">
                    <h3 class="text-xl font-bold text-gray-800">بيانات حساب المستخدم</h3>
                    <button @click="showUserModal = false" class="text-gray-500 hover:text-gray-800">&times;</button>
                </div>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="font-semibold text-gray-600">الاسم الكامل:</span>
                        <span class="text-gray-800">{{ selectedUserForModal.full_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-semibold text-gray-600">البريد الإلكتروني:</span>
                        <span class="text-gray-800">{{ selectedUserForModal.email }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-semibold text-gray-600">تاريخ الإنشاء:</span>
                        <span class="text-gray-800">{{ new Date(selectedUserForModal.created_at).toLocaleDateString('ar-LY') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="font-semibold text-gray-600">حالة الحساب:</span>
                        <span class="px-3 py-1 text-xs font-semibold leading-5 rounded-full" 
                              :class="selectedUserForModal.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                            {{ selectedUserForModal.is_active ? 'نشط' : 'غير نشط' }}
                        </span>
                    </div>
                </div>
                 <div class="mt-6 pt-4 border-t flex justify-end">
                    <button @click="showUserModal = false" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">إغلاق</button>
                </div>
            </div>
        </div>
    </HrLayout>
</template>

