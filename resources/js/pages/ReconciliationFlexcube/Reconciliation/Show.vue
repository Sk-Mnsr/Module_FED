<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PlotlyChart from '@/components/reconciliation/PlotlyChart.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link } from '@inertiajs/vue3';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    CalendarRange,
    CheckCircle2,
    CircleDot,
    Clock,
    Copy,
    FileSpreadsheet,
    Filter,
    ImageIcon,
    Layers,
    Play,
    RefreshCw,
    Scale,
    Trash2,
    Upload,
    Wifi,
    WifiOff,
} from 'lucide-vue-next';
import { computed, ref, type Component } from 'vue';

type Partenaire = {
    id: number;
    identifiant: string;
    nom: string;
    icone_url: string | null;
};

type GatewayInfo = {
    online: boolean;
    mode: string | null;
    error: string | null;
    url: string | null;
};

type UploadedFile = {
    id: string;
    file: File;
    name: string;
    size: number;
};

type SummaryRow = Record<string, string | number | null>;
type TauxPayload = {
    taux_reussite?: number | string | null;
    reconcilies?: number | string | null;
    total?: number | string | null;
    comptes?: Record<string, number> | null;
    [key: string]: string | number | null | Record<string, number> | undefined;
};

type PlotlyFigure = {
    data?: unknown[];
    layout?: Record<string, unknown>;
    config?: Record<string, unknown>;
};

type TabKey = 'resume' | 'graphiques' | 'excel' | 'flex' | 'reconciliation';

const props = defineProps<{
    partenaire: Partenaire;
    gateway: GatewayInfo;
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
/** true uniquement après un POST /run réussi — évite d’afficher d’anciens graphes/tables. */
const reconciliationDone = ref(false);
const gatewayMode = ref<string | null>(props.gateway.mode);
/** Pas de bandeau rouge au chargement : le badge « Service indisponible » suffit. */
const message = ref<{ type: 'info' | 'success' | 'error'; text: string } | null>(null);
const summary = ref<SummaryRow[] | SummaryRow | null>(null);
const taux = ref<TauxPayload | null>(null);
const carte = ref<SummaryRow | null>(null);
const resumeRows = ref<SummaryRow[]>([]);
const selectedStatuts = ref<string[]>([]);
const lastExcelUrl = ref<string | null>(null);
const activeTab = ref<TabKey>('resume');
const tableRows = ref<SummaryRow[]>([]);
const tableTotal = ref<number | null>(null);
const tableBusy = ref(false);
const graphsBusy = ref(false);
const grapheStatut = ref<PlotlyFigure | null>(null);
const grapheEvolution = ref<PlotlyFigure | null>(null);
const evolutionType = ref<'W2B' | 'B2W'>('W2B');

/** Aligné sur streambase / reconciliation_engine — libellés orientés métier. */
type StatutMeta = {
    icon: Component;
    shortLabel: string;
    hint: string;
    tone: 'ok' | 'watch' | 'alert';
    chip: string;
    iconWrap: string;
    iconClass: string;
    bar: string;
};

const STATUT_META: Record<string, StatutMeta> = {
    Réconcilié: {
        icon: CheckCircle2,
        shortLabel: 'Exact',
        hint: 'Montant et horaire parfaitement alignés',
        tone: 'ok',
        chip: 'bg-emerald-50 text-emerald-800 border-emerald-200',
        iconWrap: 'bg-emerald-100',
        iconClass: 'text-emerald-700',
        bar: 'bg-emerald-500',
    },
    'Réconcilié avec tolérance': {
        icon: CircleDot,
        shortLabel: 'Tolérance horaire',
        hint: 'Montants OK, léger décalage d’horaire',
        tone: 'ok',
        chip: 'bg-teal-50 text-teal-800 border-teal-200',
        iconWrap: 'bg-teal-100',
        iconClass: 'text-teal-700',
        bar: 'bg-teal-500',
    },
    'Réconcilié - écart 8s à 1h': {
        icon: Clock,
        shortLabel: 'Écart 8s–1h',
        hint: 'Correspondance avec un écart de temps moyen',
        tone: 'watch',
        chip: 'bg-amber-50 text-amber-900 border-amber-200',
        iconWrap: 'bg-amber-100',
        iconClass: 'text-amber-700',
        bar: 'bg-amber-500',
    },
    'Réconcilié - écart > 1h': {
        icon: Clock,
        shortLabel: 'Écart > 1h',
        hint: 'Correspondance avec un écart de temps important',
        tone: 'watch',
        chip: 'bg-orange-50 text-orange-900 border-orange-200',
        iconWrap: 'bg-orange-100',
        iconClass: 'text-orange-700',
        bar: 'bg-orange-500',
    },
    'Ecart montant': {
        icon: Scale,
        shortLabel: 'Écart de montant',
        hint: 'Même opération, montants différents',
        tone: 'alert',
        chip: 'bg-orange-50 text-orange-800 border-orange-200',
        iconWrap: 'bg-orange-100',
        iconClass: 'text-orange-700',
        bar: 'bg-orange-500',
    },
    'Non comptabilisée': {
        icon: AlertCircle,
        shortLabel: 'Absent Flexcube',
        hint: 'Présent côté partenaire, absent en compta',
        tone: 'alert',
        chip: 'bg-sky-50 text-sky-800 border-sky-200',
        iconWrap: 'bg-sky-100',
        iconClass: 'text-sky-700',
        bar: 'bg-sky-500',
    },
    'Comptabilisation isolée': {
        icon: Layers,
        shortLabel: 'Isolé Flexcube',
        hint: 'Présent en compta, absent côté partenaire',
        tone: 'alert',
        chip: 'bg-indigo-50 text-indigo-800 border-indigo-200',
        iconWrap: 'bg-indigo-100',
        iconClass: 'text-indigo-700',
        bar: 'bg-indigo-500',
    },
    Doublon: {
        icon: Copy,
        shortLabel: 'Doublon',
        hint: 'Transaction détectée plus d’une fois',
        tone: 'alert',
        chip: 'bg-rose-50 text-rose-800 border-rose-200',
        iconWrap: 'bg-rose-100',
        iconClass: 'text-rose-700',
        bar: 'bg-rose-500',
    },
};

const STATUTS_ORDER = Object.keys(STATUT_META);

const RESUME_COLUMNS = [
    'Type Transaction',
    'Montant Partenaire',
    'Montant Flex',
    'Num_Tel_Client',
    'Nom_Client',
    'Compte',
    'Agence',
    'Ecart Montant',
    'Diff Heure',
    'Date Fichier Partenaire',
    'Periode Fichier',
    'Statut',
] as const;

const RESUME_COLUMN_LABELS: Record<string, string> = {
    'Type Transaction': 'Type',
    'Montant Partenaire': 'Montant partenaire',
    'Montant Flex': 'Montant Flexcube',
    Num_Tel_Client: 'Téléphone',
    Nom_Client: 'Client',
    Compte: 'Compte',
    Agence: 'Agence',
    'Ecart Montant': 'Écart montant',
    'Diff Heure': 'Écart horaire',
    'Date Fichier Partenaire': 'Date partenaire',
    'Periode Fichier': 'Période fichier',
    Statut: 'Statut',
};

const CARTE_META: Record<string, { label: string; hint: string }> = {
    diff_montant_partenaire: {
        label: 'Solde net partenaire',
        hint: 'W2B − B2W côté fichier partenaire',
    },
    diff_montant_flexcube: {
        label: 'Solde net Flexcube',
        hint: 'W2B − B2W côté écritures Flexcube',
    },
    ecart_difference: {
        label: 'Écart entre les deux soldes',
        hint: 'Différence à investiguer si non nulle',
    },
};

const DEFAULT_STATUT_META: StatutMeta = {
    icon: AlertTriangle,
    shortLabel: 'Autre',
    hint: '',
    tone: 'watch',
    chip: 'bg-slate-50 text-slate-700 border-slate-200',
    iconWrap: 'bg-slate-100',
    iconClass: 'text-slate-600',
    bar: 'bg-slate-400',
};

const canLaunch = computed(
    () => filesLoaded.value && dateDebut.value !== '' && dateFin.value !== '' && !busy.value,
);

const baseUrl = computed(
    () => `/reconciliation-flexcube/reconciliation/${props.partenaire.id}`,
);

const dbTabs = computed(() => {
    const tabs: { key: TabKey; label: string; resource: string | null }[] = [];

    // Excel / Flex en premier (sources), puis Résumé / graphes / résultat
    if (filesLoaded.value || reconciliationDone.value) {
        tabs.push(
            { key: 'excel', label: 'Excel', resource: 'excel' },
            { key: 'flex', label: 'Flex', resource: 'flex' },
        );
    }

    tabs.push({ key: 'resume', label: 'Résumé', resource: null });

    if (reconciliationDone.value && gatewayMode.value === 'two_pointers') {
        tabs.push({ key: 'graphiques', label: 'Graphiques', resource: null });
    }

    if (reconciliationDone.value) {
        if (gatewayMode.value === 'agence') {
            tabs.push({
                key: 'reconciliation',
                label: 'Réconciliation',
                resource: 'reconciliation-agence',
            });
        } else {
            tabs.push({
                key: 'reconciliation',
                label: 'Réconciliation',
                resource: 'reconciliation',
            });
        }
    }

    return tabs;
});

function readCookie(name: string): string | null {
    const match = document.cookie.match(
        new RegExp(`(?:^|; )${name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1')}=([^;]*)`),
    );
    return match ? decodeURIComponent(match[1]) : null;
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * En-têtes CSRF pour les fetch hors Inertia.
 *
 * Laravel (VerifyCsrfToken) lit d'abord `_token` / `X-CSRF-TOKEN`,
 * et seulement sinon `X-XSRF-TOKEN` (cookie chiffré). Le meta peut
 * être périmé après navigation Inertia / régénération de session,
 * alors que le cookie XSRF reste aligné → on privilégie le cookie
 * et on n'envoie PAS les deux en même temps.
 */
function csrfHeaders(extra: Record<string, string> = {}): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...extra,
    };
    const xsrf = readCookie('XSRF-TOKEN');
    if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
        return headers;
    }
    const meta = csrfToken();
    if (meta) {
        headers['X-CSRF-TOKEN'] = meta;
    }
    return headers;
}

