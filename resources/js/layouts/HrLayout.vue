<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const hasPermission = (permission) => {
    // --- THE FIX: Use the correct path to access permissions ---
    if (!page.props.auth || !page.props.auth.user || !page.props.auth.user.permissions) {
        return false; 
    }
    return page.props.auth.user.permissions.includes(permission);
};

const hasAnyPermission = (permissions) => {
    if (!page.props.auth || !page.props.auth.user || !page.props.auth.user.permissions) {
        return false;
    }
    for (const permission of permissions) {
        if (page.props.auth.user.permissions.includes(permission)) return true;
    }
    return false;
};

const isSettingsMenuOpen = ref(false);
const toggleSettingsMenu = () => {
    isSettingsMenuOpen.value = !isSettingsMenuOpen.value;
};
</script>

<template>
    <div dir="rtl" class="flex h-screen bg-gray-100 font-sans">
        <!-- Sidebar -->
        <aside class="w-64 bg-gray-800 text-white flex flex-col">
            <div class="p-4 text-2xl font-bold border-b border-gray-700">
                لوحة التحكم
            </div>
            <nav class="mt-4 px-2 flex-grow">
                <!-- HR Management Section -->
                <div class="mb-4">
                    <h3 class="px-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">إدارة الموارد البشرية</h3>
                    <div class="mt-2 space-y-1">
                        <Link :href="route('dashboard')" :class="{ 'bg-gray-700': $page.component === 'Dashboard' }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-tachometer-alt fa-fw w-6 text-center"></i>
                            <span class="ml-3">لوحة التحكم</span>
                        </Link>
                         <Link v-if="hasPermission('manage departments')" :href="route('hr.departments.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Departments') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-building fa-fw w-6 text-center"></i>
                            <span class="ml-3">إدارة الأقسام</span>
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
                    </div>
                </div>

                <!-- Settings & Integrations -->
                 <div v-if="hasAnyPermission(['manage roles', 'manage users', 'access integrations'])">
                    <h3 class="px-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">الإعدادات والتكاملات</h3>
                    <div class="mt-2 space-y-1">
                         <Link v-if="hasPermission('manage users')" :href="route('hr.users.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Users') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-users-cog fa-fw w-6 text-center"></i>
                            <span class="ml-3">إدارة المستخدمين</span>
                        </Link>
                         <Link v-if="hasPermission('manage roles')" :href="route('hr.roles.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Roles') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-user-shield fa-fw w-6 text-center"></i>
                            <span class="ml-3">الأدوار والصلاحيات</span>
                        </Link>
                        <Link v-if="hasPermission('access integrations')" :href="route('hr.integrations.fingerprint.index')" :class="{ 'bg-gray-700': $page.component.startsWith('Integrations/Fingerprint') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-fingerprint fa-fw w-6 text-center"></i>
                            <span class="ml-3">جهاز البصمة</span>
                        </Link>
                         <Link v-if="hasPermission('access integrations')" :href="route('hr.integrations.shifts.index')" :class="{ 'bg-gray-700': $page.component.startsWith('Integrations/Shifts') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-business-time fa-fw w-6 text-center"></i>
                            <span class="ml-3">إدارة الورديات</span>
                        </Link>
                        <Link v-if="hasPermission('access integrations')" :href="route('hr.integrations.scheduling.index')" :class="{ 'bg-gray-700': $page.component.startsWith('Integrations/Scheduling') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-calendar-check fa-fw w-6 text-center"></i>
                            <span class="ml-3">إعدادات الجدولة</span>
                        </Link>
                        <Link :href="route('hr.leave-settings.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/LeaveSettings') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                        <i class="fas fa-cogs fa-fw w-6 text-center"></i>
                        <span class="ml-3">إعدادات الإجازات</span>
                        </Link>
                        <Link v-if="hasPermission('manage evaluation settings')" :href="route('hr.evaluation-settings.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/EvaluationSettings') }" class="flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-700">
                            <i class="fas fa-star-half-alt fa-fw w-6 text-center"></i>
                            <span class="ml-3">إعدادات التقييمات</span>
                        </Link>


                    </div>
                </div>
            </nav>

            <!-- Logout Button -->
            <div class="px-2 py-4 border-t border-gray-700">
                 <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center px-2 py-2 text-sm font-medium rounded-md text-red-400 hover:bg-red-500 hover:text-white">
                    <i class="fas fa-sign-out-alt fa-fw w-6 text-center"></i>
                    <span class="ml-3">تسجيل الخروج</span>
                </Link>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
             <header class="bg-white shadow-md no-print">
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

<style>
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
</style>

