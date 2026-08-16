<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
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

const selectClass =
    'mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400';

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

const assignments = reactive<Record<string, number | null>>({});
const abilityState = reactive<Record<string, AbilityMap>>({});

const rolesByModule = (moduleKey: string): Role[] => {
    const matrix = matrixByKey.value.get(moduleKey);
    if (matrix) {
        return matrix.roles
            .map((slug) => rolesBySlug.value.get(slug))
            .filter((role): role is Role => role !== undefined);
    }

    return props.roles.filter((role) => role.module === moduleKey);
};

const accessRoleForModule = (moduleKey: string): Role | null => {
    const roles = rolesByModule(moduleKey).filter((role) => role.slug !== 'it' && role.slug !== 'admin');
    return (
        roles.find((role) => role.slug === moduleKey || role.module === moduleKey) ?? roles[0] ?? null
    );
};

const modulesCoveredByRole = (role: Role): string[] => {
    const keys: string[] = [];
    for (const row of props.moduleMatrix) {
        if (row.roles.includes(role.slug)) {
            keys.push(row.key);
        }
    }
    if (keys.length > 0) {
        return keys;
    }
    return role.module ? [role.module] : [];
};

const selectedRoleIds = computed(() =>
    [...new Set(Object.values(assignments).filter((id): id is number => id !== null))],
);

const selectedCount = computed(
    () => Object.values(assignments).filter((id) => id !== null).length,
);

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

const syncFromModel = () => {
    for (const module of modulesWithRoles.value) {
        assignments[module.key] = null;
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
                    assignments[module.key] = roleId;
                }
                if (module.abilities.length > 0) {
                    abilityState[module.key] = allAbilities(module.key);
                }
            }
            continue;
        }

        for (const moduleKey of modulesCoveredByRole(role)) {
            if (!(moduleKey in assignments)) {
                continue;
            }
            if (isAccessOnly(moduleKey) && role.slug !== moduleKey && role.module !== moduleKey) {
                continue;
            }
            assignments[moduleKey] = roleId;
            if (abilitiesFor(moduleKey).length > 0) {
                ensureAbilityState(moduleKey);
                abilityState[moduleKey] = {
                    ...defaultAbilities(moduleKey),
                    ...(props.moduleAbilities?.[moduleKey] ?? {}),
                };
            }
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
        if (JSON.stringify(ids) !== JSON.stringify(props.modelValue)) {
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

const onModuleRoleChange = (moduleKey: string, roleId: number | null) => {
    const previousId = assignments[moduleKey] ?? null;

    if (roleId === null) {
        if (previousId !== null) {
            const previousRole = props.roles.find((item) => item.id === previousId);
            const keysToClear = previousRole
                ? modulesCoveredByRole(previousRole).filter(
                      (key) => !isAccessOnly(key) || previousRole.slug === key || previousRole.module === key,
                  )
                : [moduleKey];

            for (const key of keysToClear) {
                if (assignments[key] === previousId) {
                    assignments[key] = null;
                }
            }
        } else {
            assignments[moduleKey] = null;
        }
        return;
    }

    const role = props.roles.find((item) => item.id === roleId);
    if (!role) {
        assignments[moduleKey] = roleId;
        return;
    }

    for (const key of modulesCoveredByRole(role)) {
        if (!(key in assignments)) {
            continue;
        }
        if (isAccessOnly(key) && role.slug !== key && role.module !== key) {
            continue;
        }
        assignments[key] = roleId;
    }
};

const toggleAccessOnly = (moduleKey: string, enabled: boolean) => {
    if (!enabled) {
        assignments[moduleKey] = null;
        if (abilitiesFor(moduleKey).length > 0) {
            abilityState[moduleKey] = defaultAbilities(moduleKey);
        }
        return;
    }

    const role = accessRoleForModule(moduleKey);
    assignments[moduleKey] = role?.id ?? null;
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
    return assignments[moduleKey] !== null && assignments[moduleKey] !== undefined;
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

const descriptionForModule = (moduleKey: string) => {
    if (isAccessOnly(moduleKey)) {
        if (hasItSelected.value) {
            return 'Inclus automatiquement pour le rôle IT / administrateur (tous les droits).';
        }
        if (abilitiesFor(moduleKey).length > 0) {
            return 'Cochez l’accès puis les droits souhaités.';
        }
        return accessRoleForModule(moduleKey)?.description
            ?? 'Cochez pour autoriser l’accès à ce module.';
    }

    const roleId = assignments[moduleKey];
    if (!roleId) {
        return null;
    }

    return props.roles.find((role) => role.id === roleId)?.description ?? null;
};
</script>

<template>
    <div class="grid gap-4">
        <p class="text-sm text-gray-600">
            Attribuez un rôle par module. Pour Budget, cochez l’accès puis les droits (consultation, ajouter…).
        </p>

        <div
            v-for="module in modulesWithRoles"
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

            <select
                v-else
                :id="`role-${module.key}`"
                :value="assignments[module.key] ?? ''"
                :class="selectClass"
                @change="
                    onModuleRoleChange(
                        module.key,
                        ($event.target as HTMLSelectElement).value === ''
                            ? null
                            : Number(($event.target as HTMLSelectElement).value),
                    )
                "
            >
                <option value="">Aucun accès</option>
                <option
                    v-for="role in rolesByModule(module.key)"
                    :key="role.id"
                    :value="role.id"
                >
                    {{ role.nom }}
                </option>
            </select>

            <p v-if="descriptionForModule(module.key)" class="mt-1 text-xs text-gray-500">
                {{ descriptionForModule(module.key) }}
            </p>
        </div>

        <p v-if="selectedCount === 0 && !hasItSelected" class="text-sm text-amber-700">
            Sélectionnez au moins un rôle pour donner accès à l'application.
        </p>
        <p v-else class="text-xs text-gray-500">
            {{ selectedCount }} module{{ selectedCount > 1 ? 's' : '' }} configuré{{ selectedCount > 1 ? 's' : '' }}.
        </p>

        <p v-if="roles.length === 0" class="text-sm text-gray-500">
            Aucun rôle disponible. Veuillez contacter un administrateur.
        </p>
        <InputError :message="error" />
    </div>
</template>
