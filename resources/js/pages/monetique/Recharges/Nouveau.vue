<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CoficartePlasticPreview from '@/components/monetique/CoficartePlasticPreview.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCardNumberDisplay } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    Building2,
    CreditCard,
    History,
    Info,
    Mail,
    Megaphone,
    MessageSquare,
    Sparkles,
} from 'lucide-vue-next';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Recharges', href: '/monetique/recharges/historique' },
    { title: 'Nouvelle recharge', href: '/monetique/recharges/nouveau' },
];

type CampagneOption = { id: number; nom: string };

const props = withDefaults(
    defineProps<{
        campagnes?: CampagneOption[];
    }>(),
    {
        campagnes: () => [],
    },
);

const form = useForm({
    numero_carte: '',
    titulaire_carte: '',
    email_titulaire: '',
    montant: '' as number | '',
    honoraire_chargement: 0,
    commentaire: '',
    coficarte_campaign_id: '' as number | '',
});

const inputShell =
    'h-11 rounded-lg border-gray-200 bg-white shadow-none transition-[box-shadow,border-color] ' +
    'focus-visible:border-violet-500 focus-visible:ring-2 focus-visible:ring-violet-500/20';

const selectClass =
    'mt-1.5 flex h-11 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-none ' +
    'focus-visible:outline-none focus-visible:border-violet-500 focus-visible:ring-2 focus-visible:ring-violet-500/20';

const textareaClass =
    'mt-1.5 min-h-[100px] w-full rounded-lg border border-gray-200 bg-white px-3 py-3 text-sm shadow-none ' +
    'placeholder:text-gray-400 focus-visible:border-violet-500 focus-visible:ring-2 focus-visible:ring-violet-500/20';

const numeroAffiche = computed(() => formatCardNumberDisplay(form.numero_carte.trim()));

const selectedCampagne = computed(() => {
    const id = form.coficarte_campaign_id;
    if (id === '' || id === null) {
        return null;
    }
    return props.campagnes.find((c) => c.id === Number(id)) ?? null;
});

const montantPreview = computed(() => {
    const n = Number(form.montant);
    if (!Number.isFinite(n) || n <= 0) {
        return null;
    }
    return `${n.toLocaleString('fr-FR')} F CFA`;
});

const honorairesNum = computed(() => {
    const n = Number(form.honoraire_chargement);
    return Number.isFinite(n) && n >= 0 ? n : 0;
});

const totalEncaissementMontant = computed(() => {
    const m = Number(form.montant);
    if (!Number.isFinite(m) || m <= 0) {
        return null;
    }
    return m + honorairesNum.value;
});

const totalEncaissementLabel = computed(() => {
    const t = totalEncaissementMontant.value;
    if (t == null) {
        return null;
    }
    return `${t.toLocaleString('fr-FR')} F CFA`;
});

const submit = () => {
    form.transform((d) => ({
        ...d,
        numero_carte: typeof d.numero_carte === 'string' ? d.numero_carte.trim() : String(d.numero_carte ?? ''),
        montant: Number(d.montant),
        honoraire_chargement: Math.max(
            0,
            Number(d.honoraire_chargement === '' || d.honoraire_chargement === null ? 0 : d.honoraire_chargement) || 0,
        ),
        titulaire_carte: typeof d.titulaire_carte === 'string' ? d.titulaire_carte.trim() : d.titulaire_carte,
        email_titulaire:
            typeof d.email_titulaire === 'string' && d.email_titulaire.trim() !== ''
                ? d.email_titulaire.trim()
                : null,
        coficarte_campaign_id: d.coficarte_campaign_id === '' ? null : Number(d.coficarte_campaign_id),
    })).post('/monetique/recharges', { preserveScroll: true });
};

const goHistorique = () => router.visit('/monetique/recharges/historique');
</script>

