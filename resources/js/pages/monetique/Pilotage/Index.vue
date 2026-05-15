<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    CalendarRange,
    Inbox,
    Megaphone,
    RefreshCw,
    ShoppingCart,
    TrendingUp,
    UserCircle,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Pilotage', href: '/monetique/pilotage' },
];

const props = withDefaults(
    defineProps<{
        periode: { debut: string; fin: string };
        totaux: { nb_ventes: number; volume_ventes: number; nb_recharges: number; montant_recharges: number };
        objectifs_reseau: { nb_ventes: number; montant_recharges: number };
        ventes_par_agence: {
            agence_id: number | null;
            agence: string;
            nb_ventes: number;
            volume_ventes: number;
            montant_recharges: number;
            objectif_nb_ventes: number;
            objectif_montant_recharges: number;
        }[];
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
    }>(),
    {
        objectifs_reseau: () => ({ nb_ventes: 0, montant_recharges: 0 }),
        ventes_par_agence: () => [],
        ventes_par_cc: () => [],
        ventes_par_apporteur: () => [],
        campagnes: () => [],
    },
);

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

const maxCc = computed(() => Math.max(1, ...props.ventes_par_cc.map((r) => r.nb_ventes)));
const maxApporteur = computed(() => Math.max(1, ...props.ventes_par_apporteur.map((r) => r.nb_ventes)));
const maxAgenceVol = computed(() => Math.max(1, ...props.ventes_par_agence.map((r) => r.volume_ventes)));

const barPct = (value: number, max: number) => `${Math.min(100, max <= 0 ? 0 : (value / max) * 100)}%`;

const pctObjectif = (realise: number, objectif: number) => {
    if (!objectif || objectif <= 0) {
        return null;
    }
    return Math.min(100, Math.round((realise / objectif) * 100));
};

const pctLabel = (realise: number, objectif: number) => {
    const p = pctObjectif(realise, objectif);
    return p === null ? '—' : `${p} %`;
};
</script>

