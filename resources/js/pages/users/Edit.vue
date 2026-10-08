<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
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
    Save,
    ShieldCheck,
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
    user: {
        id: number;
        name: string;
        fonction?: string;
        email: string;
        matricule?: string | null;
        department_id?: number | null;
        agence_id?: number | null;
        n_plus_1_user_id?: number | null;
        n_plus_2_user_id?: number | null;
        roles?: Role[];
    };
    roles: Role[];
    modules: ModuleOption[];
    moduleMatrix: ModuleMatrixRow[];
    moduleAbilities?: Record<string, Record<string, boolean>>;
    moduleAbilityOptions?: Record<string, Array<{ key: string; label: string }>>;
    departments: Array<{ id: number; name: string }>;
    agences: Array<{ id: number; code: string; nom: string }>;
    supervisors: Array<{ id: number; name: string; email: string }>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Utilisateurs', href: '/users' },
    { title: props.user.name, href: '#' },
];

const fieldClass =
    'h-10 border-slate-300 bg-white text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-primary focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const selectClass =
    'flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-slate-600 dark:bg-card dark:text-foreground';

const form = useForm({
    name: props.user.name,
    fonction: props.user.fonction || '',
    email: props.user.email,
    matricule: props.user.matricule ?? '',
    password: '',
    role_ids: (props.user.roles ?? []).map((role) => role.id),
    module_abilities: props.moduleAbilities ?? {},
    department_id: props.user.department_id ?? null,
    agence_id: props.user.agence_id ?? null,
    n_plus_1_user_id: props.user.n_plus_1_user_id ?? null,
    n_plus_2_user_id: props.user.n_plus_2_user_id ?? null,
});

const showPassword = ref(false);
const localErrors = ref<Record<string, string>>({});

const fieldError = (key: 'name' | 'fonction' | 'email' | 'password' | 'matricule') =>
    localErrors.value[key] || form.errors[key];

const initials = (props.user.name || '?')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');

const generatePassword = () => {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#!?';
    const bytes = new Uint32Array(12);
    crypto.getRandomValues(bytes);

    form.password = Array.from(bytes, (value) => alphabet[value % alphabet.length]).join('');
    showPassword.value = true;
};

