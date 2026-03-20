<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';
import { Plus, Trash2, ArrowLeft, Paperclip } from 'lucide-vue-next';
import SupplierCombobox from '@/components/SupplierCombobox.vue';

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
    prix_unitaire?: number | null;
    delais_livraison?: string | null;
    garanties_offertes?: string | null;
    conformite_reglementaire?: string | null;
    acompte_requis?: string | null;
    pourcentage_acompte?: number | null;
    attachments?: OffreAttachment[];
}

interface Fournisseur {
    id: number;
    nom: string;
    type?: string | null;
    categorie?: string | null;
}

interface FedItem {
    id: number;
    label: string;
    quantity: number;
}

interface Fed {
    id: number;
    code: string;
    motive?: string | null;
    department?: string | null;
    demandeur?: string | null;
    status: string;
    items?: FedItem[];
    expert_opinion_offre_id?: number | null;
}

interface Props {
    fed: Fed & { fournisseur_offres?: FedFournisseurOffre[] };
    fournisseurs: Fournisseur[];
}

type OffreWithFile = FedFournisseurOffre & { _file?: File | null };

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Demandes en cours', href: '/feds/achats' },
    { title: props.fed.code, href: `/feds/achats/${props.fed.id}` },
    { title: 'Tableau comparatif', href: '#' },
];

const canEdit = computed(() => ['n1_approved', 'achats_needs_info'].includes(props.fed.status));

const items = computed(() => props.fed.items ?? []);

interface FournisseurOffreGroup {
    fournisseur: string;
    fournisseur_id: number | null;
    delais_livraison: string;
    garanties_offertes: string;
    conformite_reglementaire: string | null;
    acompte_requis: string | null;
    pourcentage_acompte: number | null;
    _file: File | null;
    prices: Record<number, number | null>; // Partial map of item_id -> price
    attachments?: OffreAttachment[];
    id?: number;
}

const makeEmptyGroup = (): FournisseurOffreGroup => ({
    fournisseur: '',
    fournisseur_id: null,
    delais_livraison: '',
    garanties_offertes: '',
    conformite_reglementaire: null,
    acompte_requis: null,
    pourcentage_acompte: null,
    _file: null,
    prices: {},
});

// Initialisation des offres regroupées par fournisseur
const initFournisseurOffres = () => {
    const existing = props.fed.fournisseur_offres ?? [];
    if (existing.length === 0) return [makeEmptyGroup()];

    // Regrouper par fournisseur (nom ou id)
    const groups: Record<string, FournisseurOffreGroup> = {};
    
    existing.forEach(o => {
        const key = o.fournisseur_id ? `id_${o.fournisseur_id}` : `name_${o.fournisseur}`;
        if (!groups[key]) {
            groups[key] = {
                fournisseur: o.fournisseur,
                fournisseur_id: o.fournisseur_id ?? null,
                delais_livraison: o.delais_livraison ?? '',
                garanties_offertes: o.garanties_offertes ?? '',
                conformite_reglementaire: o.conformite_reglementaire ?? null,
                acompte_requis: o.acompte_requis ?? null,
                pourcentage_acompte: o.pourcentage_acompte ?? null,
                _file: null,
                prices: {},
                attachments: o.attachments,
            };
        }
        if (o.fed_item_id) {
            groups[key].prices[o.fed_item_id] = o.prix_unitaire ?? null;
        } else {
            // Cas global (sans items)
            groups[key].prices[0] = o.prix_unitaire ?? null;
        }
    });

    return Object.values(groups).map((g, index) => {
        // Associer l'id de l'offre si elle existe dans la FED pour repérer la recommandation expert
        const representative = existing.find(o => 
            (o.fournisseur_id === g.fournisseur_id && g.fournisseur_id) || 
            (o.fournisseur === g.fournisseur && !g.fournisseur_id)
        );
        return { ...g, id: representative?.id };
    });
};

const internalFournisseurOffres = ref(initFournisseurOffres());
const fournisseurOffres = computed(() => internalFournisseurOffres.value);

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

// ── Actions ──────────────────────────────────────────────────

const addFournisseur = () => {
    fournisseurOffres.value.push(makeEmptyGroup());
};

const removeFournisseur = (index: number) => {
    if (fournisseurOffres.value.length > 1) {
        fournisseurOffres.value.splice(index, 1);
    }
};

const onFournisseurSelect = (index: number, id: number | null) => {
    const fid = id;
    const group = fournisseurOffres.value[index];
    group.fournisseur_id = fid;
    const found = fid ? props.fournisseurs.find(f => f.id === fid) : null;
    group.fournisseur = found?.nom ?? '';
};

const triggerFileInput = (index: number) => {
    (document.getElementById(`file-${index}`) as HTMLInputElement | null)?.click();
};

