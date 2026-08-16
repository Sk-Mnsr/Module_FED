<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    CalendarDays,
    Clock,
    FileSearch,
    FileSpreadsheet,
    FileText,
    Hash,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    User,
    UserCheck,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type ClasseurRow = {
    id: number;
    nom_classeur: string;
    numero_batch: string;
    date_valeur: string | null;
    maker_name: string | null;
    checker_name: string | null;
    integrated_at: string | null;
    justificatifs_count: number;
    resume_url: string;
    can_validate: boolean;
    valider_checker_url: string;
};

const props = defineProps<{
    classeurs?: ClasseurRow[];
    filters?: { q?: string; nom_classeur?: string; numero_batch?: string };
    canViewAllAgents?: boolean;
}>();

const breadcrumbs = [
    { title: 'Opérations diverses', href: '/operations-diverses/piece-comptable' },
    { title: 'Intégration', href: '/operations-diverses/integrations' },
    { title: 'En attente de validation', href: '/operations-diverses/attente-validation' },
];

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);
const validerEnCours = ref<number | null>(null);
const showFilters = ref(false);

const fieldClass =
    'h-10 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground dark:placeholder:text-slate-500';

const searchForm = ref({
    q: props.filters?.q ?? '',
    nom_classeur: props.filters?.nom_classeur ?? '',
    numero_batch: props.filters?.numero_batch ?? '',
});

const hasActiveFilters = computed(() => Object.values(searchForm.value).some(Boolean));
const total = computed(() => props.classeurs?.length ?? 0);
const aValider = computed(() => props.classeurs?.filter((c) => c.can_validate).length ?? 0);

function applySearch() {
    const params: Record<string, string> = {};
    Object.entries(searchForm.value).forEach(([k, v]) => {
        if (v) params[k] = v;
    });
    router.get('/operations-diverses/attente-validation', params, {
        preserveScroll: true,
        preserveState: true,
    });
}

function resetSearch() {
    searchForm.value = { q: '', nom_classeur: '', numero_batch: '' };
    router.get('/operations-diverses/attente-validation', {}, { preserveScroll: true });
}

function valider(c: ClasseurRow) {
    if (validerEnCours.value !== null || !c.can_validate) return;
    if (!window.confirm(`Valider et archiver « ${c.nom_classeur} » ?`)) return;
    validerEnCours.value = c.id;
    router.post(
        c.valider_checker_url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                validerEnCours.value = null;
            },
        },
    );
}

