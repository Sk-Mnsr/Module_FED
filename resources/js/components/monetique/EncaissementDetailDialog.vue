<script setup lang="ts">
import type {
    EncaissementBordereauPayload,
    RechargeEncaissementDetail,
} from '@/components/monetique/EncaissementBordereauDialog.vue';
import OdConfirmDialog from '@/components/OdConfirmDialog.vue';
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
const showRejet = ref(false);
const rejetAvis = ref('');
const fileError = ref('');
const bypassCloseGuard = ref(false);
const fileInputKey = ref(0);

watch(
    () => props.payload,
    () => {
        fichier.value = null;
        fileError.value = '';
        showRejet.value = false;
        rejetAvis.value = '';
        fileInputKey.value += 1;
    },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        fichier.value = null;
        fileError.value = '';
        submitting.value = false;
        showRejet.value = false;
        rejetAvis.value = '';
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

const rejectUrl = computed(() => {
    if (!props.payload) {
        return '';
    }
    if (props.payload.kind === 'vente') {
        return `/monetique/encaissements/ventes/${props.payload.row.id}/rejeter`;
    }
    return `/monetique/encaissements/recharges/${props.payload.row.id}/rejeter`;
});

function mettreEnAttente() {
    bypassCloseGuard.value = true;
    open.value = false;
}

function ouvrirRejet() {
    if (!props.payload || !rejectUrl.value || submitting.value) {
        return;
    }
    rejetAvis.value = '';
    showRejet.value = true;
}

function confirmerRejet() {
    const avis = rejetAvis.value.trim();
    if (!props.payload || !rejectUrl.value || submitting.value || avis.length < 3) {
        return;
    }
    submitting.value = true;
    fileError.value = '';
    router.post(
        rejectUrl.value,
        { avis },
        {
            preserveScroll: true,
            onFinish: () => {
                submitting.value = false;
            },
            onSuccess: () => {
                showRejet.value = false;
                bypassCloseGuard.value = true;
                open.value = false;
            },
        },
    );
}

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

function maskCompte(raw: string | null | undefined): string {
    if (!raw?.trim()) return '—';
    const c = raw.replace(/\s+/g, '');
    if (c.length <= 4) return c;
    const maskLen = Math.max(7, c.length - 4);
    return `${'X'.repeat(maskLen)}${c.slice(-4)}`;
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

</script>

<template>
    <Dialog :open="open" @update:open="onDialogOpenUpdate">
        <DialogContent
            class="gap-0 overflow-hidden p-0 sm:max-w-xl"
            :hide-close="caisseFlow"
        >
            <div class="h-1.5 bg-red-600" />
            <DialogHeader class="px-6 pt-6 pb-0 text-left">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-red-700">Caisse</p>
                <DialogTitle class="text-lg">Encaissement</DialogTitle>
            </DialogHeader>

            <div v-if="payload" class="max-h-[min(74vh,720px)] space-y-5 overflow-y-auto px-6 py-5">
                <div
                    v-if="payload.row.encaissement_code?.trim()"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-center"
                >
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-800">
                        Code
                    </p>
                    <p class="mt-1 font-mono text-2xl font-bold tracking-[0.16em] text-amber-950">
                        {{ payload.row.encaissement_code }}
                    </p>
                </div>

                <template v-if="payload.kind === 'vente'">
                    <section class="space-y-1.5 text-sm">
                        <h3 class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">Client</h3>
                        <div class="grid grid-cols-[7.5rem_1fr] gap-x-3 gap-y-1.5">
                            <span class="text-neutral-500">Acheteur</span>
                            <span class="font-medium">{{ payload.row.acheteur?.trim() || '—' }}</span>
                            <span class="text-neutral-500">Téléphone</span>
                            <span class="font-medium tabular-nums">{{ payload.row.telephone_client?.trim() || '—' }}</span>
                            <span class="text-neutral-500">Compte</span>
                            <span class="font-medium">{{ libelleComptePack(payload.row.compte_client_pack) }}</span>
                            <template v-if="payload.row.compte_client_pack === 'in_pack'">
                                <span class="text-neutral-500">N° compte</span>
                                <span class="font-medium tabular-nums">{{ maskCompte(payload.row.numero_compte_client) }}</span>
                            </template>
                            <span class="text-neutral-500">Pièce</span>
                            <span class="break-all font-medium">{{
                                libellePiece(payload.row.kyc_type_piece, payload.row.kyc_numero_piece)
                            }}</span>
                            <template v-if="payload.row.fiche_enrolement_url">
                                <span class="text-neutral-500">Fiche</span>
                                <a
                                    :href="payload.row.fiche_enrolement_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium text-red-700 underline-offset-2 hover:underline"
                                >
                                    Voir la fiche d’enrôlement
                                </a>
                            </template>
                        </div>
                    </section>
                    <section class="space-y-1.5 border-t border-neutral-100 pt-3 text-sm">
                        <h3 class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">Carte</h3>
                        <div class="grid grid-cols-[7.5rem_1fr] gap-x-3 gap-y-1.5">
                            <span class="text-neutral-500">Numéro</span>
                            <span class="font-mono font-medium">{{ formatCardNumberDisplay(payload.row.numero_carte) }}</span>
                            <span class="text-neutral-500">Vendeur</span>
                            <span class="font-medium">{{ payload.row.vendeur }}</span>
                            <span class="text-neutral-500">Agence</span>
                            <span class="font-medium">{{ payload.row.agence }}</span>
                        </div>
                    </section>
                    <section class="space-y-1.5 border-t border-neutral-100 pt-3 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-neutral-500">Prix carte</span>
                            <span class="font-medium tabular-nums">{{ formatCfa(payload.row.prix_vente) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-neutral-500">1re recharge</span>
                            <span class="font-medium tabular-nums">{{
                                (payload.row.montant_premiere_recharge ?? 0) > 0
                                    ? formatCfa(Number(payload.row.montant_premiere_recharge))
                                    : '—'
                            }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline justify-between gap-3 rounded-lg bg-neutral-900 px-3 py-2.5 text-white">
                            <span>Total à encaisser</span>
                            <span class="text-base font-bold tabular-nums">{{
                                formatCfa(
                                    payload.row.montant_total_a_encaisser ??
                                        payload.row.prix_vente + Number(payload.row.montant_premiere_recharge ?? 0),
                                )
                            }}</span>
                        </div>
                    </section>
                </template>

                <template v-else-if="rechargeDetail">
                    <section class="space-y-1.5 text-sm">
                        <h3 class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">Titulaire</h3>
                        <div class="grid grid-cols-[7.5rem_1fr] gap-x-3 gap-y-1.5">
                            <span class="text-neutral-500">Nom</span>
                            <span class="font-medium">{{ rechargeDetail.titulaire_carte?.trim() || '—' }}</span>
                            <template v-if="rechargeDetail.email_titulaire?.trim()">
                                <span class="text-neutral-500">E-mail</span>
                                <span class="break-all font-medium">{{ rechargeDetail.email_titulaire }}</span>
                            </template>
                            <span class="text-neutral-500">Carte</span>
                            <span class="font-mono font-medium">{{
                                formatCardNumberDisplay(rechargeDetail.numero_carte)
                            }}</span>
                            <span class="text-neutral-500">Demandeur</span>
                            <span class="font-medium">{{ rechargeDetail.demandeur }}</span>
                            <span class="text-neutral-500">Agence</span>
                            <span class="font-medium">{{ rechargeDetail.agence }}</span>
                            <template v-if="rechargeDetail.commentaire?.trim()">
                                <span class="text-neutral-500">Note</span>
                                <span class="whitespace-pre-wrap break-words font-medium">{{
                                    rechargeDetail.commentaire
                                }}</span>
                            </template>
                        </div>
                    </section>
                    <section class="space-y-1.5 border-t border-neutral-100 pt-3 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-neutral-500">Recharge</span>
                            <span class="font-medium tabular-nums">{{ formatCfa(rechargeDetail.montant) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-neutral-500">Honoraires</span>
                            <span class="font-medium tabular-nums">{{
                                formatCfa(rechargeDetail.honoraire_chargement ?? 0)
                            }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline justify-between gap-3 rounded-lg bg-neutral-900 px-3 py-2.5 text-white">
                            <span>Total à encaisser</span>
                            <span class="text-base font-bold tabular-nums">{{ formatCfa(rechargeTotalEncaisser) }}</span>
                        </div>
                    </section>
                </template>
            </div>

            <div v-if="caisseFlow && payload" class="space-y-3 border-t border-neutral-200 bg-neutral-50 px-6 py-5">
                <div class="space-y-2">
                    <Label for="bordereau-caisse-file" class="text-sm font-medium">Bordereau caisse</Label>
                    <input
                        :key="fileInputKey"
                        id="bordereau-caisse-file"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                        class="block w-full cursor-pointer rounded-lg border border-dashed border-neutral-300 bg-white px-3 py-2.5 text-sm text-neutral-600 file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-neutral-800"
                        :disabled="submitting"
                        @change="onFileChange"
                    />
                    <p class="text-xs text-neutral-500">PDF, JPEG ou PNG.</p>
                    <p v-if="fileError" class="text-sm text-red-600">{{ fileError }}</p>
                </div>
                <Button
                    type="button"
                    class="h-11 w-full bg-red-600 text-base hover:bg-red-700"
                    :disabled="submitting"
                    @click="validerEncaissement"
                >
                    {{ submitting ? 'Validation…' : 'Valider' }}
                </Button>
                <div class="grid grid-cols-2 gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-10"
                        :disabled="submitting"
                        @click="mettreEnAttente"
                    >
                        Mettre en attente
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-10 border-red-200 text-red-700 hover:bg-red-50 hover:text-red-800"
                        :disabled="submitting"
                        @click="ouvrirRejet"
                    >
                        Rejeter
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <OdConfirmDialog
        :open="showRejet"
        title="Rejeter l’encaissement"
        description="L’opération ne pourra plus être encaissée avec ce code. L’avis est obligatoire."
        confirm-label="Rejeter"
        cancel-label="Annuler"
        variant="danger"
        :loading="submitting"
        :disabled="rejetAvis.trim().length < 3"
        @confirm="confirmerRejet"
        @cancel="showRejet = false"
        @update:open="(v: boolean) => (showRejet = v)"
    >
        <div class="space-y-2">
            <Label for="rejet-avis" class="text-sm font-medium">Avis</Label>
            <textarea
                id="rejet-avis"
                v-model="rejetAvis"
                rows="4"
                class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus-visible:border-red-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/30"
                placeholder="Indiquez le motif du rejet…"
            />
        </div>
    </OdConfirmDialog>
</template>
