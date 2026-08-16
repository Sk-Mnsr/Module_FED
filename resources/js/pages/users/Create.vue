<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import RoleModuleSelect from '@/components/RoleModuleSelect.vue';
import { ref } from 'vue';
import {
    Building2,
    Eye,
    EyeOff,
    KeyRound,
    Network,
    ShieldCheck,
    UserPlus,
    UserRound,
    Wand2,
} from 'lucide-vue-next';

interface Role {
    id: number;
    nom: string;
    slug: string;
    module: string | null;
    description?: string | null;
}

interface ModuleOption {
    key: string;
    label: string;
}

interface ModuleMatrixRow {
    key: string;
    label: string;
    roles: string[];
    access_only?: boolean;
    abilities?: Array<{ key: string; label: string }>;
}

interface Props {
    roles: Role[];
    modules: ModuleOption[];
    moduleMatrix: ModuleMatrixRow[];
    moduleAbilityOptions?: Record<string, Array<{ key: string; label: string }>>;
    departments: Array<{ id: number; name: string }>;
    agences: Array<{ id: number; code: string; nom: string }>;
    supervisors: Array<{ id: number; name: string; email: string }>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Utilisateurs', href: '/users' },
    { title: 'Nouvel utilisateur', href: '#' },
];

const fieldClass =
    'h-10 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const selectClass =
    'flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const form = useForm({
    name: '',
    fonction: '',
    email: '',
    matricule: '',
    password: '',
    role_ids: [] as number[],
    module_abilities: {} as Record<string, Record<string, boolean>>,
    department_id: null as number | null,
    agence_id: null as number | null,
    n_plus_1_user_id: null as number | null,
    n_plus_2_user_id: null as number | null,
});

const showPassword = ref(false);

// Alphabet sans caractères ambigus (O/0, l/1) : le mot de passe est communiqué oralement.
const generatePassword = () => {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#!?';
    const bytes = new Uint32Array(12);
    crypto.getRandomValues(bytes);

    form.password = Array.from(bytes, (value) => alphabet[value % alphabet.length]).join('');
    showPassword.value = true;
};

const submit = () => {
    form.post('/users', { preserveScroll: true });
};
</script>