function withCsrf(form: FormData): FormData {
    // Même logique : ne pas forcer un _token meta si le cookie XSRF existe.
    if (readCookie('XSRF-TOKEN')) {
        return form;
    }
    const token = csrfToken();
    if (token && !form.has('_token')) {
        form.append('_token', token);
    }
    return form;
}

function formatCsrfHint(status: number, fallback: string): string {
    if (status !== 419 && !/csrf/i.test(fallback)) {
        return fallback;
    }
    const host = window.location.hostname;
    return (
        `${fallback} — reconnectez-vous ou rechargez la page. ` +
        `Utilisez toujours la même URL (ex. http://localhost et non 127.0.0.1) : ` +
        `vous êtes sur « ${host} », APP_URL Laravel doit correspondre.`
    );
}

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

function revokeExcelUrl() {
    if (lastExcelUrl.value) {
        URL.revokeObjectURL(lastExcelUrl.value);
        lastExcelUrl.value = null;
    }
}

function clearReconciliationUi() {
    reconciliationDone.value = false;
    summary.value = null;
    taux.value = null;
    carte.value = null;
    resumeRows.value = [];
    selectedStatuts.value = [];
    grapheStatut.value = null;
    grapheEvolution.value = null;
    tableRows.value = [];
    tableTotal.value = null;
    activeTab.value = 'resume';
    revokeExcelUrl();
}

async function reinitialiser() {
    dateDebut.value = '';
    dateFin.value = '';
    files.value = [];
    filesLoaded.value = false;
    clearReconciliationUi();
    fileInputKey.value += 1;
    message.value = null;

    if (!props.gateway.online) {
        return;
    }

    busy.value = true;
    try {
        const res = await fetch(`${baseUrl.value}/reset`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders(),
            body: withCsrf(new FormData()),
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.ok === false) {
            const raw = json.message ?? `Reset échoué (HTTP ${res.status})`;
            throw new Error(formatCsrfHint(res.status, raw));
        }
        message.value = { type: 'success', text: 'Données gateway réinitialisées pour ce partenaire.' };
    } catch (e) {
        message.value = {
            type: 'error',
            text: e instanceof Error ? e.message : 'Échec du reset gateway.',
        };
    } finally {
        busy.value = false;
    }
}