function dateFmt(iso: string | null): string {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

function horodatage(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="En attente de validation — OD" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div
                v-if="flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.warning"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                {{ flash.warning }}
            </div>

            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div
                    class="border-b border-border/80 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-5 sm:px-6 dark:from-primary/10"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
                            >
                                <UserCheck class="size-5" />
                            </div>
                            <div>
                                <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                    En attente de validation
                                </h1>
                                <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                                    Intégrations transmises, en attente de validation checker (4
                                    yeux).
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1.5 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200"
                            >
                                <span class="font-semibold tabular-nums">{{ total }}</span>
                                <span>en attente</span>
                            </div>
                            <div
                                v-if="aValider > 0"
                                class="inline-flex items-center gap-2 rounded-full border border-primary/25 bg-primary/5 px-3 py-1.5 text-sm text-primary"
                            >
                                <span class="font-semibold tabular-nums">{{ aValider }}</span>
                                <span>à traiter par vous</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recherche -->
                <div class="space-y-3 border-b border-border/80 p-5 sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative flex-1">
                            <Search
                                class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <Input
                                v-model="searchForm.q"
                                :class="[fieldClass, 'pl-9']"
                                placeholder="Rechercher classeur, batch, maker…"
                                @keydown.enter="applySearch"
                            />
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Button
                                variant="outline"
                                class="h-10 border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-foreground"
                                @click="showFilters = !showFilters"
                            >
                                <SlidersHorizontal class="size-4" />
                                Filtres
                                <span
                                    v-if="hasActiveFilters"
                                    class="ml-0.5 size-1.5 rounded-full bg-primary"
                                />
                            </Button>
                            <Button
                                class="h-10 bg-primary text-primary-foreground hover:bg-primary/90"
                                @click="applySearch"
                            >
                                Rechercher
                            </Button>
                        </div>
                    </div>

                    <div
                        v-show="showFilters || hasActiveFilters"
                        class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4 sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-700 dark:bg-muted/20"
                    >
                        <Input
                            v-model="searchForm.nom_classeur"
                            placeholder="Nom du classeur"
                            :class="fieldClass"
                        />
                        <Input
                            v-model="searchForm.numero_batch"
                            placeholder="N° batch"
                            :class="fieldClass"
                        />
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            class="h-10 justify-start text-muted-foreground"
                            @click="resetSearch"
                        >
                            <X class="size-4" /> Effacer les filtres
                        </Button>
                    </div>
                </div>

                <!-- Liste -->
                <div class="px-5 py-4 sm:px-6">
                    <div class="mb-4">
                        <h2 class="text-sm font-semibold text-foreground">File d’attente checker</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Seul le validateur désigné par le maker peut valider et archiver.
                        </p>
                    </div>

                    <div
                        v-if="!classeurs?.length"
                        class="flex flex-col items-center rounded-2xl border border-dashed border-slate-300 px-6 py-16 text-center dark:border-slate-600"
                    >
                        <div
                            class="mb-4 flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                        >
                            <FileSpreadsheet class="size-7" />
                        </div>
                        <p class="text-sm font-medium text-foreground">
                            Aucune intégration en attente
                        </p>
                        <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                            Les intégrations apparaissent ici après transmission par un maker.
                        </p>
                    </div>

                    <ul v-else class="space-y-3">
                        <li
                            v-for="c in classeurs"
                            :key="c.id"
                            class="group flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-primary/30 hover:shadow-md dark:border-slate-700 dark:bg-card lg:flex-row lg:items-center lg:justify-between lg:px-5"
                        >
                            <div class="flex min-w-0 flex-1 items-start gap-3.5">
                                <div
                                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                >
                                    <FileText class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="rounded-full bg-sky-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-800 dark:bg-sky-950 dark:text-sky-200"
                                        >
                                            Attente validation
                                        </span>
                                        <h3 class="truncate text-base font-semibold text-foreground">
                                            {{ c.nom_classeur }}
                                        </h3>
                                    </div>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        {{ c.justificatifs_count }} justificatif(s)
                                    </p>

                                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
                                        <span
                                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <Hash class="size-3.5 text-primary" />
                                            <span class="font-medium text-foreground">{{
                                                c.numero_batch
                                            }}</span>
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <CalendarDays class="size-3.5 text-primary" />
                                            {{ dateFmt(c.date_valeur) }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <User class="size-3.5 text-primary" />
                                            Maker : {{ c.maker_name }}
                                        </span>
                                        <span
                                            v-if="canViewAllAgents"
                                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <UserCheck class="size-3.5 text-primary" />
                                            Checker : {{ c.checker_name }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <Clock class="size-3.5 text-primary" />
                                            {{ horodatage(c.integrated_at) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-1.5 lg:justify-end">
                                <Button
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    class="h-9 border-slate-300"
                                >
                                    <Link :href="c.resume_url" title="Voir le résumé">
                                        <FileSearch class="size-4" />
                                        Résumé
                                    </Link>
                                </Button>
                                <Button
                                    v-if="c.can_validate"
                                    size="sm"
                                    class="h-9 bg-emerald-600 text-white hover:bg-emerald-700"
                                    :disabled="validerEnCours === c.id"
                                    :title="
                                        validerEnCours === c.id
                                            ? 'Validation…'
                                            : 'Valider et archiver'
                                    "
                                    @click="valider(c)"
                                >
                                    <ShieldCheck class="size-4" />
                                    {{
                                        validerEnCours === c.id
                                            ? 'Validation…'
                                            : 'Valider et archiver'
                                    }}
                                </Button>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