const submit = () => {
    const errors: Record<string, string> = {};
    if (!form.name.trim()) {
        errors.name = 'Le nom complet est obligatoire.';
    }
    if (!form.fonction.trim()) {
        errors.fonction = 'La fonction est obligatoire.';
    }
    if (!form.email.trim()) {
        errors.email = 'L’e-mail est obligatoire.';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
        errors.email = 'Saisissez un e-mail valide.';
    }
    if (form.password !== '' && form.password.length < 8) {
        errors.password = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    localErrors.value = errors;
    if (Object.keys(errors).length > 0) {
        return;
    }
    form.put(`/users/${props.user.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Modifier ${props.user.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form novalidate class="flex min-h-0 flex-1 flex-col gap-4 p-4 sm:p-6" @submit.prevent="submit">
            <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4 bg-gradient-to-r from-primary/5 via-card to-transparent px-5 py-4 sm:px-6">
                    <div class="flex items-start gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground shadow-sm">
                            {{ initials }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">Paramétrage</p>
                            <h1 class="truncate text-xl font-semibold tracking-tight text-foreground">{{ props.user.name }}</h1>
                            <p class="mt-1 truncate text-sm text-muted-foreground">{{ props.user.email }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-9 border-slate-300"
                            :disabled="form.processing"
                            @click="router.visit('/users')"
                        >
                            Annuler
                        </Button>
                        <Button type="submit" class="h-9" :disabled="form.processing">
                            <Save v-if="!form.processing" class="mr-2 size-4" />
                            {{ form.processing ? 'Mise à jour…' : 'Enregistrer' }}
                        </Button>
                    </div>
                </div>
            </section>

            <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
                <div class="flex min-w-0 flex-col gap-4">
                    <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                        <div class="flex items-start gap-3 border-b border-border/80 px-5 py-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <UserRound class="size-4" />
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-foreground">Identité</h2>
                                <p class="text-xs text-muted-foreground">Nom, fonction et identifiants.</p>
                            </div>
                        </div>

                        <div class="grid gap-3 p-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <Label for="name" class="mb-1.5 block text-sm font-medium text-foreground">
                                    Nom complet <span class="text-red-600">*</span>
                                </Label>
                                <Input id="name" v-model="form.name" type="text" autocomplete="name" :class="fieldClass" />
                                <InputError :message="fieldError('name')" />
                            </div>
                            <div class="min-w-0">
                                <Label for="fonction" class="mb-1.5 block text-sm font-medium text-foreground">
                                    Fonction <span class="text-red-600">*</span>
                                </Label>
                                <Input id="fonction" v-model="form.fonction" type="text" :class="fieldClass" placeholder="Responsable" />
                                <InputError :message="fieldError('fonction')" />
                            </div>
                            <div class="min-w-0">
                                <Label for="email" class="mb-1.5 block text-sm font-medium text-foreground">
                                    E-mail <span class="text-red-600">*</span>
                                </Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="text"
                                    inputmode="email"
                                    autocomplete="email"
                                    :class="fieldClass"
                                />
                                <InputError :message="fieldError('email')" />
                            </div>
                            <div class="min-w-0">
                                <Label for="matricule" class="mb-1.5 block text-sm font-medium text-foreground">IDFLEX</Label>
                                <Input id="matricule" v-model="form.matricule" type="text" autocomplete="off" :class="fieldClass" />
                                <InputError :message="fieldError('matricule')" />
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                        <div class="flex items-start gap-3 border-b border-border/80 px-5 py-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <KeyRound class="size-4" />
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-foreground">Mot de passe</h2>
                                <p class="text-xs text-muted-foreground">Laissez vide pour conserver l’actuel.</p>
                            </div>
                        </div>
                        <div class="p-4">
                            <Label for="password" class="mb-1.5 block text-sm font-medium text-foreground">Nouveau mot de passe</Label>
                            <div class="flex min-w-0 gap-2">
                                <div class="relative min-w-0 flex-1">
                                    <Input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
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
                                <Button type="button" variant="outline" class="h-10 shrink-0 border-slate-300" @click="generatePassword">
                                    <Wand2 class="mr-2 size-4" />
                                    Générer
                                </Button>
                            </div>
                            <InputError :message="fieldError('password')" />
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                        <div class="flex items-start gap-3 border-b border-border/80 px-5 py-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Building2 class="size-4" />
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-foreground">Affectation</h2>
                                <p class="text-xs text-muted-foreground">Entité, département et valideurs.</p>
                            </div>
                        </div>
                        <div class="grid gap-3 p-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <Label for="department_id" class="mb-1.5 block text-sm font-medium text-foreground">Département</Label>
                                <select id="department_id" v-model="form.department_id" :class="selectClass">
                                    <option :value="null">Sélectionner un département</option>
                                    <option v-for="department in props.departments" :key="department.id" :value="department.id">
                                        {{ department.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.department_id" />
                            </div>
                            <div class="min-w-0">
                                <Label for="agence_id" class="mb-1.5 block text-sm font-medium text-foreground">Agence</Label>
                                <select id="agence_id" v-model="form.agence_id" :class="selectClass">
                                    <option :value="null">Aucune / Siège</option>
                                    <option v-for="agence in props.agences" :key="agence.id" :value="agence.id">
                                        {{ agence.nom }} ({{ agence.code }})
                                    </option>
                                </select>
                                <InputError :message="form.errors.agence_id" />
                            </div>
                            <div class="min-w-0">
                                <Label for="n_plus_1_user_id" class="mb-1.5 block text-sm font-medium text-foreground">N+1</Label>
                                <select id="n_plus_1_user_id" v-model="form.n_plus_1_user_id" :class="selectClass">
                                    <option :value="null">Manager du département</option>
                                    <option v-for="supervisor in props.supervisors" :key="supervisor.id" :value="supervisor.id">
                                        {{ supervisor.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.n_plus_1_user_id" />
                            </div>
                            <div class="min-w-0">
                                <Label for="n_plus_2_user_id" class="mb-1.5 block text-sm font-medium text-foreground">N+2</Label>
                                <select id="n_plus_2_user_id" v-model="form.n_plus_2_user_id" :class="selectClass">
                                    <option :value="null">Aucun</option>
                                    <option v-for="supervisor in props.supervisors" :key="`n2-${supervisor.id}`" :value="supervisor.id">
                                        {{ supervisor.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.n_plus_2_user_id" />
                            </div>
                        </div>
                    </section>
                </div>

                <section class="min-w-0 overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm">
                    <div class="flex items-start gap-3 border-b border-border/80 px-5 py-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <ShieldCheck class="size-4" />
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Accès et rôles</h2>
                            <p class="text-xs text-muted-foreground">Plusieurs rôles possibles dans un même module.</p>
                        </div>
                    </div>
                    <div class="p-4">
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
            </div>
        </form>
    </AppLayout>
</template>
