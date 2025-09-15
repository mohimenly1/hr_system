<script setup>
import HrLayout from '@/layouts/HrLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    documents: Object, // Paginator object from Laravel
    filters: Object,
});

const page = usePage();

// --- Helper functions for permissions and status display ---
const hasPermission = (permission) => {
    if (!page.props.auth || !page.props.auth.user || !page.props.auth.user.permissions) {
        return false; 
    }
    return page.props.auth.user.permissions.includes(permission);
};

const getStatusDetails = (status) => {
    const statuses = {
        draft: { text: 'مسودة', class: 'bg-gray-200 text-gray-800', icon: 'fas fa-pencil-alt' },
        in_review: { text: 'قيد المراجعة', class: 'bg-blue-100 text-blue-800', icon: 'fas fa-hourglass-half' },
        approved: { text: 'معتمد', class: 'bg-green-100 text-green-800', icon: 'fas fa-check-circle' },
        sent: { text: 'مرسل', class: 'bg-indigo-100 text-indigo-800', icon: 'fas fa-paper-plane' },
        executed: { text: 'منفذ', class: 'bg-purple-100 text-purple-800', icon: 'fas fa-stamp' },
        archived: { text: 'مؤرشف', class: 'bg-gray-100 text-gray-500', icon: 'fas fa-archive' },
        rejected: { text: 'مرفوض', class: 'bg-red-100 text-red-800', icon: 'fas fa-times-circle' },
    };
    return statuses[status] || statuses['draft'];
};
</script>

<template>
    <Head title="إدارة المراسلات" />
    <HrLayout>
        <template #header>
            إدارة المراسلات (الصادر والوارد)
        </template>

        <div class="bg-white shadow-md rounded-lg p-6">
            <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
                <h2 class="text-2xl font-bold text-gray-800">سجل المراسلات</h2>
                
                <div class="flex items-center space-x-2 rtl:space-x-reverse">
                    <Link v-if="hasPermission('create outgoing documents')" 
                          :href="route('documents.create', { type: 'outgoing' })"
                          class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 flex items-center text-sm font-medium transition-colors">
                        <i class="fas fa-file-export mr-2 rtl:ml-2"></i>
                        <span>إنشاء صادر</span>
                    </Link>
                    
                    <Link v-if="hasPermission('register incoming documents')" 
                          :href="route('documents.create', { type: 'incoming' })"
                          class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 flex items-center text-sm font-medium transition-colors">
                        <i class="fas fa-file-import mr-2 rtl:ml-2"></i>
                        <span>تسجيل وارد</span>
                    </Link>

                    <Link v-if="hasPermission('execute document workflows')" 
                          :href="route('documents.tasks')"
                          class="bg-yellow-500 text-white px-4 py-2 rounded-md hover:bg-yellow-600 flex items-center text-sm font-medium transition-colors">
                        <i class="fas fa-tasks mr-2 rtl:ml-2"></i>
                        <span>المهام المعلقة</span>
                    </Link>
                </div>
            </div>

            <div class="border-b border-gray-200 mb-4">
                <nav class="-mb-px flex space-x-6 rtl:space-x-reverse" aria-label="Tabs">
                    <Link :href="route('documents.index')" 
                          :class="[filters.tab === 'all' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']"
                          class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        كل المراسلات
                    </Link>
                    <Link :href="route('documents.index', { tab: 'outgoing' })"
                          :preserve-state="true" :replace="true"
                          :class="[filters.tab === 'outgoing' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']"
                          class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        الصادر
                    </Link>
                    <Link :href="route('documents.index', { tab: 'incoming' })"
                          :preserve-state="true" :replace="true"
                          :class="[filters.tab === 'incoming' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']"
                          class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        الوارد
                    </Link>
                    <Link :href="route('documents.index', { tab: 'drafts' })"
                          :preserve-state="true" :replace="true"
                          :class="[filters.tab === 'drafts' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']"
                          class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        المسودات
                    </Link>
                    <Link :href="route('documents.index', { tab: 'archived' })"
                          :preserve-state="true" :replace="true"
                          :class="[filters.tab === 'archived' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']"
                          class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        الأرشيف
                    </Link>
                </nav>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">الرقم</th>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">الموضوع</th>
                            <th class="py-3 px-4 text-center text-sm font-semibold text-gray-600">النوع</th>
                            <th class="py-3 px-4 text-center text-sm font-semibold text-gray-600">الحالة</th>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">المنشئ</th>
                            <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">التاريخ</th>
                            <th class="py-3 px-4 text-center text-sm font-semibold text-gray-600">الإجراء</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="doc in documents.data" :key="doc.id" class="border-b hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-4 font-mono">{{ doc.serial_number }}</td>
                            <td class="py-3 px-4 font-medium">{{ doc.subject }}</td>
                            <td class="py-3 px-4 text-center">
                                <span :class="[doc.type === 'outgoing' ? 'text-blue-600' : 'text-green-600']">
                                    {{ doc.type === 'outgoing' ? 'صادر' : 'وارد' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full inline-flex items-center" :class="getStatusDetails(doc.status).class">
                                    <i :class="getStatusDetails(doc.status).icon" class="mr-1.5 rtl:ml-1.5"></i>
                                    {{ getStatusDetails(doc.status).text }}
                                </span>
                            </td>
                            <td class="py-3 px-4">{{ doc.creator.name }}</td>
                            <td class="py-3 px-4">{{ new Date(doc.created_at).toLocaleDateString('ar-LY') }}</td>
                            <td class="py-3 px-4 text-center">
                                <Link :href="route('documents.show', doc.id)" class="text-sm bg-indigo-100 text-indigo-700 hover:bg-indigo-200 px-3 py-1 rounded-full font-semibold transition-colors">عرض</Link>
                            </td>
                        </tr>
                        <tr v-if="documents.data.length === 0">
                            <td colspan="7" class="text-center py-8 text-gray-500">لا توجد وثائق لعرضها في هذا التبويب.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ### NEW: Pagination Component ### -->
            <div v-if="documents.links.length > 3" class="mt-6 flex justify-center">
                <div class="flex flex-wrap -mb-1">
                    <template v-for="(link, key) in documents.links" :key="key">
                        <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded" v-html="link.label" />
                        <Link v-else 
                              class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500" 
                              :class="{ 'bg-indigo-500 text-white': link.active }" 
                              :href="link.url" 
                              v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </HrLayout>
</template>

