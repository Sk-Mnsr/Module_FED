<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    Building2,
    CreditCard,
    RefreshCw,
    ShoppingCart,
    Wallet,
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Suivi agence', href: '/monetique/agence/suivi' },
];

const props = withDefaults(
    defineProps<{
        agence?: { nom: string; code: string };
        stats?: {
            en_stock_pool: number;
            en_stock_cc: number;
            ventes_mois: number;
            ventes_total: number;
            recharges_mois: number;
            montant_recharges_mois: number;
        };
        recharges?: { disponible: boolean; message: string };
    }>(),
    {
        agence: () => ({ nom: '', code: '' }),
        stats: () => ({
            en_stock_pool: 0,
            en_stock_cc: 0,
            ventes_mois: 0,
            ventes_total: 0,
            recharges_mois: 0,
            montant_recharges_mois: 0,
        }),
        recharges: () => ({ disponible: false, message: '' }),
    },
);

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;
</script>

<template>
    <Head title="Suivi agence - Chef d'agence" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-6 max-w-6xl mx-auto w-full">
            <button
                type="button"
                class="inline-flex items-center gap-2 text-sm font-medium text-violet-700 hover:text-violet-900 w-fit -ml-1"
                @click="router.visit('/monetique/coficarte')"
            >
                ← Coficarte
            </button>

            <div class="flex items-start gap-4">
                <div class="p-3.5 bg-violet-100 text-violet-800 rounded-2xl shadow-sm shrink-0">
                    <Activity class="h-7 w-7" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Suivi d’activité</h1>
                    <p class="text-sm text-gray-600 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <Building2 class="h-4 w-4 text-gray-400 shrink-0" />
                        <span>
                            {{ agence.nom }}<span v-if="agence.code" class="text-gray-500"> ({{ agence.code }})</span>
                        </span>
                        <span class="text-gray-400 hidden sm:inline">—</span>
                        <span class="text-gray-500">Ventes et pilotage du stock</span>
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pool agence</span>
                        <div class="p-1.5 rounded-lg bg-slate-100 text-slate-700">
                            <CreditCard class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-900 tabular-nums">{{ stats.en_stock_pool }}</p>
                    <p class="text-xs text-gray-500 mt-2 leading-snug">Non attribuées à un CC</p>
                </div>

                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Chez les CC</span>
                        <div class="p-1.5 rounded-lg bg-indigo-100 text-indigo-700">
                            <BarChart3 class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-indigo-900 tabular-nums">{{ stats.en_stock_cc }}</p>
                    <p class="text-xs text-gray-500 mt-2 leading-snug">En stock, affectées</p>
                </div>

                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ventes mois</span>
                        <div class="p-1.5 rounded-lg bg-violet-100 text-violet-700">
                            <ShoppingCart class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-violet-700 tabular-nums">{{ stats.ventes_mois }}</p>
                    <p class="text-xs text-gray-500 mt-2 leading-snug">Mois en cours</p>
                </div>

                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ventes total</span>
                        <div class="p-1.5 rounded-lg bg-gray-100 text-gray-700">
                            <ShoppingCart class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-gray-900 tabular-nums">{{ stats.ventes_total }}</p>
                    <p class="text-xs text-gray-500 mt-2 leading-snug">Toutes périodes</p>
                </div>

                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Recharges mois</span>
                        <div class="p-1.5 rounded-lg bg-teal-100 text-teal-700">
                            <RefreshCw class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-teal-800 tabular-nums">{{ stats.recharges_mois }}</p>
                    <p class="text-xs text-gray-500 mt-2 leading-snug">Opérations encaissées</p>
                </div>

                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Volume recharges</span>
                        <div class="p-1.5 rounded-lg bg-emerald-100 text-emerald-700">
                            <Wallet class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="text-xl sm:text-2xl font-bold text-emerald-800 tabular-nums leading-tight">
                        {{ formatCfa(stats.montant_recharges_mois) }}
                    </p>
                    <p class="text-xs text-gray-500 mt-2 leading-snug">Montant encaissé (mois)</p>
                </div>
            </div>

            <div class="rounded-2xl border border-teal-200/80 bg-gradient-to-br from-teal-50/80 to-white p-6 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                    <div class="p-3 rounded-xl bg-white border border-teal-100 shadow-sm text-teal-700 shrink-0">
                        <RefreshCw class="h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-semibold text-gray-900">Synthèse recharges (mois en cours)</h2>
                        <p class="text-sm text-gray-700 mt-2 leading-relaxed">{{ recharges.message }}</p>
                        <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg bg-white/80 border border-teal-100 px-3 py-2">
                                <dt class="text-xs text-gray-500">Nombre encaissé</dt>
                                <dd class="font-semibold text-gray-900 tabular-nums text-lg">{{ stats.recharges_mois }}</dd>
                            </div>
                            <div class="rounded-lg bg-white/80 border border-teal-100 px-3 py-2">
                                <dt class="text-xs text-gray-500">Montant total</dt>
                                <dd class="font-semibold text-emerald-800 tabular-nums text-lg">
                                    {{ formatCfa(stats.montant_recharges_mois) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <Button as-child class="bg-violet-600 hover:bg-violet-700">
                    <Link href="/monetique/ventes/historique">Historique détaillé des ventes</Link>
                </Button>
                <Button as-child variant="outline" class="bg-white border-gray-300">
                    <Link href="/monetique/cartes/vendus">Cartes vendues (agence)</Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
