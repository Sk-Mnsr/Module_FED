<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

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

interface AbilityOption {
    key: string;
    label: string;
}

interface ModuleMatrixRow {
    key: string;
    label: string;
    roles: string[];
    access_only?: boolean;
    abilities?: AbilityOption[];
}

type AbilityMap = Record<string, boolean>;

const props = withDefaults(
    defineProps<{
        roles: Role[];
        modules: ModuleOption[];
        moduleMatrix?: ModuleMatrixRow[];
        modelValue: number[];
        moduleAbilities?: Record<string, AbilityMap>;
        error?: string;
    }>(),
    {
        moduleMatrix: () => [],
        moduleAbilities: () => ({}),
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
    'update:moduleAbilities': [value: Record<string, AbilityMap>];
}>();

const page = usePage();

const canAssignSuperAdmin = computed(() => Boolean(page.props.auth?.isSuperAdmin));

const superAdminRole = computed(() => props.roles.find((role) => role.slug === 'it') ?? null);

const matrixByKey = computed(() => {
    const map = new Map<string, ModuleMatrixRow>();
    for (const row of props.moduleMatrix) {
        map.set(row.key, row);
    }
    return map;
});

const rolesBySlug = computed(() => {
    const map = new Map<string, Role>();
    for (const role of props.roles) {
        map.set(role.slug, role);
    }
    return map;
});

const isAccessOnly = (moduleKey: string): boolean =>
    matrixByKey.value.get(moduleKey)?.access_only === true;

const abilitiesFor = (moduleKey: string): AbilityOption[] =>
    matrixByKey.value.get(moduleKey)?.abilities ?? [];

const modulesWithRoles = computed(() => {
    if (props.moduleMatrix.length > 0) {
        return props.moduleMatrix
            .filter((row) => row.roles.length > 0 || row.access_only)
            .map((row) => ({
                key: row.key,
                label: row.label,
                accessOnly: Boolean(row.access_only),
                abilities: row.abilities ?? [],
            }));
    }

    const keys = new Set(props.roles.map((role) => role.module).filter(Boolean));

    return props.modules
        .filter((module) => keys.has(module.key))
        .map((module) => ({
            key: module.key,
            label: module.label,
            accessOnly: isAccessOnly(module.key),
            abilities: abilitiesFor(module.key),
        }));
});

const assignments = reactive<Record<string, number[]>>({});
const abilityState = reactive<Record<string, AbilityMap>>({});

const rolesByModule = (moduleKey: string): Role[] => {
    const matrix = matrixByKey.value.get(moduleKey);
    const source = matrix
        ? matrix.roles
              .map((slug) => rolesBySlug.value.get(slug))
              .filter((role): role is Role => role !== undefined)
        : props.roles.filter((role) => role.module === moduleKey);

    return source.filter(
        (role) => role.slug !== 'it' && role.slug !== 'admin' && role.module === moduleKey,
    );
};

const accessRoleForModule = (moduleKey: string): Role | null => {
    const roles = rolesByModule(moduleKey).filter((role) => role.slug !== 'it' && role.slug !== 'admin');
    return (
        roles.find((role) => role.slug === moduleKey || role.module === moduleKey) ?? roles[0] ?? null
    );
};

const selectedRoleIds = computed(() =>
    [...new Set(Object.values(assignments).flat())].sort((a, b) => a - b),
);

const selectedCount = computed(() => selectedRoleIds.value.length);

const hasItSelected = computed(() =>
    selectedRoleIds.value.some((id) => {
        const role = props.roles.find((item) => item.id === id);
        return role?.slug === 'it' || role?.slug === 'admin';
    }),
);

const defaultAbilities = (moduleKey: string): AbilityMap => {
    const map: AbilityMap = {};
    for (const ability of abilitiesFor(moduleKey)) {
        map[ability.key] = ability.key === 'view';
    }
    return map;
};

const allAbilities = (moduleKey: string): AbilityMap => {
    const map: AbilityMap = {};
    for (const ability of abilitiesFor(moduleKey)) {
        map[ability.key] = true;
    }
    return map;
};

const ensureAbilityState = (moduleKey: string) => {
    if (!abilityState[moduleKey]) {
        abilityState[moduleKey] = {
            ...defaultAbilities(moduleKey),
            ...(props.moduleAbilities?.[moduleKey] ?? {}),
        };
    }
};

const emitAbilities = () => {
    const payload: Record<string, AbilityMap> = {};
    for (const [moduleKey, abilities] of Object.entries(abilityState)) {
        if (isAccessEnabled(moduleKey) || hasItSelected.value) {
            payload[moduleKey] = { ...abilities };
        }
    }
    if (JSON.stringify(payload) !== JSON.stringify(props.moduleAbilities ?? {})) {
        emit('update:moduleAbilities', payload);
    }
};

const sameIds = (left: number[], right: number[]) => {
    if (left.length !== right.length) {
        return false;
    }
    const a = [...left].sort((x, y) => x - y);
    const b = [...right].sort((x, y) => x - y);
    return a.every((id, index) => id === b[index]);
};

const addRole = (moduleKey: string, roleId: number) => {
    const current = assignments[moduleKey] ?? [];
    if (!current.includes(roleId)) {
        assignments[moduleKey] = [...current, roleId];
    }
};

const removeRole = (moduleKey: string, roleId: number) => {
    assignments[moduleKey] = (assignments[moduleKey] ?? []).filter((id) => id !== roleId);
};

const syncFromModel = () => {
    for (const module of modulesWithRoles.value) {
        assignments[module.key] = [];
        if (module.abilities.length > 0) {
            ensureAbilityState(module.key);
            abilityState[module.key] = {
                ...defaultAbilities(module.key),
                ...(props.moduleAbilities?.[module.key] ?? {}),
            };
        }
    }

    for (const roleId of props.modelValue) {
        const role = props.roles.find((item) => item.id === roleId);
        if (!role) {
            continue;
        }

        if (role.slug === 'it' || role.slug === 'admin') {
            for (const module of modulesWithRoles.value) {
                if (!module.accessOnly && module.key in assignments) {
                    assignments[module.key] = [roleId];
                }
                if (module.abilities.length > 0) {
                    abilityState[module.key] = allAbilities(module.key);
                }
            }
            continue;
        }

        const moduleKey = role.module;
        if (!moduleKey || !(moduleKey in assignments)) {
            continue;
        }
        if (isAccessOnly(moduleKey) && role.slug !== moduleKey) {
            continue;
        }
        addRole(moduleKey, roleId);
        if (abilitiesFor(moduleKey).length > 0) {
            ensureAbilityState(moduleKey);
            abilityState[moduleKey] = {
                ...defaultAbilities(moduleKey),
                ...(props.moduleAbilities?.[moduleKey] ?? {}),
            };
        }
    }
};

watch(
    () => [props.modelValue, props.roles, props.modules, props.moduleMatrix, props.moduleAbilities],
    syncFromModel,
    { immediate: true, deep: true },
);

watch(
    assignments,
    () => {
        const ids = selectedRoleIds.value;
        if (!sameIds(ids, props.modelValue)) {
            emit('update:modelValue', ids);
        }
        emitAbilities();
    },
    { deep: true },
);

watch(
    abilityState,
    () => emitAbilities(),
    { deep: true },
);

const isRoleChecked = (moduleKey: string, roleId: number) =>
    (assignments[moduleKey] ?? []).includes(roleId);

const toggleRole = (moduleKey: string, roleId: number, enabled: boolean) => {
    if (enabled) {
        addRole(moduleKey, roleId);
        return;
    }

    removeRole(moduleKey, roleId);
};

const toggleAccessOnly = (moduleKey: string, enabled: boolean) => {
    if (!enabled) {
        assignments[moduleKey] = [];
        if (abilitiesFor(moduleKey).length > 0) {
            abilityState[moduleKey] = defaultAbilities(moduleKey);
        }
        return;
    }

    const role = accessRoleForModule(moduleKey);
    assignments[moduleKey] = role ? [role.id] : [];
    if (abilitiesFor(moduleKey).length > 0) {
        abilityState[moduleKey] = {
            ...defaultAbilities(moduleKey),
            view: true,
        };
    }
};

const isAccessEnabled = (moduleKey: string): boolean => {
    if (hasItSelected.value) {
        return true;
    }
    return (assignments[moduleKey]?.length ?? 0) > 0;
};

const setAbility = (moduleKey: string, abilityKey: string, enabled: boolean) => {
    ensureAbilityState(moduleKey);
    abilityState[moduleKey][abilityKey] = enabled;

    if (enabled && abilityKey !== 'view') {
        abilityState[moduleKey].view = true;
    }

    if (abilityKey === 'view' && !enabled) {
        for (const key of Object.keys(abilityState[moduleKey])) {
            abilityState[moduleKey][key] = false;
        }
    }
};

const toggleSuperAdmin = (enabled: boolean) => {
    if (!superAdminRole.value) {
        return;
    }

    if (enabled) {
        emit('update:modelValue', [superAdminRole.value.id]);
        return;
    }

    emit('update:modelValue', []);
};

const descriptionForModule = (moduleKey: string) => {
    if (isAccessOnly(moduleKey)) {
        if (hasItSelected.value) {
            return 'Inclus automatiquement pour le rôle SuperAdmin / IT (tous les droits).';
        }
        if (moduleKey === 'administration') {
            return 'Autorise la gestion système (utilisateurs, rôles, départements…) sans ouvrir les modules métier.';
        }
        if (abilitiesFor(moduleKey).length > 0) {
            return 'Cochez l’accès puis les droits souhaités.';
        }
        return accessRoleForModule(moduleKey)?.description
            ?? 'Cochez pour autoriser l’accès à ce module.';
    }

    return null;
};
</script>

<template>
    <div class="grid gap-4">
        <p v-if="!hasItSelected" class="text-sm text-gray-600">
            Cochez un ou plusieurs rôles par module. Caissier et Chargé clientèle peuvent être cumulés. Pour Budget, cochez l’accès puis les droits.
        </p>

        <div
            v-if="superAdminRole && (canAssignSuperAdmin || hasItSelected)"
            class="rounded-lg border border-amber-200 bg-amber-50 p-4"
        >
            <label
                for="role-superadmin"
                class="flex cursor-pointer items-start gap-3"
                :class="{ 'cursor-default': !canAssignSuperAdmin }"
            >
                <input
                    id="role-superadmin"
                    type="checkbox"
                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                    :checked="hasItSelected"
                    :disabled="!canAssignSuperAdmin"
                    @change="toggleSuperAdmin(($event.target as HTMLInputElement).checked)"
                />
                <span class="text-sm text-gray-800">
                    <span class="font-medium">{{ superAdminRole.nom }} (IT)</span>
                    <span class="mt-0.5 block text-xs text-gray-600">
                        {{
                            superAdminRole.description
                                ?? 'Accès complet à tous les modules métier et à l’administration système.'
                        }}
                    </span>
                    <span
                        v-if="!canAssignSuperAdmin && hasItSelected"
                        class="mt-1 block text-xs text-amber-800"
                    >
                        Seul un SuperAdmin peut modifier ce profil.
                    </span>
                </span>
            </label>
        </div>

        <p v-if="hasItSelected" class="text-sm text-gray-600">
            Tous les modules sont activés automatiquement. Décochez SuperAdmin pour attribuer des rôles module par module.
        </p>

        <div
            v-for="module in modulesWithRoles"
            v-show="!hasItSelected"
            :key="module.key"
            class="rounded-lg border border-gray-200 p-4"
        >
            <Label :for="`role-${module.key}`" class="text-base font-medium text-gray-700">
                {{ module.label }}
            </Label>

            <template v-if="module.accessOnly">
                <label
                    :for="`role-${module.key}`"
                    class="mt-3 flex cursor-pointer items-start gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5"
                    :class="{ 'opacity-70': hasItSelected }"
                >
                    <input
                        :id="`role-${module.key}`"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                        :checked="isAccessEnabled(module.key)"
                        :disabled="hasItSelected || !accessRoleForModule(module.key)"
                        @change="
                            toggleAccessOnly(
                                module.key,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    <span class="text-sm text-gray-800">
                        <span class="font-medium">Autoriser l’accès</span>
                        <span class="mt-0.5 block text-xs text-gray-500">
                            Active le module pour cet utilisateur.
                        </span>
                    </span>
                </label>

                <div
                    v-if="module.abilities.length > 0 && isAccessEnabled(module.key)"
                    class="mt-3 grid gap-2 rounded-md border border-dashed border-gray-200 bg-white p-3 sm:grid-cols-2"
                >
                    <p class="sm:col-span-2 text-xs font-medium uppercase tracking-wide text-gray-500">
                        Droits
                    </p>
                    <label
                        v-for="ability in module.abilities"
                        :key="`${module.key}-${ability.key}`"
                        class="flex items-center gap-2 text-sm text-gray-800"
                        :class="{ 'opacity-70': hasItSelected }"
                    >
                        <input
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                            :checked="Boolean(abilityState[module.key]?.[ability.key])"
                            :disabled="hasItSelected"
                            @change="
                                setAbility(
                                    module.key,
                                    ability.key,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        {{ ability.label }}
                    </label>
                </div>
            </template>

            <div v-else class="mt-3 grid gap-2 sm:grid-cols-2">
                <label
                    v-for="role in rolesByModule(module.key)"
                    :key="role.id"
                    class="flex cursor-pointer items-start gap-2.5 rounded-md border border-gray-200 bg-gray-50 px-3 py-2"
                    :class="isRoleChecked(module.key, role.id) ? 'border-primary/40 bg-primary/5' : ''"
                >
                    <input
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                        :checked="isRoleChecked(module.key, role.id)"
                        @change="toggleRole(module.key, role.id, ($event.target as HTMLInputElement).checked)"
                    />
                    <span class="min-w-0 text-sm text-gray-800">
                        <span class="font-medium">{{ role.nom }}</span>
                        <span v-if="role.description" class="mt-0.5 block text-xs text-gray-500">
                            {{ role.description }}
                        </span>
                    </span>
                </label>
            </div>

            <p v-if="descriptionForModule(module.key)" class="mt-1 text-xs text-gray-500">
                {{ descriptionForModule(module.key) }}
            </p>
        </div>

        <p v-if="selectedCount === 0 && !hasItSelected" class="text-sm text-amber-700">
            Sélectionnez au moins un rôle pour donner accès à l'application.
        </p>
        <p v-else-if="hasItSelected" class="text-xs text-gray-500">
            Profil SuperAdmin — accès total à l’application.
        </p>
        <p v-else class="text-xs text-gray-500">
            {{ selectedCount }} rôle{{ selectedCount > 1 ? 's' : '' }} attribué{{ selectedCount > 1 ? 's' : '' }}.
        </p>

        <p v-if="roles.length === 0" class="text-sm text-gray-500">
            Aucun rôle disponible. Veuillez contacter un administrateur.
        </p>
        <InputError :message="error" />
    </div>
</template>
