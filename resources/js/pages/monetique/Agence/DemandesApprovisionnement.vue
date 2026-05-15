<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, FileText, Inbox, PackagePlus, XCircle } from 'lucide-vue-next';

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

const submit = () => {
    form.post('/monetique/agence/demandes-approvisionnement', { preserveScroll: true });
};

const annuler = (id: number) => {
    if (!confirm('Annuler cette demande ?')) return;
    router.post(`/monetique/agence/demandes-approvisionnement/${id}/annuler`, {}, { preserveScroll: true });
};

const libelleStatut = (s: string) => {
    const m: Record<string, string> = {
        en_attente: 'En attente monétique',
        transfert_en_cours: 'Transfert en attente de réception',
        partielle: 'Partiellement livrée — suite possible',
        acceptee: 'Satisfaite / clôturée',
        refusee: 'Refusée',
        annulee: 'Annulée',
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
        <div class="flex flex-col gap-8 p-6 max-w-4xl mx-auto w-full">
            <div class="flex items-start gap-4">
                <div class="p-3.5 bg-violet-100 text-violet-800 rounded-2xl shadow-sm shrink-0">
                    <PackagePlus class="h-7 w-7" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Demander des cartes au siège</h1>
                    <p class="text-sm text-gray-600 mt-1 leading-relaxed max-w-2xl">
                        Votre demande est adressée à la <strong class="font-medium text-gray-800">monétique centrale</strong>. Après
                        traitement, un transfert et un bon peuvent être associés à votre demande.
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 bg-violet-50/40">
                    <h2 class="font-semibold text-gray-900">Nouvelle demande</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Renseignez la quantité souhaitée et un commentaire si besoin.</p>
                </div>
                <form class="p-6 space-y-5" @submit.prevent="submit">
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div class="space-y-2">
                            <Label for="quantite" class="text-xs font-medium text-gray-600">Quantité souhaitée</Label>
                            <Input
                                id="quantite"
                                v-model.number="form.quantite_demandee"
                                type="number"
                                min="1"
                                class="border-gray-300 shadow-sm"
                                placeholder="Ex. 10"
                            />
                            <InputError :message="form.errors.quantite_demandee" />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="com" class="text-xs font-medium text-gray-600">Commentaire</Label>
                        <textarea
                            id="com"
                            v-model="form.commentaire"
                            rows="3"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500"
                            placeholder="Contexte, urgence, point de contact…"
                        />
                        <InputError :message="form.errors.commentaire" />
                    </div>
                    <Button type="submit" class="bg-violet-600 hover:bg-violet-700" :disabled="form.processing">
                        Envoyer la demande
                    </Button>
                </form>
            </div>

            <section class="space-y-4">
                <div class="flex items-center gap-2">
                    <Building2 class="h-5 w-5 text-gray-600" />
                    <h2 class="text-lg font-semibold text-gray-900">Historique des demandes</h2>
                </div>

                <div
                    v-if="!demandes.length"
                    class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/80 px-6 py-14 text-center"
                >
                    <Inbox class="h-11 w-11 text-gray-300 mx-auto mb-3" />
                    <p class="text-sm text-gray-600">Aucune demande encore enregistrée.</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="d in demandes"
                        :key="d.id"
                        class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden hover:shadow-md transition-shadow"
                    >
                        <div class="px-5 py-4 flex flex-wrap items-start justify-between gap-3 border-b border-gray-50 bg-gray-50/50">
                            <div>
                                <p class="font-semibold text-gray-900">
                                    Demande #{{ d.id }}
                                    <span class="font-normal text-gray-500">—</span>
                                    <span class="tabular-nums">{{ d.quantite_demandee }} carte(s) demandée(s)</span>
                                </p>
                                <p class="text-xs text-gray-600 mt-1 tabular-nums">
                                    Livré (réceptions validées) : {{ d.quantite_livree }} / {{ d.quantite_demandee }}
                                    <span v-if="d.cloture_partielle" class="text-indigo-700 font-medium">
                                        — clôturée avec reliquat
                                    </span>
                                </p>
                                <p v-if="d.traite_le" class="text-xs text-gray-500 mt-1">Traitée le {{ d.traite_le }}</p>
                            </div>
                            <span
                                :class="['inline-flex items-center rounded-md border px-2.5 py-1 text-xs font-semibold', badgeStatut(d.status)]"
                            >
                                {{
                                    d.status === 'acceptee' && d.cloture_partielle
                                        ? 'Clôturée — reliquat'
                                        : libelleStatut(d.status)
                                }}
                            </span>
                        </div>
                        <div class="px-5 py-4 text-sm space-y-3">
                            <p v-if="d.commentaire" class="text-gray-700 leading-relaxed">{{ d.commentaire }}</p>
                            <p
                                v-if="d.bon_numero"
                                class="inline-flex items-center gap-2 text-violet-800 font-mono text-xs font-medium bg-violet-50 border border-violet-100 rounded-lg px-3 py-2 w-fit"
                            >
                                <FileText class="h-3.5 w-3.5" />
                                Bon {{ d.bon_numero }}
                            </p>
                            <div
                                v-if="d.reponse_monetique"
                                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950"
                            >
                                <span class="font-semibold">Réponse monétique :</span>
                                {{ d.reponse_monetique }}
                            </div>
                            <Button
                                v-if="d.status === 'en_attente'"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="border-rose-200 text-rose-800 hover:bg-rose-50"
                                @click="annuler(d.id)"
                            >
                                <XCircle class="h-4 w-4 mr-1.5" />
                                Annuler la demande
                            </Button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