const onFileSelect = (index: number, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) fournisseurOffres.value[index]._file = file;
    input.value = '';
};

const saveOffres = () => {
    // Vérifier qu'au moins un fournisseur est rempli
    const validGroups = fournisseurOffres.value.filter(g => g.fournisseur_id || g.fournisseur.trim());
    if (validGroups.length === 0) {
        alert('Ajoutez au moins un fournisseur.');
        return;
    }

    // Transformer le regroupement en liste plate pour le backend
    const flatOffres: any[] = [];
    const files: { index: number; file: File }[] = [];

    validGroups.forEach((group, gIdx) => {
        // Pour chaque groupe, on crée N entrées (une par article)
        if (items.value.length > 0) {
            items.value.forEach(item => {
                flatOffres.push({
                    fournisseur: group.fournisseur,
                    fournisseur_id: group.fournisseur_id,
                    fed_item_id: item.id,
                    prix_unitaire: group.prices[item.id] ?? null,
                    delais_livraison: group.delais_livraison || null,
                    garanties_offertes: group.garanties_offertes || null,
                    conformite_reglementaire: group.conformite_reglementaire,
                    acompte_requis: group.acompte_requis,
                    pourcentage_acompte: group.pourcentage_acompte,
                });
            });
        } else {
            // Cas global (pas d'articles)
            flatOffres.push({
                fournisseur: group.fournisseur,
                fournisseur_id: group.fournisseur_id,
                fed_item_id: null,
                prix_unitaire: group.prices[0] ?? null,
                delais_livraison: group.delais_livraison || null,
                garanties_offertes: group.garanties_offertes || null,
                conformite_reglementaire: group.conformite_reglementaire,
                acompte_requis: group.acompte_requis,
                pourcentage_acompte: group.pourcentage_acompte,
            });
        }
        
        if (group._file) {
            // On attache le fichier à la PREMIÈRE ligne de ce fournisseur
            // Le backend devra gérer l'association si nécessaire
            files.push({ index: flatOffres.length - (items.value.length || 1), file: group._file });
        }
    });

    const formData = new FormData();
    formData.append('offres', JSON.stringify(flatOffres));
    files.forEach(f => {
        formData.append(`file_${f.index}`, f.file);
    });

    router.post(`/feds/achats/${props.fed.id}/offres`, formData, { 
        preserveScroll: true, 
        forceFormData: true 
    });
};

const hasOffresEnregistrees = computed(() => (props.fed.fournisseur_offres?.length ?? 0) >= 1);
const transmitComment = ref('');

const transmitToFacilities = () => {
    if (!hasOffresEnregistrees.value) {
        alert('Enregistrez d\'abord le tableau comparatif avec au moins un fournisseur.');
        return;
    }
    if (!confirm('Confirmer la transmission au responsable Facilities ?')) return;
    router.post(
        `/feds/achats/${props.fed.id}/transmit`,
        { comment: transmitComment.value },
        { preserveScroll: true }
    );
};

</script>

