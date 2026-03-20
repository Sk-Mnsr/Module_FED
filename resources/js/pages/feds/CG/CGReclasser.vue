<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';
import { ArrowRightLeft, AlertCircle, ArrowLeft } from 'lucide-vue-next';

interface BudgetLine {
    id: number;
    code?: string | null;
    label?: string | null;
    montant_estime?: number | null;
    is_reclassified?: boolean;
}

interface BudgetLineHistory {
    id: number;
    action: string;
    montant_transfere?: number | null;
    note?: string | null;
    status?: string | null;
    created_at: string;
    fromLine?: BudgetLine | null;
    toLine?: BudgetLine | null;
}

interface Fed {
    id: number;
    code: string;
    status: string;
    budget_line_histories?: BudgetLineHistory[];
}

interface Props {
    fed: Fed;
    budgetLines: BudgetLine[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contrôle de Gestion', href: '/feds/cg' },
    { title: props.fed.code, href: `/feds/cg/${props.fed.id}` },
    { title: 'Reclassement Budgétaire', href: '#' },
];

const formatAmount = (value?: number | null) => {
    if (value === null || value === undefined) return '-';
    // Formatage avec séparateur de milliers virgule
    const formattedNum = new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Number(value) || 0);
    return `${formattedNum} FCFA`;
};

const formatQuantity = (value?: number | null) => {
    if (value === null || value === undefined) return '—';
    return Math.round(value).toString();
};

// === Reclassement (transfert de montant) ===
const reclassifyFromLine = ref<number | null>(null);
const reclassifyToLine = ref<number | null>(null);
const reclassifyMontant = ref<number | null>(null);
const reclassifyNote = ref('');
const isSubmittingReclassify = ref(false);

const sourceLineObj = computed(() => props.budgetLines.find(l => l.id === reclassifyFromLine.value));
const targetLineObj = computed(() => props.budgetLines.find(l => l.id === reclassifyToLine.value));

const submitReclassify = () => {
    if (!reclassifyFromLine.value || !reclassifyToLine.value || !reclassifyMontant.value) {
        alert('Veuillez remplir tous les champs obligatoires.');
        return;
    }
    if (!confirm('Confirmer la soumission de la demande de transfert au DAF ?')) return;

    isSubmittingReclassify.value = true;
    router.post(
        `/feds/cg/${props.fed.id}/reclassify-transfer`,
        {
            from_line_id: reclassifyFromLine.value,
            to_line_id: reclassifyToLine.value,
            montant_transfere: reclassifyMontant.value,
            note: reclassifyNote.value || null,
        },
        {
            preserveScroll: true,
            onError: () => {
                isSubmittingReclassify.value = false;
            },
        }
    );
};
</script>