async function charger() {
    if (files.value.length === 0) {
        message.value = { type: 'error', text: 'Ajoutez au moins un fichier partenaire à charger.' };
        return;
    }
    if (!dateDebut.value || !dateFin.value) {
        message.value = { type: 'error', text: 'Renseignez la date début et la date fin Flexcube avant le chargement.' };
        return;
    }
    if (dateFin.value < dateDebut.value) {
        message.value = { type: 'error', text: 'La date fin doit être postérieure à la date début.' };
        return;
    }

    busy.value = true;
    message.value = { type: 'info', text: 'Chargement des fichiers vers le gateway…' };
    clearReconciliationUi();

    try {
        const form = withCsrf(new FormData());
        form.append('date_debut', dateDebut.value);
        form.append('date_fin', dateFin.value);
        for (const f of files.value) {
            form.append('files[]', f.file, f.name);
        }

        const res = await fetch(`${baseUrl.value}/charger`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders(),
            body: form,
        });

        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.ok === false) {
            const raw = json.message ?? `Chargement échoué (HTTP ${res.status})`;
            throw new Error(formatCsrfHint(res.status, raw));
        }

        if (typeof json.mode === 'string') {
            gatewayMode.value = json.mode;
        }

        filesLoaded.value = true;
        activeTab.value = 'excel';
        await loadDbTab('excel');
        const gatewayMessage =
            typeof json.data?.message === 'string' ? json.data.message : null;
        message.value = {
            type: 'success',
            text:
                gatewayMessage ??
                `${files.value.length} fichier(s) chargé(s) pour « ${props.partenaire.nom} ». Vous pouvez lancer la réconciliation.`,
        };
    } catch (e) {
        filesLoaded.value = false;
        message.value = {
            type: 'error',
            text: e instanceof Error ? e.message : 'Échec du chargement.',
        };
    } finally {
        busy.value = false;
    }
}

async function loadResults() {
    const mode = gatewayMode.value ? `?mode=${encodeURIComponent(gatewayMode.value)}` : '';
    const res = await fetch(`${baseUrl.value}/results${mode}`, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) {
        return;
    }
    summary.value = json.summary ?? null;
    taux.value = json.taux ?? null;
    carte.value = json.carte ?? null;
    resumeRows.value = Array.isArray(json.resume) ? json.resume : [];
    if (typeof json.mode === 'string') {
        gatewayMode.value = json.mode;
    }

    const present = new Set(
        statusCards.value.filter((c) => c.count > 0).map((c) => c.statut),
    );
    selectedStatuts.value = STATUTS_ORDER.filter((s) => present.has(s));

    if (gatewayMode.value === 'two_pointers') {
        await loadGraphs();
    } else {
        grapheStatut.value = null;
        grapheEvolution.value = null;
    }
}

async function loadGraphs() {
    graphsBusy.value = true;
    try {
        const [statutRes, evoRes] = await Promise.all([
            fetch(`${baseUrl.value}/graphe/statut`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }),
            fetch(
                `${baseUrl.value}/graphe/evolution?type_transaction=${encodeURIComponent(evolutionType.value)}`,
                {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                },
            ),
        ]);

        const statutJson = await statutRes.json().catch(() => ({}));
        const evoJson = await evoRes.json().catch(() => ({}));

        grapheStatut.value =
            statutRes.ok && statutJson.ok !== false && statutJson.data ? statutJson.data : null;
        grapheEvolution.value =
            evoRes.ok && evoJson.ok !== false && evoJson.data ? evoJson.data : null;
    } catch {
        grapheStatut.value = null;
        grapheEvolution.value = null;
    } finally {
        graphsBusy.value = false;
    }
}

async function changeEvolutionType(type: 'W2B' | 'B2W') {
    evolutionType.value = type;
    if (activeTab.value === 'graphiques' || grapheEvolution.value) {
        graphsBusy.value = true;
        try {
            const res = await fetch(
                `${baseUrl.value}/graphe/evolution?type_transaction=${encodeURIComponent(type)}`,
                {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                },
            );
            const json = await res.json().catch(() => ({}));
            grapheEvolution.value = res.ok && json.ok !== false && json.data ? json.data : null;
        } finally {
            graphsBusy.value = false;
        }
    }
}

async function loadDbTab(resource: string | null) {
    if (!resource) {
        tableRows.value = [];
        tableTotal.value = null;
        return;
    }

    tableBusy.value = true;
    try {
        const res = await fetch(`${baseUrl.value}/db/${resource}?limit=50`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.ok === false) {
            throw new Error(json.message ?? `Lecture ${resource} échouée`);
        }
        tableRows.value = Array.isArray(json.data) ? json.data : [];
        tableTotal.value = typeof json.total === 'number' ? json.total : tableRows.value.length;
    } catch (e) {
        tableRows.value = [];
        tableTotal.value = null;
        message.value = {
            type: 'error',
            text: e instanceof Error ? e.message : 'Échec lecture table gateway.',
        };
    } finally {
        tableBusy.value = false;
    }
}

async function selectTab(key: TabKey) {
    activeTab.value = key;
    if (key === 'resume') {
        if (reconciliationDone.value && !resumeRows.value.length) {
            await loadResults();
        }
        return;
    }
    if (key === 'graphiques') {
        if (!reconciliationDone.value) {
            grapheStatut.value = null;
            grapheEvolution.value = null;
            return;
        }
        if (!grapheStatut.value && !grapheEvolution.value) {
            await loadGraphs();
        }
        return;
    }
    if (key === 'reconciliation' && !reconciliationDone.value) {
        tableRows.value = [];
        tableTotal.value = null;
        return;
    }
    const tab = dbTabs.value.find((t) => t.key === key);
    await loadDbTab(tab?.resource ?? null);
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
    message.value = { type: 'info', text: 'Lancement de la réconciliation via le gateway…' };
    revokeExcelUrl();

    try {
        const form = withCsrf(new FormData());
        if (gatewayMode.value) {
            form.append('mode', gatewayMode.value);
        }
        if (dateDebut.value) {
            form.append('date_debut', dateDebut.value);
        }
        if (dateFin.value) {
            form.append('date_fin', dateFin.value);
        }

        const res = await fetch(`${baseUrl.value}/run`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders({
                Accept: 'application/json, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, */*',
            }),
            body: form,
        });

        const contentType = res.headers.get('Content-Type') ?? '';

        if (!res.ok) {
            const json = contentType.includes('application/json')
                ? await res.json().catch(() => ({}))
                : {};
            const raw = json.message ?? `Réconciliation échouée (HTTP ${res.status})`;
            throw new Error(formatCsrfHint(res.status, raw));
        }

        if (contentType.includes('application/json')) {
            const json = await res.json();
            if (json.ok === false) {
                throw new Error(json.message ?? 'Réconciliation échouée.');
            }
        } else {
            // Garder l'Excel en mémoire pour le bouton « Télécharger » (pas de téléchargement auto).
            const blob = await res.blob();
            lastExcelUrl.value = URL.createObjectURL(blob);
        }

        await loadResults();
        reconciliationDone.value = true;
        activeTab.value = 'resume';
        if (gatewayMode.value === 'two_pointers') {
            // Précharge les graphes pour l’onglet Graphiques
            await loadGraphs();
        } else if (gatewayMode.value === 'agence') {
            // Table détail agence disponible via l’onglet Réconciliation
        }

        const tauxText =
            taux.value && typeof taux.value === 'object' && 'taux_reussite' in taux.value
                ? ` — taux ${(taux.value as { taux_reussite: number }).taux_reussite}%`
                : '';

        message.value = {
            type: 'success',
            text: `Réconciliation « ${props.partenaire.nom} » terminée (${gatewayMode.value ?? 'mode auto'})${tauxText}. Enregistrée dans l’historique.`,
        };
    } catch (e) {
        message.value = {
            type: 'error',
            text: e instanceof Error ? e.message : 'Échec de la réconciliation.',
        };
    } finally {
        busy.value = false;
    }
}

