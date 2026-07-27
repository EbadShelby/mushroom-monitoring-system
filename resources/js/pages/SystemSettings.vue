<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import type { AppUser, SystemSettings as SystemSettingsType } from '@/types';
import {
    Users,
    Settings,
    Sliders,
    MessageSquare,
    Sun,
    Monitor,
    Plus,
    Pencil,
    X,
    Check,
    UserX,
    UserCheck,
    ShieldX,
    Eye,
    EyeOff,
} from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Settings', href: '/settings' }],
    },
});

const props = defineProps<{
    users?: AppUser[];
    settings?: SystemSettingsType;
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role ?? 'student');
const isAdmin = computed(() => userRole.value === 'admin');

// Tabs
type Tab = 'users' | 'thresholds' | 'sms' | 'schedule' | 'system';
const activeTab = ref<Tab>('users');

const tabs: { id: Tab; label: string; icon: unknown }[] = [
    { id: 'users', label: 'User Management', icon: Users },
    { id: 'thresholds', label: 'Sensor Thresholds', icon: Sliders },
    { id: 'sms', label: 'SMS Recipients', icon: MessageSquare },
    { id: 'schedule', label: 'LED Schedule', icon: Sun },
    { id: 'system', label: 'System', icon: Monitor },
];

// ---------- Users Tab ----------
const usersData = ref<AppUser[]>(props.users ?? []);

// Create user form
const showCreateForm = ref(false);
const newUser = ref({ name: '', email: '', password: '', role: 'student', contact_number: '' });
const showNewPassword = ref(false);
const creating = ref(false);

async function createUser() {
    creating.value = true;
    try {
        const res = await axios.post('/api/users', newUser.value);
        usersData.value.push(res.data.user);
        newUser.value = { name: '', email: '', password: '', role: 'student', contact_number: '' };
        showCreateForm.value = false;
        toast.success('User created successfully.');
    } catch (e: any) {
        const errors = e.response?.data?.errors;
        if (errors) {
            toast.error(Object.values(errors).flat().join(' '));
        } else {
            toast.error('Failed to create user.');
        }
    } finally {
        creating.value = false;
    }
}

// Edit user inline
const editingId = ref<number | null>(null);
const editForm = ref<{ name: string; role: string; contact_number: string }>({ name: '', role: '', contact_number: '' });
const saving = ref(false);

function startEdit(user: AppUser) {
    editingId.value = user.id;
    editForm.value = { name: user.name, role: user.role, contact_number: user.contact_number ?? '' };
}

function cancelEdit() {
    editingId.value = null;
}

async function saveUser(user: AppUser) {
    saving.value = true;
    try {
        await axios.put(`/api/users/${user.id}`, editForm.value);
        const idx = usersData.value.findIndex((u) => u.id === user.id);
        if (idx >= 0) {
            usersData.value[idx] = { ...usersData.value[idx], ...editForm.value } as AppUser;
        }
        editingId.value = null;
        toast.success('User updated.');
    } catch {
        toast.error('Failed to update user.');
    } finally {
        saving.value = false;
    }
}

async function deactivateUser(user: AppUser) {
    if (!confirm(`Deactivate ${user.name}?`)) return;
    try {
        await axios.delete(`/api/users/${user.id}`);
        const idx = usersData.value.findIndex((u) => u.id === user.id);
        if (idx >= 0) usersData.value[idx].is_active = false;
        toast.success(`${user.name} deactivated.`);
    } catch (e: any) {
        toast.error(e.response?.data?.error ?? 'Failed to deactivate.');
    }
}

async function activateUser(user: AppUser) {
    try {
        await axios.patch(`/api/users/${user.id}/activate`);
        const idx = usersData.value.findIndex((u) => u.id === user.id);
        if (idx >= 0) usersData.value[idx].is_active = true;
        toast.success(`${user.name} activated.`);
    } catch {
        toast.error('Failed to activate.');
    }
}

const roleColors: Record<string, string> = {
    admin: 'bg-red-500/20 text-red-600 dark:text-red-400',
    faculty: 'bg-blue-500/20 text-blue-600 dark:text-blue-400',
    student: 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400',
};

// ---------- Settings Tab ----------
const settingsForm = ref<SystemSettingsType>({ ...props.settings });
const savingSettings = ref(false);

async function saveSettings() {
    savingSettings.value = true;
    try {
        await axios.put('/api/settings', settingsForm.value);
        toast.success('Settings saved.');
    } catch {
        toast.error('Failed to save settings.');
    } finally {
        savingSettings.value = false;
    }
}

