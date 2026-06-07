<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import RoleModuleSelect from '@/components/RoleModuleSelect.vue';
import { Code } from 'lucide-vue-next';

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
    departments: Array<{ id: number; name: string }>;
    agences: Array<{ id: number; code: string; nom: string }>;
    supervisors: Array<{ id: number; name: string; email: string }>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Utilisateurs',
        href: '/users',
    },
    {
        title: 'Modifier l\'utilisateur',
        href: '#',
    },
];

const form = useForm({
    name: props.user.name,
    fonction: props.user.fonction || '',
    email: props.user.email,
    matricule: props.user.matricule ?? '',
    password: '',
    password_confirmation: '',
    role_ids: (props.user.roles ?? []).map((role) => role.id),
    department_id: props.user.department_id ?? null,
    agence_id: props.user.agence_id ?? null,
    n_plus_1_user_id: props.user.n_plus_1_user_id ?? null,
    n_plus_2_user_id: props.user.n_plus_2_user_id ?? null,
});

const submit = () => {
    form.put(`/users/${props.user.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Modifier l'utilisateur" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <h1 class="text-3xl font-bold text-gray-900">Modifier l'utilisateur</h1>
                <Code class="h-5 w-5 text-gray-500" />
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection :columns="2">
                    <div>
                        <Label for="name" class="text-base font-medium text-gray-700">First Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            placeholder="John"
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    
                    <div>
                        <Label for="fonction" class="text-base font-medium text-gray-700">Fonction</Label>
                        <Input
                            id="fonction"
                            v-model="form.fonction"
                            type="text"
                            required
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            placeholder="Responsable"
                        />
                        <InputError :message="form.errors.fonction" />
                    </div>

                    <div>
                        <Label for="email" class="text-base font-medium text-gray-700">Email</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            placeholder="johndoe@email.com"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div>
                        <Label for="matricule" class="text-base font-medium text-gray-700">IDFLEX</Label>
                        <Input
                            id="matricule"
                            v-model="form.matricule"
                            type="text"
                            autocomplete="off"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            placeholder="Identifiant Flexcube"
                        />
                        <InputError :message="form.errors.matricule" />
                    </div>
                </FormSection>

                <FormSection title="Changer le mot de passe" :columns="2" :show-code-icon="false">
                    <p class="col-span-2 mb-4 text-sm text-gray-600">
                        Laissez ces champs vides si vous ne souhaitez pas modifier le mot de passe.
                    </p>
                    <div>
                        <Label for="password" class="text-base font-medium text-gray-700">Nouveau mot de passe</Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            placeholder="Minimum 8 caractères"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div>
                        <Label for="password_confirmation" class="text-base font-medium text-gray-700">Confirmer le mot de passe</Label>
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            placeholder="Répétez le mot de passe"
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>
                </FormSection>

                <FormSection title="Accès & rôle" :columns="1">
                    <RoleModuleSelect
                        v-model="form.role_ids"
                        :roles="props.roles"
                        :modules="props.modules"
                        :error="form.errors.role_ids"
                    />
                </FormSection>

                <FormSection title="Département & entité" :columns="1" :show-code-icon="false">
                    <div>
                        <Label for="department_id" class="text-base font-medium text-gray-700">Département</Label>
                        <select
                            id="department_id"
                            v-model="form.department_id"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
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
                        <Label for="agence_id" class="text-base font-medium text-gray-700">Agence (entité)</Label>
                        <select
                            id="agence_id"
                            v-model="form.agence_id"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option :value="null">Aucune / Siège</option>
                            <option
                                v-for="agence in props.agences"
                                :key="agence.id"
                                :value="agence.id"
                            >
                                {{ agence.nom }} ({{ agence.code }})
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Utile pour le module Monétique (stock par agence).</p>
                        <InputError :message="form.errors.agence_id" />
                    </div>
                </FormSection>

                <FormSection title="Hiérarchie" :columns="1" :show-code-icon="false">
                    <div>
                        <Label for="n_plus_1_user_id" class="text-base font-medium text-gray-700">N+1</Label>
                        <select
                            id="n_plus_1_user_id"
                            v-model="form.n_plus_1_user_id"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option :value="null">Aucun (manager du département par défaut)</option>
                            <option v-for="supervisor in props.supervisors" :key="supervisor.id" :value="supervisor.id">
                                {{ supervisor.name }} ({{ supervisor.email }})
                            </option>
                        </select>
                        <InputError :message="form.errors.n_plus_1_user_id" />
                    </div>
                    <div>
                        <Label for="n_plus_2_user_id" class="text-base font-medium text-gray-700">N+2</Label>
                        <select
                            id="n_plus_2_user_id"
                            v-model="form.n_plus_2_user_id"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option :value="null">Aucun</option>
                            <option v-for="supervisor in props.supervisors" :key="`n2-${supervisor.id}`" :value="supervisor.id">
                                {{ supervisor.name }} ({{ supervisor.email }})
                            </option>
                        </select>
                        <InputError :message="form.errors.n_plus_2_user_id" />
                    </div>
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/users')">
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Mise à jour...' : 'Mettre à jour' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

