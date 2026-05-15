<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { formatCardNumberDisplay } from '@/lib/utils';
import { computed } from 'vue';

export type BordereauEntite = {
    raison_sociale: string;
    sous_titre?: string;
    ligne_adresse: string;
    telephones: string;
    email: string;
};

export type VenteEncaissementDetail = {
    id: number;
    numero_carte: string;
    prix_vente: number;
    /** Montant demandé pour la 1re recharge (0 si aucun). */
    montant_premiere_recharge?: number | null;
    /** Prix carte + 1re recharge — total à encaisser à la caisse. */
    montant_total_a_encaisser?: number;
    vendeur: string;
    acheteur: string | null;
    date_vente: string | null;
    agence: string;
    type_acheteur: string | null;
    telephone_client: string | null;
    email_client: string | null;
    numero_compte_client: string | null;
    compte_client_pack: string | null;
    kyc_type_piece: string | null;
    kyc_numero_piece: string | null;
    derniers_4: string | null;
    created_at_detail: string | null;
    numero_transaction: string;
    encaissement_code?: string | null;
    /** Renseigné après encaissement confirmé : affichée comme date transaction sur le bordereau */
    date_encaisse_confirme?: string | null;
};

export type RechargeEncaissementDetail = {
    id: number;
    numero_carte: string;
    montant: number;
    titulaire_carte?: string | null;
    email_titulaire?: string | null;
    honoraire_chargement?: number | null;
    montant_total_a_encaisser?: number;
    demandeur: string;
    agence: string;
    created_at: string;
    commentaire: string | null;
    created_at_detail: string | null;
    numero_transaction: string;
    encaissement_code?: string | null;
    date_encaisse_confirme?: string | null;
};

export type EncaissementBordereauPayload =
    | { kind: 'vente'; row: VenteEncaissementDetail }
    | { kind: 'recharge'; row: RechargeEncaissementDetail };

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
    payload: EncaissementBordereauPayload | null;
    entite: BordereauEntite;
}>();

const titreDocument = computed(() => {
    if (props.payload?.kind === 'recharge') return 'Encaissement recharge Coficarte';
    return 'Encaissement vente Coficarte';
});

/** Aligné sur le reste du module monétique (ex. Caisse, historiques). */
const formatCfa = (n: number) => `${n.toLocaleString('fr-FR')} F CFA`;