function inputClass() {
    return 'w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50';
}
</script>

<template>
    <Head title="Settings" />

    <div class="relative flex h-full min-h-[calc(100vh-theme(spacing.16))] flex-1 flex-col bg-gradient-to-br from-primary/5 via-background to-secondary/10">
        <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6 z-10">

            <!-- Access Denied -->
            <div v-if="!isAdmin" class="flex flex-col items-center justify-center py-24 text-center">
                <ShieldX class="mb-4 h-16 w-16 text-destructive opacity-50" />
                <h2 class="text-2xl font-bold text-foreground">Access Denied</h2>
                <p class="mt-2 text-muted-foreground">This page is restricted to administrators only.</p>
            </div>

            <template v-else>
                <!-- Header -->
                <div>
                    <h1 class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent">
                        System Settings
                    </h1>
                    <p class="mt-1 text-muted-foreground">Manage users, thresholds, and system configuration.</p>
                </div>

                <!-- Tabs -->
                <div class="flex flex-wrap gap-2 rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        :id="`tab-${tab.id}`"
                        @click="activeTab = tab.id"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition-all"
                        :class="
                            activeTab === tab.id
                                ? 'bg-primary text-primary-foreground shadow-md'
                                : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                        "
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        {{ tab.label }}
                    </button>
                </div>

                <!-- ======= USER MANAGEMENT TAB ======= -->
                <div v-if="activeTab === 'users'" class="space-y-6">
                    <!-- Create user toggle -->
                    <div class="flex justify-end">
                        <button
                            id="btn-toggle-create-user"
                            @click="showCreateForm = !showCreateForm"
                            class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 active:scale-95"
                        >
                            <Plus class="h-4 w-4" />
                            Create User
                        </button>
                    </div>

                    <!-- Create user form -->
                    <div
                        v-if="showCreateForm"
                        class="rounded-2xl border border-primary/30 bg-card/80 backdrop-blur-md p-6 shadow-md"
                    >
                        <h2 class="mb-4 font-semibold text-foreground">New User</h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-1">
                                <label for="new-name" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Name</label>
                                <input id="new-name" v-model="newUser.name" type="text" :class="inputClass()" placeholder="Full name" />
                            </div>
                            <div class="space-y-1">
                                <label for="new-email" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Email</label>
                                <input id="new-email" v-model="newUser.email" type="email" :class="inputClass()" placeholder="user@cotsu.edu.ph" />
                            </div>
                            <div class="space-y-1">
                                <label for="new-password" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Password</label>
                                <div class="relative">
                                    <input
                                        id="new-password"
                                        v-model="newUser.password"
                                        :type="showNewPassword ? 'text' : 'password'"
                                        :class="inputClass()"
                                        placeholder="Min. 8 characters"
                                    />
                                    <button
                                        type="button"
                                        @click="showNewPassword = !showNewPassword"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    >
                                        <Eye v-if="!showNewPassword" class="h-4 w-4" />
                                        <EyeOff v-else class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label for="new-role" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Role</label>
                                <select id="new-role" v-model="newUser.role" :class="inputClass()">
                                    <option value="student">Student</option>
                                    <option value="faculty">Faculty</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="space-y-1 md:col-span-2">
                                <label for="new-contact" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Contact Number (optional)</label>
                                <input id="new-contact" v-model="newUser.contact_number" type="text" :class="inputClass()" placeholder="+63 912 345 6789" />
                            </div>
                        </div>
                        <div class="mt-4 flex gap-3">
                            <button
                                id="btn-create-user"
                                @click="createUser"
                                :disabled="creating"
                                class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:opacity-60"
                            >
                                <Check class="h-4 w-4" />
                                {{ creating ? 'Creating...' : 'Create User' }}
                            </button>
                            <button
                                @click="showCreateForm = false"
                                class="flex items-center gap-2 rounded-lg border border-border/50 px-4 py-2 text-sm font-medium text-muted-foreground hover:bg-muted/50"
                            >
                                <X class="h-4 w-4" />
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border/50 bg-muted/30">
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Role</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Contact</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Skeleton -->
                                    <tr v-if="!users">
                                        <td colspan="6" class="px-4 py-8 text-center">
                                            <div class="animate-pulse space-y-2 max-w-lg mx-auto">
                                                <div class="h-3 w-full rounded bg-muted"></div>
                                                <div class="h-3 w-3/4 rounded bg-muted"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr
                                        v-else
                                        v-for="user in usersData"
                                        :key="user.id"
                                        class="border-b border-border/30 transition-colors hover:bg-muted/20"
                                    >
                                        <td class="px-4 py-3">
                                            <div v-if="editingId === user.id">
                                                <input v-model="editForm.name" type="text" class="w-40 rounded-lg border border-primary/50 bg-background px-2 py-1 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-primary" />
                                            </div>
                                            <span v-else class="font-medium text-foreground">{{ user.name }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground">{{ user.email }}</td>
                                        <td class="px-4 py-3">
                                            <div v-if="editingId === user.id">
                                                <select v-model="editForm.role" class="rounded-lg border border-primary/50 bg-background px-2 py-1 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-primary">
                                                    <option value="student">Student</option>
                                                    <option value="faculty">Faculty</option>
                                                    <option value="admin">Admin</option>
                                                </select>
                                            </div>
                                            <span v-else class="rounded-full px-2 py-0.5 text-xs font-bold" :class="roleColors[user.role] ?? 'bg-muted text-muted-foreground'">
                                                {{ user.role }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground">
                                            <div v-if="editingId === user.id">
                                                <input v-model="editForm.contact_number" type="text" class="w-36 rounded-lg border border-primary/50 bg-background px-2 py-1 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-primary" />
                                            </div>
                                            <span v-else>{{ user.contact_number ?? '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-bold"
                                                :class="user.is_active ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-destructive/20 text-destructive'"
                                            >
                                                {{ user.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-2">
                                                <!-- Edit mode -->
                                                <template v-if="editingId === user.id">
                                                    <button @click="saveUser(user)" :disabled="saving"
                                                        class="flex items-center gap-1 rounded-lg bg-emerald-500/20 px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-500/30 dark:text-emerald-400 disabled:opacity-50">
                                                        <Check class="h-3 w-3" /> Save
                                                    </button>
                                                    <button @click="cancelEdit"
                                                        class="flex items-center gap-1 rounded-lg bg-muted px-2 py-1 text-xs font-medium text-muted-foreground hover:bg-muted/80">
                                                        <X class="h-3 w-3" /> Cancel
                                                    </button>
                                                </template>
                                                <template v-else>
                                                    <button :id="`btn-edit-user-${user.id}`" @click="startEdit(user)"
                                                        class="flex items-center gap-1 rounded-lg bg-primary/10 px-2 py-1 text-xs font-medium text-primary hover:bg-primary/20">
                                                        <Pencil class="h-3 w-3" /> Edit
                                                    </button>
                                                    <button v-if="user.is_active" :id="`btn-deactivate-${user.id}`" @click="deactivateUser(user)"
                                                        class="flex items-center gap-1 rounded-lg bg-destructive/10 px-2 py-1 text-xs font-medium text-destructive hover:bg-destructive/20">
                                                        <UserX class="h-3 w-3" /> Deactivate
                                                    </button>
                                                    <button v-else :id="`btn-activate-${user.id}`" @click="activateUser(user)"
                                                        class="flex items-center gap-1 rounded-lg bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-500/20 dark:text-emerald-400">
                                                        <UserCheck class="h-3 w-3" /> Activate
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="users && !usersData.length">
                                        <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">No users found.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ======= SENSOR THRESHOLDS TAB ======= -->
                <div v-if="activeTab === 'thresholds'" class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                    <div class="mb-6 flex items-center gap-2">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary shadow-inner">
                            <Sliders class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">SENSOR THRESHOLDS</h2>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <label for="thresh-temp-min" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Temperature Min (°C)</label>
                            <input id="thresh-temp-min" v-model="settingsForm.threshold_temperature_min" type="number" step="0.1" :class="inputClass()" placeholder="24" />
                        </div>
                        <div class="space-y-1">
                            <label for="thresh-temp-max" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Temperature Max (°C)</label>
                            <input id="thresh-temp-max" v-model="settingsForm.threshold_temperature_max" type="number" step="0.1" :class="inputClass()" placeholder="30" />
                        </div>
                        <div class="space-y-1">
                            <label for="thresh-hum-low" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Humidity Low — Humidifier ON (%)</label>
                            <input id="thresh-hum-low" v-model="settingsForm.threshold_humidity_low" type="number" step="1" :class="inputClass()" placeholder="80" />
                        </div>
                        <div class="space-y-1">
                            <label for="thresh-hum-high" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Humidity High — Humidifier OFF (%)</label>
                            <input id="thresh-hum-high" v-model="settingsForm.threshold_humidity_high" type="number" step="1" :class="inputClass()" placeholder="90" />
                        </div>
                        <div class="space-y-1">
                            <label for="thresh-co2" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">CO₂ Max — Fan ON (ppm)</label>
                            <input id="thresh-co2" v-model="settingsForm.threshold_co2_max" type="number" step="1" :class="inputClass()" placeholder="1000" />
                        </div>
                        <div class="space-y-1">
                            <label for="thresh-soil-warn" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Soil Warning (%)</label>
                            <input id="thresh-soil-warn" v-model="settingsForm.threshold_soil_warning" type="number" step="1" :class="inputClass()" placeholder="30" />
                        </div>
                        <div class="space-y-1">
                            <label for="thresh-soil-crit" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Soil Critical (%)</label>
                            <input id="thresh-soil-crit" v-model="settingsForm.threshold_soil_critical" type="number" step="1" :class="inputClass()" placeholder="20" />
                        </div>
                    </div>
                    <button
                        id="btn-save-thresholds"
                        @click="saveSettings"
                        :disabled="savingSettings"
                        class="mt-6 rounded-lg bg-primary px-6 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:opacity-60"
                    >
                        {{ savingSettings ? 'Saving...' : 'Save Thresholds' }}
                    </button>
                </div>

                <!-- ======= SMS RECIPIENTS TAB ======= -->
                <div v-if="activeTab === 'sms'" class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                    <div class="mb-6 flex items-center gap-2">
                        <div class="rounded-lg bg-blue-500/10 p-2 text-blue-500 shadow-inner">
                            <MessageSquare class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">SMS RECIPIENT NUMBERS</h2>
                    </div>
                    <div class="space-y-1">
                        <label for="sms-recipients" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Recipient Numbers (comma-separated)
                        </label>
                        <textarea
                            id="sms-recipients"
                            v-model="settingsForm.sms_recipients"
                            rows="4"
                            :class="inputClass()"
                            placeholder="+639123456789,+639987654321"
                        ></textarea>
                        <p class="text-xs text-muted-foreground">Enter numbers in E.164 format, separated by commas. Uses Semaphore API.</p>
                    </div>
                    <button
                        id="btn-save-sms"
                        @click="saveSettings"
                        :disabled="savingSettings"
                        class="mt-6 rounded-lg bg-primary px-6 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:opacity-60"
                    >
                        {{ savingSettings ? 'Saving...' : 'Save Recipients' }}
                    </button>
                </div>

                <!-- ======= LED SCHEDULE TAB ======= -->
                <div v-if="activeTab === 'schedule'" class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                    <div class="mb-6 flex items-center gap-2">
                        <div class="rounded-lg bg-yellow-500/10 p-2 text-yellow-500 shadow-inner">
                            <Sun class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">LED GROW LIGHT SCHEDULE</h2>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 max-w-md">
                        <div class="space-y-1">
                            <label for="sched-on" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">ON Hour (0–23)</label>
                            <input id="sched-on" v-model="settingsForm.led_on_hour" type="number" min="0" max="23" :class="inputClass()" placeholder="6" />
                        </div>
                        <div class="space-y-1">
                            <label for="sched-off" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">OFF Hour (0–23)</label>
                            <input id="sched-off" v-model="settingsForm.led_off_hour" type="number" min="0" max="23" :class="inputClass()" placeholder="18" />
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Lights ON at {{ settingsForm.led_on_hour ?? 6 }}:00 and OFF at {{ settingsForm.led_off_hour ?? 18 }}:00 daily (12h photoperiod).
                    </p>
                    <button
                        id="btn-save-schedule-settings"
                        @click="saveSettings"
                        :disabled="savingSettings"
                        class="mt-6 rounded-lg bg-primary px-6 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:opacity-60"
                    >
                        {{ savingSettings ? 'Saving...' : 'Save Schedule' }}
                    </button>
                </div>

                <!-- ======= SYSTEM TAB ======= -->
                <div v-if="activeTab === 'system'" class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                    <div class="mb-6 flex items-center gap-2">
                        <div class="rounded-lg bg-secondary/60 p-2 text-muted-foreground shadow-inner">
                            <Monitor class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">SYSTEM CONFIGURATION</h2>
                    </div>
                    <div class="max-w-md space-y-4">
                        <div class="space-y-1">
                            <label for="system-name" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">System Name</label>
                            <input id="system-name" v-model="settingsForm.system_name" type="text" :class="inputClass()" placeholder="IoT Mushroom Monitoring System" />
                        </div>
                    </div>
                    <button
                        id="btn-save-system"
                        @click="saveSettings"
                        :disabled="savingSettings"
                        class="mt-6 rounded-lg bg-primary px-6 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:opacity-60"
                    >
                        {{ savingSettings ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>
            </template>

        </div>
    </div>
</template>
