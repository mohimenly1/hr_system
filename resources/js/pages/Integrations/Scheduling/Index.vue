<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps({
    departments: Array,
    selectedDepartmentId: Number,
    personnel: Object, // Paginated personnel
    search: String,
    subjects: Array,
    sections: Array,
    templates: Array,
    personnelConstraints: Object,
    timetables: Array,
    activePersonIdentifier: String,
    shifts: Array,
    customHours: Array,
    defaultShiftSetting: Object,
    personDepartmentShiftSetting: Object,
    organizationShiftSetting: Object,
    personDepartmentId: Number,
    departmentShiftSettings: Array,
    overtimeEntries: Array,
    leaveEntries: Array,
    hasOrganizationActiveShift: Boolean, // مؤشر وجود جدول للمؤسسة
});

// --- Main State Management ---
const activeView = ref('welcome');
const selectedDepartmentId = ref(props.selectedDepartmentId || null);
const selectedPersonIdentifier = ref(props.activePersonIdentifier || null);
const activePersonTab = ref('constraints');
const selectedMonth = ref(new Date().toISOString().slice(0, 7)); // YYYY-MM format
const showOvertimeModal = ref(false);
const editingOvertimeId = ref(null);
const overtimeForm = useForm({
    schedulable_id: null,
    schedulable_type: null,
    date: null,
    start_time: '',
    end_time: '',
    minutes: 0,
    notes: '',
});
const draggedOvertimeItem = ref(null);
const dragOverDateIndex = ref(null);
const selectedDatesForOvertime = ref([]);
const overtimeHoursInput = ref(0);
const overtimeMinutesInput = ref(0);
const showConfirmDialog = ref(false);
const confirmDialogData = ref({
    title: '',
    employmentType: '',
    workDays: [],
    startTime: null,
    endTime: null,
    totalHoursPerWeek: 0,
    warnings: [],
    notes: [],
});
const isEditMode = ref(false);
const draggedEntry = ref(null);
const dragOverCell = ref(null);
const showEntryModal = ref(false);
const editingEntry = ref(null);
const showCustomHoursModal = ref(false);
const customHoursForm = useForm({
    hourly_id: null,
    hourly_type: null,
    hours: [],
});

// تهيئة activeView بناءً على props
if (selectedPersonIdentifier.value) {
    activeView.value = 'person';
} else if (selectedDepartmentId.value) {
    activeView.value = 'department';
} else {
    activeView.value = 'welcome';
}

// Watch للتغييرات في props
watch(() => props.selectedDepartmentId, (newDeptId) => {
    if (newDeptId !== selectedDepartmentId.value) {
        selectedDepartmentId.value = newDeptId;
        if (newDeptId && !selectedPersonIdentifier.value) {
            activeView.value = 'department';
        } else if (!newDeptId && !selectedPersonIdentifier.value) {
            activeView.value = 'welcome';
        }
    }
}, { immediate: true });

watch(() => props.activePersonIdentifier, (newPersonId) => {
    if (newPersonId !== selectedPersonIdentifier.value) {
        selectedPersonIdentifier.value = newPersonId;
        if (newPersonId) {
            activeView.value = 'person';
        } else if (selectedDepartmentId.value) {
            activeView.value = 'department';
        } else {
            activeView.value = 'welcome';
        }
    }
}, { immediate: true });

// --- Department Selection ---
const selectDepartment = (departmentId) => {
    selectedDepartmentId.value = departmentId;
    selectedPersonIdentifier.value = null;
    activeView.value = 'department';
    router.get(route('hr.integrations.scheduling.index'), {
        department_id: departmentId
    }, {
        preserveState: false, // إعادة جلب جميع البيانات
        replace: true,
        preserveScroll: false,
        only: ['departments', 'selectedDepartmentId', 'personnel', 'defaultShiftSetting', 'departmentShiftSettings'] // جلب البيانات المطلوبة فقط
    });
};

const clearDepartmentSelection = () => {
    selectedDepartmentId.value = null;
    selectedPersonIdentifier.value = null;
    activeView.value = 'welcome';
    router.get(route('hr.integrations.scheduling.index'), {}, {
        preserveState: false, // إعادة جلب جميع البيانات
        replace: true,
        preserveScroll: false,
        only: ['departments', 'selectedDepartmentId', 'personnel', 'defaultShiftSetting', 'departmentShiftSettings'] // جلب البيانات المطلوبة فقط
    });
};

// حذف جدول الدوام لقسم معين
const deleteDepartmentShift = (departmentId, departmentName) => {
    if (!confirm(`هل أنت متأكد من رغبتك في حذف جدول الدوام الخاص بقسم "${departmentName}"؟\n\nسيتم حذف جميع إعدادات جدول الدوام لهذا القسم بشكل نهائي.`)) {
        return;
    }

    router.delete(route('hr.integrations.scheduling.default-shift-settings.delete'), {
        data: {
            department_id: departmentId,
        },
        preserveScroll: true,
        onSuccess: () => {
            // إعادة جلب البيانات بعد الحذف
            router.reload({
                only: ['departments', 'defaultShiftSetting', 'departmentShiftSettings', 'hasOrganizationActiveShift'],
                preserveState: true,
            });
        },
        onError: (errors) => {
            console.error('Error deleting shift settings:', errors);
            alert('حدث خطأ أثناء حذف جدول الدوام. يرجى المحاولة مرة أخرى.');
        }
    });
};

// --- Personnel Search & Filter ---
const searchQuery = ref(props.search || '');
const perPage = ref(15);

const performSearch = () => {
    router.get(route('hr.integrations.scheduling.index'), {
        department_id: selectedDepartmentId.value,
        search: searchQuery.value,
        per_page: perPage.value,
        page: 1, // Reset to first page on new search
    }, {
        preserveState: false,
        replace: true,
        preserveScroll: false,
        only: ['personnel', 'defaultShiftSetting', 'search'], // جلب البيانات المطلوبة فقط
    });
};

const changePage = (page) => {
    router.get(route('hr.integrations.scheduling.index'), {
        department_id: selectedDepartmentId.value,
        search: searchQuery.value,
        per_page: perPage.value,
        page: page,
    }, {
        preserveState: false, // إعادة جلب البيانات لضمان التحديث
        replace: true,
        preserveScroll: false,
        only: ['personnel', 'defaultShiftSetting'], // جلب البيانات المطلوبة فقط
    });
};

// Debounce search
let searchTimeout = null;
watch(searchQuery, (newValue) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        performSearch();
    }, 500);
});

const selectPerson = (person) => {
    selectedPersonIdentifier.value = `${person.type}-${person.id}`;
    activeView.value = 'person';
    activePersonTab.value = 'constraints';
    isEditMode.value = false;

    // Navigate with person selection
    router.get(route('hr.integrations.scheduling.index'), {
        department_id: selectedDepartmentId.value,
        person_id: person.id,
        person_type: `App\\Models\\${person.type}`,
        search: searchQuery.value,
        per_page: perPage.value,
    }, {
        preserveState: false, // إعادة جلب البيانات لضمان التحديث
        replace: true,
        preserveScroll: false,
        only: ['personnelConstraints', 'timetables', 'customHours', 'defaultShiftSetting', 'personDepartmentShiftSetting', 'organizationShiftSetting', 'personDepartmentId', 'activePersonIdentifier'], // جلب البيانات المطلوبة فقط
    });

    // تهيئة الساعات المخصصة عند اختيار موظف
    nextTick(() => {
        initializeCustomHours();
    });
};

const showTemplateManager = () => {
    selectedPersonIdentifier.value = null;
    activeView.value = 'templates';
};

// --- Week Days Definition (must be before functions that use it) ---
const weekDays = [
    { v: 6, n: 'السبت', isWeekend: true },
    { v: 0, n: 'الأحد', isWeekend: false },
    { v: 1, n: 'الإثنين', isWeekend: false },
    { v: 2, n: 'الثلاثاء', isWeekend: false },
    { v: 3, n: 'الأربعاء', isWeekend: false },
    { v: 4, n: 'الخميس', isWeekend: false },
    { v: 5, n: 'الجمعة', isWeekend: true }
];

// --- Time Format Helper Functions ---
const formatTimeTo12Hour = (time24) => {
    if (!time24) return '';

    // Handle different time formats (HH:mm, HH:mm:ss, etc.)
    const timeStr = typeof time24 === 'string' ? time24 : time24.toString();

    // Extract hours and minutes (ignore seconds if present)
    const timeParts = timeStr.split(':');
    const hours = parseInt(timeParts[0], 10);
    const minutes = parseInt(timeParts[1] || '0', 10);

    if (isNaN(hours) || isNaN(minutes)) return time24;

    // Determine period (صباحاً/مساءً)
    const period = hours >= 12 ? 'مساءً' : 'صباحاً';

    // Convert to 12-hour format
    let hours12 = hours % 12;
    if (hours12 === 0) hours12 = 12;

    // Format minutes with leading zero
    const minutesStr = minutes.toString().padStart(2, '0');

    return `${hours12}:${minutesStr} ${period}`;
};

// --- Custom Hours Management (must be before selectPerson) ---
const initializeCustomHours = () => {
    if (!selectedPerson.value || !selectedPersonIdentifier.value) return;

    const [type, id] = selectedPersonIdentifier.value.split('-');
    customHoursForm.hourly_id = parseInt(id);
    customHoursForm.hourly_type = `App\\Models\\${type}`;

    // تهيئة الأيام
    customHoursForm.hours = weekDays.map(day => {
        const existing = props.customHours?.find(ch => ch.day_of_week === day.v);
        return {
            day_of_week: day.v,
            day_name: day.n,
            hours: existing ? parseFloat(existing.hours) : 0,
            start_time: existing?.start_time ? existing.start_time.substring(0, 5) : '',
            end_time: existing?.end_time ? existing.end_time.substring(0, 5) : '',
            notes: existing?.notes || '',
        };
    });
};

// --- Default Shift Settings ---
const showDefaultShiftSettings = ref(false);
const defaultShiftSettingsForm = useForm({
    name: props.defaultShiftSetting?.name || (props.selectedDepartmentId ? `الدوام الافتراضي لقسم ${props.departments?.find(d => d.id === props.selectedDepartmentId)?.name || ''}` : 'الدوام الافتراضي للمؤسسة'),
    start_time: props.defaultShiftSetting?.start_time || '08:00',
    end_time: props.defaultShiftSetting?.end_time || '16:00',
    work_days: props.defaultShiftSetting?.work_days || [0, 1, 2, 3, 4], // الأحد - الخميس (بدون السبت والجمعة)
    description: props.defaultShiftSetting?.description || '',
    department_id: props.defaultShiftSetting?.department_id || props.selectedDepartmentId || null, // تلقائياً القسم المحدد أو null للمؤسسة
});

// Watch لتحديث defaultShiftSetting عند تغيير القسم أو الإعدادات
watch(() => [props.selectedDepartmentId, props.defaultShiftSetting], ([newDeptId, newSetting]) => {
    // تحديث النموذج عند تغيير القسم أو الإعدادات
    if (newSetting) {
        defaultShiftSettingsForm.name = newSetting.name || (newDeptId ? `الدوام الافتراضي لقسم ${props.departments?.find(d => d.id === newDeptId)?.name || ''}` : 'الدوام الافتراضي للمؤسسة');
        defaultShiftSettingsForm.start_time = newSetting.start_time || '08:00';
        defaultShiftSettingsForm.end_time = newSetting.end_time || '16:00';
        defaultShiftSettingsForm.work_days = newSetting.work_days || [0, 1, 2, 3, 4];
        defaultShiftSettingsForm.description = newSetting.description || '';
        defaultShiftSettingsForm.department_id = newSetting.department_id || newDeptId || null;
    } else {
        // إذا لم يكن هناك إعداد، استخدم القيم الافتراضية
        const dept = newDeptId ? props.departments?.find(d => d.id === newDeptId) : null;
        defaultShiftSettingsForm.name = dept ? `الدوام الافتراضي لقسم ${dept.name}` : 'الدوام الافتراضي للمؤسسة';
        defaultShiftSettingsForm.start_time = '08:00';
        defaultShiftSettingsForm.end_time = '16:00';
        defaultShiftSettingsForm.work_days = [0, 1, 2, 3, 4];
        defaultShiftSettingsForm.description = '';
        defaultShiftSettingsForm.department_id = newDeptId || null;
    }
}, { immediate: false, deep: true }); // immediate: false لتجنب المشكلة عند التهيئة


const toggleWorkDay = (dayValue) => {
    // إنشاء نسخة جديدة من المصفوفة لتجنب مشاكل التفاعلية
    const currentDays = [...defaultShiftSettingsForm.work_days];
    const index = currentDays.indexOf(dayValue);

    if (index > -1) {
        // إزالة اليوم إذا كان موجوداً
        currentDays.splice(index, 1);
    } else {
        // إضافة اليوم إذا لم يكن موجوداً
        currentDays.push(dayValue);
    }

    // تحديث المصفوفة بشكل مباشر
    defaultShiftSettingsForm.work_days = currentDays;
};

const calculatedHoursPerWeek = computed(() => {
    if (!defaultShiftSettingsForm.start_time || !defaultShiftSettingsForm.end_time || defaultShiftSettingsForm.work_days.length === 0) {
        return 0;
    }
    const start = new Date(`2000-01-01T${defaultShiftSettingsForm.start_time}`);
    const end = new Date(`2000-01-01T${defaultShiftSettingsForm.end_time}`);
    if (end < start) {
        end.setDate(end.getDate() + 1);
    }
    const hoursPerDay = (end - start) / (1000 * 60 * 60);
    return Math.round(hoursPerDay * defaultShiftSettingsForm.work_days.length * 100) / 100;
});

const calculatedHoursPerMonth = computed(() => {
    return Math.round(calculatedHoursPerWeek.value * 4.33 * 100) / 100;
});

const submitDefaultShiftSettings = () => {
    defaultShiftSettingsForm.post(route('hr.integrations.scheduling.default-shift-settings.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showDefaultShiftSettings.value = false;
            // إعادة جلب البيانات بعد الحفظ لضمان التحديث
            router.reload({
                only: ['defaultShiftSetting', 'departmentShiftSettings'],
                preserveState: true,
            });
        },
    });
};

// --- Template Management Logic ---
const showTemplateModal = ref(false);
const isEditingTemplate = ref(false);
const selectedTemplateForConstraints = ref(null);
const templateForm = useForm({ id: null, name: '', description: '', type: 'general', is_active: true });
const templateConstraintsForm = useForm({
    total_hours_per_week: 40,
    required_days: [6, 0, 1, 2, 3],
    max_subjects_per_day: 3,
    max_sections_per_day: 3,
    allowed_subjects: [],
    allowed_sections: [],
});
const openCreateTemplateModal = () => { isEditingTemplate.value = false; templateForm.reset(); showTemplateModal.value = true; };
const openEditTemplateModal = (template) => { isEditingTemplate.value = true; Object.assign(templateForm, template); showTemplateModal.value = true; };
const submitTemplateForm = () => {
    const action = isEditingTemplate.value
        ? templateForm.put(route('hr.integrations.scheduling.templates.update', templateForm.id))
        : templateForm.post(route('hr.integrations.scheduling.templates.store'));
    action.then(() => { if (!templateForm.hasErrors) showTemplateModal.value = false; });
};
const editTemplateConstraints = (template) => {
    selectedTemplateForConstraints.value = template;
    templateConstraintsForm.reset();
    template.constraints.forEach(constraint => {
        if (templateConstraintsForm.hasOwnProperty(constraint.constraint_type)) {
            templateConstraintsForm[constraint.constraint_type] = constraint.value;
        }
    });
};
const submitTemplateConstraintsForm = () => {
    router.post(
        route('hr.integrations.scheduling.templates.constraints.store', selectedTemplateForConstraints.value.id),
        templateConstraintsForm.data(),
        {
            preserveScroll: true,
            onSuccess: () => {
                alert('تم حفظ قيود القالب بنجاح!');
                selectedTemplateForConstraints.value = null;
                router.reload({ only: ['templates'] });
            }
        }
    );
};

// --- Individual Constraint Form Logic ---
const individualConstraintsForm = useForm({
    schedulable_id: null,
    schedulable_type: null,
    total_hours_per_week: 40,
    required_days: [6, 0, 1, 2, 3],
    assigned_shift_id: null,
    employment_type: null,
    max_subjects_per_day: 3,
    max_sections_per_day: 3,
    allowed_subjects: [],
    allowed_sections: [],
});

const selectedPerson = computed(() => {
    if (!selectedPersonIdentifier.value || !props.personnel) return null;
    const [type, id] = selectedPersonIdentifier.value.split('-');
    // البحث في جميع الصفحات (قد نحتاج إلى جلب جميع البيانات)
    // للآن، نبحث في الصفحة الحالية فقط
    const found = props.personnel.data?.find(p => p.id === parseInt(id) && p.type === type);
    if (found) return found;

    // إذا لم نجده في الصفحة الحالية، نعيد كائن بسيط
    return {
        id: parseInt(id),
        type: type,
        name: '...', // سيتم تحديثه من props.personnelConstraints
        full_name: '...', // الاسم الكامل
    };
});

watch(selectedPersonIdentifier, (newValue) => {
    if (newValue) {
        const [type, id] = newValue.split('-');
        router.get(route('hr.integrations.scheduling.index'), {
            person_id: id,
            person_type: `App\\Models\\${type}`,
            department_id: selectedDepartmentId.value
        }, {
            preserveState: false, // إعادة جلب البيانات لضمان التحديث
            replace: true,
            preserveScroll: false,
            only: ['personnelConstraints', 'timetables', 'customHours', 'defaultShiftSetting', 'personDepartmentShiftSetting', 'organizationShiftSetting', 'personDepartmentId', 'activePersonIdentifier'] // جلب البيانات المطلوبة فقط
        });
    }
});

// Computed لتحديد إعدادات الدوام المستخدمة
const activeShiftSetting = computed(() => {
    // الأولوية: 1) إعدادات قسم الموظف/المعلم 2) إعدادات المؤسسة العامة
    return props.personDepartmentShiftSetting || props.organizationShiftSetting;
});

// Computed لتحديد مصدر الإعدادات
const shiftSettingSource = computed(() => {
    if (props.personDepartmentShiftSetting) {
        const deptName = props.personDepartmentShiftSetting.department?.name || 'القسم';
        return { type: 'department', name: deptName };
    } else if (props.organizationShiftSetting) {
        return { type: 'organization', name: 'المؤسسة' };
    }
    return null;
});

watch(() => [props.personnelConstraints, props.personDepartmentShiftSetting, props.organizationShiftSetting], ([newConstraints, deptSetting, orgSetting]) => {
    individualConstraintsForm.reset();
    if (selectedPerson.value) {
        const [type, id] = selectedPersonIdentifier.value.split('-');
        individualConstraintsForm.schedulable_id = parseInt(id);
        individualConstraintsForm.schedulable_type = `App\\Models\\${type}`;
    }

    // إذا كانت هناك قيود فردية محفوظة، استخدمها
    if (newConstraints && Object.keys(newConstraints).length > 0) {
         Object.keys(newConstraints).forEach(key => {
            if (individualConstraintsForm.hasOwnProperty(key)) {
                individualConstraintsForm[key] = newConstraints[key];
            }
        });
    } else {
        // إذا لم تكن هناك قيود فردية، استخدم إعدادات القسم/المؤسسة كقيم افتراضية
        const defaultSetting = activeShiftSetting.value;
        if (defaultSetting) {
            // تهيئة القيم من إعدادات الدوام الافتراضية
            if (!individualConstraintsForm.employment_type) {
                // افتراضياً شهري كامل إذا كان هناك إعدادات دوام افتراضية
                individualConstraintsForm.employment_type = 'monthly_full';
            }

            // استخدام أيام العمل من إعدادات القسم/المؤسسة
            if (defaultSetting.work_days && Array.isArray(defaultSetting.work_days)) {
                individualConstraintsForm.required_days = [...defaultSetting.work_days];
            }

            // حساب ساعات العمل الأسبوعية من إعدادات القسم/المؤسسة
            if (defaultSetting.hours_per_week) {
                individualConstraintsForm.total_hours_per_week = defaultSetting.hours_per_week;
            } else if (defaultSetting.start_time && defaultSetting.end_time) {
                // حساب الساعات من أوقات الدوام
                const start = new Date(`2000-01-01T${defaultSetting.start_time}`);
                const end = new Date(`2000-01-01T${defaultSetting.end_time}`);
                if (end < start) {
                    end.setDate(end.getDate() + 1);
                }
                const hoursPerDay = (end - start) / (1000 * 60 * 60);
                const workDaysCount = defaultSetting.work_days?.length || 5;
                individualConstraintsForm.total_hours_per_week = Math.round(hoursPerDay * workDaysCount * 100) / 100;
            }
        }
    }
}, { immediate: true, deep: true });

// Watch for custom hours changes
watch(() => props.customHours, (newCustomHours) => {
    if (newCustomHours && selectedPerson.value) {
        initializeCustomHours();
    }
}, { immediate: true, deep: true });

const applyTemplate = (event) => {
    const templateId = event.target.value;
    if (!templateId) { router.reload({ only: ['personnelConstraints'] }); return; }
    const template = props.templates.find(t => t.id == templateId);
    if (template) {
        individualConstraintsForm.reset();
        template.constraints.forEach(constraint => {
            if (individualConstraintsForm.hasOwnProperty(constraint.constraint_type)) {
                individualConstraintsForm[constraint.constraint_type] = constraint.value;
            }
        });
    }
};

// --- Validation & Summary Functions ---
const validateShiftSelection = () => {
    // التحقق من منطقية اختيار الوردية
    if (individualConstraintsForm.assigned_shift_id) {
        const selectedShift = props.shifts.find(s => s.id === individualConstraintsForm.assigned_shift_id);
        if (selectedShift && activeShiftSetting.value) {
            // يمكن إضافة منطق للتحقق من التوافق
            // Removed console.log for performance
        }
    }
};

// حساب ملخص الجدول المتوقع
const calculateScheduleSummary = computed(() => {
    if (!selectedPerson.value || !individualConstraintsForm.employment_type) {
        return null;
    }

    const summary = {
        employmentType: individualConstraintsForm.employment_type,
        workDays: individualConstraintsForm.required_days || [],
        workDaysCount: (individualConstraintsForm.required_days || []).length,
        totalHoursPerWeek: individualConstraintsForm.total_hours_per_week || 0,
        shift: null,
        startTime: null,
        endTime: null,
        hoursPerDay: 0,
        isValid: true,
        warnings: [],
        departmentSettings: activeShiftSetting.value,
    };

    // إذا كان هناك وردية محددة
    if (individualConstraintsForm.assigned_shift_id) {
        const selectedShift = props.shifts.find(s => s.id === individualConstraintsForm.assigned_shift_id);
        if (selectedShift) {
            summary.shift = selectedShift.name;
            summary.startTime = selectedShift.start_time;
            summary.endTime = selectedShift.end_time;

            // حساب ساعات اليوم
            const start = new Date(`2000-01-01T${selectedShift.start_time}`);
            let end = new Date(`2000-01-01T${selectedShift.end_time}`);
            if (end < start) {
                end.setDate(end.getDate() + 1);
            }
            summary.hoursPerDay = (end - start) / (1000 * 60 * 60);
            summary.totalHoursPerWeek = Math.round(summary.hoursPerDay * summary.workDaysCount * 100) / 100;
        }
    } else if (activeShiftSetting.value) {
        // استخدام إعدادات القسم/المؤسسة
        summary.startTime = activeShiftSetting.value.start_time;
        summary.endTime = activeShiftSetting.value.end_time;

        const start = new Date(`2000-01-01T${activeShiftSetting.value.start_time}`);
        let end = new Date(`2000-01-01T${activeShiftSetting.value.end_time}`);
        if (end < start) {
            end.setDate(end.getDate() + 1);
        }
        summary.hoursPerDay = (end - start) / (1000 * 60 * 60);

        if (summary.totalHoursPerWeek === 0 && activeShiftSetting.value.hours_per_week) {
            summary.totalHoursPerWeek = activeShiftSetting.value.hours_per_week;
        } else if (summary.totalHoursPerWeek === 0) {
            summary.totalHoursPerWeek = Math.round(summary.hoursPerDay * summary.workDaysCount * 100) / 100;
        }
    }

    // التحقق من المنطقية
    if (summary.workDaysCount === 0) {
        summary.isValid = false;
        summary.warnings.push('لم يتم تحديد أيام عمل');
    }

    if (summary.totalHoursPerWeek === 0) {
        summary.isValid = false;
        summary.warnings.push('لم يتم تحديد ساعات العمل الأسبوعية');
    }

    if (summary.workDaysCount > 0 && summary.totalHoursPerWeek > 0) {
        const calculatedHoursPerDay = summary.totalHoursPerWeek / summary.workDaysCount;
        if (calculatedHoursPerDay > 12) {
            summary.warnings.push(`عدد ساعات العمل اليومي (${calculatedHoursPerDay.toFixed(1)}) مرتفع جداً`);
        }
        if (calculatedHoursPerDay < 2) {
            summary.warnings.push(`عدد ساعات العمل اليومي (${calculatedHoursPerDay.toFixed(1)}) منخفض جداً`);
        }
    }

    // التحقق من التوافق مع إعدادات القسم
    if (activeShiftSetting.value && summary.workDaysCount > 0) {
        const deptWorkDays = activeShiftSetting.value.work_days || [];
        const hasMismatch = summary.workDays.some(day => !deptWorkDays.includes(day));
        if (hasMismatch) {
            summary.warnings.push('بعض أيام العمل المحددة لا تتطابق مع إعدادات القسم');
        }
    }

    return summary;
});

// --- Enhanced Validation for Different Employment Types ---
const validateConstraintsForEmploymentType = () => {
    const employmentType = individualConstraintsForm.employment_type;
    const requiredDays = individualConstraintsForm.required_days || [];
    const errors = [];
    const warnings = [];

    if (!employmentType) {
        errors.push('يجب تحديد نوع التوظيف');
        return { isValid: false, errors, warnings };
    }

    // التحقق من الأيام المطلوبة
    if (requiredDays.length === 0) {
        errors.push('يجب تحديد أيام العمل على الأقل');
        return { isValid: false, errors, warnings };
    }

    // للموظفين الشهريين الجزئيين
    if (employmentType === 'monthly_partial') {
        const dayNames = requiredDays.map(d => weekDays.find(w => w.v === d)?.n).filter(Boolean);

        if (requiredDays.length === 0) {
            errors.push('يجب تحديد الأيام المحددة للعمل للموظفين الشهريين الجزئيين');
        } else {
            warnings.push(`الأيام المحددة للعمل: ${dayNames.join('، ')}`);

            // التحقق من وجود ساعات مخصصة
            const hasCustomHours = props.customHours && props.customHours.length > 0;
            if (hasCustomHours) {
                const totalCustomHours = props.customHours.reduce((sum, ch) => sum + (parseFloat(ch.hours) || 0), 0);
                warnings.push(`الساعات المخصصة: ${totalCustomHours.toFixed(1)} ساعة/أسبوع`);
            } else {
                warnings.push('⚠️ لم يتم تحديد ساعات مخصصة - سيتم استخدام إعدادات القسم/المؤسسة');
            }
        }
    }

    // للموظفين بالساعات
    if (employmentType === 'hourly') {
        const totalHours = individualConstraintsForm.total_hours_per_week || 0;
        const dayNames = requiredDays.map(d => weekDays.find(w => w.v === d)?.n).filter(Boolean);

        if (totalHours === 0) {
            errors.push('يجب تحديد إجمالي الساعات الأسبوعية للموظفين بالساعات');
        } else {
            const hoursPerDay = totalHours / requiredDays.length;
            warnings.push(`الأيام المحددة: ${dayNames.join('، ')}`);
            warnings.push(`إجمالي الساعات/أسبوع: ${totalHours.toFixed(1)} ساعة`);
            warnings.push(`متوسط الساعات/يوم: ${hoursPerDay.toFixed(1)} ساعة`);

            // التحقق من وجود ساعات مخصصة
            const hasCustomHours = props.customHours && props.customHours.length > 0;
            if (hasCustomHours) {
                warnings.push('✓ تم تحديد ساعات مخصصة - سيتم استخدامها في الجدول');
            } else {
                warnings.push('⚠️ لم يتم تحديد ساعات مخصصة - يجب تحديد الساعات اليومية لكل يوم');
            }
        }
    }

    // للموظفين الشهريين الكاملين
    if (employmentType === 'monthly_full') {
        const dayNames = requiredDays.map(d => weekDays.find(w => w.v === d)?.n).filter(Boolean);
        warnings.push(`أيام العمل: ${dayNames.join('، ')}`);

        if (activeShiftSetting.value) {
            warnings.push(`سيتم استخدام أوقات القسم: ${formatTimeTo12Hour(activeShiftSetting.value.start_time)} - ${formatTimeTo12Hour(activeShiftSetting.value.end_time)}`);
        }
    }

    return { isValid: errors.length === 0, errors, warnings };
};

