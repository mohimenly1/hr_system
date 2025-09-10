<script setup>
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    employees: Array,
    teachers: Array,
    subjects: Array,
    sections: Array,
    templates: Array,
    personnelConstraints: Object,
    timetables: Array,
    activePersonIdentifier: String,
    shifts: Array,
});

// --- Main State Management ---
const activeView = ref('welcome');
const selectedPersonIdentifier = ref(props.activePersonIdentifier || null);
const activePersonTab = ref('constraints');
const searchQuery = ref('');

if (selectedPersonIdentifier.value) {
    activeView.value = 'person';
}

// --- Combined Personnel List & Search ---
const allPersonnel = computed(() => [
    ...props.employees.map(e => ({ id: e.id, name: e.user.name, type: 'Employee' })),
    ...props.teachers.map(t => ({ id: t.id, name: t.user.name, type: 'Teacher' })),
]);

const filteredPersonnel = computed(() => {
    if (!searchQuery.value) return allPersonnel.value;
    return allPersonnel.value.filter(p => p.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const selectPerson = (person) => {
    selectedPersonIdentifier.value = `${person.type}-${person.id}`;
    activeView.value = 'person';
    activePersonTab.value = 'constraints';
};

const showTemplateManager = () => {
    selectedPersonIdentifier.value = null;
    activeView.value = 'templates';
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
    max_subjects_per_day: 3,
    max_sections_per_day: 3,
    allowed_subjects: [],
    allowed_sections: [],
});

const selectedPerson = computed(() => {
    if (!selectedPersonIdentifier.value) return null;
    const [type, id] = selectedPersonIdentifier.value.split('-');
    return allPersonnel.value.find(p => p.id === parseInt(id) && p.type === type);
});

watch(selectedPersonIdentifier, (newValue) => {
    if (newValue) {
        const [type, id] = newValue.split('-');
        router.get(route('hr.integrations.scheduling.index'), {
            person_id: id, person_type: `App\\Models\\${type}`
        }, { preserveState: true, replace: true, preserveScroll: true });
    }
});

watch(() => props.personnelConstraints, (newConstraints) => {
    individualConstraintsForm.reset();
    if (selectedPerson.value) {
        const [type, id] = selectedPersonIdentifier.value.split('-');
        individualConstraintsForm.schedulable_id = parseInt(id);
        individualConstraintsForm.schedulable_type = `App\\Models\\${type}`;
    }
    if (newConstraints) {
         Object.keys(newConstraints).forEach(key => {
            if (individualConstraintsForm.hasOwnProperty(key)) {
                individualConstraintsForm[key] = newConstraints[key];
            }
        });
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

const submitIndividualConstraints = () => {
    individualConstraintsForm.transform((data) => {
        const payload = {
            schedulable_id: data.schedulable_id,
            schedulable_type: data.schedulable_type,
            total_hours_per_week: data.total_hours_per_week,
            required_days: data.required_days,
        };
        if (selectedPerson.value.type === 'Employee') {
            payload.assigned_shift_id = data.assigned_shift_id;
        } else if (selectedPerson.value.type === 'Teacher') {
            payload.max_subjects_per_day = data.max_subjects_per_day;
            payload.max_sections_per_day = data.max_sections_per_day;
            payload.allowed_subjects = data.allowed_subjects;
            payload.allowed_sections = data.allowed_sections;
        }
        return payload;
    }).post(route('hr.integrations.scheduling.constraints.store'), {
        preserveScroll: true,
        onSuccess: () => alert('تم حفظ القيود بنجاح!'),
    });
};

// --- Timetable Generation & View Logic ---
const generateTimetableForm = useForm({});
const generateTimetable = () => {
    if (confirm('هل أنت متأكد من رغبتك في بدء عملية الجدولة؟ سيتم حذف جميع الجداول القديمة وإنشاء جداول جديدة.')) {
        generateTimetableForm.transform(data => ({ ...data, person_identifier: selectedPersonIdentifier.value }))
            .post(route('hr.integrations.scheduling.generate'));
    }
};

const printTimetable = () => {
    window.print();
};

const weekDays = [
    { v: 6, n: 'السبت' }, { v: 0, n: 'الأحد' }, { v: 1, n: 'الإثنين' },
    { v: 2, n: 'الثلاثاء' }, { v: 3, n: 'الأربعاء' }, { v: 4, n: 'الخميس' },
    { v: 5, n: 'الجمعة' }
];

const processedTimetableData = computed(() => {
    if (!selectedPerson.value) return { slots: [], data: [] };

    let startHour = 8;
    let endHour = 17;

    const personTimetable = props.timetables.filter(entry => 
        entry.schedulable_id === selectedPerson.value.id && 
        entry.schedulable_type.includes(selectedPerson.value.type)
    );

    if (personTimetable.length > 0) {
        const allStartHours = personTimetable.map(e => parseInt(e.start_time.split(':')[0]));
        const allEndHours = personTimetable.map(e => parseInt(e.end_time.split(':')[0]));
        startHour = Math.min(...allStartHours);
        endHour = Math.max(...allEndHours);
    } else if (props.personnelConstraints) {
        const shiftId = props.personnelConstraints.assigned_shift_id;
        if (shiftId) {
            const shift = props.shifts.find(s => s.id === shiftId);
            if (shift) {
                startHour = parseInt(shift.start_time.split(':')[0]);
                endHour = parseInt(shift.end_time.split(':')[0]);
            }
        }
    }

    const timeSlots = [];
    for (let i = startHour; i < endHour; i++) {
        const start = i.toString().padStart(2, '0') + ':00';
        const end = (i + 1).toString().padStart(2, '0') + ':00';
        timeSlots.push({ start, end });
    }

    const data = [];
    const rowspanCounters = { 6: 0, 0: 0, 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };

    timeSlots.forEach(slot => {
        const row = { time: slot, days: {} };
        weekDays.forEach(day => {
            if (rowspanCounters[day.v] > 0) {
                row.days[day.v] = { render: false };
                rowspanCounters[day.v]--;
            } else {
                const entry = personTimetable.find(e => {
                    if (e.day_of_week != day.v) return false;
                    const entryStart = e.start_time.substring(0, 5);
                    return entryStart >= slot.start && entryStart < slot.end;
                });
                if (entry) {
                    const entryStartHour = parseInt(entry.start_time.split(':')[0]);
                    const entryEndHour = parseInt(entry.end_time.split(':')[0]);
                    const duration = entryEndHour > entryStartHour ? entryEndHour - entryStartHour : (24 - entryStartHour) + entryEndHour;
                    row.days[day.v] = { render: true, rowspan: duration, entry: entry };
                    rowspanCounters[day.v] = duration - 1;
                } else {
                    row.days[day.v] = { render: true, rowspan: 1, entry: null };
                }
            }
        });
        data.push(row);
    });

    return { slots: timeSlots, data: data };
});
</script>

<template>
    <Head title="إعدادات الجدولة" />
    <HrLayout>
        <template #header>
            إعدادات الجدولة الذكية
        </template>

        <div class="flex space-x-6 rtl:space-x-reverse h-[calc(100vh-100px)]">
            <!-- Right Sidebar for selection -->
            <aside class="w-1/4 bg-white shadow-md rounded-lg p-4 flex flex-col no-print">
                <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-2">الاختيار والتحكم</h3>
                <div class="relative mb-4">
                    <input type="text" v-model="searchQuery" placeholder="ابحث عن موظف أو معلم..." class="w-full pl-8 pr-4 py-2 border rounded-lg text-gray-800">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
                <div class="border-t -mx-4"></div>
                <button @click="showTemplateManager" class="w-full text-right font-semibold text-gray-800 p-3 mt-2 rounded-lg hover:bg-gray-100"
                    :class="{ 'bg-indigo-100 text-indigo-700': activeView === 'templates' }">
                    <i class="fas fa-layer-group fa-fw mr-2"></i> إدارة قوالب الجدولة
                </button>
                <div class="border-t -mx-4"></div>
                <div class="overflow-y-auto flex-grow mt-2">
                    <p class="text-sm text-gray-500 px-2 mb-2">الموظفين والمعلمين</p>
                    <ul>
                        <li v-for="person in filteredPersonnel" :key="`${person.type}-${person.id}`">
                            <button @click="selectPerson(person)" 
                                    class="w-full text-right p-2 rounded-md text-sm text-gray-800 hover:bg-gray-100"
                                    :class="{ 'bg-indigo-100 font-semibold': selectedPersonIdentifier === `${person.type}-${person.id}` }">
                                {{ person.name }}
                            </button>
                        </li>
                    </ul>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="w-3/4 bg-white shadow-md rounded-lg p-6 overflow-y-auto" id="main-content">
                <!-- Welcome View -->
                <div v-if="activeView === 'welcome'" class="text-center text-gray-500 flex flex-col items-center justify-center h-full">
                    <i class="fas fa-mouse-pointer text-5xl mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-800">ابدأ الآن</h2>
                    <p>يرجى اختيار موظف أو معلم من القائمة على اليمين لبدء تعيين القيود أو عرض جدوله.</p>
                </div>

                <!-- Template Management View -->
                <div v-if="activeView === 'templates'">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">إدارة قوالب الجدولة</h2>
                    <p class="text-gray-600">هنا يمكنك إنشاء وتعديل القوالب التي تسهل عملية تعيين القيود لمجموعات من الموظفين أو المعلمين.</p>
                </div>

                <!-- Person-specific View -->
                <div v-if="activeView === 'person' && selectedPerson">
                    <div class="flex justify-between items-center border-b pb-4 mb-4 no-print">
                         <div>
                            <h2 class="text-2xl font-bold text-gray-800">إعدادات: {{ selectedPerson.name }}</h2>
                            <p class="text-sm text-gray-500">{{ selectedPerson.type === 'Employee' ? 'موظف' : 'معلم' }}</p>
                        </div>
                        <div>
                             <button @click="activePersonTab = 'constraints'" :class="['px-4 py-2 rounded-md text-sm font-semibold', activePersonTab === 'constraints' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-800']">
                                <i class="fas fa-sliders-h mr-2"></i>القيود
                            </button>
                            <button @click="activePersonTab = 'timetable'" :class="['px-4 py-2 rounded-md text-sm font-semibold mr-2', activePersonTab === 'timetable' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-800']">
                                <i class="fas fa-calendar-alt mr-2"></i>عرض الجدول
                            </button>
                        </div>
                    </div>

                    <!-- Individual Constraints Content -->
                    <div v-show="activePersonTab === 'constraints'" class="no-print">
                         <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <label class="block text-sm font-medium text-gray-800 mb-2">تطبيق قالب جاهز (اختياري)</label>
                            <select @change="applyTemplate" class="w-full max-w-md bg-white border border-gray-300 text-gray-800 text-sm rounded-lg block p-2.5">
                                <option value="">-- تطبيق قالب --</option>
                                <option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</option>
                            </select>
                        </div>
                         <form @submit.prevent="submitIndividualConstraints">
                            <div class="space-y-8">
                                <div class="bg-gray-50 p-6 rounded-lg">
                                    <h3 class="text-lg leading-6 font-medium text-gray-800">القيود العامة</h3>
                                    <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                        <div v-if="selectedPerson.type === 'Employee'" class="sm:col-span-3">
                                            <label class="block text-sm font-medium text-gray-800">الدوام المحدد</label>
                                            <select v-model="individualConstraintsForm.assigned_shift_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-gray-800">
                                                <option :value="null">-- دوام مرن (حسب الساعات) --</option>
                                                <option v-for="shift in shifts" :key="shift.id" :value="shift.id">{{ shift.name }} ({{ shift.start_time }} - {{ shift.end_time }})</option>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="block text-sm font-medium text-gray-800">إجمالي ساعات العمل في الأسبوع</label>
                                            <input type="number" v-model="individualConstraintsForm.total_hours_per_week" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-gray-800" :disabled="!!individualConstraintsForm.assigned_shift_id">
                                            <p v-if="individualConstraintsForm.assigned_shift_id" class="text-xs text-gray-500 mt-1">يتم تجاهله عند تحديد دوام.</p>
                                        </div>
                                        <div class="sm:col-span-6">
                                            <label class="block text-sm font-medium text-gray-800">الأيام المطلوبة للعمل</label>
                                            <div class="mt-2 flex flex-wrap gap-4">
                                                <label v-for="day in weekDays" :key="day.v" class="flex items-center">
                                                    <input type="checkbox" :value="day.v" v-model="individualConstraintsForm.required_days" class="rounded border-gray-300 text-indigo-600 shadow-sm">
                                                    <span class="ml-2 rtl:mr-2 text-sm text-gray-800">{{ day.n }}</span>
                                                </label>
                                            </div>
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
                            </div>
                            <div class="pt-5 mt-5 border-t border-gray-200">
                                <div class="flex justify-end">
                                    <button type="submit" :disabled="individualConstraintsForm.processing" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">حفظ القيود</button>
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
                                </div>
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
                            
                            <div class="printable-container">
                                <div class="printable-header">
                                    <img src="../../../../../public/images/alfaw.png" alt="Company Logo" class="logo">
                                    <div>
                                        <h2 class="text-2xl font-bold text-gray-800">الجدول الأسبوعي لـِ {{ selectedPerson.name }}</h2>
                                        <p class="text-sm text-gray-500">تاريخ الطباعة: {{ new Date().toLocaleDateString('ar-LY') }}</p>
                                    </div>
                                </div>
                                <div class="border rounded-lg overflow-hidden timetable-wrapper">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="w-32 py-3 px-6 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">الوقت</th>
                                            <th v-for="day in weekDays" :key="day.v" class="py-3 px-6 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">{{ day.n }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 text-gray-800">
                                        <tr v-for="row in processedTimetableData.data" :key="row.time.start">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium bg-gray-50 text-center">{{ row.time.start }} - {{ row.time.end }}</td>
                                            <template v-for="day in weekDays" :key="day.v">
                                                <td v-if="row.days[day.v].render" 
                                                    class="px-2 py-2 whitespace-nowrap text-sm border text-center align-middle"
                                                    :rowspan="row.days[day.v].rowspan">
                                                    <div v-if="row.days[day.v].entry" 
                                                        class="bg-indigo-100 text-indigo-800 rounded-lg p-2 h-full flex flex-col justify-center">
                                                        <p class="font-bold" v-if="row.days[day.v].entry.subject">{{ row.days[day.v].entry.subject.name }}</p>
                                                        <p class="font-bold" v-else>دوام عمل</p>
                                                        <p class="text-xs" v-if="row.days[day.v].entry.section">
                                                            ({{ row.days[day.v].entry.section.name }} - {{ row.days[day.v].entry.section.grade.name }})
                                                        </p>
                                                    </div>
                                                </td>
                                            </template>
                                        </tr>
                                    </tbody>
                                </table>
                                </div>
                                <div class="printable-footer">
                                    <div class="signature-line">
                                        <p class="font-semibold">توقيع مدير شؤون الموظفين</p>
                                    </div>
                                </div>
                                <div class="watermark">
                                    <img src="../../../../../public/images/alfaw.png" alt="Watermark Logo">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </HrLayout>
</template>

<style>
/* --- THE FIX FOR PRINTING --- */
@media print {
    @page {
        size: landscape;
        margin: 1cm;
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
        font-size: 10pt; /* Smaller font for print */
    }
    .no-print {
        display: none !important;
    }
    .printable-header, .printable-footer {
        display: flex !important;
    }
    .watermark {
        display: block !important;
    }
    .bg-indigo-100 {
        background-color: #e0e7ff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .bg-gray-50, .bg-gray-100 {
        background-color: #f9fafb !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .timetable-wrapper table td, .timetable-wrapper table th {
        padding: 4px 6px; /* Reduced padding for print */
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
    max-width: 150px;
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

