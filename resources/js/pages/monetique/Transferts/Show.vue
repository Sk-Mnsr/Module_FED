<script setup lang="ts">
import CoficartePlasticPreview from '@/components/monetique/CoficartePlasticPreview.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRightLeft,
    Building2,
    ClipboardList,
    CreditCard,
    FileDown,
    FileText,
    Grid3x3,
    List,
    Package,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Intervenant = {
    name: string;
    email: string | null;
    badge: string;
    stats: { nb_cartes: number; nb_transferts: number };
};

type CarteRow = {
    numero_carte: string;
    reference_facture: string;
    date_livraison: string | null;
    /** ISO Y-m-d */
    date_expiration: string | null;
};

type SupplyRequestInfo = {
    id: number;
    quantite_demandee: number;
    quantite_livree: number;
    agence_nom: string;
};

const props = defineProps<{
    back_to: string;
    transfer: {
        id: number;
        status: string;
        debut_plage: string;
        fin_plage: string;
        bon_numero: string | null;
        commentaire: string;
        date_transfert: string | null;
        nb_cartes?: number;
        supply_request_completion?: 'cloturer_apres_reception' | 'poursuivre_demande' | null;
    };
    supply_request?: SupplyRequestInfo | null;
    envoyeur: Intervenant | null;
    receveur: Intervenant | null;
    cartes: CarteRow[];
    receptionniste_nom: string;
}>();

const backHref = computed(() => props.back_to || '/monetique/transferts/en-attente');

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Transferts', href: backHref.value },
    { title: 'Détail', href: '#' },
]);

const visuelCartes = ref(false);

const statutTransfert = computed(() => {
    const m: Record<string, { label: string; class: string }> = {
        en_attente: { label: 'En attente de réception', class: 'bg-amber-50 text-amber-950 border-amber-200' },
        valide: { label: 'Réception validée', class: 'bg-emerald-50 text-emerald-950 border-emerald-200' },
        rejete: { label: 'Rejeté', class: 'bg-rose-50 text-rose-950 border-rose-200' },
        annule: { label: 'Annulé', class: 'bg-gray-100 text-gray-800 border-gray-200' },
    };
    return m[props.transfer.status] ?? { label: props.transfer.status, class: 'bg-gray-50 text-gray-800 border-gray-200' };
});

const libelleCompletion = computed(() => {
    switch (props.transfer.supply_request_completion) {
        case 'cloturer_apres_reception':
            return 'Après réception : clôture de la demande (même en livraison partielle).';
        case 'poursuivre_demande':
            return 'Après réception : la demande reste ouverte tant que la quantité totale n’est pas atteinte.';
        default:
            return null;
    }
});

const nbCartesTotal = computed(() => props.transfer.nb_cartes ?? props.cartes.length);

function formatExpirationFr(iso: string | null | undefined): string {
    if (!iso?.trim()) {
        return '—';
    }
    const [y, m, d] = iso.split('-');
    if (!y || !m || !d) {
        return iso;
    }
    return `${d}/${m}/${y}`;
}

function expirationMmYy(iso: string | null | undefined): string | null {
    if (!iso?.trim()) {
        return null;
    }
    const [y, m] = iso.split('-');
    if (!y || !m) {
        return null;
    }
    return `${m}/${y.slice(-2)}`;
}

function tagLivraisonCarte(c: CarteRow) {
    return {
        title: 'Livré',
        subtitle: c.date_livraison?.trim() ? c.date_livraison : '—',
    };
}
</script>

