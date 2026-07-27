<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Loader2 } from '@lucide/vue';
import { ref } from 'vue';

const form = useForm({
    password: '',
});

const passwordInput = ref<HTMLInputElement | null>(null);

const submit = () => {
    form.post('/user/confirm-password', {
        onFinish: () => {
            form.reset();
            passwordInput.value?.focus();
        },
    });
};
</script>

<template>
    <Head title="Secure Area" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-900/20 p-4">
        <div class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-700/50 bg-slate-800/80 p-8 shadow-2xl backdrop-blur-xl">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-500/10 shadow-inner">
                    <span class="text-2xl font-bold text-emerald-400">🔒</span>
                </div>
                <h1 class="text-2xl font-bold text-white">Secure Area</h1>
                <p class="mt-2 text-sm text-slate-400">
                    This is a secure area of the application. Please confirm your password before continuing.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">Password</label>
                    <input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-xl border border-slate-700 bg-slate-900/50 px-4 py-3 text-white placeholder-slate-500 transition-colors focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        placeholder="••••••••"
                        autofocus
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-400">
                        {{ form.errors.password }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-900/20 transition-all hover:bg-emerald-500 disabled:opacity-70"
                >
                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Confirm Password
                </button>
            </form>
        </div>
    </div>
</template>
