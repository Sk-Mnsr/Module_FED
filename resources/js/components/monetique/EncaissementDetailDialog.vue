<script setup lang="ts">
import type {
    EncaissementBordereauPayload,
    RechargeEncaissementDetail,
    VenteEncaissementDetail,
} from '@/components/monetique/EncaissementBordereauDialog.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { formatCardNumberDisplay } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
    payload: EncaissementBordereauPayload | null;
    /** Flux caisse : fermeture désactivée tant que l’encaissement n’est pas validé avec pièce jointe */
    caisseFlow?: boolean;
}>();

const caisseFlow = computed(() => props.caisseFlow === true);

const fichier = ref<File | null>(null);
const submitting = ref(false);
const fileError = ref('');
const bypassCloseGuard = ref(false);
const fileInputKey = ref(0);

watch(
    () => props.payload,
    () => {
        fichier.value = null;
        fileError.value = '';
        fileInputKey.value += 1;
    },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        fichier.value = null;
        fileError.value = '';
        submitting.value = false;
        bypassCloseGuard.value = false;
        fileInputKey.value += 1;
    }
});

function onDialogOpenUpdate(v: boolean) {
    if (caisseFlow.value && !v && !bypassCloseGuard.value) {
        return;
    }
    open.value = v;
}

function onFileChange(e: Event) {
    const t = e.target as HTMLInputElement;
    fichier.value = t.files?.[0] ?? null;
    fileError.value = '';
}

const confirmUrl = computed(() => {
    if (!props.payload) {
        return '';
    }
    if (props.payload.kind === 'vente') {
        return `/monetique/encaissements/ventes/${props.payload.row.id}/confirmer`;
    }
    return `/monetique/encaissements/recharges/${props.payload.row.id}/confirmer`;
});

function validerEncaissement() {
    if (!props.payload || !confirmUrl.value) {
        return;
    }
    if (!fichier.value) {
        fileError.value = 'Veuillez joindre le bordereau caisse (PDF ou image).';
        return;
    }
    submitting.value = true;
    fileError.value = '';
    router.post(
        confirmUrl.value,
        { bordereau_caisse: fichier.value },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                submitting.value = false;
            },
            onSuccess: () => {
                bypassCloseGuard.value = true;
                open.value = false;
            },
        },
    );
}

const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

function maskCompte(raw: string | null | undefined, derniers4: string | null | undefined): string {
    if (raw && raw.trim()) {
        const c = raw.replace(/\s+/g, '');
        if (c.length <= 4) return c;
        const maskLen = Math.max(7, c.length - 4);
        return `${'X'.repeat(maskLen)}${c.slice(-4)}`;
    }
    if (derniers4?.trim()) {
        const d4 = derniers4.replace(/\s+/g, '').slice(-4);
        return `XXXXXXX${d4}`;
    }
    return '—';
}

function libellePiece(type: string | null | undefined, numero: string | null | undefined): string {
    if (!numero?.trim()) return '—';
    const t = type?.trim();
    return t ? `${t} — ${numero}` : String(numero);
}

function libelleComptePack(pack: string | null | undefined): string {
    if (pack === 'in_pack') return 'In Pack';
    if (pack === 'hors_pack') return 'Hors Pack';
    return '—';
}

const rechargeDetail = computed((): RechargeEncaissementDetail | null =>
    props.payload?.kind === 'recharge' ? props.payload.row : null,
);

const rechargeTotalEncaisser = computed(() => {
    const r = rechargeDetail.value;
    if (!r) return 0;
    return r.montant_total_a_encaisser ?? r.montant + (r.honoraire_chargement ?? 0);
});

type VenteDetailLine = { label: string; value: string; emphasis?: boolean; mono?: boolean };

const venteRows = (row: VenteEncaissementDetail): VenteDetailLine[] => {
    const pr = Math.max(0, Number(row.montant_premiere_recharge ?? 0));
    const pv = Number(row.prix_vente ?? 0);
    const total = Number(row.montant_total_a_encaisser ?? pv + pr);

    return [
        { label: 'Code encaissement', value: row.encaissement_code?.trim() || '—', mono: true },
        { label: 'Carte', value: formatCardNumberDisplay(row.numero_carte) },
        { label: 'Prix carte', value: formatCfa(pv) },
        { label: '1re recharge', value: pr > 0 ? formatCfa(pr) : '—' },
        { label: 'Total à encaisser', value: formatCfa(total), emphasis: true },
        { label: 'Acheteur', value: row.acheteur?.trim() || '—' },
        { label: 'Téléphone', value: row.telephone_client?.trim() || '—' },
        { label: 'E-mail', value: row.email_client?.trim() || '—' },
        { label: 'Compte (aperçu)', value: maskCompte(row.numero_compte_client, row.derniers_4) },
        { label: 'Compte client', value: libelleComptePack(row.compte_client_pack) },
        { label: 'Identification', value: libellePiece(row.kyc_type_piece, row.kyc_numero_piece) },
        { label: 'Type acheteur', value: row.type_acheteur?.trim() || '—' },
        { label: 'Vendeur', value: row.vendeur },
        { label: 'Agence', value: row.agence },
        { label: 'Date vente', value: row.date_vente ?? '—' },
        { label: 'Date / heure saisie', value: row.created_at_detail ?? '—' },
        { label: 'Réf. transaction', value: row.numero_transaction },
    ];
};
</script>