<template>
    <Head :title="`Transfert #${transfer.id} - Monétique`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-6xl mx-auto w-full">
            <button
                type="button"
                class="inline-flex items-center gap-2 text-sm font-medium text-violet-700 hover:text-violet-900 w-fit rounded-lg -ml-1 px-1 py-0.5 hover:bg-violet-50 transition-colors"
                @click="router.visit(backHref)"
            >
                <ArrowLeft class="h-4 w-4" />
                Retour aux transferts
            </button>

            <!-- En-tête -->
            <div
                class="rounded-2xl border border-violet-200/80 bg-gradient-to-br from-violet-50/95 via-white to-white shadow-md shadow-violet-100/40 overflow-hidden"
            >
                <div class="p-6 sm:p-7 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
                    <div class="flex items-start gap-4 min-w-0">
                        <div class="rounded-xl bg-violet-600 text-white p-3 shadow-sm shrink-0">
                            <Package class="h-7 w-7" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 gap-y-2 mb-1">
                                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Détail du transfert</h1>
                                <span
                                    class="inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-semibold"
                                    :class="statutTransfert.class"
                                >
                                    {{ statutTransfert.label }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600">
                                <span class="font-mono tabular-nums">{{
                                    formatCardNumberDisplay(transfer.debut_plage)
                                }}</span>
                                <span class="mx-1.5 text-violet-400">→</span>
                                <span class="font-mono tabular-nums">{{
                                    formatCardNumberDisplay(transfer.fin_plage)
                                }}</span>
                            </p>
                            <p class="text-xs text-gray-500 mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span>Émis le {{ transfer.date_transfert ?? '—' }}</span>
                                <span class="hidden sm:inline text-gray-300">|</span>
                                <span class="tabular-nums font-medium text-gray-700">{{ nbCartesTotal }} carte(s)</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row lg:flex-col gap-2 shrink-0 lg:items-end">
                        <div
                            v-if="transfer.bon_numero"
                            class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-white/90 px-3 py-2 text-sm"
                        >
                            <span class="text-gray-500">Bon</span>
                            <span class="font-mono font-semibold text-gray-900">{{ transfer.bon_numero }}</span>
                        </div>
                        <a
                            v-if="transfer.bon_numero"
                            :href="`/monetique/transferts/${transfer.id}/bon-pdf`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors"
                        >
                            <FileDown class="h-4 w-4 shrink-0" />
                            Télécharger le PDF
                        </a>
                    </div>
                </div>

                <div
                    v-if="supply_request"
                    class="px-6 sm:px-7 py-4 border-t border-violet-100 bg-teal-50/50 flex flex-col sm:flex-row sm:items-start gap-3"
                >
                    <ClipboardList class="h-5 w-5 text-teal-700 shrink-0 mt-0.5" />
                    <div class="text-sm text-gray-800 space-y-1">
                        <p class="font-semibold text-gray-900">
                            Demande d’approvisionnement n° {{ supply_request.id }}
                            <span class="font-normal text-gray-500">— {{ supply_request.agence_nom }}</span>
                        </p>
                        <p class="text-xs sm:text-sm text-gray-600 tabular-nums">
                            Quantité demandée : {{ supply_request.quantite_demandee }} — déjà livré (côté demande) :
                            {{ supply_request.quantite_livree }}
                            <span class="text-gray-400">(mis à jour après réceptions validées)</span>
                        </p>
                        <p v-if="libelleCompletion" class="text-xs text-teal-900 bg-teal-100/80 border border-teal-200/80 rounded-lg px-3 py-2 mt-2">
                            {{ libelleCompletion }}
                        </p>
                    </div>
                </div>

                <div v-if="transfer.commentaire && transfer.commentaire !== 'RAS'" class="px-6 sm:px-7 py-4 border-t border-gray-100 bg-gray-50/70">
                    <div class="flex gap-2 items-start">
                        <FileText class="h-4 w-4 text-gray-500 shrink-0 mt-0.5" />
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Commentaire</p>
                            <p class="text-sm text-gray-800 mt-1 whitespace-pre-wrap">{{ transfer.commentaire }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Intervenants (compact) -->
            <section>
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Intervenants</h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <div
                        class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-sm flex gap-4 items-start"
                    >
                        <div class="rounded-lg bg-violet-100 p-2.5 text-violet-700 shrink-0">
                            <User class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Envoyeur</p>
                                    <p class="text-base font-bold text-gray-900 leading-tight">
                                        {{ envoyeur?.name ?? '—' }}
                                    </p>
                                </div>
                                <span
                                    v-if="envoyeur"
                                    class="text-[10px] font-semibold uppercase tracking-wide rounded-full bg-violet-50 text-violet-800 border border-violet-100 px-2 py-0.5 shrink-0"
                                >
                                    {{ envoyeur.badge }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 truncate mt-1">{{ envoyeur?.email ?? '—' }}</p>
                            <div class="flex flex-wrap gap-3 mt-3 text-xs text-gray-600">
                                <span class="inline-flex items-center gap-1 tabular-nums">
                                    <CreditCard class="h-3.5 w-3.5 text-violet-500" />
                                    <strong class="text-gray-900">{{ envoyeur?.stats.nb_cartes ?? '—' }}</strong> cartes
                                    créées
                                </span>
                                <span class="inline-flex items-center gap-1 tabular-nums">
                                    <ArrowRightLeft class="h-3.5 w-3.5 text-violet-500" />
                                    <strong class="text-gray-900">{{ envoyeur?.stats.nb_transferts ?? '—' }}</strong>
                                    transferts initiés
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-sm flex gap-4 items-start"
                    >
                        <div class="rounded-lg bg-emerald-100 p-2.5 text-emerald-700 shrink-0">
                            <Building2 class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Receveur</p>
                                    <p class="text-base font-bold text-gray-900 leading-tight">
                                        {{ receveur?.name ?? '—' }}
                                    </p>
                                </div>
                                <span
                                    v-if="receveur"
                                    class="text-[10px] font-semibold uppercase tracking-wide rounded-full bg-emerald-50 text-emerald-800 border border-emerald-100 px-2 py-0.5 shrink-0"
                                >
                                    {{ receveur.badge }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 truncate mt-1">{{ receveur?.email ?? '—' }}</p>
                            <div class="flex flex-wrap gap-3 mt-3 text-xs text-gray-600">
                                <span class="inline-flex items-center gap-1 tabular-nums">
                                    <CreditCard class="h-3.5 w-3.5 text-emerald-600" />
                                    <strong class="text-gray-900">{{ receveur?.stats.nb_cartes ?? '—' }}</strong> cartes
                                    agence
                                </span>
                                <span class="inline-flex items-center gap-1 tabular-nums">
                                    <ArrowRightLeft class="h-3.5 w-3.5 text-emerald-600" />
                                    <strong class="text-gray-900">{{ receveur?.stats.nb_transferts ?? '—' }}</strong>
                                    transferts reçus
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Cartes -->
            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 sm:px-5 py-3 border-b border-gray-100 bg-gray-50/80 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                        <CreditCard class="h-4 w-4 text-violet-600" />
                        Cartes ({{ cartes.length }})
                    </h2>
                    <div
                        v-if="cartes.length > 0"
                        class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-xs font-medium"
                    >
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition-colors"
                            :class="!visuelCartes ? 'bg-violet-100 text-violet-900 shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                            @click="visuelCartes = false"
                        >
                            <List class="h-3.5 w-3.5" />
                            Liste
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition-colors"
                            :class="visuelCartes ? 'bg-violet-100 text-violet-900 shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                            @click="visuelCartes = true"
                        >
                            <Grid3x3 class="h-3.5 w-3.5" />
                            Aperçu cartes
                        </button>
                    </div>
                </div>

                <p v-if="cartes.length === 0" class="text-sm text-amber-900 bg-amber-50/90 border-b border-amber-100 px-5 py-4">
                    Aucune carte trouvée pour ce transfert avec votre périmètre, ou les numéros ne correspondent pas au
                    stock actuel.
                </p>

                <!-- Mode liste -->
                <div v-else-if="!visuelCartes" class="overflow-x-auto max-h-[min(70vh,560px)] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-100/95 backdrop-blur border-b border-gray-200">
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-4 py-3">Numéro</th>
                                <th class="px-4 py-3 hidden sm:table-cell">Réf. facture</th>
                                <th class="px-4 py-3 hidden md:table-cell">Expiration</th>
                                <th class="px-4 py-3 text-right">Livraison</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="(c, idx) in cartes"
                                :key="`${c.numero_carte}-${idx}`"
                                class="hover:bg-violet-50/40 transition-colors"
                            >
                                <td class="px-4 py-2.5 font-mono tabular-nums font-medium text-gray-900">
                                    {{ formatCardNumberDisplay(c.numero_carte) }}
                                </td>
                                <td class="px-4 py-2.5 text-gray-700 hidden sm:table-cell">
                                    {{ c.reference_facture || '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-gray-600 tabular-nums whitespace-nowrap hidden md:table-cell">
                                    {{ formatExpirationFr(c.date_expiration) }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums whitespace-nowrap">
                                    {{ c.date_livraison ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mode aperçu -->
                <div v-else class="p-4 sm:p-5">
                    <p class="text-xs text-gray-500 mb-4">
                        Aperçu visuel —
                        <span class="font-medium text-gray-700">Réception documentaire : {{ receptionniste_nom }}</span>
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <CoficartePlasticPreview
                            v-for="(c, idx) in cartes"
                            :key="`vis-${c.numero_carte}-${idx}`"
                            :numero-carte="c.numero_carte"
                            :expiration="expirationMmYy(c.date_expiration)"
                            :tag-top-right="tagLivraisonCarte(c)"
                            :reference-facture="c.reference_facture || null"
                            :show-footer-note="false"
                            compact
                        />
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
