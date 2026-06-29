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
    settings: Settings,
};

const accentStyles: Record<string, { ring: string; icon: string; hover: string }> = {
    rose: {
        ring: 'ring-rose-200/80',
        icon: 'bg-rose-50 text-rose-600',
        hover: 'hover:border-rose-200 hover:shadow-rose-100/50',
    },
    emerald: {
        ring: 'ring-emerald-200/80',
        icon: 'bg-emerald-50 text-emerald-600',
        hover: 'hover:border-emerald-200 hover:shadow-emerald-100/50',
    },
    amber: {
        ring: 'ring-amber-200/80',
        icon: 'bg-amber-50 text-amber-600',
        hover: 'hover:border-amber-200 hover:shadow-amber-100/50',
    },
    blue: {
        ring: 'ring-blue-200/80',
        icon: 'bg-blue-50 text-blue-600',
        hover: 'hover:border-blue-200 hover:shadow-blue-100/50',
    },
    violet: {
        ring: 'ring-violet-200/80',
        icon: 'bg-violet-50 text-violet-600',
        hover: 'hover:border-violet-200 hover:shadow-violet-100/50',
    },
    orange: {
        ring: 'ring-orange-200/80',
        icon: 'bg-orange-50 text-orange-600',
        hover: 'hover:border-orange-200 hover:shadow-orange-100/50',
    },
    slate: {
        ring: 'ring-slate-200/80',
        icon: 'bg-slate-100 text-slate-600',
        hover: 'hover:border-slate-200 hover:shadow-slate-100/50',
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
        <div class="relative min-h-full">
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-56 bg-gradient-to-b from-primary/5 via-primary/[0.02] to-transparent"
                aria-hidden="true"
            />

            <div class="relative mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 py-8 md:px-8 md:py-10">
                <header class="flex flex-col gap-4 border-b border-border/60 pb-8 md:flex-row md:items-end md:justify-between">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">
                            Espace de travail
                        </p>
                        <h1 class="text-3xl font-bold tracking-tight text-foreground md:text-4xl">
                            COFI COMPTA
                        </h1>
                        <p class="max-w-xl text-base text-muted-foreground">
                            Choisissez un module pour accéder à vos outils métier.
                        </p>
                    </div>

                    <div v-if="userName" class="rounded-xl border border-border bg-card/80 px-4 py-3 text-sm shadow-xs backdrop-blur-sm">
                        <p class="text-muted-foreground">Connecté en tant que</p>
                        <p class="font-semibold text-foreground">{{ userName }}</p>
                    </div>
                </header>

                <section v-if="modules.length" class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Modules disponibles
                        </h2>
                        <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                            {{ modules.length }} module{{ modules.length > 1 ? 's' : '' }}
                        </span>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <Link
                            v-for="module in modules"
                            :key="module.key"
                            :href="module.entry_url"
                            class="group flex items-start gap-4 rounded-2xl border border-border bg-card p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                            :class="styleFor(module.accent).hover"
                        >
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl ring-1"
                                :class="[styleFor(module.accent).icon, styleFor(module.accent).ring]"
                            >
                                <component :is="resolveIcon(module.icon)" class="size-5" />
                            </div>

                            <div class="min-w-0 flex-1 space-y-1">
                                <h3 class="text-base font-semibold text-foreground">
                                    {{ module.label }}
                                </h3>
                                <p class="text-sm leading-relaxed text-muted-foreground">
                                    {{ module.description }}
                                </p>
                            </div>

                            <span
                                class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground transition-colors group-hover:bg-primary group-hover:text-primary-foreground"
                            >
                                <ArrowRight class="size-4" />
                            </span>
                        </Link>
                    </div>
                </section>

                <section
                    v-else
                    class="rounded-2xl border border-dashed border-border bg-muted/20 px-6 py-16 text-center"
                >
                    <LayoutGrid class="mx-auto mb-3 size-10 text-muted-foreground/60" />
                    <p class="font-medium text-foreground">Aucun module disponible</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Votre profil ne donne accès à aucun module. Contactez l'administrateur.
                    </p>
                </section>

                <section
                    v-if="adminLinks?.length"
                    class="rounded-2xl border border-border bg-muted/15 p-5 md:p-6"
                >
                    <p class="mb-4 text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                        Administration plateforme
                    </p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <Link
                            v-for="link in adminLinks"
                            :key="link.href"
                            :href="link.href"
                            class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 text-sm font-medium text-foreground shadow-xs transition-colors hover:border-primary/30 hover:bg-primary/5"
                        >
                            <component :is="adminIcon(link.label)" class="size-4 text-primary" />
                            {{ link.label }}
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </PortalLayout>
</template>