<template>
    <Head title="Monétique — Nouvelle recharge" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-[calc(100vh-4rem)] bg-gradient-to-b from-slate-50/90 via-white to-violet-50/30">
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
                <!-- En-tête page -->
                <header class="mb-8 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-3">
                        <div class="inline-flex items-center gap-2 rounded-full bg-violet-100/90 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-violet-800">
                            <Sparkles class="h-3.5 w-3.5" />
                            Coficarte
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Nouvelle recharge</h1>
                            <p class="mt-1 text-sm text-gray-600">
                                Enregistrez une recharge à transmettre à la caisse pour encaissement.
                            </p>

                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11 shrink-0 self-start border-gray-200 bg-white/80 shadow-sm backdrop-blur-sm hover:bg-white lg:self-auto"
                        @click="goHistorique"
                    >
                        <History class="mr-2 h-4 w-4 text-violet-600" />
                        Historique des recharges
                    </Button>
                </header>

                <!-- Alerte workflow -->
                <div
                    class="mb-8 flex gap-4 rounded-2xl border border-amber-200/70 bg-gradient-to-r from-amber-50 to-amber-50/50 px-5 py-4 text-sm text-amber-950 shadow-sm"
                    role="status"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                        <Info class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 pt-0.5">
                        <p class="font-semibold text-amber-950">En attente caisse</p>
                        <p class="mt-1 leading-relaxed text-amber-900/90">
                            Tant que la caisse n’a pas confirmé l’encaissement, la recharge reste au statut
                            <span class="whitespace-nowrap font-medium">« En attente caisse »</span>. Vous pouvez suivre
                            l’avancement depuis l’historique.
                        </p>
                    </div>
                </div>

                <form class="grid gap-8 lg:grid-cols-12 lg:items-start" @submit.prevent="submit">
                    <!-- Colonne formulaire -->
                    <div class="space-y-6 lg:col-span-7">
                        <Card class="overflow-hidden border-gray-200/80 shadow-md shadow-gray-200/40">
                            <CardHeader class="border-b border-gray-100 bg-gray-50/60 pb-5">
                                <div class="flex items-start gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
                                        <CreditCard class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <CardTitle class="text-lg font-semibold text-gray-900">Carte et titulaire</CardTitle>
                                        <CardDescription class="mt-1 text-sm text-gray-600">
                                            Identifiez la carte rechargée et la personne côté client.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-5 pt-2">
                                <div>
                                    <Label for="numero-carte" class="text-sm font-medium text-gray-700">
                                        Numéro de carte <span class="text-red-600">*</span>
                                    </Label>
                                    <div class="relative mt-2">
                                        <CreditCard
                                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                        />
                                        <Input
                                            id="numero-carte"
                                            v-model="form.numero_carte"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            placeholder="Ex. 5399 8765 4321 0000"
                                            :class="[inputShell, 'pl-10 font-mono text-base tracking-wide']"
                                            required
                                        />
                                    </div>
                                    <InputError :message="form.errors.numero_carte" />
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label for="titulaire" class="text-sm font-medium text-gray-700">
                                            Titulaire de la carte <span class="text-red-600">*</span>
                                        </Label>
                                        <Input
                                            id="titulaire"
                                            v-model="form.titulaire_carte"
                                            type="text"
                                            autocomplete="name"
                                            placeholder="Nom et prénom"
                                            :class="['mt-2', inputShell]"
                                            required
                                        />
                                        <InputError :message="form.errors.titulaire_carte" />
                                    </div>
                                    <div>
                                        <Label for="email-titulaire" class="text-sm font-medium text-gray-700">E-mail titulaire</Label>
                                        <div class="relative mt-2">
                                            <Mail class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                            <Input
                                                id="email-titulaire"
                                                v-model="form.email_titulaire"
                                                type="email"
                                                autocomplete="email"
                                                placeholder="Facultatif"
                                                :class="[inputShell, 'pl-10']"
                                            />
                                        </div>
                                        <InputError :message="form.errors.email_titulaire" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="overflow-hidden border-gray-200/80 shadow-md shadow-gray-200/40">
                            <CardHeader class="border-b border-gray-100 bg-gray-50/60 pb-5">
                                <div class="flex items-start gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                        <Banknote class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <CardTitle class="text-lg font-semibold text-gray-900">Montants</CardTitle>
                                        <CardDescription class="mt-1 text-sm text-gray-600">
                                            Recharge créditée sur la carte et frais éventuels à encaisser à la caisse.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-5 pt-2">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label for="montant" class="text-sm font-medium text-gray-700">
                                            Montant recharge (F CFA) <span class="text-red-600">*</span>
                                        </Label>
                                        <Input
                                            id="montant"
                                            v-model.number="form.montant"
                                            type="number"
                                            min="1"
                                            step="1"
                                            placeholder="100000"
                                            :class="['mt-2 tabular-nums', inputShell]"
                                        />
                                        <InputError :message="form.errors.montant" />
                                    </div>
                                    <div>
                                        <Label for="honoraires" class="text-sm font-medium text-gray-700">
                                            Honoraires de chargement (F CFA) <span class="text-red-600">*</span>
                                        </Label>
                                        <Input
                                            id="honoraires"
                                            v-model.number="form.honoraire_chargement"
                                            type="number"
                                            min="0"
                                            step="1"
                                            placeholder="0"
                                            :class="['mt-2 tabular-nums', inputShell]"
                                            required
                                        />
                                        <InputError :message="form.errors.honoraire_chargement" />
                                    </div>
                                </div>

                                <div
                                    v-if="props.campagnes.length"
                                    class="rounded-xl border border-dashed border-gray-200 bg-gray-50/50 p-4"
                                >
                                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                                        <Megaphone class="h-4 w-4 text-orange-600" />
                                        Campagne commerciale
                                    </div>
                                    <Label for="campagne-select" class="mt-2 block text-xs font-medium text-gray-600">
                                        Facultatif
                                    </Label>
                                    <select id="campagne-select" v-model="form.coficarte_campaign_id" :class="selectClass">
                                        <option value="">Aucune campagne</option>
                                        <option v-for="c in props.campagnes" :key="c.id" :value="c.id">{{ c.nom }}</option>
                                    </select>
                                    <p v-if="selectedCampagne" class="mt-2 text-xs text-gray-600">
                                        Sélection : <span class="font-medium text-gray-800">{{ selectedCampagne.nom }}</span>
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="overflow-hidden border-gray-200/80 shadow-md shadow-gray-200/40">
                            <CardHeader class="border-b border-gray-100 bg-gray-50/60 pb-5">
                                <div class="flex items-start gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                                        <MessageSquare class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <CardTitle class="text-lg font-semibold text-gray-900">Commentaire interne</CardTitle>
                                        <CardDescription class="mt-1 text-sm text-gray-600">
                                            Visible dans votre périmètre (référence, contexte…).
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="pt-2">
                                <Label for="commentaire" class="text-sm font-medium text-gray-700">Remarque</Label>
                                <textarea
                                    id="commentaire"
                                    v-model="form.commentaire"
                                    :class="textareaClass"
                                    placeholder="Référence client, contexte de l’opération…"
                                />
                                <InputError :message="form.errors.commentaire" />
                            </CardContent>
                        </Card>

                        <!-- Actions desktop -->
                        <div class="hidden gap-3 sm:flex sm:justify-end lg:flex">
                            <Button
                                type="button"
                                variant="outline"
                                class="h-11 min-w-[120px] border-gray-200 bg-white"
                                :disabled="form.processing"
                                @click="goHistorique"
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                class="h-11 min-w-[200px] bg-violet-600 text-white shadow-lg shadow-violet-600/25 hover:bg-violet-700"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">Enregistrement…</span>
                                <span v-else>Enregistrer la recharge</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Colonne aperçu -->
                    <aside class="lg:col-span-5">
                        <div class="space-y-5 lg:sticky lg:top-24">
                            <!-- Carte visuelle (plastique Cofina / Visa) -->
                            <div class="space-y-2">
                                <p class="text-xs font-medium text-gray-500 text-center">Aperçu carte — recharge</p>
                                <CoficartePlasticPreview :numero-carte="form.numero_carte" :show-footer-note="false" />
                            </div>

                            <div class="rounded-2xl border border-gray-200/90 bg-white p-4 text-sm shadow-sm space-y-2">
                                <p class="text-xs font-semibold text-gray-600">Montants (aperçu)</p>
                                <template v-if="montantPreview">
                                    <p class="flex justify-between text-gray-700 gap-3">
                                        <span>Recharge</span>
                                        <span class="tabular-nums font-medium text-gray-900">{{ montantPreview }}</span>
                                    </p>
                                    <p v-if="honorairesNum > 0" class="flex justify-between text-gray-600 gap-3">
                                        <span>Honoraires</span>
                                        <span class="tabular-nums text-gray-900"
                                            >{{ honorairesNum.toLocaleString('fr-FR') }} F CFA</span
                                        >
                                    </p>
                                    <p
                                        v-if="totalEncaissementLabel"
                                        class="flex justify-between pt-2 border-t border-gray-100 text-base font-bold text-gray-900 gap-3"
                                    >
                                        <span>Total caisse</span>
                                        <span class="tabular-nums">{{ totalEncaissementLabel }}</span>
                                    </p>
                                </template>
                                <p v-else class="text-sm text-gray-500">Saisissez un montant pour voir le total à encaisser.</p>
                            </div>

                            <!-- Récap synthétique -->
                            <Card class="border-gray-200/80 shadow-md shadow-gray-200/40">
                                <CardHeader class="pb-2 pt-5">
                                    <div class="flex items-center gap-2">
                                        <Building2 class="h-4 w-4 text-gray-500" />
                                        <CardTitle class="text-base font-semibold">Récapitulatif</CardTitle>
                                    </div>
                                    <CardDescription class="text-xs">Vérification avant envoi à la caisse.</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-3 pb-5 text-sm">
                                    <div class="flex justify-between gap-4 border-b border-gray-100 py-2">
                                        <span class="text-gray-500">Titulaire</span>
                                        <span class="max-w-[60%] truncate text-right font-medium text-gray-900">
                                            {{ form.titulaire_carte.trim() || '—' }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between gap-4 border-b border-gray-100 py-2">
                                        <span class="text-gray-500">Montant recharge</span>
                                        <span class="tabular-nums font-medium text-gray-900">{{ montantPreview ?? '—' }}</span>
                                    </div>
                                    <div class="flex justify-between gap-4 border-b border-gray-100 py-2">
                                        <span class="text-gray-500">Honoraires</span>
                                        <span class="tabular-nums font-medium text-gray-900">{{
                                            honorairesNum > 0 ? `${honorairesNum.toLocaleString('fr-FR')} F CFA` : '0 F CFA'
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between gap-4 rounded-lg bg-emerald-50/80 px-3 py-3">
                                        <span class="font-semibold text-emerald-900">Total à encaisser</span>
                                        <span class="tabular-nums text-lg font-bold text-emerald-900">{{
                                            totalEncaissementLabel ?? '—'
                                        }}</span>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Actions mobile -->
                            <div class="flex flex-col gap-3 sm:hidden">
                                <Button
                                    type="submit"
                                    class="h-12 w-full bg-violet-600 text-white shadow-lg shadow-violet-600/20 hover:bg-violet-700"
                                    :disabled="form.processing"
                                >
                                    <span v-if="form.processing">Enregistrement…</span>
                                    <span v-else>Enregistrer la recharge</span>
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-12 w-full border-gray-200"
                                    :disabled="form.processing"
                                    @click="goHistorique"
                                >
                                    Annuler
                                </Button>
                            </div>
                        </div>
                    </aside>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
