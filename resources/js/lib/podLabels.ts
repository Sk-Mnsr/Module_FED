/** Libellés et styles partagés — module Produits divers */

export const modeFraisLabels: Record<string, string> = {
    fixe: 'Frais fixe',
    tranche: 'Par tranche',
    taux: 'Au pourcentage',
    mixte: 'Fixe + taux',
    manuel: 'Saisie manuelle',
    gratuit: 'Gratuit',
};

export const statutProduitLabels: Record<string, string> = {
    brouillon: 'Brouillon',
    valide: 'Validé',
    production: 'Production',
};

export const statutOperationLabels: Record<string, string> = {
    brouillon: 'Brouillon',
    valide: 'Validée',
    annule: 'Annulée',
};

export const modeConstanteLabels: Record<string, string> = {
    defaut: 'Valeur par défaut',
    champ: 'Champ saisi',
    schema: 'Schéma comptable',
    script: 'Script',
};

export function statutProduitClass(s: string): string {
    if (s === 'production') return 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200';
    if (s === 'valide') return 'bg-sky-100 text-sky-800 ring-1 ring-sky-200';
    return 'bg-amber-100 text-amber-900 ring-1 ring-amber-200';
}

export function statutOperationClass(s: string): string {
    if (s === 'valide') return 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200';
    if (s === 'annule') return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200';
    return 'bg-amber-100 text-amber-900 ring-1 ring-amber-200';
}

export function labelModeFrais(mode: string): string {
    return modeFraisLabels[mode] || mode;
}

export function labelStatutProduit(s: string): string {
    return statutProduitLabels[s] || s;
}

export function labelStatutOperation(s: string): string {
    return statutOperationLabels[s] || s;
}
