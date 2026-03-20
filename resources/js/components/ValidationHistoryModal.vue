<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { History } from 'lucide-vue-next';

interface Fed {
    n1_avis?: string | null;
    n1_comment?: string | null;
    n1_action_at?: string | null;
    n1_validated_by?: string | null;
    achats_comment?: string | null;
    achats_action_at?: string | null;
    achats_validated_by?: string | null;
    facilities_comment?: string | null;
    facilities_action_at?: string | null;
    facilities_validated_by?: string | null;
    daf_comment?: string | null;
    daf_action_at?: string | null;
    daf_validated_by?: string | null;
    dga_comment?: string | null;
    dga_action_at?: string | null;
    dga_validated_by?: string | null;
    cg_budget_status?: string | null;
    cg_comment?: string | null;
    cg_action_at?: string | null;
    cg_validated_by?: string | null;
    expert_opinion_offre_id?: number | null;
    expert_opinion_comment?: string | null;
    expert_opinion_at?: string | null;
    fournisseur_offres?: { id?: number; fournisseur: string }[];
}

const props = defineProps<{
    fed: Fed;
}>();

const hasHistory = () => {
    return (
        props.fed.n1_action_at ||
        props.fed.achats_action_at ||
        props.fed.facilities_action_at ||
        props.fed.daf_action_at ||
        props.fed.dga_action_at ||
        props.fed.cg_action_at ||
        props.fed.expert_opinion_at
    );
};

const formatDateTime = (value?: string | null) => {
    if (!value) return '';
    return new Date(value).toLocaleString('fr-FR');
};
</script>

