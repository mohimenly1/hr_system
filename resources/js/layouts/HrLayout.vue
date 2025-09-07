<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

// Access page props, including auth and permissions
const page = usePage();

// Helper function to check for user permissions
const hasPermission = (permission) => {
    // Fallback to true if permissions are not set up, to avoid hiding everything
    if (!page.props.auth || !page.props.auth.permissions) {
        return true; 
    }
    return page.props.auth.permissions.includes(permission);
};

// State for collapsible menus
const isAcademicMgmtMenuOpen = ref(false);
const isSettingsMenuOpen = ref(false);

const toggleAcademicMgmtMenu = () => {
    isAcademicMgmtMenuOpen.value = !isAcademicMgmtMenuOpen.value;
};

const toggleSettingsMenu = () => {
    isSettingsMenuOpen.value = !isSettingsMenuOpen.value;
};
</script>

<template>
    <div dir="rtl" class="flex h-screen bg-gray-100 font-sans">
        <!-- Sidebar -->
        <aside class="w-64 bg-gray-800 text-white flex-shrink-0">
            <div class="p-4 text-2xl font-bold border-b border-gray-700">
                لوحة التحكم
            </div>
            <nav class="mt-4 px-2">
                <!-- HR Management Section -->
                <div class="mb-4">
                    <h3 class="px-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">إدارة الموارد البشرية</h3>
                    <div class="mt-2 space-y-1">
                        <Link v-if="hasPermission('view dashboard')" :href="route('dashboard')" :class="{ 'bg-gray-700': $page.component === 'Dashboard' }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-tachometer-alt fa-fw w-6 text-center"></i>
                            <span class="ml-3">لوحة التحكم</span>
                        </Link>
                        <Link v-if="hasPermission('manage employees')" :href="route('hr.employees.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Employees') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-users fa-fw w-6 text-center"></i>
                            <span class="ml-3">الموظفين</span>
                        </Link>
                        <Link v-if="hasPermission('manage attendance')" :href="route('hr.attendances.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Attendances') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-clock fa-fw w-6 text-center"></i>
                            <span class="ml-3">الحضور والانصراف</span>
                        </Link>
                         <Link v-if="hasPermission('manage leaves')" :href="route('hr.leaves.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Leaves') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-calendar-alt fa-fw w-6 text-center"></i>
                            <span class="ml-3">الإجازات</span>
                        </Link>
                         <Link v-if="hasPermission('manage payroll')" :href="route('hr.payroll.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Payroll') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-money-bill-wave fa-fw w-6 text-center"></i>
                            <span class="ml-3">الرواتب</span>
                        </Link>
                    </div>
                </div>

                <!-- Academic Management Section -->
                <div class="mb-4">
    <h3 class="px-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">الإدارة الأكاديمية</h3>
    <div class="mt-2 space-y-1">
        <Link :href="route('school.teachers.index')" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
            <i class="fas fa-chalkboard-teacher fa-fw w-6 text-center"></i>
            <span class="ml-3">إدارة المعلمين</span>
        </Link>
        <a href="#" class="flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-500 cursor-not-allowed">
            <i class="fas fa-user-graduate fa-fw w-6 text-center"></i>
            <span class="ml-3">إدارة الطلاب (قريباً)</span>
        </a>
        <a href="#" class="flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-500 cursor-not-allowed">
            <i class="fas fa-calendar-week fa-fw w-6 text-center"></i>
            <span class="ml-3">الجداول الدراسية (قريباً)</span>
        </a>
    </div>
</div>

                <!-- Settings Section -->
                <div>
                    <h3 class="px-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">الإعدادات العامة</h3>
                    <div class="mt-2 space-y-1">
                        <!-- Collapsible Academic Setup Menu -->
                        <div v-if="hasPermission('manage academic settings')">
                            <button @click="toggleSettingsMenu" class="w-full flex items-center justify-between px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                                <div class="flex items-center">
                                    <i class="fas fa-school fa-fw w-6 text-center"></i>
                                    <span class="ml-3">الإعدادات الأكاديمية</span>
                                </div>
                                <i class="fas transition-transform duration-300" :class="{'fa-chevron-down': !isSettingsMenuOpen, 'fa-chevron-up': isSettingsMenuOpen}"></i>
                            </button>
                            <div v-show="isSettingsMenuOpen" class="mt-1 space-y-1">
                                <Link :href="route('school.academic-years.index')" :class="{ 'bg-gray-700': $page.component.startsWith('School/Academic/Years') }" class="flex items-center w-full pr-8 pl-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
            - السنوات الدراسية
        </Link>
        <Link :href="route('school.grades.index')" :class="{ 'bg-gray-700': $page.component.startsWith('School/Academic/Grades') }" class="flex items-center w-full pr-8 pl-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
            - المراحل الدراسية
        </Link>
        <!-- UPDATED LINK -->
        <Link :href="route('school.sections.index')" :class="{ 'bg-gray-700': $page.component.startsWith('School/Academic/Sections') }" class="flex items-center w-full pr-8 pl-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
            - الشعب الدراسية
        </Link>
        <Link :href="route('school.subjects.index')" :class="{ 'text-white font-bold': $page.component.startsWith('School/Academic/Subjects') }" class="block px-4 py-2 text-gray-300 hover:text-white">المقررات الدراسية</Link>
                            </div>
                        </div>

                        <!-- System Users and Roles -->
                         <Link v-if="hasPermission('manage users')" :href="route('hr.users.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Users') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-users-cog fa-fw w-6 text-center"></i>
                            <span class="ml-3">إدارة المستخدمين</span>
                        </Link>
                         <Link v-if="hasPermission('manage roles')" :href="route('hr.roles.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Roles') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-user-shield fa-fw w-6 text-center"></i>
                            <span class="ml-3">الأدوار والصلاحيات</span>
                        </Link>
                    </div>

                    <!-- Add this inside the <nav> tag, perhaps in a new "Integrations" section -->
<div class="px-4 mt-6">
    <div class="border-t border-gray-700"></div>
    <p class="mt-4 text-xs uppercase text-gray-400 tracking-wider">التكاملات</p>
</div>
<Link :href="route('hr.fingerprint.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Fingerprint') }" class="block px-4 py-3 hover:bg-gray-700 transition duration-200">
    <i class="fas fa-fingerprint mr-2 w-5 text-center"></i> جهاز البصمة
</Link>


                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
             <!-- Topbar, Flash Messages, Main Content Slot -->
             <header class="bg-white shadow-md">
                <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                     <h1 class="text-xl font-semibold text-gray-800">
                        <slot name="header" />
                    </h1>
                </div>
            </header>
             <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-200">
                 <div class="container mx-auto px-6 py-8">
                     <div v-if="$page.props.flash && ($page.props.flash.success || $page.props.flash.error || $page.props.flash.info)" class="mb-4">
                        <div v-if="$page.props.flash.success" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert">
                            <p>{{ $page.props.flash.success }}</p>
                        </div>
                         <div v-if="$page.props.flash.error" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
                            <p>{{ $page.props.flash.error }}</p>
                        </div>
                         <div v-if="$page.props.flash.info" class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-md" role="alert">
                            <p>{{ $page.props.flash.info }}</p>
                        </div>
                    </div>
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<!-- Add Font Awesome for icons -->
<style>
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
</style>

