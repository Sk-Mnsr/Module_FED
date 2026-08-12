<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';
import {
    ArrowLeft,
    Building2,
    Calendar,
    ChevronRight,
    IdCard,
    Mail,
    Network,
    Pencil,
    Shield,
    UserRound,
} from 'lucide-vue-next';

interface Role {
    id: number;
    nom: string;
    slug: string;
    module?: string | null;
    description?: string | null;
}

interface ModuleOption {
    key: string;
    label: string;
}

interface PersonRef {
    id: number;
    name: string;
    email: string;
}

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        fonction?: string | null;
        activated?: boolean;
        created_at: string;
        updated_at: string;
        roles?: Role[];
        agence?: {
            id: number;
            code: string;
            nom: string;
        } | null;
        matricule?: string | null;
        department_id?: number | null;
        department?: { id: number; name: string } | null;
        n_plus1?: PersonRef | null;
        n_plus2?: PersonRef | null;
        n_plus_1?: PersonRef | null;
        n_plus_2?: PersonRef | null;
    };
    modules: ModuleOption[];
    accessibleModules: string[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Utilisateurs', href: '/users' },
    { title: props.user.name, href: '#' },
];

const initials = computed(() => {
    const parts = props.user.name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
});

const moduleLabel = (key: string) =>
    props.modules.find((module) => module.key === key)?.label ?? key;

const accessibleModuleItems = computed(() =>
    props.accessibleModules.map((key) => ({
        key,
        label: moduleLabel(key),
    })),
);

const nPlus1 = computed(() => props.user.n_plus1 ?? props.user.n_plus_1 ?? null);
const nPlus2 = computed(() => props.user.n_plus2 ?? props.user.n_plus_2 ?? null);

const isActive = computed(() => props.user.activated !== false);

const formatDateTime = (value: string) =>
    new Date(value).toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
</script>

