<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import axios from 'axios';
import type { CameraSnapshot, GrowingCycle } from '@/types';
import * as camera from '@/routes/camera';
import {
    Camera, Upload, X, Trash2, Filter, ChevronLeft, ChevronRight,
    ImageIcon, CalendarDays, FlaskConical, Loader2,
} from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Growth Photos', href: camera.index() }],
    },
});

const props = defineProps<{
    snapshots: CameraSnapshot[];
    cycles: Pick<GrowingCycle, 'id' | 'name' | 'status'>[];
    filters: { cycle_id?: number | null; date?: string | null };
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role ?? 'student');

// ── Filters ───────────────────────────────────────────────────────────────────
const filterCycle = ref<string>(props.filters.cycle_id ? String(props.filters.cycle_id) : '');
const filterDate  = ref<string>(props.filters.date ?? '');

function applyFilters() {
    router.get(
        camera.index.url(),
        {
            cycle_id: filterCycle.value || undefined,
            date: filterDate.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters() {
    filterCycle.value = '';
    filterDate.value  = '';
    applyFilters();
}

// ── Grouped snapshots by date ─────────────────────────────────────────────────
const groupedSnapshots = computed(() => {
    const map = new Map<string, CameraSnapshot[]>();
    for (const s of props.snapshots) {
        const key = s.captured_date ?? '';
        if (!map.has(key)) { map.set(key, []); }
        map.get(key)!.push(s);
    }
    return Array.from(map.entries())
        .sort(([a], [b]) => b.localeCompare(a))
        .map(([date, items]) => ({ date, items }));
});

// ── Upload form ───────────────────────────────────────────────────────────────
const showUpload = ref(false);
const uploading  = ref(false);
const uploadErrors = ref<Record<string, string>>({});

const uploadForm = ref({
    growing_cycle_id: props.filters.cycle_id ? String(props.filters.cycle_id) : '',
    flush_number: '1',
    captured_date: new Date().toISOString().split('T')[0],
    notes: '',
});
const photoFile   = ref<File | null>(null);
const photoPreview = ref<string | null>(null);
const dropActive  = ref(false);

function onFileSelected(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) { setFile(file); }
}

function setFile(file: File) {
    photoFile.value = file;
    const reader = new FileReader();
    reader.onload = (e) => { photoPreview.value = e.target?.result as string; };
    reader.readAsDataURL(file);
}

function onDrop(e: DragEvent) {
    dropActive.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file && file.type.startsWith('image/')) { setFile(file); }
}

function clearPhoto() {
    photoFile.value   = null;
    photoPreview.value = null;
}

async function submitUpload() {
    if (!photoFile.value) { toast.error('Please select a photo'); return; }
    uploadErrors.value = {};
    uploading.value = true;

    const fd = new FormData();
    fd.append('photo', photoFile.value);
    fd.append('growing_cycle_id', uploadForm.value.growing_cycle_id);
    fd.append('flush_number', uploadForm.value.flush_number);
    fd.append('captured_date', uploadForm.value.captured_date);
    if (uploadForm.value.notes) { fd.append('notes', uploadForm.value.notes); }

    try {
        await axios.post('/api/camera/upload', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success('Photo uploaded!');
        showUpload.value   = false;
        clearPhoto();
        router.reload({ only: ['snapshots'] });
    } catch (e: any) {
        if (e.response?.status === 422) { uploadErrors.value = e.response.data.errors ?? {}; }
        else { toast.error('Upload failed'); }
    } finally {
        uploading.value = false;
    }
}

// ── Delete snapshot ───────────────────────────────────────────────────────────
async function deleteSnapshot(id: number) {
    if (!confirm('Delete this photo permanently?')) { return; }
    try {
        await axios.delete(`/api/camera/${id}`);
        toast.success('Photo deleted');
        router.reload({ only: ['snapshots'] });
    } catch { toast.error('Delete failed'); }
}

// ── Lightbox ──────────────────────────────────────────────────────────────────
const lightboxSnap = ref<CameraSnapshot | null>(null);
const lightboxIdx  = ref(0);

function openLightbox(snap: CameraSnapshot) {
    lightboxSnap.value = snap;
    lightboxIdx.value  = props.snapshots.findIndex(s => s.id === snap.id);
}

function lightboxPrev() {
    if (lightboxIdx.value > 0) {
        lightboxIdx.value--;
        lightboxSnap.value = props.snapshots[lightboxIdx.value];
    }
}

function lightboxNext() {
    if (lightboxIdx.value < props.snapshots.length - 1) {
        lightboxIdx.value++;
        lightboxSnap.value = props.snapshots[lightboxIdx.value];
    }
}

function formatDate(d: string | null) {
    if (!d) { return '—'; }
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
}
</script>

<template>
    <Head title="Growth Photos" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">Growth Photos</h1>
                <p class="mt-1 text-sm text-slate-400">Daily mushroom documentation timeline</p>
            </div>
            <button
                v-if="userRole !== 'student'"
                id="upload-photo-btn"
                class="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-900/40 transition hover:bg-emerald-500 active:scale-95"
                @click="showUpload = true"
            >
                <Upload class="h-4 w-4" />
                Upload Photo
            </button>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-wrap items-end gap-4 rounded-2xl border border-slate-700/50 bg-slate-800/50 p-4 backdrop-blur-sm">
            <Filter class="h-4 w-4 shrink-0 text-slate-400" />
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-400">Growing Cycle</label>
                <select
                    v-model="filterCycle"
                    class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none"
                    @change="applyFilters"
                >
                    <option value="">All Cycles</option>
                    <option v-for="c in cycles" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-400">Date</label>
                <input
                    v-model="filterDate"
                    type="date"
                    class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none"
                    style="color-scheme: dark"
                    @change="applyFilters"
                />
            </div>
            <button
                v-if="filterCycle || filterDate"
                class="flex items-center gap-1.5 rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-400 transition hover:text-white"
                @click="clearFilters"
            >
                <X class="h-3.5 w-3.5" /> Clear
            </button>
            <div class="ml-auto text-sm text-slate-400">
                {{ snapshots.length }} photo{{ snapshots.length !== 1 ? 's' : '' }}
            </div>
        </div>

        <!-- Timeline -->
        <div v-if="snapshots.length === 0" class="flex flex-col items-center gap-4 rounded-2xl border border-slate-700/50 bg-slate-800/50 py-20 text-center backdrop-blur-sm">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-700/60">
                <Camera class="h-8 w-8 text-slate-500" />
            </div>
            <div>
                <p class="font-semibold text-white">No photos found</p>
                <p class="mt-1 text-sm text-slate-400">Upload daily growth photos to start documenting.</p>
            </div>
            <button
                v-if="userRole !== 'student'"
                class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                @click="showUpload = true"
            >
                Upload First Photo
            </button>
        </div>

        <div v-else class="space-y-8">
            <div v-for="group in groupedSnapshots" :key="group.date" class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-5 backdrop-blur-sm">
                <div class="mb-4 flex items-center gap-2">
                    <CalendarDays class="h-4 w-4 text-emerald-400" />
                    <h2 class="font-semibold text-white">{{ formatDate(group.date) }}</h2>
                    <span class="rounded-full bg-slate-700 px-2 py-0.5 text-xs text-slate-400">{{ group.items.length }} photo{{ group.items.length !== 1 ? 's' : '' }}</span>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                    <div
                        v-for="snap in group.items"
                        :key="snap.id"
                        class="group relative aspect-square cursor-pointer overflow-hidden rounded-xl bg-slate-900 shadow-lg"
                        @click="openLightbox(snap)"
                    >
                        <img
                            :src="`/storage/${snap.file_path}`"
                            :alt="snap.file_name"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                        />
                        <!-- Overlay -->
                        <div class="absolute inset-0 flex flex-col justify-end bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 p-2 transition duration-200 group-hover:opacity-100">
                            <span v-if="snap.flush_number" class="text-xs font-bold text-white">Flush {{ snap.flush_number }}</span>
                            <span v-if="snap.notes" class="mt-0.5 line-clamp-2 text-xs text-slate-300">{{ snap.notes }}</span>
                        </div>
                        <button
                            v-if="userRole !== 'student'"
                            class="absolute right-1.5 top-1.5 rounded-lg bg-red-500/90 p-1.5 opacity-0 shadow-lg transition group-hover:opacity-100"
                            @click.stop="deleteSnapshot(snap.id)"
                        >
                            <Trash2 class="h-3 w-3 text-white" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div
                v-if="showUpload"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background: rgba(0,0,0,0.75);"
                @click.self="showUpload = false"
            >
                <div class="w-full max-w-lg rounded-2xl border border-slate-700/50 bg-slate-900 shadow-2xl">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-700/50 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/10">
                                <Camera class="h-5 w-5 text-emerald-400" />
                            </div>
                            <h2 class="text-lg font-semibold text-white">Upload Growth Photo</h2>
                        </div>
                        <button class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-700 hover:text-white" @click="showUpload = false">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Form -->
                    <form class="space-y-4 p-6" @submit.prevent="submitUpload">
                        <!-- Cycle + Flush -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Growing Cycle <span class="text-red-400">*</span></label>
                                <select
                                    v-model="uploadForm.growing_cycle_id"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none"
                                >
                                    <option value="">Select cycle...</option>
                                    <option v-for="c in cycles" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                                </select>
                                <p v-if="uploadErrors.growing_cycle_id" class="mt-1 text-xs text-red-400">{{ uploadErrors.growing_cycle_id }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Flush # <span class="text-red-400">*</span></label>
                                <input v-model="uploadForm.flush_number" type="number" min="1" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none" />
                                <p v-if="uploadErrors.flush_number" class="mt-1 text-xs text-red-400">{{ uploadErrors.flush_number }}</p>
                            </div>
                        </div>

                        <!-- Date -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-300">Capture Date <span class="text-red-400">*</span></label>
                            <input v-model="uploadForm.captured_date" type="date" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none" style="color-scheme: dark" />
                        </div>

                        <!-- Photo dropzone -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-300">Photo <span class="text-red-400">*</span></label>
                            <div v-if="!photoPreview">
                                <label
                                    class="flex cursor-pointer flex-col items-center gap-3 rounded-xl border-2 border-dashed p-8 text-center transition"
                                    :class="dropActive ? 'border-emerald-500 bg-emerald-500/5' : 'border-slate-700 hover:border-slate-500'"
                                    @dragover.prevent="dropActive = true"
                                    @dragleave="dropActive = false"
                                    @drop.prevent="onDrop"
                                >
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-700/60">
                                        <ImageIcon class="h-6 w-6 text-slate-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-300">Drop photo here or click to browse</p>
                                        <p class="mt-1 text-xs text-slate-500">JPG, PNG, WEBP up to 8MB</p>
                                    </div>
                                    <input type="file" class="hidden" accept="image/*" @change="onFileSelected" />
                                </label>
                            </div>
                            <div v-else class="relative overflow-hidden rounded-xl">
                                <img :src="photoPreview" alt="Preview" class="max-h-48 w-full object-cover" />
                                <button
                                    type="button"
                                    class="absolute right-2 top-2 rounded-lg bg-red-500/90 p-1.5"
                                    @click="clearPhoto"
                                >
                                    <X class="h-4 w-4 text-white" />
                                </button>
                            </div>
                            <p v-if="uploadErrors.photo" class="mt-1 text-xs text-red-400">{{ uploadErrors.photo }}</p>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-300">Notes</label>
                            <textarea v-model="uploadForm.notes" rows="2" placeholder="Observations about this photo..." class="w-full resize-none rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none" />
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="button" class="flex-1 rounded-xl border border-slate-700 py-2.5 text-sm text-slate-300 transition hover:bg-slate-800" @click="showUpload = false">Cancel</button>
                            <button type="submit" :disabled="uploading" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-60">
                                <Loader2 v-if="uploading" class="h-4 w-4 animate-spin" />
                                {{ uploading ? 'Uploading...' : 'Upload Photo' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Lightbox -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div
                v-if="lightboxSnap"
                class="fixed inset-0 z-50 flex items-center justify-center"
                style="background: rgba(0,0,0,0.93);"
                @click="lightboxSnap = null"
            >
                <button class="absolute right-4 top-4 rounded-full bg-slate-800/80 p-2 text-white backdrop-blur-sm hover:bg-slate-700" @click="lightboxSnap = null">
                    <X class="h-5 w-5" />
                </button>
                <button
                    class="absolute left-4 rounded-full bg-slate-800/80 p-2 text-white hover:bg-slate-700 disabled:opacity-30"
                    :disabled="lightboxIdx === 0"
                    @click.stop="lightboxPrev"
                >
                    <ChevronLeft class="h-5 w-5" />
                </button>
                <div class="flex max-h-screen max-w-5xl flex-col items-center gap-3 p-4" @click.stop>
                    <img :src="`/storage/${lightboxSnap.file_path}`" :alt="lightboxSnap.file_name" class="max-h-[80vh] rounded-2xl object-contain shadow-2xl" />
                    <div class="text-center">
                        <p class="text-sm font-semibold text-white">{{ formatDate(lightboxSnap.captured_date ?? null) }}</p>
                        <p v-if="lightboxSnap.flush_number" class="text-xs text-slate-400">Flush {{ lightboxSnap.flush_number }}</p>
                        <p v-if="lightboxSnap.notes" class="mt-1 text-sm text-slate-300">{{ lightboxSnap.notes }}</p>
                    </div>
                    <p class="text-xs text-slate-500">{{ lightboxIdx + 1 }} / {{ snapshots.length }}</p>
                </div>
                <button
                    class="absolute right-4 rounded-full bg-slate-800/80 p-2 text-white hover:bg-slate-700 disabled:opacity-30"
                    :disabled="lightboxIdx === snapshots.length - 1"
                    @click.stop="lightboxNext"
                >
                    <ChevronRight class="h-5 w-5" />
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
