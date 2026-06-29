<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import {
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    Download,
    Eye,
    Eraser,
    FileSpreadsheet,
    FileText,
    Hash,
    ListOrdered,
    Plus,
    Save,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { odFileTooLarge } from '@/lib/odUpload';

type OdLigne = {
    date_de_valeur: string;
    code_agence: string;
    no_compte: string;
    related_account: string;
    montant: string;
    sens: string;
    libelle_ecriture: string;
    code_operation: string;
};

type JustificatifLigne = {
    description: string;
    file: File | null;
};

type Agence = { id: number; code: string; nom: string };

type EditPiece = {
    id: number | string;
    description: string | null;
    original_name: string;
    url: string;
    preview_url?: string | null;
    is_piece_comptable?: boolean;
};

type EditClasseur = {
    id: number;
    numero_batch: string;
    nom_classeur: string;
    lignes: OdLigne[];
    resume_url: string;
    pieces: EditPiece[];
};

const props = defineProps<{
    agences?: Agence[];
    codesOperation?: string[];
    comptableImportApiConfigured?: boolean;
    maxUploadMo?: number;
    editing?: boolean;
    classeur?: EditClasseur;
}>();

const breadcrumbs = [
    { title: 'Opérations diverses', href: '/operations-diverses/piece-comptable' },
    { title: 'Intégration', href: '/operations-diverses/integrations' },
    { title: 'Manuelle', href: '/operations-diverses/piece-comptable/manuelle' },
];

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string; warning?: string } | undefined);
const flashSuccess = computed(() => flash.value?.success);
const flashWarning = computed(() => flash.value?.warning);
const justificatifListKey = ref(0);
const maxUploadMo = computed(() => props.maxUploadMo ?? 25);

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-background px-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-violet-400/40';

const ligneVide = (): OdLigne => ({
    date_de_valeur: new Date().toISOString().slice(0, 10),
    code_agence: '',
    no_compte: '',
    related_account: '',
    montant: '',
    sens: '',
    libelle_ecriture: '',
    code_operation: '',
});

const form = useForm<{
    numero_batch: string;
    nom_classeur: string;
    lignes: OdLigne[];
    justificatifs: JustificatifLigne[];
}>({
    numero_batch: props.classeur?.numero_batch ?? '',
    nom_classeur: props.classeur?.nom_classeur ?? '',
    lignes: props.classeur?.lignes?.length ? props.classeur.lignes : [ligneVide()],
    justificatifs: props.editing ? [] : [{ description: 'Email', file: null }],
});

function ajouterLigne() {
    form.lignes.push(ligneVide());
}

function retirerLigne(index: number) {
    if (form.lignes.length <= 1) return;
    form.lignes.splice(index, 1);
}

function monterLigne(index: number) {
    if (index <= 0) return;
    const tmp = form.lignes[index - 1];
    form.lignes[index - 1] = form.lignes[index];
    form.lignes[index] = tmp;
}

function descendreLigne(index: number) {
    if (index >= form.lignes.length - 1) return;
    const tmp = form.lignes[index + 1];
    form.lignes[index + 1] = form.lignes[index];
    form.lignes[index] = tmp;
}

function onJustificatifFile(index: number, e: Event) {
    const t = e.target as HTMLInputElement;
    const f = t.files?.[0] ?? null;
    const tooLarge = odFileTooLarge(f, maxUploadMo.value);
    if (tooLarge) {
        form.setError(`justificatifs.${index}.file`, tooLarge);
        form.justificatifs[index].file = null;
        t.value = '';
        return;
    }
    form.clearErrors(`justificatifs.${index}.file`);
    form.justificatifs[index].file = f;
}

function ajouterJustificatif() {
    form.justificatifs.push({ description: '', file: null });
}

function retirerJustificatif(index: number) {
    if (!props.editing && form.justificatifs.length <= 1) return;
    form.justificatifs.splice(index, 1);
}

function effacerTout() {
    form.reset();
    form.lignes = [ligneVide()];
    form.justificatifs = [{ description: 'Email', file: null }];
    justificatifListKey.value += 1;
}

function submit() {
    if (props.editing && props.classeur) {
        const hasNewJustificatifs = form.justificatifs.some((j) => j.file !== null);
        form
            .transform((data) => ({
                ...data,
                justificatifs: data.justificatifs.filter((j) => j.file !== null),
            }))
            .put(`/operations-diverses/piece-comptable/${props.classeur.id}/manuelle`, {
                forceFormData: hasNewJustificatifs,
                preserveScroll: true,
            });
        return;
    }

    form.post('/operations-diverses/piece-comptable/manuelle', {
        forceFormData: true,
        preserveScroll: true,
    });
}

