<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';

interface Role {
    id: number;
    nom: string;
    slug: string;
    module?: string | null;
}

interface ModuleOption {
    key: string;
    label: string;
}

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
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
        n_plus1?: { id: number; name: string; email: string } | null;
        n_plus2?: { id: number; name: string; email: string } | null;
    };
    modules: ModuleOption[];
    accessibleModules: string[];
}

const props = defineProps<Props>();

const moduleLabels = computed(() =>
    props.accessibleModules
        .map((key) => props.modules.find((module) => module.key === key)?.label ?? key)
        .join(', '),
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Utilisateurs',
        href: '/users',
    },
    {
        title: props.user.name,
        href: '#',
    },
];
</script>

<template>
    <Head :title="user.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ user.name }}</h1>
                <div class="flex gap-2">
                    <Link :href="`/users/${user.id}/edit`">
                        <Button variant="outline">Modifier</Button>
                    </Link>
                    <Link href="/users">
                        <Button>Retour à la liste</Button>
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Informations de l'utilisateur</h2>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Nom complet</dt>
                            <dd class="mt-1 text-sm">{{ user.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Email</dt>
                            <dd class="mt-1 text-sm">{{ user.email }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">IDFLEX</dt>
                            <dd class="mt-1 text-sm">{{ user.matricule || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Département</dt>
                            <dd class="mt-1 text-sm">
                                {{ user.department?.name || '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Agence (entité)</dt>
                            <dd class="mt-1 text-sm">
                                <template v-if="user.agence">{{ user.agence.nom }} ({{ user.agence.code }})</template>
                                <template v-else>—</template>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Date de création</dt>
                            <dd class="mt-1 text-sm">
                                {{ new Date(user.created_at).toLocaleDateString('fr-FR', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                }) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Dernière modification</dt>
                            <dd class="mt-1 text-sm">
                                {{ new Date(user.updated_at).toLocaleDateString('fr-FR', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                }) }}
                            </dd>
                        </div>
                        <div v-if="accessibleModules.length > 0">
                            <dt class="text-muted-foreground text-sm font-medium">Modules accessibles</dt>
                            <dd class="mt-1 text-sm">{{ moduleLabels }}</dd>
                        </div>
                        <div v-if="user.roles && user.roles.length > 0">
                            <dt class="text-muted-foreground text-sm font-medium">Rôles</dt>
                            <dd class="mt-1">
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="rounded-full bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
                                    >
                                        {{ role.nom }}
                                    </span>
                                </div>
                            </dd>
                        </div>
                        <div v-else>
                            <dt class="text-muted-foreground text-sm font-medium">Rôles</dt>
                            <dd class="mt-1 text-sm text-muted-foreground">Aucun rôle assigné</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Hiérarchie</h2>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">N+1</dt>
                            <dd class="mt-1 text-sm">
                                <template v-if="user.n_plus1">{{ user.n_plus1.name }} ({{ user.n_plus1.email }})</template>
                                <template v-else>Manager du département ou non défini</template>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">N+2</dt>
                            <dd class="mt-1 text-sm">
                                <template v-if="user.n_plus2">{{ user.n_plus2.name }} ({{ user.n_plus2.email }})</template>
                                <template v-else>—</template>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

