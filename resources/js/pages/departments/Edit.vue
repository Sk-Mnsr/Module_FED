<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';

interface Profile {
    id: number;
    prenom?: string | null;
    nom?: string | null;
    email?: string | null;
}

interface Props {
    department: {
        id: number;
        name: string;
        code: string;
        manager_profile_id?: number | null;
    };
    profiles: Profile[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Départements', href: '/departments' },
    { title: 'Modifier', href: '#' },
];

const form = useForm({
    name: props.department.name,
    code: props.department.code,
    manager_profile_id: props.department.manager_profile_id ?? null,
});

const submit = () => {
    form.put(`/departments/${props.department.id}`, { preserveScroll: true });
};

const formatProfile = (profile: Profile) => {
    const name = `${profile.prenom ?? ''} ${profile.nom ?? ''}`.trim();
    return name || profile.email || `Profil #${profile.id}`;
};
</script>

<template>
    <Head title="Modifier un département" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-3xl font-bold text-gray-900">Modifier un département</h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection :columns="2">
                    <div>
                        <Label for="name" class="text-base font-medium text-gray-700">Nom du département</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            class="mt-1.5 border-gray-300"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div>
                        <Label for="code" class="text-base font-medium text-gray-700">Code du département</Label>
                        <Input
                            id="code"
                            v-model="form.code"
                            type="text"
                            required
                            class="mt-1.5 border-gray-300"
                        />
                        <InputError :message="form.errors.code" />
                    </div>

                    <div>
                        <Label for="manager_profile_id" class="text-base font-medium text-gray-700">N+1 du département</Label>
                        <select
                            id="manager_profile_id"
                            v-model="form.manager_profile_id"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900"
                        >
                            <option :value="null">-- Aucun --</option>
                            <option v-for="profile in props.profiles" :key="profile.id" :value="profile.id">
                                {{ formatProfile(profile) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.manager_profile_id" />
                    </div>
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/departments')">
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
