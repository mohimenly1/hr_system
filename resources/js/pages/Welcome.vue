<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    // You can pass employee-specific data here in the future
});
</script>

<template>
    <Head title="مرحباً بك" />

    <div class="flex items-center justify-center min-h-screen bg-gray-100">
        <div class="text-center p-10 bg-white rounded-lg shadow-xl">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">
                مرحباً بك في بوابة الموظفين
            </h1>
            <p class="text-gray-600 mb-6">
                هذه هي صفحتك الرئيسية. من هنا ستتمكن مستقبلاً من الوصول إلى خدماتك.
            </p>
            <div>
                 <!-- Logout Form -->
                <form @submit.prevent="logout">
                     <Link :href="route('logout')" method="post" as="button" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700">
                        تسجيل الخروج
                    </Link>
                </form>
            </div>
        </div>
    </div>
</template>
