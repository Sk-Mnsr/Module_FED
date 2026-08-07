<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    Building2,
    CalendarRange,
    Download,
    Inbox,
    Megaphone,
    RefreshCw,
    ShoppingCart,
    TrendingUp,
    UserCircle,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Pilotage', href: '/monetique/pilotage' },
];

type AgenceRow = {
    agence_id: number | null;
    agence: string;
    nb_ventes: number;
    volume_ventes: number;
    montant_recharges: number;
    objectif_nb_ventes: number;
    objectif_montant_recharges: number;
    pct_ventes: number | null;
    pct_recharges: number | null;
    ecart_ventes: number;
};

const props = withDefaults(
    defineProps<{
        periode: {
            debut: string;
            fin: string;
            preset: string;
            debut_iso: string;
            fin_iso: string;
        };
        totaux: {
            nb_ventes: number;
            volume_ventes: number;
            nb_recharges: number;
            montant_recharges: number;
            ticket_moyen: number;
            ratio_recharges_ventes: number | null;
        };
        objectifs_reseau: { nb_ventes: number; montant_recharges: number };
        ventes_par_agence: AgenceRow[];
        ventes_par_cc: { nom: string; nb_ventes: number }[];
        ventes_par_apporteur: { apporteur: string; nb_ventes: number }[];
        campagnes: {
            id: number;
            nom: string;
            objectif_ventes: number;
            ventes_realisees: number;
            objectif_montant_recharges: number;
            montant_recharges: number;
        }[];
        serie_journaliere: {
            date: string;
            label: string;
            nb_ventes: number;
            montant_recharges: number;
        }[];
        alertes: {
            agence: string;
            pct_ventes: number | null;
            nb_ventes: number;
            objectif_nb_ventes: number;
        }[];
        perimetre: 'reseau' | 'agence';
    }>(),
    {
        objectifs_reseau: () => ({ nb_ventes: 0, montant_recharges: 0 }),
        ventes_par_agence: () => [],
        ventes_par_cc: () => [],
        ventes_par_apporteur: () => [],
        campagnes: () => [],
        serie_journaliere: () => [],
        alertes: () => [],
        perimetre: 'reseau',
    },
);

const preset = ref(props.periode.preset || 'current_month');
const fromIso = ref(props.periode.debut_iso);
const toIso = ref(props.periode.fin_iso);

watch(
    () => props.periode,
    (p) => {
        preset.value = p.preset;
        fromIso.value = p.debut_iso;
        toIso.value = p.fin_iso;
    },
);

