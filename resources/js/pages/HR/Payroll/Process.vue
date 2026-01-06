<script setup lang="ts">
import HrLayout from '../../../layouts/HrLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    departments: Array,
    filters: Object,
    preview: Object,
});

const form = useForm({
    month: props.filters?.month || new Date().getMonth() + 1,
    year: props.filters?.year || new Date().getFullYear(),
    department_id: props.filters?.department_id || null,
    mode: 'with_review', // 'manual' or 'with_review'
    include_teachers: true,
    include_employees: true,
});

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - i);
const months = [
    { value: 1, name: 'يناير' }, { value: 2, name: 'فبراير' }, { value: 3, name: 'مارس' },
    { value: 4, name: 'أبريل' }, { value: 5, name: 'مايو' }, { value: 6, name: 'يونيو' },
    { value: 7, name: 'يوليو' }, { value: 8, name: 'أغسطس' }, { value: 9, name: 'سبتمبر' },
    { value: 10, name: 'أكتوبر' }, { value: 11, name: 'نوفمبر' }, { value: 12, name: 'ديسمبر' }
];

const selectedPersonnel = ref([]);
const availablePersonnel = ref([]);
const loadingPersonnel = ref(false);
const showPreview = ref(false);
const previewData = ref(props.preview || null);
const additionalEarnings = ref([]);
const deductionsOverridden = ref(false);
const overrideReason = ref('');
const searchQuery = ref('');
const currentPage = ref(1);
const perPage = ref(15);
const pagination = ref(null);
const totalPersonnel = ref(0);
const expandedItems = ref([]); // For accordion functionality

// Show preview if data exists from props
watch(() => props.preview, (newPreview) => {
    if (newPreview && newPreview.preview && newPreview.preview.length > 0) {
        previewData.value = newPreview;
        showPreview.value = true;
    }
}, { immediate: true });

const monthName = computed(() => {
    const month = months.find(m => m.value === form.month);
    return month ? month.name : '';
});

