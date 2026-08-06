<script setup lang="ts">
import PortalLayout from '@/layouts/PortalLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    ArrowRight,
    Calculator,
    CreditCard,
    FileSpreadsheet,
    FileText,
    GitCompare,
    Layers,
    LayoutGrid,
    Settings,
    Shield,
    Table2,
    Users,
    Wrench,
} from 'lucide-vue-next';
import type { Component } from 'vue';

type PortalModule = {
    key: string;
    label: string;
    description: string;
    accent: string;
    icon: string;
    entry_url: string;
};

type AdminLink = {
    label: string;
    href: string;
};

defineProps<{
    modules: PortalModule[];
    adminLinks?: AdminLink[];
}>();

const page = usePage();
const userName = computed(() => (page.props.auth as { user?: { name?: string } })?.user?.name ?? '');

const iconMap: Record<string, Component> = {
    'layout-grid': LayoutGrid,
    'file-text': FileText,
    calculator: Calculator,
    'table-2': Table2,
    layers: Layers,
    'credit-card': CreditCard,
    'file-spreadsheet': FileSpreadsheet,
    'git-compare': GitCompare,
    settings: Settings,
};

const accentStyles: Record<string, { ring: string; icon: string; hover: string }> = {
    rose: {
        ring: 'ring-rose-200/80',
        icon: 'bg-rose-50 text-rose-600',
        hover: 'hover:border-rose-300/80 hover:bg-rose-50/40',
    },
    emerald: {
        ring: 'ring-emerald-200/80',
        icon: 'bg-emerald-50 text-emerald-600',
        hover: 'hover:border-emerald-300/80 hover:bg-emerald-50/40',
    },
    amber: {
        ring: 'ring-amber-200/80',
        icon: 'bg-amber-50 text-amber-600',
        hover: 'hover:border-amber-300/80 hover:bg-amber-50/40',
    },
    blue: {
        ring: 'ring-blue-200/80',
        icon: 'bg-blue-50 text-blue-600',
        hover: 'hover:border-blue-300/80 hover:bg-blue-50/40',
    },
    violet: {
        ring: 'ring-violet-200/80',
        icon: 'bg-violet-50 text-violet-600',
        hover: 'hover:border-violet-300/80 hover:bg-violet-50/40',
    },
    orange: {
        ring: 'ring-orange-200/80',
        icon: 'bg-orange-50 text-orange-600',
        hover: 'hover:border-orange-300/80 hover:bg-orange-50/40',
    },
    cyan: {
        ring: 'ring-cyan-200/80',
        icon: 'bg-cyan-50 text-cyan-700',
        hover: 'hover:border-cyan-300/80 hover:bg-cyan-50/40',
    },
    slate: {
        ring: 'ring-slate-200/80',
        icon: 'bg-slate-100 text-slate-600',
        hover: 'hover:border-slate-300/80 hover:bg-slate-50/60',
    },
};

function styleFor(accent: string) {
    return accentStyles[accent] ?? accentStyles.slate;
}

function resolveIcon(name: string) {
    return iconMap[name] ?? LayoutGrid;
}

function adminIcon(label: string) {
    if (label.includes('Utilisateur')) return Users;
    if (label.includes('Rôle')) return Shield;
    return Wrench;
}
</script>

<template>
    <Head title="Modules — COFI COMPTA" />

    <PortalLayout>
        <div class="relative flex min-h-full flex-col">
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(ellipse_at_top_left,rgba(var(--primary)/0.08),transparent_55%),linear-gradient(to_bottom,rgb(248_250_252),transparent)]"
                aria-hidden="true"
            />

            <!-- Plein largeur utile de l’écran -->
            <div class="relative flex w-full flex-1 flex-col gap-7 px-4 py-5 sm:px-6 lg:gap-8 lg:px-8 xl:px-10 2xl:px-12">
                <header
                    class="flex flex-col gap-4 border-b border-border/60 pb-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div class="min-w-0 space-y-1.5">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-primary">
                            Espace de travail
                        </p>
                        <h1 class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                            COFI COMPTA
                        </h1>
                        <p class="text-sm text-muted-foreground sm:text-base">
                            Choisissez un module pour accéder à vos outils métier.
                        </p>
                    </div>

                    <div
                        v-if="userName"
                        class="flex shrink-0 items-center gap-3 self-start rounded-lg border border-border bg-card px-3.5 py-2 text-sm sm:self-auto"
                    >
                        <span
                            class="flex size-8 items-center justify-center rounded-full bg-primary/10 text-[11px] font-bold text-primary"
                        >
                            {{
                                userName
                                    .split(/\s+/)
                                    .filter(Boolean)
                                    .slice(0, 2)
                                    .map((p) => p[0]?.toUpperCase() ?? '')
                                    .join('')
                            }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-[11px] text-muted-foreground">Connecté en tant que</p>
                            <p class="truncate font-semibold text-foreground">{{ userName }}</p>
                        </div>
                    </div>
                </header>

                <section v-if="modules.length" class="flex flex-1 flex-col gap-3.5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Modules disponibles
                        </h2>
                        <span class="text-xs tabular-nums text-muted-foreground">
                            {{ modules.length }} module{{ modules.length > 1 ? 's' : '' }}
                        </span>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 min-[1600px]:grid-cols-4"
                    >
                        <Link
                            v-for="module in modules"
                            :key="module.key"
                            :href="module.entry_url"
                            class="group flex h-full min-h-[7.5rem] items-start gap-3.5 rounded-xl border border-border bg-card/90 p-4 transition-colors duration-150"
                            :class="styleFor(module.accent).hover"
                        >
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg ring-1"
                                :class="[styleFor(module.accent).icon, styleFor(module.accent).ring]"
                            >
                                <component :is="resolveIcon(module.icon)" class="size-5" />
                            </div>

                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-[0.95rem] font-semibold leading-snug text-foreground">
                                        {{ module.label }}
                                    </h3>
                                    <ArrowRight
                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground"
                                    />
                                </div>
                                <p class="text-sm leading-relaxed text-muted-foreground">
                                    {{ module.description }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </section>

                <section
                    v-else
                    class="rounded-xl border border-dashed border-border bg-muted/20 px-6 py-16 text-center"
                >
                    <LayoutGrid class="mx-auto mb-3 size-10 text-muted-foreground/60" />
                    <p class="font-medium text-foreground">Aucun module disponible</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Votre profil ne donne accès à aucun module. Contactez l'administrateur.
                    </p>
                </section>

                <section v-if="adminLinks?.length" class="mt-auto border-t border-border/70 pt-6">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                            Administration plateforme
                        </p>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <Link
                            v-for="link in adminLinks"
                            :key="link.href"
                            :href="link.href"
                            class="flex items-center gap-3 rounded-lg border border-border bg-card px-4 py-3 text-sm font-medium text-foreground transition-colors hover:border-primary/40 hover:bg-primary/[0.04]"
                        >
                            <component :is="adminIcon(link.label)" class="size-4 shrink-0 text-primary" />
                            <span class="min-w-0 truncate">{{ link.label }}</span>
                            <ArrowRight class="ml-auto size-3.5 shrink-0 text-muted-foreground" />
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </PortalLayout>
</template>