const submitIndividualConstraints = async () => {
    // التحقق من صحة البيانات قبل الحفظ
    const summary = calculateScheduleSummary.value;
    if (summary && !summary.isValid) {
        alert('يرجى تصحيح الأخطاء قبل الحفظ:\n' + summary.warnings.join('\n'));
        return;
    }

    // التحقق المحسّن حسب نوع التوظيف
    const validation = validateConstraintsForEmploymentType();
    if (!validation.isValid) {
        alert('يرجى تصحيح الأخطاء التالية:\n' + validation.errors.join('\n'));
        return;
    }

    // تجهيز بيانات dialog التأكيد
    const employmentTypeLabel = individualConstraintsForm.employment_type === 'monthly_full'
        ? 'شهري كامل'
        : individualConstraintsForm.employment_type === 'monthly_partial'
        ? 'شهري جزئي'
        : 'بالساعات';

    confirmDialogData.value = {
        title: 'تأكيد حفظ القيود وإنشاء الجدول',
        employmentType: employmentTypeLabel,
        workDays: (individualConstraintsForm.required_days || []).map(d => weekDays.find(w => w.v === d)?.n).filter(Boolean),
        startTime: summary?.startTime ? formatTimeTo12Hour(summary.startTime) : null,
        endTime: summary?.endTime ? formatTimeTo12Hour(summary.endTime) : null,
        totalHoursPerWeek: summary?.totalHoursPerWeek || 0,
        warnings: summary?.warnings || [],
        notes: validation.warnings || [],
    };

    // عرض dialog التأكيد
    showConfirmDialog.value = true;
};

const handleConfirmConstraints = async () => {
    showConfirmDialog.value = false;

    // حفظ القيود
    await individualConstraintsForm.transform((data) => {
        const payload = {
            schedulable_id: data.schedulable_id,
            schedulable_type: data.schedulable_type,
            total_hours_per_week: data.total_hours_per_week,
            required_days: data.required_days,
            employment_type: data.employment_type,
        };
        if (selectedPerson.value.type === 'Employee') {
            // Always include assigned_shift_id, even if null, to ensure it's removed if not set
            payload.assigned_shift_id = data.assigned_shift_id || null;
        } else if (selectedPerson.value.type === 'Teacher') {
            payload.max_subjects_per_day = data.max_subjects_per_day;
            payload.max_sections_per_day = data.max_sections_per_day;
            payload.allowed_subjects = data.allowed_subjects;
            payload.allowed_sections = data.allowed_sections;
        }
        return payload;
    }).post(route('hr.integrations.scheduling.constraints.store'), {
        preserveScroll: true,
        onSuccess: async () => {
            // بعد حفظ القيود، إنشاء الجدول تلقائياً
            try {
                await generateTimetableForm.transform(data => ({
                    ...data,
                    person_identifier: selectedPersonIdentifier.value
                })).post(route('hr.integrations.scheduling.generate'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        alert('تم حفظ القيود وإنشاء الجدول بنجاح!');
                        router.reload({
                            only: ['timetables', 'personnelConstraints', 'defaultShiftSetting', 'personDepartmentShiftSetting', 'customHours']
                        });
                        // الانتقال تلقائياً إلى تبويب الجدول
                        activePersonTab.value = 'timetable';
                    },
                    onError: (errors) => {
                        console.error('Error generating timetable:', errors);
                        alert('تم حفظ القيود بنجاح، لكن حدث خطأ أثناء إنشاء الجدول. يمكنك إنشاء الجدول يدوياً من تبويب "الجدول الأسبوعي"');
                        router.reload({
                            only: ['personnelConstraints', 'defaultShiftSetting', 'personDepartmentShiftSetting']
                        });
                    }
                });
            } catch (error) {
                // Removed console.error for performance
                alert('تم حفظ القيود بنجاح، لكن حدث خطأ أثناء إنشاء الجدول. يمكنك إنشاء الجدول يدوياً من تبويب "الجدول الأسبوعي"');
                router.reload({
                    only: ['personnelConstraints', 'defaultShiftSetting', 'personDepartmentShiftSetting']
                });
            }
        },
        onError: (errors) => {
            console.error('Error saving constraints:', errors);
            alert('حدث خطأ أثناء حفظ القيود. يرجى المحاولة مرة أخرى.');
        }
    });
};

// --- Delete Individual Constraints ---
const deleteIndividualConstraints = () => {
    if (!selectedPerson.value) {
        alert('يرجى اختيار موظف/معلم أولاً');
        return;
    }

    if (!confirm('هل أنت متأكد من رغبتك في حذف جميع القيود الفردية؟ سيتم تطبيق إعدادات المؤسسة أو القسم بدلاً منها.')) {
        return;
    }

    const [type, id] = selectedPersonIdentifier.value.split('-');
    router.delete(route('hr.integrations.scheduling.constraints.delete'), {
        data: {
            schedulable_id: parseInt(id),
            schedulable_type: `App\\Models\\${type}`,
        },
        preserveScroll: true,
        onSuccess: () => {
            // إعادة جلب البيانات بعد الحذف
            router.reload({
                only: ['personnelConstraints', 'defaultShiftSetting', 'personDepartmentShiftSetting', 'organizationShiftSetting'],
                preserveState: true,
            });
        },
        onError: (errors) => {
            console.error('Error deleting constraints:', errors);
            alert('حدث خطأ أثناء حذف القيود. يرجى المحاولة مرة أخرى.');
        }
    });
};

// --- Apply Default Shift Settings ---
const removeShiftAssignment = () => {
    if (!selectedPerson.value) {
        alert('يرجى اختيار موظف/معلم أولاً');
        return;
    }

    if (!props.personnelConstraints?.assigned_shift_assignment) {
        alert('لا توجد وردية محددة لإزالتها');
        return;
    }

    if (!confirm('هل أنت متأكد من إزالة الوردية المحددة؟ سيتم الاعتماد على إعدادات المؤسسة/القسم بدلاً منها.')) {
        return;
    }

    const [type, id] = selectedPersonIdentifier.value.split('-');
    router.delete(route('hr.integrations.shift-assignments.destroy'), {
        data: {
            shiftable_id: parseInt(id),
            shiftable_type: `App\\Models\\${type}`,
        },
        preserveScroll: true,
        onSuccess: () => {
            alert('تم إزالة الوردية المحددة بنجاح.');
            router.reload({
                only: ['personnelConstraints', 'timetables', 'defaultShiftSetting', 'personDepartmentShiftSetting', 'organizationShiftSetting']
            });
        },
        onError: (errors) => {
            console.error('Error removing shift assignment:', errors);
            alert('حدث خطأ أثناء إزالة الوردية. يرجى المحاولة مرة أخرى.');
        }
    });
};

const applyDefaultShiftSettings = () => {
    if (!selectedPerson.value) {
        alert('يرجى اختيار موظف/معلم أولاً');
        return;
    }

    if (!activeShiftSetting.value) {
        alert('لا توجد إعدادات دوام افتراضية متاحة للمؤسسة أو القسم');
        return;
    }

    if (!confirm(`هل أنت متأكد من رغبتك في تطبيق إعدادات ${shiftSettingSource.value?.name || 'المؤسسة'}؟ سيتم حذف القيود الفردية الحالية.`)) {
        return;
    }

    // حذف القيود الفردية أولاً
    const [type, id] = selectedPersonIdentifier.value.split('-');
    router.delete(route('hr.integrations.scheduling.constraints.delete'), {
        data: {
            schedulable_id: parseInt(id),
            schedulable_type: `App\\Models\\${type}`,
        },
        preserveScroll: true,
        onSuccess: () => {
            // تطبيق إعدادات المؤسسة/القسم
            individualConstraintsForm.reset();
            individualConstraintsForm.schedulable_id = parseInt(id);
            individualConstraintsForm.schedulable_type = `App\\Models\\${type}`;

            // تطبيق القيم من إعدادات الدوام الافتراضية
            if (activeShiftSetting.value) {
                individualConstraintsForm.employment_type = 'monthly_full';

                // إزالة assigned_shift_id للاعتماد على إعدادات المؤسسة/القسم
                individualConstraintsForm.assigned_shift_id = null;

                if (activeShiftSetting.value.work_days && Array.isArray(activeShiftSetting.value.work_days)) {
                    individualConstraintsForm.required_days = [...activeShiftSetting.value.work_days];
                }

                if (activeShiftSetting.value.hours_per_week) {
                    individualConstraintsForm.total_hours_per_week = activeShiftSetting.value.hours_per_week;
                } else if (activeShiftSetting.value.start_time && activeShiftSetting.value.end_time) {
                    const start = new Date(`2000-01-01T${activeShiftSetting.value.start_time}`);
                    const end = new Date(`2000-01-01T${activeShiftSetting.value.end_time}`);
                    if (end < start) {
                        end.setDate(end.getDate() + 1);
                    }
                    const hoursPerDay = (end - start) / (1000 * 60 * 60);
                    const workDaysCount = activeShiftSetting.value.work_days?.length || 5;
                    individualConstraintsForm.total_hours_per_week = Math.round(hoursPerDay * workDaysCount * 100) / 100;
                }
            }

            // حفظ القيود الجديدة
            submitIndividualConstraints();
        },
        onError: (errors) => {
            console.error('Error applying default settings:', errors);
            alert('حدث خطأ أثناء تطبيق الإعدادات. يرجى المحاولة مرة أخرى.');
        }
    });
};

// --- Timetable Generation & View Logic ---
const generateTimetableForm = useForm({});
const generateTimetable = () => {
    // التحقق من وجود قيود محفوظة
    if (!props.personnelConstraints || Object.keys(props.personnelConstraints).length === 0) {
        alert('يرجى حفظ القيود أولاً قبل إنشاء الجدول!');
        return;
    }

    // التحقق من صحة القيود
    const summary = calculateScheduleSummary.value;
    if (summary && !summary.isValid) {
        alert('يرجى تصحيح القيود قبل إنشاء الجدول:\n' + summary.warnings.join('\n'));
        return;
    }

    // عرض ملخص الجدول قبل التأكيد
    let confirmMessage = 'هل أنت متأكد من رغبتك في إنشاء الجدول؟\n\n';
    if (summary) {
        confirmMessage += `ملخص الجدول:\n`;
        confirmMessage += `- نوع التوظيف: ${summary.employmentType === 'monthly_full' ? 'شهري كامل' : summary.employmentType === 'monthly_partial' ? 'شهري جزئي' : 'بالساعات'}\n`;
        confirmMessage += `- أيام العمل: ${summary.workDaysCount} يوم\n`;
        confirmMessage += `- إجمالي الساعات/أسبوع: ${summary.totalHoursPerWeek.toFixed(1)} ساعة\n`;
        if (summary.shift) {
            confirmMessage += `- الوردية: ${summary.shift}\n`;
        }
        if (summary.startTime && summary.endTime) {
            confirmMessage += `- أوقات العمل: ${formatTimeTo12Hour(summary.startTime)} - ${formatTimeTo12Hour(summary.endTime)}\n`;
        }
        if (summary.warnings.length > 0) {
            confirmMessage += `\nتحذيرات:\n${summary.warnings.join('\n')}\n`;
        }
    }
    confirmMessage += '\nسيتم حذف جميع الجداول القديمة وإنشاء جداول جديدة.';

    if (confirm(confirmMessage)) {
        generateTimetableForm.transform(data => ({ ...data, person_identifier: selectedPersonIdentifier.value }))
            .post(route('hr.integrations.scheduling.generate'), {
                preserveScroll: true,
                onSuccess: () => {
                    alert('تم إنشاء الجدول بنجاح!');
                    router.reload({ only: ['timetables', 'personnelConstraints'] });
                },
                onError: (errors) => {
                    console.error('Error generating timetable:', errors);
                    alert('حدث خطأ أثناء إنشاء الجدول. يرجى المحاولة مرة أخرى.');
                }
            });
    }
};

const printTimetable = () => {
    // Set document title to include person name for PDF filename
    const originalTitle = document.title;
    if (selectedPerson.value) {
        const personName = (selectedPerson.value.full_name || selectedPerson.value.name || 'جدول')
            .replace(/\s+/g, '_')
            .replace(/[^\w\u0600-\u06FF_]/g, ''); // Remove special characters, keep Arabic and alphanumeric
        const personType = selectedPerson.value.type === 'Employee' ? 'موظف' : 'معلم';
        const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
        document.title = `الجدول_الأسبوعي_${personType}_${personName}_${dateStr}`;

        // Also update the page title in Head component if available
        const headTitle = document.querySelector('title');
        if (headTitle) {
            headTitle.textContent = document.title;
        }
    }

    // Trigger print
    window.print();

    // Restore original title after print dialog closes
    setTimeout(() => {
        document.title = originalTitle;
        const headTitle = document.querySelector('title');
        if (headTitle) {
            headTitle.textContent = originalTitle;
        }
    }, 2000);
};

// Note: weekDays and initializeCustomHours are defined earlier in the file

const openCustomHoursModal = () => {
    initializeCustomHours();
    showCustomHoursModal.value = true;
};

const submitCustomHours = () => {
    customHoursForm.transform(data => ({
        ...data,
        hours: data.hours.map(h => ({
            ...h,
            start_time: h.start_time || null,
            end_time: h.end_time || null,
        }))
    })).post(route('hr.integrations.scheduling.custom-hours.store'), {
        preserveScroll: true,
        onSuccess: async () => {
            showCustomHoursModal.value = false;

            // إعادة إنشاء الجدول تلقائياً بعد حفظ الساعات المخصصة
            if (selectedPersonIdentifier.value) {
                try {
                    await generateTimetableForm.transform(data => ({
                        ...data,
                        person_identifier: selectedPersonIdentifier.value
                    })).post(route('hr.integrations.scheduling.generate'), {
                        preserveScroll: true,
                        onSuccess: () => {
                            alert('تم حفظ الساعات المخصصة وإعادة إنشاء الجدول بنجاح!');
                            router.reload({
                                only: ['timetables', 'customHours', 'personnelConstraints']
                            });
                        },
                        onError: (errors) => {
                            console.error('Error generating timetable:', errors);
                            alert('تم حفظ الساعات المخصصة بنجاح، لكن حدث خطأ أثناء إعادة إنشاء الجدول. يرجى إنشاء الجدول يدوياً من زر "إنشاء / تحديث الجدول"');
                            router.reload({ only: ['customHours'] });
                        }
                    });
                } catch (error) {
                    console.error('Error generating timetable:', error);
                    alert('تم حفظ الساعات المخصصة بنجاح، لكن حدث خطأ أثناء إعادة إنشاء الجدول. يرجى إنشاء الجدول يدوياً من زر "إنشاء / تحديث الجدول"');
                    router.reload({ only: ['customHours'] });
                }
            } else {
                alert('تم حفظ الساعات المخصصة بنجاح!');
                router.reload({ only: ['customHours'] });
            }
        },
    });
};

const calculateTotalCustomHours = computed(() => {
    return customHoursForm.hours.reduce((total, day) => total + (parseFloat(day.hours) || 0), 0).toFixed(2);
});

const isHourlyEmployee = computed(() => {
    return props.personnelConstraints?.employment_type === 'hourly';
});

// --- Drag and Drop Logic ---
const handleDragStart = (event, entry) => {
    if (!isEditMode.value) return;
    draggedEntry.value = entry;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/html', event.target);
    const target = event.target.closest('.rounded-lg');
    if (target) {
        target.style.opacity = '0.5';
        target.style.cursor = 'grabbing';
    }
};

// Throttle function for performance
const throttle = (func, limit) => {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
};

const handleDragOver = throttle((event, day, slot) => {
    if (!isEditMode.value) return;

    // Handle entry dragging (only on empty cells)
    if (draggedEntry.value) {
        event.preventDefault();
        event.stopPropagation();
        event.dataTransfer.dropEffect = 'move';
        dragOverCell.value = { day, slot };

        // Visual feedback - only on empty cells
        const targetCell = event.currentTarget.tagName === 'TD' ? event.currentTarget : event.currentTarget.closest('td');
        if (targetCell && !targetCell.querySelector('.rounded-lg')) {
            if (!targetCell.classList.contains('bg-blue-50')) {
                targetCell.classList.add('bg-blue-50', 'border-blue-300');
            }
        }
    }
}, 50); // Throttle to 50ms for better performance

const handleDragLeave = (event) => {
    // Only process if we're actually leaving the cell, not entering a child
    if (event.currentTarget && !event.currentTarget.contains(event.relatedTarget)) {
        const targetCell = event.currentTarget.tagName === 'TD' ? event.currentTarget : event.currentTarget.closest('td');
        if (targetCell) {
            // Remove all possible color classes
            targetCell.classList.remove('bg-blue-50', 'border-blue-300', 'bg-orange-50', 'border-orange-300', 'bg-yellow-50', 'border-yellow-300', 'bg-purple-50', 'border-purple-300', 'bg-green-50', 'border-green-300', 'bg-gray-50', 'border-gray-300', 'border-2');
            // Reset entry styling if exists
            const entryDiv = targetCell.querySelector('.rounded-lg');
            if (entryDiv) {
                entryDiv.style.opacity = '';
                entryDiv.style.border = '';
            }
        }
    }
};

const handleDragEnd = (event) => {
    const target = event.target.closest('.rounded-lg');
    if (target) {
        target.style.opacity = '1';
        target.style.cursor = 'grab';
    }
    dragOverCell.value = null;

    // Remove all drag over styles efficiently
    const coloredCells = document.querySelectorAll('td.bg-orange-50, td.bg-yellow-50, td.bg-blue-50, td.bg-purple-50, td.bg-green-50, td.bg-gray-50');
    coloredCells.forEach(cell => {
        cell.classList.remove('bg-orange-50', 'border-orange-300', 'bg-yellow-50', 'border-yellow-300', 'bg-blue-50', 'border-blue-300', 'bg-purple-50', 'border-purple-300', 'bg-green-50', 'border-green-300', 'bg-gray-50', 'border-gray-300', 'border-2');
        const entryDiv = cell.querySelector('.rounded-lg');
        if (entryDiv) {
            entryDiv.style.opacity = '';
            entryDiv.style.border = '';
        }
    });
};

const handleDrop = async (event, day, slot) => {
    if (!isEditMode.value) return;
    event.preventDefault();
    event.stopPropagation();

    // Handle entry dragging (moving existing entries)
    if (!draggedEntry.value) return;

    const entry = draggedEntry.value;
    const newStartTime = slot.start;

    // Calculate duration
    const start = new Date(`2000-01-01T${entry.start_time}`);
    const end = new Date(`2000-01-01T${entry.end_time}`);
    if (end < start) end.setDate(end.getDate() + 1);
    const durationMinutes = (end - start) / (1000 * 60);

    const newEnd = new Date(`2000-01-01T${newStartTime}`);
    newEnd.setMinutes(newEnd.getMinutes() + durationMinutes);
    const calculatedEndTime = newEnd.toTimeString().slice(0, 5);

    // Visual feedback
    const targetCell = event.target.closest('td');
    if (targetCell) {
        targetCell.classList.add('bg-green-100');
        setTimeout(() => targetCell.classList.remove('bg-green-100'), 500);
    }

    try {
        const response = await fetch(route('hr.integrations.scheduling.timetable-entries.update', entry.id), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                day_of_week: day.v,
                start_time: newStartTime,
                end_time: calculatedEndTime,
            }),
        });

        if (response.ok) {
            router.reload({ only: ['timetables'] });
        } else {
            const errorData = await response.json();
            alert('حدث خطأ أثناء تحديث الجدول: ' + (errorData.message || 'خطأ غير معروف'));
        }
    } catch (error) {
        // Removed console.error for performance
        alert('حدث خطأ أثناء تحديث الجدول');
    }

    draggedEntry.value = null;
    dragOverCell.value = null;
};

const handleCellClick = async (day, slot) => {
    if (!isEditMode.value) return;

    // Check if day is locked
    if (isDayLocked.value[day.v]) {
        alert('هذا اليوم محظور حسب إعدادات القسم. يجب تحديده في القيود أولاً.');
        return;
    }

    // For monthly employees, auto-fill full work day
    if (isMonthlyEmployee.value) {
        const defaultHours = getDefaultWorkHours.value;

        // Check if there's already an entry for this day
        const existingEntry = props.timetables?.find(entry =>
            entry.schedulable_id === selectedPerson.value.id &&
            entry.schedulable_type === `App\\Models\\${selectedPerson.value.type}` &&
            entry.day_of_week === day.v &&
            !entry.is_break
        );

        if (existingEntry) {
            // Open break period modal for this work day
            handleEntryClick(existingEntry);
            return;
        }

        // Create full day entry automatically
        if (selectedPerson.value) {
            const [type, id] = selectedPersonIdentifier.value.split('-');

            entryForm.schedulable_id = parseInt(id);
            entryForm.schedulable_type = `App\\Models\\${type}`;
            entryForm.day_of_week = day.v;
            entryForm.start_time = defaultHours.start;
            entryForm.end_time = defaultHours.end;
            entryForm.work_type = props.personnelConstraints?.employment_type || 'monthly_full';
            entryForm.is_break = false;

            // Auto-submit for monthly employees
            await submitEntryForm();
            return;
        }
    }

    // For hourly employees or manual entry, open modal
    editingEntry.value = null;
    showEntryModal.value = true;
    nextTick(() => {
        if (selectedPerson.value) {
            const [type, id] = selectedPersonIdentifier.value.split('-');
            entryForm.schedulable_id = parseInt(id);
            entryForm.schedulable_type = `App\\Models\\${type}`;
            entryForm.day_of_week = day.v;
            entryForm.start_time = slot.start;
            entryForm.end_time = slot.end;
        }
    });
};

const handleEntryClick = (entry) => {
    if (!isEditMode.value) return;

    console.log('=== HANDLE ENTRY CLICK - START (BREAK PERIODS ONLY) ===');
    console.log('Entry:', entry);
    console.log('selectedPerson:', selectedPerson.value);
    console.log('selectedPersonIdentifier:', selectedPersonIdentifier.value);
    console.log('employment_type:', props.personnelConstraints?.employment_type);

    // Check if person is selected
    if (!selectedPerson.value || !selectedPersonIdentifier.value) {
        // Removed console.error for performance
        alert('يرجى اختيار موظف أو معلم أولاً');
        return;
    }

    // Allow editing any entry type (work, break, meeting, etc.)
    editingEntry.value = entry;
    showEntryModal.value = true;

    nextTick(() => {
        // Safely get person identifier
        if (!selectedPersonIdentifier.value) {
            // Removed console.error for performance
            alert('خطأ: لم يتم تحديد الموظف');
            showEntryModal.value = false;
            return;
        }

        const [type, id] = selectedPersonIdentifier.value.split('-');

        // Always set from selected person
        entryForm.schedulable_id = parseInt(id);
        entryForm.schedulable_type = `App\\Models\\${type}`;

        // Set day from entry
        entryForm.day_of_week = entry.day_of_week;

        // Use entry times
        entryForm.start_time = entry.start_time ? entry.start_time.substring(0, 5) : '';
        entryForm.end_time = entry.end_time ? entry.end_time.substring(0, 5) : '';

        // Always work type
        entryForm.entry_type = 'work';
        entryForm.title = null;

        // Always set work_type from constraints (never change it)
        entryForm.work_type = props.personnelConstraints?.employment_type || entry.work_type || null;

        // Always work type
        entryForm.is_break = false;

        // Keep shift_id, subject_id, section_id if they exist (for work entries)
        entryForm.shift_id = entry.shift_id || null;
        entryForm.subject_id = entry.subject_id || null;
        entryForm.section_id = entry.section_id || null;

        // Remove shift_id, subject_id, section_id for non-work entries
        if (entryForm.entry_type !== 'work') {
            entryForm.shift_id = null;
            entryForm.subject_id = null;
            entryForm.section_id = null;
        }

        // Removed console.log for performance
    });
};

const deleteEntry = async (entry) => {
    if (!confirm('هل أنت متأكد من حذف هذا الإدخال؟')) return;

    router.delete(route('hr.integrations.scheduling.timetable-entries.delete', entry.id), {
        preserveScroll: true,
        onSuccess: () => {
            alert('تم حذف الإدخال بنجاح!');
            // Force full page reload to ensure fresh timetables
            router.reload();
        },
        onError: (errors) => {
            alert('حدث خطأ أثناء حذف الإدخال: ' + (errors?.message || 'خطأ غير معروف'));
        },
    });
};

// --- Time Drag Adjustment (تعديل الوقت بالسحب) ---
const timeDragState = ref({
    isDragging: false,
    entry: null,
    type: null, // 'start' or 'end'
    initialY: 0,
    initialTime: null,
});

const handleTimeDragStart = (event, entry, type) => {
    if (!isEditMode.value) return;
    event.preventDefault();
    event.stopPropagation();

    timeDragState.value = {
        isDragging: true,
        entry: entry,
        type: type,
        initialY: event.clientY,
        initialTime: type === 'start' ? entry.start_time : entry.end_time,
    };

    document.addEventListener('mousemove', handleTimeDrag);
    document.addEventListener('mouseup', handleTimeDragEnd);
};

const handleTimeDrag = (event) => {
    if (!timeDragState.value.isDragging) return;

    const deltaY = event.clientY - timeDragState.value.initialY;
    const slotHeight = 30; // 30 minutes per slot
    const slotsToAdjust = Math.round(deltaY / 20); // Adjust sensitivity

    if (slotsToAdjust === 0) return;

    const currentTime = timeDragState.value.initialTime;
    const [hours, minutes] = currentTime.split(':').map(Number);
    const totalMinutes = hours * 60 + minutes;
    const newTotalMinutes = totalMinutes + (slotsToAdjust * slotHeight);

    // Clamp to valid time range (0-23:59)
    const clampedMinutes = Math.max(0, Math.min(23 * 60 + 59, newTotalMinutes));
    const newHours = Math.floor(clampedMinutes / 60);
    const newMins = clampedMinutes % 60;
    const newTime = `${newHours.toString().padStart(2, '0')}:${newMins.toString().padStart(2, '0')}`;

    // Update entry form temporarily for visual feedback
    if (timeDragState.value.type === 'start') {
        entryForm.start_time = newTime;
    } else {
        entryForm.end_time = newTime;
    }
};

const handleTimeDragEnd = async () => {
    if (!timeDragState.value.isDragging) return;

    const entry = timeDragState.value.entry;
    const newStartTime = timeDragState.value.type === 'start' ? entryForm.start_time : entry.start_time.substring(0, 5);
    const newEndTime = timeDragState.value.type === 'end' ? entryForm.end_time : entry.end_time.substring(0, 5);

    // Validate times
    if (newStartTime >= newEndTime) {
        alert('وقت البدء يجب أن يكون قبل وقت النهاية');
        entryForm.start_time = entry.start_time.substring(0, 5);
        entryForm.end_time = entry.end_time.substring(0, 5);
    } else {
        // Update the entry
        try {
            const response = await fetch(route('hr.integrations.scheduling.timetable-entries.update', entry.id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    start_time: newStartTime + ':00',
                    end_time: newEndTime + ':00',
                }),
            });

            if (response.ok) {
                router.reload({ only: ['timetables'] });
            }
        } catch (error) {
            console.error('Error updating time:', error);
            alert('حدث خطأ أثناء تحديث الوقت');
        }
    }

    timeDragState.value = {
        isDragging: false,
        entry: null,
        type: null,
        initialY: 0,
        initialTime: null,
    };

    document.removeEventListener('mousemove', handleTimeDrag);
    document.removeEventListener('mouseup', handleTimeDragEnd);
};

