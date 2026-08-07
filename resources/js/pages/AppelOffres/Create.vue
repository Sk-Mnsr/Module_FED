<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { computed } from 'vue';
import {
    ClipboardList,
    FileText,
    ListChecks,
    Paperclip,
    Plus,
    Send,
    Users,
    Minus,
} from 'lucide-vue-next';

const props = defineProps<{
    fournisseurs: Array<{ id: number; nom: string; contact_email?: string | null }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: "Appels d'Offres", href: '/appel-offres' },
    { title: 'Nouveau', href: '#' },
];

interface CritereForm {
    nom: string;
    ponderation: number;
    type: string;
    note_maximale: number;
}

const makeCritere = (): CritereForm => ({
    nom: '',
    ponderation: 1,
    type: 'technique',
    note_maximale: 100,
});

const form = useForm({
    objet: '',
    description: '',
    date_lancement: '',
    date_limite_soumission: '',
    type_publication: 'interne',
    criteres: [
        { nom: 'Conformité technique', ponderation: 30, type: 'technique', note_maximale: 10 },
        { nom: 'Expérience / Références', ponderation: 20, type: 'experience', note_maximale: 10 },
        { nom: 'Délai de livraison', ponderation: 20, type: 'delais', note_maximale: 10 },
        { nom: 'Offre financière', ponderation: 20, type: 'financier', note_maximale: 10 },
        { nom: 'Documents administratifs', ponderation: 10, type: 'docs', note_maximale: 10 },
    ],
    fournisseurs: [] as number[],
    dao_file: null as File | null,
    cahier_charges_file: null as File | null,
});

const onDaoChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files?.length) form.dao_file = target.files[0];
};

const onCcChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files?.length) form.cahier_charges_file = target.files[0];
};

const typeOptions = [
    { value: 'technique', label: 'Technique' },
    { value: 'financier', label: 'Financier' },
    { value: 'delais', label: 'Délais' },
    { value: 'experience', label: 'Expérience' },
    { value: 'docs', label: 'Documents administratifs' },
];

const publicationOptions = [
    { value: 'interne', label: 'Interne (Utilisateurs du système)' },
    { value: 'externe', label: 'Externe (Lien public)' },
];

const publicationLabel = computed(
    () => publicationOptions.find((o) => o.value === form.type_publication)?.label ?? '—',
);

const ponderationTotal = computed(() =>
    form.criteres.reduce((sum, c) => sum + (Number(c.ponderation) || 0), 0),
);

const addCritere = () => {
    form.criteres.push(makeCritere());
};

const removeCritere = (index: number) => {
    form.criteres.splice(index, 1);
};

const submit = () => {
    if (form.criteres.length === 0) {
        alert("Veuillez ajouter au moins un critère d'évaluation.");
        return;
    }

    form.post('/appel-offres', {
        preserveScroll: true,
    });
};

const fieldClass =
    'mt-1.5 h-10 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';
const selectClass =
    'mt-1.5 flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';
const textareaClass =
    'mt-1.5 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';
</script>