<template>
    <Dialog v-if="hasHistory()">
        <DialogTrigger as-child>
            <Button variant="outline" class="flex items-center gap-2 border-dashed">
                <History class="h-4 w-4" />
                Historique des validations
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[800px]">
            <DialogHeader>
                <DialogTitle>Historique des validations</DialogTitle>
                <DialogDescription>
                    Retrace des différentes étapes de validation de cette Fiche d'Engagement de Dépense.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 max-h-[70vh] overflow-y-auto p-1">
                <!-- N+1 -->
                <div v-if="props.fed.n1_avis || props.fed.n1_comment || props.fed.n1_action_at" class="rounded-lg border border-green-200 bg-green-50 p-4">
                    <h3 class="mb-0.5 text-sm font-bold text-green-800">
                        {{ props.fed.n1_validated_by || 'Manager (N+1)' }}
                    </h3>
                    <p class="mb-3 text-xs text-green-600 uppercase tracking-wide">Validation Manager</p>
                    <div v-if="props.fed.n1_avis" class="mb-2">
                        <span class="block text-xs font-semibold text-green-700">Avis :</span>
                        <p class="text-sm font-medium text-green-900">{{ props.fed.n1_avis }}</p>
                    </div>
                    <div v-if="props.fed.n1_comment">
                        <span class="block text-xs font-semibold text-green-700">Message :</span>
                        <p class="text-sm font-medium text-green-900">{{ props.fed.n1_comment }}</p>
                    </div>
                    <span v-if="props.fed.n1_action_at" class="mt-4 block text-xs text-green-600">
                        Le {{ formatDateTime(props.fed.n1_action_at) }}
                    </span>
                </div>

                <!-- Achats -->
                <div v-if="props.fed.achats_comment || props.fed.achats_action_at" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <h3 class="mb-0.5 text-sm font-bold text-blue-800">
                        {{ props.fed.achats_validated_by || 'Service Achats' }}
                    </h3>
                    <p class="mb-3 text-xs text-blue-600 uppercase tracking-wide">Traitement Achats</p>
                    <div v-if="props.fed.achats_comment">
                        <span class="block text-xs font-semibold text-blue-700">Message :</span>
                        <p class="text-sm font-medium text-blue-900">{{ props.fed.achats_comment }}</p>
                    </div>
                    <span v-if="props.fed.achats_action_at" class="mt-4 block text-xs text-blue-600">
                        Le {{ formatDateTime(props.fed.achats_action_at) }}
                    </span>
                </div>

                <!-- Facilities -->
                <div v-if="props.fed.facilities_comment || props.fed.facilities_action_at" class="rounded-lg border border-purple-200 bg-purple-50 p-4">
                    <h3 class="mb-0.5 text-sm font-bold text-purple-800">
                        {{ props.fed.facilities_validated_by || 'Responsable Facilities' }}
                    </h3>
                    <p class="mb-3 text-xs text-purple-600 uppercase tracking-wide">Validation Facilities</p>
                    <div v-if="props.fed.facilities_comment">
                        <span class="block text-xs font-semibold text-purple-700">Décision :</span>
                        <p class="text-sm font-medium text-purple-900">{{ props.fed.facilities_comment }}</p>
                    </div>
                    <span v-if="props.fed.facilities_action_at" class="mt-4 block text-xs text-purple-600">
                        Le {{ formatDateTime(props.fed.facilities_action_at) }}
                    </span>
                </div>

                <!-- DAF -->
                <div v-if="props.fed.daf_comment || props.fed.daf_action_at" class="rounded-lg border border-orange-200 bg-orange-50 p-4">
                    <h3 class="mb-0.5 text-sm font-bold text-orange-800">
                        {{ props.fed.daf_validated_by || 'Direction Financière' }}
                    </h3>
                    <p class="mb-3 text-xs text-orange-600 uppercase tracking-wide">Validation DAF</p>
                    <div v-if="props.fed.daf_comment">
                        <span class="block text-xs font-semibold text-orange-700">Commentaire :</span>
                        <p class="text-sm font-medium text-orange-900">{{ props.fed.daf_comment }}</p>
                    </div>
                    <span v-if="props.fed.daf_action_at" class="mt-4 block text-xs text-orange-600">
                        Le {{ formatDateTime(props.fed.daf_action_at) }}
                    </span>
                </div>

                <!-- DGA -->
                <div v-if="props.fed.dga_comment || props.fed.dga_action_at" class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                    <h3 class="mb-0.5 text-sm font-bold text-indigo-800">
                        {{ props.fed.dga_validated_by || 'Direction Générale Adjointe' }}
                    </h3>
                    <p class="mb-3 text-xs text-indigo-600 uppercase tracking-wide">Validation DGA</p>
                    <div v-if="props.fed.dga_comment">
                        <span class="block text-xs font-semibold text-indigo-700">Commentaire :</span>
                        <p class="text-sm font-medium text-indigo-900">{{ props.fed.dga_comment }}</p>
                    </div>
                    <span v-if="props.fed.dga_action_at" class="mt-4 block text-xs text-indigo-600">
                        Le {{ formatDateTime(props.fed.dga_action_at) }}
                    </span>
                </div>

                <!-- Contrôle de Gestion -->
                <div v-if="props.fed.cg_budget_status || props.fed.cg_comment || props.fed.cg_action_at" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                    <h3 class="mb-0.5 text-sm font-bold text-yellow-800">
                        {{ props.fed.cg_validated_by || 'Contrôle de Gestion' }}
                    </h3>
                    <p class="mb-3 text-xs text-yellow-600 uppercase tracking-wide">Contrôle de Gestion</p>
                    <div v-if="props.fed.cg_budget_status" class="mb-2">
                        <span class="block text-xs font-semibold text-yellow-700">Statut budgétaire :</span>
                        <p class="text-sm font-medium text-yellow-900">
                            {{ props.fed.cg_budget_status === 'in_budget' ? 'Dans le budget' : 'Hors budget' }}
                        </p>
                    </div>
                    <div v-if="props.fed.cg_comment">
                        <span class="block text-xs font-semibold text-yellow-700">Message :</span>
                        <p class="text-sm font-medium text-yellow-900">{{ props.fed.cg_comment }}</p>
                    </div>
                    <span v-if="props.fed.cg_action_at" class="mt-4 block text-xs text-yellow-600">
                        Le {{ formatDateTime(props.fed.cg_action_at) }}
                    </span>
                </div>

                <!-- Avis Expert -->
                <div v-if="props.fed.expert_opinion_at" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <h3 class="mb-0.5 text-sm font-bold text-amber-800">
                        Expert Métier (N+1)
                    </h3>
                    <p class="mb-3 text-xs text-amber-600 uppercase tracking-wide">Avis Expert Sollicité</p>
                    <div v-if="props.fed.expert_opinion_offre_id" class="mb-2">
                        <span class="block text-xs font-semibold text-amber-700">Offre recommandée :</span>
                        <p class="text-sm font-black text-amber-900 uppercase">
                            {{ props.fed.fournisseur_offres?.find(o => o.id === props.fed.expert_opinion_offre_id)?.fournisseur || 'Fournisseur inconnu' }}
                        </p>
                    </div>
                    <div v-if="props.fed.expert_opinion_comment">
                        <span class="block text-xs font-semibold text-amber-700">Commentaire expert :</span>
                        <p class="text-sm italic font-medium text-amber-900">" {{ props.fed.expert_opinion_comment }} "</p>
                    </div>
                    <span v-if="props.fed.expert_opinion_at" class="mt-4 block text-xs text-amber-600">
                        Le {{ formatDateTime(props.fed.expert_opinion_at) }}
                    </span>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