const summaryRows = computed((): SummaryRow[] => {
    if (!summary.value) return [];
    return Array.isArray(summary.value) ? summary.value : [summary.value];
});

const statusCards = computed(() => {
    const counts = new Map<string, number>();

    for (const row of summaryRows.value) {
        const statut = String(row.STATUT ?? row.Statut ?? '');
        if (!statut) continue;
        const nb = Number(row.NB ?? row.nb ?? 0);
        counts.set(statut, Number.isFinite(nb) ? nb : 0);
    }

    if (counts.size === 0 && taux.value?.comptes && typeof taux.value.comptes === 'object') {
        for (const [statut, nb] of Object.entries(taux.value.comptes)) {
            counts.set(statut, Number(nb) || 0);
        }
    }

    const order =
        gatewayMode.value === 'agence'
            ? ['Réconcilié', 'Ecart montant', 'Non comptabilisée', 'Comptabilisation isolée']
            : STATUTS_ORDER;

    return order.map((statut) => {
        const meta = STATUT_META[statut] ?? DEFAULT_STATUT_META;
        return {
            statut,
            count: counts.get(statut) ?? 0,
            ...meta,
        };
    });
});

const statusOkCards = computed(() => statusCards.value.filter((c) => c.tone === 'ok'));
const statusWatchCards = computed(() => statusCards.value.filter((c) => c.tone === 'watch'));
const statusAlertCards = computed(() => statusCards.value.filter((c) => c.tone === 'alert'));
const statusAttentionCards = computed(() => [...statusWatchCards.value, ...statusAlertCards.value]);

const tauxHero = computed(() => {
    if (!taux.value) return null;
    const tauxRaw = Number(taux.value.taux_reussite ?? NaN);
    const reconcilies = Number(taux.value.reconcilies ?? 0);
    const total = Number(taux.value.total ?? 0);
    const aTraiter = Math.max(0, total - reconcilies);
    const pct = Number.isFinite(tauxRaw) ? tauxRaw : total > 0 ? (reconcilies / total) * 100 : 0;

    let verdict = 'Réconciliation terminée';
    let verdictClass = 'text-slate-700';
    if (pct >= 99) {
        verdict = 'Excellent — quasi toutes les opérations sont alignées';
        verdictClass = 'text-emerald-800';
    } else if (pct >= 95) {
        verdict = 'Bon résultat — quelques lignes à revoir';
        verdictClass = 'text-teal-800';
    } else if (pct >= 80) {
        verdict = 'Attention — plusieurs écarts à analyser';
        verdictClass = 'text-amber-800';
    } else {
        verdict = 'Écarts importants — revue manuelle recommandée';
        verdictClass = 'text-rose-800';
    }

    return {
        pct,
        reconcilies,
        total,
        aTraiter,
        verdict,
        verdictClass,
        barClass:
            pct >= 99
                ? 'bg-emerald-500'
                : pct >= 95
                  ? 'bg-teal-500'
                  : pct >= 80
                    ? 'bg-amber-500'
                    : 'bg-rose-500',
    };
});

const carteKpis = computed(() => {
    if (!carte.value) return [];
    return Object.entries(CARTE_META)
        .filter(([key]) => key in carte.value!)
        .map(([key, meta]) => ({
            key,
            ...meta,
            value: carte.value![key],
            highlight: key === 'ecart_difference' && Number(carte.value![key]) !== 0,
        }));
});

const resumeColumns = computed((): string[] => {
    if (!resumeRows.value.length) return [];
    const keys = Object.keys(resumeRows.value[0] ?? {});
    const preferred = RESUME_COLUMNS.filter((c) => keys.includes(c));
    return preferred.length ? preferred : keys;
});

const filteredResumeRows = computed(() => {
    if (!resumeRows.value.length) return [];
    if (!selectedStatuts.value.length) return resumeRows.value;
    return resumeRows.value.filter((row) => {
        const statut = String(row.Statut ?? row.STATUT ?? '');
        return selectedStatuts.value.includes(statut);
    });
});

function toggleStatutFilter(statut: string) {
    if (selectedStatuts.value.includes(statut)) {
        selectedStatuts.value = selectedStatuts.value.filter((s) => s !== statut);
    } else {
        selectedStatuts.value = [...selectedStatuts.value, statut];
    }
}

function selectAllStatuts() {
    selectedStatuts.value = statusCards.value.map((c) => c.statut);
}

function clearStatutFilters() {
    selectedStatuts.value = [];
}

function filterAlertsOnly() {
    selectedStatuts.value = statusAlertCards.value.map((c) => c.statut);
}

function formatResumeCell(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'number') {
        return Number.isInteger(value)
            ? value.toLocaleString('fr-FR')
            : value.toLocaleString('fr-FR', { maximumFractionDigits: 4 });
    }
    return String(value);
}

function formatMoney(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    const n = typeof value === 'number' ? value : Number(String(value).replace(/\s/g, ''));
    if (!Number.isFinite(n)) return String(value);
    return n.toLocaleString('fr-FR');
}

function columnLabel(col: string): string {
    return RESUME_COLUMN_LABELS[col] ?? col;
}

function statutMeta(statut: string): StatutMeta {
    return STATUT_META[statut] ?? DEFAULT_STATUT_META;
}

const tableColumns = computed((): string[] => {
    if (!tableRows.value.length) return [];
    return Object.keys(tableRows.value[0] ?? {});
});

const showResultsPanel = computed(
    () => filesLoaded.value || reconciliationDone.value || Boolean(lastExcelUrl.value),
);

const hasResumeContent = computed(
    () =>
        Boolean(tauxHero.value) ||
        Boolean(carteKpis.value.length) ||
        Boolean(statusCards.value.length) ||
        Boolean(resumeRows.value.length),
);

const modeLabel = computed(() => {
    if (gatewayMode.value === 'agence') return 'Réconciliation par agence';
    if (gatewayMode.value === 'two_pointers') return 'Réconciliation opération par opération';
    return null;
});

