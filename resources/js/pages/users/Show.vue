<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        created_at: string;
        updated_at: string;
        roles?: {
            id: number;
            nom: string;
            slug: string;
        }[];
        agence?: {
            id: number;
            code: string;
            nom: string;
        } | null;
        matricule?: string | null;
        department_id?: number | null;
        department?: { id: number; name: string } | null;
        profil?: {
            id: number;
            nom: string;
            prenom: string;
            matricule: string;
            fonction?: string;
            departement?: string;
            email?: string;
            telephone?: string;
        };
    };
}

const props = defineProps<Props>();

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
                            <dd class="mt-1 text-sm">{{ user.matricule || user.profil?.matricule || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Département</dt>
                            <dd class="mt-1 text-sm">
                                {{ user.department?.name || user.profil?.departement || '—' }}
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

                <div v-if="user.profil" class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Annuaire RH (synchronisé)</h2>
                    <p class="mb-3 text-muted-foreground text-xs">
                        Copie des champs utilisateur pour les modules (FED, budgets, N+1).
                    </p>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-muted-foreground text-sm font-medium">Nom annuaire</dt>
                            <dd class="mt-1 text-sm">
                                {{ user.profil.prenom }} {{ user.profil.nom }}
                            </dd>
                        </div>
                        <div v-if="user.profil.fonction">
                            <dt class="text-muted-foreground text-sm font-medium">Fonction</dt>
                            <dd class="mt-1 text-sm">{{ user.profil.fonction }}</dd>
                        </div>
                        <div v-if="user.profil.telephone">
                            <dt class="text-muted-foreground text-sm font-medium">Téléphone</dt>
                            <dd class="mt-1 text-sm">{{ user.profil.telephone }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-else class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Annuaire RH</h2>
                    <p class="text-muted-foreground text-sm">
                        Aucune fiche annuaire pour l’instant. Elle est créée ou mise à jour lors de l’enregistrement de l’utilisateur.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

