<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import RoleModuleKeysField from '@/components/RoleModuleKeysField.vue';
import { watch } from 'vue';

interface ModuleOption {
    key: string;
    label: string;
}

interface Props {
    modules: ModuleOption[];
    accessProfiles: Array<{ value: string; label: string }>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rôles', href: '/roles' },
    { title: 'Créer', href: '#' },
];

const selectClass =
    'mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900';

const form = useForm({
    nom: '',
    slug: '',
    module: '' as string,
    module_keys: [] as string[],
    access_profile: 'other' as string,
    description: '',
    actif: true,
});

watch(
    () => form.module,
    (moduleKey) => {
        if (moduleKey && !form.module_keys.includes(moduleKey)) {
            form.module_keys = [...form.module_keys, moduleKey].sort();
        }
    },
);

const submit = () => {
    form.post('/roles', { preserveScroll: true });
};
</script>

<template>
    <Head title="Créer un rôle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-3xl font-bold text-gray-900">Créer un rôle</h1>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <FormSection :columns="2">
                    <div>
                        <Label for="nom">Nom *</Label>
                        <Input id="nom" v-model="form.nom" required class="mt-1.5" placeholder="Responsable Achats" />
                        <InputError :message="form.errors.nom" />
                    </div>
                    <div>
                        <Label for="slug">Slug *</Label>
                        <Input id="slug" v-model="form.slug" required class="mt-1.5" placeholder="responsable_achats" />
                        <p class="mt-1 text-xs text-gray-500">Minuscules, chiffres et underscores uniquement.</p>
                        <InputError :message="form.errors.slug" />
                    </div>
                    <div>
                        <Label for="module">Module principal *</Label>
                        <select id="module" v-model="form.module" required :class="selectClass">
                            <option value="">Sélectionner…</option>
                            <option v-for="module in props.modules" :key="module.key" :value="module.key">
                                {{ module.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.module" />
                    </div>
                    <div>
                        <Label for="access_profile">Profil d'accès *</Label>
                        <select id="access_profile" v-model="form.access_profile" required :class="selectClass">
                            <option v-for="profile in props.accessProfiles" :key="profile.value" :value="profile.value">
                                {{ profile.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.access_profile" />
                    </div>

                    <RoleModuleKeysField
                        v-model="form.module_keys"
                        :modules="props.modules"
                        :primary-module="form.module"
                        :error="form.errors.module_keys"
                    />

                    <div class="col-span-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1.5 w-full rounded-md border border-gray-300 px-3 py-2 text-base"
                            placeholder="Description du rôle…"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div class="col-span-2 flex items-center gap-2">
                        <input id="actif" v-model="form.actif" type="checkbox" class="rounded border-gray-300" />
                        <Label for="actif">Rôle actif (proposé à l'assignation)</Label>
                    </div>
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/roles')">Annuler</Button>
                    <Button type="submit" :disabled="form.processing || form.module_keys.length === 0">
                        {{ form.processing ? 'Création…' : 'Créer' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