// --- Entry Form ---
const entryForm = useForm({
    schedulable_id: null,
    schedulable_type: null,
    day_of_week: null,
    start_time: '',
    end_time: '',
    shift_id: null,
    work_type: null,
    subject_id: null,
    section_id: null,
    is_break: false,
    entry_type: 'work', // Always work type
    title: null,
});

const submitEntryForm = async () => {

    if (!selectedPerson.value) {
        // Removed console.error for performance
        alert('يرجى اختيار موظف أو معلم أولاً');
        return;
    }

    // Ensure schedulable_id and schedulable_type are set
    if (!entryForm.schedulable_id || !entryForm.schedulable_type) {
        const [type, id] = selectedPersonIdentifier.value.split('-');
        entryForm.schedulable_id = parseInt(id);
        entryForm.schedulable_type = `App\\Models\\${type}`;
        console.log('Set schedulable_id and schedulable_type:', entryForm.schedulable_id, entryForm.schedulable_type);
    }

    // Always set work_type from constraints (never allow changing it)
    if (props.personnelConstraints?.employment_type) {
        entryForm.work_type = props.personnelConstraints.employment_type;
        // Removed console.log for performance
    }

    // Set is_break based on entry_type (for backward compatibility)
    // Always work type
    entryForm.entry_type = 'work';
    entryForm.is_break = false;

    // Remove shift_id for non-work entries
    if (entryForm.entry_type !== 'work') {
        entryForm.shift_id = null;
    }

    // Validate required fields
    if (!entryForm.start_time || !entryForm.end_time || entryForm.day_of_week === null) {
        alert('يرجى ملء جميع الحقول المطلوبة');
        return;
    }

    const isEditing = !!editingEntry.value;
    const entryId = editingEntry.value?.id;

    // Ensure times are in correct format (HH:mm:ss)
    // Get current values from form (they may have been changed in the modal)
    let startTimeValue = entryForm.start_time || '';
    let endTimeValue = entryForm.end_time || '';

    // Convert HH:mm to HH:mm:ss if needed (for time input type, format is HH:mm)
    if (startTimeValue && startTimeValue.includes(':') && startTimeValue.length === 5 && !startTimeValue.includes(':', 3)) {
        startTimeValue = startTimeValue + ':00';
    }
    if (endTimeValue && endTimeValue.includes(':') && endTimeValue.length === 5 && !endTimeValue.includes(':', 3)) {
        endTimeValue = endTimeValue + ':00';
    }

    // Ensure we have valid times
    if (!startTimeValue || !endTimeValue) {
        alert('يرجى تحديد وقت البداية والنهاية');
        return;
    }

    // Update form values directly before submission
    entryForm.start_time = startTimeValue;
    entryForm.end_time = endTimeValue;

    try {
        if (isEditing) {
            entryForm.put(route('hr.integrations.scheduling.timetable-entries.update', entryId), {
                preserveScroll: true,
                onSuccess: () => {
                    showEntryModal.value = false;
                    editingEntry.value = null;
                    entryForm.reset();
                    alert('تم تحديث الإدخال بنجاح!');
                    // Force full page reload to ensure fresh timetables
                    router.reload();
                },
                onError: (errors) => {
                    alert('حدث خطأ أثناء تحديث الإدخال: ' + (entryForm.errors?.message || 'خطأ غير معروف'));
                },
            });
        } else {
            entryForm.post(route('hr.integrations.scheduling.timetable-entries.store'), {
                preserveScroll: true,
                onSuccess: () => {
                    showEntryModal.value = false;
                    editingEntry.value = null;
                    entryForm.reset();
                    alert('تم إضافة الإدخال بنجاح!');
                    // Force full page reload to ensure fresh timetables
                    router.reload();
                },
                onError: (errors) => {
                    alert('حدث خطأ أثناء إضافة الإدخال: ' + (entryForm.errors?.message || 'خطأ غير معروف'));
                },
            });
        }
    } catch (error) {
        alert('حدث خطأ غير متوقع: ' + (error.message || 'خطأ غير معروف'));
    }
};

// --- Computed Properties for Day Restrictions ---
const isDayRestricted = (dayValue) => {
    // إذا كان اليوم في required_days، فهو غير محظور
    if (individualConstraintsForm.required_days?.includes(dayValue)) {
        return false;
    }

    // إذا كان اليوم عطلة في إعدادات القسم ولم يتم تحديده في required_days، فهو محظور
    if (activeShiftSetting.value && activeShiftSetting.value.work_days) {
        const isInDepartmentWorkDays = activeShiftSetting.value.work_days.includes(dayValue);
        // إذا لم يكن في أيام عمل القسم، فهو محظور
        if (!isInDepartmentWorkDays) {
            return true;
        }
    }

    return false;
};

const processedTimetableData = computed(() => {
    if (!selectedPerson.value) return { slots: [], data: [] };

    // Always start with department shift settings as the base range
    // This ensures the full work day is displayed, even if there are no entries yet
    let startHour = 6;
    let endHour = 22;

    // استخدام إعدادات القسم/المؤسسة كأولوية أولى
    if (activeShiftSetting.value) {
        const startTime = activeShiftSetting.value.start_time;
        const endTime = activeShiftSetting.value.end_time;

        console.log('=== TIMETABLE RANGE CALCULATION ===');
        console.log('activeShiftSetting:', activeShiftSetting.value);
        console.log('startTime:', startTime);
        console.log('endTime:', endTime);

        if (startTime) {
            const startHourParsed = parseInt(startTime.split(':')[0]);
            const startMinutes = parseInt(startTime.split(':')[1] || '0');
            startHour = startMinutes > 0 ? startHourParsed : startHourParsed;
            console.log('Calculated startHour:', startHour);
        }
        if (endTime) {
            const endHourParsed = parseInt(endTime.split(':')[0]);
            const endMinutes = parseInt(endTime.split(':')[1] || '0');
            // Ensure we include the full hour where end_time falls
            // If end_time is 17:00, we want to show slots until 17:00-17:30 (so endHour = 17)
            // If end_time is 17:30, we want to show slots until 18:00-18:30 (so endHour = 18)
            // Always add 1 to ensure we show the slot that contains the end time
            // This ensures the full work day is displayed
            if (endMinutes > 0) {
                // If there are minutes (e.g., 17:30), show until the next hour slot (18:00-18:30)
                endHour = endHourParsed + 1;
            } else {
                // If it's exactly on the hour (e.g., 17:00), show until that hour's second slot (17:00-17:30)
                endHour = endHourParsed + 1;
            }
            console.log('Calculated endHour:', endHour, '(from endTime:', endTime, ')');
        }

        console.log('Final range:', startHour, '-', endHour);
    }

    // Filter entries for selected person
    // Note: schedulable_type format is "App\Models\Employee" or "App\Models\Teacher"
    // selectedPerson.value.type is "Employee" or "Teacher"
    const personTimetable = props.timetables.filter(entry => {
        if (!entry || !selectedPerson.value) return false;

        const matchesPerson = entry.schedulable_id === selectedPerson.value.id;
        const matchesType = entry.schedulable_type && (
            entry.schedulable_type.includes(selectedPerson.value.type) ||
            entry.schedulable_type === `App\\Models\\${selectedPerson.value.type}`
        );

        return matchesPerson && matchesType;
    });

    // Debug: Log filtered entries to verify they're being loaded
    if (personTimetable.length > 0 && selectedPerson.value) {
        console.log('=== PERSON TIMETABLE DEBUG ===');
        console.log('Selected Person:', selectedPerson.value.id, selectedPerson.value.type);
        console.log('Total entries found:', personTimetable.length);
        console.log('All entries:', personTimetable.map(e => ({
            id: e.id,
            day: e.day_of_week,
            start: e.start_time,
            end: e.end_time,
            entry_type: e.entry_type,
            is_break: e.is_break
        })));
    }

    // Store the base range from department/organization settings
    // This ensures we always show the full work day, even if there are no entries yet
    const baseStartHour = startHour;
    const baseEndHour = endHour;

    // Extend range ONLY if there are entries outside the department shift hours
    // This way, the full work day is always displayed, and we can add breaks/meals within it
    if (personTimetable.length > 0) {
        const allStartHours = personTimetable.map(e => {
            const hour = parseInt(e.start_time.split(':')[0]);
            return hour;
        });
        const allEndHours = personTimetable.map(e => {
            const hour = parseInt(e.end_time.split(':')[0]);
            const minutes = parseInt(e.end_time.split(':')[1] || '0');
            // Calculate end hour: if minutes > 0, we need the next hour slot
            return minutes > 0 ? hour + 1 : hour + 1;
        });
        const minStart = Math.min(...allStartHours);
        const maxEnd = Math.max(...allEndHours);
        // Only extend if entries are outside the base range
        if (minStart < startHour) startHour = minStart;
        if (maxEnd > endHour) endHour = maxEnd;

        console.log('Extended range based on entries:', startHour, '-', endHour);
    } else if (props.personnelConstraints) {
        // إذا كان هناك وردية محددة، استخدم أوقاتها
        const shiftId = props.personnelConstraints.assigned_shift_id;
        if (shiftId) {
            const shift = props.shifts.find(s => s.id === shiftId);
            if (shift) {
                const shiftStartHour = parseInt(shift.start_time.split(':')[0]);
                const shiftEndHour = parseInt(shift.end_time.split(':')[0]);
                const shiftEndMinutes = parseInt(shift.end_time.split(':')[1] || '0');
                startHour = Math.min(startHour, shiftStartHour);
                // Ensure we include the full hour where shift ends
                endHour = Math.max(endHour, shiftEndMinutes > 0 ? shiftEndHour + 1 : shiftEndHour + 1);
            }
        }
    }

    // CRITICAL: Ensure we always show at least the full department shift range
    // This guarantees the complete work day is displayed, even if entries don't extend that far
    // Never allow the range to be smaller than the base department/organization shift settings
    if (baseStartHour !== undefined && startHour > baseStartHour) {
        startHour = baseStartHour;
    }
    if (baseEndHour !== undefined && endHour < baseEndHour) {
        endHour = baseEndHour;
    }

    console.log('Final timetable range:', startHour, '-', endHour, 'hours');
    console.log('Base range was:', baseStartHour, '-', baseEndHour, 'hours');

    // Create 30-minute slots based on the calculated range
    const timeSlots = [];
    for (let hour = startHour; hour <= endHour; hour++) {
        for (let minute = 0; minute < 60; minute += 30) {
            const start = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
            const endMinute = minute + 30;
            const endHourCalc = endMinute >= 60 ? hour + 1 : hour;
            const endMinuteCalc = endMinute >= 60 ? endMinute - 60 : endMinute;
            const end = `${endHourCalc.toString().padStart(2, '0')}:${endMinuteCalc.toString().padStart(2, '0')}`;
        timeSlots.push({ start, end });
        }
    }

    const data = [];
    const rowspanCounters = { 6: 0, 0: 0, 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
    const usedEntries = new Set(); // Track which entries have been displayed
    const workEntryRowspans = {}; // Track work entries that use rowspan: { day: { entryId: rowspan } }

    timeSlots.forEach(slot => {
        const row = { time: slot, days: {} };
        weekDays.forEach(day => {
            // Check if we're in the middle of a work entry's rowspan
            // But allow non-work entries to show even if there's a work entry rowspan
            const hasWorkRowspan = rowspanCounters[day.v] > 0;

            if (hasWorkRowspan) {
                // We're in the middle of a work entry's rowspan
                // Still check for non-work entries that should be displayed
                const overlappingNonWorkEntries = personTimetable.filter(e => {
                    if (e.day_of_week != day.v) return false;
                    if (usedEntries.has(e.id)) return false;

                    const entryStart = e.start_time ? e.start_time.substring(0, 5) : '';
                    const entryEnd = e.end_time ? e.end_time.substring(0, 5) : '';
                    if (!entryStart || !entryEnd) return false;

                    const overlaps = entryStart < slot.end && entryEnd > slot.start;
                    if (!overlaps) return false;

                    // Only show non-work entries
                    const entryIsWork = e.entry_type === 'work' || (!e.entry_type && !e.is_break);
                    return !entryIsWork;
                });

                if (overlappingNonWorkEntries.length > 0) {
                    // There's a non-work entry - display it
                    const entry = overlappingNonWorkEntries[0];
                    const entryStart = entry.start_time ? entry.start_time.substring(0, 5) : '';
                    const entryStartsAtThisSlot = entryStart === slot.start;

                    if (entryStartsAtThisSlot) {
                        // This non-work entry starts here - display it
                        const startTimeStr = entry.start_time || '';
                        const endTimeStr = entry.end_time || '';
                        const startTimeFormatted = startTimeStr.length === 5 ? startTimeStr + ':00' : startTimeStr;
                        const endTimeFormatted = endTimeStr.length === 5 ? endTimeStr + ':00' : endTimeStr;
                        const start = new Date(`2000-01-01T${startTimeFormatted}`);
                        const end = new Date(`2000-01-01T${endTimeFormatted}`);
                        if (end < start) end.setDate(end.getDate() + 1);
                        const durationMinutes = (end - start) / (1000 * 60);
                        const rowspan = Math.max(1, Math.ceil(durationMinutes / 30));

                        usedEntries.add(entry.id);
                        row.days[day.v] = {
                            render: true,
                            rowspan: rowspan,
                            entry: entry,
                            isRestricted: isDayRestricted(day.v),
                            allEntries: overlappingNonWorkEntries
                        };
                        // Don't set rowspanCounters for non-work entries when there's already a work rowspan
                    } else {
                        // Non-work entry doesn't start here - skip
                row.days[day.v] = { render: false };
                    }
                } else {
                    // No non-work entries - just continue the work entry rowspan
                    row.days[day.v] = { render: false };
                }

                rowspanCounters[day.v]--;
            } else {
                // Find all entries that overlap with this time slot and haven't been displayed yet
                const overlappingEntries = personTimetable.filter(e => {
                    // Check day match
                    if (e.day_of_week != day.v) return false;

                    // Skip already displayed entries
                    if (usedEntries.has(e.id)) return false;

                    // Extract time in HH:mm format for comparison
                    const entryStart = e.start_time ? e.start_time.substring(0, 5) : '';
                    const entryEnd = e.end_time ? e.end_time.substring(0, 5) : '';

                    if (!entryStart || !entryEnd) return false;

                    // Check if entry overlaps with this time slot
                    // Simple overlap logic: entry overlaps if it starts before slot ends AND ends after slot starts
                    // This covers all cases:
                    // - Entry starts and ends within slot (e.g., 10:15-10:45 in 10:00-11:00 slot)
                    // - Entry starts before and ends within slot (e.g., 09:30-10:15 in 10:00-10:30 slot)
                    // - Entry starts within and ends after slot (e.g., 10:15-11:30 in 10:00-10:30 slot)
                    // - Entry completely contains slot (e.g., 09:00-11:00 contains 10:00-10:30 slot)
                    // Note: Entry that starts exactly at slot end (10:30 entry in 10:00-10:30 slot) does NOT overlap
                    // Note: Entry that ends exactly at slot start (10:00 entry in 10:30-11:00 slot) does NOT overlap
                    const overlaps = entryStart < slot.end && entryEnd > slot.start;

                    // Debug: Log entries for specific slot to troubleshoot
                    if (slot.start === '09:00' && day.v === 6 && overlaps) {
                        console.log('Found overlapping entry for 09:00 slot:', {
                            id: e.id,
                            start: entryStart,
                            end: entryEnd,
                            entry_type: e.entry_type,
                            slot: slot
                        });
                    }

                    return overlaps;
                });

                if (overlappingEntries.length > 0) {
                    // Prioritize non-work entries (breaks, meetings, etc.) over work entries
                    // Sort: non-work entries first, then by start time
                    overlappingEntries.sort((a, b) => {
                        // Determine if entry is work type
                        // Entry is work if:
                        // 1. entry_type is explicitly 'work'
                        // 2. entry_type is null/undefined AND is_break is false (legacy entries)
                        // Entry is NOT work if:
                        // - entry_type is 'breakfast', 'break', 'meeting', 'workshop', 'training', 'other'
                        // - entry_type is null/undefined AND is_break is true
                        const aIsWork = a.entry_type === 'work' || (!a.entry_type && !a.is_break);
                        const bIsWork = b.entry_type === 'work' || (!b.entry_type && !b.is_break);

                        // Non-work entries come first (highest priority)
                        if (aIsWork && !bIsWork) return 1;
                        if (!aIsWork && bIsWork) return -1;

                        // If both are same type, prefer shorter entries (more specific)
                        // This ensures that a 30-minute breakfast shows over a 10-hour work entry
                        const aStartTime = a.start_time ? (a.start_time.length === 5 ? a.start_time + ':00' : a.start_time) : '';
                        const aEndTime = a.end_time ? (a.end_time.length === 5 ? a.end_time + ':00' : a.end_time) : '';
                        const bStartTime = b.start_time ? (b.start_time.length === 5 ? b.start_time + ':00' : b.start_time) : '';
                        const bEndTime = b.end_time ? (b.end_time.length === 5 ? b.end_time + ':00' : b.end_time) : '';

                        const aDuration = aStartTime && aEndTime ? (new Date(`2000-01-01T${aEndTime}`) - new Date(`2000-01-01T${aStartTime}`)) : 0;
                        const bDuration = bStartTime && bEndTime ? (new Date(`2000-01-01T${bEndTime}`) - new Date(`2000-01-01T${bStartTime}`)) : 0;

                        if (aDuration !== bDuration) return aDuration - bDuration;

                        // If same duration, sort by start time
                        return (a.start_time || '').localeCompare(b.start_time || '');
                    });

                    // Get the highest priority entry (first in sorted array)
                    // This should be a non-work entry if available, or the shortest work entry
                    const entry = overlappingEntries[0];

                    // Check if this entry starts at this exact slot
                    const entryStart = entry.start_time ? entry.start_time.substring(0, 5) : '';
                    const entryStartsAtThisSlot = entryStart === slot.start;

                    // Determine if entry is work type
                    const entryIsWork = entry.entry_type === 'work' || (!entry.entry_type && !entry.is_break);

                    // Display logic:
                    // 1. Work entries: only display if they start at this slot (they use rowspan)
                    // 2. Non-work entries: always display if they start at this slot
                    if (!entryStartsAtThisSlot) {
                        // Entry doesn't start here - skip it
                        // This allows entries to show only at their start time
                        row.days[day.v] = {
                            render: true,
                            rowspan: 1,
                            entry: null,
                            isRestricted: isDayRestricted(day.v)
                        };
                        // Skip to next day (don't process this entry)
                } else {
                        // Process and display the entry

                        // Debug: Log selected entry for troubleshooting
                        if (slot.start === '09:00' && day.v === 6) {
                            console.log('Selected entry for 09:00 slot:', {
                                id: entry.id,
                                start: entry.start_time,
                                end: entry.end_time,
                                entry_type: entry.entry_type,
                                is_break: entry.is_break,
                                entryStartsAtThisSlot,
                                entryIsWork,
                                allOverlapping: overlappingEntries.map(e => ({
                                    id: e.id,
                                    entry_type: e.entry_type,
                                    start: e.start_time,
                                    end: e.end_time
                                }))
                            });
                        }

                        // Parse times - handle both HH:mm and HH:mm:ss formats
                        const startTimeStr = entry.start_time || '';
                        const endTimeStr = entry.end_time || '';

                        // Ensure format is HH:mm:ss for Date parsing
                        const startTimeFormatted = startTimeStr.length === 5 ? startTimeStr + ':00' : startTimeStr;
                        const endTimeFormatted = endTimeStr.length === 5 ? endTimeStr + ':00' : endTimeStr;

                        const start = new Date(`2000-01-01T${startTimeFormatted}`);
                        const end = new Date(`2000-01-01T${endTimeFormatted}`);
                        if (end < start) end.setDate(end.getDate() + 1);
                        const durationMinutes = (end - start) / (1000 * 60);
                        const rowspan = Math.max(1, Math.ceil(durationMinutes / 30));

                        // Mark this entry as used
                        usedEntries.add(entry.id);

                        row.days[day.v] = {
                            render: true,
                            rowspan: rowspan,
                            entry: entry,
                            isRestricted: isDayRestricted(day.v),
                            allEntries: overlappingEntries, // Store all entries for potential future use
                            overlay: false // Not an overlay
                        };

                        // Set rowspan counter ONLY for work entries
                        // Non-work entries don't set rowspan counters, allowing them to show alongside work entries
                        if (entryIsWork) {
                            rowspanCounters[day.v] = rowspan - 1;
                        }
                    }
                } else {
                    row.days[day.v] = {
                        render: true,
                        rowspan: 1,
                        entry: null,
                        isRestricted: isDayRestricted(day.v)
                    };
                }
            }
        });
        data.push(row);
    });

    return { slots: timeSlots, data: data };
});

const totalWorkHours = computed(() => {
    if (!selectedPerson.value) return 0;
    const personTimetable = props.timetables.filter(entry =>
        entry.schedulable_id === selectedPerson.value.id &&
        entry.schedulable_type.includes(selectedPerson.value.type) &&
        !entry.is_break
    );

    let totalMinutes = 0;
    personTimetable.forEach(entry => {
        if (entry.work_minutes) {
            totalMinutes += entry.work_minutes;
        } else {
            const start = new Date(`2000-01-01T${entry.start_time}`);
            const end = new Date(`2000-01-01T${entry.end_time}`);
            if (end < start) end.setDate(end.getDate() + 1);
            totalMinutes += (end - start) / (1000 * 60);
        }
    });

    return (totalMinutes / 60).toFixed(2);
});

// --- Day Lock Logic (حظر الأيام حسب إعدادات القسم) ---
const isDayLocked = computed(() => {
    const locked = {};
    weekDays.forEach(day => {
        // إذا كان اليوم عطلة في إعدادات القسم ولم يتم تحديده في required_days
        if (activeShiftSetting.value) {
            const deptWorkDays = activeShiftSetting.value.work_days || [];
            const requiredDays = individualConstraintsForm.required_days || [];

            // إذا كان اليوم عطلة في إعدادات القسم (الجمعة والسبت عادة)
            if (day.isWeekend && !deptWorkDays.includes(day.v)) {
                // إذا لم يتم تحديده في required_days، قفله
                locked[day.v] = !requiredDays.includes(day.v);
            } else {
                // إذا كان يوم عمل عادي، لا تقفله
                locked[day.v] = false;
            }
        } else {
            locked[day.v] = false;
        }
    });
    return locked;
});

// --- Auto-fill logic for monthly employees ---
const isMonthlyEmployee = computed(() => {
    return props.personnelConstraints?.employment_type === 'monthly_full' ||
           props.personnelConstraints?.employment_type === 'monthly_partial';
});

// Get default work hours for monthly employees
const getDefaultWorkHours = computed(() => {
    if (!isMonthlyEmployee.value || !activeShiftSetting.value) {
        return { start: '08:00', end: '16:00' };
    }

    const start = activeShiftSetting.value.start_time || '08:00';
    const end = activeShiftSetting.value.end_time || '16:00';

    // Handle format (HH:mm or HH:mm:ss)
    return {
        start: start.substring(0, 5),
        end: end.substring(0, 5)
    };
});

// Printable timetable data - uses only actual department shift hours
const printableTimetableData = computed(() => {
    if (!selectedPerson.value) return { slots: [], data: [] };

    // Get actual start and end times from department/organization settings
    let actualStartTime = '08:00';
    let actualEndTime = '16:00';

    if (activeShiftSetting.value) {
        actualStartTime = activeShiftSetting.value.start_time || '08:00';
        actualEndTime = activeShiftSetting.value.end_time || '16:00';
    }

    // Parse times (handle HH:mm:ss format)
    const [startH, startM] = actualStartTime.split(':').map(Number);
    const [endH, endM] = actualEndTime.split(':').map(Number);

    // Round start time down to nearest 30-minute slot
    const startHour = startH;
    const startMinute = Math.floor((startM || 0) / 30) * 30;

    // Round end time up to nearest 30-minute slot to ensure we show the full shift
    // If end time is 17:00, we want to show slots until 17:00-17:30
    // If end time is 17:30, we want to show slots until 18:00-18:30
    let endHour = endH;
    let endMinute = endM || 0;
    // Always round up to include the slot that contains the end time
    if (endMinute === 0) {
        // If exactly on the hour (e.g., 17:00), show until 17:00-17:30
        endMinute = 30;
    } else if (endMinute > 0 && endMinute <= 30) {
        // If between 0 and 30 (e.g., 17:15), show until 17:30-18:00
        endMinute = 30;
    } else {
        // If after 30 (e.g., 17:45), show until 18:00-18:30
        endMinute = 0;
        endHour += 1;
    }

    console.log('Printable timetable - Start:', actualStartTime, 'End:', actualEndTime);
    console.log('Printable timetable - Calculated range:', startHour + ':' + startMinute.toString().padStart(2, '0'), 'to', endHour + ':' + endMinute.toString().padStart(2, '0'));

    // Filter entries for selected person
    const personTimetable = props.timetables.filter(entry => {
        if (!entry || !selectedPerson.value) return false;
        const matchesPerson = entry.schedulable_id === selectedPerson.value.id;
        const matchesType = entry.schedulable_type && (
            entry.schedulable_type.includes(selectedPerson.value.type) ||
            entry.schedulable_type === `App\\Models\\${selectedPerson.value.type}`
        );
        return matchesPerson && matchesType;
    });

    console.log('Printable timetable - Person timetable entries:', personTimetable.length);
    console.log('Printable timetable - Entries:', personTimetable.map(e => ({
        day: e.day_of_week,
        start: e.start_time,
        end: e.end_time,
        type: e.entry_type
    })));

    // For printable timetable, we should show ALL available hours from department shift settings
    // This allows the admin to see and manage the full range of available hours
    // The admin can then add/edit/delete entries to distribute hours according to individual constraints
    // Create time slots for the full department shift range
    const actualTimeSlots = [];
    let slotHour = startHour;
    let slotMinute = startMinute;

    // Continue creating slots until we've covered the full end time
    // We need to include slots that end at or after the end time
    while (true) {
        const start = `${slotHour.toString().padStart(2, '0')}:${slotMinute.toString().padStart(2, '0')}`;

        // Calculate end time for this slot
        let endSlotMinute = slotMinute + 30;
        let endSlotHour = slotHour;
        if (endSlotMinute >= 60) {
            endSlotMinute -= 60;
            endSlotHour += 1;
        }

        const end = `${endSlotHour.toString().padStart(2, '0')}:${endSlotMinute.toString().padStart(2, '0')}`;

        // Add the slot
        actualTimeSlots.push({ start, end });

        // Check if we've reached or passed the end time
        // Stop if the slot's end time is beyond the department shift end time
        if (endSlotHour > endHour || (endSlotHour === endHour && endSlotMinute >= endMinute)) {
            break;
        }

        // Move to next slot
        slotMinute += 30;
        if (slotMinute >= 60) {
            slotMinute = 0;
            slotHour += 1;
        }
    }

    console.log('Printable timetable - Full department shift range:', startHour + ':' + startMinute.toString().padStart(2, '0'), 'to', endHour + ':' + endMinute.toString().padStart(2, '0'));
    console.log('Printable timetable - Total available slots:', actualTimeSlots.length);

    const data = [];
    const rowspanCounters = { 6: 0, 0: 0, 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
    const usedEntries = new Set();

    actualTimeSlots.forEach(slot => {
        const row = { time: slot, days: {} };
        weekDays.forEach(day => {
            const hasWorkRowspan = rowspanCounters[day.v] > 0;

            if (hasWorkRowspan) {
                rowspanCounters[day.v]--;
                row.days[day.v] = { render: false };
                return;
            }

            // Find overlapping entries
            const overlappingEntries = personTimetable.filter(e => {
                if (e.day_of_week != day.v) return false;
                if (usedEntries.has(e.id)) return false;

                const entryStart = e.start_time ? e.start_time.substring(0, 5) : '';
                const entryEnd = e.end_time ? e.end_time.substring(0, 5) : '';
                if (!entryStart || !entryEnd) return false;

                return entryStart < slot.end && entryEnd > slot.start;
            });

            if (overlappingEntries.length > 0) {
                // Sort to prioritize work entries
                overlappingEntries.sort((a, b) => {
                    if (a.entry_type === 'work' && b.entry_type !== 'work') return -1;
                    if (a.entry_type !== 'work' && b.entry_type === 'work') return 1;
                    return 0;
                });

                const entry = overlappingEntries[0];
                usedEntries.add(entry.id);

                // Calculate rowspan
                const entryStart = entry.start_time.substring(0, 5);
                const entryEnd = entry.end_time.substring(0, 5);
                const slotIndex = actualTimeSlots.findIndex(s => s.start === slot.start);
                let rowspan = 1;

                for (let i = slotIndex + 1; i < actualTimeSlots.length; i++) {
                    const nextSlot = actualTimeSlots[i];
                    if (entryStart < nextSlot.end && entryEnd > nextSlot.start) {
                        rowspan++;
                    } else {
                        break;
                    }
                }

                if (rowspan > 1) {
                    rowspanCounters[day.v] = rowspan - 1;
                }

                row.days[day.v] = {
                    render: true,
                    entry: entry,
                    rowspan: rowspan > 1 ? rowspan : undefined
                };
            } else {
                row.days[day.v] = { render: true, entry: null };
            }
        });

        // Always add row - we want to show all time slots even if empty
        // This ensures all hours are visible in the printed timetable
        data.push(row);
    });

    console.log('Printable timetable - Total rows created:', data.length);
    console.log('Printable timetable - First row time:', data[0]?.time?.start, '-', data[0]?.time?.end);
    console.log('Printable timetable - Last row time:', data[data.length - 1]?.time?.start, '-', data[data.length - 1]?.time?.end);

    return { slots: actualTimeSlots, data: data };
});

// --- Monthly View Functions ---
const getMonthlyCalendarDates = (monthStr) => {
    const [year, month] = monthStr.split('-').map(Number);
    const firstDay = new Date(year, month - 1, 1);
    const lastDay = new Date(year, month, 0);
    const daysInMonth = lastDay.getDate();

    // Debug: Log overtime entries for troubleshooting
    if (props.overtimeEntries && Array.isArray(props.overtimeEntries) && props.overtimeEntries.length > 0 && selectedPerson.value) {
        console.log('Overtime Entries available:', props.overtimeEntries.length);
        console.log('Selected Person ID:', selectedPerson.value.id);
    }

    // Convert JavaScript day to weekDays array index
    // JavaScript getDay(): 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
    // weekDays array: [0]=Saturday(6), [1]=Sunday(0), [2]=Monday(1), [3]=Tuesday(2), [4]=Wednesday(3), [5]=Thursday(4), [6]=Friday(5)
    const jsDayToWeekDaysIndex = (jsDay) => {
        // Map: 6 (Saturday) -> 0, 0 (Sunday) -> 1, 1 (Monday) -> 2, ..., 5 (Friday) -> 6
        return jsDay === 6 ? 0 : jsDay + 1;
    };

    const startDayOfWeek = firstDay.getDay(); // JavaScript: 0 = Sunday, 6 = Saturday
    // Convert to weekDays array index (where Saturday is at index 0)
    const startDayIndex = jsDayToWeekDaysIndex(startDayOfWeek);

    const dates = [];

    // Add previous month's days to fill the first week
    // We need to fill from Saturday (index 0 in weekDays) up to the day before the first day of the month
    const prevMonthLastDay = new Date(year, month - 1, 0).getDate();
    // Calculate how many days from previous month we need
    const daysToAdd = startDayIndex; // Days before the first day of the month
    for (let i = daysToAdd - 1; i >= 0; i--) {
        const prevDate = new Date(year, month - 2, prevMonthLastDay - i);
        const prevDayOfWeek = prevDate.getDay();
        dates.push({
            day: prevMonthLastDay - i,
            date: prevDate,
            isCurrentMonth: false,
            isWeekend: prevDayOfWeek === 5 || prevDayOfWeek === 6,
            isToday: false,
            timetableEntry: null,
            overtimeEntry: null,
            leaveEntry: null,
            isWorkDay: false,
        });
    }

    // Add current month's days
    const today = new Date();
    // Get required_days from constraints - handle both array and JSON string
    let requiredDays = [];
    if (props.personnelConstraints?.required_days) {
        requiredDays = Array.isArray(props.personnelConstraints.required_days)
            ? props.personnelConstraints.required_days
            : (typeof props.personnelConstraints.required_days === 'string'
                ? JSON.parse(props.personnelConstraints.required_days)
                : []);
    } else if (individualConstraintsForm.required_days && individualConstraintsForm.required_days.length > 0) {
        requiredDays = individualConstraintsForm.required_days;
    } else if (activeShiftSetting.value && activeShiftSetting.value.work_days && Array.isArray(activeShiftSetting.value.work_days)) {
        // Use default shift setting work days if no constraints
        requiredDays = activeShiftSetting.value.work_days;
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month - 1, day);
        const dayOfWeek = date.getDay();
        const isWeekend = dayOfWeek === 5 || dayOfWeek === 6; // Friday or Saturday

        // Check if this day is a work day according to constraints
        const isWorkDay = requiredDays.includes(dayOfWeek);

        // Find timetable entry for this day of week
        // Note: dayOfWeek in JavaScript: 0=Sunday, 1=Monday, ..., 6=Saturday
        // Our system uses: 0=Sunday, 1=Monday, ..., 5=Friday, 6=Saturday
        const dayOfWeekForEntry = dayOfWeek; // Already matches our system
        let timetableEntry = null;

        // Only show timetable entry if this day is a work day
        if (isWorkDay && selectedPerson.value && props.timetables) {
            const [type, id] = selectedPersonIdentifier.value?.split('-') || [];
            // Convert JavaScript dayOfWeek (0-6) to TimetableEntry format (1-7)
            // JavaScript: 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
            // TimetableEntry: 1=Saturday, 2=Sunday, 3=Monday, 4=Tuesday, 5=Wednesday, 6=Thursday, 7=Friday
            const timetableDayOfWeek = (dayOfWeek === 0) ? 2 : ((dayOfWeek === 6) ? 1 : (dayOfWeek + 2));

            timetableEntry = props.timetables.find(entry => {
                if (!entry || !selectedPerson.value) return false;
                return entry.schedulable_id === selectedPerson.value.id &&
                       entry.schedulable_type === `App\\Models\\${type}` &&
                       entry.day_of_week === timetableDayOfWeek &&
                       !entry.is_break;
            }) || null;
        }

        // Find overtime entry for this specific date
        // Use local date string directly (avoid toISOString() which converts to UTC and may change the day)
        const dateStrLocal = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

        let overtimeEntry = null;
        if (props.overtimeEntries && Array.isArray(props.overtimeEntries) && props.overtimeEntries.length > 0 && selectedPerson.value) {
            const [type, id] = selectedPersonIdentifier.value?.split('-') || [];

            overtimeEntry = props.overtimeEntries.find(entry => {
                if (!entry || !entry.date) return false;

                // Check if this overtime entry belongs to the selected person
                const matchesPerson = entry.schedulable_id === selectedPerson.value.id &&
                                      entry.schedulable_type === `App\\Models\\${type}`;
                if (!matchesPerson) return false;

                // Handle date comparison - normalize both dates
                let entryDate = null;
                if (typeof entry.date === 'string') {
                    // Remove time part if present (YYYY-MM-DD HH:MM:SS or YYYY-MM-DD)
                    entryDate = entry.date.split(' ')[0].split('T')[0];
                } else if (entry.date && typeof entry.date === 'object') {
                    // Handle Carbon date object from Laravel or Date object
                    if (entry.date.date) {
                        entryDate = entry.date.date.split(' ')[0].split('T')[0];
                    } else if (entry.date instanceof Date) {
                        // Use local date components instead of toISOString() to avoid UTC conversion
                        // This ensures we compare dates in local timezone, not UTC
                        const entryYear = entry.date.getFullYear();
                        const entryMonth = String(entry.date.getMonth() + 1).padStart(2, '0');
                        const entryDay = String(entry.date.getDate()).padStart(2, '0');
                        entryDate = `${entryYear}-${entryMonth}-${entryDay}`;
                    } else if (entry.date.toString) {
                        // Try to parse as string
                        const dateStr = entry.date.toString();
                        entryDate = dateStr.split(' ')[0].split('T')[0];
                    }
                }

                // Normalize dates for comparison (remove any timezone issues)
                const normalizedEntryDate = entryDate ? entryDate.substring(0, 10) : null;
                const normalizedDateStrLocal = dateStrLocal.substring(0, 10);

                // Debug log for all entries being checked
                console.log('Checking overtime entry:', {
                    entryId: entry.id,
                    entryDate: entry.date,
                    normalizedEntryDate: normalizedEntryDate,
                    dateStrLocal: normalizedDateStrLocal,
                    calendarDay: day,
                    calendarMonth: month,
                    calendarYear: year,
                    matches: normalizedEntryDate === normalizedDateStrLocal
                });

                // Compare with local date format only (avoid UTC conversion issues)
                return normalizedEntryDate === normalizedDateStrLocal;
            }) || null;
        }

        // Find leave entry for this specific date
        let leaveEntry = null;
        let isUnpaidLeave = false; // تحديد ما إذا كانت الإجازة بدون رصيد
        if (props.leaveEntries && Array.isArray(props.leaveEntries) && props.leaveEntries.length > 0 && selectedPerson.value) {
            const [type, id] = selectedPersonIdentifier.value?.split('-') || [];

            leaveEntry = props.leaveEntries.find(leave => {
                if (!leave || !leave.start_date || !leave.end_date) return false;

                // Check if this leave belongs to the selected person
                const matchesPerson = leave.leavable_id === selectedPerson.value.id &&
                                      leave.leavable_type === `App\\Models\\${type}`;
                if (!matchesPerson) return false;

                // Check if the current date falls within the leave date range
                const leaveStart = new Date(leave.start_date);
                const leaveEnd = new Date(leave.end_date);
                const currentDate = new Date(year, month - 1, day);

                // Normalize dates to compare only dates (ignore time)
                leaveStart.setHours(0, 0, 0, 0);
                leaveEnd.setHours(0, 0, 0, 0);
                currentDate.setHours(0, 0, 0, 0);

                return currentDate >= leaveStart && currentDate <= leaveEnd;
            }) || null;

            // تحديد ما إذا كانت الإجازة بدون رصيد (غير مدفوعة)
            if (leaveEntry && leaveEntry.leave_type && !leaveEntry.leave_type.is_paid) {
                isUnpaidLeave = true;
            }
        }

        dates.push({
            day,
            date,
            isCurrentMonth: true,
            isWeekend,
            isWorkDay,
            isToday: date.toDateString() === today.toDateString(),
            timetableEntry: isUnpaidLeave ? null : timetableEntry, // إزالة timetableEntry إذا كانت هناك إجازة بدون رصيد
            overtimeEntry,
            leaveEntry,
            isUnpaidLeave, // إضافة خاصية جديدة
        });
    }

    // Add next month's days to fill the grid (6 weeks * 7 days = 42)
    const remainingDays = 42 - dates.length;
    for (let day = 1; day <= remainingDays; day++) {
        dates.push({
            day,
            date: new Date(year, month, day),
            isCurrentMonth: false,
            isWeekend: false,
            isToday: false,
            timetableEntry: null,
            overtimeEntry: null,
            leaveEntry: null,
            isUnpaidLeave: false,
            isWorkDay: false,
        });
    }

    return dates;
};

const getMonthlySummary = (monthStr) => {
    const [year, month] = monthStr.split('-').map(Number);
    const lastDay = new Date(year, month, 0);
    const daysInMonth = lastDay.getDate();

    let workDays = 0;
    let totalHours = 0;
    let overtimeHours = 0;

    // Calculate based on timetable entries
    if (props.timetables && selectedPerson.value) {
        const personTimetable = props.timetables.filter(entry =>
            entry.schedulable_id === selectedPerson.value.id &&
            entry.schedulable_type.includes(selectedPerson.value.type) &&
            !entry.is_break
        );

        // Get required days from constraints
        const requiredDays = individualConstraintsForm.required_days || [];

        // Get leave entries for this month to exclude unpaid leave days
        const monthLeaveEntries = [];
        if (props.leaveEntries && Array.isArray(props.leaveEntries) && props.leaveEntries.length > 0 && selectedPerson.value) {
            const [type, id] = selectedPersonIdentifier.value?.split('-') || [];
            monthLeaveEntries.push(...props.leaveEntries.filter(leave => {
                if (!leave || !leave.start_date || !leave.end_date) return false;
                const matchesPerson = leave.leavable_id === selectedPerson.value.id &&
                                      leave.leavable_type === `App\\Models\\${type}`;
                if (!matchesPerson) return false;

                // Check if leave overlaps with this month
                const leaveStart = new Date(leave.start_date);
                const leaveEnd = new Date(leave.end_date);
                const monthStart = new Date(year, month - 1, 1);
                const monthEnd = new Date(year, month, 0);

                return leaveStart <= monthEnd && leaveEnd >= monthStart;
            }));
        }

        // Calculate actual work days in the month
        // Count how many times each required weekday appears in the month
        // Exclude days with unpaid leave
        workDays = 0;
        const unpaidLeaveDays = new Set(); // Set to store dates with unpaid leave

        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month - 1, day);
            const dayOfWeek = date.getDay(); // 0=Sunday, 1=Monday, ..., 6=Saturday

            // Check if this day has unpaid leave
            let hasUnpaidLeave = false;
            const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

            for (const leave of monthLeaveEntries) {
                if (leave.leave_type && !leave.leave_type.is_paid) {
                    const leaveStart = new Date(leave.start_date);
                    const leaveEnd = new Date(leave.end_date);
                    const currentDate = new Date(year, month - 1, day);

                    leaveStart.setHours(0, 0, 0, 0);
                    leaveEnd.setHours(0, 0, 0, 0);
                    currentDate.setHours(0, 0, 0, 0);

                    if (currentDate >= leaveStart && currentDate <= leaveEnd) {
                        hasUnpaidLeave = true;
                        unpaidLeaveDays.add(dateStr);
                        break;
                    }
                }
            }

            if (requiredDays.includes(dayOfWeek) && !hasUnpaidLeave) {
                workDays++;
            }
        }

        // Calculate total hours from actual timetable entries for each work day in the month
        // This is more accurate for hourly employees with custom hours
        totalHours = 0;

        // Create a map of day_of_week -> total hours for that day from timetable
        const hoursPerDayOfWeek = {};
        personTimetable.forEach(entry => {
            const dayOfWeek = entry.day_of_week;
            let hours = 0;

            if (entry.work_minutes) {
                hours = entry.work_minutes / 60;
            } else if (entry.start_time && entry.end_time) {
                const start = new Date(`2000-01-01T${entry.start_time}`);
                const end = new Date(`2000-01-01T${entry.end_time}`);
                if (end < start) end.setDate(end.getDate() + 1);
                hours = (end - start) / (1000 * 60 * 60);
            }

            if (!hoursPerDayOfWeek[dayOfWeek]) {
                hoursPerDayOfWeek[dayOfWeek] = 0;
            }
            hoursPerDayOfWeek[dayOfWeek] += hours;
        });

        // Calculate total hours by summing actual hours for each work day in the month
        // Exclude days with unpaid leave
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month - 1, day);
            const dayOfWeek = date.getDay(); // JavaScript: 0=Sunday, 1=Monday, ..., 6=Saturday
            const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

            if (requiredDays.includes(dayOfWeek) && !unpaidLeaveDays.has(dateStr)) {
                // Convert JavaScript dayOfWeek (0-6) to TimetableEntry format (1-7)
                // JavaScript: 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
                // TimetableEntry: 1=Saturday, 2=Sunday, 3=Monday, 4=Tuesday, 5=Wednesday, 6=Thursday, 7=Friday
                const timetableDayOfWeek = (dayOfWeek === 0) ? 2 : ((dayOfWeek === 6) ? 1 : (dayOfWeek + 2));
                const dayHours = hoursPerDayOfWeek[timetableDayOfWeek] || 0;
                totalHours += dayHours;
            }
        }

        // Fallback: If no timetable entries, use total_hours_per_week from constraints
        if (totalHours === 0 && individualConstraintsForm.total_hours_per_week && individualConstraintsForm.total_hours_per_week > 0 && requiredDays.length > 0) {
            const hoursPerDay = individualConstraintsForm.total_hours_per_week / requiredDays.length;
            totalHours = workDays * hoursPerDay;
            console.log('Using total_hours_per_week from constraints (fallback):', {
                total_hours_per_week: individualConstraintsForm.total_hours_per_week,
                requiredDays: requiredDays.length,
                hoursPerDay: hoursPerDay,
                workDays: workDays,
                totalHours: totalHours
            });
        }

        // Calculate total hours per week for display
        let totalHoursPerWeek = 0;
        requiredDays.forEach(dayOfWeek => {
            if (hoursPerDayOfWeek[dayOfWeek] !== undefined) {
                totalHoursPerWeek += hoursPerDayOfWeek[dayOfWeek];
            }
        });

        // If no timetable entries, use from constraints
        if (totalHoursPerWeek === 0 && individualConstraintsForm.total_hours_per_week) {
            totalHoursPerWeek = individualConstraintsForm.total_hours_per_week;
        }

        console.log('=== MONTHLY SUMMARY CALCULATION ===');
        console.log('Month:', monthStr);
        console.log('Days in month:', daysInMonth);
        console.log('Required days:', requiredDays);
        console.log('Required days count:', requiredDays.length);
        console.log('Work days count:', workDays);
        console.log('Hours per day of week:', hoursPerDayOfWeek);
        console.log('Total hours per week (from timetable):', totalHoursPerWeek);
        console.log('Total hours (calculated from actual entries):', totalHours);
        console.log('Calculation: Sum of actual hours for each work day in the month');
    }

    // Calculate overtime hours from overtime entries
    if (props.overtimeEntries && Array.isArray(props.overtimeEntries) && props.overtimeEntries.length > 0 && selectedPerson.value) {
        const monthOvertime = props.overtimeEntries.filter(entry => {
            if (!entry || !entry.date) return false;
            const entryDate = new Date(entry.date);
            if (isNaN(entryDate.getTime())) return false;
            return entryDate.getFullYear() === year &&
                   entryDate.getMonth() + 1 === month;
        });

        monthOvertime.forEach(entry => {
            if (entry.minutes) {
                overtimeHours += entry.minutes / 60;
            }
        });
    }

    return {
        workDays,
        totalHours,
        overtimeHours,
    };
};

