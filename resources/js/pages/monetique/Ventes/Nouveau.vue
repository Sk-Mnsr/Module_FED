<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ExpirationBar from '@/components/ExpirationBar.vue';
import CoficartePlasticPreview from '@/components/monetique/CoficartePlasticPreview.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CreditCard, Eraser, Save, ShoppingCart } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Ventes', href: '/monetique/ventes/nouveau' },
    { title: 'Nouveau', href: '/monetique/ventes/nouveau' },
];

type CarteVenteOption = {
    id: number;
    numero_carte: string;
    prix_vente: number;
    prix_achat: number | null;
    date_livraison: string | null;
    date_expiration: string | null;
    reference_facture: string | null;
    reference_bon_livraison: string | null;
    possesseur: string | null;
    status: string;
    agence_nom: string | null;
    agence_code: string | null;
};

type ApporteurOption = { id: number; nom: string; code: string | null };
type CampagneOption = { id: number; nom: string };

const props = withDefaults(
    defineProps<{
        cartesDisponibles?: CarteVenteOption[];
        apporteurs?: ApporteurOption[];
        campagnes?: CampagneOption[];
        apporteurRequis?: boolean;
    }>(),
    {
        cartesDisponibles: () => [],
        apporteurs: () => [],
        campagnes: () => [],
        apporteurRequis: false,
    },
);

const typesAcheteur = ['Particulier', 'Entreprise'];

const optionsCompteClient = [
    { value: 'in_pack', label: 'In Pack' },
    { value: 'hors_pack', label: 'Hors Pack' },
] as const;

const ficheInputKey = ref(0);

const form = useForm({
    coficarte_card_id: '' as number | '',
    date_vente: '',
    derniers_4: '',
    type_acheteur: '',
    nom_client: '',
    compte_client_pack: '' as '' | 'in_pack' | 'hors_pack',
    numero_compte_client: '',
    telephone_client: '',
    email_client: '',
    adresse_client: '',
    montant_premiere_recharge: '' as number | '',
    coficarte_apporteur_id: '' as number | '',
    coficarte_campaign_id: '' as number | '',
    kyc_type_piece: '',
    kyc_numero_piece: '',
    kyc_date_emission: '',
    fiche_enrolement: null as File | null,
    gpt_id: '',
});

const carteSelectionnee = computed(() => {
    const id = form.coficarte_card_id;
    if (id === '' || id === null) {
        return null;
    }
    return props.cartesDisponibles.find((c) => c.id === Number(id)) ?? null;
});

function formatCfa(n: number | null | undefined): string {
    if (n == null || Number.isNaN(Number(n))) {
        return '—';
    }
    return `${Number(n).toLocaleString('fr-FR')} F CFA`;
}

function formatDateFr(iso: string | null | undefined): string {
    if (!iso?.trim()) {
        return '—';
    }
    const [y, m, d] = iso.split('-');
    if (!y || !m || !d) {
        return iso;
    }
    return `${d}/${m}/${y}`;
}

/** Affichage MM/AA sur le plastique (zone « Expire fin »). */
function expirationPlasticMmYy(iso: string | null | undefined): string | null {
    if (!iso?.trim()) {
        return null;
    }
    const [y, m] = iso.split('-');
    if (!y || !m) {
        return null;
    }
    return `${m}/${y.slice(-2)}`;
}

const reset = () => {
    form.coficarte_card_id = '';
    form.date_vente = '';
    form.derniers_4 = '';
    form.type_acheteur = '';
    form.nom_client = '';
    form.compte_client_pack = '';
    form.numero_compte_client = '';
    form.telephone_client = '';
    form.email_client = '';
    form.adresse_client = '';
    form.montant_premiere_recharge = '';
    form.coficarte_apporteur_id = '';
    form.coficarte_campaign_id = '';
    form.kyc_type_piece = '';
    form.kyc_numero_piece = '';
    form.kyc_date_emission = '';
    form.fiche_enrolement = null;
    ficheInputKey.value += 1;
    form.clearErrors();
};

watch(
    () => form.coficarte_card_id,
    (id) => {
        if (id === '' || id === null) {
            return;
        }
        const c = props.cartesDisponibles.find((x) => x.id === Number(id));
        if (!c?.numero_carte) {
            return;
        }
        const digits = String(c.numero_carte).replace(/\D/g, '');
        if (digits.length >= 4) {
            form.derniers_4 = digits.slice(-4);
        }
    },
);

