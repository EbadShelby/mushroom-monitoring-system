<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Bell,
    Camera,
    ClipboardList,
    Cpu,
    FileText,
    LayoutDashboard,
    Ruler,
    ScrollText,
    Settings,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavGroup } from '@/types';

const page = usePage();
const userRole = computed(
    () => (page.props.auth as any)?.user?.role ?? 'student',
);
const isAdmin = computed(() => userRole.value === 'admin');

const monitoringNav = computed<NavGroup>(() => {
    const items = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutDashboard,
        },
        {
            title: 'Historical Data',
            href: '/historical',
            icon: Activity,
        },
    ];

    if (userRole.value !== 'student') {
        items.push({
            title: 'Actuators',
            href: '/actuators',
            icon: Cpu,
        });
    }

    return {
        label: 'Monitoring',
        items,
    };
});

const growingNav: NavGroup = {
    label: 'Cultivation',
    items: [
        {
            title: 'Growing Cycles',
            href: '/cycles',
            icon: ClipboardList,
        },
        {
            title: 'Measurements',
            href: '/measurements',
            icon: Ruler,
        },
        {
            title: 'Growth Camera',
            href: '/camera',
            icon: Camera,
        },
    ],
};

const reportsNav = computed<NavGroup>(() => {
    const items = [
        {
            title: 'Reports',
            href: '/reports',
            icon: FileText,
        },
    ];

    if (userRole.value !== 'student') {
        items.unshift({
            title: 'Alert Logs',
            href: '/alerts',
            icon: Bell,
        });
    }

    return {
        label: 'Logs & Reports',
        items,
    };
});

const adminNavItems = computed(() => {
    if (!isAdmin.value) {
        return null;
    }

    return {
        label: 'Administration',
        items: [
            {
                title: 'User Logs',
                href: '/user-logs',
                icon: ScrollText,
            },
            {
                title: 'User Management',
                href: '/users',
                icon: Users,
            },
            {
                title: 'Settings',
                href: '/settings',
                icon: Settings,
            },
        ],
    } satisfies NavGroup;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :group="monitoringNav" />
            <SidebarSeparator class="mx-3 my-1" />
            <NavMain :group="growingNav" />
            <SidebarSeparator class="mx-3 my-1" />
            <NavMain :group="reportsNav" />
            <template v-if="adminNavItems">
                <SidebarSeparator class="mx-3 my-1" />
                <NavMain :group="adminNavItems" />
            </template>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