<template>
    <Head title="Nouvel utilisateur" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex min-h-0 flex-1 flex-col gap-4 p-4 sm:p-6" @submit.prevent="submit">
            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div
                    class="border-b border-border/80 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-5 sm:px-6 dark:from-primary/10"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
                        >
                            <UserPlus class="size-5" />
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                                Paramétrage
                            </p>
                            <h1 class="text-xl font-semibold tracking-tight text-foreground">
                                Nouvel utilisateur
                            </h1>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Renseignez l’identité, définissez les accès par module, puis rattachez
                                l’utilisateur à son entité.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div class="flex items-start gap-3 border-b border-border/80 px-5 py-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <UserRound class="size-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Identité</h2>
                        <p class="text-xs text-muted-foreground">Nom, fonction et identifiants de connexion.</p>
                    </div>
                </div>

                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div>
                        <Label for="name" class="mb-1.5 block text-sm font-medium text-foreground">
                            Nom complet <span class="text-red-600">*</span>
                        </Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            :class="fieldClass"
                            placeholder="Mansour Seck"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div>
                        <Label for="fonction" class="mb-1.5 block text-sm font-medium text-foreground">
                            Fonction <span class="text-red-600">*</span>
                        </Label>
                        <Input
                            id="fonction"
                            v-model="form.fonction"
                            type="text"
                            required
                            :class="fieldClass"
                            placeholder="Responsable"
                        />
                        <InputError :message="form.errors.fonction" />
                    </div>

                    <div>
                        <Label for="email" class="mb-1.5 block text-sm font-medium text-foreground">
                            Email <span class="text-red-600">*</span>
                        </Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            :class="fieldClass"
                            placeholder="prenom.nom@cofina.sn"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div>
                        <Label for="matricule" class="mb-1.5 block text-sm font-medium text-foreground">
                            IDFLEX
                        </Label>
                        <Input
                            id="matricule"
                            v-model="form.matricule"
                            type="text"
                            autocomplete="off"
                            :class="fieldClass"
                            placeholder="Identifiant Flexcube"
                        />
                        <InputError :message="form.errors.matricule" />
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div class="flex items-start gap-3 border-b border-border/80 px-5 py-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <KeyRound class="size-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Mot de passe temporaire</h2>
                        <p class="text-xs text-muted-foreground">
                            L’utilisateur devra le changer lors de sa première connexion.
                        </p>
                    </div>
                </div>

                <div class="p-5">
                    <div class="max-w-md">
                        <Label for="password" class="mb-1.5 block text-sm font-medium text-foreground">
                            Mot de passe <span class="text-red-600">*</span>
                        </Label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="new-password"
                                    :class="[fieldClass, 'pr-10']"
                                    placeholder="Minimum 8 caractères"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-500 transition-colors hover:text-primary"
                                    :title="showPassword ? 'Masquer' : 'Afficher'"
                                    @click="showPassword = !showPassword"
                                >
                                    <component :is="showPassword ? EyeOff : Eye" class="size-4" />
                                </button>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-10 shrink-0 border-slate-300"
                                @click="generatePassword"
                            >
                                <Wand2 class="mr-2 size-4" />
                                Générer
                            </Button>
                        </div>
                        <InputError :message="form.errors.password" />
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div class="flex items-start gap-3 border-b border-border/80 px-5 py-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <ShieldCheck class="size-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Accès &amp; rôles</h2>
                        <p class="text-xs text-muted-foreground">Un rôle par module métier.</p>
                    </div>
                </div>

                <div class="p-5">
                    <RoleModuleSelect
                        v-model="form.role_ids"
                        v-model:module-abilities="form.module_abilities"
                        :roles="props.roles"
                        :modules="props.modules"
                        :module-matrix="props.moduleMatrix"
                        :error="form.errors.role_ids"
                    />
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div class="flex items-start gap-3 border-b border-border/80 px-5 py-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Building2 class="size-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Rattachement</h2>
                        <p class="text-xs text-muted-foreground">Département et entité d’affectation.</p>
                    </div>
                </div>

                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div>
                        <Label for="department_id" class="mb-1.5 block text-sm font-medium text-foreground">
                            Département
                        </Label>
                        <select id="department_id" v-model="form.department_id" :class="selectClass">
                            <option :value="null">Sélectionner un département</option>
                            <option
                                v-for="department in props.departments"
                                :key="department.id"
                                :value="department.id"
                            >
                                {{ department.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.department_id" />
                    </div>

                    <div>
                        <Label for="agence_id" class="mb-1.5 block text-sm font-medium text-foreground">
                            Agence (entité)
                        </Label>
                        <select id="agence_id" v-model="form.agence_id" :class="selectClass">
                            <option :value="null">Aucune / Siège</option>
                            <option v-for="agence in props.agences" :key="agence.id" :value="agence.id">
                                {{ agence.nom }} ({{ agence.code }})
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Utilisé par le module Monétique pour le stock par agence.
                        </p>
                        <InputError :message="form.errors.agence_id" />
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div class="flex items-start gap-3 border-b border-border/80 px-5 py-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Network class="size-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Hiérarchie</h2>
                        <p class="text-xs text-muted-foreground">Valideurs pour les circuits d’approbation.</p>
                    </div>
                </div>

                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div>
                        <Label for="n_plus_1_user_id" class="mb-1.5 block text-sm font-medium text-foreground">
                            N+1
                        </Label>
                        <select id="n_plus_1_user_id" v-model="form.n_plus_1_user_id" :class="selectClass">
                            <option :value="null">Manager du département par défaut</option>
                            <option
                                v-for="supervisor in props.supervisors"
                                :key="supervisor.id"
                                :value="supervisor.id"
                            >
                                {{ supervisor.name }} ({{ supervisor.email }})
                            </option>
                        </select>
                        <InputError :message="form.errors.n_plus_1_user_id" />
                    </div>

                    <div>
                        <Label for="n_plus_2_user_id" class="mb-1.5 block text-sm font-medium text-foreground">
                            N+2
                        </Label>
                        <select id="n_plus_2_user_id" v-model="form.n_plus_2_user_id" :class="selectClass">
                            <option :value="null">Aucun</option>
                            <option
                                v-for="supervisor in props.supervisors"
                                :key="`n2-${supervisor.id}`"
                                :value="supervisor.id"
                            >
                                {{ supervisor.name }} ({{ supervisor.email }})
                            </option>
                        </select>
                        <InputError :message="form.errors.n_plus_2_user_id" />
                    </div>
                </div>
            </section>

            <div
                class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border/80 bg-background/85 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="border-slate-300"
                    :disabled="form.processing"
                    @click="$inertia.visit('/users')"
                >
                    Annuler
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <UserPlus v-if="!form.processing" class="mr-2 size-4" />
                    {{ form.processing ? 'Création…' : 'Créer l’utilisateur' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