const submitOvertime = () => {
    console.log('=== SUBMIT OVERTIME - START ===');
    console.log('selectedPerson.value:', selectedPerson.value);

    if (!selectedPerson.value) {
        console.log('No selected person, returning');
        return;
    }

    const [type, id] = selectedPersonIdentifier.value.split('-');
    overtimeForm.schedulable_id = parseInt(id);
    overtimeForm.schedulable_type = `App\\Models\\${type}`;

    console.log('Before calculation:');
    console.log('overtimeForm.date:', overtimeForm.date);
    console.log('overtimeHoursInput.value:', overtimeHoursInput.value);
    console.log('overtimeMinutesInput.value:', overtimeMinutesInput.value);
    console.log('overtimeForm.start_time:', overtimeForm.start_time);
    console.log('overtimeForm.end_time:', overtimeForm.end_time);
    console.log('selectedDatesForOvertime.value:', selectedDatesForOvertime.value);
    console.log('selectedDatesForOvertime.value[0]:', selectedDatesForOvertime.value[0]);
    console.log('selectedDatesForOvertime.value[0].day:', selectedDatesForOvertime.value[0]?.day);

    // Calculate minutes from hours/minutes input if provided
    if (overtimeHoursInput.value > 0 || overtimeMinutesInput.value > 0) {
        overtimeForm.minutes = (overtimeHoursInput.value * 60) + overtimeMinutesInput.value;
        console.log('Calculated minutes from hours/minutes:', overtimeForm.minutes);
        // If no start/end time set, use end of regular work time as start
        if (!overtimeForm.start_time && selectedDatesForOvertime.value.length > 0) {
            const date = selectedDatesForOvertime.value[0];
            if (date.timetableEntry && date.timetableEntry.end_time) {
                overtimeForm.start_time = date.timetableEntry.end_time.substring(0, 5);
                const endTime = new Date(`2000-01-01T${date.timetableEntry.end_time}`);
                const overtimeEnd = new Date(endTime.getTime() + overtimeForm.minutes * 60000);
                overtimeForm.end_time = overtimeEnd.toTimeString().substring(0, 5);
                console.log('Set start_time and end_time from timetable:', overtimeForm.start_time, overtimeForm.end_time);
            }
        }
    } else if (overtimeForm.start_time && overtimeForm.end_time) {
        // Calculate minutes from time inputs
        const start = new Date(`2000-01-01T${overtimeForm.start_time}`);
        const end = new Date(`2000-01-01T${overtimeForm.end_time}`);
        if (end < start) end.setDate(end.getDate() + 1);
        overtimeForm.minutes = Math.round((end - start) / (1000 * 60));
        console.log('Calculated minutes from time inputs:', overtimeForm.minutes);
    }

    // Validate
    if (!overtimeForm.date || (!overtimeForm.start_time && overtimeForm.minutes === 0)) {
        console.log('Validation failed - missing date or time');
        alert('يرجى تحديد التاريخ والوقت أو المدة');
        return;
    }

    const isEditing = !!editingOvertimeId.value;
    const overtimeId = editingOvertimeId.value;

    console.log('Before submit:');
    console.log('isEditing:', isEditing);
    console.log('overtimeId:', overtimeId);
    console.log('Final overtimeForm.date:', overtimeForm.date);
    console.log('Final overtimeForm.start_time:', overtimeForm.start_time);
    console.log('Final overtimeForm.end_time:', overtimeForm.end_time);
    console.log('Final overtimeForm.minutes:', overtimeForm.minutes);
    console.log('Final overtimeForm.schedulable_id:', overtimeForm.schedulable_id);
    console.log('Final overtimeForm.schedulable_type:', overtimeForm.schedulable_type);
    console.log('Full overtimeForm object:', JSON.stringify(overtimeForm.data(), null, 2));

    const routeName = isEditing
        ? route('hr.integrations.scheduling.overtime.update', overtimeId)
        : route('hr.integrations.scheduling.overtime.store');

    const method = isEditing ? 'put' : 'post';
    console.log('Route:', routeName);
    console.log('Method:', method);

    overtimeForm[method](routeName, {
        preserveScroll: true,
        onSuccess: (response) => {
            console.log('=== SUBMIT OVERTIME - SUCCESS ===');
            console.log('Response:', response);
            showOvertimeModal.value = false;
            overtimeForm.reset();
            selectedDatesForOvertime.value = [];
            overtimeHoursInput.value = 0;
            overtimeMinutesInput.value = 0;
            editingOvertimeId.value = null;
            alert(isEditing ? 'تم تحديث الوقت الإضافي بنجاح!' : 'تم إضافة الوقت الإضافي بنجاح!');
            // Reload with person parameters to ensure overtimeEntries are fetched
            const [type, id] = selectedPersonIdentifier.value.split('-');
            router.get(route('hr.integrations.scheduling.index'), {
                person_id: id,
                person_type: `App\\Models\\${type}`,
                department_id: props.selectedDepartmentId,
            }, {
                preserveState: false,
                preserveScroll: true,
                only: ['overtimeEntries'],
            });
        },
        onError: (errors) => {
            console.log('=== SUBMIT OVERTIME - ERROR ===');
            console.log('Errors:', errors);
        },
    });
};

const editOvertime = (overtimeEntry, date) => {
    editingOvertimeId.value = overtimeEntry.id;
    const [type, id] = selectedPersonIdentifier.value.split('-');
    overtimeForm.schedulable_id = parseInt(id);
    overtimeForm.schedulable_type = `App\\Models\\${type}`;
    overtimeForm.date = overtimeEntry.date;
    overtimeForm.start_time = overtimeEntry.start_time.substring(0, 5);
    overtimeForm.end_time = overtimeEntry.end_time.substring(0, 5);
    overtimeForm.notes = overtimeEntry.notes || '';

    // Calculate hours and minutes from total minutes
    const totalMinutes = overtimeEntry.minutes || 0;
    overtimeHoursInput.value = Math.floor(totalMinutes / 60);
    overtimeMinutesInput.value = totalMinutes % 60;

    selectedDatesForOvertime.value = [date];
    showOvertimeModal.value = true;
};

const deleteOvertime = (overtimeId) => {
    console.log('=== DELETE OVERTIME - START ===');
    console.log('overtimeId:', overtimeId);

    if (!confirm('هل أنت متأكد من حذف الوقت الإضافي؟')) {
        console.log('Delete cancelled by user');
        return;
    }

    router.delete(route('hr.integrations.scheduling.overtime.delete', overtimeId), {
        preserveScroll: true,
        onSuccess: () => {
            console.log('=== DELETE OVERTIME - SUCCESS ===');
            alert('تم حذف الوقت الإضافي بنجاح!');
            router.reload({ only: ['overtimeEntries'] });
        },
        onError: (errors) => {
            console.log('=== DELETE OVERTIME - ERROR ===');
            console.log('Errors:', errors);
            alert('حدث خطأ أثناء حذف الوقت الإضافي');
        },
    });
};

// --- Drag and Drop for Entry Types ---

// --- Drag and Drop for Overtime ---
const handleOvertimeDragStart = (event) => {
    draggedOvertimeItem.value = 'overtime';
    event.dataTransfer.effectAllowed = 'copy';
    event.dataTransfer.setData('text/plain', 'overtime');
    // Add visual feedback
    if (event.target) {
        event.target.style.opacity = '0.5';
    }
};

const handleOvertimeDragEnd = (event) => {
    draggedOvertimeItem.value = null;
    dragOverDateIndex.value = null;
    if (event.target) {
        event.target.style.opacity = '1';
    }
};

const handleDateDragOver = (event, dateIndex, date) => {
    if (draggedOvertimeItem.value === 'overtime' && date.isCurrentMonth) {
        event.preventDefault();
        event.stopPropagation(); // Prevent event bubbling
        event.dataTransfer.dropEffect = 'copy';
        dragOverDateIndex.value = dateIndex;
    }
};

const handleDateDragLeave = (event) => {
    // Only clear if we're actually leaving the date cell, not entering a child element
    if (!event.currentTarget.contains(event.relatedTarget)) {
        dragOverDateIndex.value = null;
    }
};

const handleDateDrop = (event, date) => {
    event.preventDefault();
    event.stopPropagation(); // Prevent event bubbling
    dragOverDateIndex.value = null;

    if (draggedOvertimeItem.value === 'overtime' && date.isCurrentMonth && selectedPerson.value) {
        // Only allow dropping on work days
        if (!date.isWorkDay) {
            alert('لا يمكن إضافة وقت إضافي في يوم عطلة. يرجى اختيار يوم عمل.');
            return;
        }

        console.log('=== DRAG & DROP - START ===');
        console.log('Original date object:', date);
        console.log('date.day:', date.day);
        console.log('date.date:', date.date);
        console.log('date.date.getDate():', date.date?.getDate());
        console.log('date.date.getMonth():', date.date?.getMonth());
        console.log('date.date.getFullYear():', date.date?.getFullYear());
        console.log('selectedMonth.value:', selectedMonth.value);

        // Extract date components directly from selectedMonth and date.day to avoid timezone issues
        // Use the day number from the calendar grid, not from Date object methods
        const [year, month] = selectedMonth.value.split('-').map(Number);
        const day = date.day; // Use the day number from the calendar date object (1-31)

        console.log('Extracted values - year:', year, 'month:', month, 'day:', day);

        // Format date string in YYYY-MM-DD format without any timezone conversion
        const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        console.log('Formatted dateStr:', dateStr);

        // Create a date object for display purposes using local timezone (midday to avoid DST issues)
        const dateForModal = new Date(year, month - 1, day, 12, 0, 0);
        console.log('dateForModal:', dateForModal);
        console.log('dateForModal.toISOString():', dateForModal.toISOString());
        console.log('dateForModal.getDate():', dateForModal.getDate());
        console.log('dateForModal.getMonth():', dateForModal.getMonth());
        console.log('dateForModal.getFullYear():', dateForModal.getFullYear());

        // Create a date object that matches the calendar date
        const calendarDate = {
            ...date,
            date: dateForModal,
            day: day
        };
        console.log('calendarDate:', calendarDate);
        console.log('calendarDate.day:', calendarDate.day);

        // Open modal with this date
        const [type, id] = selectedPersonIdentifier.value.split('-');
        overtimeForm.schedulable_id = parseInt(id);
        overtimeForm.schedulable_type = `App\\Models\\${type}`;
        overtimeForm.date = dateStr; // Use local date format instead of ISO
        overtimeForm.start_time = '';
        overtimeForm.end_time = '';
        overtimeForm.minutes = 0;
        overtimeForm.notes = '';
        overtimeHoursInput.value = 0;
        overtimeMinutesInput.value = 0;
        editingOvertimeId.value = null;
        selectedDatesForOvertime.value = [calendarDate];

        console.log('overtimeForm.date:', overtimeForm.date);
        console.log('selectedDatesForOvertime.value:', selectedDatesForOvertime.value);
        console.log('selectedDatesForOvertime.value[0].day:', selectedDatesForOvertime.value[0]?.day);
        console.log('=== DRAG & DROP - END ===');

        showOvertimeModal.value = true;
    }
};