function splitNomComplet(full: string | null | undefined): { prenom: string; nom: string } {
    const t = (full ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    if (t.length === 0) return { prenom: '—', nom: '—' };
    if (t.length === 1) return { prenom: '—', nom: t[0] ?? '—' };
    return { prenom: t[0] ?? '—', nom: t.slice(1).join(' ') };
}

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

const venteNames = computed(() =>
    props.payload?.kind === 'vente' ? splitNomComplet(props.payload.row.acheteur) : { prenom: '—', nom: '—' },
);

const rechargeNames = computed(() => {
    if (props.payload?.kind !== 'recharge') {
        return { prenom: '—', nom: '—' };
    }
    const tit = props.payload.row.titulaire_carte?.trim();
    if (tit) {
        return splitNomComplet(tit);
    }
    return splitNomComplet(props.payload.row.demandeur);
});

const metaBloc = computed(() => {
    const p = props.payload;
    if (!p) return { date: '—', numero: '—', typeOp: '—' };
    if (p.kind === 'vente') {
        return {
            date: p.row.date_encaisse_confirme?.trim()
                ? p.row.date_encaisse_confirme
                : (p.row.created_at_detail ?? '—'),
            numero: p.row.numero_transaction,
            typeOp: 'Vente carte Coficarte',
        };
    }
    return {
        date: p.row.date_encaisse_confirme?.trim()
            ? p.row.date_encaisse_confirme
            : (p.row.created_at_detail ?? '—'),
        numero: p.row.numero_transaction,
        typeOp: 'Recharge carte Coficarte',
    };
});

const imprimer = () => {
    document.documentElement.classList.add('enc-bordereau-print-active');
    const onAfter = () => {
        document.documentElement.classList.remove('enc-bordereau-print-active');
        window.removeEventListener('afterprint', onAfter);
    };
    window.addEventListener('afterprint', onAfter);
    window.print();
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="enc-bordereau-dialog sm:max-w-2xl w-[calc(100%-1.5rem)] max-h-[min(92vh,880px)] overflow-y-auto border-0 bg-transparent p-0 shadow-none gap-0"
        >
            <DialogTitle class="sr-only">{{ titreDocument }}</DialogTitle>

            <div
                class="enc-bordereau-outer rounded-xl overflow-hidden border border-red-900/15 shadow-2xl bg-gradient-to-b from-red-600 via-rose-100/80 to-gray-200/90 p-3 sm:p-5"
            >
                <div
                    id="enc-bordereau-print-root"
                    class="bg-white rounded-lg border border-neutral-500/90 px-5 py-6 sm:px-8 sm:py-8 text-neutral-900 print:border-neutral-800 print:shadow-none"
                >
                    <header class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-5 border-b border-neutral-200 pb-5 mb-5 print:pb-4 print:mb-4">
                        <div class="space-y-1">
                            <h2 class="text-lg sm:text-xl font-bold text-neutral-900 leading-tight tracking-tight">
                                {{ titreDocument }}
                            </h2>
                            <p
                                v-if="entite.raison_sociale?.trim()"
                                class="text-sm font-semibold text-neutral-800"
                            >
                                {{ entite.raison_sociale.trim() }}
                            </p>
                            <p v-if="entite.sous_titre" class="text-xs text-neutral-500 font-medium">
                                {{ entite.sous_titre }}
                            </p>
                        </div>
                        <div class="flex items-start gap-3 sm:gap-4 shrink-0">
                            <img
                                src="/logo_Cofina.png"
                                :alt="`${entite.raison_sociale} — ${entite.sous_titre ?? 'Compagnie Financière Africaine'}`"
                                class="h-11 sm:h-12 w-auto max-w-[168px] shrink-0 object-contain object-left"
                                width="168"
                                height="48"
                            />
                            <div class="text-xs sm:text-sm text-neutral-700 space-y-0.5 min-w-0">
                                <p class="font-semibold text-neutral-900">{{ entite.ligne_adresse }}</p>
                                <p class="tabular-nums text-neutral-600">{{ entite.telephones }}</p>
                                <p class="text-neutral-600 break-all">{{ entite.email }}</p>
                            </div>
                        </div>
                    </header>

                    <template v-if="payload">
                        <div class="border border-neutral-800 rounded-sm px-3 py-3 sm:px-4 mb-4 text-sm space-y-2">
                            <div
                                v-if="payload.row.encaissement_code?.trim()"
                                class="rounded-md bg-amber-50 border border-amber-200 px-3 py-2 mb-2 print:border-amber-400 enc-bordereau-print-amber"
                            >
                                <p class="text-xs font-semibold text-amber-900 uppercase tracking-wide">
                                    Code à communiquer à la caisse
                                </p>
                                <p class="text-lg font-mono font-bold text-amber-950 tracking-widest mt-1">
                                    {{ payload.row.encaissement_code }}
                                </p>
                            </div>
                            <div class="flex flex-wrap sm:flex-nowrap gap-x-6 gap-y-1">
                                <p>
                                    <span class="text-neutral-500 font-medium">Date transaction :</span>
                                    <span class="ml-2 font-semibold text-neutral-950 tabular-nums">{{
                                        metaBloc.date
                                    }}</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-x-6 gap-y-1">
                                <p class="min-w-0 break-all">
                                    <span class="text-neutral-500 font-medium">Numéro transaction :</span>
                                    <span class="ml-2 font-semibold text-neutral-950 tabular-nums">{{
                                        metaBloc.numero
                                    }}</span>
                                </p>
                            </div>
                            <p>
                                <span class="text-neutral-500 font-medium">Type d’opération :</span>
                                <span class="ml-2 font-semibold text-neutral-950">{{ metaBloc.typeOp }}</span>
                            </p>
                        </div>

                        <div
                            v-if="payload.kind === 'vente'"
                            class="border border-neutral-800 rounded-sm px-3 py-3 sm:px-4 text-sm grid grid-cols-1 gap-x-8 gap-y-2.5"
                        >
                            <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 items-baseline">
                                <span class="text-neutral-500 shrink-0">Prénom :</span>
                                <span class="font-semibold text-neutral-950 uppercase">{{ venteNames.prenom }}</span>
                                <span class="text-neutral-500 shrink-0">Nom :</span>
                                <span class="font-semibold text-neutral-950 uppercase">{{ venteNames.nom }}</span>
                                <span class="text-neutral-500 shrink-0">Numéro téléphone :</span>
                                <span class="font-semibold text-neutral-950 tabular-nums">{{
                                    payload.row.telephone_client?.trim() || '—'
                                }}</span>
                                <span class="text-neutral-500 shrink-0">E-mail :</span>
                                <span class="font-semibold text-neutral-950 break-all">{{
                                    payload.row.email_client?.trim() || '—'
                                }}</span>
                                <span class="text-neutral-500 shrink-0">Numéro identification :</span>
                                <span class="font-semibold text-neutral-950 break-all">{{
                                    libellePiece(payload.row.kyc_type_piece, payload.row.kyc_numero_piece)
                                }}</span>
                                <span class="text-neutral-500 shrink-0">Numéro compte :</span>
                                <span class="font-semibold text-neutral-950 tabular-nums tracking-wide">{{
                                    maskCompte(payload.row.numero_compte_client, payload.row.derniers_4)
                                }}</span>
                                <span class="text-neutral-500 shrink-0">Compte client :</span>
                                <span class="font-semibold text-neutral-950">{{
                                    libelleComptePack(payload.row.compte_client_pack)
                                }}</span>
                                <span class="text-neutral-500 shrink-0">Type acheteur :</span>
                                <span class="font-semibold text-neutral-950">{{
                                    payload.row.type_acheteur?.trim() || '—'
                                }}</span>
                                <span class="text-neutral-500 shrink-0">Carte :</span>
                                <span class="font-mono font-semibold text-neutral-950">{{
                                    formatCardNumberDisplay(payload.row.numero_carte)
                                }}</span>
                                <span class="text-neutral-500 shrink-0">Vendeur :</span>
                                <span class="font-semibold text-neutral-950">{{ payload.row.vendeur }}</span>
                                <span class="text-neutral-500 shrink-0">Agence :</span>
                                <span class="font-semibold text-neutral-950">{{ payload.row.agence }}</span>
                                <span class="text-neutral-500 shrink-0">Date vente :</span>
                                <span class="font-semibold text-neutral-950 tabular-nums">{{ payload.row.date_vente ?? '—' }}</span>
                                <span class="text-neutral-500 shrink-0">Prix carte :</span>
                                <span class="font-semibold text-neutral-950">{{ formatCfa(payload.row.prix_vente) }}</span>
                                <span class="text-neutral-500 shrink-0">1re recharge :</span>
                                <span class="font-semibold text-neutral-950">{{
                                    (payload.row.montant_premiere_recharge ?? 0) > 0
                                        ? formatCfa(Number(payload.row.montant_premiere_recharge))
                                        : '—'
                                }}</span>
                                <span class="text-neutral-500 shrink-0">Total à encaisser :</span>
                                <span class="font-semibold text-neutral-950 text-base">{{
                                    formatCfa(
                                        payload.row.montant_total_a_encaisser ??
                                            payload.row.prix_vente + Number(payload.row.montant_premiere_recharge ?? 0),
                                    )
                                }}</span>
                                <span class="text-neutral-500 shrink-0">Frais :</span>
                                <span class="font-semibold text-neutral-950">{{ formatCfa(0) }}</span>
                            </div>
                        </div>

                        <div
                            v-else
                            class="border border-neutral-800 rounded-sm px-3 py-3 sm:px-4 text-sm grid grid-cols-1 gap-x-8 gap-y-2.5"
                        >
                            <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 items-baseline">
                                <span class="text-neutral-500 shrink-0">Prénom :</span>
                                <span class="font-semibold text-neutral-950 uppercase">{{ rechargeNames.prenom }}</span>
                                <span class="text-neutral-500 shrink-0">Nom :</span>
                                <span class="font-semibold text-neutral-950 uppercase">{{ rechargeNames.nom }}</span>
                                <span class="text-neutral-500 shrink-0">Carte :</span>
                                <span class="font-mono font-semibold text-neutral-950">{{
                                    formatCardNumberDisplay(payload.row.numero_carte)
                                }}</span>
                                <span class="text-neutral-500 shrink-0">E-mail titulaire :</span>
                                <span class="font-semibold text-neutral-950 break-all">{{
                                    payload.row.email_titulaire?.trim() || '—'
                                }}</span>
                                <span class="text-neutral-500 shrink-0">Agence :</span>
                                <span class="font-semibold text-neutral-950">{{ payload.row.agence }}</span>
                                <span class="text-neutral-500 shrink-0">Montant recharge :</span>
                                <span class="font-semibold text-neutral-950">{{ formatCfa(payload.row.montant) }}</span>
                                <span class="text-neutral-500 shrink-0">Honoraire de chargement :</span>
                                <span class="font-semibold text-neutral-950">{{
                                    formatCfa(Number(payload.row.honoraire_chargement ?? 0))
                                }}</span>
                                <span class="text-neutral-500 shrink-0">Total à encaisser :</span>
                                <span class="font-semibold text-neutral-950 text-base">{{
                                    formatCfa(
                                        payload.row.montant_total_a_encaisser ??
                                            payload.row.montant + Number(payload.row.honoraire_chargement ?? 0),
                                    )
                                }}</span>
                                <span class="text-neutral-500 shrink-0">Demandeur (saisie) :</span>
                                <span class="font-semibold text-neutral-950">{{ payload.row.demandeur }}</span>
                                <span class="text-neutral-500 shrink-0">Commentaire :</span>
                                <span class="font-semibold text-neutral-950 whitespace-pre-wrap break-words">{{
                                    payload.row.commentaire?.trim() || '—'
                                }}</span>
                            </div>
                        </div>

                        <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-8 text-sm">
                            <div>
                                <p class="font-semibold text-neutral-800 mb-2">Signature Client</p>
                                <div class="border-b-2 border-neutral-400 h-12" />
                            </div>
                            <div>
                                <p class="font-semibold text-neutral-800 mb-2">Signature Agent</p>
                                <div class="border-b-2 border-neutral-400 h-12" />
                            </div>
                        </div>

                        <div class="enc-no-print mt-8 flex justify-center">
                            <Button
                                type="button"
                                class="min-w-[200px] bg-red-600 text-white shadow-md hover:bg-red-700"
                                @click="imprimer"
                            >
                                Imprimer
                            </Button>
                        </div>
                    </template>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@media print {
    :deep(.enc-bordereau-dialog > button) {
        display: none !important;
    }
}
</style>

<style>
/* Impression : le DialogContent est en fixed + translate (reka-ui) → aperçu Chrome souvent vide sans ces règles. */
@media print {
    @page {
        size: A4 portrait;
        margin: 14mm 12mm;
    }

    html,
    body {
        background: #fff !important;
        height: auto !important;
    }

    [data-slot='dialog-overlay'] {
        display: none !important;
    }

    [data-slot='dialog-content'].enc-bordereau-dialog {
        position: static !important;
        inset: auto !important;
        left: auto !important;
        top: auto !important;
        transform: none !important;
        translate: none !important;
        width: 100% !important;
        max-width: 100% !important;
        max-height: none !important;
        height: auto !important;
        overflow: visible !important;
        padding: 0 !important;
        margin: 0 !important;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
        animation: none !important;
        opacity: 1 !important;
    }

    /* Masquer toute l’appli sauf le contenu du dialogue au moment du clic « Imprimer » (classe posée en JS). */
    html.enc-bordereau-print-active body * {
        visibility: hidden !important;
    }

    html.enc-bordereau-print-active .enc-bordereau-dialog,
    html.enc-bordereau-print-active .enc-bordereau-dialog * {
        visibility: visible !important;
    }

    .enc-bordereau-outer {
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }

    html.enc-bordereau-print-active .enc-bordereau-outer {
        position: static !important;
    }

    #enc-bordereau-print-root {
        position: relative !important;
        left: auto !important;
        top: auto !important;
        width: 100% !important;
        max-width: 100% !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
    }

    .enc-bordereau-print-amber {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .enc-no-print {
        display: none !important;
    }
}
</style>
