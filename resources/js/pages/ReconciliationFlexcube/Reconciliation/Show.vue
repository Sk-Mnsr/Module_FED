<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    FileUp,
    ImageIcon,
    Play,
    RefreshCw,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Partenaire = {
    id: number;
    identifiant: string;
    nom: string;
    icone_url: string | null;
};

type UploadedFile = {
    id: string;
    file: File;
    name: string;
    size: number;
};

const props = defineProps<{
    partenaire: Partenaire;
}>();

const breadcrumbs = [
    { title: 'Reconciliation Flexcube', href: '/reconciliation-flexcube' },
    { title: 'Reconciliation', href: '/reconciliation-flexcube/reconciliation' },
    { title: props.partenaire.nom, href: `/reconciliation-flexcube/reconciliation/${props.partenaire.id}` },
];

const dateDebut = ref('');
const dateFin = ref('');
const files = ref<UploadedFile[]>([]);
const fileInputKey = ref(0);
const busy = ref(false);
const filesLoaded = ref(false);
const message = ref<{ type: 'info' | 'success' | 'error'; text: string } | null>(null);

const canLaunch = computed(
    () => filesLoaded.value && dateDebut.value !== '' && dateFin.value !== '' && !busy.value,
);

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}

function onFilesSelected(e: Event) {
    const input = e.target as HTMLInputElement;
    const selected = Array.from(input.files ?? []);
    for (const file of selected) {
        files.value.push({
            id: `${file.name}-${file.size}-${file.lastModified}-${Math.random().toString(36).slice(2)}`,
            file,
            name: file.name,
            size: file.size,
        });
    }
    filesLoaded.value = false;
    fileInputKey.value += 1;
}

function removeFile(id: string) {
    files.value = files.value.filter((f) => f.id !== id);
    filesLoaded.value = false;
}

function reinitialiser() {
    dateDebut.value = '';
    dateFin.value = '';
    files.value = [];
    filesLoaded.value = false;
    fileInputKey.value += 1;
    message.value = null;
}

/** Front only — le backend Python sera branché via API plus tard. */
async function charger() {
    if (files.value.length === 0) {
        message.value = { type: 'error', text: 'Ajoutez au moins un fichier partenaire à charger.' };
        return;
    }
    busy.value = true;
    message.value = { type: 'info', text: 'Chargement des fichiers… (API Python à brancher)' };
    await new Promise((r) => setTimeout(r, 400));
    filesLoaded.value = true;
    busy.value = false;
    message.value = {
        type: 'success',
        text: `${files.value.length} fichier(s) chargé(s) pour « ${props.partenaire.nom} ». Vous pouvez lancer la réconciliation.`,
    };
}

async function lancer() {
    if (!filesLoaded.value) {
        message.value = { type: 'error', text: 'Chargez d’abord les fichiers avant de lancer la réconciliation.' };
        return;
    }
    if (!dateDebut.value || !dateFin.value) {
        message.value = { type: 'error', text: 'Renseignez la date début et la date fin Flexcube.' };
        return;
    }
    if (dateFin.value < dateDebut.value) {
        message.value = { type: 'error', text: 'La date fin doit être postérieure à la date début.' };
        return;
    }

    busy.value = true;
    message.value = { type: 'info', text: 'Lancement de la réconciliation… (API Python à brancher)' };
    await new Promise((r) => setTimeout(r, 500));
    busy.value = false;
    message.value = {
        type: 'success',
        text: `Réconciliation « ${props.partenaire.nom} » — période ${dateDebut.value} → ${dateFin.value} : appel API à connecter.`,
    };
}
</script>

<template>
    <Head :title="`Reconciliation — ${partenaire.nom}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-white">
                        <img
                            v-if="partenaire.icone_url"
                            :src="partenaire.icone_url"
                            :alt="partenaire.nom"
                            class="size-full object-contain p-1"
                        />
                        <ImageIcon v-else class="size-5 text-muted-foreground" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">{{ partenaire.nom }}</h1>
                        <p class="mt-1 font-mono text-sm text-muted-foreground">{{ partenaire.identifiant }}</p>
                    </div>
                </div>

                <Link
                    href="/reconciliation-flexcube/reconciliation"
                    class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> Changer de partenaire
                </Link>
            </div>

            <div
                v-if="message"
                class="rounded-lg border px-4 py-3 text-sm"
                :class="{
                    'border-blue-200 bg-blue-50 text-blue-900': message.type === 'info',
                    'border-green-200 bg-green-50 text-green-800': message.type === 'success',
                    'border-red-200 bg-red-50 text-red-800': message.type === 'error',
                }"
            >
                {{ message.text }}
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Upload -->
                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm lg:col-span-1">
                    <div class="border-b border-border bg-slate-50 px-4 py-3">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">
                            Fichiers à réconcilier
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Fichiers partenaire — multi-sélection possible.
                        </p>
                    </div>
                    <div class="space-y-3 p-4">
                        <label
                            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/60 px-4 py-8 text-center transition hover:border-cyan-300 hover:bg-cyan-50/30"
                        >
                            <div class="rounded-full bg-cyan-100 p-2 text-cyan-800">
                                <Upload class="size-5" />
                            </div>
                            <span class="text-sm font-medium text-foreground">Ajouter des fichiers</span>
                            <span class="text-xs text-muted-foreground">CSV, Excel, TXT…</span>
                            <input
                                :key="fileInputKey"
                                type="file"
                                class="sr-only"
                                multiple
                                accept=".csv,.txt,.xlsx,.xls"
                                @change="onFilesSelected"
                            />
                        </label>

                        <ul v-if="files.length" class="divide-y divide-border rounded-lg border border-border">
                            <li
                                v-for="f in files"
                                :key="f.id"
                                class="flex items-center gap-2 px-3 py-2 text-sm"
                            >
                                <FileUp class="size-4 shrink-0 text-muted-foreground" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-foreground">{{ f.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ formatSize(f.size) }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded p-1.5 text-red-600 hover:bg-red-50"
                                    title="Retirer"
                                    @click="removeFile(f.id)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Dates Flexcube -->
                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm lg:col-span-2">
                    <div class="border-b border-border bg-slate-50 px-4 py-3">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">
                            Période Flexcube
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Plage de dates des écritures Flexcube à comparer.
                        </p>
                    </div>
                    <div class="grid gap-4 p-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="date_debut">Date début</Label>
                            <Input id="date_debut" v-model="dateDebut" type="date" class="h-11" />
                        </div>
                        <div class="space-y-2">
                            <Label for="date_fin">Date fin</Label>
                            <Input id="date_fin" v-model="dateFin" type="date" class="h-11" />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border bg-muted/30 px-4 py-3">
                        <Button
                            type="button"
                            variant="outline"
                            class="min-w-[140px]"
                            :disabled="busy"
                            @click="reinitialiser"
                        >
                            <RefreshCw class="size-4" />
                            Réinitialiser
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="min-w-[140px] border-cyan-200 text-cyan-800 hover:bg-cyan-50"
                            :disabled="busy || files.length === 0"
                            @click="charger"
                        >
                            <Upload class="size-4" />
                            Charger
                        </Button>
                        <Button
                            type="button"
                            class="min-w-[180px] bg-slate-900 text-white hover:bg-slate-800 disabled:opacity-50"
                            :disabled="!canLaunch"
                            :title="filesLoaded ? 'Lancer la réconciliation' : 'Chargez d’abord les fichiers'"
                            @click="lancer"
                        >
                            <Play class="size-4" />
                            Lancer la Reconciliation
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
