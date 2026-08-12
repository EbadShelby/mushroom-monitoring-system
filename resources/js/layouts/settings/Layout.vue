<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Security',
        href: editSecurity(),
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div
        class="relative flex min-h-[calc(100vh-theme(spacing.16))] flex-1 flex-col bg-gradient-to-br from-primary/5 via-background to-secondary/10 px-4 py-8 md:px-10"
    >
        <!-- Subtle decorative blobs -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-primary/5 blur-3xl"
            ></div>
            <div
                class="absolute bottom-0 left-0 h-96 w-96 rounded-full bg-secondary/5 blur-3xl"
            ></div>
        </div>

        <div class="z-10 mx-auto w-full max-w-7xl">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div class="mt-8 flex flex-col lg:flex-row lg:space-x-12">
                <aside class="w-full max-w-xl lg:w-64">
                    <nav
                        class="flex flex-col space-y-2 space-x-0"
                        aria-label="Settings"
                    >
                        <Button
                            v-for="item in sidebarNavItems"
                            :key="toUrl(item.href)"
                            variant="ghost"
                            :class="[
                                'w-full justify-start rounded-xl transition-all duration-200',
                                isCurrentOrParentUrl(item.href)
                                    ? 'bg-primary/10 font-medium text-primary shadow-sm backdrop-blur-sm'
                                    : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                            ]"
                            as-child
                        >
                            <Link :href="item.href">
                                <component
                                    :is="item.icon"
                                    class="mr-2 h-4 w-4"
                                />
                                {{ item.title }}
                            </Link>
                        </Button>
                    </nav>
                </aside>

                <Separator class="my-6 lg:hidden" />

                <div class="flex-1">
                    <section class="max-w-2xl space-y-12">
                        <slot />
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