watch(
    () => form.compte_client_pack,
    (v) => {
        if (v === 'hors_pack') {
            form.numero_compte_client = '';
        }
    },
);

const onFicheChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.fiche_enrolement = input.files?.[0] ?? null;
    form.clearErrors('fiche_enrolement');
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        coficarte_card_id: data.coficarte_card_id === '' ? null : Number(data.coficarte_card_id),
        montant_premiere_recharge:
            data.montant_premiere_recharge === '' ? null : Number(data.montant_premiere_recharge),
        numero_compte_client: data.compte_client_pack === 'hors_pack' ? '' : data.numero_compte_client,
        coficarte_apporteur_id:
            data.coficarte_apporteur_id === '' || data.coficarte_apporteur_id === null
                ? null
                : Number(data.coficarte_apporteur_id),
        coficarte_campaign_id:
            data.coficarte_campaign_id === '' || data.coficarte_campaign_id === null
                ? null
                : Number(data.coficarte_campaign_id),
    })).post('/monetique/ventes', {
        preserveScroll: true,
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Monétique - Ventes - Nouveau" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-6 max-w-6xl mx-auto w-full">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-violet-100 text-violet-700 rounded-xl shrink-0">
                        <ShoppingCart class="h-6 w-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Nouvelle vente Coficarte</h1>
                        <p class="text-sm text-gray-500 mt-1">
                            Choisissez une carte en stock, vérifiez les informations, puis saisissez les données client.
                        </p>
                    </div>
                </div>
                <Button type="button" variant="outline" class="shrink-0 border-violet-200 text-violet-800" @click="router.visit('/monetique/ventes/historique')">
                    Historique des ventes
                </Button>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    <!-- Panneau carte -->
                    <aside class="lg:col-span-4 space-y-4 lg:sticky lg:top-6">
                        <div class="rounded-2xl border border-violet-200/80 bg-gradient-to-br from-violet-50/90 via-white to-white shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-violet-100/80 bg-violet-50/50">
                                <h2 class="text-sm font-semibold text-violet-950 flex items-center gap-2">
                                    <CreditCard class="h-4 w-4 shrink-0" />
                                    Carte vendue
                                </h2>
                            </div>
                            <div class="p-5 space-y-4">
                                <div class="space-y-2">
                                    <Label for="coficarte_card_id" class="text-xs font-medium text-gray-600">Sélection</Label>
                                    <select
                                        id="coficarte_card_id"
                                        v-model="form.coficarte_card_id"
                                        class="flex h-10 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500"
                                    >
                                        <option value="">— Choisir une carte —</option>
                                        <option v-for="c in cartesDisponibles" :key="c.id" :value="c.id" class="font-mono">
                                            {{ formatCardNumberDisplay(c.numero_carte) }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.coficarte_card_id" />
                                </div>

                                <template v-if="carteSelectionnee">
                                    <CoficartePlasticPreview
                                        :numero-carte="carteSelectionnee.numero_carte"
                                        :expiration="expirationPlasticMmYy(carteSelectionnee.date_expiration)"
                                        :reference-facture="carteSelectionnee.reference_facture"
                                        :show-footer-note="false"
                                    />
                                    <div
                                        class="rounded-xl bg-gray-50/80 border border-gray-100 px-3 py-3 flex justify-between items-baseline gap-3 text-sm"
                                    >
                                        <span class="text-gray-500 shrink-0">Prix de vente</span>
                                        <span class="font-semibold text-violet-700 tabular-nums text-right">{{
                                            formatCfa(carteSelectionnee.prix_vente)
                                        }}</span>
                                    </div>

                                    <div class="rounded-xl border border-amber-200/90 bg-gradient-to-br from-amber-50 via-orange-50/40 to-amber-50/30 px-4 py-3 shadow-sm">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-900/75 mb-2">Expiration de la carte</p>
                                        <ExpirationBar
                                            class="w-full"
                                            :expiration="formatDateFr(carteSelectionnee.date_expiration)"
                                            :date-expiration="carteSelectionnee.date_expiration ?? ''"
                                        />
                                    </div>

                                    <div class="space-y-2 pt-2 border-t border-gray-100">
                                        <Label for="derniers_4" class="text-xs font-medium text-gray-600">Contrôle — 4 derniers chiffres</Label>
                                        <Input
                                            id="derniers_4"
                                            v-model="form.derniers_4"
                                            placeholder="8456"
                                            maxlength="4"
                                            class="border-gray-200 font-mono tracking-widest"
                                        />
                                        <p class="text-[11px] text-gray-500">Prérempli depuis le numéro ; à confirmer avec la carte physique.</p>
                                        <InputError :message="form.errors.derniers_4" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="date_vente" class="text-xs font-medium text-gray-600">Date de la vente</Label>
                                        <Input id="date_vente" v-model="form.date_vente" type="date" class="border-gray-200" />
                                        <InputError :message="form.errors.date_vente" />
                                    </div>
                                </template>

                                <div
                                    v-else
                                    class="rounded-xl border border-dashed border-violet-200 bg-violet-50/30 px-4 py-8 text-center text-sm text-violet-800/80"
                                >
                                    Sélectionnez une carte dans la liste pour afficher prix, dates, références et entité.
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- Formulaire client & vente -->
                    <div class="lg:col-span-8 space-y-6">
                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6 space-y-5">
                            <h3 class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2">Acheteur & contact</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="type_acheteur" class="text-xs font-medium text-gray-600">Type de l’acheteur</Label>
                                    <select
                                        id="type_acheteur"
                                        v-model="form.type_acheteur"
                                        class="flex h-10 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
                                    >
                                        <option value="">— Sélectionner —</option>
                                        <option v-for="t in typesAcheteur" :key="t" :value="t">{{ t }}</option>
                                    </select>
                                    <InputError :message="form.errors.type_acheteur" />
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="nom_client" class="text-xs font-medium text-gray-600">Titulaire de la carte</Label>
                                    <Input id="nom_client" v-model="form.nom_client" placeholder="Ex : Jean Dupont" class="border-gray-200" />
                                    <InputError :message="form.errors.nom_client" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="telephone_client" class="text-xs font-medium text-gray-600">Téléphone</Label>
                                    <Input id="telephone_client" v-model="form.telephone_client" placeholder="Ex : +228 90 90 90 90" class="border-gray-200" />
                                    <InputError :message="form.errors.telephone_client" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="email_client" class="text-xs font-medium text-gray-600">E-mail</Label>
                                    <Input
                                        id="email_client"
                                        v-model="form.email_client"
                                        type="email"
                                        autocomplete="email"
                                        placeholder="Ex : client@exemple.com"
                                        class="border-gray-200"
                                    />
                                    <InputError :message="form.errors.email_client" />
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <Label for="adresse_client" class="text-xs font-medium text-gray-600">Adresse</Label>
                                    <textarea
                                        id="adresse_client"
                                        v-model="form.adresse_client"
                                        rows="3"
                                        maxlength="2000"
                                        placeholder="Adresse complète"
                                        class="flex min-h-[88px] w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500"
                                    />
                                    <InputError :message="form.errors.adresse_client" />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6 space-y-5">
                            <h3 class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2">Compte client</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="compte_client_pack" class="text-xs font-medium text-gray-600">Compte client</Label>
                                    <select
                                        id="compte_client_pack"
                                        v-model="form.compte_client_pack"
                                        class="flex h-10 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
                                    >
                                        <option value="">— Sélectionner —</option>
                                        <option v-for="o in optionsCompteClient" :key="o.value" :value="o.value">{{ o.label }}</option>
                                    </select>
                                    <InputError :message="form.errors.compte_client_pack" />
                                </div>
                                <div v-if="form.compte_client_pack === 'in_pack'" class="space-y-2 sm:col-span-2">
                                    <Label for="numero_compte_client" class="text-xs font-medium text-gray-600">Numéro de compte</Label>
                                    <Input id="numero_compte_client" v-model="form.numero_compte_client" placeholder="Ex : 251789654001" class="border-gray-200 font-mono" />
                                    <InputError :message="form.errors.numero_compte_client" />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6 space-y-5">
                            <h3 class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-2">Opération & pièces</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="montant_premiere_recharge" class="text-xs font-medium text-gray-600">Montant 1<sup>re</sup> recharge (FCFA)</Label>
                                    <Input
                                        id="montant_premiere_recharge"
                                        v-model="form.montant_premiere_recharge"
                                        type="number"
                                        min="0"
                                        step="1"
                                        placeholder="0"
                                        class="border-gray-200"
                                    />
                                    <InputError :message="form.errors.montant_premiere_recharge" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="apporteur" class="text-xs font-medium text-gray-600">
                                        Apporteur d’affaires
                                        <span v-if="apporteurRequis" class="text-rose-600">*</span>
                                    </Label>
                                    <select
                                        id="apporteur"
                                        v-model="form.coficarte_apporteur_id"
                                        class="flex h-10 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
                                    >
                                        <option value="">— Sélectionner —</option>
                                        <option v-for="a in apporteurs" :key="a.id" :value="a.id">
                                            {{ a.code ? `${a.nom} (${a.code})` : a.nom }}
                                        </option>
                                    </select>
                                    <p v-if="apporteurs.length === 0" class="text-xs text-gray-500">Aucun apporteur configuré.</p>
                                    <InputError :message="form.errors.coficarte_apporteur_id" />
                                </div>
                                <div v-if="campagnes.length" class="space-y-2 sm:col-span-2">
                                    <Label for="campagne" class="text-xs font-medium text-gray-600">Campagne (optionnel)</Label>
                                    <select
                                        id="campagne"
                                        v-model="form.coficarte_campaign_id"
                                        class="flex h-10 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
                                    >
                                        <option value="">— Aucune —</option>
                                        <option v-for="c in campagnes" :key="c.id" :value="c.id">{{ c.nom }}</option>
                                    </select>
                                    <InputError :message="form.errors.coficarte_campaign_id" />
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:col-span-2 md:items-end">
                                    <div class="space-y-2">
                                        <Label for="fiche_enrolement" class="text-xs font-medium text-gray-600">Fiche d’enrôlement</Label>
                                        <input
                                            id="fiche_enrolement"
                                            :key="ficheInputKey"
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                            class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border file:border-gray-200 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-gray-50"
                                            @change="onFicheChange"
                                        />
                                        <p class="text-xs text-gray-500">PDF, JPEG ou PNG — 10 Mo max.</p>
                                        <InputError :message="form.errors.fiche_enrolement" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="gpt_id" class="text-xs font-medium text-gray-600">ID GPT</Label>
                                        <Input
                                            id="gpt_id"
                                            v-model="form.gpt_id"
                                            type="text"
                                            autocomplete="off"
                                            placeholder="Identifiant GPT (optionnel)"
                                            class="border-gray-200"
                                        />
                                        <p class="text-xs text-gray-500">Référence associée à l’enrôlement si applicable.</p>
                                        <InputError :message="form.errors.gpt_id" />
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 pt-2">
                                <Label class="text-xs font-medium text-gray-600">KYC — pièce d’identité</Label>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div class="space-y-1">
                                        <Label for="kyc_type_piece" class="text-[10px] text-gray-500">Type</Label>
                                        <Input id="kyc_type_piece" v-model="form.kyc_type_piece" placeholder="CNI, Passeport…" class="border-gray-200" />
                                        <InputError :message="form.errors.kyc_type_piece" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label for="kyc_numero_piece" class="text-[10px] text-gray-500">Numéro</Label>
                                        <Input id="kyc_numero_piece" v-model="form.kyc_numero_piece" class="border-gray-200" />
                                        <InputError :message="form.errors.kyc_numero_piece" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label for="kyc_date_emission" class="text-[10px] text-gray-500">Date d’émission</Label>
                                        <Input id="kyc_date_emission" v-model="form.kyc_date_emission" type="date" class="border-gray-200" />
                                        <InputError :message="form.errors.kyc_date_emission" />
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-2 border-t border-gray-100">
                    <p class="text-xs text-gray-500 text-center sm:text-left">
                        Les champs marqués par l’étoile sur l’apporteur suivent la configuration de votre agence.
                    </p>
                    <div class="flex items-center justify-end gap-2">
                        <Button type="button" variant="outline" @click="reset">
                            <Eraser class="h-4 w-4 mr-2" />
                            Effacer
                        </Button>
                        <Button
                            type="submit"
                            class="bg-violet-600 hover:bg-violet-700 min-w-[140px]"
                            :disabled="form.processing || !form.coficarte_card_id"
                        >
                            <Save class="h-4 w-4 mr-2" />
                            Enregistrer
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
