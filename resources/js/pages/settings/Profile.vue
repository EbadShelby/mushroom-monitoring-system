<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const canReceiveSms = computed(() => (user.value.role ?? 'student') !== 'student');
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            :description="
                canReceiveSms
                    ? 'Update your name, email address, and SMS alert contact number'
                    : 'Update your name and email address'
            "
        />

        <div
            class="rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md transition-all duration-300 hover:shadow-md sm:p-8"
        >
            <Form
                v-bind="ProfileController.update.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        class="mt-1 block w-full bg-background/50"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                        placeholder="Full name"
                    />
                    <InputError class="mt-2" :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        class="mt-1 block w-full bg-background/50"
                        name="email"
                        :default-value="user.email"
                        required
                        autocomplete="username"
                        placeholder="Email address"
                    />
                    <InputError class="mt-2" :message="errors.email" />
                </div>

                <div v-if="canReceiveSms" class="grid gap-2">
                    <Label for="contact_number">Contact number (SMS alerts)</Label>
                    <Input
                        id="contact_number"
                        type="tel"
                        class="mt-1 block w-full bg-background/50"
                        name="contact_number"
                        :default-value="user.contact_number ?? ''"
                        autocomplete="tel"
                        placeholder="e.g. 09171234567"
                    />
                    <InputError class="mt-2" :message="errors.contact_number" />
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <Button
                        :disabled="processing"
                        data-test="update-profile-button"
                        >Save changes</Button
                    >
                </div>
            </Form>
        </div>
    </div>
</template>