const loadPersonnel = async (page = 1) => {
    if (!form.month || !form.year) return;

    loadingPersonnel.value = true;
    currentPage.value = page;
    try {
        const params = new URLSearchParams({
            month: form.month,
            year: form.year,
            department_id: form.department_id || '',
            include_employees: form.include_employees ? '1' : '0',
            include_teachers: form.include_teachers ? '1' : '0',
            search: searchQuery.value,
            page: page,
            per_page: perPage.value,
        });

        const response = await fetch(route('hr.payroll.process.personnel') + '?' + params.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        availablePersonnel.value = data.personnel || [];
        pagination.value = data.pagination || null;
        totalPersonnel.value = data.pagination?.total || 0;
    } catch (error) {
        console.error('Error loading personnel:', error);
        alert('حدث خطأ أثناء جلب الموظفين والمعلمين. يرجى المحاولة مرة أخرى.');
    } finally {
        loadingPersonnel.value = false;
    }
};

const searchPersonnel = () => {
    currentPage.value = 1;
    loadPersonnel(1);
};

const toggleSelectAll = () => {
    if (selectedPersonnel.value.length === availablePersonnel.value.length) {
        selectedPersonnel.value = [];
    } else {
        selectedPersonnel.value = availablePersonnel.value.map(p => `${p.type}-${p.id}`);
    }
};

const toggleAccordion = (itemId) => {
    const index = expandedItems.value.indexOf(itemId);
    if (index > -1) {
        expandedItems.value.splice(index, 1);
    } else {
        expandedItems.value.push(itemId);
    }
};

const isExpanded = (itemId) => {
    return expandedItems.value.includes(itemId);
};

const isAllSelected = computed(() => {
    return availablePersonnel.value.length > 0 &&
           selectedPersonnel.value.length === availablePersonnel.value.length;
});

// Format time to 12-hour format with Arabic period (صباحاً/مساءً)
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

// Format minutes to hours and minutes format
// Example: 530 minutes = "8 ساعة و 50 دقيقة"
const formatMinutesToHours = (minutes) => {
    if (!minutes || minutes === 0) return '0 دقيقة';

    const mins = parseInt(minutes);
    if (mins < 60) {
        return mins + ' دقيقة';
    }

    const hours = Math.floor(mins / 60);
    const remainingMinutes = mins % 60;

    if (remainingMinutes === 0) {
        return hours + ' ساعة';
    }

    return hours + ' ساعة و ' + remainingMinutes + ' دقيقة';
};

// Parse event details string and format minutes
const parseAndFormatEventDetails = (detailsString) => {
    if (!detailsString) return '';

    // Replace "X دقيقة" with formatted hours and minutes
    return detailsString.replace(/(\d+)\s*دقيقة/g, (match, minutes) => {
        return formatMinutesToHours(parseInt(minutes));
    });
};

const previewPayroll = () => {
    const selected = availablePersonnel.value.filter(p =>
        selectedPersonnel.value.includes(`${p.type}-${p.id}`)
    );

    if (selected.length === 0) {
        alert('يرجى تحديد الموظفين/المعلمين أولاً');
        return;
    }

    showPreview.value = true;
    previewData.value = null; // Reset preview data

    // Prepare personnel data properly
    const personnelData = selected.map(p => ({
        id: parseInt(p.id),
        type: p.type,
        contract_id: parseInt(p.contract_id)
    }));

    // Log selected personnel before sending
    console.log('Selected personnel for preview:', personnelData);
    console.log('Selected personnel count:', personnelData.length);
    console.log('Form data:', { month: form.month, year: form.year, mode: form.mode });

    // Use router.post instead of router.get for complex array data
    // GET requests don't handle nested arrays well in Inertia
    router.post(route('hr.payroll.process.preview'), {
        month: form.month,
        year: form.year,
        mode: form.mode,
        personnel: personnelData,
    }, {
        preserveState: false,
        preserveScroll: true,
        only: ['preview', 'departments', 'filters'],
        onSuccess: (page) => {
            console.log('Preview response:', page.props);
            previewData.value = page.props.preview || null;
            console.log('Preview data set:', previewData.value);

            // Check if preview data is valid
            if (!previewData.value) {
                console.error('No preview data in response');
                alert('لا توجد بيانات للمعاينة. يرجى التحقق من البيانات المحددة.');
                showPreview.value = false;
                return;
            }

            if (!previewData.value.preview || previewData.value.preview.length === 0) {
                console.warn('Preview array is empty:', previewData.value);
                alert('لا توجد بيانات للمعاينة. يرجى التحقق من البيانات المحددة.');
                showPreview.value = false;
                return;
            }

            console.log('Preview loaded successfully:', previewData.value.preview.length, 'items');
        },
        onError: (errors) => {
            console.error('Error loading preview:', errors);
            showPreview.value = false;
            alert('حدث خطأ أثناء تحميل المعاينة. يرجى المحاولة مرة أخرى.');
        },
    });
};

const addAdditionalEarning = () => {
    additionalEarnings.value.push({
        person_id: null,
        person_type: null,
        description: '',
        amount: 0,
    });
};

const removeAdditionalEarning = (index) => {
    additionalEarnings.value.splice(index, 1);
};

const submitPayroll = () => {
    if (!previewData.value || !previewData.value.preview || previewData.value.preview.length === 0) {
        alert('يرجى معاينة الرواتب أولاً قبل الصرف');
        return;
    }

    // Build personnel array from preview data with apply_deductions flag
    const personnel = previewData.value.preview.map(item => ({
        id: item.id,
        type: item.type,
        contract_id: item.contract_id,
        apply_deductions: item.apply_deductions !== false, // Default to true if not set
    }));

    // Calculate totals for confirmation
    const totalGross = previewData.value.preview.reduce((sum, item) => sum + (parseFloat(item.gross_salary) || 0), 0);
    const totalDeductions = previewData.value.preview.reduce((sum, item) =>
        sum + (item.apply_deductions ? (parseFloat(item.deductions?.total_deduction) || 0) : 0), 0
    );
    const totalNet = previewData.value.preview.reduce((sum, item) =>
        sum + (parseFloat(item.apply_deductions ? item.net_salary : item.gross_salary) || 0), 0
    );

    // Show confirmation dialog with summary
    const confirmMessage = `
        تأكيد صرف الرواتب:

        عدد الموظفين/المعلمين: ${personnel.length}
        إجمالي الرواتب: ${totalGross.toFixed(2)} دينار
        إجمالي الخصومات: ${totalDeductions.toFixed(2)} دينار
        صافي الإجمالي: ${totalNet.toFixed(2)} دينار

        هل تريد المتابعة؟
    `;

    if (!confirm(confirmMessage)) {
        return;
    }

    const payrollForm = useForm({
        month: form.month,
        year: form.year,
        mode: form.mode,
        personnel: personnel,
        deductions_overridden: deductionsOverridden.value,
        override_reason: overrideReason.value,
        additional_earnings: additionalEarnings.value.filter(e => e.person_id && e.amount > 0),
    });

    payrollForm.post(route('hr.payroll.process.store'), {
        onSuccess: () => {
            // Reset selections after successful submission
            selectedPersonnel.value = [];
            previewData.value = null;
            showPreview.value = false;
            loadPersonnel(1);
        },
        onError: (errors) => {
            console.error('Error submitting payroll:', errors);
            alert('حدث خطأ أثناء صرف الرواتب. يرجى المحاولة مرة أخرى.');
        },
    });
};

watch([() => form.month, () => form.year, () => form.department_id, () => form.include_employees, () => form.include_teachers], () => {
    currentPage.value = 1;
    loadPersonnel(1);
}, { immediate: true });

// طباعة/تصدير التقرير
const printPreview = () => {
    if (!previewData.value || !previewData.value.preview || previewData.value.preview.length === 0) {
        alert('لا توجد بيانات للطباعة');
        return;
    }

    // إنشاء نافذة جديدة للطباعة
    const printWindow = window.open('', '_blank');
    const monthName = months.find(m => m.value === form.month)?.name || '';
    const year = form.year;

    // بناء محتوى HTML للتقرير
    let htmlContent = `
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>تقرير معاينة الرواتب - ${monthName} ${year}</title>
            <style>
                @media print {
                    @page {
                        size: A4;
                        margin: 1cm;
                    }
                }
                body {
                    font-family: 'Arial', 'Tahoma', sans-serif;
                    direction: rtl;
                    padding: 20px;
                    color: #333;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 3px solid #4F46E5;
                    padding-bottom: 20px;
                }
                .header h1 {
                    color: #1F2937;
                    margin: 0;
                    font-size: 24px;
                }
                .header .period {
                    color: #6B7280;
                    margin-top: 10px;
                    font-size: 16px;
                }
                .person-section {
                    margin-bottom: 30px;
                    page-break-inside: avoid;
                    border: 2px solid #E5E7EB;
                    border-radius: 8px;
                    padding: 20px;
                    background: #F9FAFB;
                }
                .person-header {
                    background: linear-gradient(to right, #4F46E5, #6366F1);
                    color: white;
                    padding: 15px;
                    border-radius: 6px;
                    margin-bottom: 15px;
                }
                .person-header h2 {
                    margin: 0;
                    font-size: 18px;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 15px;
                    margin-bottom: 15px;
                }
                .info-item {
                    background: white;
                    padding: 10px;
                    border-radius: 6px;
                    border: 1px solid #E5E7EB;
                }
                .info-label {
                    font-size: 12px;
                    color: #6B7280;
                    margin-bottom: 5px;
                }
                .info-value {
                    font-size: 16px;
                    font-weight: bold;
                    color: #1F2937;
                }
                .deduction-group {
                    background: white;
                    padding: 15px;
                    border-radius: 6px;
                    margin-bottom: 10px;
                    border-right: 4px solid #EF4444;
                }
                .group-header {
                    font-weight: bold;
                    color: #DC2626;
                    margin-bottom: 10px;
                    font-size: 14px;
                }
                .days-list {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin-top: 8px;
                }
                .day-badge {
                    background: #FEE2E2;
                    color: #991B1B;
                    padding: 5px 10px;
                    border-radius: 4px;
                    font-size: 11px;
                }
                .summary {
                    background: linear-gradient(to right, #10B981, #059669);
                    color: white;
                    padding: 20px;
                    border-radius: 8px;
                    margin-top: 20px;
                }
                .summary-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 15px;
                    text-align: center;
                }
                .summary-item {
                    background: rgba(255, 255, 255, 0.2);
                    padding: 15px;
                    border-radius: 6px;
                }
                .summary-label {
                    font-size: 12px;
                    opacity: 0.9;
                    margin-bottom: 5px;
                }
                .summary-value {
                    font-size: 20px;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>تقرير معاينة الرواتب</h1>
                <div class="period">الفترة: ${monthName} ${year}</div>
            </div>
    `;

    // إضافة بيانات كل موظف/معلم
    previewData.value.preview.forEach((item) => {
        htmlContent += `
            <div class="person-section">
                <div class="person-header">
                    <h2>${item.name} - ${item.type === 'employee' ? 'موظف' : 'معلم'}</h2>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">الراتب الإجمالي</div>
                        <div class="info-value">${(parseFloat(item.gross_salary) || 0).toFixed(2)} دينار</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">إجمالي الخصومات</div>
                        <div class="info-value" style="color: #DC2626;">${(parseFloat(item.deductions?.total_deduction) || 0).toFixed(2)} دينار</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">صافي الراتب</div>
                        <div class="info-value" style="color: #059669;">${(parseFloat(item.apply_deductions ? item.net_salary : item.gross_salary) || 0).toFixed(2)} دينار</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">تطبيق الخصميات</div>
                        <div class="info-value">${item.apply_deductions ? 'نعم' : 'لا'}</div>
                    </div>
                </div>
        `;

        // إضافة معلومات جدول الدوام
        if (item.schedule_info) {
            htmlContent += `
                <div class="info-item" style="grid-column: 1 / -1; margin-top: 10px;">
                    <div class="info-label">معلومات جدول الدوام</div>
                    <div style="font-size: 13px; margin-top: 8px;">
                        <div>أيام العمل: ${item.schedule_info.working_days_names?.join('، ') || 'غير محدد'}</div>
                        <div>عدد أيام العمل في الأسبوع: ${item.schedule_info.working_days_per_week || 0} أيام</div>
                        <div>عدد أيام العمل الفعلية في الفترة: ${item.schedule_info.actual_working_days_in_period || 0} يوم</div>
                        ${item.schedule_info.shift ? `<div>الدوام: ${item.schedule_info.shift.name} (${item.schedule_info.shift.start_time} - ${item.schedule_info.shift.end_time})</div>` : ''}
                    </div>
                </div>
            `;
        }

        // إضافة قواعد الخصم مع المجموعات
        if (item.deductions?.applied_deductions && item.deductions.applied_deductions.length > 0) {
            htmlContent += `
                <div style="margin-top: 20px;">
                    <h3 style="color: #DC2626; margin-bottom: 15px; font-size: 16px;">قواعد الخصم المطبقة:</h3>
            `;

            item.deductions.applied_deductions.forEach((deduction) => {
                htmlContent += `
                    <div class="deduction-group">
                        <div class="group-header">
                            ${deduction.rule?.name || 'قاعدة خصم'} - المبلغ المخصوم: ${deduction.deduction_amount} دينار
                            ${deduction.amount_per_day && deduction.triggered_count ? ` (${deduction.amount_per_day.toFixed(2)} دينار × ${deduction.triggered_count} يوم)` : ''}
                            ${deduction.total_groups ? ` (${deduction.total_groups} مجموعة)` : ''}
                        </div>
                `;

                // عرض المجموعات إذا كانت موجودة
                if (deduction.groups && deduction.groups.length > 0) {
                    deduction.groups.forEach((group) => {
                        htmlContent += `
                            <div style="margin-top: 10px; padding: 10px; background: #FEF3C7; border-radius: 4px; margin-bottom: 8px;">
                                <div style="font-weight: bold; color: #92400E; margin-bottom: 5px;">
                                    المجموعة ${group.group_number} (${group.days_count} أيام) - الخصم: ${group.deduction_amount} دينار
                                </div>
                                <div class="days-list">
                        `;

                        group.days.forEach((day) => {
                            htmlContent += `
                                <span class="day-badge">
                                    ${day.date} (${day.day_name})
                                    ${day.details ? ` - ${parseAndFormatEventDetails(day.details)}` : ''}
                                </span>
                            `;
                        });

                        htmlContent += `
                                </div>
                            </div>
                        `;
                    });
                } else if (deduction.triggered_days && deduction.triggered_days.length > 0) {
                    // عرض الأيام بدون مجموعات
                    htmlContent += `<div class="days-list">`;
                    deduction.triggered_days.forEach((day) => {
                        htmlContent += `
                            <span class="day-badge">
                                ${day.date} (${day.day_name})
                                ${day.details ? ` - ${day.details}` : ''}
                            </span>
                        `;
                    });
                    htmlContent += `</div>`;
                }

                htmlContent += `</div>`;
            });

            htmlContent += `</div>`;
        }

        htmlContent += `</div>`;
    });

    // إضافة الملخص الإجمالي
    const totalGross = previewData.value.preview.reduce((sum, item) => sum + (parseFloat(item.gross_salary) || 0), 0);
    const totalDeductions = previewData.value.preview.reduce((sum, item) => sum + (item.apply_deductions ? (parseFloat(item.deductions?.total_deduction) || 0) : 0), 0);
    const totalNet = previewData.value.preview.reduce((sum, item) => sum + (parseFloat(item.apply_deductions ? item.net_salary : item.gross_salary) || 0), 0);

    htmlContent += `
            <div class="summary">
                <h3 style="text-align: center; margin-bottom: 20px; font-size: 18px;">ملخص إجمالي الرواتب</h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">إجمالي الرواتب</div>
                        <div class="summary-value">${totalGross.toFixed(2)} دينار</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">إجمالي الخصومات</div>
                        <div class="summary-value">${totalDeductions.toFixed(2)} دينار</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">صافي الإجمالي</div>
                        <div class="summary-value">${totalNet.toFixed(2)} دينار</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">عدد الموظفين/المعلمين</div>
                        <div class="summary-value">${previewData.value.preview.length}</div>
                    </div>
                </div>
            </div>
        </body>
        </html>
    `;

    printWindow.document.write(htmlContent);
    printWindow.document.close();

    // انتظر تحميل المحتوى ثم افتح نافذة الطباعة
    setTimeout(() => {
        printWindow.focus();
        printWindow.print();
    }, 250);
};

// تصدير إلى Excel
const exportToExcel = () => {
    if (!previewData.value || !previewData.value.preview || previewData.value.preview.length === 0) {
        alert('لا توجد بيانات للتصدير');
        return;
    }

    // Add personnel data as JSON
    const personnelData = previewData.value.preview.map(item => ({
        id: item.id,
        type: item.type,
        contract_id: item.contract_id,
    }));

    console.log('Exporting personnel data:', personnelData);
    console.log('Month:', form.month, 'Year:', form.year);

    // استخدام POST لضمان إرسال البيانات بشكل صحيح
    const exportForm = document.createElement('form');
    exportForm.method = 'POST';
    exportForm.action = route('hr.payroll.process.preview.export');
    exportForm.style.display = 'none';

    // إضافة CSRF token - استخدام طريقة أكثر موثوقية
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // إذا لم يوجد في meta tag، جرب الحصول من cookies
    if (!csrfToken) {
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'XSRF-TOKEN') {
                csrfToken = decodeURIComponent(value);
                break;
            }
        }
    }

    if (!csrfToken) {
        console.error('CSRF token not found');
        alert('حدث خطأ في الأمان. يرجى إعادة تحميل الصفحة والمحاولة مرة أخرى.');
        return;
    }

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = csrfToken;
    exportForm.appendChild(csrfInput);

    // إضافة البيانات
    const monthInput = document.createElement('input');
    monthInput.type = 'hidden';
    monthInput.name = 'month';
    monthInput.value = form.month.toString();
    exportForm.appendChild(monthInput);

    const yearInput = document.createElement('input');
    yearInput.type = 'hidden';
    yearInput.name = 'year';
    yearInput.value = form.year.toString();
    exportForm.appendChild(yearInput);

    const modeInput = document.createElement('input');
    modeInput.type = 'hidden';
    modeInput.name = 'mode';
    modeInput.value = form.mode;
    exportForm.appendChild(modeInput);

    const personnelInput = document.createElement('input');
    personnelInput.type = 'hidden';
    personnelInput.name = 'personnel';
    personnelInput.value = JSON.stringify(personnelData);
    exportForm.appendChild(personnelInput);

    document.body.appendChild(exportForm);
    exportForm.submit();
    document.body.removeChild(exportForm);
};