const setupStep = computed(() => {
    if (!files.value.length) return 1;
    if (!filesLoaded.value) return 2;
    if (!reconciliationDone.value) return 3;
    return 4;
});
</script>

<template>
    <Head :title="`Reconciliation — ${partenaire.nom}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative min-h-[calc(100vh-4rem)] overflow-hidden">
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(ellipse_at_top,_rgba(8,145,178,0.12),_transparent_60%),linear-gradient(180deg,#f8fafc_0%,transparent_100%)]"
            />

            <div class="relative z-10 flex w-full flex-col gap-6 p-4 sm:p-6 lg:px-8 lg:py-6 xl:px-10">
                <!-- En-tête partenaire -->
                <header
                    class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm backdrop-blur sm:p-5"
                >
                    <div class="flex min-w-0 items-center gap-4">
                        <div
                            class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white shadow-inner"
                        >
                            <img
                                v-if="partenaire.icone_url"
                                :src="partenaire.icone_url"
                                :alt="partenaire.nom"
                                class="size-full object-contain p-1.5"
                            />
                            <ImageIcon v-else class="size-6 text-slate-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wider text-cyan-800/70">
                                Reconciliation Flexcube
                            </p>
                            <h1 class="truncate text-2xl font-semibold tracking-tight text-slate-900">
                                {{ partenaire.nom }}
                            </h1>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span
                                    v-if="modeLabel"
                                    class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700"
                                >
                                    {{ modeLabel }}
                                </span>

                                <span
                                    v-if="gateway.online"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-emerald-200/80"
                                >
                                    <Wifi class="size-3.5" />
                                    Service disponible
                                </span>

                                <TooltipProvider v-else :delay-duration="150">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-800 ring-1 ring-rose-200/80"
                                            >
                                                <WifiOff class="size-3.5" />
                                                Service indisponible
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent side="bottom" class="max-w-xs text-xs leading-relaxed">
                                            <p class="font-medium">Gateway inaccessible</p>
                                            <p class="mt-1 text-primary-foreground/90">
                                                Démarrez le service :
                                                <code class="ml-1 rounded bg-black/20 px-1 py-0.5 font-mono">
                                                    npm run gateway:recon
                                                </code>
                                            </p>
                                            <p v-if="gateway.url" class="mt-1 opacity-80">
                                                {{ gateway.url }}
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </div>
                    </div>

                    <Link
                        href="/reconciliation-flexcube/reconciliation"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        <ArrowLeft class="size-4" />
                        Changer de partenaire
                    </Link>
                </header>

                <div
                    v-if="message"
                    class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-sm"
                    :class="{
                        'border-sky-200 bg-sky-50 text-sky-950': message.type === 'info',
                        'border-emerald-200 bg-emerald-50 text-emerald-950': message.type === 'success',
                        'border-rose-200 bg-rose-50 text-rose-950': message.type === 'error',
                    }"
                >
                    <CheckCircle2 v-if="message.type === 'success'" class="mt-0.5 size-4 shrink-0" />
                    <AlertCircle v-else-if="message.type === 'error'" class="mt-0.5 size-4 shrink-0" />
                    <CircleDot v-else class="mt-0.5 size-4 shrink-0" />
                    <p class="min-w-0 flex-1 break-words">{{ message.text }}</p>
                    <button
                        type="button"
                        class="shrink-0 rounded-md px-1.5 py-0.5 text-xs font-medium opacity-60 hover:opacity-100"
                        title="Fermer"
                        @click="message = null"
                    >
                        ✕
                    </button>
                </div>

                <!-- Configuration : parcours en 3 étapes -->
                <section
                    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
                >
                    <div
                        class="border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-cyan-50/40 px-5 py-4 sm:px-6"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">
                                    Préparer la réconciliation
                                </h2>
                                <p class="mt-0.5 text-sm text-slate-500">
                                    Déposez le fichier partenaire, choisissez la période, puis lancez
                                    l’analyse.
                                </p>
                            </div>
                            <ol class="flex flex-wrap items-center gap-2 text-xs font-medium">
                                <li
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1"
                                    :class="
                                        setupStep >= 1
                                            ? 'bg-cyan-100 text-cyan-900'
                                            : 'bg-slate-100 text-slate-500'
                                    "
                                >
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full text-[11px] font-bold"
                                        :class="
                                            files.length
                                                ? 'bg-cyan-700 text-white'
                                                : 'bg-white text-cyan-800'
                                        "
                                    >
                                        1
                                    </span>
                                    Fichiers
                                </li>
                                <li class="hidden text-slate-300 sm:inline">→</li>
                                <li
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1"
                                    :class="
                                        setupStep >= 2
                                            ? 'bg-cyan-100 text-cyan-900'
                                            : 'bg-slate-100 text-slate-500'
                                    "
                                >
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full text-[11px] font-bold"
                                        :class="
                                            dateDebut && dateFin
                                                ? 'bg-cyan-700 text-white'
                                                : 'bg-white text-cyan-800'
                                        "
                                    >
                                        2
                                    </span>
                                    Période
                                </li>
                                <li class="hidden text-slate-300 sm:inline">→</li>
                                <li
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1"
                                    :class="
                                        setupStep >= 3
                                            ? 'bg-cyan-100 text-cyan-900'
                                            : 'bg-slate-100 text-slate-500'
                                    "
                                >
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full bg-white text-[11px] font-bold text-cyan-800"
                                    >
                                        3
                                    </span>
                                    Lancer
                                </li>
                            </ol>
                        </div>
                    </div>

                    <div class="grid lg:grid-cols-5">
                        <!-- Étape 1 : fichiers -->
                        <div class="space-y-4 border-b border-slate-100 p-5 sm:p-6 lg:col-span-2 lg:border-b-0 lg:border-r">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex size-7 items-center justify-center rounded-lg bg-cyan-100 text-xs font-bold text-cyan-800"
                                >
                                    1
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">
                                        Fichier partenaire
                                    </h3>
                                    <p class="text-xs text-slate-500">Excel, CSV ou TXT</p>
                                </div>
                            </div>

                            <label
                                class="group flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/80 px-4 py-10 text-center transition hover:border-cyan-400 hover:bg-cyan-50/50"
                            >
                                <div
                                    class="rounded-2xl bg-gradient-to-br from-cyan-500 to-teal-600 p-3 text-white shadow-md shadow-cyan-500/25 transition group-hover:scale-105"
                                >
                                    <Upload class="size-6" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">
                                        Glissez un fichier ou cliquez ici
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Multi-sélection possible · .xlsx .xls .csv .txt
                                    </p>
                                </div>
                                <input
                                    :key="fileInputKey"
                                    type="file"
                                    class="sr-only"
                                    multiple
                                    accept=".csv,.txt,.xlsx,.xls"
                                    @change="onFilesSelected"
                                />
                            </label>

                            <p class="text-xs leading-relaxed text-slate-500">
                                <template v-if="gatewayMode === 'agence'">
                                    Wave Agence : colonnes
                                    <span class="font-medium text-slate-700">Quand</span>,
                                    <span class="font-medium text-slate-700">Quoi</span>,
                                    <span class="font-medium text-slate-700">Montant</span>,
                                    <span class="font-medium text-slate-700">Opérateur</span>.
                                </template>
                                <template v-else>
                                    Colonnes attendues :
                                    <span class="font-medium text-slate-700">DATE TRANSACTION</span>,
                                    <span class="font-medium text-slate-700">TYPE TRANSACTION</span>,
                                    <span class="font-medium text-slate-700">MONTANT</span>…
                                </template>
                            </p>

                            <ul v-if="files.length" class="space-y-2">
                                <li
                                    v-for="f in files"
                                    :key="f.id"
                                    class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm"
                                >
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"
                                    >
                                        <FileSpreadsheet class="size-4" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-800">
                                            {{ f.name }}
                                        </p>
                                        <p class="text-xs text-slate-500">{{ formatSize(f.size) }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                        title="Retirer"
                                        @click="removeFile(f.id)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Étapes 2–3 : période + actions -->
                        <div class="flex flex-col lg:col-span-3">
                            <div class="flex-1 space-y-5 p-5 sm:p-6">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex size-7 items-center justify-center rounded-lg bg-cyan-100 text-xs font-bold text-cyan-800"
                                    >
                                        2
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900">
                                            Période Flexcube
                                        </h3>
                                        <p class="text-xs text-slate-500">
                                            Dates des écritures à comparer
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="grid gap-4 rounded-2xl border border-slate-100 bg-slate-50/70 p-4 sm:grid-cols-2"
                                >
                                    <div class="space-y-2">
                                        <Label for="date_debut" class="text-slate-700">
                                            Date de début
                                        </Label>
                                        <div class="relative">
                                            <CalendarRange
                                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                                            />
                                            <Input
                                                id="date_debut"
                                                v-model="dateDebut"
                                                type="date"
                                                class="h-12 border-slate-200 bg-white pl-10 shadow-sm"
                                            />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="date_fin" class="text-slate-700">Date de fin</Label>
                                        <div class="relative">
                                            <CalendarRange
                                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                                            />
                                            <Input
                                                id="date_fin"
                                                v-model="dateFin"
                                                type="date"
                                                class="h-12 border-slate-200 bg-white pl-10 shadow-sm"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="rounded-xl border border-dashed border-slate-200 bg-white px-4 py-3 text-sm text-slate-600"
                                >
                                    <p class="font-medium text-slate-800">Comment procéder ?</p>
                                    <ol class="mt-1 list-decimal space-y-0.5 pl-4 text-xs text-slate-500">
                                        <li>Ajoutez le fichier partenaire</li>
                                        <li>Indiquez la période Flexcube</li>
                                        <li>
                                            Cliquez sur
                                            <span class="font-medium text-slate-700">Charger</span>
                                            puis
                                            <span class="font-medium text-slate-700">Lancer</span>
                                        </li>
                                    </ol>
                                </div>
                            </div>

                            <div
                                class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6"
                            >
                                <p class="text-xs text-slate-500">
                                    <template v-if="!files.length">
                                        Commencez par ajouter un fichier.
                                    </template>
                                    <template v-else-if="!filesLoaded">
                                        Fichier prêt — chargez-le vers le service.
                                    </template>
                                    <template v-else-if="!reconciliationDone">
                                        Fichiers chargés — vous pouvez lancer l’analyse.
                                    </template>
                                    <template v-else>
                                        Réconciliation terminée — relancez si besoin.
                                    </template>
                                </p>
                                <div class="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="h-11 rounded-xl border-slate-200 bg-white"
                                        :disabled="busy"
                                        @click="reinitialiser"
                                    >
                                        <RefreshCw class="size-4" />
                                        Réinitialiser
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="h-11 rounded-xl border-cyan-200 bg-cyan-50 text-cyan-900 hover:bg-cyan-100"
                                        :disabled="busy || files.length === 0 || !gateway.online"
                                        @click="charger"
                                    >
                                        <Upload class="size-4" />
                                        Charger
                                    </Button>
                                    <Button
                                        type="button"
                                        class="h-11 min-w-[200px] rounded-xl bg-gradient-to-r from-slate-900 to-slate-800 text-white shadow-md shadow-slate-900/20 hover:from-slate-800 hover:to-slate-700 disabled:opacity-50"
                                        :disabled="!canLaunch || !gateway.online"
                                        :title="
                                            filesLoaded
                                                ? 'Lancer la réconciliation'
                                                : 'Chargez d’abord les fichiers'
                                        "
                                        @click="lancer"
                                    >
                                        <Play class="size-4" />
                                        Lancer la réconciliation
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

            <div
                v-if="showResultsPanel"
                class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-slate-50 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">
                            Résultats de réconciliation
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Synthèse, graphiques et tables détaillées pour « {{ partenaire.nom }} »
                        </p>
                    </div>
                    <a
                        v-if="lastExcelUrl"
                        :href="lastExcelUrl"
                        class="inline-flex items-center gap-1.5 rounded-md border border-cyan-200 bg-cyan-50 px-3 py-1.5 text-sm font-medium text-cyan-900 hover:bg-cyan-100"
                        :download="`reconciliation_${partenaire.identifiant}.xlsx`"
                    >
                        <FileSpreadsheet class="size-4" />
                        Télécharger l’Excel
                    </a>
                </div>

                <div class="flex flex-wrap gap-1 border-b border-border px-3 pt-3">
                    <button
                        v-for="tab in dbTabs"
                        :key="tab.key"
                        type="button"
                        class="rounded-t-md px-3 py-2 text-sm"
                        :class="
                            activeTab === tab.key
                                ? 'bg-white font-medium text-foreground shadow-sm ring-1 ring-border'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="selectTab(tab.key)"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div class="space-y-4 p-4">
                    <template v-if="activeTab === 'resume'">
                        <div v-if="hasResumeContent" class="space-y-6">
                            <!-- Verdict global -->
                            <div
                                v-if="tauxHero"
                                class="overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-cyan-50/40 p-5 shadow-sm"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                            Résultat global
                                        </p>
                                        <p class="mt-1 text-3xl font-semibold tracking-tight text-slate-900">
                                            {{
                                                tauxHero.pct.toLocaleString('fr-FR', {
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 2,
                                                })
                                            }}%
                                            <span class="text-base font-medium text-slate-500">
                                                de réussite
                                            </span>
                                        </p>
                                        <p class="mt-1 text-sm font-medium" :class="tauxHero.verdictClass">
                                            {{ tauxHero.verdict }}
                                        </p>
                                        <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-200/80">
                                            <div
                                                class="h-full rounded-full transition-all duration-500"
                                                :class="tauxHero.barClass"
                                                :style="{ width: `${Math.min(100, Math.max(0, tauxHero.pct))}%` }"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-3 sm:min-w-[280px]">
                                        <div class="rounded-lg border border-white/80 bg-white/90 px-3 py-2.5 text-center shadow-sm">
                                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">
                                                Alignées
                                            </p>
                                            <p class="mt-0.5 text-lg font-semibold text-emerald-700">
                                                {{ tauxHero.reconcilies.toLocaleString('fr-FR') }}
                                            </p>
                                        </div>
                                        <div class="rounded-lg border border-white/80 bg-white/90 px-3 py-2.5 text-center shadow-sm">
                                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">
                                                À traiter
                                            </p>
                                            <p
                                                class="mt-0.5 text-lg font-semibold"
                                                :class="
                                                    tauxHero.aTraiter > 0 ? 'text-amber-700' : 'text-slate-700'
                                                "
                                            >
                                                {{ tauxHero.aTraiter.toLocaleString('fr-FR') }}
                                            </p>
                                        </div>
                                        <div class="rounded-lg border border-white/80 bg-white/90 px-3 py-2.5 text-center shadow-sm">
                                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">
                                                Total
                                            </p>
                                            <p class="mt-0.5 text-lg font-semibold text-slate-900">
                                                {{ tauxHero.total.toLocaleString('fr-FR') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Répartition -->
                            <div v-if="statusCards.length" class="space-y-4">
                                <div class="flex flex-wrap items-end justify-between gap-2">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-800">
                                            Répartition des opérations
                                        </h3>
                                        <p class="text-xs text-muted-foreground">
                                            Cliquez sur une catégorie pour filtrer le détail ci-dessous
                                        </p>
                                    </div>
                                    <button
                                        v-if="statusAlertCards.some((c) => c.count > 0)"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-900 hover:bg-amber-100"
                                        @click="filterAlertsOnly"
                                    >
                                        <Filter class="size-3.5" />
                                        Voir uniquement les écarts
                                    </button>
                                </div>

                                <div v-if="statusOkCards.length" class="space-y-2">
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700/80"
                                    >
                                        Correspondances
                                    </p>
                                    <div class="grid gap-2.5 sm:grid-cols-2">
                                        <button
                                            v-for="card in statusOkCards"
                                            :key="card.statut"
                                            type="button"
                                            class="group flex items-start gap-3 rounded-xl border bg-white p-3.5 text-left shadow-sm transition hover:border-slate-300 hover:shadow"
                                            :class="
                                                selectedStatuts.includes(card.statut)
                                                    ? 'border-teal-300 ring-2 ring-teal-100'
                                                    : 'border-slate-200'
                                            "
                                            @click="toggleStatutFilter(card.statut)"
                                        >
                                            <span
                                                class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                                                :class="card.iconWrap"
                                            >
                                                <component
                                                    :is="card.icon"
                                                    class="size-5"
                                                    :class="card.iconClass"
                                                />
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-baseline justify-between gap-2">
                                                    <span class="text-sm font-semibold text-slate-800">
                                                        {{ card.shortLabel }}
                                                    </span>
                                                    <span class="text-xl font-semibold tabular-nums text-slate-900">
                                                        {{ card.count.toLocaleString('fr-FR') }}
                                                    </span>
                                                </span>
                                                <span class="mt-0.5 block text-xs leading-snug text-slate-500">
                                                    {{ card.hint }}
                                                </span>
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="statusAttentionCards.length" class="space-y-2">
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-wider text-amber-700/80"
                                    >
                                        À analyser
                                    </p>
                                    <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                                        <button
                                            v-for="card in statusAttentionCards"
                                            :key="card.statut"
                                            type="button"
                                            class="group flex items-start gap-3 rounded-xl border bg-white p-3.5 text-left shadow-sm transition hover:border-slate-300 hover:shadow"
                                            :class="
                                                selectedStatuts.includes(card.statut)
                                                    ? 'border-amber-300 ring-2 ring-amber-100'
                                                    : card.count > 0
                                                      ? 'border-slate-200'
                                                      : 'border-slate-100 opacity-60'
                                            "
                                            @click="toggleStatutFilter(card.statut)"
                                        >
                                            <span
                                                class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                                                :class="card.iconWrap"
                                            >
                                                <component
                                                    :is="card.icon"
                                                    class="size-5"
                                                    :class="card.iconClass"
                                                />
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-baseline justify-between gap-2">
                                                    <span class="text-sm font-semibold text-slate-800">
                                                        {{ card.shortLabel }}
                                                    </span>
                                                    <span
                                                        class="text-xl font-semibold tabular-nums"
                                                        :class="
                                                            card.count > 0 && card.tone === 'alert'
                                                                ? 'text-rose-700'
                                                                : 'text-slate-900'
                                                        "
                                                    >
                                                        {{ card.count.toLocaleString('fr-FR') }}
                                                    </span>
                                                </span>
                                                <span class="mt-0.5 block text-xs leading-snug text-slate-500">
                                                    {{ card.hint }}
                                                </span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Soldes -->
                            <div v-if="carteKpis.length" class="space-y-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-800">Contrôle des soldes</h3>
                                    <p class="text-xs text-muted-foreground">
                                        Comparaison des flux nets partenaire vs Flexcube
                                    </p>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div
                                        v-for="kpi in carteKpis"
                                        :key="kpi.key"
                                        class="rounded-xl border p-4 shadow-sm"
                                        :class="
                                            kpi.highlight
                                                ? 'border-amber-200 bg-amber-50/60'
                                                : 'border-slate-200 bg-white'
                                        "
                                    >
                                        <p class="text-sm font-medium text-slate-800">{{ kpi.label }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ kpi.hint }}</p>
                                        <p
                                            class="mt-3 text-xl font-semibold tabular-nums"
                                            :class="kpi.highlight ? 'text-amber-900' : 'text-slate-900'"
                                        >
                                            {{ formatMoney(kpi.value as string | number | null) }}
                                            <span class="text-sm font-medium text-slate-500">XOF</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Table détail -->
                            <div v-if="resumeRows.length" class="space-y-3">
                                <div class="flex flex-wrap items-end justify-between gap-2">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-800">
                                            Détail des opérations
                                        </h3>
                                        <p class="text-xs text-muted-foreground">
                                            {{ filteredResumeRows.length.toLocaleString('fr-FR') }}
                                            affichée(s) sur
                                            {{ resumeRows.length.toLocaleString('fr-FR') }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-xs">
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-200 bg-white px-2.5 py-1.5 font-medium text-slate-700 hover:bg-slate-50"
                                            @click="selectAllStatuts"
                                        >
                                            Tout afficher
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-200 bg-white px-2.5 py-1.5 font-medium text-slate-500 hover:bg-slate-50"
                                            @click="clearStatutFilters"
                                        >
                                            Effacer filtres
                                        </button>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="card in statusCards.filter((c) => c.count > 0)"
                                        :key="`filtre-${card.statut}`"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition"
                                        :class="
                                            selectedStatuts.includes(card.statut)
                                                ? card.chip
                                                : 'border-slate-200 bg-slate-50 text-slate-500'
                                        "
                                        @click="toggleStatutFilter(card.statut)"
                                    >
                                        <component :is="card.icon" class="size-3.5 shrink-0" />
                                        {{ card.shortLabel }}
                                        <span class="opacity-70">{{ card.count }}</span>
                                    </button>
                                </div>

                                <div class="max-h-[28rem] overflow-auto rounded-xl border border-slate-200 shadow-sm">
                                    <table class="min-w-full text-left text-sm">
                                        <thead
                                            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/95 text-xs font-semibold text-slate-600 backdrop-blur"
                                        >
                                            <tr>
                                                <th
                                                    v-for="col in resumeColumns"
                                                    :key="col"
                                                    class="whitespace-nowrap px-3 py-2.5"
                                                >
                                                    {{ columnLabel(col) }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(row, idx) in filteredResumeRows"
                                                :key="idx"
                                                class="border-b border-slate-100 last:border-0 odd:bg-white even:bg-slate-50/50 hover:bg-cyan-50/40"
                                            >
                                                <td
                                                    v-for="col in resumeColumns"
                                                    :key="col"
                                                    class="max-w-[200px] truncate px-3 py-2"
                                                    :title="String(row[col] ?? '')"
                                                >
                                                    <span
                                                        v-if="col === 'Statut' || col === 'STATUT'"
                                                        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium"
                                                        :class="statutMeta(String(row[col] ?? '')).chip"
                                                    >
                                                        <component
                                                            :is="statutMeta(String(row[col] ?? '')).icon"
                                                            class="size-3.5"
                                                        />
                                                        {{
                                                            statutMeta(String(row[col] ?? '')).shortLabel
                                                        }}
                                                    </span>
                                                    <template
                                                        v-else-if="
                                                            col === 'Montant Partenaire' ||
                                                            col === 'Montant Flex' ||
                                                            col === 'Ecart Montant'
                                                        "
                                                    >
                                                        <span class="tabular-nums">
                                                            {{ formatMoney(row[col]) }}
                                                        </span>
                                                    </template>
                                                    <template v-else>
                                                        {{ formatResumeCell(row[col]) }}
                                                    </template>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-6 py-14 text-center"
                        >
                            <Layers class="size-8 text-slate-300" />
                            <p class="text-sm font-medium text-slate-700">Aucun résumé pour le moment</p>
                            <p class="max-w-md text-sm text-muted-foreground">
                                Chargez les fichiers partenaire, puis lancez la réconciliation pour voir le
                                taux de réussite, les écarts et le détail des opérations.
                            </p>
                        </div>
                    </template>

                    <template v-else-if="activeTab === 'graphiques'">
                        <p v-if="graphsBusy" class="text-sm text-muted-foreground">Chargement des graphiques…</p>
                        <div v-else class="grid gap-6 lg:grid-cols-2">
                            <div class="rounded-lg border border-border p-3">
                                <h3 class="mb-2 text-sm font-semibold text-slate-700">Répartition par statut</h3>
                                <PlotlyChart v-if="grapheStatut" :figure="grapheStatut" :height="380" />
                                <p v-else class="py-10 text-center text-sm text-muted-foreground">
                                    Aucun graphique statut (lancez d’abord la réconciliation).
                                </p>
                            </div>
                            <div class="rounded-lg border border-border p-3">
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-slate-700">Évolution montants</h3>
                                    <div class="flex gap-1">
                                        <button
                                            type="button"
                                            class="rounded px-2 py-1 text-xs"
                                            :class="
                                                evolutionType === 'W2B'
                                                    ? 'bg-slate-900 text-white'
                                                    : 'bg-muted text-muted-foreground'
                                            "
                                            @click="changeEvolutionType('W2B')"
                                        >
                                            W2B
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded px-2 py-1 text-xs"
                                            :class="
                                                evolutionType === 'B2W'
                                                    ? 'bg-slate-900 text-white'
                                                    : 'bg-muted text-muted-foreground'
                                            "
                                            @click="changeEvolutionType('B2W')"
                                        >
                                            B2W
                                        </button>
                                    </div>
                                </div>
                                <PlotlyChart v-if="grapheEvolution" :figure="grapheEvolution" :height="380" />
                                <p v-else class="py-10 text-center text-sm text-muted-foreground">
                                    Aucun graphique évolution.
                                </p>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <p v-if="tableBusy" class="text-sm text-muted-foreground">Chargement de la table…</p>
                        <p v-else-if="!tableRows.length" class="text-sm text-muted-foreground">
                            Aucune ligne (table vide ou pas encore chargée).
                        </p>
                        <div v-else class="overflow-x-auto">
                            <p v-if="tableTotal !== null" class="mb-2 text-xs text-muted-foreground">
                                {{ Math.min(tableRows.length, tableTotal) }} / {{ tableTotal }} ligne(s)
                            </p>
                            <table class="min-w-full text-left text-sm">
                                <thead class="border-b border-border bg-muted/40 text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th
                                            v-for="col in tableColumns"
                                            :key="col"
                                            class="whitespace-nowrap px-3 py-2 font-medium"
                                        >
                                            {{ col }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, idx) in tableRows"
                                        :key="idx"
                                        class="border-b border-border last:border-0"
                                    >
                                        <td
                                            v-for="col in tableColumns"
                                            :key="col"
                                            class="max-w-[220px] truncate px-3 py-2"
                                            :title="String(row[col] ?? '')"
                                        >
                                            {{ row[col] }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>
            </div>
        </div>
    </AppLayout>
</template>
