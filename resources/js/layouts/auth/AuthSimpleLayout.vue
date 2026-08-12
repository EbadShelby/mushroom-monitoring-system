<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="min-h-svh bg-gradient-to-br from-primary/5 via-background to-secondary/10 lg:grid lg:grid-cols-2"
    >
        <!-- Left panel: Branding / Hero -->
        <div
            class="relative hidden flex-col justify-between overflow-hidden bg-emerald-950 p-10 text-white lg:flex"
        >
            <!-- Background pattern -->
            <div class="absolute inset-0 opacity-10">
                <svg
                    width="100%"
                    height="100%"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <defs>
                        <pattern
                            id="hex"
                            x="0"
                            y="0"
                            width="60"
                            height="52"
                            patternUnits="userSpaceOnUse"
                        >
                            <polygon
                                points="30,2 58,17 58,35 30,50 2,35 2,17"
                                fill="none"
                                stroke="white"
                                stroke-width="1"
                            />
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#hex)" />
                </svg>
            </div>

            <!-- Glow blobs -->
            <div
                class="absolute -top-24 -left-24 size-96 rounded-full bg-emerald-500/20 blur-3xl"
            />
            <div
                class="absolute -right-12 -bottom-24 size-80 rounded-full bg-teal-400/15 blur-3xl"
            />

            <!-- Top: Logo + school -->
            <div class="relative z-10 flex items-center gap-3">
                <div
                    class="flex size-10 items-center justify-center rounded-xl bg-emerald-700/60 backdrop-blur"
                >
                    <AppLogoIcon class="size-6 fill-current text-white" />
                </div>
                <div>
                    <p class="text-sm font-bold tracking-wide">MycoSense</p>
                    <p
                        class="text-[11px] tracking-widest text-emerald-300/70 uppercase"
                    >
                        CotSU · IoT System
                    </p>
                </div>
            </div>

            <!-- Middle: Main text -->
            <div class="relative z-10 space-y-4">
                <div
                    class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs text-emerald-300"
                >
                    <span
                        class="size-1.5 animate-pulse rounded-full bg-emerald-400"
                    />
                    Real-time Environmental Monitoring
                </div>
                <h2
                    class="text-3xl leading-tight font-extrabold tracking-tight"
                >
                    IoT-Based Cultivation<br />
                    <span class="text-emerald-400">Control System</span>
                </h2>
                <p class="max-w-xs text-sm leading-relaxed text-emerald-200/70">
                    Monitor temperature, humidity, CO₂, light, and soil moisture
                    conditions for
                    <em>Pleurotus sajor-caju</em> cultivation in real-time.
                </p>

                <!-- Sensor stat pills -->
                <div class="flex flex-wrap gap-2 pt-2">
                    <span
                        class="rounded-full bg-emerald-800/60 px-3 py-1 text-xs text-emerald-300"
                        >🌡 Temperature</span
                    >
                    <span
                        class="rounded-full bg-emerald-800/60 px-3 py-1 text-xs text-emerald-300"
                        >💧 Humidity</span
                    >
                    <span
                        class="rounded-full bg-emerald-800/60 px-3 py-1 text-xs text-emerald-300"
                        >🌬 CO₂</span
                    >
                    <span
                        class="rounded-full bg-emerald-800/60 px-3 py-1 text-xs text-emerald-300"
                        >☀️ Light</span
                    >
                    <span
                        class="rounded-full bg-emerald-800/60 px-3 py-1 text-xs text-emerald-300"
                        >🌱 Soil</span
                    >
                </div>
            </div>

            <!-- Bottom: Institution -->
            <p class="relative z-10 text-xs text-emerald-300/50">
                Cotabato State University · BS Agriculture Program
            </p>
        </div>

        <!-- Right panel: Login form -->
        <div
            class="relative z-10 flex flex-col items-center justify-center overflow-hidden px-6 py-12 md:px-12"
        >
            <!-- Subtle decorative blobs for right panel -->
            <div
                class="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
            >
                <div
                    class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-primary/10 blur-3xl"
                ></div>
                <div
                    class="absolute bottom-0 left-0 h-96 w-96 rounded-full bg-secondary/10 blur-3xl"
                ></div>
            </div>

            <!-- Mobile logo (only shows on small screens) -->
            <div class="mb-8 flex flex-col items-center gap-2 lg:hidden">
                <div
                    class="flex size-12 items-center justify-center rounded-xl bg-emerald-700 shadow"
                >
                    <AppLogoIcon class="size-7 fill-current text-white" />
                </div>
                <p class="text-sm font-bold tracking-wide">MycoSense</p>
                <p class="text-xs text-muted-foreground">
                    CotSU IoT Monitoring System
                </p>
            </div>

            <div
                class="w-full max-w-md rounded-3xl border border-border/50 bg-card/60 p-8 shadow-xl backdrop-blur-xl transition-all duration-300 hover:shadow-2xl sm:p-10"
            >
                <div class="mb-8 text-center lg:text-left">
                    <h1 class="text-2xl font-bold tracking-tight">
                        Sign in to your account
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{
                            description ||
                            'Enter your credentials to access the dashboard'
                        }}
                    </p>
                </div>

                <slot />
            </div>

            <p class="mt-8 text-center text-xs text-muted-foreground/60">
                © {{ new Date().getFullYear() }} Cotabato State University. All
                rights reserved.
            </p>
        </div>
    </div>
</template>