// معاينة مرتب موظف/معلم واحد
const previewSinglePerson = async (person) => {
    if (!form.month || !form.year) {
        alert('يرجى تحديد الشهر والسنة أولاً');
        return;
    }

    const personnelData = [{
        id: parseInt(person.id),
        type: person.type,
        contract_id: parseInt(person.contract_id)
    }];

    router.post(route('hr.payroll.process.preview'), {
        month: form.month,
        year: form.year,
        mode: form.mode,
        personnel: personnelData,
    }, {
        preserveState: false,
        preserveScroll: true,
        only: ['preview', 'departments', 'filters'],
        onSuccess: (page) => {
            previewData.value = page.props.preview || null;
            if (previewData.value && previewData.value.preview && previewData.value.preview.length > 0) {
                showPreview.value = true;
            } else {
                alert('لا توجد بيانات للمعاينة');
            }
        },
        onError: (errors) => {
            console.error('Error loading preview:', errors);
            alert('حدث خطأ أثناء تحميل المعاينة. يرجى المحاولة مرة أخرى.');
        },
    });
};

// تصدير Excel لموظف/معلم واحد
const exportSinglePerson = (person) => {
    if (!form.month || !form.year) {
        alert('يرجى تحديد الشهر والسنة أولاً');
        return;
    }

    // التحقق من وجود contract_id
    if (!person.contract_id) {
        alert('لا يوجد عقد نشط لهذا الموظف/المعلم. يرجى التحقق من بيانات العقد.');
        return;
    }

    const personnelData = [{
        id: parseInt(person.id),
        type: person.type,
        contract_id: parseInt(person.contract_id)
    }];

    console.log('Exporting for person:', person);
    console.log('Personnel data:', personnelData);
    console.log('Month:', form.month, 'Year:', form.year);

    // استخدام POST بدلاً من GET لضمان إرسال البيانات بشكل صحيح
    const exportForm = document.createElement('form');
    exportForm.method = 'POST';
    exportForm.action = route('hr.payroll.process.preview.export');
    exportForm.style.display = 'none';

    // إضافة CSRF token - استخدام طريقة أكثر موثوقية
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // إذا لم يوجد في meta tag، جرب الحصول من cookies
    if (!csrfToken) {
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'XSRF-TOKEN') {
                csrfToken = decodeURIComponent(value);
                break;
            }
        }
    }

    if (!csrfToken) {
        console.error('CSRF token not found');
        alert('حدث خطأ في الأمان. يرجى إعادة تحميل الصفحة والمحاولة مرة أخرى.');
        return;
    }

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = csrfToken;
    exportForm.appendChild(csrfInput);

    // إضافة البيانات
    const monthInput = document.createElement('input');
    monthInput.type = 'hidden';
    monthInput.name = 'month';
    monthInput.value = form.month.toString();
    exportForm.appendChild(monthInput);

    const yearInput = document.createElement('input');
    yearInput.type = 'hidden';
    yearInput.name = 'year';
    yearInput.value = form.year.toString();
    exportForm.appendChild(yearInput);

    const modeInput = document.createElement('input');
    modeInput.type = 'hidden';
    modeInput.name = 'mode';
    modeInput.value = form.mode;
    exportForm.appendChild(modeInput);

    const personnelInput = document.createElement('input');
    personnelInput.type = 'hidden';
    personnelInput.name = 'personnel';
    personnelInput.value = JSON.stringify(personnelData);
    exportForm.appendChild(personnelInput);

    document.body.appendChild(exportForm);
    exportForm.submit();
    document.body.removeChild(exportForm);
};

