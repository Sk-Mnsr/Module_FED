<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Trash2 } from 'lucide-vue-next';

type Tranche = {
    libelle?: string | null;
    montant_min?: number | string | null;
    montant_max?: number | string | null;
    frais_fixe?: number | string | null;
    taux?: number | string | null;
    frais_min?: number | string | null;
    frais_max?: number | string | null;
};

type Ligne = {
    sens: string;
    compte: string;
    libelle_ecriture?: string | null;
    nature_compte?: string;
    type_montant?: string;
    montant_fixe?: number | string | null;
    taux?: number | string | null;
    obligatoire?: boolean;
};

type Produit = {
    id?: number;
    code: string;
    libelle: string;
    type_operation?: string | null;
    devise: string;
    code_taf?: string | null;
    libelle_ecriture_produit?: string | null;
    libelle_ecriture_taf?: string | null;
    compte_produit?: string | null;
    compte_taf?: string | null;
    compte_client_mask?: string | null;
    initiateur?: string | null;
    validateur?: string | null;
    mode_frais: string;
    frais_fixe?: number | string | null;
    taux_frais?: number | string | null;
    frais_min?: number | string | null;
    frais_max?: number | string | null;
    taux_taf?: number | string | null;
    base_calcul?: string | null;
    notes?: string | null;
    statut: string;
    actif: boolean;
    tranches?: Tranche[];
    lignes?: Ligne[];
};

const props = defineProps<{
    produit: Produit | null;
    tauxTafDefaut: number;
    statuts: string[];
    modesFrais: string[];
    naturesCompte: string[];
    typesMontant: string[];
    initiateurs: string[];
}>();

const editing = computed(() => !!props.produit?.id);

const breadcrumbs = computed(() => [
    { title: 'POD', href: '/pod/produits' },
    {
        title: editing.value ? `Modifier ${props.produit?.code}` : 'Nouveau produit',
        href: editing.value ? `/pod/produits/${props.produit?.id}/edit` : '/pod/produits/create',
    },
]);

const emptyLigne = (): Ligne => ({
    sens: 'D',
    compte: '',
    libelle_ecriture: '',
    nature_compte: 'client',
    type_montant: 'frais_ht',
    obligatoire: true,
});

const emptyTranche = (): Tranche => ({
    libelle: '',
    montant_min: 0,
    montant_max: null,
    frais_fixe: null,
    taux: null,
    frais_min: null,
    frais_max: null,
});

const form = useForm({
    code: props.produit?.code ?? '',
    libelle: props.produit?.libelle ?? '',
    type_operation: props.produit?.type_operation ?? '',
    devise: props.produit?.devise ?? 'XOF',
    code_taf: props.produit?.code_taf ?? '',
    libelle_ecriture_produit: props.produit?.libelle_ecriture_produit ?? '',
    libelle_ecriture_taf: props.produit?.libelle_ecriture_taf ?? '',
    compte_produit: props.produit?.compte_produit ?? '',
    compte_taf: props.produit?.compte_taf ?? '331431012',
    compte_client_mask: props.produit?.compte_client_mask ?? '251XXXXXX',
    initiateur: props.produit?.initiateur ?? 'CC',
    validateur: props.produit?.validateur ?? '',
    mode_frais: props.produit?.mode_frais ?? 'fixe',
    frais_fixe: props.produit?.frais_fixe ?? '',
    taux_frais: props.produit?.taux_frais ?? '',
    frais_min: props.produit?.frais_min ?? '',
    frais_max: props.produit?.frais_max ?? '',
    taux_taf: props.produit?.taux_taf ?? '',
    base_calcul: props.produit?.base_calcul ?? '',
    notes: props.produit?.notes ?? '',
    statut: props.produit?.statut ?? 'brouillon',
    actif: props.produit?.actif ?? true,
    tranches: (props.produit?.tranches?.length ? props.produit.tranches : []) as Tranche[],
    lignes: (props.produit?.lignes?.length ? props.produit.lignes : [emptyLigne(), emptyLigne()]) as Ligne[],
});

const submit = () => {
    if (editing.value) {
        form.put(`/pod/produits/${props.produit!.id}`);
    } else {
        form.post('/pod/produits');
    }
};

const fieldError = (key: string) => (form.errors as Record<string, string>)[key];
</script>