<template>
    <Dialog :open="open" @update:open="onDialogOpenUpdate">
        <DialogContent
            class="sm:max-w-lg max-h-[85vh] overflow-y-auto"
            :hide-close="caisseFlow"
        >
            <DialogHeader>
                <DialogTitle>Encaissement caisse</DialogTitle>
                <p class="text-sm text-muted-foreground text-left font-normal">
                    <template v-if="caisseFlow">
                        Jointez le bordereau caisse signé ou scanné, puis validez. La fenêtre ne peut pas être fermée
                        avant validation.
                    </template>
                    <template v-else> Aperçu de l’opération. </template>
                </p>
            </DialogHeader>

            <div v-if="payload?.kind === 'vente'" class="border rounded-lg border-gray-200 divide-y divide-gray-100 text-sm">
                <div
                    v-for="(line, idx) in venteRows(payload.row)"
                    :key="idx"
                    class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5"
                >
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">{{ line.label }}</span>
                    <span
                        class="break-words"
                        :class="[
                            line.emphasis
                                ? 'font-bold text-base text-red-900'
                                : 'font-medium text-gray-900',
                            line.mono ? 'font-mono tracking-wide' : '',
                        ]"
                        >{{ line.value }}</span
                    >
                </div>
            </div>

            <div v-else-if="payload?.kind === 'recharge' && rechargeDetail" class="border rounded-lg border-gray-200 divide-y divide-gray-100 text-sm">
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5">
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">Code encaissement</span>
                    <span class="font-medium text-gray-900 font-mono tracking-wide">{{
                        rechargeDetail.encaissement_code?.trim() || '—'
                    }}</span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5">
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">Carte</span>
                    <span class="font-medium text-gray-900 font-mono">{{
                        formatCardNumberDisplay(rechargeDetail.numero_carte)
                    }}</span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5">
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">Titulaire</span>
                    <span class="font-medium text-gray-900">{{ rechargeDetail.titulaire_carte?.trim() || '—' }}</span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5">
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">E-mail titulaire</span>
                    <span class="font-medium text-gray-900 break-all">{{
                        rechargeDetail.email_titulaire?.trim() || '—'
                    }}</span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5">
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">Montant recharge</span>
                    <span class="font-medium text-gray-900">{{ formatCfa(rechargeDetail.montant) }}</span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5">
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">Honoraire chargement</span>
                    <span class="font-medium text-gray-900">{{ formatCfa(rechargeDetail.honoraire_chargement ?? 0) }}</span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5">
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">Total à encaisser</span>
                    <span class="font-bold text-base text-red-900">{{ formatCfa(rechargeTotalEncaisser) }}</span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5">
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">Demandeur</span>
                    <span class="font-medium text-gray-900">{{ rechargeDetail.demandeur }}</span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5">
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">Agence</span>
                    <span class="font-medium text-gray-900">{{ rechargeDetail.agence }}</span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5">
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">Saisie</span>
                    <span class="font-medium text-gray-900 tabular-nums">{{ rechargeDetail.created_at }}</span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5">
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">Commentaire</span>
                    <span class="font-medium text-gray-900 whitespace-pre-wrap break-words">{{
                        rechargeDetail.commentaire?.trim() || '—'
                    }}</span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-4 px-3 py-2.5">
                    <span class="text-gray-500 shrink-0 sm:min-w-[140px]">Réf. transaction</span>
                    <span class="font-medium text-gray-900 tabular-nums">{{ rechargeDetail.numero_transaction }}</span>
                </div>
            </div>

            <div v-if="caisseFlow && payload" class="space-y-3 pt-2 border-t border-gray-100">
                <div class="space-y-2">
                    <Label for="bordereau-caisse-file" class="text-sm font-medium">Bordereau caisse (PDF, JPEG, PNG)</Label>
                    <input
                        :key="fileInputKey"
                        id="bordereau-caisse-file"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                        class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border file:border-gray-300 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium"
                        :disabled="submitting"
                        @change="onFileChange"
                    />
                    <p v-if="fileError" class="text-sm text-red-600">{{ fileError }}</p>
                </div>
                <Button
                    type="button"
                    class="w-full bg-red-600 hover:bg-red-700"
                    :disabled="submitting"
                    @click="validerEncaissement"
                >
                    {{ submitting ? 'Validation…' : 'Valider l’encaissement' }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
