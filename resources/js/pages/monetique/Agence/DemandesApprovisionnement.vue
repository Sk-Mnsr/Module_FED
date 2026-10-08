<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import OdConfirmDialog from '@/components/OdConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { FileText, Inbox, PackagePlus, Send } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monétique', href: '/monetique/coficarte' },
    { title: 'Demandes siège', href: '/monetique/agence/demandes-approvisionnement' },
];

type DemandeRow = {
    id: number;
    quantite_demandee: number;
    quantite_livree: number;
    cloture_partielle: boolean;
    commentaire: string | null;
    status: string;
    reponse_monetique: string | null;
    created_at: string | null;
    traite_le: string | null;
    bon_numero: string | null;
    transfer_statut: string | null;
    pending_transfer_id: number | null;
};

const props = withDefaults(defineProps<{ demandes?: DemandeRow[] }>(), {
    demandes: () => [],
});

const form = useForm({
    quantite_demandee: '' as number | '',
    commentaire: '',
});

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const enAttente = computed(() => props.demandes.filter((d) => d.status === 'en_attente').length);

const submit = () => {
    form.post('/monetique/agence/demandes-approvisionnement', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const annulerId = ref<number | null>(null);
const annulerProcessing = ref(false);

const ouvrirAnnulation = (id: number) => {
    annulerId.value = id;
};

const confirmerAnnulation = () => {
    if (annulerId.value === null) return;
    annulerProcessing.value = true;
    router.post(`/monetique/agence/demandes-approvisionnement/${annulerId.value}/annuler`, {}, {
        preserveScroll: true,
        onFinish: () => {
            annulerProcessing.value = false;
            annulerId.value = null;
        },
    });
};

const libelleStatut = (s: string) => {
    const m: Record<string, string> = {
        en_attente: 'En attente',
        transfert_en_cours: 'En transfert',
        partielle: 'Partielle',
        acceptee: 'Satisfaite',
        refusee: 'Refusée',
        annulee: 'Annulée',
    };
    return m[s] ?? s;
};

const detailStatut = (s: string) => {
    const m: Record<string, string> = {
        en_attente: 'En attente de traitement par la monétique centrale',
        transfert_en_cours: 'Transfert envoyé, en attente de réception',
        partielle: 'Partiellement livrée, une suite reste possible',
        acceptee: 'Demande satisfaite et clôturée',
        refusee: 'Demande refusée par la monétique',
        annulee: 'Demande annulée par l’agence',
    };
    return m[s] ?? s;
};

const badgeStatut = (s: string) => {
    const styles: Record<string, string> = {
        en_attente: 'bg-amber-50 text-amber-950 border-amber-200',
        transfert_en_cours: 'bg-sky-50 text-sky-950 border-sky-200',
        partielle: 'bg-teal-50 text-teal-950 border-teal-200',
        acceptee: 'bg-emerald-50 text-emerald-950 border-emerald-200',
        refusee: 'bg-rose-50 text-rose-950 border-rose-200',
        annulee: 'bg-gray-100 text-gray-700 border-gray-200',
    };
    return styles[s] ?? 'bg-gray-50 text-gray-800 border-gray-200';
};
</script>

<template>
    <Head title="Demandes d’approvisionnement siège" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-5 p-6 w-full">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="rounded-xl bg-violet-100 p-2.5 text-violet-800">
                        <PackagePlus class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Demander des cartes au siège</h1>
                        <p class="mt-0.5 text-sm text-gray-600">
                            La monétique centrale traite la demande, puis associe un transfert et un bon.
                        </p>
                    </div>
                </div>
                <p v-if="demandes.length" class="text-sm text-gray-500">
                    {{ demandes.length }} demande(s)
                    <span v-if="enAttente"> · {{ enAttente }} en attente</span>
                </p>
            </div>

            <div
                v-if="flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash?.error"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
            >
                {{ flash.error }}
            </div>

            <div class="grid items-start gap-5 xl:grid-cols-[20rem_minmax(0,1fr)]">
                <form
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm space-y-4 xl:sticky xl:top-4"
                    @submit.prevent="submit"
                >
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Nouvelle demande</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Quantité et commentaire éventuel.</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="quantite" class="text-xs font-medium text-gray-600">Quantité souhaitée</Label>
                        <Input
                            id="quantite"
                            v-model.number="form.quantite_demandee"
                            type="number"
                            min="1"
                            class="border-gray-300"
                            placeholder="Ex. 10"
                        />
                        <InputError :message="form.errors.quantite_demandee" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="com" class="text-xs font-medium text-gray-600">Commentaire</Label>
                        <textarea
                            id="com"
                            v-model="form.commentaire"
                            rows="4"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500"
                            placeholder="Contexte, urgence, point de contact…"
                        />
                        <InputError :message="form.errors.commentaire" />
                    </div>
                    <Button type="submit" class="w-full bg-violet-600 hover:bg-violet-700" :disabled="form.processing">
                        <Send class="mr-2 h-4 w-4" />
                        Envoyer la demande
                    </Button>
                </form>

                <section class="min-w-0 rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-4 py-3">
                        <h2 class="text-sm font-semibold text-gray-900">Historique des demandes</h2>
                    </div>

                    <div
                        v-if="!demandes.length"
                        class="px-6 py-16 text-center"
                    >
                        <Inbox class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                        <p class="text-sm text-gray-600">Aucune demande encore enregistrée.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                    <th class="px-4 py-2.5">N°</th>
                                    <th class="px-4 py-2.5">Demandé le</th>
                                    <th class="px-4 py-2.5 text-right">Qté</th>
                                    <th class="px-4 py-2.5 text-right">Livré</th>
                                    <th class="px-4 py-2.5">Statut</th>
                                    <th class="px-4 py-2.5">Détail</th>
                                    <th class="px-4 py-2.5"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="d in demandes"
                                    :key="d.id"
                                    class="border-b border-gray-100 last:border-0 align-top"
                                >
                                    <td class="whitespace-nowrap px-4 py-3 font-mono font-medium text-gray-900">#{{ d.id }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ d.created_at || '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums font-medium text-gray-900">
                                        {{ d.quantite_demandee }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700">
                                        {{ d.quantite_livree }} / {{ d.quantite_demandee }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="['inline-flex whitespace-nowrap rounded-md border px-2 py-0.5 text-xs font-semibold', badgeStatut(d.status)]"
                                            :title="detailStatut(d.status)"
                                        >
                                            {{
                                                d.status === 'acceptee' && d.cloture_partielle
                                                    ? 'Clôturée — reliquat'
                                                    : libelleStatut(d.status)
                                            }}
                                        </span>
                                        <p v-if="d.traite_le" class="mt-1 whitespace-nowrap text-xs text-gray-500">
                                            Traitée le {{ d.traite_le }}
                                        </p>
                                    </td>
                                    <td class="max-w-xs px-4 py-3 text-gray-700">
                                        <p v-if="d.commentaire" class="line-clamp-2">{{ d.commentaire }}</p>
                                        <p
                                            v-if="d.bon_numero"
                                            class="mt-1 inline-flex items-center gap-1 font-mono text-xs text-violet-800"
                                        >
                                            <FileText class="h-3.5 w-3.5" />
                                            Bon {{ d.bon_numero }}
                                        </p>
                                        <p v-if="d.reponse_monetique" class="mt-1 text-xs text-amber-900">
                                            {{ d.reponse_monetique }}
                                        </p>
                                        <span v-if="!d.commentaire && !d.bon_numero && !d.reponse_monetique" class="text-gray-400">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Button
                                            v-if="d.status === 'en_attente'"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="border-rose-200 text-rose-800 hover:bg-rose-50"
                                            @click="ouvrirAnnulation(d.id)"
                                        >
                                            Annuler
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <OdConfirmDialog
                :open="annulerId !== null"
                title="Annuler cette demande ?"
                description="La monétique centrale ne la traitera plus. Cette action est définitive."
                confirm-label="Annuler la demande"
                cancel-label="Retour"
                variant="danger"
                :loading="annulerProcessing"
                @update:open="(v) => { if (!v) annulerId = null }"
                @confirm="confirmerAnnulation"
            />
        </div>
    </AppLayout>
</template>
