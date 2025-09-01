<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div dir="rtl" class="flex h-screen bg-gray-100 font-sans">
        <!-- Sidebar -->
        <aside class="w-64 bg-gray-800 text-white flex-shrink-0">
            <div class="p-4 text-2xl font-bold border-b border-gray-700">
                نظام HR
            </div>
            <nav class="mt-4">
                <Link :href="route('dashboard')" :class="{ 'bg-gray-700': $page.component === 'Dashboard' }" class="block px-4 py-3 hover:bg-gray-700 transition duration-200">
                    <i class="fas fa-tachometer-alt mr-2"></i> لوحة التحكم
                </Link>
                <Link :href="route('hr.employees.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Employees') }" class="block px-4 py-3 hover:bg-gray-700 transition duration-200">
                    <i class="fas fa-users mr-2"></i> الموظفين
                </Link>
                 <Link :href="route('hr.contracts.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Contracts') }" class="block px-4 py-3 hover:bg-gray-700 transition duration-200">
                    <i class="fas fa-file-contract mr-2"></i> العقود
                </Link>
                <Link :href="route('hr.payroll.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Payroll') }" class="block px-4 py-3 hover:bg-gray-700 transition duration-200">
                    <i class="fas fa-money-bill-wave mr-2"></i> الرواتب
                </Link>
                <Link :href="route('hr.attendances.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Attendances') }" class="block px-4 py-3 hover:bg-gray-700 transition duration-200">
                    <i class="fas fa-clock mr-2"></i> الحضور والانصراف
                </Link>
                 <Link :href="route('hr.leaves.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Leaves') }" class="block px-4 py-3 hover:bg-gray-700 transition duration-200">
                    <i class="fas fa-calendar-alt mr-2"></i> الإجازات
                </Link>
                <Link :href="route('hr.roles.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Roles') }" class="block px-4 py-3 hover:bg-gray-700 transition duration-200">
                    <i class="fas fa-user-shield mr-2 w-5 text-center"></i> الأدوار والصلاحيات
                </Link>
                <Link :href="route('hr.users.index')" :class="{ 'bg-gray-700': $page.component.startsWith('HR/Users') }" class="block px-4 py-3 hover:bg-gray-700 transition duration-200">
    <i class="fas fa-users-cog mr-2 w-5 text-center"></i> إدارة المستخدمين
</Link>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Topbar (with Flash Messages) -->
            <header class="bg-white shadow-md relative z-10">
                <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                     <h1 class="text-xl font-semibold text-gray-800">
                        <slot name="header" />
                    </h1>
                     <!-- Settings Dropdown etc. can go here -->
                </div>
            </header>

            <!-- Flash Messages Container -->
            <div class="p-4 space-y-4">
                <div v-if="$page.props.flash.success" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md animate-fade-in-down" role="alert">
                    <p class="font-bold">نجاح!</p>
                    <p>{{ $page.props.flash.success }}</p>
                </div>
                <div v-if="$page.props.flash.error" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md animate-fade-in-down" role="alert">
                    <p class="font-bold">خطأ!</p>
                    <p>{{ $page.props.flash.error }}</p>
                </div>
                <div v-if="$page.props.flash.info" class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg shadow-md animate-fade-in-down" role="alert">
                    <p class="font-bold">معلومة</p>
                    <p>{{ $page.props.flash.info }}</p>
                </div>
            </div>

            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-200 -mt-16 pt-16">
                <div class="container mx-auto px-6 py-8">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<!-- Add Font Awesome for icons -->
<style>
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');

@keyframes fade-in-down {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fade-in-down {
    animation: fade-in-down 0.5s ease-out forwards;
}
</style>

