<script setup>
import HrLayout from '@/layouts/HrLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

// --- إعداد محرر النصوص Tiptap ---
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';

// --- إعداد السحب والإفلات للملفات ---
import { useDropZone } from '@vueuse/core';

const props = defineProps({
    documentType: String,
    generatedSerialNumber: String,
    documentTypes: Array,
    externalParties: Array,
    availableDepartments: Array,
    users: Array,
});

const form = useForm({
    type: props.documentType,
    serial_number: props.generatedSerialNumber,
    subject: '',
    content: '',
    document_type_id: null,
    department_id: null,
    priority: 'normal',
    confidentiality_level: 'normal',
    external_party_id: null,
    attachments: [],
    workflow_steps: [], // سيحتوي على مصفوفة من الكائنات: [{ user_id: X }, { user_id: Y }]
});

onMounted(() => {
    if (props.availableDepartments && props.availableDepartments.length === 1) {
        form.department_id = props.availableDepartments[0].id;
    }
});

const editor = useEditor({
    content: form.content,
    extensions: [
        StarterKit,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
    ],
    onUpdate: ({ editor }) => {
        form.content = editor.getHTML();
    },
    editorProps: {
        attributes: {
            class: 'prose max-w-none p-4 border-gray-300 rounded-b-md min-h-[400px] focus:outline-none',
        },
    },
});

const dropZoneRef = ref(null);
function onDrop(files) { if (files) { form.attachments.push(...Array.from(files)); } }
const { isOverDropZone } = useDropZone(dropZoneRef, onDrop);
const removeAttachment = (index) => { form.attachments.splice(index, 1); };
const handleFileSelect = (event) => { onDrop(event.target.files); };

// --- ### منطق مسار العمل الجديد ### ---
const selectedUserForWorkflow = ref(null);
const addWorkflowStep = () => {
    // التأكد من اختيار مستخدم وأنه غير مضاف مسبقاً
    if (selectedUserForWorkflow.value && !form.workflow_steps.some(step => step.user_id === selectedUserForWorkflow.value)) {
        form.workflow_steps.push({ user_id: selectedUserForWorkflow.value });
        selectedUserForWorkflow.value = null; // إعادة تعيين القائمة المنسدلة
    }
};
const removeWorkflowStep = (index) => {
    form.workflow_steps.splice(index, 1);
};
const getUserNameById = (id) => {
    const user = props.users.find(u => u.id === id);
    return user ? user.name : 'مستخدم غير معروف';
};

// --- ### تحديث دالة الإرسال ### ---
const submitForm = () => {
    // إرسال البيانات فعلياً إلى الخادم
    form.post(route('documents.store'));
};

const formInputClass = "mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 transition";
</script>