// صرف مرتب موظف/معلم واحد
const submitSinglePayroll = (person) => {
    if (!form.month || !form.year) {
        alert('يرجى تحديد الشهر والسنة أولاً');
        return;
    }

    if (!confirm(`هل أنت متأكد من رغبتك في صرف مرتب ${person.name} للفترة ${form.month}/${form.year}؟`)) {
        return;
    }

    const personnel = [{
        id: parseInt(person.id),
        type: person.type,
        contract_id: parseInt(person.contract_id),
        apply_deductions: true,
    }];

    router.post(route('hr.payroll.process.submit'), {
        month: form.month,
        year: form.year,
        mode: form.mode,
        personnel: personnel,
        deductions_overridden: deductionsOverridden.value,
        override_reason: overrideReason.value,
    }, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
            alert('تم صرف المرتب بنجاح');
            // إعادة تحميل البيانات
            loadPersonnel(currentPage.value);
        },
        onError: (errors) => {
            console.error('Error submitting payroll:', errors);
            alert('حدث خطأ أثناء صرف المرتب. يرجى المحاولة مرة أخرى.');
        },
    });
};
</script>

<template>
    <Head title="صرف الرواتب" />
    <HrLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-gray-800">صرف الرواتب الشهرية</h2>
                <Link
                    :href="route('hr.payroll.index')"
                    class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-all"
                >
                    <i class="fas fa-arrow-right mr-2"></i>
                    العودة للقائمة
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Filters Section -->
            <div class="bg-gradient-to-br from-indigo-50 to-purple-50 border-2 border-indigo-200 rounded-xl p-6 shadow-lg">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-filter ml-2 text-indigo-600"></i>
                    تحديد الفترة والفلترة
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الشهر</label>
                        <select v-model="form.month" class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                            <option v-for="month in months" :key="month.value" :value="month.value">{{ month.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">السنة</label>
                        <select v-model="form.year" class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                            <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">القسم (اختياري)</label>
                        <select v-model="form.department_id" class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                            <option :value="null">جميع الأقسام</option>
                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">النوع</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" v-model="form.include_employees" class="ml-2 rounded">
                                <span>موظفين</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" v-model="form.include_teachers" class="ml-2 rounded">
                                <span>معلمين</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mode Selection -->
            <div class="bg-white border-2 border-gray-200 rounded-xl p-6 shadow-lg">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-cog ml-2 text-gray-600"></i>
                    طريقة الصرف
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        @click="form.mode = 'manual'"
                        class="border-2 rounded-xl p-6 cursor-pointer transition-all"
                        :class="form.mode === 'manual' ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300'"
                    >
                        <div class="flex items-center mb-3">
                            <input type="radio" v-model="form.mode" value="manual" class="ml-2">
                            <h4 class="text-lg font-bold text-gray-800">صرف يدوي</h4>
                        </div>
                        <p class="text-sm text-gray-600">صرف الرواتب بدون مراجعة سجل الحضور والخصومات</p>
                    </div>
                    <div
                        @click="form.mode = 'with_review'"
                        class="border-2 rounded-xl p-6 cursor-pointer transition-all"
                        :class="form.mode === 'with_review' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                    >
                        <div class="flex items-center mb-3">
                            <input type="radio" v-model="form.mode" value="with_review" class="ml-2">
                            <h4 class="text-lg font-bold text-gray-800">مع مراجعة الحضور</h4>
                        </div>
                        <p class="text-sm text-gray-600">مراجعة سجل الحضور وتطبيق القواعد الخصمية تلقائياً</p>
                    </div>
                </div>
            </div>

            <!-- Personnel Selection -->
            <div class="bg-white border-2 border-gray-200 rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-users ml-2 text-gray-600"></i>
                            اختيار الموظفين/المعلمين
                        </h3>
                        <p v-if="totalPersonnel > 0" class="text-sm text-gray-600 mt-1">
                            إجمالي: <span class="font-semibold">{{ totalPersonnel }}</span> موظف/معلم
                        </p>
                    </div>
                    <button
                        @click="loadPersonnel(1)"
                        :disabled="loadingPersonnel"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-all disabled:opacity-50"
                    >
                        <i class="fas fa-sync-alt ml-2" :class="{ 'fa-spin': loadingPersonnel }"></i>
                        تحديث
                    </button>
                </div>

                <!-- Search Bar -->
                <div class="mb-4">
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            @keyup.enter="searchPersonnel"
                            type="text"
                            placeholder="ابحث بالاسم أو القسم..."
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 pr-12 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                        >
                        <button
                            @click="searchPersonnel"
                            class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-indigo-600"
                        >
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                <div v-if="loadingPersonnel" class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-4xl text-indigo-600"></i>
                    <p class="mt-4 text-gray-600">جاري التحميل...</p>
                </div>

                <div v-else-if="availablePersonnel.length === 0" class="text-center py-8 text-gray-500">
                    <i class="fas fa-users text-4xl mb-4"></i>
                    <p>لا يوجد موظفين/معلمين متاحين</p>
                    <p class="text-sm mt-2">يرجى تحديد الفترة والفلترة</p>
                </div>

                <div v-else class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                            <tr>
                                <th class="px-4 py-4 text-center text-xs font-medium uppercase">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        @change="toggleSelectAll"
                                        class="rounded cursor-pointer"
                                    >
                                </th>
                                <th class="px-6 py-4 text-right text-xs font-medium uppercase">الاسم</th>
                                <th class="px-6 py-4 text-center text-xs font-medium uppercase">النوع</th>
                                <th class="px-6 py-4 text-center text-xs font-medium uppercase">القسم</th>
                                <th class="px-6 py-4 text-center text-xs font-medium uppercase">جدول الدوام</th>
                                <th class="px-6 py-4 text-center text-xs font-medium uppercase">نوع العمل</th>
                                <th class="px-6 py-4 text-center text-xs font-medium uppercase">المرتب الأساسي</th>
                                <th class="px-6 py-4 text-center text-xs font-medium uppercase">الراتب الإجمالي</th>
                                <th class="px-6 py-4 text-center text-xs font-medium uppercase">العمليات</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="person in availablePersonnel"
                                :key="`${person.type}-${person.id}`"
                                class="hover:bg-gray-50 transition-colors"
                                :class="person.type === 'employee' ? 'bg-blue-50/30' : 'bg-purple-50/30'"
                            >
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <input
                                        type="checkbox"
                                        :value="`${person.type}-${person.id}`"
                                        v-model="selectedPersonnel"
                                        class="rounded"
                                    >
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ person.name }}</p>
                                            <p class="text-xs text-gray-500">{{ person.contract_type }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                        :class="person.type === 'employee'
                                            ? 'bg-blue-100 text-blue-800'
                                            : 'bg-purple-100 text-purple-800'"
                                    >
                                        {{ person.type_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                    {{ person.department }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span
                                        v-if="person.has_schedule"
                                        class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800"
                                    >
                                        <i class="fas fa-check-circle ml-1"></i>
                                        موجود
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600"
                                    >
                                        <i class="fas fa-times-circle ml-1"></i>
                                        غير موجود
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                        :class="person.work_type === 'شهري'
                                            ? 'bg-indigo-100 text-indigo-800'
                                            : 'bg-orange-100 text-orange-800'"
                                    >
                                        {{ person.work_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-700">
                                    {{ (parseFloat(person.basic_salary) || 0).toFixed(2) }} دينار
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-bold text-green-700">
                                    {{ (parseFloat(person.salary) || 0).toFixed(2) }} دينار
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            @click="previewSinglePerson(person)"
                                            class="px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 rounded-lg hover:bg-blue-200 transition-all"
                                            title="معاينة المرتب"
                                        >
                                            <i class="fas fa-eye ml-1"></i>
                                            معاينة
                                        </button>
                                        <button
                                            @click="exportSinglePerson(person)"
                                            class="px-3 py-1.5 text-xs font-medium text-green-700 bg-green-100 rounded-lg hover:bg-green-200 transition-all"
                                            title="تصدير Excel"
                                        >
                                            <i class="fas fa-file-excel ml-1"></i>
                                            Excel
                                        </button>
                                        <button
                                            @click="submitSinglePayroll(person)"
                                            class="px-3 py-1.5 text-xs font-medium text-purple-700 bg-purple-100 rounded-lg hover:bg-purple-200 transition-all"
                                            title="صرف المرتب"
                                        >
                                            <i class="fas fa-money-bill-wave ml-1"></i>
                                            صرف
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="pagination && pagination.last_page > 1" class="mt-6 bg-gray-50 rounded-lg p-4">
                        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                            <div class="text-sm text-gray-700">
                                <span class="font-semibold">عرض {{ pagination.from }} إلى {{ pagination.to }}</span>
                                <span class="text-gray-500"> من إجمالي {{ pagination.total }} نتيجة</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="loadPersonnel(1)"
                                    :disabled="pagination.current_page === 1"
                                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                                    title="الصفحة الأولى"
                                >
                                    <i class="fas fa-angle-double-right"></i>
                                </button>
                                <button
                                    @click="loadPersonnel(pagination.current_page - 1)"
                                    :disabled="pagination.current_page === 1"
                                    class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                                >
                                    <i class="fas fa-angle-right ml-1"></i>
                                    السابق
                                </button>
                                <div class="flex items-center gap-1">
                                    <span class="px-3 py-2 text-sm font-semibold text-gray-700">
                                        صفحة {{ pagination.current_page }} من {{ pagination.last_page }}
                                    </span>
                                </div>
                                <button
                                    @click="loadPersonnel(pagination.current_page + 1)"
                                    :disabled="pagination.current_page === pagination.last_page"
                                    class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                                >
                                    التالي
                                    <i class="fas fa-angle-left mr-1"></i>
                                </button>
                                <button
                                    @click="loadPersonnel(pagination.last_page)"
                                    :disabled="pagination.current_page === pagination.last_page"
                                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                                    title="الصفحة الأخيرة"
                                >
                                    <i class="fas fa-angle-double-left"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="pagination && pagination.total > 0" class="mt-4 text-sm text-gray-600 text-center">
                        عرض جميع النتائج ({{ pagination.total }})
                    </div>
                </div>
            </div>

            <!-- Override Deductions Option -->
            <div v-if="form.mode === 'with_review'" class="bg-white border-2 border-gray-200 rounded-xl p-6 shadow-lg">
                <div class="flex items-center mb-4">
                    <input
                        type="checkbox"
                        v-model="deductionsOverridden"
                        class="ml-3 rounded"
                    >
                    <label class="text-lg font-semibold text-gray-800">تجاوز القيود الخصمية</label>
                </div>
                <div v-if="deductionsOverridden" class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">سبب التجاوز:</label>
                    <textarea
                        v-model="overrideReason"
                        rows="3"
                        class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"
                        placeholder="أدخل سبب تجاوز القيود الخصمية..."
                    ></textarea>
                </div>
            </div>

            <!-- Additional Earnings Section -->
            <div class="bg-white border-2 border-gray-200 rounded-xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-plus-circle ml-2 text-green-600"></i>
                        إضافات إضافية
                    </h3>
                    <button
                        @click="addAdditionalEarning"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-all text-sm"
                    >
                        <i class="fas fa-plus ml-1"></i>
                        إضافة بند
                    </button>
                </div>
                <div v-if="additionalEarnings.length > 0" class="space-y-3">
                    <div
                        v-for="(earning, index) in additionalEarnings"
                        :key="index"
                        class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg"
                    >
                        <select
                            v-model="earning.person_id"
                            class="flex-1 border-2 border-gray-200 rounded-lg px-4 py-2 focus:outline-none focus:border-indigo-500"
                        >
                            <option :value="null">اختر الموظف/المعلم</option>
                            <option
                                v-for="person in availablePersonnel.filter(p => selectedPersonnel.includes(`${p.type}-${p.id}`))"
                                :key="`${person.type}-${person.id}`"
                                :value="person.id"
                            >
                                {{ person.name }} ({{ person.type_label }})
                            </option>
                        </select>
                        <select
                            v-model="earning.person_type"
                            class="w-32 border-2 border-gray-200 rounded-lg px-4 py-2 focus:outline-none focus:border-indigo-500"
                        >
                            <option :value="null">النوع</option>
                            <option value="employee">موظف</option>
                            <option value="teacher">معلم</option>
                        </select>
                        <input
                            v-model="earning.description"
                            type="text"
                            placeholder="وصف الإضافة"
                            class="flex-1 border-2 border-gray-200 rounded-lg px-4 py-2 focus:outline-none focus:border-indigo-500"
                        >
                        <input
                            v-model.number="earning.amount"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="المبلغ"
                            class="w-32 border-2 border-gray-200 rounded-lg px-4 py-2 focus:outline-none focus:border-indigo-500"
                        >
                        <button
                            @click="removeAdditionalEarning(index)"
                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-all"
                        >
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <p v-else class="text-center text-gray-500 py-4">لا توجد إضافات إضافية</p>
            </div>

            <!-- Preview Modal -->
            <div
                v-if="showPreview"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-labelledby="modal-title"
                role="dialog"
                aria-modal="true"
            >
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showPreview = false"></div>

                <!-- Modal panel -->
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all w-full max-w-6xl max-h-[90vh] flex flex-col">
                        <!-- Modal Header -->
                        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center gap-3">
                                <i class="fas fa-eye"></i>
                                معاينة الرواتب
                            </h3>
                            <button
                                @click="showPreview = false"
                                class="text-white hover:text-gray-200 transition-colors text-2xl"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="flex-1 overflow-y-auto p-6">
                            <div v-if="!previewData || !previewData.preview || previewData.preview.length === 0" class="text-center py-12 text-gray-500">
                                <i class="fas fa-spinner fa-spin text-5xl mb-4 text-blue-600"></i>
                                <p class="text-lg">جاري تحميل المعاينة...</p>
                            </div>
                            <div v-else class="space-y-4">
                                <!-- Accordion Item -->
                                <div
                                    v-for="item in previewData.preview"
                                    :key="`${item.type}-${item.id}`"
                                    class="border-2 border-gray-200 rounded-lg overflow-hidden shadow-md transition-all"
                                    :class="isExpanded(`${item.type}-${item.id}`) ? 'border-blue-400 shadow-lg' : ''"
                                >
                                    <!-- Accordion Header -->
                                    <button
                                        @click="toggleAccordion(`${item.type}-${item.id}`)"
                                        class="w-full px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 hover:from-gray-100 hover:to-gray-200 transition-all flex items-center justify-between text-right"
                                    >
                                        <div class="flex items-center gap-4 flex-1">
                                            <div class="flex-shrink-0">
                                                <i
                                                    class="fas transition-transform duration-300"
                                                    :class="isExpanded(`${item.type}-${item.id}`) ? 'fa-chevron-up text-blue-600' : 'fa-chevron-down text-gray-500'"
                                                ></i>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="text-lg font-bold text-gray-900 mb-1">{{ item.name }}</h4>
                                                <div class="flex items-center gap-3 text-sm">
                                                    <span
                                                        class="px-3 py-1 text-xs font-medium rounded-full"
                                                        :class="item.type === 'employee' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'"
                                                    >
                                                        {{ item.type === 'employee' ? 'موظف' : 'معلم' }}
                                                    </span>
                                                    <span class="text-gray-600">
                                                        الراتب الإجمالي: <span class="font-bold text-gray-900">{{ (parseFloat(item.gross_salary) || 0).toFixed(2) }} دينار</span>
                                                    </span>
                                                    <span v-if="item.deductions?.total_deduction > 0" class="text-red-600">
                                                        الخصومات: <span class="font-bold">{{ (parseFloat(item.deductions.total_deduction) || 0).toFixed(2) }} دينار</span>
                                                    </span>
                                                    <span class="text-green-700 font-bold">
                                                        الصافي: {{ (parseFloat(item.apply_deductions ? item.net_salary : item.gross_salary) || 0).toFixed(2) }} دينار
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </button>

                                    <!-- Accordion Content -->
                                    <div
                                        v-show="isExpanded(`${item.type}-${item.id}`)"
                                        class="px-6 py-4 bg-white border-t border-gray-200"
                                    >

                        <!-- Schedule Information -->
                        <div v-if="item.schedule_info" class="mb-4 p-4 bg-indigo-50 rounded-lg border border-indigo-200">
                            <h5 class="font-semibold text-indigo-900 mb-3 flex items-center gap-2">
                                <i class="fas fa-calendar-alt"></i>
                                معلومات جدول الدوام
                            </h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <div class="text-gray-600 mb-1">أيام العمل في الأسبوع:</div>
                                    <div class="font-bold text-indigo-800">
                                        <span v-if="item.schedule_info.working_days_names && item.schedule_info.working_days_names.length > 0">
                                            {{ item.schedule_info.working_days_names.join('، ') }}
                                        </span>
                                        <span v-else class="text-gray-500">لم يتم تحديد جدول دوام</span>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1">
                                        عدد أيام العمل في الأسبوع: {{ item.schedule_info.working_days_per_week || 0 }} أيام
                                    </div>
                                </div>
                                <div>
                                    <div class="text-gray-600 mb-1">عدد أيام العمل الفعلية في الفترة:</div>
                                    <div class="font-bold text-indigo-800 text-lg">
                                        {{ item.schedule_info.actual_working_days_in_period || 0 }} يوم
                                    </div>
                                </div>
                                <div v-if="item.schedule_info.shift" class="md:col-span-2">
                                    <div class="text-gray-600 mb-1">
                                        الدوام:
                                        <span v-if="item.schedule_info.shift.source === 'assigned'" class="text-xs text-gray-500 mr-2">(محدد)</span>
                                        <span v-else-if="item.schedule_info.shift.source === 'department'" class="text-xs text-blue-600 mr-2">(قسم)</span>
                                        <span v-else-if="item.schedule_info.shift.source === 'organization'" class="text-xs text-green-600 mr-2">(مؤسسة)</span>
                                    </div>
                                    <div class="font-semibold text-indigo-800">
                                        {{ item.schedule_info.shift.name }}
                                        <span class="text-gray-600">(من {{ item.schedule_info.shift.start_time }} إلى {{ item.schedule_info.shift.end_time }})</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Constraints Information -->
                        <div v-if="item.constraints_info && item.constraints_info.has_constraints" class="mb-4 p-4 bg-purple-50 rounded-lg border border-purple-200">
                            <h5 class="font-semibold text-purple-900 mb-3 flex items-center gap-2">
                                <i class="fas fa-cog"></i>
                                القيود الخاصة
                            </h5>
                            <div class="space-y-2 text-sm">
                                <div v-for="(constraint, idx) in item.constraints_info.constraints" :key="idx" class="bg-white p-2 rounded border border-purple-200">
                                    <div class="font-semibold text-purple-800">{{ constraint.type }}</div>
                                    <div class="text-gray-600 text-xs mt-1">
                                        <span v-if="constraint.employment_type">نوع العمل: {{ constraint.employment_type }}</span>
                                        <span v-if="constraint.value"> | القيمة: {{ JSON.stringify(constraint.value) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Leaves Information -->
                        <div v-if="item.leaves_info && item.leaves_info.total_leaves > 0" class="mb-4 p-4 bg-green-50 rounded-lg border border-green-200">
                            <h5 class="font-semibold text-green-900 mb-3 flex items-center gap-2">
                                <i class="fas fa-calendar-times"></i>
                                الإجازات
                            </h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div v-if="item.leaves_info.paid_leaves_count > 0">
                                    <div class="text-gray-600 mb-2">إجازات مدفوعة ({{ item.leaves_info.paid_leaves_count }})</div>
                                    <div class="space-y-1">
                                        <div v-for="(leave, idx) in item.leaves_info.paid_leaves" :key="idx" class="bg-white p-2 rounded border border-green-300">
                                            <div class="font-semibold text-green-800">{{ leave.leave_type?.name || 'إجازة' }}</div>
                                            <div class="text-xs text-gray-600">
                                                من {{ leave.start_date }} إلى {{ leave.end_date }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="item.leaves_info.unpaid_leaves_count > 0">
                                    <div class="text-gray-600 mb-2">إجازات غير مدفوعة ({{ item.leaves_info.unpaid_leaves_count }})</div>
                                    <div class="space-y-1">
                                        <div v-for="(leave, idx) in item.leaves_info.unpaid_leaves" :key="idx" class="bg-white p-2 rounded border border-red-300">
                                            <div class="font-semibold text-red-800">{{ leave.leave_type?.name || 'إجازة' }}</div>
                                            <div class="text-xs text-gray-600">
                                                من {{ leave.start_date }} إلى {{ leave.end_date }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance Summary -->
                        <div v-if="item.attendance_summary" class="mb-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                            <h5 class="font-semibold text-blue-900 mb-3 flex items-center gap-2">
                                <i class="fas fa-calendar-check"></i>
                                ملخص الحضور والغياب
                            </h5>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <div class="text-gray-600">أيام الحضور</div>
                                    <div class="font-bold text-green-700">{{ item.attendance_summary.present || 0 }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-600">أيام الغياب</div>
                                    <div class="font-bold text-red-700">{{ item.attendance_summary.absent || 0 }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-600">أيام التأخير</div>
                                    <div class="font-bold text-orange-700">{{ item.attendance_summary.late_count || 0 }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-600">انصراف مبكر</div>
                                    <div class="font-bold text-yellow-700">{{ item.attendance_summary.early_leave_count || 0 }}</div>
                                </div>
                            </div>
                            <div v-if="item.attendance_summary.total_minutes_late > 0" class="mt-2 text-xs text-gray-600">
                                إجمالي دقائق التأخير: {{ item.attendance_summary.total_minutes_late }} دقيقة
                            </div>
                        </div>

                                        <!-- Days Subject to Deduction Rules -->
                                        <div v-if="item.deductions?.applied_deductions && item.deductions.applied_deductions.length > 0" class="mb-4 p-4 bg-orange-50 rounded-lg border border-orange-200">
                                            <h5 class="font-semibold text-orange-900 mb-3 flex items-center gap-2">
                                                <i class="fas fa-calendar-day"></i>
                                                الأيام التي ستطبق عليها سياسات الخصم
                                            </h5>
                                            <div class="space-y-2">
                                                <div
                                                    v-for="(deduction, idx) in item.deductions.applied_deductions"
                                                    :key="idx"
                                                    class="bg-white p-3 rounded border border-orange-300"
                                                >
                                                    <div class="font-semibold text-orange-800 mb-2">
                                                        {{ deduction.rule?.name }}
                                                        <span v-if="deduction.amount_per_day && deduction.triggered_count" class="text-xs text-gray-600 mr-2">
                                                            - الخصم لكل يوم: {{ (parseFloat(deduction.amount_per_day) || 0).toFixed(2) }} دينار
                                                        </span>
                                                    </div>
                                                    <div v-if="deduction.triggered_days && deduction.triggered_days.length > 0" class="flex flex-wrap gap-2">
                                                        <span
                                                            v-for="(day, dayIdx) in deduction.triggered_days"
                                                            :key="dayIdx"
                                                            class="px-3 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-medium"
                                                        >
                                                            {{ day.date }} ({{ day.day_name }})
                                                            <span v-if="day.details" class="text-orange-600 mr-1">- {{ parseAndFormatEventDetails(day.details) }}</span>
                                                        </span>
                                                    </div>
                                                    <div v-else class="text-xs text-gray-500">
                                                        لا توجد أيام محددة
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Deduction Rules -->
                                        <div class="mb-4">
                                            <h5 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                                <i class="fas fa-exclamation-triangle text-red-600"></i>
                                                قواعد الخصم المطبقة
                                                <span v-if="item.deductions?.applied_deductions && item.deductions.applied_deductions.length > 0" class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">
                                                    {{ item.deductions.applied_deductions.length }}
                                                </span>
                                            </h5>
                                            <div v-if="item.deductions?.applied_deductions && item.deductions.applied_deductions.length > 0" class="space-y-2">
                                                <div
                                                    v-for="(deduction, idx) in item.deductions.applied_deductions"
                                                    :key="idx"
                                                    class="bg-red-50 p-4 rounded-lg border-2 border-red-200 shadow-sm"
                                                >
                                                    <div class="flex items-start justify-between mb-2">
                                                        <div class="flex-1">
                                                            <div class="font-bold text-red-900 text-base mb-1">
                                                                {{ deduction.rule?.name || 'قاعدة خصم' }}
                                                            </div>
                                                            <div v-if="deduction.rule?.description" class="text-sm text-gray-700 mb-2">
                                                                {{ deduction.rule.description }}
                                                            </div>
                                                            <div class="flex items-center gap-4 mb-2">
                                                                <div class="bg-white px-3 py-1 rounded border border-red-300">
                                                                    <span class="text-xs text-gray-600">المبلغ المخصوم:</span>
                                                                    <span class="text-lg font-bold text-red-700 mr-1">{{ deduction.deduction_amount }} دينار</span>
                                                                    <span v-if="deduction.amount_per_day && deduction.triggered_count" class="text-sm text-gray-600 mr-2">
                                                                        ({{ (parseFloat(deduction.amount_per_day) || 0).toFixed(2) }} دينار × {{ deduction.triggered_count }} يوم)
                                                                    </span>
                                                                </div>
                                                                <div v-if="deduction.triggered_count" class="bg-white px-3 py-1 rounded border border-red-300">
                                                                    <span class="text-xs text-gray-600">عدد الأيام:</span>
                                                                    <span class="text-sm font-semibold text-red-700 mr-1">{{ deduction.triggered_count }}</span>
                                                                </div>
                                                                <div v-if="deduction.total_groups && deduction.total_groups > 0" class="bg-white px-3 py-1 rounded border border-red-300">
                                                                    <span class="text-xs text-gray-600">عدد المجموعات:</span>
                                                                    <span class="text-sm font-semibold text-red-700 mr-1">{{ deduction.total_groups }}</span>
                                                                </div>
                                                            </div>
                                                            <div v-if="deduction.reason" class="text-sm text-gray-700 bg-white p-2 rounded mb-2">
                                                                <i class="fas fa-info-circle text-blue-600"></i>
                                                                <span class="mr-1">{{ deduction.reason }}</span>
                                                            </div>

                                                            <!-- عرض المجموعات إذا كانت موجودة -->
                                                            <div v-if="deduction.groups && deduction.groups.length > 0" class="mb-3">
                                                                <div class="font-semibold mb-2 text-gray-800 bg-white p-2 rounded">
                                                                    <i class="fas fa-layer-group text-indigo-600 mr-1"></i>
                                                                    المجموعات المطبقة (كل مجموعة 3 أيام غير متتالية):
                                                                </div>
                                                                <div class="space-y-2">
                                                                    <div
                                                                        v-for="(group, groupIdx) in deduction.groups"
                                                                        :key="groupIdx"
                                                                        class="bg-white p-3 rounded-lg border-2 border-indigo-200 shadow-sm"
                                                                    >
                                                                        <div class="flex items-center justify-between mb-2">
                                                                            <div class="font-semibold text-indigo-900">
                                                                                المجموعة {{ group.group_number }}
                                                                            </div>
                                                                            <div class="text-sm text-gray-600">
                                                                                عدد الأيام: <span class="font-bold text-indigo-700">{{ group.days_count }}</span>
                                                                                | الخصم: <span class="font-bold text-red-700">{{ group.deduction_amount }} دينار</span>
                                                                                <span v-if="group.amount_per_day" class="text-xs text-gray-600 mr-1">
                                                                                    ({{ (parseFloat(group.amount_per_day) || 0).toFixed(2) }} دينار × {{ group.days_count }} يوم)
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="flex flex-wrap gap-2">
                                                                            <span
                                                                                v-for="(day, dayIdx) in group.days"
                                                                                :key="dayIdx"
                                                                                class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-xs font-medium"
                                                                            >
                                                                                {{ day.date }} ({{ day.day_name }})
                                                                                <span v-if="day.details" class="text-indigo-600 mr-1">- {{ parseAndFormatEventDetails(day.details) }}</span>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- عرض الأيام المطابقة (إذا لم تكن هناك مجموعات) -->
                                                            <div v-else-if="deduction.triggered_days && deduction.triggered_days.length > 0" class="text-xs text-gray-600 bg-white p-2 rounded">
                                                                <div class="font-semibold mb-1 text-gray-800">الأيام المطابقة:</div>
                                                                <div class="flex flex-wrap gap-1">
                                                                    <span
                                                                        v-for="(day, dayIdx) in deduction.triggered_days"
                                                                        :key="dayIdx"
                                                                        class="px-2 py-1 bg-gray-100 rounded text-xs"
                                                                    >
                                                                        {{ day.date }} ({{ day.day_name }})
                                                                        <span v-if="day.details" class="text-gray-500 mr-1">- {{ parseAndFormatEventDetails(day.details) }}</span>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="text-sm text-gray-500 bg-gray-50 p-4 rounded-lg border border-gray-200 text-center">
                                                <i class="fas fa-check-circle text-green-600 mb-2"></i>
                                                <div>لا توجد قواعد خصم مطبقة</div>
                                            </div>
                                        </div>

                                        <!-- Not Applied Rules -->
                                        <div v-if="item.deductions?.not_applied_rules && item.deductions.not_applied_rules.length > 0" class="mb-4">
                                            <h5 class="font-semibold text-gray-700 mb-2 text-sm flex items-center gap-2">
                                                <i class="fas fa-info-circle text-blue-600"></i>
                                                قواعد خصم غير مطبقة ({{ item.deductions.not_applied_rules.length }})
                                            </h5>
                                            <div class="space-y-1">
                                                <div
                                                    v-for="(rule, idx) in item.deductions.not_applied_rules"
                                                    :key="idx"
                                                    class="bg-gray-50 p-2 rounded text-xs text-gray-600"
                                                >
                                                    <span class="font-medium">{{ rule.rule?.name }}</span>
                                                    <span v-if="rule.reason"> - {{ rule.reason }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Apply Deductions Toggle -->
                                        <div class="flex items-center justify-between p-4 bg-yellow-50 rounded-lg border border-yellow-200 mb-4">
                                            <div>
                                                <div class="font-semibold text-gray-900">تطبيق الخصميات على المرتب</div>
                                                <div class="text-xs text-gray-600 mt-1">يمكنك إلغاء تطبيق الخصميات إذا لزم الأمر</div>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    v-model="item.apply_deductions"
                                                    class="sr-only peer"
                                                />
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                            </label>
                                        </div>

                                        <!-- Net Salary Summary -->
                                        <div class="flex items-center justify-between p-4 bg-green-50 rounded-lg border border-green-200 mb-4">
                                            <div class="text-sm text-gray-700">
                                                <div>إجمالي الخصومات: <span class="font-semibold text-red-700">{{ (parseFloat(item.deductions?.total_deduction) || 0).toFixed(2) }} دينار</span></div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm text-gray-600">صافي الراتب</div>
                                                <div class="text-2xl font-bold text-green-700">
                                                    {{ (parseFloat(item.apply_deductions ? item.net_salary : item.gross_salary) || 0).toFixed(2) }} دينار
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Summary Footer -->
                                <div class="mt-6 p-6 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border-2 border-green-200 shadow-md">
                                    <h4 class="text-lg font-bold text-gray-900 mb-4 text-center">ملخص إجمالي الرواتب</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-center">
                                        <div class="bg-white p-4 rounded-lg shadow-sm">
                                            <div class="text-sm text-gray-600 mb-2">إجمالي الرواتب</div>
                                            <div class="text-2xl font-bold text-gray-900">
                                                {{ (previewData.preview.reduce((sum, item) => sum + (parseFloat(item.gross_salary) || 0), 0)).toFixed(2) }} دينار
                                            </div>
                                        </div>
                                        <div class="bg-white p-4 rounded-lg shadow-sm">
                                            <div class="text-sm text-gray-600 mb-2">إجمالي الخصومات</div>
                                            <div class="text-2xl font-bold text-red-700">
                                                {{ (previewData.preview.reduce((sum, item) => sum + (item.apply_deductions ? (parseFloat(item.deductions?.total_deduction) || 0) : 0), 0)).toFixed(2) }} دينار
                                            </div>
                                        </div>
                                        <div class="bg-white p-4 rounded-lg shadow-sm">
                                            <div class="text-sm text-gray-600 mb-2">صافي الإجمالي</div>
                                            <div class="text-2xl font-bold text-green-800">
                                                {{ (previewData.preview.reduce((sum, item) => sum + (parseFloat(item.apply_deductions ? item.net_salary : item.gross_salary) || 0), 0)).toFixed(2) }} دينار
                                            </div>
                                        </div>
                                        <div class="bg-white p-4 rounded-lg shadow-sm">
                                            <div class="text-sm text-gray-600 mb-2">عدد الموظفين/المعلمين</div>
                                            <div class="text-2xl font-bold text-blue-800">
                                                {{ previewData.preview.length }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t border-gray-200">
                            <div class="flex items-center gap-3">
                                <button
                                    @click="showPreview = false"
                                    class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-all font-semibold"
                                >
                                    إلغاء
                                </button>
                                <button
                                    @click="printPreview"
                                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all font-semibold flex items-center gap-2"
                                >
                                    <i class="fas fa-print"></i>
                                    طباعة/تصدير PDF
                                </button>
                                <button
                                    @click="exportToExcel"
                                    :disabled="!previewData || !previewData.preview || previewData.preview.length === 0"
                                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all font-semibold flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <i class="fas fa-file-excel"></i>
                                    تصدير Excel
                                </button>
                            </div>
                            <button
                                @click="submitPayroll"
                                :disabled="!previewData || !previewData.preview || previewData.preview.length === 0"
                                class="px-8 py-2 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg hover:from-green-700 hover:to-emerald-700 transition-all font-bold shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                            >
                                <i class="fas fa-money-bill-wave"></i>
                                صرف الرواتب
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end gap-4 mt-6">
                <button
                    @click="previewPayroll"
                    :disabled="selectedPersonnel.length === 0"
                    class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-8 py-3 rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all font-semibold shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                    <i class="fas fa-eye"></i>
                    معاينة الرواتب
                </button>
            </div>
        </div>
    </HrLayout>
</template>














