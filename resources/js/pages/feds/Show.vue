<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Printer } from 'lucide-vue-next';
import ValidationHistoryModal from '@/components/ValidationHistoryModal.vue';

interface FedItem {
    id: number;
    label: string;
    quantity: number | null;
    description?: string | null;
    unit_price?: number | null;
    total_price?: number | null;
    budget_line?: BudgetLine | null;
}

interface FedAttachment {
    id: number;
    original_name: string;
    path: string;
}

interface BudgetLine {
    code?: string | null;
    label?: string | null;
}

interface Fed {
    id: number;
    code: string;
    date?: string | null;
    demandeur?: string | null;
    n1_avis?: string | null;
    n1_comment?: string | null;
    n1_action_at?: string | null;
    department?: string | null;
    fonction?: string | null;
    category?: string | null;
    subcategory?: string | null;
    beneficiaire?: string | null;
    motive?: string | null;
    estimated_total?: number | null;
    priority?: string | null;
    status: string;
    requester_signature?: string | null;
    n1_signature?: string | null;
    items: FedItem[];
    attachments: FedAttachment[];
    budget_line?: BudgetLine | null;
    budget_lines?: BudgetLine[];
    offre_choisie_id?: number | null;
    fournisseur_offres?: FedFournisseurOffre[];
}

interface OffreAttachment {
    id: number;
    original_name: string;
    path: string;
}

interface FedFournisseurOffre {
    id?: number;
    fournisseur: string;
    fournisseur_id?: number | null;
    fed_item_id?: number | null;
    montant?: number | null;
    prix_unitaire?: number | null;
    delais_livraison?: string | null;
    garanties_offertes?: string | null;
    conformite_reglementaire?: string | null;
    attachments?: OffreAttachment[];
}

interface Props {
    fed: Fed;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Fiches de dépense', href: '/feds' },
    { title: props.fed.code, href: '#' },
];

const formatAmount = (value?: number | string | null) => {
    if (value === null || value === undefined || value === '') return '—';
    const val = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('fr-FR').format(val);
};

const formatQuantity = (value?: number | string | null) => {
    if (value === null || value === undefined || value === '') return '—';
    const val = typeof value === 'string' ? parseFloat(value) : value;
    return Math.floor(val);
};

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatBudgetLines = (fed: Fed) => {
    // On récupère toutes les lignes budgétaires uniques des articles
    const uniqueCodes = [...new Set(fed.items?.filter(item => item.budget_line?.code).map(item => item.budget_line!.code))];
    if (uniqueCodes.length > 0) {
        return uniqueCodes.join(' ; ');
    }
    return '—';
};

const getArticleQuantity = (itemId?: number | null) => {
    if (!itemId) return 1;
    const item = props.fed.items?.find(i => i.id == itemId);
    return item ? (item.quantity ?? 1) : 1;
};

const expandedGroups = ref<Record<number, boolean>>({});

const toggleDetails = (id: number) => {
    expandedGroups.value[id] = !expandedGroups.value[id];
};

interface SupplierGroup {
    id: number;
    fournisseur: string;
    fournisseur_id: number | null;
    total: number;
    offres: FedFournisseurOffre[];
    representative_id: number;
    delais: string;
    garantie: string;
    conformite: string;
    attachments: OffreAttachment[];
}

const groupedOffres = computed(() => {
    const existing = props.fed.fournisseur_offres ?? [];
    const groups: Record<string, SupplierGroup> = {};

    existing.forEach(o => {
        const key = o.fournisseur_id ? `id_${o.fournisseur_id}` : `name_${o.fournisseur}`;
        if (!groups[key]) {
            groups[key] = {
                id: o.id ?? Math.random(),
                fournisseur: o.fournisseur,
                fournisseur_id: o.fournisseur_id ?? null,
                total: 0,
                offres: [],
                representative_id: o.id ?? 0,
                delais: o.delais_livraison ?? '—',
                garantie: o.garanties_offertes ?? '—',
                conformite: o.conformite_reglementaire ?? '—',
                attachments: o.attachments ?? []
            };
        }
        groups[key].offres.push(o);
        const qty = getArticleQuantity(o.fed_item_id);
        groups[key].total += (o.prix_unitaire ?? 0) * qty;
    });

    return Object.values(groups);
});

const printDocument = () => {
    window.print();
};

</script>

