<script setup lang="ts">
import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200 p-6">
        <Head title="Log in" />

        <div class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-2 bg-white shadow-2xl rounded-2xl overflow-hidden">
            <!-- القسم الأيسر: صورة + شعار -->
            <div class="hidden md:flex flex-col justify-center items-center bg-gradient-to-br from-indigo-600 to-indigo-800 p-10 text-white">
                <div class="text-center space-y-6">
                    <h1 class="text-3xl font-bold tracking-wide">HR Management System</h1>
                    <p class="text-sm opacity-80">Manage employees, payroll, and performance with ease.</p>
                </div>
                <div class="mt-10">
                    <img src="/images/logo-school-one.png" alt="HR Illustration" class="w-72 drop-shadow-lg" />
                </div>
            </div>

            <!-- القسم الأيمن: فورم تسجيل الدخول -->
            <div class="p-10 flex flex-col justify-center">
                <h2 class="text-2xl font-bold text-slate-800 mb-2">Welcome 👋</h2>
                <p class="text-sm text-slate-500 mb-8">Please log in to your account</p>

                <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
                    {{ status }}
                </div>

                <Form
                    v-bind="AuthenticatedSessionController.store.form()
                    "
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <div class="space-y-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            placeholder="email@example.com"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="password">Password</Label>
                 
                        </div>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            placeholder="••••••••"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                        <Label for="remember" class="flex items-center space-x-3">
                            <Checkbox id="remember" name="remember" :tabindex="3" />
                            <span>Remember me</span>
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-lg"
                        :tabindex="4"
                        :disabled="processing"
                    >
                        <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin mr-2 inline" />
                        Log in
                    </Button>

                </Form>
            </div>
        </div>
    </div>
</template>