function ligneError(index: number, field: string): string | undefined {
    return (form.errors as Record<string, string>)[`lignes.${index}.${field}`]
        ?? (form.errors as Record<string, string>).lignes;
}
</script>

<template>
    <Head title="Intégration manuelle — OD" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div v-if="flashSuccess" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ flashSuccess }}
            </div>
            <div v-if="flashWarning" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                {{ flashWarning }}
            </div>

            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-muted p-2 text-muted-foreground">
                    <FileSpreadsheet class="size-6" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        {{ editing ? 'Modifier l’intégration manuelle' : 'Intégration manuelle' }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <template v-if="editing">
                            Corrigez les lignes OD. Les pièces existantes sont conservées et vous pouvez en ajouter d’autres.
                        </template>
                        <template v-else>
                            Saisissez les lignes OD une à une, puis joignez les pièces justificatives.
                        </template>
                    </p>
                </div>
            </div>

            <Link
                v-if="editing && classeur?.resume_url"
                :href="classeur.resume_url"
                class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> Retour au résumé
            </Link>

            <form id="form-integration-manuelle-od" class="space-y-8" @submit.prevent="submit">
                <!-- Données d'intégration -->
                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div class="border-b border-border bg-gradient-to-r from-violet-50/90 to-transparent px-5 py-3 dark:from-violet-950/40 dark:to-transparent">
                        <h2 class="text-sm font-semibold text-foreground">Données d'intégration</h2>
                    </div>

                    <div class="space-y-5 p-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-violet-200/80 bg-violet-50/50 p-4 shadow-xs dark:border-violet-900/60 dark:bg-violet-950/20">
                                <div class="flex items-center gap-2">
                                    <div class="rounded-lg bg-violet-600 p-1.5 text-white">
                                        <Hash class="size-4" />
                                    </div>
                                    <Label for="numero_batch" class="text-xs font-semibold uppercase tracking-wide text-violet-800 dark:text-violet-300">
                                        Numéro batch <span class="text-red-600">*</span>
                                    </Label>
                                </div>
                                <Input
                                    id="numero_batch"
                                    v-model="form.numero_batch"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="xxxx"
                                    class="mt-3 h-11 border-violet-200 bg-white text-base font-semibold tracking-wide focus-visible:ring-violet-400/40 dark:border-violet-900 dark:bg-card"
                                />
                                <InputError :message="form.errors.numero_batch" />
                            </div>

                            <div class="rounded-xl border border-violet-200/80 bg-violet-50/50 p-4 shadow-xs dark:border-violet-900/60 dark:bg-violet-950/20">
                                <div class="flex items-center gap-2">
                                    <div class="rounded-lg bg-violet-600 p-1.5 text-white">
                                        <ListOrdered class="size-4" />
                                    </div>
                                    <Label for="nom_classeur" class="text-xs font-semibold uppercase tracking-wide text-violet-800 dark:text-violet-300">
                                        Nom du classeur <span class="text-red-600">*</span>
                                    </Label>
                                </div>
                                <Input
                                    id="nom_classeur"
                                    v-model="form.nom_classeur"
                                    type="text"
                                    placeholder="Libellé du classeur"
                                    class="mt-3 h-11 border-violet-200 bg-white text-base font-semibold focus-visible:ring-violet-400/40 dark:border-violet-900 dark:bg-card"
                                />
                                <InputError :message="form.errors.nom_classeur" />
                            </div>
                        </div>

                        <p
                            v-if="comptableImportApiConfigured === false"
                            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
                        >
                            Non intégré : les lignes seront enregistrées en brouillon mais non transmises à la plateforme.
                        </p>

                        <!-- Lignes OD -->
                        <div class="overflow-hidden rounded-lg border border-border">
                            <div class="border-b border-border bg-muted/70 px-4 py-3 dark:bg-muted/40">
                                <h3 class="text-sm font-semibold text-foreground">Lignes des OD</h3>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Renseignez chaque écriture. Utilisez
                                    <span class="font-medium text-foreground">+ Ajouter</span> pour plusieurs lignes.
                                </p>
                            </div>

                            <div class="space-y-4 bg-muted/10 p-4 dark:bg-muted/5">
                                <div
                                    v-for="(ligne, index) in form.lignes"
                                    :key="index"
                                    class="relative rounded-lg border border-border bg-background p-4 pr-14 shadow-xs dark:bg-card/50"
                                >
                                    <div class="absolute right-2 top-2 flex flex-col gap-0.5">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-muted-foreground"
                                            :disabled="index === 0"
                                            @click="monterLigne(index)"
                                        >
                                            <ChevronUp class="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-muted-foreground"
                                            :disabled="index === form.lignes.length - 1"
                                            @click="descendreLigne(index)"
                                        >
                                            <ChevronDown class="size-4" />
                                        </Button>
                                        <Button
                                            v-if="form.lignes.length > 1"
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-muted-foreground hover:text-destructive"
                                            @click="retirerLigne(index)"
                                        >
                                            <X class="size-4" />
                                        </Button>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                                        <div class="space-y-1.5">
                                            <Label class="text-xs font-medium text-muted-foreground">Date <span class="text-red-600">*</span></Label>
                                            <Input v-model="ligne.date_de_valeur" type="date" class="h-9" />
                                            <InputError :message="ligneError(index, 'date_de_valeur')" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label class="text-xs font-medium text-muted-foreground">Agence <span class="text-red-600">*</span></Label>
                                            <select v-model="ligne.code_agence" :class="selectClass">
                                                <option value="">Agence</option>
                                                <option v-for="a in agences" :key="a.id" :value="a.code">
                                                    {{ a.code }} — {{ a.nom }}
                                                </option>
                                            </select>
                                            <InputError :message="ligneError(index, 'code_agence')" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label class="text-xs font-medium text-muted-foreground">N° CPT <span class="text-red-600">*</span></Label>
                                            <Input v-model="ligne.no_compte" placeholder="N° CPT" class="h-9" />
                                            <InputError :message="ligneError(index, 'no_compte')" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label class="text-xs font-medium text-muted-foreground">Related Account</Label>
                                            <Input v-model="ligne.related_account" placeholder="Related Account" class="h-9" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label class="text-xs font-medium text-muted-foreground">Montant <span class="text-red-600">*</span></Label>
                                            <Input v-model="ligne.montant" type="number" min="0" step="0.01" placeholder="Montant" class="h-9" />
                                            <InputError :message="ligneError(index, 'montant')" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label class="text-xs font-medium text-muted-foreground">Sens <span class="text-red-600">*</span></Label>
                                            <select v-model="ligne.sens" :class="selectClass">
                                                <option value="">Sens</option>
                                                <option value="D">D — Débit</option>
                                                <option value="C">C — Crédit</option>
                                            </select>
                                            <InputError :message="ligneError(index, 'sens')" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label class="text-xs font-medium text-muted-foreground">Libellé <span class="text-red-600">*</span></Label>
                                            <Input v-model="ligne.libelle_ecriture" placeholder="Libellé" class="h-9" />
                                            <InputError :message="ligneError(index, 'libelle_ecriture')" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label class="text-xs font-medium text-muted-foreground">Code <span class="text-red-600">*</span></Label>
                                            <Input
                                                v-model="ligne.code_operation"
                                                list="codes-operation"
                                                placeholder="Code"
                                                class="h-9"
                                            />
                                            <datalist id="codes-operation">
                                                <option v-for="code in codesOperation" :key="code" :value="code" />
                                            </datalist>
                                            <InputError :message="ligneError(index, 'code_operation')" />
                                        </div>
                                    </div>
                                </div>

                                <Button
                                    type="button"
                                    class="bg-violet-600 text-white hover:bg-violet-700 dark:bg-violet-600 dark:hover:bg-violet-500"
                                    @click="ajouterLigne"
                                >
                                    <Plus class="size-4" />
                                    Ajouter
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pièces justificatives -->
                <div class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                    <div class="border-b border-border bg-muted/70 px-4 py-3 dark:bg-muted/40">
                        <h2 class="text-sm font-semibold text-foreground">Pièces justificatives</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            <template v-if="editing">
                                Documents déjà joints. Utilisez
                                <span class="font-medium text-foreground">+ Ajouter</span> pour joindre d’autres pièces.
                            </template>
                            <template v-else>
                                Renseignez le libellé affiché et joignez le document. Utilisez
                                <span class="font-medium text-foreground">+ Ajouter</span> pour plusieurs pièces.
                            </template>
                        </p>
                    </div>

                    <ul v-if="editing && classeur?.pieces?.length" class="divide-y divide-border border-b border-border">
                        <li v-for="p in classeur.pieces" :key="p.id" class="flex items-center justify-between px-4 py-3">
                            <div class="flex items-center gap-2 text-sm">
                                <FileText class="size-4" :class="p.is_piece_comptable ? 'text-violet-600 dark:text-violet-400' : 'text-muted-foreground'" />
                                <span class="font-medium text-foreground">{{ p.description || p.original_name }}</span>
                                <span class="text-xs text-muted-foreground">({{ p.original_name }})</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <a
                                    v-if="p.preview_url"
                                    :href="p.preview_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-violet-700 hover:underline dark:text-violet-300"
                                    title="Visualiser"
                                >
                                    <Eye class="size-3.5" /> Voir
                                </a>
                                <a :href="p.url" class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">
                                    <Download class="size-3.5" /> Télécharger
                                </a>
                            </div>
                        </li>
                    </ul>

                    <div class="space-y-4 p-4" :class="{ 'border-t border-border': editing && classeur?.pieces?.length }">
                        <p v-if="editing" class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            {{ form.justificatifs.length ? 'Nouvelles pièces' : 'Ajouter des pièces supplémentaires' }}
                        </p>
                        <div
                            v-for="(ligne, index) in form.justificatifs"
                            :key="`${justificatifListKey}-${index}`"
                            class="relative rounded-lg border border-border bg-background p-4 pr-14 shadow-xs dark:bg-card/50"
                        >
                            <Button
                                v-if="editing || form.justificatifs.length > 1"
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="absolute right-2 top-2 size-8 text-muted-foreground hover:text-destructive"
                                :aria-label="'Supprimer la pièce ' + (index + 1)"
                                @click="retirerJustificatif(index)"
                            >
                                <Trash2 class="size-4" />
                            </Button>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label :for="'pj-desc-' + index">Texte à afficher <span class="text-red-600">*</span></Label>
                                    <Input
                                        :id="'pj-desc-' + index"
                                        v-model="ligne.description"
                                        type="text"
                                        placeholder="Texte à afficher"
                                    />
                                    <InputError :message="form.errors[`justificatifs.${index}.description`]" />
                                </div>

                                <div class="space-y-2">
                                    <Label :for="'pj-file-' + index">Joindre la pièce <span class="text-red-600">*</span></Label>
                                    <Input
                                        :id="'pj-file-' + index"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.csv,.txt,.xlsx,.xls,.doc,.docx,.eml"
                                        class="cursor-pointer file:mr-3 file:rounded-md file:border-0 file:bg-violet-100 file:px-3 file:py-1 file:text-sm file:font-medium file:text-violet-800 hover:file:bg-violet-200 dark:file:bg-violet-950 dark:file:text-violet-200"
                                        @change="onJustificatifFile(index, $event)"
                                    />
                                    <InputError
                                        :message="(form.errors as Record<string, string>)[`justificatifs.${index}.file`]"
                                    />
                                </div>
                            </div>
                        </div>

                        <Button
                            type="button"
                            class="bg-violet-600 text-white hover:bg-violet-700 dark:bg-violet-600 dark:hover:bg-violet-500"
                            @click="ajouterJustificatif"
                        >
                            <Plus class="size-4" />
                            {{ editing ? 'Ajouter une pièce' : 'Ajouter' }}
                        </Button>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border bg-muted/40 px-4 py-3 dark:bg-muted/25">
                        <Button
                            v-if="!editing"
                            type="button"
                            variant="outline"
                            class="border-violet-200 bg-background text-violet-800 hover:bg-violet-50 dark:border-violet-900 dark:text-violet-200 dark:hover:bg-violet-950/50"
                            @click="effacerTout"
                        >
                            <Eraser class="size-4" />
                            Effacer
                        </Button>
                        <Button
                            v-if="!editing"
                            type="submit"
                            class="bg-violet-700 text-white hover:bg-violet-800 dark:bg-violet-600 dark:hover:bg-violet-500"
                            :disabled="form.processing"
                        >
                            <Save class="size-4" />
                            {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                        </Button>
                    </div>
                </div>

                <div
                    v-if="editing"
                    class="flex flex-wrap items-center justify-end gap-2 rounded-lg border border-border bg-card px-4 py-3"
                >
                    <Button
                        type="submit"
                        class="bg-violet-700 text-white hover:bg-violet-800 dark:bg-violet-600 dark:hover:bg-violet-500"
                        :disabled="form.processing"
                    >
                        <Save class="size-4" />
                        {{ form.processing ? 'Enregistrement…' : 'Enregistrer les modifications' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