<template>
    <Head :title="`Tableau comparatif - ${props.fed.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-full p-6">
            <!-- En-tête -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="`/feds/achats/${props.fed.id}`" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        <ArrowLeft class="h-4 w-4" /> Retour à la demande
                    </Link>
                </div>
                <span class="rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">
                    FED {{ props.fed.code }}
                </span>
            </div>
            

            <div class="rounded-lg border-2 border-gray-200 bg-white p-6 shadow-sm">
                <h1 class="mb-1 text-2xl font-bold text-gray-900">Tableau comparatif des offres fournisseurs</h1>
                <div v-if="props.fed.motive" class="hidden sm:block text-sm text-gray-600">
                        Motif : <span class="font-medium uppercase">{{ props.fed.motive }}</span>
                    </div>

                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- Section par article                                         -->
                <!-- ═══════════════════════════════════════════════════════════ -->

                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- Cartes Fournisseur                                          -->
                <!-- ═══════════════════════════════════════════════════════════ -->

                <div class="space-y-8">
                    <div 
                        v-for="(group, gIdx) in fournisseurOffres" 
                        :key="gIdx" 
                        class="rounded-xl border-2 shadow-sm overflow-hidden transition-all"
                        :class="[
                            group.id === props.fed.expert_opinion_offre_id 
                                ? 'border-orange-200 bg-orange-50/20 ring-4 ring-orange-50' 
                                : 'border-gray-100 bg-white'
                        ]"
                    >
                        <!-- En-tête de la carte fournisseur -->
                        <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/50 px-6 py-4">
                            <div class="flex flex-1 items-center gap-4">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-800 text-sm font-bold text-white">{{ gIdx + 1 }}</span>
                                <div class="flex-1 max-w-md flex items-center gap-3">
                                    <template v-if="canEdit">
                                        <SupplierCombobox
                                            :suppliers="props.fournisseurs"
                                            :model-value="group.fournisseur_id"
                                            placeholder="Saisir pour rechercher..."
                                            @update:model-value="onFournisseurSelect(gIdx, $event)"
                                        />
                                    </template>
                                    <span v-else class="text-lg font-bold text-gray-900 uppercase">{{ group.fournisseur || 'Fournisseur non défini' }}</span>
                                    
                                    <span 
                                        v-if="group.id === props.fed.expert_opinion_offre_id"
                                        class="inline-flex items-center rounded bg-orange-100 px-2 py-0.5 text-[10px] font-black text-orange-700 border border-orange-200 uppercase tracking-tighter shadow-sm"
                                    >
                                        ⭐ Choix de l'expert métier
                                    </span>
                                </div>
                            </div>
                            <Button v-if="canEdit && fournisseurOffres.length > 1" type="button" variant="ghost" size="icon" class="text-red-500 hover:bg-red-50" @click="removeFournisseur(gIdx)">
                                <Trash2 class="h-5 w-5" />
                            </Button>
                        </div>

                        <div class="p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-[1fr_350px] gap-8">
                                <!-- Colonne Gauche : Prix par article -->
                                <div>
                                    <h3 class="mb-4 flex items-center gap-2 text-sm font-bold uppercase text-gray-600">
                                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                        Détail des prix unitaires
                                    </h3>
                                    
                                    <template v-if="items.length > 0">
                                        <div class="overflow-hidden rounded-lg border border-gray-200">
                                            <table class="w-full text-sm">
                                                <thead class="bg-gray-50 text-xs font-bold uppercase text-gray-500">
                                                    <tr>
                                                        <th class="px-4 py-3 text-left">Article / Service</th>
                                                        <th class="px-4 py-3 text-center w-24">Quantité</th>
                                                        <th class="px-4 py-3 text-right w-40">Prix Unitaire *</th>
                                                        <th class="px-4 py-3 text-right w-40 bg-blue-50/50">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                    <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50/50 transition-colors">
                                                        <td class="px-4 py-3 font-medium text-gray-800">{{ item.label }}</td>
                                                        <td class="px-4 py-3 text-center text-gray-500">{{ formatQuantity(item.quantity) }}</td>
                                                        <td class="px-4 py-3">
                                                            <div class="relative">
                                                                <input 
                                                                    v-model.number="group.prices[item.id]" 
                                                                    type="number" 
                                                                    placeholder="0" 
                                                                    class="h-9 w-full rounded-md border border-gray-300 pl-2 pr-8 text-right text-sm focus:border-blue-400 focus:ring-2 focus:ring-blue-100" 
                                                                    :readonly="!canEdit" 
                                                                />
                                                                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400">F</span>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3 text-right font-bold text-blue-700 bg-blue-50/30">
                                                            {{ group.prices[item.id] ? formatAmount(group.prices[item.id]! * item.quantity) : '0' }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                                <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                                    <tr class="font-bold">
                                                        <td colspan="3" class="px-4 py-3 text-right uppercase text-gray-600">Total Global de l'offre</td>
                                                        <td class="px-4 py-3 text-right text-lg text-blue-900 bg-blue-100/50">
                                                            {{ formatAmount(items.reduce((sum, item) => sum + ((group.prices[item.id] ?? 0) * item.quantity), 0)) }}
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </template>
                                    <div v-else class="rounded-lg border border-dashed border-gray-300 p-8 text-center">
                                        <div class="mb-2 text-sm font-medium text-gray-700">Prix Global pour la demande</div>
                                        <div class="mx-auto max-w-[200px] relative">
                                            <input 
                                                v-model.number="group.prices[0]" 
                                                type="number" 
                                                placeholder="Saisir montant" 
                                                class="h-10 w-full rounded-md border border-gray-300 px-4 text-center text-lg font-bold focus:border-blue-400 focus:ring-2 focus:ring-blue-100" 
                                                :readonly="!canEdit" 
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Colonne Droite : Conditions de l'offre -->
                                <div class="space-y-4 rounded-xl bg-gray-50 p-5 border border-gray-200">
                                    <h3 class="mb-2 text-xs font-bold uppercase text-gray-500">Conditions & Documents</h3>
                                    
                                    <div class="space-y-3">
                                        <div>
                                            <label class="mb-1 block text-[11px] font-bold uppercase text-gray-600">Délai livraison</label>
                                            <input v-model="group.delais_livraison" placeholder="ex: 7 jours" class="h-9 w-full rounded-md border border-gray-300 px-3 py-1 text-sm focus:border-blue-400 focus:outline-none" :readonly="!canEdit" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[11px] font-bold uppercase text-gray-600">Garantie</label>
                                            <input v-model="group.garanties_offertes" placeholder="ex: 12 mois" class="h-9 w-full rounded-md border border-gray-300 px-3 py-1 text-sm focus:border-blue-400 focus:outline-none" :readonly="!canEdit" />
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="mb-1 block text-[11px] font-bold uppercase text-gray-600">Conformité</label>
                                                <select v-model="group.conformite_reglementaire" class="h-9 w-full rounded-md border border-gray-300 px-2 text-sm" :disabled="!canEdit">
                                                    <option :value="null">—</option>
                                                    <option value="OUI">OUI</option>
                                                    <option value="NON">NON</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-[11px] font-bold uppercase text-gray-600">Acompte</label>
                                                <select v-model="group.acompte_requis" class="h-9 w-full rounded-md border border-gray-300 px-2 text-sm" :disabled="!canEdit">
                                                    <option :value="null">—</option>
                                                    <option value="OUI">OUI</option>
                                                    <option value="NON">NON</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div v-if="group.acompte_requis === 'OUI'">
                                            <label class="mb-1 block text-[11px] font-bold uppercase text-gray-600">% Acompte</label>
                                            <input v-model.number="group.pourcentage_acompte" type="number" min="0" max="100" class="h-9 w-full rounded-md border border-gray-300 px-3 text-sm focus:border-blue-400" :readonly="!canEdit" />
                                        </div>

                                        <!-- Pièce Jointe de l'offre -->
                                        <div class="mt-4 border-t border-gray-200 pt-4">
                                            <label class="mb-2 block text-[11px] font-bold uppercase text-gray-600">Offre signée / Devis (PDF/JPG)</label>
                                            <div v-if="canEdit" class="flex flex-col gap-2">
                                                <input :id="`file-${gIdx}`" type="file" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" @change="onFileSelect(gIdx, $event)" />
                                                <Button type="button" variant="outline" size="sm" class="w-full text-blue-600 border-blue-200 hover:bg-blue-50" @click="triggerFileInput(gIdx)">
                                                    <Paperclip class="mr-2 h-4 w-4" /> 
                                                    {{ group._file ? 'Changer le fichier' : 'Joindre le devis' }}
                                                </Button>
                                                <div v-if="group._file" class="flex items-center justify-between rounded bg-white px-2 py-1.5 border border-blue-100">
                                                    <span class="truncate text-xs text-gray-600 max-w-[180px]">{{ group._file.name }}</span>
                                                    <button type="button" class="text-[10px] font-bold text-red-500 hover:underline" @click="group._file = null">Effacer</button>
                                                </div>
                                            </div>
                                            <div v-else-if="group.attachments?.length" class="space-y-1">
                                                <a v-for="att in group.attachments" :key="att.id" :href="`/storage/${att.path}`" target="_blank" class="flex items-center gap-2 rounded-lg bg-white p-2 text-xs text-blue-600 shadow-sm border border-gray-100 hover:bg-blue-50 transition-colors">
                                                    <Paperclip class="h-3 w-3" />
                                                    <span class="truncate">{{ att.original_name }}</span>
                                                </a>
                                            </div>
                                            <div v-else class="rounded-lg bg-gray-100 p-3 text-center text-xs text-gray-400 italic">
                                                Aucun document joint
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bouton Ajouter un fournisseur -->
                    <div v-if="canEdit" class="flex justify-center py-4">
                        <Button type="button" variant="outline" class="h-12 border-2 border-dashed border-gray-300 px-8 text-gray-600 hover:border-blue-400 hover:text-blue-600 transition-all" @click="addFournisseur">
                            <Plus class="mr-2 h-5 w-5" /> Ajouter un autre fournisseur pour comparaison
                        </Button>
                    </div>
                </div>

                <!-- Zone commentaire + actions -->
                <div v-if="canEdit" class="mt-6 space-y-4 border-t border-gray-200 pt-6">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Commentaire pour le responsable Facilities
                            <span class="ml-1 text-xs text-gray-400">(optionnel)</span>
                        </label>
                        <textarea
                            v-model="transmitComment"
                            rows="2"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-400"
                            placeholder="Informations complémentaires pour le responsable Facilities..."
                        />
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <Button type="button" size="sm" @click="saveOffres">
                            Enregistrer le tableau
                        </Button>
                        <Button
                            type="button"
                            class="bg-green-600 hover:bg-green-700 text-white"
                            :disabled="!hasOffresEnregistrees"
                            @click="transmitToFacilities"
                        >
                            ✅ Envoyer au responsable Facilities
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