<template>
    <Head :title="`Reclassement - ${props.fed.code}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-5xl p-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                        <Link :href="`/feds/cg/${props.fed.id}`" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <ArrowLeft class="h-6 w-6" />
                        </Link>
                        Demande de Reclassement Budgétaire
                    </h1>
                    <p class="mt-2 text-sm text-gray-500">
                        Transfert de fonds entre deux lignes budgétaires pour la FED <strong>{{ props.fed.code }}</strong>.
                        Cette action nécessitera l'approbation du DAF.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Formulaire principal -->
                <div class="lg:col-span-2 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="space-y-6">
                        <!-- Ligne Source -->
                        <div class="rounded-lg border-2 border-red-100 bg-red-50/30 p-5 transition-colors" :class="{'bg-red-50/80 border-red-200': sourceLineObj}">
                            <label class="mb-2 block text-sm font-bold text-red-900">
                                Ligne Source (à débiter) <span class="text-red-500">*</span>
                            </label>
                            <select v-model="reclassifyFromLine" class="w-full rounded-md border-red-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-red-500 focus:ring-red-500">
                                <option :value="null">— Sélectionner la ligne source —</option>
                                <option v-for="line in props.budgetLines" :key="line.id" :value="line.id" :disabled="line.id === reclassifyToLine">
                                    {{ line.code }} — {{ line.label }} (Solde: {{ formatAmount(line.montant_estime) }})
                                </option>
                            </select>

                            <div v-if="sourceLineObj" class="mt-4 flex justify-between text-sm rounded bg-white p-3 border border-red-100 shadow-sm">
                                <span class="text-gray-600">Solde disponible :</span>
                                <span class="font-bold text-red-700">{{ formatAmount(sourceLineObj.montant_estime) }}</span>
                            </div>
                        </div>

                        <div class="flex justify-center -my-2 relative z-10">
                            <div class="bg-white p-2 rounded-full border border-gray-200 shadow-sm text-gray-400">
                                <ArrowRightLeft class="h-5 w-5 rotate-90" />
                            </div>
                        </div>

                        <!-- Ligne Cible -->
                        <div class="rounded-lg border-2 border-green-100 bg-green-50/30 p-5 transition-colors" :class="{'bg-green-50/80 border-green-200': targetLineObj}">
                            <label class="mb-2 block text-sm font-bold text-green-900">
                                Ligne Cible (à créditer) <span class="text-red-500">*</span>
                            </label>
                            <select v-model="reclassifyToLine" class="w-full rounded-md border-green-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500 focus:ring-green-500">
                                <option :value="null">— Sélectionner la ligne cible —</option>
                                <option v-for="line in props.budgetLines" :key="line.id" :value="line.id" :disabled="line.id === reclassifyFromLine">
                                    {{ line.code }} — {{ line.label }} (Solde: {{ formatAmount(line.montant_estime) }})
                                </option>
                            </select>

                            <div v-if="targetLineObj" class="mt-4 flex justify-between text-sm rounded bg-white p-3 border border-green-100 shadow-sm">
                                <span class="text-gray-600">Solde actuel :</span>
                                <span class="font-bold text-green-700">{{ formatAmount(targetLineObj.montant_estime) }}</span>
                            </div>
                        </div>

                        <!-- Montant & Note -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-100">
                            <div>
                                <label class="mb-2 block text-sm font-bold text-gray-900">Montant à transférer <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input
                                        v-model="reclassifyMontant"
                                        type="number" step="1" min="1"
                                        class="w-full rounded-md border border-gray-300 px-4 py-3 text-lg font-medium shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="Ex: 500000"
                                    />
                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-medium bg-white pl-2">XOF</span>
                                </div>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-bold text-gray-900">Note justificative (Optionnelle)</label>
                                <input
                                    v-model="reclassifyNote"
                                    type="text"
                                    class="w-full rounded-md border border-gray-300 px-3 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="Raison du transfert budgétaire..."
                                />
                            </div>
                        </div>

                        <div class="pt-6 border-t border-gray-100 flex justify-end gap-3">
                            <Link :href="`/feds/cg/${props.fed.id}`">
                                <Button variant="outline" type="button" class="px-6">Annuler</Button>
                            </Link>
                            <Button
                                type="button"
                                :disabled="isSubmittingReclassify || !reclassifyFromLine || !reclassifyToLine || !reclassifyMontant"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-8"
                                @click="submitReclassify"
                            >
                                <ArrowRightLeft class="mr-2 h-4 w-4" />
                                {{ isSubmittingReclassify ? 'Envoi en cours...' : 'Soumettre le transfert au DAF' }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Simulation Sidebar -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-6 shadow-sm">
                        <h3 class="font-bold text-blue-900 mb-4 flex items-center gap-2">
                            <AlertCircle class="h-5 w-5" />
                            Simulation de l'impact
                        </h3>
                        
                        <div v-if="!sourceLineObj || !targetLineObj || !reclassifyMontant" class="text-sm text-blue-700 opacity-80 italic">
                            Sélectionnez une ligne source, une ligne cible et un montant pour voir la simulation.
                        </div>

                        <div v-else class="space-y-5">
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Après validation DAF</p>
                                
                                <div class="bg-white p-3 rounded border border-blue-100 flex justify-between items-center mb-3">
                                    <div class="truncate mr-4 text-sm font-medium text-gray-700">{{ sourceLineObj.code }}</div>
                                    <div class="text-right">
                                        <div class="text-xs text-red-500 line-through">{{ formatAmount(sourceLineObj.montant_estime) }}</div>
                                        <div class="font-bold text-gray-900 text-sm">
                                            {{ formatAmount((sourceLineObj.montant_estime || 0) - (reclassifyMontant || 0)) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white p-3 rounded border border-blue-100 flex justify-between items-center">
                                    <div class="truncate mr-4 text-sm font-medium text-gray-700">{{ targetLineObj.code }}</div>
                                    <div class="text-right">
                                        <div class="text-xs text-green-500 line-through">{{ formatAmount(targetLineObj.montant_estime) }}</div>
                                        <div class="font-bold text-gray-900 text-sm">
                                            {{ formatAmount((targetLineObj.montant_estime || 0) + (reclassifyMontant || 0)) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
