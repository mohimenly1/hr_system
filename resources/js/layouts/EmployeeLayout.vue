<script setup>
import { Link, usePage } from '@inertiajs/vue3';

const user = usePage().props.auth.user;
</script>

<template>
    <div dir="rtl" class="min-h-screen bg-gray-100 font-sans">
        <nav class="bg-white border-b border-gray-100">
            <!-- Primary Navigation Menu -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <!-- Logo -->
                        <div class="shrink-0 flex items-center">
                             <Link :href="route('dashboard')">
                                <span class="font-bold text-xl text-gray-800">بوابة الموظفين</span>
                            </Link>
                        </div>

                        <!-- Navigation Links -->
                        <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                             <Link :href="route('employee.payslips.index')" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium" :class="route().current('employee.payslips.index') ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                                قسائم الرواتب
                            </Link>
                             <!-- Future links here -->
                        </div>
                    </div>

                     <!-- Logout -->
                    <div class="hidden sm:flex sm:items-center sm:ms-6">
                        <span class="text-gray-700 mr-4">مرحباً, {{ user.name }}</span>
                         <form @submit.prevent>
                             <Link :href="route('logout')" method="post" as="button" class="bg-red-500 text-white px-4 py-2 rounded-md text-sm hover:bg-red-600">
                                تسجيل الخروج
                            </Link>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Heading -->
        <header class="bg-white shadow" v-if="$slots.header">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main>
            <slot />
        </main>
    </div>
</template>
