<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Printer } from 'lucide-vue-next';

interface FedItem {
    id: number;
    label: string;
    quantity: number | null;
    description?: string | null;
    unit_price?: number | null;
    total_price?: number | null;
}

interface FedFournisseur {
    id: number;
    nom: string;
    adresse_physique?: string | null;
    contact_telephone?: string | null;
    contact_email?: string | null;
}

interface FedFournisseurOffre {
    id?: number;
    fournisseur: string;
    conditions_paiement?: string | null;
    fournisseur_id?: number | null;
    fournisseur_relation?: FedFournisseur | null;
}

interface FedRequester {
    name: string;
    email?: string | null;
}

interface Fed {
    id: number;
    code: string;
    numero_bon_commande?: string | null;
    date_bon_commande?: string | null;
    adresse_livraison?: string | null;
    demandeur?: string | null;
    department?: string | null;
    beneficiaire?: string | null;
    motive?: string | null;
    items: FedItem[];
    offre_choisie?: FedFournisseurOffre | null;
    requester?: FedRequester | null;
    // Signatures
    facilities_signature?: string | null;
    facilities_validated_by?: string | null;
    facilities_signed_at?: string | null;
    daf_signature?: string | null;
    daf_validated_by?: string | null;
    daf_signed_at?: string | null;
    dga_signature?: string | null;
    dga_validated_by?: string | null;
    dga_signed_at?: string | null;
}

interface Props {
    fed: Fed;
    sousTotal: number;
    taxes: number;
    totalTTC: number;
    montantEnLettres: string;
    tauxTVA: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Bons de commande', href: '/bons-de-commande' },
    { title: props.fed.numero_bon_commande || props.fed.code, href: '#' },
];

const formatAmount = (value?: number | string | null) => {
    if (value === null || value === undefined || value === '') return '—';
    const val = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('fr-FR').format(val);
};

const formatDate = (value?: string | null) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatQuantity = (value?: number | string | null) => {
    if (value === null || value === undefined || value === '') return '—';
    const val = typeof value === 'string' ? parseFloat(value) : value;
    return Math.floor(val);
};

const clientNom = () => 'Cofina, Compagnie Financière Africaine';
const fournisseurNom = () => props.fed.offre_choisie?.fournisseur_relation?.nom || props.fed.offre_choisie?.fournisseur || '—';
const fournisseurAdresse = () => props.fed.offre_choisie?.fournisseur_relation?.adresse_physique || '—';
const fournisseurContact = () => {
    const tel = props.fed.offre_choisie?.fournisseur_relation?.contact_telephone;
    const email = props.fed.offre_choisie?.fournisseur_relation?.contact_email;
    if (tel && email) return `${tel} / ${email}`;
    return tel || email || '—';
};
const modePaiement = () => props.fed.offre_choisie?.conditions_paiement || 'Selon conditions contractuelles';
const expédiéAdresse = () => props.fed.adresse_livraison || props.fed.beneficiaire || props.fed.department || 'Avenue Birago Diop X Rue H, Point E';

const imprimer = () => window.print();
</script>