<template>
    <Head :title="user.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <!-- En-tête profil -->
            <section
                class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            >
                <div
                    class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-primary via-primary to-red-800"
                />
                <div
                    class="pointer-events-none absolute -right-16 -top-16 size-56 rounded-full bg-primary/[0.06]"
                />
                <div
                    class="pointer-events-none absolute -bottom-20 left-1/3 size-40 rounded-full bg-slate-100/80"
                />

                <div
                    class="relative flex flex-col gap-6 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8"
                >
                    <div class="flex min-w-0 items-start gap-4 sm:items-center sm:gap-5">
                        <div
                            class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-red-800 text-xl font-semibold tracking-wide text-white shadow-lg shadow-primary/25 sm:size-20 sm:text-2xl"
                        >
                            {{ initials }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="truncate text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                                    {{ user.name }}
                                </h1>
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="
                                        isActive
                                            ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                                            : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200'
                                    "
                                >
                                    {{ isActive ? 'Actif' : 'Inactif' }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ user.fonction || 'Fonction non renseignée' }}
                            </p>
                            <p class="mt-2 flex items-center gap-1.5 truncate text-sm text-slate-600">
                                <Mail class="size-3.5 shrink-0 text-slate-400" />
                                {{ user.email }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Link :href="`/users/${user.id}/edit`">
                            <Button
                                variant="outline"
                                class="h-10 rounded-xl border-slate-300 bg-white text-slate-800 hover:bg-slate-50"
                            >
                                <Pencil class="mr-2 size-4" />
                                Modifier
                            </Button>
                        </Link>
                        <Link href="/users">
                            <Button class="h-10 rounded-xl bg-primary text-white hover:bg-primary/90">
                                <ArrowLeft class="mr-2 size-4" />
                                Retour à la liste
                            </Button>
                        </Link>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <!-- Identité & organisation -->
                <section
                    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm xl:col-span-2"
                >
                    <div class="flex items-center gap-2 border-b border-slate-100 px-6 py-4">
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <UserRound class="size-4" />
                        </div>
                        <h2 class="text-base font-semibold text-slate-900">
                            Identité & organisation
                        </h2>
                    </div>

                    <div class="grid gap-px bg-slate-100 sm:grid-cols-2">
                        <div class="bg-white px-6 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                Nom complet
                            </p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ user.name }}</p>
                        </div>
                        <div class="bg-white px-6 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                Email
                            </p>
                            <p class="mt-1 truncate text-sm font-medium text-slate-900">
                                {{ user.email }}
                            </p>
                        </div>
                        <div class="bg-white px-6 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                IDFLEX
                            </p>
                            <p class="mt-1 flex items-center gap-1.5 text-sm font-medium text-slate-900">
                                <IdCard class="size-3.5 text-slate-400" />
                                {{ user.matricule || '—' }}
                            </p>
                        </div>
                        <div class="bg-white px-6 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                Département
                            </p>
                            <p class="mt-1 text-sm font-medium text-slate-900">
                                {{ user.department?.name || '—' }}
                            </p>
                        </div>
                        <div class="bg-white px-6 py-4 sm:col-span-2">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                Agence (entité)
                            </p>
                            <p class="mt-1 flex items-center gap-1.5 text-sm font-medium text-slate-900">
                                <Building2 class="size-3.5 text-slate-400" />
                                <template v-if="user.agence">
                                    {{ user.agence.nom }}
                                    <span class="font-normal text-slate-500"
                                        >({{ user.agence.code }})</span
                                    >
                                </template>
                                <template v-else>—</template>
                            </p>
                        </div>
                        <div class="bg-white px-6 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                Créé le
                            </p>
                            <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-700">
                                <Calendar class="size-3.5 text-slate-400" />
                                {{ formatDateTime(user.created_at) }}
                            </p>
                        </div>
                        <div class="bg-white px-6 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                Dernière modification
                            </p>
                            <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-700">
                                <Calendar class="size-3.5 text-slate-400" />
                                {{ formatDateTime(user.updated_at) }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Hiérarchie -->
                <section
                    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
                >
                    <div class="flex items-center gap-2 border-b border-slate-100 px-6 py-4">
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Network class="size-4" />
                        </div>
                        <h2 class="text-base font-semibold text-slate-900">Hiérarchie</h2>
                    </div>

                    <div class="flex flex-col gap-3 p-6">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                Collaborateur
                            </p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ user.name }}</p>
                        </div>

                        <div class="flex justify-center text-slate-300">
                            <ChevronRight class="size-5 rotate-90" />
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                N+1
                            </p>
                            <template v-if="nPlus1">
                                <p class="mt-1 text-sm font-semibold text-slate-900">
                                    {{ nPlus1.name }}
                                </p>
                                <p class="mt-0.5 truncate text-xs text-slate-500">
                                    {{ nPlus1.email }}
                                </p>
                            </template>
                            <p v-else class="mt-1 text-sm text-slate-500">
                                Manager du département ou non défini
                            </p>
                        </div>

                        <div class="flex justify-center text-slate-300">
                            <ChevronRight class="size-5 rotate-90" />
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                N+2
                            </p>
                            <template v-if="nPlus2">
                                <p class="mt-1 text-sm font-semibold text-slate-900">
                                    {{ nPlus2.name }}
                                </p>
                                <p class="mt-0.5 truncate text-xs text-slate-500">
                                    {{ nPlus2.email }}
                                </p>
                            </template>
                            <p v-else class="mt-1 text-sm text-slate-500">—</p>
                        </div>
                    </div>
                </section>

                <!-- Accès -->
                <section
                    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm xl:col-span-3"
                >
                    <div class="flex items-center gap-2 border-b border-slate-100 px-6 py-4">
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Shield class="size-4" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Accès & rôles</h2>
                            <p class="text-xs text-slate-500">
                                Modules ouverts et rôles attribués à cet utilisateur
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-6 p-6 lg:grid-cols-2">
                        <div>
                            <p
                                class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-400"
                            >
                                Modules accessibles
                            </p>
                            <div
                                v-if="accessibleModuleItems.length > 0"
                                class="flex flex-wrap gap-2"
                            >
                                <span
                                    v-for="module in accessibleModuleItems"
                                    :key="module.key"
                                    class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-medium text-slate-700"
                                >
                                    {{ module.label }}
                                </span>
                            </div>
                            <p v-else class="text-sm text-slate-500">Aucun module accessible</p>
                        </div>

                        <div>
                            <p
                                class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-400"
                            >
                                Rôles
                            </p>
                            <div
                                v-if="user.roles && user.roles.length > 0"
                                class="flex flex-col gap-2"
                            >
                                <div
                                    v-for="role in user.roles"
                                    :key="role.id"
                                    class="flex items-start justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2.5"
                                >
                                    <div class="min-w-0">
                                        <span
                                            class="inline-flex rounded-md bg-primary px-2 py-0.5 text-xs font-semibold text-white"
                                        >
                                            {{ role.nom }}
                                        </span>
                                        <p
                                            v-if="role.description"
                                            class="mt-1.5 text-xs leading-relaxed text-slate-500"
                                        >
                                            {{ role.description }}
                                        </p>
                                    </div>
                                    <span
                                        v-if="role.module"
                                        class="shrink-0 text-xs text-slate-400"
                                    >
                                        {{ moduleLabel(role.module) }}
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-sm text-slate-500">Aucun rôle assigné</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