// Update overtime form from hours/minutes input
const updateOvertimeFromHours = () => {
    if (overtimeHoursInput.value >= 0 && selectedDatesForOvertime.value.length > 0) {
        const date = selectedDatesForOvertime.value[0];
        if (date.timetableEntry && date.timetableEntry.end_time) {
            // Start overtime from end of regular work time
            const endTime = new Date(`2000-01-01T${date.timetableEntry.end_time}`);
            const totalMinutes = (overtimeHoursInput.value * 60) + overtimeMinutesInput.value;
            const overtimeEnd = new Date(endTime.getTime() + totalMinutes * 60000);

            overtimeForm.start_time = date.timetableEntry.end_time.substring(0, 5);
            overtimeForm.end_time = overtimeEnd.toTimeString().substring(0, 5);
            overtimeForm.minutes = totalMinutes;
        }
    }
};

const updateOvertimeFromMinutes = () => {
    updateOvertimeFromHours();
};

// Get overtime hours display
const getOvertimeHoursDisplay = () => {
    if (overtimeHoursInput.value > 0 || overtimeMinutesInput.value > 0) {
        return (overtimeHoursInput.value + (overtimeMinutesInput.value / 60)).toFixed(1);
    }
    if (overtimeForm.start_time && overtimeForm.end_time) {
        const start = new Date(`2000-01-01T${overtimeForm.start_time}`);
        const end = new Date(`2000-01-01T${overtimeForm.end_time}`);
        if (end < start) end.setDate(end.getDate() + 1);
        return ((end - start) / (1000 * 60 * 60)).toFixed(1);
    }
    return '0';
};

// Format date for display without timezone issues
const formatDateForDisplay = (dateObj) => {
    if (!dateObj) return '';

    // Use the day number directly from the calendar object to avoid timezone issues
    const [year, month] = selectedMonth.value.split('-').map(Number);
    const day = dateObj.day || (dateObj.date ? dateObj.date.getDate() : null);

    if (!day) return '';

    // Create a date object in local timezone for display (use midday to avoid DST issues)
    const displayDate = new Date(year, month - 1, day, 12, 0, 0);

    // Format using Arabic locale
    const weekdays = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    const months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

    const weekday = weekdays[displayDate.getDay()];
    const monthName = months[displayDate.getMonth()];

    return `${weekday}، ${day} ${monthName} ${year}`;
};

// Get overtime minutes display
const getOvertimeMinutesDisplay = () => {
    if (overtimeMinutesInput.value > 0) {
        return overtimeMinutesInput.value;
    }
    if (overtimeForm.start_time && overtimeForm.end_time) {
        const start = new Date(`2000-01-01T${overtimeForm.start_time}`);
        const end = new Date(`2000-01-01T${overtimeForm.end_time}`);
        if (end < start) end.setDate(end.getDate() + 1);
        const totalMinutes = Math.round((end - start) / (1000 * 60));
        return totalMinutes % 60;
    }
    return 0;
};

// Calculate total work hours for a date (regular + overtime)
const getTotalWorkHoursForDate = (date) => {
    let totalMinutes = 0;

    // Add regular work hours
    if (date.timetableEntry) {
        const start = new Date(`2000-01-01T${date.timetableEntry.start_time}`);
        let end = new Date(`2000-01-01T${date.timetableEntry.end_time}`);
        if (end < start) end.setDate(end.getDate() + 1);
        totalMinutes += Math.round((end - start) / (1000 * 60));
    }

    // Add overtime hours
    if (date.overtimeEntry && date.overtimeEntry.minutes) {
        totalMinutes += date.overtimeEntry.minutes;
    }

    // Add pending overtime from form if this is the selected date
    if (selectedDatesForOvertime.value.length > 0 &&
        selectedDatesForOvertime.value.some(d => d.date.toDateString() === date.date.toDateString())) {
        if (overtimeForm.start_time && overtimeForm.end_time) {
            const start = new Date(`2000-01-01T${overtimeForm.start_time}`);
            let end = new Date(`2000-01-01T${overtimeForm.end_time}`);
            if (end < start) end.setDate(end.getDate() + 1);
            totalMinutes += Math.round((end - start) / (1000 * 60));
        }
    }

    return (totalMinutes / 60).toFixed(1);
};
</script>

