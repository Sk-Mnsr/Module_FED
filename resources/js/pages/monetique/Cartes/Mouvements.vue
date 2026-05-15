<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CoficartePlasticPreview from '@/components/monetique/CoficartePlasticPreview.vue';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, History } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    card: { id: number; numero_carte: string; expiration_plastic?: string | null };
    mouvements: {
        id: number;
        event_type: string;
        meta: Record<string, unknown> | null;
        acteur: string;
        date: string;
    }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'En stock', href: '/monetique/cartes/en-stock' },
    { title: 'Mouvements', href: '#' },
];

/** Libellés métier : les `event_type` en base sont des codes techniques pour le logger. */
const EVENT_LABELS: Record<string, string> = {
    carte_creee: 'Carte créée',
    prix_vente_maj: 'Prix de vente modifié',
    transfert_en_attente: 'Transfert vers agence (en attente de réception)',
    transfert_recu_agence: 'Transfert réceptionné en agence',
    assignation_cc: 'Attribution à un chargé de clientèle',
    delester_vers_chef_agence: 'Délestage vers le chef d’agence (pool agence)',
    retour_siege: 'Retour des cartes au siège',
    vente_initiee: 'Vente initiée (attente caisse)',
    vente_encaissee: 'Vente encaissée — carte activée',
    recharge_initiee: 'Recharge initiée',
    recharge_encaissee: 'Recharge encaissée',
};

const EVENT_HINTS: Record<string, string> = {
    carte_creee: 'Enregistrement en stock.',
    transfert_en_attente: 'Expédition depuis la monétique centrale.',
    transfert_recu_agence: 'Arrivée confirmée au niveau agence.',
    assignation_cc: 'La carte est passée en poche d’un CC.',
    delester_vers_chef_agence: 'La carte revient dans le stock non attribué de l’agence.',
    vente_initiee: 'Passage en attente d’encaissement caisse.',
    vente_encaissee: 'Statut carte : vendue.',
};

const META_LABELS: Record<string, string> = {
    agence_id: 'Agence (réf.)',
    assigned_to_user_id: 'Utilisateur attribué (réf.)',
    transfer_id: 'N° transfert interne',
    bon_numero: 'Bon d’approvisionnement',
    reference_facture: 'Référence facture',
    sale_id: 'N° vente interne',
};

function eventTitle(type: string): string {
    return EVENT_LABELS[type] ?? humanizeSlug(type);
}

function eventHint(type: string): string | null {
    return EVENT_HINTS[type] ?? null;
}

function humanizeSlug(s: string): string {
    const spaced = s.replaceAll('_', ' ');
    return spaced.length ? spaced.charAt(0).toUpperCase() + spaced.slice(1) : spaced;
}

function formatMetaValue(raw: unknown): string {
    if (raw === null || raw === undefined) {
        return '—';
    }
    if (typeof raw === 'string' || typeof raw === 'number' || typeof raw === 'boolean') {
        return String(raw);
    }
    return JSON.stringify(raw);
}

const metaRows = (meta: Record<string, unknown> | null) => {
    if (!meta || typeof meta !== 'object' || Object.keys(meta).length === 0) {
        return [];
    }
    return Object.entries(meta).map(([key, raw]) => ({
        key,
        label: META_LABELS[key] ?? humanizeSlug(key),
        value: formatMetaValue(raw),
    }));
};

const timeline = computed(() =>
    props.mouvements.map((m) => ({
        ...m,
        title: eventTitle(m.event_type),
        hint: eventHint(m.event_type),
        details: metaRows(m.meta),
    })),
);
</script>

<template>
    <Head title="Historique mouvements carte" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 w-full flex-1 flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8 xl:px-10 2xl:px-12">
            <button
                type="button"
                class="inline-flex items-center gap-2 text-sm font-medium text-violet-700 hover:text-violet-900 w-fit"
                @click="router.visit('/monetique/cartes/en-stock')"
            >
                <ArrowLeft class="h-4 w-4" />
                Retour
            </button>

            <div class="flex items-center gap-3">
                <div class="p-3 bg-violet-100 text-violet-700 rounded-xl">
                    <History class="h-6 w-6" />
                </div>
                <div class="min-w-0 flex-1">
                    <h1 class="text-2xl font-bold text-gray-900">Mouvements</h1>
                    <p class="font-mono text-sm text-gray-600 mt-1">{{ formatCardNumberDisplay(card.numero_carte) }}</p>
                    <p class="text-xs text-gray-500 mt-2 max-w-4xl leading-relaxed lg:max-w-none">
                        Historique des opérations enregistrées automatiquement. Les détails proviennent des métadonnées
                        techniques sauvegardées avec chaque événement.
                    </p>
                </div>
            </div>

            <div
                class="grid w-full flex-1 grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(300px,26rem)] lg:gap-10 xl:gap-12 lg:items-start"
            >
                <aside
                    class="order-first flex shrink-0 justify-center lg:order-2 lg:justify-end lg:pl-2 xl:pl-4 lg:sticky lg:top-6 lg:self-start"
                >
                    <CoficartePlasticPreview
                        class="w-full max-w-[26rem] lg:max-w-[28rem] xl:max-w-[30rem]"
                        :numero-carte="card.numero_carte"
                        :expiration="card.expiration_plastic"
                    />
                </aside>

                <div
                    class="order-last lg:order-1 min-w-0 rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100"
                >
                    <div v-if="!timeline.length" class="p-6 text-sm text-gray-500">Aucun mouvement enregistré.</div>
                    <div v-for="m in timeline" :key="m.id" class="p-5 text-sm">
                        <div class="flex flex-col gap-1">
                            <p class="font-semibold text-gray-900 leading-snug">{{ m.title }}</p>
                            <p v-if="m.hint" class="text-xs text-gray-600">{{ m.hint }}</p>
                            <p class="text-xs text-gray-500">
                                <span class="tabular-nums">{{ m.date }}</span>
                                <span class="mx-1.5 text-gray-300">·</span>
                                <span>{{ m.acteur }}</span>
                            </p>
                        </div>
                        <dl
                            v-if="m.details.length"
                            class="mt-3 grid gap-2 sm:grid-cols-2 rounded-lg border border-gray-100 bg-gray-50/80 px-3 py-3"
                        >
                            <template v-for="row in m.details" :key="row.key">
                                <dt class="text-xs font-medium text-gray-500">{{ row.label }}</dt>
                                <dd class="text-xs text-gray-900 font-mono tabular-nums break-all sm:text-right">{{ row.value }}</dd>
                            </template>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