<template>
    <Head :title="editing ? `Modifier ${produit?.code}` : 'Nouveau produit POD'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="mx-auto flex max-w-5xl flex-col gap-6 p-6" @submit.prevent="submit">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">
                        {{ editing ? 'Modifier le produit' : 'Nouveau produit POD' }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Taux TAF par défaut applicatif : {{ tauxTafDefaut }} %
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button as-child type="button" variant="outline">
                        <Link href="/pod/produits">Annuler</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Enregistrer</Button>
                </div>
            </div>

            <section class="grid gap-4 rounded-xl border border-border bg-card p-5 md:grid-cols-2">
                <h2 class="md:col-span-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Identité
                </h2>
                <div class="space-y-1">
                    <Label>Code produit *</Label>
                    <Input v-model="form.code" required />
                    <p v-if="fieldError('code')" class="text-xs text-destructive">{{ fieldError('code') }}</p>
                </div>
                <div class="space-y-1">
                    <Label>Libellé *</Label>
                    <Input v-model="form.libelle" required />
                </div>
                <div class="space-y-1">
                    <Label>Type d’opération</Label>
                    <Input v-model="form.type_operation" />
                </div>
                <div class="space-y-1">
                    <Label>Devise</Label>
                    <Input v-model="form.devise" />
                </div>
                <div class="space-y-1">
                    <Label>Initiateur</Label>
                    <select v-model="form.initiateur" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="i in initiateurs" :key="i" :value="i">{{ i }}</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <Label>Validateur</Label>
                    <Input v-model="form.validateur" placeholder="Profil habilité" />
                </div>
                <div class="space-y-1">
                    <Label>Statut</Label>
                    <select v-model="form.statut" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="s in statuts" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input id="actif" v-model="form.actif" type="checkbox" class="size-4 rounded border" />
                    <Label for="actif">Produit actif</Label>
                </div>
            </section>

            <section class="grid gap-4 rounded-xl border border-border bg-card p-5 md:grid-cols-2">
                <h2 class="md:col-span-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Comptes & écritures
                </h2>
                <div class="space-y-1">
                    <Label>Compte produit</Label>
                    <Input v-model="form.compte_produit" />
                </div>
                <div class="space-y-1">
                    <Label>Compte TAF</Label>
                    <Input v-model="form.compte_taf" />
                </div>
                <div class="space-y-1">
                    <Label>Masque compte client</Label>
                    <Input v-model="form.compte_client_mask" />
                </div>
                <div class="space-y-1">
                    <Label>Code TAF</Label>
                    <Input v-model="form.code_taf" />
                </div>
                <div class="space-y-1 md:col-span-2">
                    <Label>Libellé écriture produit</Label>
                    <Input v-model="form.libelle_ecriture_produit" />
                </div>
                <div class="space-y-1 md:col-span-2">
                    <Label>Libellé écriture TAF</Label>
                    <Input v-model="form.libelle_ecriture_taf" />
                </div>
            </section>

            <section class="grid gap-4 rounded-xl border border-border bg-card p-5 md:grid-cols-2">
                <h2 class="md:col-span-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                    Règles de frais
                </h2>
                <div class="space-y-1">
                    <Label>Mode de frais</Label>
                    <select v-model="form.mode_frais" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="m in modesFrais" :key="m" :value="m">{{ m }}</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <Label>Frais fixe</Label>
                    <Input v-model="form.frais_fixe" type="number" step="0.01" min="0" />
                </div>
                <div class="space-y-1">
                    <Label>Taux frais (%)</Label>
                    <Input v-model="form.taux_frais" type="number" step="0.01" min="0" />
                </div>
                <div class="space-y-1">
                    <Label>Taux TAF produit (%) — vide = défaut</Label>
                    <Input v-model="form.taux_taf" type="number" step="0.01" min="0" />
                </div>
                <div class="space-y-1">
                    <Label>Minimum frais</Label>
                    <Input v-model="form.frais_min" type="number" step="0.01" min="0" />
                </div>
                <div class="space-y-1">
                    <Label>Plafond frais</Label>
                    <Input v-model="form.frais_max" type="number" step="0.01" min="0" />
                </div>
                <div class="space-y-1 md:col-span-2">
                    <Label>Base de calcul / règle texte</Label>
                    <textarea
                        v-model="form.base_calcul"
                        rows="2"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>
                <div class="space-y-1 md:col-span-2">
                    <Label>Notes</Label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>
            </section>

            <section class="space-y-3 rounded-xl border border-border bg-card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        Tranches de montant
                    </h2>
                    <Button type="button" size="sm" variant="outline" @click="form.tranches.push(emptyTranche())">
                        <Plus class="mr-1 h-4 w-4" /> Ajouter
                    </Button>
                </div>
                <div
                    v-for="(t, index) in form.tranches"
                    :key="index"
                    class="grid gap-2 rounded-lg border border-border p-3 md:grid-cols-7"
                >
                    <Input v-model="t.libelle" placeholder="Libellé" class="md:col-span-2" />
                    <Input v-model="t.montant_min" type="number" placeholder="Min" />
                    <Input v-model="t.montant_max" type="number" placeholder="Max" />
                    <Input v-model="t.frais_fixe" type="number" placeholder="Frais" />
                    <Input v-model="t.taux" type="number" placeholder="Taux %" />
                    <Button type="button" variant="ghost" size="icon" @click="form.tranches.splice(index, 1)">
                        <Trash2 class="h-4 w-4 text-destructive" />
                    </Button>
                </div>
                <p v-if="form.tranches.length === 0" class="text-sm text-muted-foreground">Aucune tranche.</p>
            </section>

            <section class="space-y-3 rounded-xl border border-border bg-card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                        Schéma comptable
                    </h2>
                    <Button type="button" size="sm" variant="outline" @click="form.lignes.push(emptyLigne())">
                        <Plus class="mr-1 h-4 w-4" /> Ligne
                    </Button>
                </div>
                <div
                    v-for="(l, index) in form.lignes"
                    :key="index"
                    class="grid gap-2 rounded-lg border border-border p-3 md:grid-cols-6"
                >
                    <select v-model="l.sens" class="h-10 rounded-md border border-input bg-background px-2 text-sm">
                        <option value="D">Débit</option>
                        <option value="C">Crédit</option>
                    </select>
                    <Input v-model="l.compte" placeholder="Compte" />
                    <Input v-model="l.libelle_ecriture" placeholder="Libellé" class="md:col-span-2" />
                    <select
                        v-model="l.nature_compte"
                        class="h-10 rounded-md border border-input bg-background px-2 text-sm"
                    >
                        <option v-for="n in naturesCompte" :key="n" :value="n">{{ n }}</option>
                    </select>
                    <div class="flex gap-1">
                        <select
                            v-model="l.type_montant"
                            class="h-10 flex-1 rounded-md border border-input bg-background px-2 text-sm"
                        >
                            <option v-for="t in typesMontant" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <Button type="button" variant="ghost" size="icon" @click="form.lignes.splice(index, 1)">
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <Button as-child type="button" variant="outline">
                    <Link href="/pod/produits">Annuler</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">Enregistrer</Button>
            </div>
        </form>
    </AppLayout>
</template>