<template>
    <Head :title="`FED ${props.fed.code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl 2xl:max-w-none p-6 2xl:px-12">
            <!-- Actions (hidden when printing) -->
            <div class="mb-6 flex justify-end gap-2 print:hidden">
                <Link :href="`/feds/${props.fed.id}/edit`">
                    <Button variant="outline" class="2xl:h-12 2xl:px-8 2xl:text-base">Modifier</Button>
                </Link>
                <!-- <Button variant="outline" @click="printDocument">
                    <Printer class="mr-2 h-4 w-4" /> Imprimer
                </Button> -->
                <Link href="/feds">
                    <Button variant="outline" class="2xl:h-12 2xl:px-8 2xl:text-base">Retour</Button>
                </Link>
            </div>

            <!-- Document format -->
            <div class="rounded-lg border-2 border-gray-900 bg-white p-6 shadow-sm print:border print:shadow-none 2xl:p-12">
                <!-- Header -->
                <div class="mb-8 flex items-start justify-between border-b border-gray-300 pb-4">
                    <div class="flex items-center gap-3">
                        <img src="/logo_Cofina.png" alt="Cofina" class="h-14 2xl:h-24 object-contain" />
                    </div>
                    <div class="text-right">
                        <h1 class="text-xl 2xl:text-3xl font-bold uppercase text-gray-900">Fiche d'Engagement de dépense</h1>
                        <p class="mt-1 text-sm 2xl:text-lg font-medium">
                            Réf. : FED n°
                            <span class="inline-block min-w-[120px] 2xl:min-w-[200px] border-b border-gray-400 font-semibold">{{ props.fed.code }}</span>
                        </p>
                    </div>
                </div>

                <!-- DEMANDE section -->
                <div class="mb-6 border-2 border-gray-900 p-4 2xl:p-8">
                    <h2 class="mb-4 text-base 2xl:text-xl font-bold uppercase">Demande</h2>
                    <div class="grid gap-3 text-sm 2xl:text-lg">
                        <div class="grid grid-cols-[140px_1fr] 2xl:grid-cols-[200px_1fr] gap-2">
                            <span class="font-medium">Date :</span>
                            <span>{{ formatDate(props.fed.date) }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] 2xl:grid-cols-[200px_1fr] gap-2">
                            <span class="font-medium">Demandeur :</span>
                            <span class="uppercase font-bold">{{ props.fed.demandeur || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] 2xl:grid-cols-[200px_1fr] gap-2">
                            <span class="font-medium">Fonction :</span>
                            <span class="uppercase">{{ props.fed.fonction || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] 2xl:grid-cols-[200px_1fr] gap-2">
                            <span class="font-medium">Département :</span>
                            <span>{{ props.fed.department || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] 2xl:grid-cols-[200px_1fr] gap-2 rounded bg-red-50/80 border-l-4 border-red-500 px-2 py-1.5 2xl:py-3 shadow-sm">
                            <span class="font-bold text-red-800">Ligne(s) budgétaire(s) :</span>
                            <span class="font-bold text-red-900 uppercase text-lg">{{ formatBudgetLines(props.fed) }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] 2xl:grid-cols-[200px_1fr] gap-2">
                            <span class="font-medium">Motif de la dépense :</span>
                            <span class="uppercase">{{ props.fed.motive || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] 2xl:grid-cols-[200px_1fr] gap-2">
                            <span class="font-medium">Bénéficiaire(s) :</span>
                            <span>{{ props.fed.beneficiaire || '—' }}</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] 2xl:grid-cols-[200px_1fr] gap-2">
                            <span class="font-medium">Fournisseur :</span>
                            <span>—</span>
                        </div>
                        <div class="grid grid-cols-[140px_1fr] 2xl:grid-cols-[200px_1fr] gap-2">
                            <span class="font-medium">Pro-forma :</span>
                            <span>☐ oui Réf n° .................... ☐ non</span>
                        </div>

                        <!-- Signatures row - égales de part et d'autre -->
                        <div class="mt-6 grid grid-cols-2 gap-8 border-t border-gray-300 pt-6">
                            <div class="flex flex-col items-center justify-center">
                                <div class="mb-2 flex h-24 2xl:h-40 w-full max-w-[250px] 2xl:max-w-[400px] items-center justify-center border-2 border-gray-500 bg-gray-50 p-2">
                                    <img
                                        v-if="props.fed.requester_signature"
                                        :src="props.fed.requester_signature"
                                        alt="Signature demandeur"
                                        class="max-h-full max-w-full object-contain"
                                    />
                                </div>
                                <span class="text-xs 2xl:text-base font-medium">Signature demandeur</span>
                            </div>
                            <div class="flex flex-col items-center justify-center">
                                <div class="mb-2 flex h-24 2xl:h-40 w-full max-w-[250px] 2xl:max-w-[400px] items-center justify-center border-2 border-gray-500 bg-gray-50 p-2">
                                    <img
                                        v-if="props.fed.n1_signature"
                                        :src="props.fed.n1_signature"
                                        alt="Signature Manager"
                                        class="max-h-full max-w-full object-contain"
                                    />
                                </div>
                                <span class="text-xs 2xl:text-base font-medium">Nom & Signature Manager</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Articles / Services -->
                <div v-if="props.fed.items?.length" class="mb-6 border border-gray-400 p-4 2xl:p-8">
                    <h2 class="mb-4 text-sm 2xl:text-xl font-bold uppercase">Articles / Services</h2>
                    <table class="w-full text-sm 2xl:text-lg">
                        <thead>
                            <tr class="border-b border-gray-400 bg-gray-100 uppercase text-[11px] 2xl:text-sm text-gray-700">
                                <th class="px-2 py-2 2xl:py-4 text-left font-bold">Ligne(s) Budgétaire(s)</th>
                                <th class="px-2 py-2 2xl:py-4 text-left font-bold">Intitulé</th>
                                <th class="px-2 py-2 2xl:py-4 text-center font-bold">Quantité</th>
                                <th class="px-2 py-2 2xl:py-4 text-center font-bold">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in props.fed.items" :key="item.id" class="border-b border-gray-200">
                                <td class="px-2 py-2 2xl:py-4 font-bold text-red-700 uppercase">{{ item.budget_line?.code || '—' }}</td>
                                <td class="px-2 py-2 2xl:py-4 uppercase font-medium">{{ item.label }}</td>
                                <td class="px-2 py-2 2xl:py-4 text-center font-bold">{{ formatQuantity(item.quantity) }}</td>
                                <td class="px-2 py-2 2xl:py-4 text-xs 2xl:text-base text-gray-600 italic leading-relaxed">{{ item.description || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pièces jointes -->
                <div v-if="props.fed.attachments?.length" class="mb-6 border border-gray-400 p-4">
                    <h2 class="mb-3 text-sm font-bold uppercase">Pièces jointes</h2>
                    <div class="space-y-2 text-sm">
                        <a
                            v-for="attachment in props.fed.attachments"
                            :key="attachment.id"
                            :href="`/storage/${attachment.path}`"
                            target="_blank"
                            class="block text-blue-600 hover:text-blue-700"
                        >
                            {{ attachment.original_name }}
                        </a>
                    </div>
                </div>

                <!-- Offre retenue (si disponible) -->
                <div v-if="props.fed.offre_choisie_id && groupedOffres.length" class="mb-6">
                    <h2 class="mb-3 text-sm font-bold uppercase text-blue-900 flex items-center gap-2">
                        <span class="inline-flex items-center justify-center h-5 w-5 rounded-full bg-blue-600 text-white text-[10px]">!</span>
                        Offre retenue par Facilities
                    </h2>
                    
                    <template v-for="(group, gIdx) in groupedOffres" :key="gIdx">
                        <div 
                            v-if="props.fed.offre_choisie_id === group.representative_id || group.offres.some(o => o.id === props.fed.offre_choisie_id)"
                            class="overflow-hidden rounded-xl border-2 border-green-500 bg-green-50/20 ring-4 ring-green-50 shadow-sm"
                        >
                            <!-- Bandeau supérieur du fournisseur -->
                            <div 
                                class="flex items-center gap-4 px-6 py-4 cursor-pointer select-none bg-green-50 border-b border-green-100"
                                @click="toggleDetails(group.id)"
                            >
                                <div class="flex-shrink-0">
                                    <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-green-600 text-white font-bold">✓</span>
                                </div>

                                <div class="flex-grow">
                                    <div class="flex items-center justify-between font-bold">
                                        <div class="flex items-center gap-3">
                                            <h3 class="text-gray-900 uppercase text-lg">{{ group.fournisseur }}</h3>
                                            <svg 
                                                xmlns="http://www.w3.org/2000/svg" 
                                                class="h-4 w-4 text-gray-400 transition-transform" 
                                                :class="{ 'rotate-180': expandedGroups[group.id] }"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            >
                                                <path d="m6 9 6 6 6-6"/>
                                            </svg>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-[10px] font-bold uppercase text-gray-500 mb-0.5">Montant total TTC </div>
                                            <div class="text-xl font-black text-blue-900">{{ formatAmount(group.total) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ligne des conditions (toujours visible) -->
                            <div class="px-6 py-2 bg-white flex items-center gap-6 border-b border-gray-50">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase text-gray-400">Délai:</span>
                                    <span class="text-xs font-semibold text-gray-700">{{ group.delais }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase text-gray-400">Garantie:</span>
                                    <span class="text-xs font-semibold text-gray-700">{{ group.garantie }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase text-gray-400">Conformité:</span>
                                    <span class="text-xs font-bold text-green-600">{{ group.conformite }}</span>
                                </div>
                                <div class="flex-grow"></div>
                                <button 
                                    @click="toggleDetails(group.id)"
                                    class="text-[10px] font-bold uppercase text-blue-600 hover:text-blue-800"
                                >
                                    {{ expandedGroups[group.id] ? 'Masquer le détail' : 'Voir le détail des articles' }}
                                </button>
                            </div>

                            <!-- Détail par article (conditionnel) -->
                            <div v-show="expandedGroups[group.id]" class="p-4 bg-gray-50/30 border-b border-gray-100 animate-in fade-in slide-in-from-top-1 duration-200">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-100/50 text-[10px] font-bold uppercase text-gray-500">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Article / Service</th>
                                            <th class="px-3 py-2 text-center w-20">Qté</th>
                                            <th class="px-3 py-2 text-right w-32">P.U.</th>
                                            <th class="px-3 py-2 text-right w-32 bg-blue-50/50">Total TTC</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="o in group.offres" :key="o.id" class="hover:bg-white transition-colors">
                                            <td class="px-3 py-2 font-medium text-gray-800">
                                                {{ props.fed.items?.find(i => i.id === o.fed_item_id)?.label || 'Montant global' }}
                                            </td>
                                            <td class="px-3 py-2 text-center text-gray-500">
                                                {{ formatQuantity(getArticleQuantity(o.fed_item_id)) }}
                                            </td>
                                            <td class="px-3 py-2 text-right text-gray-600">
                                                {{ formatAmount(o.prix_unitaire) }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-bold text-blue-800 bg-blue-50/30">
                                                {{ formatAmount((o.prix_unitaire ?? 0) * getArticleQuantity(o.fed_item_id)) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pied de carte : Documents -->
                            <div class="bg-gray-100/30 px-6 py-2.5 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-2">
                                    <template v-if="group.attachments.length">
                                        <a 
                                            v-for="att in group.attachments" 
                                            :key="att.id" 
                                            :href="`/storage/${att.path}`" 
                                            target="_blank" 
                                            class="flex items-center gap-2 rounded bg-white px-3 py-1.5 text-[11px] font-bold text-blue-600 shadow-sm border border-blue-100 hover:bg-blue-50 transition-colors"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.51a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                            Voir le devis
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Historique des validations -->
                <div class="mb-4">
                    <ValidationHistoryModal :fed="props.fed" />
                </div>

                <!-- Validation sections -->
                <!-- <div class="space-y-4">
                    <div v-for="(section, i) in ['Validation DAF', 'Validation DAL', 'Validation responsable 1', 'Validation responsable 2']" :key="i" class="border border-gray-400 p-4">
                        <h3 class="mb-3 text-sm font-bold">{{ section }}</h3>
                        <div class="grid gap-2 text-sm">
                            <div class="flex gap-4">
                                <span>☐ oui</span>
                                <span>☐ non</span>
                                <span v-if="i < 2">(Nombre de signatures requises : .........)</span>
                            </div>
                            <div>
                                <span class="font-medium">Réserve :</span>
                                <span class="ml-2 border-b border-dotted border-gray-500">...................................................</span>
                            </div>
                            <div class="flex gap-8">
                                <div>
                                    <span class="font-medium">Montant (chiffre) :</span>
                                    <span class="ml-2 border-b border-dotted border-gray-500">........................</span>
                                </div>
                                <div>
                                    <span class="font-medium">Montant (lettre) :</span>
                                    <span class="ml-2 border-b border-dotted border-gray-500">....................................................................................</span>
                                </div>
                            </div>
                            <div class="mt-2">
                                <span class="font-medium">{{ i < 2 ? 'Signature' : 'Nom et signature' }} :</span>
                                <span class="ml-2 border-b border-dotted border-gray-500">....................................................................................</span>
                            </div>
                        </div>
                    </div>
                </div> -->
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    .print\:hidden {
        display: none !important;
    }
    .print\:border {
        border-width: 1px;
    }
    .print\:shadow-none {
        box-shadow: none;
    }
}
</style>