<template>
    <Head title="Pilotage Coficarte" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 w-full max-w-none flex-1 flex-col gap-6 lg:gap-8 px-4 pb-8 pt-3 sm:px-6 lg:px-8">
            <header class="flex shrink-0 flex-col gap-4 border-b border-gray-200 pb-6 dark:border-neutral-800">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <div class="flex min-w-0 flex-1 items-start gap-4">
                        <div class="shrink-0 rounded-2xl bg-violet-600 p-3.5 text-white shadow-md shadow-violet-600/25 dark:shadow-violet-900/40">
                            <BarChart3 class="h-7 w-7" />
                        </div>
                        <div class="min-w-0 space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-violet-600 dark:text-violet-400">
                                Monétique
                            </p>
                            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-neutral-50 sm:text-3xl">
                                Pilotage
                            </h1>
                            <p class="flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-neutral-400">
                                <CalendarRange class="h-4 w-4 shrink-0 text-gray-400 dark:text-neutral-500" />
                                <span>
                                    Période&nbsp;
                                    <span class="font-medium text-gray-900 dark:text-neutral-200"
                                        >{{ periode.debut }} — {{ periode.fin }}</span
                                    >
                                    <span class="text-gray-500 dark:text-neutral-500"> (mois en cours)</span>
                                </span>
                            </p>
                            <p class="max-w-4xl text-xs text-gray-500 dark:text-neutral-500 lg:max-w-none">
                                Indicateurs basés sur les ventes
                                <strong class="text-gray-800 dark:text-neutral-300">encaissées</strong> et les recharges
                                <strong class="text-gray-800 dark:text-neutral-300">encaissées</strong>.
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- KPI -->
            <div class="grid shrink-0 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4 xl:gap-4">
                <div
                    class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Ventes</p>
                        <div class="rounded-lg bg-violet-50 p-2 text-violet-600 dark:bg-violet-950/60 dark:text-violet-400">
                            <ShoppingCart class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900 dark:text-neutral-100 xl:text-4xl">{{ totaux.nb_ventes }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-neutral-400">Cartes vendues (période)</p>
                    <div v-if="objectifs_reseau.nb_ventes > 0" class="mt-3 space-y-1">
                        <div class="flex justify-between text-xs text-gray-600 dark:text-neutral-400">
                            <span>Objectif réseau {{ objectifs_reseau.nb_ventes }}</span>
                            <span class="font-medium text-violet-700 dark:text-violet-400">{{ pctLabel(totaux.nb_ventes, objectifs_reseau.nb_ventes) }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800">
                            <div
                                class="h-full rounded-full bg-violet-500 transition-all"
                                :style="{
                                    width: `${pctObjectif(totaux.nb_ventes, objectifs_reseau.nb_ventes) ?? 0}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Volume ventes</p>
                        <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                            <TrendingUp class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-violet-700 dark:text-violet-400 sm:text-3xl xl:text-4xl">
                        {{ formatCfa(totaux.volume_ventes) }}
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Recharges</p>
                        <div class="rounded-lg bg-sky-50 p-2 text-sky-600 dark:bg-sky-950/50 dark:text-sky-400">
                            <RefreshCw class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900 dark:text-neutral-100 xl:text-4xl">{{ totaux.nb_recharges }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-neutral-400">Opérations encaissées</p>
                </div>
                <div
                    class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Montant recharges</p>
                        <div class="rounded-lg bg-amber-50 p-2 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                            <Wallet class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-gray-900 dark:text-neutral-100 sm:text-3xl xl:text-4xl">
                        {{ formatCfa(totaux.montant_recharges) }}
                    </p>
                    <div v-if="objectifs_reseau.montant_recharges > 0" class="mt-3 space-y-1">
                        <div class="flex justify-between gap-2 text-xs text-gray-600 dark:text-neutral-400">
                            <span class="truncate">Obj. {{ formatCfa(objectifs_reseau.montant_recharges) }}</span>
                            <span class="shrink-0 font-medium text-amber-800 dark:text-amber-300">{{
                                pctLabel(totaux.montant_recharges, objectifs_reseau.montant_recharges)
                            }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800">
                            <div
                                class="h-full rounded-full bg-amber-500 transition-all"
                                :style="{
                                    width: `${pctObjectif(totaux.montant_recharges, objectifs_reseau.montant_recharges) ?? 0}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ligne 1 : agence (large) + CC — évite la cellule vide sur la grille 2 colonnes -->
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                <!-- Par agence -->
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900 xl:col-span-7">
                    <div class="flex items-center gap-2 border-b border-gray-100 bg-gray-50/80 px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950/80">
                        <Building2 class="h-5 w-5 shrink-0 text-violet-600 dark:text-violet-400" />
                        <h2 class="font-semibold text-gray-900 dark:text-neutral-100">Par agence</h2>
                    </div>
                    <div v-if="!ventes_par_agence.length" class="flex min-h-[14rem] flex-col items-center justify-center p-10 text-center">
                        <Inbox class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-neutral-600" />
                        <p class="text-sm text-gray-600 dark:text-neutral-400">Aucune vente encaissée sur la période.</p>
                    </div>
                    <div v-else class="max-h-[min(28rem,50vh)] overflow-auto xl:max-h-[min(32rem,55vh)]">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead class="sticky top-0 z-10 bg-gray-50/95 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 shadow-sm backdrop-blur-sm dark:bg-neutral-950/95 dark:text-neutral-400">
                                <tr>
                                    <th class="px-4 py-3">Agence</th>
                                    <th class="px-4 py-3 text-right">Ventes</th>
                                    <th class="px-4 py-3 text-right">Volume</th>
                                    <th class="hidden px-4 py-3 text-right md:table-cell">Recharges</th>
                                    <th class="hidden w-40 px-4 py-3 sm:table-cell">Part volume</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-neutral-800">
                                <tr v-for="(r, i) in ventes_par_agence" :key="i" class="align-top hover:bg-gray-50/80 dark:hover:bg-neutral-800/50">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-neutral-100">{{ r.agence }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-neutral-300">
                                        <span class="font-medium">{{ r.nb_ventes }}</span>
                                        <span
                                            v-if="r.objectif_nb_ventes > 0"
                                            class="mt-0.5 block text-xs font-normal text-gray-500 dark:text-neutral-500"
                                        >
                                            Obj.
                                            {{ r.objectif_nb_ventes }} — {{ pctObjectif(r.nb_ventes, r.objectif_nb_ventes) }} %
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums text-violet-700 dark:text-violet-400">
                                        {{ formatCfa(r.volume_ventes) }}
                                    </td>
                                    <td class="hidden px-4 py-3 text-right tabular-nums text-sky-800 dark:text-sky-300 md:table-cell">
                                        <span>{{ formatCfa(r.montant_recharges) }}</span>
                                        <span
                                            v-if="r.objectif_montant_recharges > 0"
                                            class="mt-0.5 block text-xs font-normal text-gray-500 dark:text-neutral-500"
                                        >
                                            Obj. {{ formatCfa(r.objectif_montant_recharges) }} —
                                            {{ pctObjectif(r.montant_recharges, r.objectif_montant_recharges) }} %
                                        </span>
                                    </td>
                                    <td class="hidden px-4 py-3 sm:table-cell">
                                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800">
                                            <div
                                                class="h-full rounded-full bg-violet-500 transition-all"
                                                :style="{ width: barPct(r.volume_ventes, maxAgenceVol) }"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Par CC -->
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900 xl:col-span-5">
                    <div class="flex items-center gap-2 border-b border-gray-100 bg-gray-50/80 px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950/80">
                        <UserCircle class="h-5 w-5 shrink-0 text-teal-600 dark:text-teal-400" />
                        <h2 class="font-semibold text-gray-900 dark:text-neutral-100">Par chargé de clientèle</h2>
                    </div>
                    <div v-if="!ventes_par_cc.length" class="flex min-h-[14rem] flex-col items-center justify-center p-10 text-center">
                        <Inbox class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-neutral-600" />
                        <p class="text-sm text-gray-600 dark:text-neutral-400">Aucune donnée pour cette répartition.</p>
                    </div>
                    <ul v-else class="max-h-[min(28rem,50vh)] divide-y divide-gray-100 overflow-y-auto p-2 dark:divide-neutral-800 xl:max-h-[min(32rem,55vh)]">
                        <li v-for="(r, i) in ventes_par_cc" :key="i" class="rounded-lg px-3 py-3 hover:bg-gray-50 dark:hover:bg-neutral-800/50">
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <span class="truncate text-sm font-medium text-gray-900 dark:text-neutral-100">{{ r.nom }}</span>
                                <span class="shrink-0 text-sm font-semibold tabular-nums text-violet-700 dark:text-violet-400">{{
                                    r.nb_ventes
                                }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800">
                                <div
                                    class="h-full rounded-full bg-teal-500 transition-all dark:bg-teal-600"
                                    :style="{ width: barPct(r.nb_ventes, maxCc) }"
                                />
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Ligne 2 : apporteurs + campagnes côte à côte sur xl -->
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                <!-- Par apporteur -->
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900 xl:col-span-5">
                    <div class="flex items-center gap-2 border-b border-gray-100 bg-gray-50/80 px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950/80">
                        <Users class="h-5 w-5 shrink-0 text-orange-600 dark:text-orange-400" />
                        <h2 class="font-semibold text-gray-900 dark:text-neutral-100">Par apporteur</h2>
                    </div>
                    <div v-if="!ventes_par_apporteur.length" class="flex min-h-[14rem] flex-col items-center justify-center p-10 text-center">
                        <Inbox class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-neutral-600" />
                        <p class="text-sm text-gray-600 dark:text-neutral-400">Aucune vente associée à un apporteur sur la période.</p>
                    </div>
                    <ul v-else class="max-h-[min(24rem,45vh)] divide-y divide-gray-100 overflow-y-auto p-2 dark:divide-neutral-800 xl:max-h-[min(32rem,55vh)]">
                        <li v-for="(r, i) in ventes_par_apporteur" :key="i" class="rounded-lg px-3 py-3 hover:bg-gray-50 dark:hover:bg-neutral-800/50">
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <span class="truncate text-sm font-medium text-gray-900 dark:text-neutral-100">{{ r.apporteur }}</span>
                                <span class="shrink-0 text-sm font-semibold tabular-nums text-orange-700 dark:text-orange-400">{{
                                    r.nb_ventes
                                }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-neutral-800">
                                <div
                                    class="h-full rounded-full bg-orange-400 transition-all dark:bg-orange-600"
                                    :style="{ width: barPct(r.nb_ventes, maxApporteur) }"
                                />
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Campagnes -->
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900 xl:col-span-7">
                    <div class="flex items-center gap-2 border-b border-gray-100 bg-gray-50/80 px-5 py-4 dark:border-neutral-800 dark:bg-neutral-950/80">
                        <Megaphone class="h-5 w-5 shrink-0 text-violet-600 dark:text-violet-400" />
                        <h2 class="font-semibold text-gray-900 dark:text-neutral-100">Campagnes actives — avancement</h2>
                    </div>
                    <div v-if="!campagnes.length" class="flex min-h-[14rem] flex-col items-center justify-center p-10 text-center">
                        <Inbox class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-neutral-600" />
                        <p class="text-sm text-gray-600 dark:text-neutral-400">Aucune campagne active à la date du jour.</p>
                    </div>
                    <div v-else class="grid max-h-[min(28rem,50vh)] gap-4 overflow-y-auto p-4 sm:grid-cols-2 xl:max-h-[min(32rem,55vh)] xl:grid-cols-2 2xl:grid-cols-3">
                        <div
                            v-for="c in campagnes"
                            :key="c.id"
                            class="space-y-3 rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-neutral-800 dark:bg-neutral-950/40"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-semibold text-gray-900 dark:text-neutral-100">{{ c.nom }}</p>
                            </div>
                            <div>
                                <div class="mb-1 flex justify-between text-xs text-gray-600 dark:text-neutral-400">
                                    <span>Ventes</span>
                                    <span class="font-medium tabular-nums text-gray-800 dark:text-neutral-200"
                                        >{{ c.ventes_realisees }} / {{ c.objectif_ventes || '—' }}</span
                                    >
                                </div>
                                <div v-if="pctObjectif(c.ventes_realisees, c.objectif_ventes) !== null" class="h-2 overflow-hidden rounded-full border border-gray-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
                                    <div
                                        class="h-full rounded-full bg-violet-500"
                                        :style="{
                                            width: pctObjectif(c.ventes_realisees, c.objectif_ventes) + '%',
                                        }"
                                    />
                                </div>
                                <p v-else class="text-xs text-gray-400 dark:text-neutral-500">Pas d’objectif ventes défini</p>
                            </div>
                            <div>
                                <div class="mb-1 flex justify-between text-xs text-gray-600 dark:text-neutral-400">
                                    <span>Recharges (FCFA)</span>
                                    <span class="font-medium tabular-nums text-gray-800 dark:text-neutral-200">
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
                                    class="h-2 overflow-hidden rounded-full border border-gray-200 bg-white dark:border-neutral-700 dark:bg-neutral-900"
                                >
                                    <div
                                        class="h-full rounded-full bg-emerald-500"
                                        :style="{
                                            width:
                                                pctObjectif(c.montant_recharges, c.objectif_montant_recharges) + '%',
                                        }"
                                    />
                                </div>
                                <p v-else class="text-xs text-gray-400 dark:text-neutral-500">Pas d’objectif recharges défini</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
