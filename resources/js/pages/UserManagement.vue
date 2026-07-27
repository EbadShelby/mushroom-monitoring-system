<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import type { AppUser } from '@/types';
import {
    Users, Plus, X, Eye, EyeOff, ShieldX, Check, Pencil, UserX, UserCheck
} from '@lucide/vue';
import * as usersApi from '@/routes/users';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'User Management', href: usersApi.index() }],
    },
});

const props = defineProps<{
    users?: AppUser[];
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role ?? 'student');
const isAdmin = computed(() => userRole.value === 'admin');

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

function inputClass() {
    return 'w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50';
}
</script>

<template>
    <Head title="User Management" />

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
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent">
                            User Management
                        </h1>
                        <p class="mt-1 text-muted-foreground">Manage system users, roles, and access.</p>
                    </div>
                    <button
                        @click="showCreateForm = !showCreateForm"
                        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-lg shadow-primary/20 transition-all hover:bg-primary/90 active:scale-95"
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
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Name</label>
                            <input v-model="newUser.name" type="text" :class="inputClass()" placeholder="Full name" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Email</label>
                            <input v-model="newUser.email" type="email" :class="inputClass()" placeholder="user@cotsu.edu.ph" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Password</label>
                            <div class="relative">
                                <input
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
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Role</label>
                            <select v-model="newUser.role" :class="inputClass()">
                                <option value="admin">Admin</option>
                                <option value="faculty">Faculty</option>
                                <option value="student">Student</option>
                            </select>
                        </div>
                        <div class="space-y-1 md:col-span-2">
                            <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Contact Number (Optional)</label>
                            <input v-model="newUser.contact_number" type="text" :class="inputClass()" placeholder="09XX XXX XXXX" />
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            @click="showCreateForm = false"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-muted-foreground hover:bg-muted"
                        >
                            Cancel
                        </button>
                        <button
                            @click="createUser"
                            :disabled="creating"
                            class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                        >
                            {{ creating ? 'Creating...' : 'Save User' }}
                        </button>
                    </div>
                </div>

                <!-- Users List -->
                <div class="overflow-x-auto rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md">
                    <table class="w-full text-left text-sm text-muted-foreground">
                        <thead class="bg-muted/50 text-xs uppercase text-foreground">
                            <tr>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="px-4 py-3">Role</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/50">
                            <tr v-for="u in usersData" :key="u.id" class="hover:bg-muted/30">
                                <td class="px-4 py-3">
                                    <template v-if="editingId === u.id">
                                        <input v-model="editForm.name" type="text" :class="inputClass()" class="!py-1" />
                                    </template>
                                    <span v-else class="font-medium text-foreground">{{ u.name }}</span>
                                </td>
                                <td class="px-4 py-3">{{ u.email }}</td>
                                <td class="px-4 py-3">
                                    <template v-if="editingId === u.id">
                                        <select v-model="editForm.role" :class="inputClass()" class="!py-1">
                                            <option value="admin">Admin</option>
                                            <option value="faculty">Faculty</option>
                                            <option value="student">Student</option>
                                        </select>
                                    </template>
                                    <span v-else class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="roleColors[u.role]">
                                        {{ u.role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <template v-if="editingId === u.id">
                                        <input v-model="editForm.contact_number" type="text" :class="inputClass()" class="!py-1" />
                                    </template>
                                    <span v-else>{{ u.contact_number || '—' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="u.is_active" class="flex items-center gap-1 text-xs text-emerald-500">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                    <span v-else class="flex items-center gap-1 text-xs text-muted-foreground">
                                        <span class="h-2 w-2 rounded-full bg-muted-foreground"></span> Inactive
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div v-if="editingId === u.id" class="flex justify-end gap-1">
                                        <button @click="saveUser(u)" :disabled="saving" class="rounded p-1 text-emerald-500 hover:bg-emerald-500/10">
                                            <Check class="h-4 w-4" />
                                        </button>
                                        <button @click="cancelEdit" class="rounded p-1 text-muted-foreground hover:bg-muted">
                                            <X class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <div v-else class="flex justify-end gap-1">
                                        <button @click="startEdit(u)" class="rounded p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground">
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="u.is_active"
                                            @click="deactivateUser(u)"
                                            title="Deactivate"
                                            class="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                        >
                                            <UserX class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-else
                                            @click="activateUser(u)"
                                            title="Activate"
                                            class="rounded p-1.5 text-muted-foreground hover:bg-emerald-500/10 hover:text-emerald-500"
                                        >
                                            <UserCheck class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </div>
</template>
