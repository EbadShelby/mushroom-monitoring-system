<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit as editProfile } from '@/routes/profile';

defineOptions({
    layout: {
        title: 'Confirm your password',
        description:
            'Please confirm your current password before opening Security settings.',
    },
});

const form = useForm({
    password: '',
});

const submit = () => {
    form.post('/user/confirm-password', {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Confirm Password" />

    <form class="flex flex-col gap-5" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="password" class="text-sm font-medium">Password</Label>
            <PasswordInput
                id="password"
                v-model="form.password"
                name="password"
                required
                autofocus
                autocomplete="current-password"
                placeholder="Enter your current password"
                class="h-10"
            />
            <InputError :message="form.errors.password" />
        </div>

        <Button
            type="submit"
            class="h-10 w-full bg-emerald-700 font-semibold text-white hover:bg-emerald-600 focus:ring-emerald-500"
            :disabled="form.processing"
        >
            <Spinner v-if="form.processing" class="mr-2" />
            {{ form.processing ? 'Confirming…' : 'Confirm Password' }}
        </Button>

        <div class="text-center">
            <Link
                :href="editProfile()"
                class="text-xs text-muted-foreground transition-colors hover:text-foreground hover:underline"
            >
                ← Back to Profile Settings
            </Link>
        </div>
    </form>
</template>
