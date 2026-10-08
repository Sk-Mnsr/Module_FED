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
    fiche_enrolement_url?: string | null;
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
    if (props.payload?.kind === 'recharge') return 'Bordereau d’encaissement — recharge';
    return 'Bordereau d’encaissement — vente';
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
                class="enc-bordereau-outer overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-2xl"
            >
                <div class="h-1.5 bg-red-600 print:bg-red-600 enc-bordereau-print-amber" />
                <div
                    id="enc-bordereau-print-root"
                    class="bg-white px-5 py-6 text-neutral-900 sm:px-8 sm:py-7 print:shadow-none"
                >
                    <header class="flex items-start justify-between gap-4 border-b border-neutral-200 pb-4">
                        <img
                            src="/logo_Cofina.png"
                            :alt="`${entite.raison_sociale} — ${entite.sous_titre ?? 'Compagnie Financière Africaine'}`"
                            class="h-11 w-auto max-w-[150px] shrink-0 object-contain object-left sm:h-12"
                            width="150"
                            height="48"
                        />
                        <div class="min-w-0 text-right text-xs leading-relaxed text-neutral-600">
                            <p class="font-semibold text-neutral-900">{{ entite.ligne_adresse }}</p>
                            <p v-if="entite.sous_titre" class="text-neutral-500">{{ entite.sous_titre }}</p>
                            <p class="tabular-nums">{{ entite.telephones }}</p>
                            <p class="break-all">{{ entite.email }}</p>
                        </div>
                    </header>

                    <template v-if="payload">
                        <div class="mt-4 mb-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-red-700">
                                Coficarte
                            </p>
                            <h2 class="mt-1 text-xl font-bold leading-tight tracking-tight text-neutral-900">
                                Bordereau d’encaissement
                            </h2>
                            <p class="mt-0.5 text-sm text-neutral-500">{{ metaBloc.typeOp }}</p>
                        </div>

                        <div
                            v-if="payload.row.encaissement_code?.trim()"
                            class="enc-bordereau-print-amber mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-center"
                        >
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-800">
                                Code à communiquer à la caisse
                            </p>
                            <p class="mt-1 font-mono text-2xl font-bold tracking-[0.18em] text-amber-950">
                                {{ payload.row.encaissement_code }}
                            </p>
                        </div>

                        <dl class="mb-4 grid grid-cols-1 gap-3 rounded-lg bg-neutral-50 px-4 py-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-neutral-500">Date</dt>
                                <dd class="mt-0.5 font-semibold tabular-nums text-neutral-950">{{ metaBloc.date }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-neutral-500">
                                    N° transaction
                                </dt>
                                <dd class="mt-0.5 break-all font-semibold tabular-nums text-neutral-950">
                                    {{ metaBloc.numero }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-neutral-500">
                                    Opération
                                </dt>
                                <dd class="mt-0.5 font-semibold text-neutral-950">{{ metaBloc.typeOp }}</dd>
                            </div>
                        </dl>

                        <div v-if="payload.kind === 'vente'" class="space-y-4 text-sm">
                            <section>
                                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                                    Client
                                </h3>
                                <div class="grid grid-cols-[8.5rem_1fr] gap-x-3 gap-y-1.5">
                                    <span class="text-neutral-500">Prénom</span>
                                    <span class="font-medium text-neutral-950">{{ venteNames.prenom }}</span>
                                    <span class="text-neutral-500">Nom</span>
                                    <span class="font-medium text-neutral-950">{{ venteNames.nom }}</span>
                                    <span class="text-neutral-500">Téléphone</span>
                                    <span class="font-medium tabular-nums text-neutral-950">{{
                                        payload.row.telephone_client?.trim() || '—'
                                    }}</span>
                                    <span class="text-neutral-500">E-mail</span>
                                    <span class="break-all font-medium text-neutral-950">{{
                                        payload.row.email_client?.trim() || '—'
                                    }}</span>
                                    <span class="text-neutral-500">Pièce d’identité</span>
                                    <span class="break-all font-medium text-neutral-950">{{
                                        libellePiece(payload.row.kyc_type_piece, payload.row.kyc_numero_piece)
                                    }}</span>
                                    <span class="text-neutral-500">Compte</span>
                                    <span class="font-medium text-neutral-950">{{
                                        libelleComptePack(payload.row.compte_client_pack)
                                    }}</span>
                                    <template v-if="payload.row.compte_client_pack === 'in_pack'">
                                        <span class="text-neutral-500">N° de compte</span>
                                        <span class="font-medium tabular-nums tracking-wide text-neutral-950">{{
                                            maskCompte(payload.row.numero_compte_client)
                                        }}</span>
                                    </template>
                                    <span class="text-neutral-500">Type d’acheteur</span>
                                    <span class="font-medium text-neutral-950">{{
                                        payload.row.type_acheteur?.trim() || '—'
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

                            <section class="border-t border-neutral-100 pt-4">
                                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                                    Carte et agence
                                </h3>
                                <div class="grid grid-cols-[8.5rem_1fr] gap-x-3 gap-y-1.5">
                                    <span class="text-neutral-500">Carte</span>
                                    <span class="font-mono font-medium text-neutral-950">{{
                                        formatCardNumberDisplay(payload.row.numero_carte)
                                    }}</span>
                                    <span class="text-neutral-500">Vendeur</span>
                                    <span class="font-medium text-neutral-950">{{ payload.row.vendeur }}</span>
                                    <span class="text-neutral-500">Agence</span>
                                    <span class="font-medium text-neutral-950">{{ payload.row.agence }}</span>
                                    <span class="text-neutral-500">Date de vente</span>
                                    <span class="font-medium tabular-nums text-neutral-950">{{
                                        payload.row.date_vente ?? '—'
                                    }}</span>
                                </div>
                            </section>

                            <section class="border-t border-neutral-100 pt-4">
                                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                                    Montants
                                </h3>
                                <div class="space-y-1.5">
                                    <div class="flex items-baseline justify-between gap-4">
                                        <span class="text-neutral-500">Prix de la carte</span>
                                        <span class="font-medium tabular-nums text-neutral-950">{{
                                            formatCfa(payload.row.prix_vente)
                                        }}</span>
                                    </div>
                                    <div class="flex items-baseline justify-between gap-4">
                                        <span class="text-neutral-500">Première recharge</span>
                                        <span class="font-medium tabular-nums text-neutral-950">{{
                                            (payload.row.montant_premiere_recharge ?? 0) > 0
                                                ? formatCfa(Number(payload.row.montant_premiere_recharge))
                                                : '—'
                                        }}</span>
                                    </div>
                                    <div
                                        class="enc-bordereau-print-amber mt-2 flex items-baseline justify-between gap-4 rounded-lg bg-neutral-900 px-3 py-2.5 text-white"
                                    >
                                        <span class="text-sm font-medium">Total à encaisser</span>
                                        <span class="text-base font-bold tabular-nums">{{
                                            formatCfa(
                                                payload.row.montant_total_a_encaisser ??
                                                    payload.row.prix_vente +
                                                        Number(payload.row.montant_premiere_recharge ?? 0),
                                            )
                                        }}</span>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div v-else class="space-y-4 text-sm">
                            <section>
                                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                                    Titulaire
                                </h3>
                                <div class="grid grid-cols-[8.5rem_1fr] gap-x-3 gap-y-1.5">
                                    <span class="text-neutral-500">Prénom</span>
                                    <span class="font-medium text-neutral-950">{{ rechargeNames.prenom }}</span>
                                    <span class="text-neutral-500">Nom</span>
                                    <span class="font-medium text-neutral-950">{{ rechargeNames.nom }}</span>
                                    <span class="text-neutral-500">E-mail</span>
                                    <span class="break-all font-medium text-neutral-950">{{
                                        payload.row.email_titulaire?.trim() || '—'
                                    }}</span>
                                    <span class="text-neutral-500">Carte</span>
                                    <span class="font-mono font-medium text-neutral-950">{{
                                        formatCardNumberDisplay(payload.row.numero_carte)
                                    }}</span>
                                    <span class="text-neutral-500">Agence</span>
                                    <span class="font-medium text-neutral-950">{{ payload.row.agence }}</span>
                                    <span class="text-neutral-500">Saisi par</span>
                                    <span class="font-medium text-neutral-950">{{ payload.row.demandeur }}</span>
                                    <span class="text-neutral-500">Commentaire</span>
                                    <span class="whitespace-pre-wrap break-words font-medium text-neutral-950">{{
                                        payload.row.commentaire?.trim() || '—'
                                    }}</span>
                                </div>
                            </section>
                            <section class="border-t border-neutral-100 pt-4">
                                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                                    Montants
                                </h3>
                                <div class="space-y-1.5">
                                    <div class="flex items-baseline justify-between gap-4">
                                        <span class="text-neutral-500">Montant recharge</span>
                                        <span class="font-medium tabular-nums">{{ formatCfa(payload.row.montant) }}</span>
                                    </div>
                                    <div class="flex items-baseline justify-between gap-4">
                                        <span class="text-neutral-500">Honoraire de chargement</span>
                                        <span class="font-medium tabular-nums">{{
                                            formatCfa(Number(payload.row.honoraire_chargement ?? 0))
                                        }}</span>
                                    </div>
                                    <div
                                        class="enc-bordereau-print-amber mt-2 flex items-baseline justify-between gap-4 rounded-lg bg-neutral-900 px-3 py-2.5 text-white"
                                    >
                                        <span class="text-sm font-medium">Total à encaisser</span>
                                        <span class="text-base font-bold tabular-nums">{{
                                            formatCfa(
                                                payload.row.montant_total_a_encaisser ??
                                                    payload.row.montant +
                                                        Number(payload.row.honoraire_chargement ?? 0),
                                            )
                                        }}</span>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="enc-no-print mt-6 flex justify-center">
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