<template>
    <Head :title="`Bon de commande - ${props.fed.numero_bon_commande || props.fed.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 print:p-0">
            <div class="mb-4 flex justify-end gap-2 print:hidden">
                <Link href="/bons-de-commande">
                    <Button variant="outline">Retour</Button>
                </Link>
                <Button type="button" @click="imprimer">
                    <Printer class="mr-2 h-4 w-4" />
                    Imprimer
                </Button>
            </div>
            <div class="flex justify-center print:block">
                <div class="bon-commande-a4 w-[210mm] rounded-lg border border-gray-200 bg-white p-8 shadow-sm print:min-h-[297mm] print:rounded-none print:shadow-none">
                <!-- En-tête : logo + titre -->
                <div class="mb-8 flex items-start justify-between border-b border-gray-200 pb-6">
                    <div class="flex items-center gap-4">
                        <img src="/logo_Cofina.png" alt="Cofina" class="h-14 object-contain" />
                    </div>
                    <div class="text-right">
                        <h2 class="border-b-2 border-gray-600 pb-1 text-xl font-bold uppercase text-gray-600">Bon de Commande</h2>
                        <p class="mt-2 text-sm font-semibold text-gray-700">N° : {{ props.fed.numero_bon_commande || props.fed.code }}</p>
                        <p class="text-sm text-gray-600">Date : {{ formatDate(props.fed.date_bon_commande) }}</p>
                    </div>
                </div>

                <!-- Client (émetteur)  -->
                <div class="mb-6 grid grid-cols-2 gap-6 border-b border-gray-200 pb-6">
                    <div>
                        <p class="text-xs font-bold uppercase text-gray-500 mb-2">Adresse de livraison</p>
                        <p class="font-bold text-gray-800">COFINA</p>
                        <p class="mt-1 text-sm text-gray-600">{{ expédiéAdresse() }}</p>
                        <p class="mt-1 text-sm text-gray-600">Dakar - Sénégal</p>
                        <p class="mt-1 text-sm text-gray-600">www.cofinasenegal.com</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase text-gray-500 mb-2">Fournisseur</p>
                        <p class="font-bold text-gray-800">{{ fournisseurNom() }}</p>
                        <p class="mt-1 text-sm text-gray-600"><span class="font-medium">Adresse :</span> {{ fournisseurAdresse() }}</p>
                        <p class="mt-1 text-sm text-gray-600"><span class="font-medium">Contact :</span> {{ fournisseurContact() }}</p>
                        <p class="mt-1 text-sm text-gray-600"><span class="font-medium">Conditions :</span> {{ modePaiement() }}</p>
                    </div>
                </div>

                <!-- Tableau des articles -->
                <div class="mb-6 overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-red-500 text-white">
                                <th class="px-4 py-3 text-left font-medium">Description</th>
                                <th class="px-4 py-3 text-right font-medium">Quantité</th>
                                <th class="px-4 py-3 text-right font-medium">Prix Unitaire</th>
                                <th class="px-4 py-3 text-right font-medium">Total HT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="props.fed.items?.length">
                                <tr v-for="(item, idx) in props.fed.items" :key="item.id" :class="idx % 2 === 0 ? 'bg-gray-50' : 'bg-white'" class="border-b border-gray-200">
                                    <td class="px-4 py-3">{{ item.label }}</td>
                                    <td class="px-4 py-3 text-center">{{ formatQuantity(item.quantity) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatAmount(item.unit_price) }}</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ formatAmount(item.total_price) }}</td>
                                </tr>
                            </template>
                            <tr v-else class="border-b border-gray-200 bg-gray-50">
                                <td class="px-4 py-3">{{ props.fed.motive || 'Prestation' }}</td>
                                <td class="px-4 py-3 text-right">1</td>
                                <td class="px-4 py-3 text-right">{{ formatAmount(props.sousTotal) }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ formatAmount(props.sousTotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Récapitulatif financier -->
                <div class="mb-6 flex justify-end">
                    <div class="w-80 space-y-2 border-t border-gray-200 pt-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Sous-Total</span>
                            <span class="font-medium">{{ formatAmount(props.sousTotal) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">TVA ({{ Math.round((props.tauxTVA || 0.18) * 100) }}%)</span>
                            <span class="font-medium">{{ formatAmount(props.taxes) }}</span>
                        </div>
                        <div class="flex justify-between bg-red-500 px-4 py-3 text-white">
                            <span class="font-bold">Total TTC</span>
                            <span class="font-bold">{{ formatAmount(props.totalTTC) }}</span>
                        </div>
                    </div>
                </div>
                <p class="mb-6 text-xs text-gray-600">
                    Montant TTC en lettres : {{ props.montantEnLettres }}
                </p>

                <!-- Bloc de validation -->
                <div class="border-t border-gray-200 pt-6">
                    <table class="w-full border-collapse border border-gray-300 text-sm">
                        <thead>
                            <tr class="bg-slate-100 text-gray-900">
                                <th class="border border-gray-300 px-4 py-2 text-center font-bold uppercase text-xs w-1/3">Responsable Facilities</th>
                                <th class="border border-gray-300 px-4 py-2 text-center font-bold uppercase text-xs w-1/3">Directeur Admin. & Financier</th>
                                <th class="border border-gray-300 px-4 py-2 text-center font-bold uppercase text-xs w-1/3">DGA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="h-32">
                                <td class="border border-gray-300 px-4 py-3 align-top">
                                    <div v-if="props.fed.facilities_signature" class="flex flex-col items-center">
                                        <img :src="props.fed.facilities_signature" class="max-h-24 max-w-full object-contain mix-blend-multiply" />
                                        <p class="mt-auto text-[10px] font-medium text-gray-500">{{ props.fed.facilities_validated_by }}</p>
                                        <p class="text-[9px] text-gray-400">{{ formatDate(props.fed.facilities_signed_at) }}</p>
                                    </div>
                                    <div v-else class="flex h-full items-center justify-center italic text-gray-300">
                                        Signature
                                    </div>
                                </td>
                                <td class="border border-gray-300 px-4 py-3 align-top">
                                    <div v-if="props.fed.daf_signature" class="flex flex-col items-center">
                                        <img :src="props.fed.daf_signature" class="max-h-24 max-w-full object-contain mix-blend-multiply" />
                                        <p class="mt-auto text-[10px] font-medium text-gray-500">{{ props.fed.daf_validated_by }}</p>
                                        <p class="text-[9px] text-gray-400">{{ formatDate(props.fed.daf_signed_at) }}</p>
                                    </div>
                                    <div v-else class="flex h-full items-center justify-center italic text-gray-300">
                                        Signature
                                    </div>
                                </td>
                                <td class="border border-gray-300 px-4 py-3 align-top">
                                    <div v-if="props.fed.dga_signature" class="flex flex-col items-center">
                                        <img :src="props.fed.dga_signature" class="max-h-24 max-w-full object-contain mix-blend-multiply" />
                                        <p class="mt-auto text-[10px] font-medium text-gray-500">{{ props.fed.dga_validated_by }}</p>
                                        <p class="text-[9px] text-gray-400">{{ formatDate(props.fed.dga_signed_at) }}</p>
                                    </div>
                                    <div v-else class="flex h-full items-center justify-center italic text-gray-300">
                                        Signature
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.bon-commande-a4 {
    width: 210mm;
    min-height: 297mm;
}

@media print {
    @page {
        size: A4;
        margin: 15mm;
    }

    body {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .print\:hidden {
        display: none !important;
    }

    .print\:p-0 {
        padding: 0 !important;
    }

    .print\:min-h-\[297mm\] {
        min-height: 297mm !important;
    }

    .print\:rounded-none {
        border-radius: 0 !important;
    }

    .print\:shadow-none {
        box-shadow: none !important;
    }

    .bon-commande-a4 {
        width: 100% !important;
        max-width: none !important;
        box-shadow: none !important;
        border: 1px solid #e5e7eb !important;
    }
}
</style>