<template>
    <Head title="Nouvel Appel d'Offres" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 flex-1 flex-col gap-4 p-4 lg:p-6">
            <div
                class="flex flex-wrap items-end justify-between gap-3 rounded-2xl border border-border/80 bg-card px-5 py-4 shadow-sm sm:px-6"
            >
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">TDR</p>
                    <h1 class="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                        Nouvel Appel d'Offres
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Renseignez l’objet, les critères, les documents et les fournisseurs concernés.
                    </p>
                </div>
            </div>

            <form
                class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(300px,0.7fr)]"
                @submit.prevent="submit"
            >
                <div class="flex min-h-0 flex-col gap-4 overflow-y-auto">
                    <!-- Infos générales -->
                    <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                        <div
                            class="flex items-center gap-3 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-4 py-3 sm:px-5"
                        >
                            <div class="rounded-lg bg-primary p-2 text-primary-foreground shadow-sm">
                                <ClipboardList class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-foreground">Informations générales</h2>
                                <p class="text-sm text-muted-foreground">Objet, dates et mode de publication</p>
                            </div>
                        </div>

                        <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <Label for="objet">Objet de l'appel d'offres</Label>
                                <Input
                                    id="objet"
                                    v-model="form.objet"
                                    type="text"
                                    :class="fieldClass"
                                    required
                                />
                                <InputError :message="form.errors.objet" />
                            </div>
                            <div class="md:col-span-2">
                                <Label for="description">Description détaillée</Label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="4"
                                    :class="textareaClass"
                                    required
                                />
                                <InputError :message="form.errors.description" />
                            </div>
                            <div>
                                <Label for="date_lancement">Date de lancement</Label>
                                <Input
                                    id="date_lancement"
                                    v-model="form.date_lancement"
                                    type="date"
                                    :class="fieldClass"
                                />
                                <InputError :message="form.errors.date_lancement" />
                            </div>
                            <div>
                                <Label for="date_limite_soumission">Date limite de soumission</Label>
                                <Input
                                    id="date_limite_soumission"
                                    v-model="form.date_limite_soumission"
                                    type="datetime-local"
                                    :class="fieldClass"
                                    required
                                />
                                <InputError :message="form.errors.date_limite_soumission" />
                            </div>
                            <div class="md:col-span-2">
                                <Label for="type_publication">Type de publication</Label>
                                <select
                                    id="type_publication"
                                    v-model="form.type_publication"
                                    :class="selectClass"
                                    required
                                >
                                    <option
                                        v-for="option in publicationOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.type_publication" />
                            </div>
                        </div>
                    </section>

                    <!-- Critères -->
                    <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-4 py-3 sm:px-5"
                        >
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg bg-primary p-2 text-primary-foreground shadow-sm">
                                    <ListChecks class="size-5" />
                                </div>
                                <div>
                                    <h2 class="text-base font-semibold text-foreground">Critères d'évaluation</h2>
                                    <p class="text-sm text-muted-foreground">
                                        {{ form.criteres.length }} critère{{ form.criteres.length > 1 ? 's' : '' }}
                                        · pondération {{ ponderationTotal }}
                                    </p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="border-primary/30 text-primary hover:bg-primary/5 hover:text-primary"
                                @click="addCritere"
                            >
                                <Plus class="mr-1.5 size-4" />
                                Ajouter
                            </Button>
                        </div>

                        <div class="space-y-3 p-4 sm:p-5">
                            <div
                                v-for="(critere, index) in form.criteres"
                                :key="index"
                                class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-border dark:bg-muted/20"
                            >
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-foreground">
                                        <span
                                            class="mr-2 inline-flex size-6 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-primary-foreground"
                                        >
                                            {{ index + 1 }}
                                        </span>
                                        Critère {{ index + 1 }}
                                    </p>
                                    <Button
                                        v-if="form.criteres.length > 1"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 text-red-600 hover:bg-red-50 hover:text-red-700"
                                        @click="removeCritere(index)"
                                    >
                                        <Minus class="mr-1 size-4" />
                                        Retirer
                                    </Button>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <Label :for="`nom-${index}`">Nom du critère</Label>
                                        <Input
                                            :id="`nom-${index}`"
                                            v-model="critere.nom"
                                            type="text"
                                            :class="fieldClass"
                                            required
                                        />
                                        <InputError
                                            :message="
                                                form.errors[`criteres.${index}.nom` as keyof typeof form.errors]
                                            "
                                        />
                                    </div>
                                    <div>
                                        <Label :for="`type-${index}`">Type</Label>
                                        <select
                                            :id="`type-${index}`"
                                            v-model="critere.type"
                                            :class="selectClass"
                                            required
                                        >
                                            <option
                                                v-for="opt in typeOptions"
                                                :key="opt.value"
                                                :value="opt.value"
                                            >
                                                {{ opt.label }}
                                            </option>
                                        </select>
                                        <InputError
                                            :message="
                                                form.errors[`criteres.${index}.type` as keyof typeof form.errors]
                                            "
                                        />
                                    </div>
                                    <div>
                                        <Label :for="`max-${index}`">Note maximale</Label>
                                        <!-- @ts-ignore -->
                                        <Input
                                            :id="`max-${index}`"
                                            v-model.number="critere.note_maximale"
                                            type="number"
                                            min="1"
                                            step="0.5"
                                            :class="fieldClass"
                                            required
                                        />
                                        <InputError
                                            :message="
                                                form.errors[
                                                    `criteres.${index}.note_maximale` as keyof typeof form.errors
                                                ]
                                            "
                                        />
                                    </div>
                                    <div class="md:col-span-2">
                                        <Label :for="`pond-${index}`">Coefficient / Pondération</Label>
                                        <!-- @ts-ignore -->
                                        <Input
                                            :id="`pond-${index}`"
                                            v-model.number="critere.ponderation"
                                            type="number"
                                            min="1"
                                            step="1"
                                            :class="fieldClass"
                                            required
                                        />
                                        <InputError
                                            :message="
                                                form.errors[
                                                    `criteres.${index}.ponderation` as keyof typeof form.errors
                                                ]
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Documents -->
                    <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                        <div
                            class="flex items-center gap-3 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-4 py-3 sm:px-5"
                        >
                            <div class="rounded-lg bg-primary p-2 text-primary-foreground shadow-sm">
                                <Paperclip class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-foreground">Documents</h2>
                                <p class="text-sm text-muted-foreground">DAO et cahier des charges</p>
                            </div>
                        </div>
                        <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-2">
                            <div>
                                <Label for="dao_file">Dossier d'Appel d'Offres (DAO)</Label>
                                <Input id="dao_file" type="file" :class="fieldClass" @change="onDaoChange" />
                                <p v-if="form.dao_file" class="mt-1 truncate text-xs text-muted-foreground">
                                    {{ form.dao_file.name }}
                                </p>
                                <InputError :message="form.errors.dao_file" />
                            </div>
                            <div>
                                <Label for="cahier_charges_file">Cahier des charges</Label>
                                <Input
                                    id="cahier_charges_file"
                                    type="file"
                                    :class="fieldClass"
                                    @change="onCcChange"
                                />
                                <p
                                    v-if="form.cahier_charges_file"
                                    class="mt-1 truncate text-xs text-muted-foreground"
                                >
                                    {{ form.cahier_charges_file.name }}
                                </p>
                                <InputError :message="form.errors.cahier_charges_file" />
                            </div>
                        </div>
                    </section>

                    <!-- Fournisseurs -->
                    <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                        <div
                            class="flex items-center gap-3 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-4 py-3 sm:px-5"
                        >
                            <div class="rounded-lg bg-primary p-2 text-primary-foreground shadow-sm">
                                <Users class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-foreground">Fournisseurs concernés</h2>
                                <p class="text-sm text-muted-foreground">
                                    {{ form.fournisseurs.length }} sélectionné{{
                                        form.fournisseurs.length > 1 ? 's' : ''
                                    }}
                                </p>
                            </div>
                        </div>
                        <div class="p-4 sm:p-5">
                            <div
                                class="grid max-h-60 grid-cols-1 gap-1 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 md:grid-cols-2 dark:border-border dark:bg-background"
                            >
                                <label
                                    v-for="fournisseur in props.fournisseurs"
                                    :key="fournisseur.id"
                                    class="flex cursor-pointer items-start gap-2 rounded-md p-2 hover:bg-primary/5"
                                >
                                    <input
                                        v-model="form.fournisseurs"
                                        type="checkbox"
                                        :value="fournisseur.id"
                                        class="mt-0.5 rounded border-slate-300 text-primary focus:ring-primary/30"
                                    />
                                    <span class="min-w-0 text-sm text-foreground">
                                        {{ fournisseur.nom }}
                                        <span
                                            v-if="fournisseur.contact_email"
                                            class="block truncate text-xs text-muted-foreground"
                                        >
                                            {{ fournisseur.contact_email }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <InputError :message="form.errors.fournisseurs" />
                        </div>
                    </section>
                </div>

                <!-- Récap -->
                <aside class="xl:sticky xl:top-4 xl:self-start">
                    <div class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                        <div
                            class="flex items-start gap-3 border-b border-border/80 bg-gradient-to-r from-primary/5 via-transparent to-transparent px-4 py-4 lg:px-5"
                        >
                            <div class="rounded-lg bg-primary p-2 text-primary-foreground shadow-sm">
                                <FileText class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                                    Récapitulatif
                                </p>
                                <h2 class="text-base font-semibold text-foreground">Avant création</h2>
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 px-4 py-4 lg:px-5 lg:pb-5">
                            <dl class="space-y-3 text-sm">
                                <div>
                                    <dt class="text-muted-foreground">Objet</dt>
                                    <dd class="mt-0.5 line-clamp-2 font-medium text-foreground">
                                        {{ form.objet || '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Publication</dt>
                                    <dd class="mt-0.5 font-medium text-foreground">{{ publicationLabel }}</dd>
                                </div>
                                <div class="grid grid-cols-2 gap-3 border-t border-border pt-3">
                                    <div>
                                        <dt class="text-muted-foreground">Critères</dt>
                                        <dd class="font-medium tabular-nums text-foreground">
                                            {{ form.criteres.length }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">Pondération</dt>
                                        <dd class="font-medium tabular-nums text-foreground">
                                            {{ ponderationTotal }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">Fournisseurs</dt>
                                        <dd class="font-medium tabular-nums text-foreground">
                                            {{ form.fournisseurs.length }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted-foreground">Documents</dt>
                                        <dd class="font-medium tabular-nums text-foreground">
                                            {{ (form.dao_file ? 1 : 0) + (form.cahier_charges_file ? 1 : 0) }}
                                        </dd>
                                    </div>
                                </div>
                                <div v-if="form.date_limite_soumission" class="border-t border-border pt-3">
                                    <dt class="text-muted-foreground">Date limite</dt>
                                    <dd class="mt-0.5 font-medium text-foreground">
                                        {{
                                            new Date(form.date_limite_soumission).toLocaleString('fr-FR', {
                                                day: '2-digit',
                                                month: '2-digit',
                                                year: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })
                                        }}
                                    </dd>
                                </div>
                            </dl>

                            <div class="flex flex-col gap-2 border-t border-border pt-4">
                                <Button type="submit" class="w-full" :disabled="form.processing">
                                    <Send class="mr-2 size-4" />
                                    {{ form.processing ? 'Création…' : "Créer l'Appel d'Offres" }}
                                </Button>
                                <Button
                                    as-child
                                    type="button"
                                    variant="outline"
                                    class="w-full border-slate-300"
                                >
                                    <Link href="/appel-offres">Annuler</Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </AppLayout>
</template>