const applyPeriode = () => {
    router.get(
        '/monetique/pilotage',
        {
            preset: preset.value,
            from: preset.value === 'custom' ? fromIso.value : undefined,
            to: preset.value === 'custom' ? toIso.value : undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    params.set('preset', preset.value);
    if (preset.value === 'custom') {
        params.set('from', fromIso.value);
        params.set('to', toIso.value);
    }
    return `/monetique/pilotage/export?${params.toString()}`;
});

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

const maxCc = computed(() => Math.max(1, ...props.ventes_par_cc.map((r) => r.nb_ventes), 0));
const maxApporteur = computed(() =>
    Math.max(1, ...props.ventes_par_apporteur.map((r) => r.nb_ventes), 0),
);
const maxSerieVentes = computed(() =>
    Math.max(1, ...props.serie_journaliere.map((d) => d.nb_ventes), 0),
);
const maxSerieRecharges = computed(() =>
    Math.max(1, ...props.serie_journaliere.map((d) => d.montant_recharges), 0),
);

const hasSerieData = computed(() =>
    props.serie_journaliere.some((d) => d.nb_ventes > 0 || d.montant_recharges > 0),
);

const barPct = (value: number, max: number) =>
    `${Math.min(100, max <= 0 ? 0 : (value / max) * 100)}%`;

const pctObjectif = (realise: number, objectif: number) => {
    if (!objectif || objectif <= 0) return null;
    return Math.min(100, Math.round((realise / objectif) * 100));
};

const pctLabel = (realise: number, objectif: number) => {
    const p = pctObjectif(realise, objectif);
    return p === null ? '—' : `${p} %`;
};

const ecartClass = (ecart: number, hasObj: boolean) => {
    if (!hasObj) return 'text-muted-foreground';
    if (ecart >= 0) return 'text-emerald-700';
    return 'text-red-600';
};

const pctBadgeClass = (pct: number | null) => {
    if (pct === null) return 'bg-slate-100 text-slate-600 ring-slate-200/80';
    if (pct >= 100) return 'bg-emerald-100 text-emerald-800 ring-emerald-200/80';
    if (pct >= 70) return 'bg-amber-100 text-amber-800 ring-amber-200/80';
    return 'bg-red-100 text-red-800 ring-red-200/80';
};
</script>

<template>
    <Head title="Pilotage Coficarte" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 sm:p-6 lg:gap-5">
            <!-- En-tête -->
            <section
                class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm"
            >
                <div
                    class="border-b border-border/80 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-5 sm:px-6 dark:from-primary/10"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
                            >
                                <BarChart3 class="size-5" />
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                                    Monétique
                                </p>
                                <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                    Pilotage
                                </h1>
                                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <CalendarRange class="size-4 shrink-0" />
                                    <span>
                                        {{ periode.debut }} — {{ periode.fin }}
                                    </span>
                                    <span
                                        class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-medium text-primary ring-1 ring-primary/20"
                                    >
                                        {{ perimetre === 'reseau' ? 'Réseau' : 'Mon agence' }}
                                    </span>
                                </p>
                                <p class="mt-1 max-w-3xl text-xs text-muted-foreground">
                                    Indicateurs basés sur les ventes et recharges
                                    <strong class="text-foreground">encaissées</strong>.
                                </p>
                            </div>
                        </div>
                        <a :href="exportUrl">
                            <Button type="button" variant="outline" class="border-slate-300">
                                <Download class="mr-2 size-4" />
                                Exporter CSV
                            </Button>
                        </a>
                    </div>
                </div>

                <div class="flex flex-wrap items-end gap-3 px-4 py-4 sm:px-5">
                    <div class="min-w-[160px]">
                        <label class="mb-1.5 block text-sm font-medium text-foreground">Période</label>
                        <select
                            v-model="preset"
                            class="flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground"
                            @change="preset !== 'custom' && applyPeriode()"
                        >
                            <option value="current_month">Mois en cours</option>
                            <option value="previous_month">Mois précédent</option>
                            <option value="custom">Personnalisée</option>
                        </select>
                    </div>
                    <template v-if="preset === 'custom'">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Du</label>
                            <Input
                                v-model="fromIso"
                                type="date"
                                class="h-10 border-slate-300 shadow-sm focus-visible:border-primary focus-visible:ring-primary/30"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Au</label>
                            <Input
                                v-model="toIso"
                                type="date"
                                class="h-10 border-slate-300 shadow-sm focus-visible:border-primary focus-visible:ring-primary/30"
                            />
                        </div>
                        <Button type="button" @click="applyPeriode">Appliquer</Button>
                    </template>
                </div>
            </section>

            <!-- Alertes -->
            <section
                v-if="alertes.length"
                class="rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-3 sm:px-5"
            >
                <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-amber-900">
                    <AlertTriangle class="size-4" />
                    Agences sous objectif (&lt; 70 %)
                </div>
                <ul class="flex flex-wrap gap-2">
                    <li
                        v-for="(a, i) in alertes"
                        :key="i"
                        class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-amber-900 ring-1 ring-amber-200"
                    >
                        {{ a.agence }} —
                        {{ a.nb_ventes }}/{{ a.objectif_nb_ventes }}
                        ({{ a.pct_ventes }} %)
                    </li>
                </ul>
            </section>

            <!-- KPI -->
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Ventes
                        </p>
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <ShoppingCart class="size-4" />
                        </div>
                    </div>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-foreground">
                        {{ totaux.nb_ventes }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">Cartes vendues</p>
                    <div v-if="objectifs_reseau.nb_ventes > 0" class="mt-3 space-y-1">
                        <div class="flex justify-between text-xs text-muted-foreground">
                            <span>Obj. {{ objectifs_reseau.nb_ventes }}</span>
                            <span class="font-medium text-primary">
                                {{ pctLabel(totaux.nb_ventes, objectifs_reseau.nb_ventes) }}
                            </span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{
                                    width: `${pctObjectif(totaux.nb_ventes, objectifs_reseau.nb_ventes) ?? 0}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Volume ventes
                        </p>
                        <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600">
                            <TrendingUp class="size-4" />
                        </div>
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-primary sm:text-3xl">
                        {{ formatCfa(totaux.volume_ventes) }}
                    </p>
                </div>

                <div class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Recharges
                        </p>
                        <div class="rounded-lg bg-sky-50 p-2 text-sky-600">
                            <RefreshCw class="size-4" />
                        </div>
                    </div>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-foreground">
                        {{ totaux.nb_recharges }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ formatCfa(totaux.montant_recharges) }}
                    </p>
                    <div v-if="objectifs_reseau.montant_recharges > 0" class="mt-3 space-y-1">
                        <div class="flex justify-between gap-2 text-xs text-muted-foreground">
                            <span class="truncate">Obj. {{ formatCfa(objectifs_reseau.montant_recharges) }}</span>
                            <span class="shrink-0 font-medium text-primary">
                                {{ pctLabel(totaux.montant_recharges, objectifs_reseau.montant_recharges) }}
                            </span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{
                                    width: `${pctObjectif(totaux.montant_recharges, objectifs_reseau.montant_recharges) ?? 0}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Ticket moyen
                        </p>
                        <div class="rounded-lg bg-amber-50 p-2 text-amber-700">
                            <Wallet class="size-4" />
                        </div>
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-foreground sm:text-3xl">
                        {{ formatCfa(totaux.ticket_moyen) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">Volume / nb ventes</p>
                </div>

                <div class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm sm:col-span-2 sm:p-5 xl:col-span-1">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Ratio rech./ventes
                        </p>
                        <div class="rounded-lg bg-slate-100 p-2 text-slate-600">
                            <RefreshCw class="size-4" />
                        </div>
                    </div>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-foreground">
                        {{ totaux.ratio_recharges_ventes ?? '—' }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">Recharges par vente</p>
                </div>
            </div>

            <!-- Tendance + Agences -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                <section
                    class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm xl:col-span-5"
                >
                    <div
                        class="flex items-center gap-2 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-5 py-4"
                    >
                        <TrendingUp class="size-5 shrink-0 text-primary" />
                        <div>
                            <h2 class="font-semibold text-foreground">Tendance journalière</h2>
                            <p class="text-xs text-muted-foreground">Ventes (nb) et recharges (F CFA)</p>
                        </div>
                    </div>
                    <div
                        v-if="!hasSerieData"
                        class="flex min-h-[14rem] flex-col items-center justify-center p-10 text-center"
                    >
                        <Inbox class="mx-auto mb-3 size-10 text-slate-300" />
                        <p class="text-sm text-muted-foreground">Aucune activité sur la période.</p>
                    </div>
                    <div v-else class="max-h-[min(28rem,50vh)] space-y-4 overflow-y-auto p-4">
                        <div>
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                Ventes / jour
                            </p>
                            <div class="flex h-28 items-end gap-0.5 sm:gap-1">
                                <div
                                    v-for="d in serie_journaliere"
                                    :key="'v-' + d.date"
                                    class="group relative flex min-w-0 flex-1 flex-col items-center justify-end"
                                    :title="`${d.label} : ${d.nb_ventes} vente(s)`"
                                >
                                    <div
                                        class="w-full max-w-[14px] rounded-t bg-primary/80 transition-all group-hover:bg-primary"
                                        :style="{
                                            height: barPct(d.nb_ventes, maxSerieVentes),
                                            minHeight: d.nb_ventes > 0 ? '4px' : '0',
                                        }"
                                    />
                                </div>
                            </div>
                        </div>
                        <div>
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                Montant recharges / jour
                            </p>
                            <div class="flex h-28 items-end gap-0.5 sm:gap-1">
                                <div
                                    v-for="d in serie_journaliere"
                                    :key="'r-' + d.date"
                                    class="group relative flex min-w-0 flex-1 flex-col items-center justify-end"
                                    :title="`${d.label} : ${formatCfa(d.montant_recharges)}`"
                                >
                                    <div
                                        class="w-full max-w-[14px] rounded-t bg-emerald-500/80 transition-all group-hover:bg-emerald-500"
                                        :style="{
                                            height: barPct(d.montant_recharges, maxSerieRecharges),
                                            minHeight: d.montant_recharges > 0 ? '4px' : '0',
                                        }"
                                    />
                                </div>
                            </div>
                            <div class="mt-1 flex justify-between text-[10px] text-muted-foreground">
                                <span>{{ serie_journaliere[0]?.label }}</span>
                                <span>{{ serie_journaliere[serie_journaliere.length - 1]?.label }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm xl:col-span-7"
                >
                    <div
                        class="flex items-center gap-2 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-5 py-4"
                    >
                        <Building2 class="size-5 shrink-0 text-primary" />
                        <div>
                            <h2 class="font-semibold text-foreground">Agences vs objectifs</h2>
                            <p class="text-xs text-muted-foreground">
                                Triées par atteinte (retards en tête)
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="!ventes_par_agence.length"
                        class="flex min-h-[14rem] flex-col items-center justify-center p-10 text-center"
                    >
                        <Inbox class="mx-auto mb-3 size-10 text-slate-300" />
                        <p class="text-sm text-muted-foreground">Aucune vente encaissée sur la période.</p>
                    </div>
                    <div v-else class="max-h-[min(28rem,50vh)] overflow-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead
                                class="sticky top-0 z-10 bg-muted/90 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground backdrop-blur-sm"
                            >
                                <tr>
                                    <th class="px-4 py-3">Agence</th>
                                    <th class="px-4 py-3 text-right">Ventes</th>
                                    <th class="px-4 py-3 text-right">Atteinte</th>
                                    <th class="px-4 py-3 text-right">Écart</th>
                                    <th class="hidden px-4 py-3 text-right md:table-cell">Volume</th>
                                    <th class="hidden px-4 py-3 text-right lg:table-cell">Recharges</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="(r, i) in ventes_par_agence"
                                    :key="i"
                                    class="hover:bg-muted/30"
                                >
                                    <td class="px-4 py-3 font-medium text-foreground">{{ r.agence }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <span class="font-medium">{{ r.nb_ventes }}</span>
                                        <span
                                            v-if="r.objectif_nb_ventes > 0"
                                            class="block text-xs text-muted-foreground"
                                        >
                                            / {{ r.objectif_nb_ventes }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1"
                                            :class="pctBadgeClass(r.pct_ventes)"
                                        >
                                            {{ r.pct_ventes === null ? '—' : `${r.pct_ventes} %` }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-semibold tabular-nums"
                                        :class="ecartClass(r.ecart_ventes, r.objectif_nb_ventes > 0)"
                                    >
                                        {{
                                            r.objectif_nb_ventes > 0
                                                ? (r.ecart_ventes > 0 ? '+' : '') + r.ecart_ventes
                                                : '—'
                                        }}
                                    </td>
                                    <td class="hidden px-4 py-3 text-right font-medium tabular-nums text-primary md:table-cell">
                                        {{ formatCfa(r.volume_ventes) }}
                                    </td>
                                    <td class="hidden px-4 py-3 text-right tabular-nums lg:table-cell">
                                        <span>{{ formatCfa(r.montant_recharges) }}</span>
                                        <span
                                            v-if="r.objectif_montant_recharges > 0"
                                            class="mt-0.5 block text-xs text-muted-foreground"
                                        >
                                            {{ r.pct_recharges }} % obj.
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- CC + Apporteurs -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                <section
                    class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm xl:col-span-5"
                >
                    <div
                        class="flex items-center gap-2 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-5 py-4"
                    >
                        <UserCircle class="size-5 shrink-0 text-primary" />
                        <h2 class="font-semibold text-foreground">Par chargé de clientèle</h2>
                    </div>
                    <div
                        v-if="!ventes_par_cc.length"
                        class="flex min-h-[12rem] flex-col items-center justify-center p-8 text-center"
                    >
                        <Inbox class="mx-auto mb-3 size-10 text-slate-300" />
                        <p class="text-sm text-muted-foreground">Aucune donnée pour cette répartition.</p>
                    </div>
                    <ul
                        v-else
                        class="max-h-[min(24rem,45vh)] divide-y divide-border overflow-y-auto p-2"
                    >
                        <li
                            v-for="(r, i) in ventes_par_cc"
                            :key="i"
                            class="rounded-lg px-3 py-3 hover:bg-muted/30"
                        >
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <span class="truncate text-sm font-medium text-foreground">{{ r.nom }}</span>
                                <span class="shrink-0 text-sm font-semibold tabular-nums text-primary">
                                    {{ r.nb_ventes }}
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-primary/70 transition-all"
                                    :style="{ width: barPct(r.nb_ventes, maxCc) }"
                                />
                            </div>
                        </li>
                    </ul>
                </section>

                <section
                    class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm xl:col-span-7"
                >
                    <div
                        class="flex items-center gap-2 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-5 py-4"
                    >
                        <Users class="size-5 shrink-0 text-primary" />
                        <h2 class="font-semibold text-foreground">Par apporteur</h2>
                    </div>
                    <div
                        v-if="!ventes_par_apporteur.length"
                        class="flex min-h-[12rem] flex-col items-center justify-center p-8 text-center"
                    >
                        <Inbox class="mx-auto mb-3 size-10 text-slate-300" />
                        <p class="text-sm text-muted-foreground">
                            Aucune vente associée à un apporteur sur la période.
                        </p>
                    </div>
                    <ul
                        v-else
                        class="max-h-[min(24rem,45vh)] divide-y divide-border overflow-y-auto p-2"
                    >
                        <li
                            v-for="(r, i) in ventes_par_apporteur"
                            :key="i"
                            class="rounded-lg px-3 py-3 hover:bg-muted/30"
                        >
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <span class="truncate text-sm font-medium text-foreground">
                                    {{ r.apporteur }}
                                </span>
                                <span class="shrink-0 text-sm font-semibold tabular-nums text-primary">
                                    {{ r.nb_ventes }}
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-emerald-500/80 transition-all"
                                    :style="{ width: barPct(r.nb_ventes, maxApporteur) }"
                                />
                            </div>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Campagnes -->
            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div
                    class="flex items-center gap-2 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-5 py-4"
                >
                    <Megaphone class="size-5 shrink-0 text-primary" />
                    <h2 class="font-semibold text-foreground">Campagnes actives — avancement</h2>
                </div>
                <div
                    v-if="!campagnes.length"
                    class="flex min-h-[10rem] flex-col items-center justify-center p-10 text-center"
                >
                    <Inbox class="mx-auto mb-3 size-10 text-slate-300" />
                    <p class="text-sm text-muted-foreground">Aucune campagne active à la date du jour.</p>
                </div>
                <div
                    v-else
                    class="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <div
                        v-for="c in campagnes"
                        :key="c.id"
                        class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-border dark:bg-muted/20"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-semibold text-foreground">{{ c.nom }}</p>
                            <span
                                v-if="
                                    pctObjectif(c.ventes_realisees, c.objectif_ventes) !== null &&
                                    (pctObjectif(c.ventes_realisees, c.objectif_ventes) as number) < 50
                                "
                                class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-800 ring-1 ring-amber-200"
                            >
                                À risque
                            </span>
                        </div>
                        <div>
                            <div class="mb-1 flex justify-between text-xs text-muted-foreground">
                                <span>Ventes</span>
                                <span class="font-medium tabular-nums text-foreground">
                                    {{ c.ventes_realisees }} / {{ c.objectif_ventes || '—' }}
                                </span>
                            </div>
                            <div
                                v-if="pctObjectif(c.ventes_realisees, c.objectif_ventes) !== null"
                                class="h-2 overflow-hidden rounded-full bg-white ring-1 ring-slate-200 dark:bg-card"
                            >
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{
                                        width: pctObjectif(c.ventes_realisees, c.objectif_ventes) + '%',
                                    }"
                                />
                            </div>
                            <p v-else class="text-xs text-muted-foreground">Pas d’objectif ventes</p>
                        </div>
                        <div>
                            <div class="mb-1 flex justify-between text-xs text-muted-foreground">
                                <span>Recharges (FCFA)</span>
                                <span class="font-medium tabular-nums text-foreground">
                                    {{ c.montant_recharges.toLocaleString('fr-FR') }} /
                                    {{
                                        c.objectif_montant_recharges > 0
                                            ? c.objectif_montant_recharges.toLocaleString('fr-FR')
                                            : '—'
                                    }}
                                </span>
                            </div>
                            <div
                                v-if="pctObjectif(c.montant_recharges, c.objectif_montant_recharges) !== null"
                                class="h-2 overflow-hidden rounded-full bg-white ring-1 ring-slate-200 dark:bg-card"
                            >
                                <div
                                    class="h-full rounded-full bg-emerald-500"
                                    :style="{
                                        width:
                                            pctObjectif(c.montant_recharges, c.objectif_montant_recharges) +
                                            '%',
                                    }"
                                />
                            </div>
                            <p v-else class="text-xs text-muted-foreground">Pas d’objectif recharges</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