<template>
    <Head title="إعدادات الجدولة" />
    <HrLayout>
        <template #header>
            إعدادات الجدولة الذكية
        </template>

        <div class="space-y-6">
            <!-- Top Bar: Department Selection & Search -->
            <div class="bg-white shadow-md rounded-lg p-6 no-print">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-800">إعدادات الجدولة</h3>
                    <div class="flex items-center space-x-3 rtl:space-x-reverse">
                        <button @click="showDefaultShiftSettings = !showDefaultShiftSettings"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors flex items-center"
                                :class="{ 'bg-indigo-700 ring-2 ring-indigo-300': showDefaultShiftSettings }">
                            <i class="fas fa-cog mr-2"></i> إعدادات الدوام الافتراضي
                        </button>
                        <button @click="showTemplateManager"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors flex items-center"
                    :class="{ 'bg-indigo-100 text-indigo-700': activeView === 'templates' }">
                            <i class="fas fa-layer-group mr-2"></i> إدارة قوالب الجدولة
                </button>
                    </div>
                </div>

                <!-- Default Shift Settings Panel -->
                <div v-if="showDefaultShiftSettings" class="mb-6 bg-gradient-to-r from-indigo-50 to-blue-50 border-2 border-indigo-200 rounded-xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-lg font-bold text-gray-800">
                            <i class="fas fa-clock mr-2 text-indigo-600"></i>
                            إعدادات الدوام الافتراضي للمؤسسة
                        </h4>
                        <button @click="showDefaultShiftSettings = false" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times"></i>
                            </button>
                </div>

                    <div v-if="props.defaultShiftSetting" class="mb-4 p-4 bg-white rounded-lg border border-indigo-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-700">الإعداد الحالي النشط:</p>
                                <p class="text-lg font-bold text-indigo-700">{{ props.defaultShiftSetting.name }}</p>
                                <p class="text-xs text-gray-600 mt-1">
                                    <span v-if="props.defaultShiftSetting.description">{{ props.defaultShiftSetting.description }} | </span>
                                    <span v-if="props.defaultShiftSetting.department" class="text-indigo-600 font-semibold">
                                        <i class="fas fa-building mr-1"></i>قسم: {{ props.defaultShiftSetting.department.name }}
                                    </span>
                                    <span v-else class="text-gray-500">
                                        <i class="fas fa-globe mr-1"></i>للمؤسسة كاملة
                                    </span>
                                    <span v-if="selectedDepartmentId && props.defaultShiftSetting.department_id === selectedDepartmentId"
                                          class="mr-2 text-green-600 font-bold">
                                        ✓ (مطبق على القسم الحالي)
                                    </span>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-600">من <span class="font-bold">{{ formatTimeTo12Hour(props.defaultShiftSetting.start_time) }}</span> إلى <span class="font-bold">{{ formatTimeTo12Hour(props.defaultShiftSetting.end_time) }}</span></p>
                                <p class="text-sm text-gray-600 mt-1">
                                    <span class="font-bold text-indigo-600">{{ props.defaultShiftSetting.hours_per_week }}</span> ساعة/أسبوع
                                    | <span class="font-bold text-indigo-600">{{ props.defaultShiftSetting.hours_per_month }}</span> ساعة/شهر
                                </p>
                            </div>
                        </div>
                    </div>

                    <form @submit.prevent="submitDefaultShiftSettings" class="space-y-4">
                        <!-- عرض القسم الحالي إذا كان محدداً -->
                        <div v-if="selectedDepartmentId" class="bg-indigo-100 border-2 border-indigo-300 rounded-lg p-4 mb-4">
                            <div class="flex items-center">
                                <i class="fas fa-building text-indigo-600 text-xl mr-3"></i>
                                <div>
                                    <p class="text-sm font-semibold text-indigo-800">الإعدادات ستطبق على:</p>
                                    <p class="text-lg font-bold text-indigo-900">
                                        {{ departments?.find(d => d.id === selectedDepartmentId)?.name || 'القسم المحدد' }}
                                    </p>
                                    <p class="text-xs text-indigo-700 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        أنت داخل قسم محدد، الإعدادات ستطبق تلقائياً على هذا القسم
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- عرض dropdown فقط إذا لم يكن هناك قسم محدد -->
                        <div v-else class="bg-gray-100 border-2 border-gray-300 rounded-lg p-4 mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-semibold text-gray-700">التطبيق على</label>
                                <!-- مؤشر وجود جدول للمؤسسة -->
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-600">المؤسسة:</span>
                                    <span v-if="hasOrganizationActiveShift"
                                          class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800"
                                          title="يوجد جدول دوام مفعل للمؤسسة">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        جدول مفعل
                                    </span>
                                    <span v-else
                                          class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600"
                                          title="لا يوجد جدول دوام مفعل للمؤسسة">
                                        <i class="fas fa-times-circle mr-1"></i>
                                        بدون جدول
                                    </span>
                                </div>
                            </div>
                            <select
                                v-model="defaultShiftSettingsForm.department_id"
                                class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option :value="null">المؤسسة كاملة (افتراضي)</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                    قسم: {{ dept.name }}
                                </option>
                            </select>
                            <p class="text-xs text-gray-600 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                يمكنك تحديد مواعيد خاصة لكل قسم أو مواعيد عامة للمؤسسة كاملة
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">اسم الإعداد</label>
                                <input
                                    type="text"
                                    v-model="defaultShiftSettingsForm.name"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">الوصف (اختياري)</label>
                                <input
                                    type="text"
                                    v-model="defaultShiftSettingsForm.description"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="وصف مختصر للإعداد">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">وقت بداية الدوام</label>
                                <input
                                    type="time"
                                    v-model="defaultShiftSettingsForm.start_time"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">وقت نهاية الدوام</label>
                                <input
                                    type="time"
                                    v-model="defaultShiftSettingsForm.end_time"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">
                                أيام العمل
                                <span class="text-xs font-normal text-gray-500 mr-2">(السبت والجمعة عادة عطلة - اختياري)</span>
                            </label>
                            <div class="grid grid-cols-7 gap-2">
                                <button
                                    v-for="day in weekDays"
                                    :key="day.v"
                                    type="button"
                                    @click.stop="toggleWorkDay(day.v)"
                                    class="px-3 py-2 rounded-lg border-2 transition-all font-medium text-sm relative"
                                    :class="defaultShiftSettingsForm.work_days.includes(day.v)
                                        ? day.isWeekend
                                            ? 'bg-orange-500 text-white border-orange-500 shadow-md'
                                            : 'bg-indigo-600 text-white border-indigo-600 shadow-md'
                                        : day.isWeekend
                                            ? 'bg-gray-100 text-gray-500 border-gray-200 hover:border-orange-400 hover:bg-orange-50'
                                            : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400 hover:bg-indigo-50'">
                                    {{ day.n }}
                                    <span v-if="day.isWeekend" class="absolute -top-1 -right-1 text-xs bg-yellow-400 text-yellow-900 rounded-full w-4 h-4 flex items-center justify-center font-bold" title="يوم عطلة">!</span>
                                </button>
                            </div>
                            <p v-if="defaultShiftSettingsForm.work_days.includes(5) || defaultShiftSettingsForm.work_days.includes(6)"
                               class="mt-2 text-xs text-orange-600 bg-orange-50 p-2 rounded-lg border border-orange-200">
                                <i class="fas fa-info-circle mr-1"></i>
                                <span v-if="defaultShiftSettingsForm.work_days.includes(5) && defaultShiftSettingsForm.work_days.includes(6)">
                                    تم اختيار أيام عطلة (السبت والجمعة). تأكد من أن المؤسسة تعمل في هذه الأيام.
                                </span>
                                <span v-else-if="defaultShiftSettingsForm.work_days.includes(6)">
                                    تم اختيار يوم السبت (عادة عطلة). تأكد من أن المؤسسة تعمل في هذا اليوم.
                                </span>
                                <span v-else-if="defaultShiftSettingsForm.work_days.includes(5)">
                                    تم اختيار يوم الجمعة (عادة عطلة). تأكد من أن المؤسسة تعمل في هذا اليوم.
                                </span>
                            </p>
                        </div>

                        <div class="bg-white rounded-lg p-4 border border-indigo-200">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <p class="text-xs text-gray-600 mb-1">عدد أيام العمل</p>
                                    <p class="text-2xl font-bold text-indigo-600">{{ defaultShiftSettingsForm.work_days.length }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-600 mb-1">ساعات العمل/أسبوع</p>
                                    <p class="text-2xl font-bold text-indigo-600">{{ calculatedHoursPerWeek }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-600 mb-1">ساعات العمل/شهر</p>
                                    <p class="text-2xl font-bold text-indigo-600">{{ calculatedHoursPerMonth }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 rtl:space-x-reverse">
                            <button
                                type="button"
                                @click="showDefaultShiftSettings = false"
                                class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium">
                                إلغاء
                            </button>
                            <button
                                type="submit"
                                :disabled="defaultShiftSettingsForm.processing"
                                class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium disabled:opacity-50">
                                <i class="fas fa-save mr-2"></i>
                                {{ defaultShiftSettingsForm.processing ? 'جاري الحفظ...' : 'حفظ الإعدادات' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Department Selection -->
                <div v-if="!selectedDepartmentId && activeView !== 'person' && activeView !== 'templates'">
                    <div class="flex items-center justify-between mb-4">
                        <label class="block text-sm font-semibold text-gray-700">اختر القسم لعرض الموظفين والمعلمين</label>
                        <!-- مؤشر وجود جدول للمؤسسة -->
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">المؤسسة:</span>
                            <span v-if="hasOrganizationActiveShift"
                                  class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800"
                                  title="يوجد جدول دوام مفعل للمؤسسة">
                                <i class="fas fa-check-circle mr-1"></i>
                                جدول مفعل
                            </span>
                            <span v-else
                                  class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600"
                                  title="لا يوجد جدول دوام مفعل للمؤسسة">
                                <i class="fas fa-times-circle mr-1"></i>
                                بدون جدول
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <div
                            v-for="dept in departments"
                            :key="dept.id"
                            class="p-5 rounded-xl border-2 border-gray-200 hover:border-indigo-500 hover:bg-indigo-50 transition-all text-right shadow-sm hover:shadow-md relative group">
                            <!-- زر الحذف -->
                            <button
                                v-if="dept.has_active_shift"
                                @click.stop="deleteDepartmentShift(dept.id, dept.name)"
                                class="absolute top-2 left-2 z-10 p-2 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors opacity-0 group-hover:opacity-100"
                                title="حذف جدول الدوام">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                            <button
                                @click="selectDepartment(dept.id)"
                                class="w-full text-right">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="font-bold text-gray-800 text-lg mb-1">{{ dept.name }}</h4>
                                        <!-- مؤشر وجود جدول دوام مفعل -->
                                        <span v-if="dept.has_active_shift"
                                              class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800"
                                              title="يوجد جدول دوام مفعل لهذا القسم">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            جدول مفعل
                                        </span>
                                        <span v-else
                                              class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600"
                                              title="لا يوجد جدول دوام مفعل لهذا القسم">
                                            <i class="fas fa-times-circle mr-1"></i>
                                            بدون جدول
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-shrink-0 h-12 w-12 rounded-full bg-indigo-100 flex items-center justify-center">
                                    <i class="fas fa-building text-indigo-600 text-xl"></i>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <!-- معلومات الموظفين والمعلمين -->
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center space-x-2 rtl:space-x-reverse">
                                        <span v-if="dept.employees_count > 0" class="bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full font-semibold">
                                            <i class="fas fa-user-tie mr-1"></i>{{ dept.employees_count }} موظف
                                        </span>
                                        <span v-if="dept.teachers_count > 0" class="bg-green-100 text-green-700 px-2.5 py-1 rounded-full font-semibold">
                                            <i class="fas fa-chalkboard-teacher mr-1"></i>{{ dept.teachers_count }} معلم
                                        </span>
                                    </div>
                                </div>

                                <!-- معلومات جدول الدوام -->
                                <div v-if="dept.shift_setting" class="pt-2 border-t border-gray-200 space-y-2">
                                    <div class="text-xs">
                                        <div class="font-semibold text-gray-700 mb-1">
                                            <i class="fas fa-clock mr-1 text-indigo-600"></i>
                                            {{ dept.shift_setting.name }}
                                        </div>
                                        <div class="text-gray-600">
                                            <span class="font-medium">الوقت:</span>
                                            {{ dept.shift_setting.start_time }} - {{ dept.shift_setting.end_time }}
                                        </div>
                                        <div class="text-gray-600 mt-1">
                                            <span class="font-medium">أيام العمل:</span>
                                            <span class="text-indigo-700 font-semibold">
                                                {{ dept.shift_setting.work_days_names?.join('، ') || '-' }}
                                            </span>
                                        </div>
                                        <div class="text-gray-600 mt-1">
                                            <span class="font-medium">عدد الأيام:</span>
                                            <span class="text-indigo-700 font-semibold">{{ dept.shift_setting.work_days_count }} أيام</span>
                                            <span class="text-gray-500 mr-1">/</span>
                                            <span class="font-medium">ساعات/أسبوع:</span>
                                            <span class="text-indigo-700 font-semibold">{{ dept.shift_setting.hours_per_week }} ساعة</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- إجمالي الأشخاص -->
                                <div class="pt-2 border-t border-gray-200">
                                    <span class="text-xs text-gray-600 font-medium">
                                        إجمالي: <span class="text-indigo-600 font-bold">{{ dept.employees_count + dept.teachers_count }}</span> شخص
                                    </span>
                                </div>
                            </div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Search & Filter Bar (when department is selected) -->
                <div v-else-if="selectedDepartmentId && activeView !== 'person' && activeView !== 'templates'" class="flex items-center space-x-4 rtl:space-x-reverse">
                    <button @click="clearDepartmentSelection"
                            class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors flex items-center font-medium">
                        <i class="fas fa-arrow-right mr-2"></i>
                        العودة للأقسام
                    </button>

                    <div class="flex-1 relative">
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="ابحث عن موظف أو معلم بالاسم..."
                            class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-lg text-gray-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all text-base">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg"></i>
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''; performSearch()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="flex items-center space-x-2 rtl:space-x-reverse bg-gray-50 px-3 py-2 rounded-lg">
                        <label class="text-sm text-gray-700 font-medium">عرض:</label>
                        <select
                            v-model="perPage"
                            @change="changePage(1)"
                            class="border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500 text-sm font-medium">
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <main class="bg-white shadow-md rounded-lg overflow-hidden min-h-[600px]" id="main-content">
                <!-- Welcome View -->
                <div v-if="activeView === 'welcome'" class="p-16 text-center">
                    <div class="max-w-md mx-auto">
                        <div class="h-32 w-32 mx-auto mb-6 rounded-full bg-gradient-to-br from-indigo-100 to-blue-100 flex items-center justify-center">
                            <i class="fas fa-building text-5xl text-indigo-500"></i>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-800 mb-3">ابدأ بإختيار القسم</h2>
                        <p class="text-gray-600 text-lg leading-relaxed">يرجى اختيار قسم من الأعلى لعرض الموظفين والمعلمين في هذا القسم وبدء إعداد الجداول.</p>
                    </div>
                </div>

                <!-- Personnel Table View (when department is selected) -->
                <div v-if="activeView === 'department' && selectedDepartmentId" class="p-6">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800 mb-1">الموظفين والمعلمين</h2>
                            <p v-if="personnel" class="text-sm text-gray-600">
                                إجمالي: <span class="font-bold text-indigo-600">{{ personnel.total }}</span> {{ personnel.total === 1 ? 'شخص' : 'شخص' }}
                                <span v-if="searchQuery" class="mr-2">| نتائج البحث</span>
                            </p>
                        </div>
                    </div>

                    <div v-if="!personnel || personnel.data.length === 0" class="text-center py-20">
                        <div class="h-24 w-24 mx-auto mb-6 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-users text-4xl text-gray-400"></i>
                        </div>
                        <p class="text-xl text-gray-500 mb-2 font-semibold">لا يوجد موظفين أو معلمين</p>
                        <p v-if="searchQuery" class="text-sm text-gray-400">جرب البحث بكلمات مختلفة أو <button @click="searchQuery = ''; performSearch()" class="text-indigo-600 hover:underline">مسح البحث</button></p>
                        <p v-else class="text-sm text-gray-400">اختر قسم آخر أو أضف موظفين/معلمين لهذا القسم</p>
                    </div>

                    <div v-else class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-lg">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gradient-to-r from-indigo-600 to-blue-600">
                                    <tr>
                                        <th class="px-6 py-4 text-right text-sm font-bold text-white uppercase tracking-wider">الاسم</th>
                                        <th class="px-6 py-4 text-center text-sm font-bold text-white uppercase tracking-wider">النوع</th>
                                        <th class="px-6 py-4 text-center text-sm font-bold text-white uppercase tracking-wider">القسم</th>
                                        <th class="px-6 py-4 text-center text-sm font-bold text-white uppercase tracking-wider">الدوام</th>
                                        <th class="px-6 py-4 text-center text-sm font-bold text-white uppercase tracking-wider">الإجراء</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr
                                        v-for="person in personnel.data"
                                        :key="`${person.type}-${person.id}`"
                                        class="hover:bg-indigo-50 transition-all duration-200 cursor-pointer border-l-4 group"
                                        :class="selectedPersonIdentifier === `${person.type}-${person.id}`
                                            ? 'bg-indigo-50 border-indigo-500 shadow-md'
                                            : 'border-transparent hover:border-indigo-300'"
                                        @click="selectPerson(person)">
                                        <td class="px-6 py-5">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-14 w-14 rounded-full flex items-center justify-center mr-4 shadow-sm"
                                                     :class="person.type === 'Employee' ? 'bg-blue-100 group-hover:bg-blue-200' : 'bg-green-100 group-hover:bg-green-200'">
                                                    <i :class="person.type === 'Employee' ? 'fas fa-user-tie text-blue-600 text-xl' : 'fas fa-chalkboard-teacher text-green-600 text-xl'"></i>
                                                </div>
                                                <div>
                                                    <div class="text-base font-bold text-gray-900">{{ person.full_name || person.name }}</div>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        <span class="bg-gray-100 px-2 py-0.5 rounded">ID: {{ person.id }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5 whitespace-nowrap text-center">
                                            <span class="inline-flex items-center px-4 py-2 rounded-full text-xs font-bold shadow-sm"
                                                  :class="person.type === 'Employee' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                                                <i :class="person.type === 'Employee' ? 'fas fa-user-tie mr-2' : 'fas fa-chalkboard-teacher mr-2'"></i>
                                                {{ person.type_label }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-5 whitespace-nowrap text-center">
                                            <span v-if="person.department" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-800 shadow-sm">
                                                <i class="fas fa-building mr-2"></i>{{ person.department }}
                                            </span>
                                            <span v-else class="text-xs text-gray-400 italic">غير محدد</span>
                                        </td>
                                        <td class="px-6 py-5 whitespace-nowrap text-center">
                                            <span v-if="person.shift" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold shadow-sm"
                                                  :class="person.shift_source === 'department' ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-800'">
                                                <i class="fas fa-clock mr-2"></i>
                                                {{ person.shift }}
                                                <span v-if="person.shift_source" class="mr-1 text-xs opacity-75">
                                                    ({{ person.shift_source === 'department' ? 'قسم' : 'مؤسسة' }})
                                                </span>
                                            </span>
                                            <span v-else class="text-xs text-gray-400 italic">غير محدد</span>
                                        </td>
                                        <td class="px-6 py-5 whitespace-nowrap text-center">
                                            <button
                                                @click.stop="selectPerson(person)"
                                                class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-bold rounded-lg text-white transition-all shadow-md"
                                                :class="selectedPersonIdentifier === `${person.type}-${person.id}`
                                                    ? 'bg-indigo-700 shadow-lg scale-105'
                                                    : 'bg-indigo-600 hover:bg-indigo-700 hover:shadow-lg hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500'">
                                                <i class="fas fa-calendar-alt mr-2"></i>
                                                {{ selectedPersonIdentifier === `${person.type}-${person.id}` ? 'محدد' : 'اختيار' }}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="personnel && personnel.last_page > 1" class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2 rtl:space-x-reverse">
                                    <span class="text-sm font-medium text-gray-700">عرض</span>
                                    <select
                                        v-model="perPage"
                                        @change="changePage(1)"
                                        class="text-sm border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-indigo-500 font-medium px-2 py-1">
                                        <option :value="10">10</option>
                                        <option :value="15">15</option>
                                        <option :value="25">25</option>
                                        <option :value="50">50</option>
                                    </select>
                                    <span class="text-sm font-medium text-gray-700">لكل صفحة</span>
                                </div>

                                <div class="flex items-center space-x-2 rtl:space-x-reverse">
                                    <button
                                        @click="changePage(personnel.current_page - 1)"
                                        :disabled="!personnel.prev_page_url"
                                        class="px-4 py-2 text-sm border rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-white transition-all font-medium"
                                        :class="personnel.prev_page_url ? 'border-gray-300 bg-white shadow-sm' : 'border-gray-200 bg-gray-50'">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>

                                    <div class="px-5 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg font-semibold shadow-sm">
                                        صفحة <span class="text-indigo-600">{{ personnel.current_page }}</span> من <span class="text-indigo-600">{{ personnel.last_page }}</span>
                                    </div>

                                    <button
                                        @click="changePage(personnel.current_page + 1)"
                                        :disabled="!personnel.next_page_url"
                                        class="px-4 py-2 text-sm border rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-white transition-all font-medium"
                                        :class="personnel.next_page_url ? 'border-gray-300 bg-white shadow-sm' : 'border-gray-200 bg-gray-50'">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Template Management View -->
                <div v-if="activeView === 'templates'" class="p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">إدارة قوالب الجدولة</h2>
                    <p class="text-gray-600">هنا يمكنك إنشاء وتعديل القوالب التي تسهل عملية تعيين القيود لمجموعات من الموظفين أو المعلمين.</p>
                </div>

                <!-- Person-specific View -->
                <div v-if="activeView === 'person' && selectedPerson" class="p-6">
                    <!-- Header with Back Button -->
                    <div class="mb-6 no-print">
                        <button @click="selectedPersonIdentifier = null; activeView = selectedDepartmentId ? 'department' : 'welcome'"
                                class="mb-4 px-4 py-2 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition-colors flex items-center font-medium">
                            <i class="fas fa-arrow-right mr-2"></i>
                            العودة لقائمة الموظفين
                        </button>

                        <div class="bg-gradient-to-r from-indigo-500 to-blue-600 rounded-xl p-8 border border-indigo-300 shadow-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-20 w-20 rounded-full flex items-center justify-center mr-6 shadow-lg bg-white"
                                         :class="selectedPerson.type === 'Employee' ? 'ring-4 ring-blue-200' : 'ring-4 ring-green-200'">
                                        <i :class="selectedPerson.type === 'Employee' ? 'fas fa-user-tie text-blue-600 text-3xl' : 'fas fa-chalkboard-teacher text-green-600 text-3xl'"></i>
                        </div>
                        <div>
                                        <h2 class="text-3xl font-bold text-white mb-2">{{ selectedPerson.full_name || selectedPerson.name || '...' }}</h2>
                                        <div class="flex items-center space-x-4 rtl:space-x-reverse">
                                            <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold shadow-md bg-white"
                                                  :class="selectedPerson.type === 'Employee' ? 'text-blue-700' : 'text-green-700'">
                                                <i :class="selectedPerson.type === 'Employee' ? 'fas fa-user-tie mr-2' : 'fas fa-chalkboard-teacher mr-2'"></i>
                                                {{ selectedPerson.type === 'Employee' ? 'موظف' : 'معلم' }}
                                            </span>
                                            <span v-if="totalWorkHours > 0" class="text-sm text-white bg-white/20 px-4 py-1.5 rounded-full font-semibold">
                                                <i class="fas fa-clock mr-2"></i>
                                                إجمالي الساعات: <span class="font-bold">{{ totalWorkHours }}</span> ساعة
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="border-b border-gray-200 mb-6 no-print">
                        <nav class="flex space-x-4 rtl:space-x-reverse" aria-label="Tabs">
                            <button @click="activePersonTab = 'constraints'"
                                    :class="['px-6 py-3 border-b-2 font-medium text-sm transition-colors',
                                             activePersonTab === 'constraints'
                                                ? 'border-indigo-500 text-indigo-600'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                <i class="fas fa-sliders-h mr-2"></i>القيود
                            </button>
                            <button v-if="isHourlyEmployee"
                                    @click="activePersonTab = 'custom-hours'"
                                    :class="['px-6 py-3 border-b-2 font-medium text-sm transition-colors',
                                             activePersonTab === 'custom-hours'
                                                ? 'border-indigo-500 text-indigo-600'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                <i class="fas fa-clock mr-2"></i>الساعات المخصصة
                            </button>
                            <button @click="activePersonTab = 'timetable'"
                                    :class="['px-6 py-3 border-b-2 font-medium text-sm transition-colors',
                                             activePersonTab === 'timetable'
                                                ? 'border-indigo-500 text-indigo-600'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                <i class="fas fa-calendar-week mr-2"></i>الجدول الأسبوعي
                            </button>
                            <button @click="activePersonTab = 'monthly'"
                                    :class="['px-6 py-3 border-b-2 font-medium text-sm transition-colors',
                                             activePersonTab === 'monthly'
                                                ? 'border-indigo-500 text-indigo-600'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                                <i class="fas fa-calendar-alt mr-2"></i>العرض الشهري
                            </button>
                            <button v-if="activePersonTab === 'timetable'"
                                    @click="isEditMode = !isEditMode"
                                    class="mr-auto rtl:mr-0 rtl:ml-auto px-4 py-2 rounded-lg text-sm font-semibold transition-all"
                                    :class="isEditMode ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-yellow-500 text-white hover:bg-yellow-600'">
                                <i :class="['fas', 'mr-2', isEditMode ? 'fa-check' : 'fa-edit']"></i>
                                {{ isEditMode ? 'إنهاء التعديل' : 'تعديل الجدول' }}
                            </button>
                        </nav>
                        </div>

                    <!-- Custom Hours Content (for hourly employees) -->
                    <div v-show="activePersonTab === 'custom-hours'" class="no-print">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                <div>
                                    <h4 class="font-semibold text-blue-800">إدارة الساعات المخصصة</h4>
                                    <p class="text-sm text-blue-700">حدد الساعات المخصصة لكل يوم من أيام الأسبوع للموظفين الذين يعملون بنمط الساعات</p>
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent="submitCustomHours">
                            <div class="bg-white border rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider">اليوم</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider">عدد الساعات</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider">وقت البداية (اختياري)</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider">وقت النهاية (اختياري)</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider">ملاحظات</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="dayHour in customHoursForm.hours" :key="dayHour.day_of_week"
                                            :class="dayHour.hours > 0 ? 'bg-green-50' : ''">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ dayHour.day_name }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input
                                                    type="number"
                                                    v-model.number="dayHour.hours"
                                                    min="0"
                                                    max="24"
                                                    step="0.5"
                                                    class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="0">
                                                <span class="text-xs text-gray-500 mr-1">ساعة</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input
                                                    type="time"
                                                    v-model="dayHour.start_time"
                                                    :disabled="!dayHour.hours || dayHour.hours <= 0"
                                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    :class="{ 'bg-gray-100': !dayHour.hours || dayHour.hours <= 0 }">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input
                                                    type="time"
                                                    v-model="dayHour.end_time"
                                                    :disabled="!dayHour.hours || dayHour.hours <= 0"
                                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    :class="{ 'bg-gray-100': !dayHour.hours || dayHour.hours <= 0 }">
                                            </td>
                                            <td class="px-6 py-4">
                                                <input
                                                    type="text"
                                                    v-model="dayHour.notes"
                                                    :disabled="!dayHour.hours || dayHour.hours <= 0"
                                                    placeholder="ملاحظات..."
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    :class="{ 'bg-gray-100': !dayHour.hours || dayHour.hours <= 0 }">
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-right">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-sm font-semibold text-gray-700">
                                                        إجمالي الساعات الأسبوعية: <span class="text-indigo-600 text-lg">{{ calculateTotalCustomHours }}</span> ساعة
                                                    </span>
                                                    <button
                                                        type="submit"
                                                        :disabled="customHoursForm.processing"
                                                        class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                                        <i class="fas fa-save mr-2"></i>
                                                        حفظ الساعات المخصصة
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </form>
                    </div>

                    <!-- Individual Constraints Content -->
                    <div v-show="activePersonTab === 'constraints'" class="no-print">
                        <!-- معلومات إعدادات الدوام الافتراضية -->
                        <div v-if="activeShiftSetting" class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-600 mt-1 ml-3"></i>
                                <div class="flex-1">
                                    <h4 class="text-sm font-semibold text-blue-900 mb-2">
                                        إعدادات الدوام الافتراضية
                                        <span v-if="shiftSettingSource" class="text-blue-700 font-normal">
                                            ({{ shiftSettingSource.type === 'department' ? `لقسم ${shiftSettingSource.name}` : `للمؤسسة` }})
                                        </span>
                                    </h4>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm text-blue-800">
                                        <div>
                                            <span class="font-medium">وقت البدء:</span>
                                            <span class="mr-2">{{ formatTimeTo12Hour(activeShiftSetting.start_time) }}</span>
                                        </div>
                                        <div>
                                            <span class="font-medium">وقت الانتهاء:</span>
                                            <span class="mr-2">{{ formatTimeTo12Hour(activeShiftSetting.end_time) }}</span>
                                        </div>
                                        <div>
                                            <span class="font-medium">ساعات/أسبوع:</span>
                                            <span class="mr-2">{{ activeShiftSetting.hours_per_week || 'غير محسوبة' }}</span>
                                        </div>
                                        <div>
                                            <span class="font-medium">أيام العمل:</span>
                                            <span class="mr-2">
                                                <span v-for="(day, idx) in (activeShiftSetting.work_days || [])" :key="day">
                                                    {{ weekDays.find(d => d.v === day)?.n }}{{ idx < (activeShiftSetting.work_days.length - 1) ? '، ' : '' }}
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                    <p class="text-xs text-blue-700 mt-2">
                                        <i class="fas fa-lightbulb mr-1"></i>
                                        هذه القيم ستُستخدم كقيم افتراضية إذا لم تقم بتعيين قيود فردية. يمكنك تجاوزها أدناه.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- عرض القيود الفردية المحفوظة -->
                        <div v-if="props.personnelConstraints && Object.keys(props.personnelConstraints).length > 0" class="mb-6 p-4 bg-yellow-50 border-2 border-yellow-300 rounded-lg">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-info-circle text-yellow-700 text-xl mr-2"></i>
                                    <h4 class="text-lg font-bold text-yellow-900">القيود الفردية المحفوظة</h4>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="applyDefaultShiftSettings"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm flex items-center"
                                    >
                                        <i class="fas fa-building mr-2"></i>
                                        تطبيق إعدادات {{ shiftSettingSource?.name || 'المؤسسة' }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="deleteIndividualConstraints"
                                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm flex items-center"
                                    >
                                        <i class="fas fa-trash mr-2"></i>
                                        حذف القيود الفردية
                                    </button>
                                </div>
                            </div>
                            <div class="bg-white p-4 rounded-lg border border-yellow-200">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div v-if="props.personnelConstraints.employment_type" class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                        <span class="text-gray-600 font-medium">نوع التوظيف:</span>
                                        <span class="text-gray-800 font-semibold">
                                            <span v-if="props.personnelConstraints.employment_type === 'monthly_full'">شهري كامل</span>
                                            <span v-else-if="props.personnelConstraints.employment_type === 'monthly_partial'">شهري جزئي</span>
                                            <span v-else-if="props.personnelConstraints.employment_type === 'hourly'">بالساعات</span>
                                            <span v-else>{{ props.personnelConstraints.employment_type }}</span>
                                        </span>
                                    </div>
                                    <div v-if="props.personnelConstraints.total_hours_per_week" class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                        <span class="text-gray-600 font-medium">إجمالي الساعات/أسبوع:</span>
                                        <span class="text-gray-800 font-semibold">{{ props.personnelConstraints.total_hours_per_week }} ساعة</span>
                                    </div>
                                    <div v-if="props.personnelConstraints.required_days && Array.isArray(props.personnelConstraints.required_days)" class="md:col-span-2 flex items-center justify-between p-2 bg-gray-50 rounded">
                                        <span class="text-gray-600 font-medium">أيام العمل:</span>
                                        <span class="text-gray-800 font-semibold">
                                            <span v-for="(day, idx) in props.personnelConstraints.required_days" :key="day">
                                                {{ weekDays.find(d => d.v === day)?.n }}{{ idx < (props.personnelConstraints.required_days.length - 1) ? '، ' : '' }}
                                            </span>
                                        </span>
                                    </div>
                                    <!-- الوردية من القيود الفردية (assigned_shift_id) -->
                                    <div v-if="props.personnelConstraints.assigned_shift_id" class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                        <span class="text-gray-600 font-medium">الوردية المحددة (من القيود):</span>
                                        <span class="text-gray-800 font-semibold">
                                            {{ shifts.find(s => s.id === props.personnelConstraints.assigned_shift_id)?.name || 'غير محدد' }}
                                        </span>
                                    </div>
                                    <!-- الوردية من ShiftAssignment -->
                                    <div v-if="props.personnelConstraints.assigned_shift_assignment" class="flex items-center justify-between p-2 bg-blue-50 rounded border border-blue-200">
                                        <div class="flex-1">
                                            <span class="text-gray-600 font-medium block mb-1">الوردية المحددة:</span>
                                            <span class="text-blue-800 font-semibold block">
                                                {{ props.personnelConstraints.assigned_shift_assignment.shift_name }}
                                                <span class="text-gray-600 text-sm mr-2">
                                                    ({{ props.personnelConstraints.assigned_shift_assignment.start_time }} - {{ props.personnelConstraints.assigned_shift_assignment.end_time }})
                                                </span>
                                            </span>
                                        </div>
                                        <button
                                            @click="removeShiftAssignment"
                                            class="text-red-600 hover:text-red-800 flex items-center text-sm mr-3 transition-colors"
                                            title="إزالة الوردية المحددة"
                                        >
                                            <i class="fas fa-times-circle mr-1"></i>
                                            إزالة
                                        </button>
                                    </div>
                                    <div v-if="props.personnelConstraints.max_subjects_per_day" class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                        <span class="text-gray-600 font-medium">أقصى مواد/يوم:</span>
                                        <span class="text-gray-800 font-semibold">{{ props.personnelConstraints.max_subjects_per_day }}</span>
                                    </div>
                                    <div v-if="props.personnelConstraints.max_sections_per_day" class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                        <span class="text-gray-600 font-medium">أقصى فصول/يوم:</span>
                                        <span class="text-gray-800 font-semibold">{{ props.personnelConstraints.max_sections_per_day }}</span>
                                    </div>
                                </div>
                                <p class="text-xs text-yellow-700 mt-3 p-2 bg-yellow-100 rounded">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    هذه القيود الفردية ستتجاوز إعدادات المؤسسة أو القسم. يمكنك حذفها لتطبيق الإعدادات الافتراضية.
                                </p>
                            </div>
                        </div>

                        <!-- تطبيق قالب -->
                         <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <label class="block text-sm font-medium text-gray-800 mb-2">تطبيق قالب جاهز (اختياري)</label>
                            <select @change="applyTemplate" class="w-full max-w-md bg-white border border-gray-300 text-gray-800 text-sm rounded-lg block p-2.5">
                                <option value="">-- تطبيق قالب --</option>
                                <option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</option>
                            </select>
                        </div>

                         <form @submit.prevent="submitIndividualConstraints">
                            <div class="space-y-8">
                                <div class="bg-white border border-gray-200 p-6 rounded-lg shadow-sm">
                                    <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg leading-6 font-medium text-gray-800">القيود العامة</h3>
                                        <span v-if="props.personnelConstraints && Object.keys(props.personnelConstraints).length > 0"
                                              class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            تم حفظ قيود فردية
                                        </span>
                                    </div>
                                    <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                        <div class="sm:col-span-3">
                                            <label class="block text-sm font-medium text-gray-800">
                                                نوع التوظيف
                                                <span class="text-red-500 mr-1">*</span>
                                            </label>
                                            <select v-model="individualConstraintsForm.employment_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-gray-800 focus:ring-indigo-500 focus:border-indigo-500">
                                                <option :value="null">-- اختر نوع التوظيف --</option>
                                                <option value="monthly_full">شهري كامل</option>
                                                <option value="monthly_partial">شهري جزئي (أيام محددة)</option>
                                                <option value="hourly">بالساعات</option>
                                            </select>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <span v-if="activeShiftSetting && !individualConstraintsForm.employment_type">
                                                    القيمة الافتراضية من إعدادات {{ shiftSettingSource?.name || 'المؤسسة' }}: <strong>شهري كامل</strong>
                                                </span>
                                            </p>
                                        </div>
                                        <div v-if="selectedPerson.type === 'Employee'" class="sm:col-span-3">
                                            <div class="flex items-center justify-between mb-2">
                                                <label class="block text-sm font-medium text-gray-800">
                                                    اختيار الوردية (الدوام)
                                                    <span class="text-xs font-normal text-gray-500 mr-2">(اختياري)</span>
                                                </label>
                                                <button
                                                    v-if="individualConstraintsForm.assigned_shift_id"
                                                    type="button"
                                                    @click="individualConstraintsForm.assigned_shift_id = null"
                                                    class="text-xs text-red-600 hover:text-red-800 flex items-center transition-colors"
                                                    title="إزالة الوردية المحددة والاعتماد على إعدادات المؤسسة/القسم"
                                                >
                                                    <i class="fas fa-times-circle mr-1"></i>
                                                    إزالة الوردية
                                                </button>
                                            </div>
                                            <select v-model="individualConstraintsForm.assigned_shift_id"
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-gray-800 focus:ring-indigo-500 focus:border-indigo-500"
                                                    @change="validateShiftSelection">
                                                <option :value="null">-- دوام مرن (حسب الساعات) --</option>
                                                <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                                                    {{ shift.name }} ({{ formatTimeTo12Hour(shift.start_time) }} - {{ formatTimeTo12Hour(shift.end_time) }})
                                                </option>
                                            </select>
                                            <div v-if="activeShiftSetting && !individualConstraintsForm.assigned_shift_id" class="mt-2 p-2 bg-gray-50 rounded text-xs text-gray-600">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                <strong>إعدادات القسم:</strong> {{ formatTimeTo12Hour(activeShiftSetting.start_time) }} - {{ formatTimeTo12Hour(activeShiftSetting.end_time) }}
                                                <span v-if="activeShiftSetting.hours_per_week" class="mr-2">
                                                    ({{ activeShiftSetting.hours_per_week }} ساعة/أسبوع)
                                                </span>
                                            </div>
                                            <div v-if="individualConstraintsForm.assigned_shift_id" class="mt-2 p-2 bg-green-50 border border-green-200 rounded text-xs text-green-700">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                تم تحديد وردية ثابتة. سيتم استخدام أوقات هذه الوردية في الجدول.
                                            </div>
                                            <p v-else class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-lightbulb mr-1"></i>
                                                إذا لم تحدد وردية، سيتم استخدام إعدادات القسم أو المؤسسة كقيم افتراضية
                                            </p>
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="block text-sm font-medium text-gray-800">
                                                إجمالي ساعات العمل في الأسبوع
                                                <span v-if="activeShiftSetting && activeShiftSetting.hours_per_week" class="text-xs font-normal text-gray-500 mr-2">
                                                    (افتراضي: {{ activeShiftSetting.hours_per_week }} ساعة)
                                                </span>
                                            </label>
                                            <input type="number" step="0.5" v-model.number="individualConstraintsForm.total_hours_per_week"
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-gray-800 focus:ring-indigo-500 focus:border-indigo-500"
                                                   :disabled="!!individualConstraintsForm.assigned_shift_id"
                                                   :placeholder="activeShiftSetting && activeShiftSetting.hours_per_week ? activeShiftSetting.hours_per_week.toString() : '40'">
                                            <p v-if="individualConstraintsForm.assigned_shift_id" class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                يتم تجاهل هذا الحقل عند تحديد دوام ثابت.
                                            </p>
                                            <p v-else-if="activeShiftSetting && activeShiftSetting.hours_per_week" class="text-xs text-blue-600 mt-1">
                                                <i class="fas fa-lightbulb mr-1"></i>
                                                القيمة الافتراضية من إعدادات {{ shiftSettingSource?.name || 'المؤسسة' }}: {{ activeShiftSetting.hours_per_week }} ساعة/أسبوع
                                            </p>
                                        </div>
                                        <div class="sm:col-span-6">
                                            <label class="block text-sm font-medium text-gray-800 mb-2">
                                                الأيام المطلوبة للعمل
                                                <span class="text-xs font-normal text-gray-500 mr-2">(السبت والجمعة عادة عطلة)</span>
                                            </label>
                                            <div v-if="activeShiftSetting && activeShiftSetting.work_days" class="mb-2 text-xs text-blue-600 bg-blue-50 p-2 rounded">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                أيام العمل الافتراضية من إعدادات {{ shiftSettingSource?.name || 'المؤسسة' }}:
                                                <span class="font-semibold mr-1">
                                                    <span v-for="(day, idx) in activeShiftSetting.work_days" :key="day">
                                                        {{ weekDays.find(d => d.v === day)?.n }}{{ idx < (activeShiftSetting.work_days.length - 1) ? '، ' : '' }}
                                                    </span>
                                                </span>
                                            </div>
                                            <div class="mt-2 flex flex-wrap gap-4">
                                                <label v-for="day in weekDays" :key="day.v" class="flex items-center relative">
                                                    <input type="checkbox" :value="day.v" v-model="individualConstraintsForm.required_days"
                                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                           :checked="activeShiftSetting && activeShiftSetting.work_days && activeShiftSetting.work_days.includes(day.v) && !individualConstraintsForm.required_days.includes(day.v)">
                                                    <span class="ml-2 rtl:mr-2 text-sm" :class="day.isWeekend ? 'text-orange-600 font-semibold' : 'text-gray-800'">
                                                        {{ day.n }}
                                                        <span v-if="day.isWeekend" class="text-xs bg-yellow-100 text-yellow-800 px-1.5 py-0.5 rounded mr-1" title="يوم عطلة">عطلة</span>
                                                    </span>
                                                </label>
                                            </div>
                                            <p v-if="individualConstraintsForm.required_days.includes(5) || individualConstraintsForm.required_days.includes(6)"
                                               class="mt-2 text-xs text-orange-600 bg-orange-50 p-2 rounded-lg border border-orange-200">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                <span v-if="individualConstraintsForm.required_days.includes(5) && individualConstraintsForm.required_days.includes(6)">
                                                    تم اختيار أيام عطلة (السبت والجمعة). تأكد من أن الموظف/المعلم يعمل في هذه الأيام.
                                                </span>
                                                <span v-else-if="individualConstraintsForm.required_days.includes(6)">
                                                    تم اختيار يوم السبت (عادة عطلة). تأكد من أن الموظف/المعلم يعمل في هذا اليوم.
                                                </span>
                                                <span v-else-if="individualConstraintsForm.required_days.includes(5)">
                                                    تم اختيار يوم الجمعة (عادة عطلة). تأكد من أن الموظف/المعلم يعمل في هذا اليوم.
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="selectedPerson.type === 'Teacher'" class="pt-8 bg-gray-50 p-6 rounded-lg mt-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-800">قيود خاصة بالمعلمين</h3>
                                    <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                        <div class="sm:col-span-3">
                                            <label class="block text-sm font-medium text-gray-800">أقصى عدد مواد مختلفة في اليوم</label>
                                            <input type="number" v-model="individualConstraintsForm.max_subjects_per_day" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-gray-800">
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="block text-sm font-medium text-gray-800">أقصى عدد فصول مختلفة في اليوم</label>
                                            <input type="number" v-model="individualConstraintsForm.max_sections_per_day" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-gray-800">
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="block text-sm font-medium text-gray-800">المواد المسموح بتدريسها</label>
                                            <select multiple v-model="individualConstraintsForm.allowed_subjects" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm h-32 text-gray-800">
                                                <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="block text-sm font-medium text-gray-800">الفصول المسموح بالتدريس فيها</label>
                                            <select multiple v-model="individualConstraintsForm.allowed_sections" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm h-32 text-gray-800">
                                                <option v-for="section in sections" :key="section.id" :value="section.id">{{ section.name }} ({{ section.grade.name }})</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- ملخص الجدول المتوقع -->
                                <div v-if="calculateScheduleSummary" class="mt-8 bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 p-6 rounded-lg shadow-sm">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-semibold text-gray-800">
                                            <i class="fas fa-calendar-check mr-2 text-blue-600"></i>
                                            ملخص الجدول المتوقع
                                        </h3>
                                        <span v-if="calculateScheduleSummary.isValid" class="text-xs bg-green-100 text-green-800 px-3 py-1 rounded-full font-semibold">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            جاهز للاعتماد
                                        </span>
                                        <span v-else class="text-xs bg-red-100 text-red-800 px-3 py-1 rounded-full font-semibold">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            يحتاج إلى تصحيح
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                                        <div class="bg-white p-4 rounded-lg border border-gray-200">
                                            <div class="text-xs text-gray-500 mb-1">نوع التوظيف</div>
                                            <div class="text-sm font-semibold text-gray-800">
                                                <span v-if="calculateScheduleSummary.employmentType === 'monthly_full'">شهري كامل</span>
                                                <span v-else-if="calculateScheduleSummary.employmentType === 'monthly_partial'">شهري جزئي</span>
                                                <span v-else-if="calculateScheduleSummary.employmentType === 'hourly'">بالساعات</span>
                                                <span v-else class="text-gray-400">غير محدد</span>
                                            </div>
                                        </div>

                                        <div class="bg-white p-4 rounded-lg border border-gray-200">
                                            <div class="text-xs text-gray-500 mb-1">أيام العمل</div>
                                            <div class="text-sm font-semibold text-gray-800">
                                                {{ calculateScheduleSummary.workDaysCount }} يوم
                                                <span v-if="calculateScheduleSummary.workDaysCount > 0" class="text-xs text-gray-500 mr-2">
                                                    ({{ calculateScheduleSummary.workDays.map(d => weekDays.find(w => w.v === d)?.n).filter(Boolean).join('، ') }})
                                                </span>
                                            </div>
                                        </div>

                                        <div class="bg-white p-4 rounded-lg border border-gray-200">
                                            <div class="text-xs text-gray-500 mb-1">إجمالي الساعات/أسبوع</div>
                                            <div class="text-sm font-semibold text-gray-800">
                                                {{ calculateScheduleSummary.totalHoursPerWeek.toFixed(1) }} ساعة
                                                <span v-if="calculateScheduleSummary.hoursPerDay > 0" class="text-xs text-gray-500 mr-2">
                                                    ({{ calculateScheduleSummary.hoursPerDay.toFixed(1) }} ساعة/يوم)
                                                </span>
                                            </div>
                                        </div>

                                        <div v-if="calculateScheduleSummary.shift" class="bg-white p-4 rounded-lg border border-gray-200">
                                            <div class="text-xs text-gray-500 mb-1">الوردية المحددة</div>
                                            <div class="text-sm font-semibold text-indigo-600">{{ calculateScheduleSummary.shift }}</div>
                                        </div>

                                        <div v-if="calculateScheduleSummary.startTime && calculateScheduleSummary.endTime" class="bg-white p-4 rounded-lg border border-gray-200">
                                            <div class="text-xs text-gray-500 mb-1">أوقات العمل</div>
                                            <div class="text-sm font-semibold text-gray-800">
                                                {{ formatTimeTo12Hour(calculateScheduleSummary.startTime) }} - {{ formatTimeTo12Hour(calculateScheduleSummary.endTime) }}
                                            </div>
                                        </div>

                                        <div v-if="calculateScheduleSummary.departmentSettings" class="bg-white p-4 rounded-lg border border-blue-200">
                                            <div class="text-xs text-blue-600 mb-1">
                                                <i class="fas fa-building mr-1"></i>
                                                إعدادات {{ shiftSettingSource?.name || 'المؤسسة' }}
                                            </div>
                                            <div class="text-xs text-gray-600">
                                                {{ formatTimeTo12Hour(calculateScheduleSummary.departmentSettings.start_time) }} - {{ formatTimeTo12Hour(calculateScheduleSummary.departmentSettings.end_time) }}
                                                <span v-if="calculateScheduleSummary.departmentSettings.hours_per_week" class="mr-2">
                                                    ({{ calculateScheduleSummary.departmentSettings.hours_per_week }} ساعة/أسبوع)
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- التحذيرات والأخطاء -->
                                    <div v-if="calculateScheduleSummary.warnings.length > 0" class="mt-4">
                                        <div v-for="(warning, idx) in calculateScheduleSummary.warnings" :key="idx"
                                             :class="calculateScheduleSummary.isValid ? 'bg-yellow-50 border-yellow-200 text-yellow-800' : 'bg-red-50 border-red-200 text-red-800'"
                                             class="p-3 rounded-lg border mb-2 text-sm">
                                            <i :class="calculateScheduleSummary.isValid ? 'fa-exclamation-circle' : 'fa-times-circle'"
                                               class="fas mr-2"></i>
                                            {{ warning }}
                                        </div>
                                    </div>

                                    <!-- معلومات إضافية حسب نوع التوظيف -->
                                    <div v-if="calculateScheduleSummary.isValid && calculateScheduleSummary.workDaysCount > 0" class="mt-4 space-y-2">
                                        <!-- للموظفين الشهريين الكاملين -->
                                        <div v-if="individualConstraintsForm.employment_type === 'monthly_full'" class="p-3 bg-green-50 border border-green-200 rounded-lg text-xs text-green-700">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            <strong>شهري كامل:</strong> سيتم إنشاء جدول عمل أسبوعي يحتوي على {{ calculateScheduleSummary.workDaysCount }} أيام عمل
                                            بإجمالي {{ calculateScheduleSummary.totalHoursPerWeek.toFixed(1) }} ساعة في الأسبوع.
                                            <span v-if="calculateScheduleSummary.shift" class="mr-2">
                                                سيتم استخدام وردية "{{ calculateScheduleSummary.shift }}" في جميع أيام العمل.
                                            </span>
                                        </div>

                                        <!-- للموظفين الشهريين الجزئيين -->
                                        <div v-if="individualConstraintsForm.employment_type === 'monthly_partial'" class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <strong>شهري جزئي:</strong> الأيام المحددة للعمل:
                                            <span class="font-semibold mr-1">
                                                {{ calculateScheduleSummary.workDays.map(d => weekDays.find(w => w.v === d)?.n).filter(Boolean).join('، ') }}
                                            </span>
                                            <div v-if="props.customHours && props.customHours.length > 0" class="mt-2">
                                                <i class="fas fa-clock mr-1"></i>
                                                الساعات المخصصة:
                                                <span class="font-semibold">
                                                    {{ props.customHours.reduce((sum, ch) => sum + (parseFloat(ch.hours) || 0), 0).toFixed(1) }} ساعة/أسبوع
                                                </span>
                                            </div>
                                            <div v-else class="mt-2 text-orange-600">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                لم يتم تحديد ساعات مخصصة - سيتم استخدام إعدادات القسم/المؤسسة
                                            </div>
                                        </div>

                                        <!-- للموظفين بالساعات -->
                                        <div v-if="individualConstraintsForm.employment_type === 'hourly'" class="p-3 bg-purple-50 border border-purple-200 rounded-lg text-xs text-purple-700">
                                            <i class="fas fa-clock mr-1"></i>
                                            <strong>بالساعات:</strong>
                                            <div class="mt-1">
                                                الأيام: <span class="font-semibold">{{ calculateScheduleSummary.workDays.map(d => weekDays.find(w => w.v === d)?.n).filter(Boolean).join('، ') }}</span>
                                            </div>
                                            <div class="mt-1">
                                                إجمالي الساعات/أسبوع: <span class="font-semibold">{{ calculateScheduleSummary.totalHoursPerWeek.toFixed(1) }} ساعة</span>
                                            </div>
                                            <div class="mt-1">
                                                متوسط الساعات/يوم: <span class="font-semibold">{{ (calculateScheduleSummary.totalHoursPerWeek / calculateScheduleSummary.workDaysCount).toFixed(1) }} ساعة</span>
                                            </div>
                                            <div v-if="props.customHours && props.customHours.length > 0" class="mt-2 text-green-600">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                تم تحديد ساعات مخصصة - سيتم استخدامها في الجدول
                                            </div>
                                            <div v-else class="mt-2 text-orange-600">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                يجب تحديد الساعات اليومية لكل يوم في تبويب "الساعات المخصصة"
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-5 mt-5 border-t border-gray-200">
                                <div class="flex justify-between items-center">
                                    <div class="text-sm text-gray-600">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <span v-if="individualConstraintsForm.employment_type === 'monthly_full'">
                                            سيتم حفظ القيود وإنشاء الجدول تلقائياً بعد الحفظ
                                        </span>
                                        <span v-else-if="individualConstraintsForm.employment_type === 'monthly_partial'">
                                            سيتم حفظ القيود وإنشاء الجدول تلقائياً - تأكد من تحديد الساعات المخصصة إن أردت
                                        </span>
                                        <span v-else-if="individualConstraintsForm.employment_type === 'hourly'">
                                            يجب تحديد الساعات المخصصة لكل يوم قبل إنشاء الجدول
                                        </span>
                                        <span v-else>
                                            بعد حفظ القيود، يمكنك إنشاء الجدول من تبويب "الجدول الأسبوعي"
                                        </span>
                                    </div>
                                    <button type="submit"
                                            :disabled="individualConstraintsForm.processing || (calculateScheduleSummary && !calculateScheduleSummary.isValid)"
                                            :class="(calculateScheduleSummary && !calculateScheduleSummary.isValid) ? 'bg-gray-400 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700'"
                                            class="text-white px-6 py-2 rounded-md transition-colors flex items-center">
                                        <i class="fas fa-save mr-2"></i>
                                        حفظ القيود وإنشاء الجدول
                                    </button>
                                </div>
                            </div>
                         </form>
                    </div>

                    <!-- Timetable View Content -->
                    <div v-show="activePersonTab === 'timetable'">
                        <div id="printable-area">
                             <div class="flex justify-between items-center mb-6 no-print">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800">الجدول الأسبوعي</h3>
                                    <div v-if="isEditMode" class="text-sm text-yellow-600 mt-1 space-y-1">
                                        <p>
                                            <i class="fas fa-info-circle mr-1"></i>
                                            وضع التعديل نشط - يمكنك سحب وإفلات الإدخالات أو النقر لإضافة/تعديل
                                        </p>
                                        <p v-if="isMonthlyEmployee" class="text-blue-600">
                                            <i class="fas fa-lightbulb mr-1"></i>
                                            للموظفين الشهريين: انقر على أي خلية فارغة لملء الدوام الكامل تلقائياً من {{ formatTimeTo12Hour(getDefaultWorkHours.start) }} إلى {{ formatTimeTo12Hour(getDefaultWorkHours.end) }}
                                        </p>
                                        <p v-if="Object.values(isDayLocked).some(locked => locked)" class="text-red-600">
                                            <i class="fas fa-lock mr-1"></i>
                                            الأيام المحظورة (🔒) لا يمكن التعديل عليها - يجب تحديدها في القيود أولاً
                                        </p>
                                </div>
                                </div>
                                <div class="flex items-center space-x-3 rtl:space-x-reverse">
                                <div>
                                    <button @click="printTimetable" class="bg-gray-700 text-white px-4 py-2 rounded-md hover:bg-gray-800 flex items-center mr-2">
                                        <i class="fas fa-print mr-2"></i> طباعة الجدول
                                    </button>
                                    <button @click="generateTimetable" :disabled="generateTimetableForm.processing" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 flex items-center">
                                        <i class="fas fa-cogs mr-2"></i>
                                        إنشاء / تحديث الجدول
                                    </button>
                                    </div>
                                </div>
                            </div>

                            <div class="printable-container">
                                <div class="printable-header">
                                    <div class="header-content">
                                        <!-- Logo removed for print to save space -->
                                        <div class="header-info">
                                            <h2 class="header-title">الجدول الأسبوعي</h2>
                                            <div class="person-info">
                                                <p class="person-name"><strong>الاسم:</strong> {{ selectedPerson.full_name || selectedPerson.name }}</p>
                                                <p class="person-type"><strong>النوع:</strong> {{ selectedPerson.type === 'Employee' ? 'موظف' : 'معلم' }}</p>
                                                <p v-if="selectedPerson.department" class="person-dept"><strong>القسم:</strong> {{ selectedPerson.department }}</p>
                                            </div>
                                        </div>
                                        <div class="print-date">
                                            <p><strong>تاريخ الطباعة:</strong> {{ new Date().toLocaleDateString('ar-LY') }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="border rounded-lg overflow-hidden timetable-wrapper">
                                <!-- Department Shift Hours Header -->
                                <div v-if="activeShiftSetting" class="shift-hours-header">
                                    <div class="shift-hours-content">
                                        <i class="fas fa-clock shift-hours-icon"></i>
                                        <div class="shift-hours-text">
                                            <span class="shift-hours-label">أوقات الدوام الرسمي للقسم:</span>
                                            <span class="shift-hours-value">
                                                {{ formatTimeTo12Hour(activeShiftSetting.start_time) }} - {{ formatTimeTo12Hour(activeShiftSetting.end_time) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <table class="timetable-print-table">
                                    <thead>
                                        <tr>
                                            <th class="time-col">الوقت</th>
                                            <th v-for="day in weekDays" :key="day.v" class="day-col"
                                                :class="{
                                                    'weekend-day': day.isWeekend,
                                                    'locked-day': isDayLocked[day.v]
                                                }">
                                                {{ day.n }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in printableTimetableData.data" :key="row.time.start">
                                            <td class="time-cell">{{ formatTimeTo12Hour(row.time.start) }} - {{ formatTimeTo12Hour(row.time.end) }}</td>
                                            <template v-for="day in weekDays" :key="day.v">
                                                <td v-if="row.days[day.v].render"
                                                    class="entry-cell px-2 py-2 whitespace-nowrap text-sm border text-center align-middle relative transition-colors"
                                                    :class="{
                                                        'bg-green-50 hover:bg-green-100 cursor-pointer': isEditMode && !row.days[day.v].entry && !isDayLocked[day.v],
                                                        'bg-gray-100 cursor-not-allowed opacity-50': isEditMode && isDayLocked[day.v],
                                                        'bg-blue-50 border-blue-300': dragOverCell && dragOverCell.day === day && dragOverCell.slot.start === row.time.start && !isDayLocked[day.v] && draggedEntry,
                                                        'bg-blue-50 border-blue-300': isEditMode && isMonthlyEmployee && !row.days[day.v].entry && !isDayLocked[day.v] && row.time.start === getDefaultWorkHours.start,
                                                        'empty-cell': !row.days[day.v].entry && !isEditMode,
                                                        'locked-cell': isDayLocked[day.v]
                                                    }"
                                                    :rowspan="row.days[day.v].rowspan"
                                                    @click="handleCellClick(day, row.time)"
                                                    @dragover.prevent="!isDayLocked[day.v] && handleDragOver($event, day, row.time)"
                                                    @dragleave="handleDragLeave($event)"
                                                    @drop.prevent="!isDayLocked[day.v] && handleDrop($event, day, row.time)">
                                                    <div v-if="row.days[day.v].entry" class="entry-content print-entry">
                                                        <p class="entry-title" v-if="row.days[day.v].entry.subject">{{ row.days[day.v].entry.subject.name }}</p>
                                                        <p class="entry-title" v-else>دوام عمل</p>
                                                        <p class="entry-time">
                                                            {{ formatTimeTo12Hour(row.days[day.v].entry.start_time) }} - {{ formatTimeTo12Hour(row.days[day.v].entry.end_time) }}
                                                        </p>
                                                        <p class="entry-details" v-if="row.days[day.v].entry.section">
                                                            {{ row.days[day.v].entry.section.name }} - {{ row.days[day.v].entry.section.grade.name }}
                                                        </p>
                                                        <p class="entry-details" v-if="row.days[day.v].entry.shift">
                                                            {{ row.days[day.v].entry.shift.name }}
                                                        </p>
                                                        <!-- Edit controls - hidden in print -->
                                                        <div v-if="isEditMode && !row.days[day.v].entry.is_break" class="no-print absolute inset-0 flex flex-col justify-between pointer-events-none">
                                                            <div class="absolute top-0 left-0 right-0 h-2 bg-blue-500 opacity-0 group-hover:opacity-50 cursor-ns-resize pointer-events-auto"
                                                                 @mousedown.stop="handleTimeDragStart($event, row.days[day.v].entry, 'start')"
                                                                 title="اسحب لتعديل وقت البدء"></div>
                                                            <div class="absolute bottom-0 left-0 right-0 h-2 bg-blue-500 opacity-0 group-hover:opacity-50 cursor-ns-resize pointer-events-auto"
                                                                 @mousedown.stop="handleTimeDragStart($event, row.days[day.v].entry, 'end')"
                                                                 title="اسحب لتعديل وقت النهاية"></div>
                                                        </div>
                                                        <div v-if="isEditMode" class="no-print absolute top-1 left-1 flex space-x-1 rtl:space-x-reverse opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                                            <button @click.stop="handleEntryClick(row.days[day.v].entry)"
                                                                    class="text-blue-600 hover:text-blue-800 bg-white rounded p-1 shadow"
                                                                    title="تعديل التفاصيل">
                                                                <i class="fas fa-edit text-xs"></i>
                                                            </button>
                                                            <button @click.stop="deleteEntry(row.days[day.v].entry)"
                                                                    class="text-red-600 hover:text-red-800 bg-white rounded p-1 shadow"
                                                                    title="حذف">
                                                                <i class="fas fa-times text-xs"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div v-else-if="isEditMode && !isDayLocked[day.v]" class="text-gray-400 text-xs">
                                                        <span v-if="isMonthlyEmployee && row.time.start === getDefaultWorkHours.start" class="text-blue-600 font-semibold">
                                                            <i class="fas fa-mouse-pointer mr-1"></i>
                                                            انقر لملء الدوام الكامل
                                                        </span>
                                                        <span v-else>
                                                            <i class="fas fa-plus-circle mr-1"></i>
                                                            انقر للإضافة
                                                        </span>
                                                    </div>
                                                    <div v-else-if="isEditMode && isDayLocked[day.v]" class="text-red-400 text-xs">
                                                        <i class="fas fa-lock mr-1"></i>
                                                        محظور
                                                    </div>
                                                </td>
                                            </template>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td class="time-end-marker" colspan="8">
                                                <div class="end-time-indicator">
                                                    <div class="end-time-main">
                                                        <i class="fas fa-stop-circle end-time-icon"></i>
                                                        <div class="end-time-content">
                                                            <span class="end-time-label">⏰ نهاية الدوام الرسمي للقسم</span>
                                                            <span class="end-time-separator">:</span>
                                                            <span class="end-time-value">{{ activeShiftSetting ? formatTimeTo12Hour(activeShiftSetting.end_time) : '5:00 مساءً' }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="end-time-note">
                                                        <i class="fas fa-info-circle"></i>
                                                        <span>وقت انتهاء الدوام الرسمي حسب إعدادات القسم ({{ activeShiftSetting?.department?.name || 'المؤسسة' }})</span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                                </div>
                                <div class="printable-footer">
                                    <div class="footer-content">
                                    <div class="signature-line">
                                            <p class="signature-label">توقيع مدير شؤون الموظفين</p>
                                        </div>
                                        <div class="signature-line">
                                            <p class="signature-label">توقيع الموظف/المعلم</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="watermark">
                                    <img src="../../../../../public/images/logo-school-one.png" alt="Watermark Logo">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly View Content -->
                    <div v-show="activePersonTab === 'monthly'">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-xl font-bold text-gray-800">العرض الشهري للدوام</h3>
                                <div class="flex items-center space-x-3 rtl:space-x-reverse">
                                    <input type="month" v-model="selectedMonth"
                                           class="px-4 py-2 border border-gray-300 rounded-lg text-gray-800 focus:ring-indigo-500 focus:border-indigo-500">

                                    <!-- Draggable Overtime Badge -->
                                    <div class="flex items-center space-x-2 rtl:space-x-reverse">
                                        <div draggable="true"
                                             @dragstart="handleOvertimeDragStart"
                                             @dragend="handleOvertimeDragEnd"
                                             class="px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg cursor-move hover:from-green-600 hover:to-green-700 transition-all shadow-lg flex items-center space-x-2 rtl:space-x-reverse"
                                             :class="{ 'opacity-50': draggedOvertimeItem === 'overtime' }"
                                             title="اسحب هذا العنصر وأفلته على اليوم المطلوب لإضافة وقت إضافي">
                                            <i class="fas fa-grip-vertical text-green-200"></i>
                                            <i class="fas fa-clock"></i>
                                            <span class="font-semibold">وقت إضافي</span>
                                            <i class="fas fa-arrow-left text-green-200 text-xs"></i>
                                        </div>

                                        <button @click="showOvertimeModal = true"
                                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                            <i class="fas fa-calendar-plus mr-2"></i>إضافة يدوي
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Instructions -->
                            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-sm text-green-800 flex items-center">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <strong>كيفية الاستخدام:</strong> اسحب عنصر "وقت إضافي" من الأعلى وأفلته على اليوم المطلوب في التقويم لإضافة وقت إضافي مباشرة
                                </p>
                            </div>

                            <!-- Monthly Calendar Grid -->
                            <div class="grid grid-cols-7 gap-2 mb-4">
                                <div v-for="day in weekDays" :key="day.v"
                                     class="text-center font-semibold py-2"
                                     :class="day.isWeekend ? 'text-orange-600' : 'text-gray-700'">
                                    {{ day.n }}
                                </div>
                            </div>

                            <div class="grid grid-cols-7 gap-2">
                                <div v-for="(date, index) in getMonthlyCalendarDates(selectedMonth)"
                                     :key="index"
                                     @dragover.prevent="handleDateDragOver($event, index, date)"
                                     @dragleave.prevent="handleDateDragLeave($event)"
                                     @drop.prevent="handleDateDrop($event, date)"
                                     class="border border-gray-200 rounded-lg p-3 min-h-[120px] transition-all relative"
                                     :class="{
                                         'bg-gray-50': !date.isCurrentMonth,
                                         'bg-white': date.isCurrentMonth,
                                         'border-orange-300 bg-orange-50': date.isWeekend && date.isCurrentMonth,
                                         'border-green-400 bg-green-100 border-3 shadow-lg': dragOverDateIndex === index && draggedOvertimeItem === 'overtime' && date.isCurrentMonth,
                                         'cursor-copy': draggedOvertimeItem === 'overtime' && date.isCurrentMonth && date.isWorkDay,
                                         'cursor-not-allowed opacity-50': draggedOvertimeItem === 'overtime' && date.isCurrentMonth && !date.isWorkDay
                                     }">

                                    <!-- Drop Zone Indicator -->
                                    <div v-if="dragOverDateIndex === index && draggedOvertimeItem === 'overtime' && date.isCurrentMonth"
                                         class="absolute inset-0 flex items-center justify-center bg-green-200 bg-opacity-50 rounded-lg z-10 pointer-events-none">
                                        <div class="bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg flex items-center space-x-2 rtl:space-x-reverse">
                                            <i class="fas fa-plus-circle"></i>
                                            <span class="font-bold">إضافة وقت إضافي هنا</span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="text-sm font-semibold"
                                              :class="date.isCurrentMonth ? 'text-gray-800' : 'text-gray-400'">
                                            {{ date.day }}
                                        </span>
                                        <span v-if="date.isToday" class="text-xs bg-indigo-600 text-white px-2 py-0.5 rounded">
                                            اليوم
                                        </span>
                                    </div>

                                    <!-- Leave Entry for this date (show first if exists) -->
                                    <div v-if="date.leaveEntry && date.isCurrentMonth" class="text-xs">
                                        <div v-if="date.isUnpaidLeave" class="bg-gradient-to-r from-red-100 to-orange-100 border-2 border-red-300 text-red-800 p-2 rounded-lg shadow-sm mb-1">
                                            <div class="flex items-center mb-1">
                                                <i class="fas fa-calendar-times text-red-600 mr-1.5"></i>
                                                <strong class="text-red-900">إجازة بدون رصيد</strong>
                                            </div>
                                            <div class="text-xs text-red-700 mb-0.5" v-if="date.leaveEntry.leave_type">
                                                <span class="font-semibold">{{ date.leaveEntry.leave_type.name }}</span>
                                                <span class="mr-1 text-orange-600">
                                                    <i class="fas fa-times-circle"></i> غير مدفوعة
                                                </span>
                                            </div>
                                            <div class="text-xs text-red-600" v-if="date.leaveEntry.reason">
                                                {{ date.leaveEntry.reason.substring(0, 30) }}{{ date.leaveEntry.reason.length > 30 ? '...' : '' }}
                                            </div>
                                        </div>
                                        <div v-else class="bg-gradient-to-r from-purple-100 to-pink-100 border-2 border-purple-300 text-purple-800 p-2 rounded-lg shadow-sm mb-1">
                                            <div class="flex items-center mb-1">
                                                <i class="fas fa-calendar-times text-purple-600 mr-1.5"></i>
                                                <strong class="text-purple-900">إجازة</strong>
                                            </div>
                                            <div class="text-xs text-purple-700 mb-0.5" v-if="date.leaveEntry.leave_type">
                                                <span class="font-semibold">{{ date.leaveEntry.leave_type.name }}</span>
                                                <span v-if="date.leaveEntry.leave_type.is_paid" class="mr-1 text-green-600">
                                                    <i class="fas fa-check-circle"></i> مدفوعة
                                                </span>
                                                <span v-else class="mr-1 text-orange-600">
                                                    <i class="fas fa-times-circle"></i> غير مدفوعة
                                                </span>
                                            </div>
                                            <div class="text-xs text-purple-600" v-if="date.leaveEntry.reason">
                                                {{ date.leaveEntry.reason.substring(0, 30) }}{{ date.leaveEntry.reason.length > 30 ? '...' : '' }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Work Day Status (only show if no unpaid leave) -->
                                    <div v-if="date.isCurrentMonth && !date.isUnpaidLeave" class="text-xs">
                                        <!-- If it's a work day and has timetable entry -->
                                        <div v-if="date.isWorkDay && date.timetableEntry" class="bg-indigo-100 text-indigo-800 p-1 rounded mb-1">
                                            <i class="fas fa-clock mr-1"></i>
                                            <strong>دوام:</strong> {{ formatTimeTo12Hour(date.timetableEntry.start_time) }} -
                                            {{ formatTimeTo12Hour(date.timetableEntry.end_time) }}
                                        </div>

                                        <!-- If it's a work day but no timetable entry yet -->
                                        <div v-else-if="date.isWorkDay && !date.timetableEntry" class="bg-yellow-50 text-yellow-700 p-1 rounded mb-1 text-xs">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            يوم عمل (لم يتم تحديد الجدول بعد)
                                        </div>

                                        <!-- If it's not a work day (holiday) -->
                                        <div v-else-if="!date.isWorkDay" class="bg-gray-100 text-gray-600 p-1 rounded mb-1 text-xs">
                                            <i class="fas fa-moon mr-1"></i>
                                            عطلة
                                        </div>
                                    </div>

                                    <!-- Overtime Entry for this date -->
                                    <div v-if="date.overtimeEntry && date.isCurrentMonth" class="text-xs mt-1">
                                        <div class="bg-gradient-to-r from-green-100 to-emerald-100 border-2 border-green-300 text-green-800 p-2 rounded-lg shadow-sm relative group">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="flex items-center mb-1">
                                                        <i class="fas fa-clock text-green-600 mr-1.5"></i>
                                                        <strong class="text-green-900">وقت إضافي</strong>
                                                    </div>
                                                    <div class="text-xs text-green-700 mb-0.5">
                                                        {{ formatTimeTo12Hour(date.overtimeEntry.start_time) }} - {{ formatTimeTo12Hour(date.overtimeEntry.end_time) }}
                                                    </div>
                                                    <div class="text-xs font-semibold text-green-800">
                                                        <i class="fas fa-hourglass-half mr-1"></i>
                                                        {{ (date.overtimeEntry.minutes / 60).toFixed(1) }} ساعة
                                                        <span v-if="date.overtimeEntry.minutes % 60 > 0">
                                                            ({{ date.overtimeEntry.minutes % 60 }} دقيقة)
                                                        </span>
                                                    </div>
                                                    <div v-if="date.overtimeEntry.notes" class="text-xs text-green-600 mt-1 italic">
                                                        <i class="fas fa-sticky-note mr-1"></i>
                                                        {{ date.overtimeEntry.notes }}
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-1 rtl:space-x-reverse opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button
                                                        @click.stop="editOvertime(date.overtimeEntry, date)"
                                                        class="p-1.5 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors"
                                                        title="تعديل">
                                                        <i class="fas fa-edit text-xs"></i>
                                                    </button>
                                                    <button
                                                        @click.stop="deleteOvertime(date.overtimeEntry.id)"
                                                        class="p-1.5 bg-red-500 text-white rounded hover:bg-red-600 transition-colors"
                                                        title="حذف">
                                                        <i class="fas fa-trash text-xs"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Total Work Hours Display -->
                                    <div v-if="date.isCurrentMonth && (date.timetableEntry || date.overtimeEntry)" class="text-xs mt-1 pt-1 border-t border-gray-200">
                                        <div class="bg-purple-50 text-purple-800 p-1 rounded font-semibold">
                                            <i class="fas fa-calculator mr-1"></i>
                                            إجمالي: {{ getTotalWorkHoursForDate(date) }} ساعة
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary -->
                            <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg">
                                <div>
                                    <div class="text-sm text-gray-600">إجمالي أيام العمل</div>
                                    <div class="text-2xl font-bold text-gray-800">{{ getMonthlySummary(selectedMonth).workDays }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">إجمالي الساعات</div>
                                    <div class="text-2xl font-bold text-indigo-600">{{ getMonthlySummary(selectedMonth).totalHours.toFixed(1) }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">الوقت الإضافي</div>
                                    <div class="text-2xl font-bold text-green-600">{{ getMonthlySummary(selectedMonth).overtimeHours.toFixed(1) }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-600">الإجمالي</div>
                                    <div class="text-2xl font-bold text-blue-600">
                                        {{ (getMonthlySummary(selectedMonth).totalHours + getMonthlySummary(selectedMonth).overtimeHours).toFixed(1) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- Overtime Modal -->
        <div v-if="showOvertimeModal"
             class="fixed inset-0 bg-opacity-30 backdrop-blur-sm flex items-center justify-center z-50 transition-all p-4"
             @click.self="showOvertimeModal = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl mx-auto overflow-hidden animate-in fade-in zoom-in duration-200"
                 @click.stop dir="rtl">
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-5 flex justify-between items-center">
                    <div class="flex items-center space-x-3 rtl:space-x-reverse">
                        <div class="bg-white bg-opacity-20 rounded-full p-2.5">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold">{{ editingOvertimeId ? 'تعديل وقت إضافي' : 'إضافة وقت إضافي' }}</h3>
                            <p class="text-green-100 text-xs mt-0.5">{{ editingOvertimeId ? 'قم بتعديل بيانات الوقت الإضافي' : 'حدد المدة أو الوقت المحدد للوقت الإضافي' }}</p>
                        </div>
                    </div>
                    <button @click="showOvertimeModal = false; selectedDatesForOvertime = []; overtimeForm.reset(); overtimeHoursInput = 0; overtimeMinutesInput = 0"
                            class="text-white hover:bg-white hover:bg-opacity-20 rounded-full p-2 transition-colors">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <div class="p-5">
                    <!-- Info Cards Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
                        <!-- Selected Date Info -->
                        <div v-if="selectedDatesForOvertime.length > 0"
                             class="bg-gradient-to-br from-blue-50 to-blue-100 border-2 border-blue-200 rounded-xl p-3 shadow-sm">
                            <div class="flex items-center space-x-2 rtl:space-x-reverse">
                                <div class="bg-blue-500 rounded-lg p-1.5">
                                    <i class="fas fa-calendar-check text-white text-base"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs text-blue-600 font-semibold mb-0.5 uppercase tracking-wide">
                                        التاريخ المحدد
                                    </p>
                                    <p class="text-sm text-blue-900 font-bold">
                                        {{ formatDateForDisplay(selectedDatesForOvertime[0]) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Regular Work Hours Display -->
                        <div v-if="selectedDatesForOvertime.length > 0 && selectedDatesForOvertime[0].timetableEntry"
                             class="bg-gradient-to-br from-indigo-50 to-indigo-100 border-2 border-indigo-200 rounded-xl p-3 shadow-sm">
                            <div class="flex items-center space-x-2 rtl:space-x-reverse">
                                <div class="bg-indigo-500 rounded-lg p-1.5">
                                    <i class="fas fa-clock text-white text-base"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs text-indigo-600 font-semibold mb-0.5 uppercase tracking-wide">
                                        ساعات الدوام العادي
                                    </p>
                                    <p class="text-sm text-indigo-900 font-bold">
                                        {{ formatTimeTo12Hour(selectedDatesForOvertime[0].timetableEntry.start_time) }} -
                                        {{ formatTimeTo12Hour(selectedDatesForOvertime[0].timetableEntry.end_time) }}
                                    </p>
                                    <p class="text-xs text-indigo-700 mt-0.5">
                                        <i class="fas fa-hourglass-half mr-1"></i>
                                        {{ ((selectedDatesForOvertime[0].timetableEntry.work_minutes || 0) / 60).toFixed(1) }} ساعة
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                <form @submit.prevent="submitOvertime">
                    <div class="space-y-6">
                        <!-- Date Input (if not from drag & drop) -->
                        <div v-if="selectedDatesForOvertime.length === 0" class="bg-gray-50 rounded-xl p-4 border-2 border-gray-200">
                            <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-calendar-alt text-indigo-600 mr-2"></i>
                                التاريخ
                            </label>
                            <input type="date" v-model="overtimeForm.date" required
                                   class="w-full rounded-lg border-gray-300 shadow-sm text-gray-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>

                        <!-- Two Column Layout for Input Methods -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Method 1: Time Range -->
                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border-2 border-purple-200 rounded-xl p-5 shadow-sm">
                                <div class="flex items-center mb-4">
                                    <div class="bg-purple-500 rounded-lg p-2 mr-3">
                                        <i class="fas fa-clock text-white"></i>
                                    </div>
                                    <h4 class="text-lg font-bold text-purple-900">تحديد الوقت</h4>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                            <i class="fas fa-play-circle text-green-600 mr-2 text-xs"></i>
                                            وقت البدء
                                        </label>
                                        <input type="time" v-model="overtimeForm.start_time"
                                               class="w-full rounded-lg border-gray-300 shadow-sm text-gray-800 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-lg py-2">
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                            <i class="fas fa-stop-circle text-red-600 mr-2 text-xs"></i>
                                            وقت النهاية
                                        </label>
                                        <input type="time" v-model="overtimeForm.end_time"
                                               class="w-full rounded-lg border-gray-300 shadow-sm text-gray-800 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-lg py-2">
                                    </div>
                                </div>
                            </div>

                            <!-- Method 2: Direct Duration -->
                            <div class="bg-gradient-to-br from-yellow-50 to-orange-50 border-2 border-yellow-300 rounded-xl p-5 shadow-sm">
                                <div class="flex items-center mb-4">
                                    <div class="bg-yellow-500 rounded-lg p-2 mr-3">
                                        <i class="fas fa-hourglass-half text-white"></i>
                                    </div>
                                    <h4 class="text-lg font-bold text-yellow-900">تحديد المدة مباشرة</h4>
                                </div>
                                <div class="space-y-4">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                                <i class="fas fa-clock text-blue-600 mr-2 text-xs"></i>
                                                الساعات
                                            </label>
                                            <input type="number"
                                                   v-model.number="overtimeHoursInput"
                                                   @input="updateOvertimeFromHours"
                                                   min="0"
                                                   max="24"
                                                   step="0.5"
                                                   class="w-full rounded-lg border-gray-300 shadow-sm text-gray-800 text-lg py-2 text-center font-bold focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                                   placeholder="0">
                                        </div>
                                        <div>
                                            <label class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                                <i class="fas fa-stopwatch text-orange-600 mr-2 text-xs"></i>
                                                الدقائق
                                            </label>
                                            <input type="number"
                                                   v-model.number="overtimeMinutesInput"
                                                   @input="updateOvertimeFromMinutes"
                                                   min="0"
                                                   max="59"
                                                   class="w-full rounded-lg border-gray-300 shadow-sm text-gray-800 text-lg py-2 text-center font-bold focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                                   placeholder="0">
                                        </div>
                                    </div>
                                    <div class="bg-yellow-100 border border-yellow-300 rounded-lg p-3">
                                        <p class="text-xs text-yellow-800 flex items-start">
                                            <i class="fas fa-info-circle text-yellow-600 mr-2 mt-0.5"></i>
                                            <span>سيتم حساب وقت البدء تلقائياً من نهاية الدوام العادي ({{ selectedDatesForOvertime.length > 0 && selectedDatesForOvertime[0].timetableEntry ? formatTimeTo12Hour(selectedDatesForOvertime[0].timetableEntry.end_time) : 'نهاية الدوام' }})</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Hours Preview -->
                        <div v-if="((overtimeForm.start_time && overtimeForm.end_time) || (overtimeHoursInput > 0 || overtimeMinutesInput > 0)) && selectedDatesForOvertime.length > 0"
                             class="bg-gradient-to-r from-purple-100 to-indigo-100 border-2 border-purple-300 rounded-xl p-4 shadow-lg">
                            <div class="flex items-center mb-3">
                                <div class="bg-purple-600 rounded-lg p-1.5 mr-2">
                                    <i class="fas fa-calculator text-white"></i>
                                </div>
                                <h4 class="text-base font-bold text-purple-900">معاينة الإجمالي</h4>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="bg-white rounded-lg p-3 border-2 border-purple-200">
                                    <div class="flex items-center mb-1.5">
                                        <i class="fas fa-plus-circle text-green-600 mr-1.5 text-sm"></i>
                                        <span class="text-xs text-gray-600 font-semibold">الوقت الإضافي</span>
                                    </div>
                                    <p class="text-xl font-bold text-green-700">
                                        {{ getOvertimeHoursDisplay() }} <span class="text-xs">ساعة</span>
                                        <span v-if="getOvertimeMinutesDisplay() > 0" class="text-xs text-gray-600 block mt-0.5">
                                            ({{ getOvertimeMinutesDisplay() }} دقيقة)
                                        </span>
                                    </p>
                                </div>
                                <div v-if="selectedDatesForOvertime[0].timetableEntry" class="bg-white rounded-lg p-3 border-2 border-indigo-200">
                                    <div class="flex items-center mb-1.5">
                                        <i class="fas fa-clock text-indigo-600 mr-1.5 text-sm"></i>
                                        <span class="text-xs text-gray-600 font-semibold">الدوام العادي</span>
                                    </div>
                                    <p class="text-xl font-bold text-indigo-700">
                                        {{ ((selectedDatesForOvertime[0].timetableEntry.work_minutes || 0) / 60).toFixed(1) }} <span class="text-xs">ساعة</span>
                                    </p>
                                </div>
                                <div class="bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg p-3 text-white">
                                    <div class="flex items-center mb-1.5">
                                        <i class="fas fa-equals text-white mr-1.5 text-sm"></i>
                                        <span class="text-xs font-semibold opacity-90">الإجمالي</span>
                                    </div>
                                    <p class="text-2xl font-bold">
                                        {{ getTotalWorkHoursForDate(selectedDatesForOvertime[0]) }} <span class="text-sm">ساعة</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Notes Section -->
                        <div class="bg-gray-50 rounded-xl p-3 border-2 border-gray-200">
                            <label class="text-xs font-semibold text-gray-700 mb-1.5 flex items-center">
                                <i class="fas fa-sticky-note text-gray-600 mr-1.5"></i>
                                الملاحظات (اختياري)
                            </label>
                            <textarea v-model="overtimeForm.notes" rows="2"
                                      class="w-full rounded-lg border-gray-300 shadow-sm text-gray-800 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                      placeholder="أضف أي ملاحظات إضافية..."></textarea>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex justify-end space-x-3 rtl:space-x-reverse mt-5 pt-4 border-t border-gray-200">
                        <button type="button"
                                @click="showOvertimeModal = false; selectedDatesForOvertime = []; overtimeForm.reset(); overtimeHoursInput = 0; overtimeMinutesInput = 0"
                                class="px-5 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-all font-semibold flex items-center text-sm">
                            <i class="fas fa-times mr-1.5"></i>
                            إلغاء
                        </button>
                        <button type="submit"
                                :disabled="overtimeForm.processing || (!overtimeForm.start_time && !overtimeForm.end_time && overtimeHoursInput === 0 && overtimeMinutesInput === 0)"
                                class="px-5 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl hover:from-green-600 hover:to-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all font-semibold shadow-lg flex items-center text-sm">
                            <i class="fas fa-save mr-1.5"></i>
                            حفظ الوقت الإضافي
                        </button>
                    </div>
                </form>
                </div>
            </div>
        </div>

        <!-- Custom Hours Modal (if needed separately) -->
        <!-- This is now integrated in the main view, but keeping for future use -->

        <!-- Entry Modal -->
        <div v-if="showEntryModal" class="fixed inset-0 bg-opacity-30 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="showEntryModal = false">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto" @click.stop dir="rtl">
                <!-- Header -->
                <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white p-5 flex justify-between items-center rounded-t-xl">
                    <div class="flex items-center space-x-3 rtl:space-x-reverse">
                        <div class="bg-white bg-opacity-20 rounded-full p-2.5">
                            <i class="fas fa-calendar-alt text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold">
                                {{ editingEntry ?
                                    (editingEntry.entry_type === 'break' ? 'تعديل فترة الراحة' :
                                     editingEntry.entry_type === 'breakfast' ? 'تعديل استراحة الفطور' :
                                     editingEntry.entry_type === 'meeting' ? 'تعديل الاجتماع' :
                                     editingEntry.entry_type === 'workshop' ? 'تعديل ورشة العمل' :
                                     editingEntry.entry_type === 'training' ? 'تعديل التدريب' :
                                     editingEntry.entry_type === 'other' ? 'تعديل النشاط' :
                                     'تعديل إدخال الجدول') :
                                    (entryForm.entry_type === 'break' ? 'إضافة فترة راحة' :
                                     entryForm.entry_type === 'breakfast' ? 'إضافة استراحة فطور' :
                                     entryForm.entry_type === 'meeting' ? 'إضافة اجتماع' :
                                     entryForm.entry_type === 'workshop' ? 'إضافة ورشة عمل' :
                                     entryForm.entry_type === 'training' ? 'إضافة تدريب' :
                                     entryForm.entry_type === 'other' ? 'إضافة نشاط' :
                                     'إضافة إدخال جديد') }}
                            </h3>
                            <p class="text-indigo-100 text-xs mt-0.5">
                                {{ selectedPerson?.name }} - {{ selectedPerson?.type_label }}
                            </p>
                        </div>
                    </div>
                    <button @click="showEntryModal = false; editingEntry = null; entryForm.reset()"
                            class="text-white hover:bg-white hover:bg-opacity-20 rounded-full p-2 transition-colors">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <form @submit.prevent="submitEntryForm" class="p-5">
                    <!-- Info Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                        <!-- Employment Type Info Card -->
                        <div v-if="props.personnelConstraints?.employment_type" class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-xl">
                            <div class="flex items-center space-x-2 rtl:space-x-reverse">
                                <i class="fas fa-briefcase text-blue-600"></i>
                                <div>
                                    <p class="text-xs text-blue-600 font-semibold mb-0.5">نوع التوظيف (ثابت)</p>
                                    <p class="text-sm text-blue-900 font-bold">
                                        {{ props.personnelConstraints.employment_type === 'monthly_full' ? 'شهري كامل' :
                                           props.personnelConstraints.employment_type === 'monthly_partial' ? 'شهري جزئي' :
                                           'بالساعات' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Department Shift Info Card -->
                        <div v-if="activeShiftSetting" class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl">
                            <div class="flex items-center space-x-2 rtl:space-x-reverse">
                                <i class="fas fa-clock text-green-600"></i>
                                <div>
                                    <p class="text-xs text-green-600 font-semibold mb-0.5">أوقات القسم</p>
                                    <p class="text-sm text-green-900 font-bold">
                                        {{ formatTimeTo12Hour(activeShiftSetting.start_time) }} - {{ formatTimeTo12Hour(activeShiftSetting.end_time) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Form -->
                    <div class="space-y-4">
                        <!-- Day Selection -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-calendar-day text-indigo-600 mr-2"></i>
                                اليوم
                            </label>
                            <select v-model="entryForm.day_of_week"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    required>
                                <option :value="null">-- اختر اليوم --</option>
                                <option v-for="day in weekDays" :key="day.v" :value="day.v"
                                        :disabled="isDayRestricted(day.v)">
                                    {{ day.n }}
                                    <span v-if="isDayRestricted(day.v)">(محظور)</span>
                                </option>
                            </select>
                        </div>

                        <!-- Break Period Time Range -->
                        <div class="bg-gradient-to-r from-orange-50 to-amber-50 border-2 border-orange-200 rounded-xl p-4">
                            <div class="flex items-center mb-3">
                                <i class="fas fa-coffee text-orange-600 mr-2"></i>
                                <h4 class="text-base font-bold text-orange-900">فترة الراحة</h4>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Start Time -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                                        <i class="fas fa-clock text-green-600 mr-2"></i>
                                        وقت البداية
                                    </label>
                                    <div class="flex items-center space-x-2 rtl:space-x-reverse">
                                        <input type="time"
                                               v-model="entryForm.start_time"
                                               class="flex-1 rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                               required>
                                        <button v-if="activeShiftSetting"
                                                type="button"
                                                @click="entryForm.start_time = activeShiftSetting.start_time.substring(0, 5)"
                                                class="px-3 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-xs font-semibold transition-colors"
                                                title="استخدام وقت بداية القسم">
                                            <i class="fas fa-magic mr-1"></i>افتراضي
                                        </button>
                                    </div>
                                </div>

                                <!-- End Time -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                                        <i class="fas fa-clock text-red-600 mr-2"></i>
                                        وقت نهاية الراحة
                                    </label>
                                    <div class="flex items-center space-x-2 rtl:space-x-reverse">
                                        <input type="time"
                                               v-model="entryForm.end_time"
                                               class="flex-1 rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                               required>
                                        <button v-if="activeShiftSetting"
                                                type="button"
                                                @click="entryForm.end_time = activeShiftSetting.end_time.substring(0, 5)"
                                                class="px-3 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-xs font-semibold transition-colors"
                                                title="استخدام وقت نهاية القسم">
                                            <i class="fas fa-magic mr-1"></i>افتراضي
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Info Note -->
                            <div v-if="activeShiftSetting && entryForm.entry_type !== 'work'" class="mt-3 p-3 rounded-lg" :class="[
                                entryForm.entry_type === 'break' ? 'bg-orange-100 border border-orange-300' :
                                entryForm.entry_type === 'breakfast' ? 'bg-yellow-100 border border-yellow-300' :
                                entryForm.entry_type === 'meeting' ? 'bg-blue-100 border border-blue-300' :
                                entryForm.entry_type === 'workshop' ? 'bg-purple-100 border border-purple-300' :
                                entryForm.entry_type === 'training' ? 'bg-green-100 border border-green-300' :
                                'bg-gray-100 border border-gray-300'
                            ]">
                                <p class="text-xs flex items-start" :class="[
                                    entryForm.entry_type === 'break' ? 'text-orange-800' :
                                    entryForm.entry_type === 'breakfast' ? 'text-yellow-800' :
                                    entryForm.entry_type === 'meeting' ? 'text-blue-800' :
                                    entryForm.entry_type === 'workshop' ? 'text-purple-800' :
                                    entryForm.entry_type === 'training' ? 'text-green-800' :
                                    'text-gray-800'
                                ]">
                                    <i :class="[
                                        'mr-2 mt-0.5',
                                        entryForm.entry_type === 'break' ? 'fas fa-info-circle text-orange-600' :
                                        entryForm.entry_type === 'breakfast' ? 'fas fa-info-circle text-yellow-600' :
                                        entryForm.entry_type === 'meeting' ? 'fas fa-info-circle text-blue-600' :
                                        entryForm.entry_type === 'workshop' ? 'fas fa-info-circle text-purple-600' :
                                        entryForm.entry_type === 'training' ? 'fas fa-info-circle text-green-600' :
                                        'fas fa-info-circle text-gray-600'
                                    ]"></i>
                                    <span>
                                        <strong>ملاحظة:</strong> سيتم إضافة {{ entryForm.entry_type === 'break' ? 'فترة الراحة' :
                                                                              entryForm.entry_type === 'breakfast' ? 'استراحة الفطور' :
                                                                              entryForm.entry_type === 'meeting' ? 'الاجتماع' :
                                                                              entryForm.entry_type === 'workshop' ? 'ورشة العمل' :
                                                                              entryForm.entry_type === 'training' ? 'التدريب' :
                                                                              'النشاط' }} في الوقت المحدد أعلاه.
                                        دوام العمل الأساسي ({{ formatTimeTo12Hour(activeShiftSetting.start_time) }} - {{ formatTimeTo12Hour(activeShiftSetting.end_time) }})
                                        سيظل موجوداً في الجدول.
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Hidden fields (set automatically) -->
                        <input type="hidden" v-model="entryForm.work_type">
                        <input type="hidden" v-model="entryForm.is_break">
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex justify-end mt-6 pt-5 border-t border-gray-200 gap-3">
                        <button type="button"
                                @click="showEntryModal = false; editingEntry = null; entryForm.reset()"
                                class="px-5 py-2.5 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors font-semibold flex items-center">
                            <i class="fas fa-times mr-2"></i>
                            إلغاء
                        </button>
                        <button type="submit"
                                :disabled="entryForm.processing || !entryForm.start_time || !entryForm.end_time || entryForm.day_of_week === null"
                                class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-lg hover:from-indigo-700 hover:to-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed transition-all font-semibold shadow-lg flex items-center">
                            <i class="fas fa-save mr-2"></i>
                            {{ editingEntry ? 'تحديث' : 'إضافة' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </HrLayout>

    <!-- Dialog تأكيد حفظ القيود -->
    <Dialog v-model:open="showConfirmDialog">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto" dir="rtl">
            <DialogHeader>
                <DialogTitle class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-check-circle text-indigo-600"></i>
                    {{ confirmDialogData.title }}
                </DialogTitle>
                <DialogDescription class="text-gray-600 mt-2">
                    يرجى مراجعة المعلومات التالية قبل المتابعة
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- معلومات أساسية -->
                <div class="bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                        <i class="fas fa-info-circle text-indigo-600"></i>
                        معلومات القيود
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        <div class="flex items-start gap-2">
                            <span class="font-medium text-gray-700 min-w-[120px]">نوع التوظيف:</span>
                            <span class="text-gray-800 font-semibold">{{ confirmDialogData.employmentType }}</span>
                        </div>
                        <div class="flex items-start gap-2" v-if="confirmDialogData.startTime && confirmDialogData.endTime">
                            <span class="font-medium text-gray-700 min-w-[120px]">أوقات العمل:</span>
                            <span class="text-gray-800 font-semibold">{{ confirmDialogData.startTime }} - {{ confirmDialogData.endTime }}</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="font-medium text-gray-700 min-w-[120px]">إجمالي الساعات/أسبوع:</span>
                            <span class="text-gray-800 font-semibold">{{ confirmDialogData.totalHoursPerWeek.toFixed(1) }} ساعة</span>
                        </div>
                    </div>
                </div>

                <!-- أيام العمل -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-2 flex items-center gap-2">
                        <i class="fas fa-calendar-check text-green-600"></i>
                        أيام العمل
                    </h3>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span v-for="day in confirmDialogData.workDays" :key="day"
                              class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                            {{ day }}
                        </span>
                    </div>
                </div>

                <!-- ملاحظات -->
                <div v-if="confirmDialogData.notes.length > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-2 flex items-center gap-2">
                        <i class="fas fa-sticky-note text-blue-600"></i>
                        ملاحظات
                    </h3>
                    <ul class="list-disc list-inside space-y-1 text-sm text-gray-700 mt-2">
                        <li v-for="(note, index) in confirmDialogData.notes" :key="index">{{ note }}</li>
                    </ul>
                </div>

                <!-- تحذيرات -->
                <div v-if="confirmDialogData.warnings.length > 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-2 flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                        تحذيرات
                    </h3>
                    <ul class="list-disc list-inside space-y-1 text-sm text-yellow-800 mt-2">
                        <li v-for="(warning, index) in confirmDialogData.warnings" :key="index">{{ warning }}</li>
                    </ul>
                </div>

                <!-- رسالة تأكيد -->
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                    <p class="text-sm text-indigo-800 flex items-center gap-2">
                        <i class="fas fa-lightbulb text-indigo-600"></i>
                        <strong>ملاحظة:</strong> سيتم حفظ القيود وإنشاء الجدول تلقائياً بعد الضغط على "تأكيد"
                    </p>
                </div>
            </div>

            <DialogFooter class="gap-2" dir="rtl">
                <DialogClose as-child>
                    <button type="button"
                            @click="showConfirmDialog = false"
                            class="px-6 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition-colors font-medium">
                        <i class="fas fa-times ml-2"></i>
                        إلغاء
                    </button>
                </DialogClose>
                <button type="button"
                        @click="handleConfirmConstraints"
                        :disabled="individualConstraintsForm.processing"
                        class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-check ml-2"></i>
                    تأكيد والمتابعة
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style>
/* --- PRINT STYLES - Optimized for single page PDF --- */
@media print {
    @page {
        size: A4 landscape;
        margin: 0.4cm;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    body > * {
        visibility: hidden;
    }

    #printable-area,
    #printable-area * {
        visibility: visible;
    }

    #printable-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        font-size: 6pt;
        font-family: 'Arial', 'Helvetica', 'Tahoma', sans-serif;
    }

    .no-print {
        display: none !important;
    }

    .printable-container {
        width: 100%;
        padding: 0.15cm;
        border: 1px solid #1a202c;
        page-break-inside: avoid;
        page-break-after: avoid;
        background: white;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .printable-header {
        display: flex !important;
        margin-bottom: 0.08cm;
        padding-bottom: 0.05cm;
        border-bottom: 1px solid #1a202c;
        page-break-inside: avoid;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        gap: 0.15cm;
    }

    .logo {
        max-width: 20px;
        height: auto;
        flex-shrink: 0;
    }

    .header-info {
        flex: 1;
        text-align: center;
    }

    .header-title {
        font-size: 8pt;
        font-weight: bold;
        margin-bottom: 0.05cm;
        color: #1a202c;
        line-height: 1.2;
    }

    .person-info {
        font-size: 6pt;
        line-height: 1.2;
        text-align: center;
    }

    .person-info p {
        margin: 0.02cm 0;
        display: block;
        font-weight: 500;
        color: #1a202c;
    }

    .person-info p strong {
        font-weight: bold;
        color: #374151;
        margin-left: 0.08cm;
    }

    .person-name, .person-type, .person-dept {
        display: block;
    }

    .print-date {
        font-size: 5.5pt;
        text-align: left;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .shift-hours-header {
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        padding: 0.06cm 0.1cm;
        border-bottom: 1px solid #1e3a8a;
        text-align: center;
        box-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
        page-break-inside: avoid;
    }

    .shift-hours-content {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.15cm;
    }

    .shift-hours-icon {
        color: #fbbf24;
        font-size: 7pt;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    }

    .shift-hours-text {
        display: flex;
        align-items: center;
        gap: 0.1cm;
        flex-wrap: wrap;
        justify-content: center;
    }

    .shift-hours-label {
        color: #ffffff;
        font-weight: 700;
        font-size: 6.5pt;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        letter-spacing: 0.2px;
    }

    .shift-hours-value {
        color: #fbbf24;
        font-weight: 900;
        font-size: 7pt;
        background: rgba(255, 255, 255, 0.15);
        padding: 0.05cm 0.15cm;
        border-radius: 2px;
        border: 1px solid rgba(251, 191, 36, 0.4);
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        letter-spacing: 0.3px;
    }

    .timetable-wrapper {
        margin: 0.05cm 0;
        border: 1px solid #1a202c;
        page-break-inside: avoid;
        max-height: none;
        overflow: visible;
    }

    .timetable-print-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 5.5pt;
        table-layout: fixed;
        border: 1px solid #1a202c;
        background: white;
        page-break-inside: avoid;
    }

    .timetable-print-table th {
        background-color: #1a202c !important;
        color: white !important;
        border: 1px solid #374151;
        padding: 0.1cm 0.08cm;
        font-weight: bold;
        text-align: center;
        font-size: 6pt;
        vertical-align: middle;
        letter-spacing: 0.3px;
    }

    .timetable-print-table .time-col {
        width: 7%;
        background-color: #374151 !important;
        color: white !important;
        font-weight: 700;
    }

    .timetable-print-table .day-col {
        width: 13.3%;
    }

    .timetable-print-table .weekend-day {
        background-color: #f59e0b !important;
        color: white !important;
    }

    .timetable-print-table .locked-day {
        background-color: #dc2626 !important;
        color: white !important;
        opacity: 0.8;
    }

    .timetable-print-table td {
        border: 1px solid #6b7280;
        padding: 0.04cm 0.04cm;
        vertical-align: top;
        font-size: 5.5pt;
        height: 0.3cm;
        overflow: hidden;
        background-color: #ffffff;
        page-break-inside: avoid;
    }

    .time-cell {
        background-color: #374151 !important;
        color: white !important;
        text-align: center;
        font-weight: 700;
        padding: 0.08cm 0.06cm;
        font-size: 5.5pt;
        border: 1px solid #4b5563 !important;
    }

    .entry-cell {
        padding: 0.05cm !important;
    }

    .entry-content {
        background-color: #dbeafe !important;
        border: 1px solid #3b82f6;
        border-radius: 2px;
        padding: 0.04cm;
        min-height: 0.2cm;
        margin: 0;
        box-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
    }

    .print-entry {
        background-color: #dbeafe !important;
    }

    .entry-title {
        font-weight: bold;
        font-size: 5.5pt;
        margin: 0;
        color: #1e40af;
        line-height: 1.2;
    }

    .entry-time {
        font-size: 4.5pt;
        margin: 0.02cm 0;
        color: #1e3a8a;
        line-height: 1.2;
        font-weight: 600;
    }

    .entry-details {
        font-size: 4pt;
        margin: 0.02cm 0;
        color: #1e40af;
        line-height: 1.1;
    }

    .empty-cell {
        background-color: #ffffff !important;
    }

    .locked-cell {
        background-color: #fee2e2 !important;
        opacity: 0.5;
    }

    .timetable-print-table tfoot {
        border-top: 2px solid #1a202c;
        background-color: #1a202c;
        page-break-inside: avoid;
    }

    .time-end-marker {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
        color: white !important;
        padding: 0.08cm 0.08cm !important;
        text-align: center !important;
        font-weight: bold !important;
        border-top: 1px solid #991b1b !important;
        border-bottom: 1px solid #7f1d1d !important;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.3);
        position: relative;
        page-break-inside: avoid;
    }

    .time-end-marker::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, #fbbf24, transparent);
    }

    .end-time-indicator {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.05cm;
        width: 100%;
    }

    .end-time-main {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.1cm;
        flex-wrap: wrap;
    }

    .end-time-icon {
        color: #fbbf24;
        font-size: 7pt;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
        animation: none;
    }

    .end-time-content {
        display: flex;
        align-items: center;
        gap: 0.08cm;
        flex-wrap: wrap;
        justify-content: center;
    }

    .end-time-label {
        color: #ffffff;
        font-weight: 800;
        font-size: 6.5pt;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
        letter-spacing: 0.2px;
    }

    .end-time-separator {
        color: #fbbf24;
        font-weight: 900;
        font-size: 7pt;
        margin: 0 0.03cm;
    }

    .end-time-value {
        color: #ffffff;
        font-weight: 900;
        font-size: 7pt;
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        padding: 0.06cm 0.15cm;
        border-radius: 2px;
        border: 1.5px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.3);
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
        letter-spacing: 0.3px;
        display: inline-block;
        min-width: 0.8cm;
        text-align: center;
    }

    .end-time-note {
        color: #fecaca;
        font-size: 5pt;
        font-weight: 600;
        opacity: 0.95;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.08cm;
        margin-top: 0.02cm;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    }

    .end-time-note i {
        font-size: 4.5pt;
    }

    .printable-footer {
        display: flex !important;
        margin-top: 0.03cm;
        padding-top: 0.03cm;
        border-top: 1px solid #1a202c;
        justify-content: space-between;
        page-break-before: avoid;
        page-break-inside: avoid;
    }

    .footer-content {
        display: flex;
        justify-content: space-between;
        width: 100%;
        gap: 0.2cm;
    }

    .signature-line {
        border-top: 1px solid #1a202c;
        width: 45%;
        text-align: center;
        padding-top: 0.08cm;
    }

    .signature-label {
        font-size: 5.5pt;
        font-weight: bold;
        margin: 0;
    }

    .watermark {
        display: none !important;
    }

    .watermark img {
        display: none !important;
    }

    /* Hide all interactive elements */
    button, .hover\:shadow-md, .group:hover, .no-print {
        display: none !important;
    }

    /* Ensure compact spacing */
    .timetable-wrapper table {
        margin: 0;
    }

    /* Prevent page breaks - but allow footer to stay with table */
    .printable-container {
        page-break-inside: avoid;
        page-break-after: avoid;
    }

    .timetable-wrapper {
        page-break-inside: avoid;
        page-break-after: avoid;
    }

    /* Ensure footer stays with table */
    .timetable-wrapper + .printable-footer {
        page-break-before: avoid;
        margin-top: 0.02cm;
    }

    /* Optimize table cell heights */
    .timetable-print-table tbody tr {
        height: auto;
        max-height: 0.6cm;
    }

    /* Screen styles - keep original for editing */
}

@media screen {
    .entry-cell {
        padding: 0.5rem 0.25rem;
    }

    .entry-content {
        padding: 0.5rem;
        min-height: auto;
    }

    .entry-title {
        font-size: 0.875rem;
    }

    .entry-time {
        font-size: 0.75rem;
    }

    .entry-details {
        font-size: 0.75rem;
    }
}

.printable-container {
    position: relative;
    border: 2px solid #4a5568;
    padding: 2rem;
    border-radius: 10px;
    background-color: white;
}
.printable-header {
    display: none;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
}
.logo {
    max-width: 100px;
    height: auto;
}
.printable-footer {
    display: none;
    margin-top: 3rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
    justify-content: flex-end;
}
.signature-line {
    border-top: 1px solid #4a5568;
    width: 250px;
    text-align: center;
    padding-top: 0.5rem;
}
.watermark {
    display: none;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    opacity: 0.1;
    pointer-events: none;
    z-index: 1;
}
.watermark img {
    width: 150px;
    height: auto;
}
table {
    position: relative;
    z-index: 1;
}

@media screen {
  .printable-container {
    border: none;
    padding: 0;
  }
}
</style>