<template>
    <Head :title="documentType === 'outgoing' ? 'إنشاء صادر جديد' : 'تسجيل وارد جديد'" />
    <HrLayout>
        <template #header>
            {{ documentType === 'outgoing' ? 'إنشاء وثيقة صادرة جديدة' : 'تسجيل وثيقة واردة جديدة' }}
        </template>

        <form @submit.prevent="submitForm">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- العمود الأيمن: البيانات الأساسية ومسار العمل -->
                <div class="lg:col-span-1 flex flex-col gap-6">
                    <div class="bg-white p-6 rounded-lg shadow-md space-y-4">
                        <h3 class="font-bold text-lg mb-2 border-b pb-3 text-gray-800">البيانات الأساسية</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">الرقم الإشاري</label>
                            <input type="text" :value="generatedSerialNumber" disabled :class="formInputClass" class="bg-gray-100">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">الموضوع</label>
                            <input type="text" v-model="form.subject" :class="formInputClass">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">الجهة المرسلة</label>
                            <select v-model="form.department_id" :class="formInputClass">
                                <option :value="null">-- اختر القسم --</option>
                                <option v-for="dept in availableDepartments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                            </select>
                            <p v-if="!availableDepartments || availableDepartments.length === 0" class="text-xs text-red-600 mt-1">
                                أنت غير معين كمدير لأي قسم حالياً.
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">نوع الوثيقة</label>
                            <select v-model="form.document_type_id" :class="formInputClass">
                                <option :value="null">-- اختر --</option>
                                <option v-for="dt in documentTypes" :key="dt.id" :value="dt.id">{{ dt.name }}</option>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">الأولوية</label>
                                <select v-model="form.priority" :class="formInputClass">
                                    <option value="normal">عادية</option>
                                    <option value="urgent">عاجل</option>
                                    <option value="immediate">فوري</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">السرية</label>
                                <select v-model="form.confidentiality_level" :class="formInputClass">
                                    <option value="normal">عادي</option>
                                    <option value="secret">سري</option>
                                    <option value="top_secret">سري للغاية</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">الجهة (صادر إلى / وارد من)</label>
                            <select v-model="form.external_party_id" :class="formInputClass">
                                <option :value="null">-- اختر جهة --</option>
                                <option v-for="party in externalParties" :key="party.id" :value="party.id">{{ party.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="font-bold text-lg mb-4 border-b pb-3 text-gray-800">مسار المراجعة والاعتماد</h3>
                        <div class="space-y-2">
                            <div v-for="(step, index) in form.workflow_steps" :key="index" class="flex items-center justify-between bg-gray-100 p-2 rounded-md animate-fade-in">
                                <div class="flex items-center">
                                    <span class="bg-indigo-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold">{{ index + 1 }}</span>
                                    <span class="ml-3 rtl:mr-3 text-sm font-medium">{{ getUserNameById(step.user_id) }}</span>
                                </div>
                                <button @click="removeWorkflowStep(index)" type="button" class="text-red-500 hover:text-red-700">&times;</button>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t">
                            <label class="block text-sm font-medium text-gray-700">إضافة خطوة جديدة للمسار</label>
                            <div class="flex items-center space-x-2 rtl:space-x-reverse mt-1">
                                <select v-model="selectedUserForWorkflow" :class="formInputClass" class="flex-grow">
                                    <option :value="null">-- اختر مستخدم --</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                                <button @click="addWorkflowStep" type="button" class="bg-indigo-500 text-white px-3 py-2 rounded-md hover:bg-indigo-600 text-sm">+</button>
                            </div>
                        </div>
                         <div v-if="form.errors.workflow_steps" class="text-sm text-red-600 mt-2">{{ form.errors.workflow_steps }}</div>
                    </div>
                </div>

                <!-- العمود الأيسر: المحتوى والمرفقات -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-lg shadow-md">
                        <div class="p-6 border-b">
                           <h3 class="font-bold text-lg text-gray-800">محتوى الوثيقة</h3>
                        </div>
                        <div v-if="editor" class="border-b">
                            <div class="p-2 bg-gray-50 border-b flex items-center space-x-1 rtl:space-x-reverse flex-wrap">
                                <!-- Basic Formatting -->
                                <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive('bold') }" class="p-2 rounded hover:bg-gray-200 transition-colors" title="عريض"><i class="fas fa-bold"></i></button>
                                <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive('italic') }" class="p-2 rounded hover:bg-gray-200 transition-colors" title="مائل"><i class="fas fa-italic"></i></button>
                                <button type="button" @click="editor.chain().focus().toggleStrike().run()" :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive('strike') }" class="p-2 rounded hover:bg-gray-200 transition-colors" title="يتوسطه خط"><i class="fas fa-strikethrough"></i></button>
                                <div class="h-5 w-px bg-gray-300 mx-1"></div>
                                <!-- Headings -->
                                <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive('heading', { level: 2 }) }" class="p-2 rounded hover:bg-gray-200 transition-colors" title="عنوان 2">H2</button>
                                <button type="button" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive('heading', { level: 3 }) }" class="p-2 rounded hover:bg-gray-200 transition-colors" title="عنوان 3">H3</button>
                                <div class="h-5 w-px bg-gray-300 mx-1"></div>
                                <!-- Lists -->
                                <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive('bulletList') }" class="p-2 rounded hover:bg-gray-200 transition-colors" title="قائمة نقطية"><i class="fas fa-list-ul"></i></button>
                                <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive('orderedList') }" class="p-2 rounded hover:bg-gray-200 transition-colors" title="قائمة رقمية"><i class="fas fa-list-ol"></i></button>
                                <div class="h-5 w-px bg-gray-300 mx-1"></div>
                                <!-- Alignment -->
                                <button type="button" @click="editor.chain().focus().setTextAlign('right').run()" :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive({ textAlign: 'right' }) }" class="p-2 rounded hover:bg-gray-200 transition-colors" title="محاذاة لليمين"><i class="fas fa-align-right"></i></button>
                                <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive({ textAlign: 'center' }) }" class="p-2 rounded hover:bg-gray-200 transition-colors" title="توسيط"><i class="fas fa-align-center"></i></button>
                                <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive({ textAlign: 'left' }) }" class="p-2 rounded hover:bg-gray-200 transition-colors" title="محاذاة لليسار"><i class="fas fa-align-left"></i></button>
                                <div class="h-5 w-px bg-gray-300 mx-1"></div>
                                <!-- Blockquote & Rule -->
                                <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="{ 'bg-indigo-100 text-indigo-600': editor.isActive('blockquote') }" class="p-2 rounded hover:bg-gray-200 transition-colors" title="اقتباس"><i class="fas fa-quote-right"></i></button>
                                <button type="button" @click="editor.chain().focus().setHorizontalRule().run()" class="p-2 rounded hover:bg-gray-200 transition-colors" title="خط أفقي"><i class="fas fa-minus"></i></button>
                                <div class="h-5 w-px bg-gray-300 mx-1"></div>
                                <!-- History -->
                                <button type="button" @click="editor.chain().focus().undo().run()" class="p-2 rounded hover:bg-gray-200 transition-colors" title="تراجع"><i class="fas fa-undo"></i></button>
                                <button type="button" @click="editor.chain().focus().redo().run()" class="p-2 rounded hover:bg-gray-200 transition-colors" title="إعادة"><i class="fas fa-redo"></i></button>
                            </div>
                            <editor-content :editor="editor" />
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="font-bold text-lg mb-4 border-b pb-3 text-gray-800">المرفقات</h3>
                        <div ref="dropZoneRef" @click="$refs.fileInput.click()" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer transition-colors" :class="{ 'bg-indigo-50 border-indigo-400': isOverDropZone }">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                            <p class="mt-2 text-gray-600">اسحب وأفلت الملفات هنا، أو اضغط للاختيار</p>
                            <input ref="fileInput" type="file" @change="handleFileSelect" multiple class="hidden">
                        </div>
                        <ul v-if="form.attachments.length > 0" class="mt-4 space-y-2">
                            <li v-for="(file, index) in form.attachments" :key="index" class="flex items-center justify-between bg-gray-100 p-2 rounded-md">
                                <span class="text-sm truncate">{{ file.name }}</span>
                                <button @click="removeAttachment(index)" type="button" class="text-red-500 hover:text-red-700 mr-2 rtl:ml-2">&times;</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end bg-white p-4 shadow-md rounded-lg">
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 transition-colors" :disabled="form.processing">
                    حفظ وبدء مسار العمل
                </button>
            </div>
        </form>
    </HrLayout>
</template>

<style>
/* استايلات خاصة بمحرر النصوص Tiptap */
.ProseMirror {
  outline: none;
}
/* تنسيق إضافي لاتجاه النص داخل المحرر */
.ProseMirror {
    text-align: right;
}
@keyframes fade-in {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.animate-fade-in {
    animation: fade-in 0.3s ease-out forwards;
}
</style>

