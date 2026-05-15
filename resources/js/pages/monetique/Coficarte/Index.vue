<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRightLeft, CreditCard, PackageMinus, ShoppingCart, UserSquare, Users, Activity } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Coficarte', href: '/monetique/coficarte' },
];

const props = withDefaults(
    defineProps<{
        stats?: { en_stock: number; vendus: number };
        chef_agence_portal?: boolean;
        cc_delester_chef_visible?: boolean;
    }>(),
    {
        stats: () => ({ en_stock: 0, vendus: 0 }),
        chef_agence_portal: false,
        cc_delester_chef_visible: false,
    },
);
</script>

<template>
    <Head title="Monétique - Coficarte" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-violet-100 text-violet-700 rounded-xl">
                    <CreditCard class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Coficarte</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        <template v-if="chef_agence_portal">Espace chef d'agence — ventes et stock de votre entité</template>
                        <template v-else>Vue d'ensemble du module</template>
                    </p>
                </div>
            </div>

            <div v-if="chef_agence_portal" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    href="/monetique/transferts/en-attente"
                    class="rounded-xl border border-violet-100 bg-violet-50/80 p-5 shadow-sm hover:border-violet-200 transition-colors"
                >
                    <ArrowRightLeft class="h-6 w-6 text-violet-700 mb-3" />
                    <p class="font-semibold text-gray-900">Réception des cartes</p>
                    <p class="text-xs text-gray-600 mt-1">Transferts en attente à réceptionner</p>
                </Link>
                <Link
                    href="/monetique/agence/retour-cartes"
                    class="rounded-xl border border-amber-100 bg-amber-50/80 p-5 shadow-sm hover:border-amber-200 transition-colors"
                >
                    <PackageMinus class="h-6 w-6 text-amber-800 mb-3" />
                    <p class="font-semibold text-gray-900">Retour au siège</p>
                    <p class="text-xs text-gray-600 mt-1">Restitution de cartes vers la monétique centrale</p>
                </Link>
                <Link
                    href="/monetique/cartes/en-stock"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-violet-200 transition-colors"
                >
                    <CreditCard class="h-6 w-6 text-violet-600 mb-3" />
                    <p class="font-semibold text-gray-900">Stock agence</p>
                    <p class="text-xs text-gray-600 mt-1">Cartes en stock sur votre entité</p>
                </Link>
                <Link
                    href="/monetique/agence/approvisionnement-cc"
                    class="rounded-xl border border-emerald-100 bg-emerald-50/80 p-5 shadow-sm hover:border-emerald-200 transition-colors"
                >
                    <Users class="h-6 w-6 text-emerald-800 mb-3" />
                    <p class="font-semibold text-gray-900">Approvisionnement CC</p>
                    <p class="text-xs text-gray-600 mt-1">Chargés de clientèle de l'agence</p>
                </Link>
                <Link
                    v-if="cc_delester_chef_visible"
                    href="/monetique/cc/delester-chef-agence"
                    class="rounded-xl border border-violet-200 bg-violet-50/90 p-5 shadow-sm hover:border-violet-300 transition-colors"
                >
                    <UserSquare class="h-6 w-6 text-violet-800 mb-3" />
                    <p class="font-semibold text-gray-900">Délester vers le chef d’agence</p>
                    <p class="text-xs text-gray-600 mt-1">Remettre vos cartes attribuées dans le pool agence</p>
                </Link>
                <Link
                    href="/monetique/agence/suivi"
                    class="rounded-xl border border-sky-100 bg-sky-50/80 p-5 shadow-sm hover:border-sky-200 transition-colors sm:col-span-2 lg:col-span-1 flex flex-col"
                >
                    <Activity class="h-6 w-6 text-sky-800 mb-3" />
                    <p class="font-semibold text-gray-900">Suivi ventes & recharges</p>
                    <p class="text-xs text-gray-600 mt-1">Pilotage d’activité au niveau agence</p>
                </Link>
                <Link
                    href="/monetique/ventes/historique"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-violet-200 transition-colors sm:col-span-2 lg:col-span-2 flex items-start gap-4"
                >
                    <ShoppingCart class="h-6 w-6 text-violet-600 shrink-0" />
                    <div>
                        <p class="font-semibold text-gray-900">Historique des ventes</p>
                        <p class="text-xs text-gray-600 mt-1">Liste détaillée des ventes sur le périmètre de l'agence</p>
                    </div>
                </Link>
            </div>

            <div v-if="cc_delester_chef_visible && !chef_agence_portal" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    href="/monetique/cc/delester-chef-agence"
                    class="rounded-xl border border-violet-200 bg-violet-50/90 p-5 shadow-sm hover:border-violet-300 transition-colors"
                >
                    <UserSquare class="h-6 w-6 text-violet-800 mb-3" />
                    <p class="font-semibold text-gray-900">Délester vers le chef d’agence</p>
                    <p class="text-xs text-gray-600 mt-1">Remettre vos cartes attribuées dans le pool agence</p>
                </Link>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-500">Cartes en stock</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ props.stats.en_stock }}</p>
                    <Link href="/monetique/cartes/en-stock" class="mt-4 inline-block text-sm font-semibold text-violet-600 hover:text-violet-700">
                        Voir la liste →
                    </Link>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-500">Cartes vendues</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ props.stats.vendus }}</p>
                    <Link href="/monetique/cartes/vendus" class="mt-4 inline-block text-sm font-semibold text-violet-600 hover:text-violet-700">
                        Voir les vendus →
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
