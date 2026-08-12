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

interface ModuleMatrixRow {
    key: string;
    label: string;
    roles: string[];
}

const props = withDefaults(
    defineProps<{
        roles: Role[];
        modules: ModuleOption[];
        moduleMatrix?: ModuleMatrixRow[];
        modelValue: number[];
        error?: string;
    }>(),
    {
        moduleMatrix: () => [],
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
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

const modulesWithRoles = computed(() => {
    if (props.moduleMatrix.length > 0) {
        return props.moduleMatrix
            .filter((row) => row.roles.length > 0)
            .map((row) => ({ key: row.key, label: row.label }));
    }

    const keys = new Set(props.roles.map((role) => role.module).filter(Boolean));

    return props.modules.filter((module) => keys.has(module.key));
});

const assignments = reactive<Record<string, number | null>>({});

const rolesByModule = (moduleKey: string): Role[] => {
    const matrix = matrixByKey.value.get(moduleKey);
    if (matrix) {
        return matrix.roles
            .map((slug) => rolesBySlug.value.get(slug))
            .filter((role): role is Role => role !== undefined);
    }

    return props.roles.filter((role) => role.module === moduleKey);
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

const syncFromModel = () => {
    for (const module of modulesWithRoles.value) {
        assignments[module.key] = null;
    }

    for (const roleId of props.modelValue) {
        const role = props.roles.find((item) => item.id === roleId);
        if (!role) {
            continue;
        }

        for (const moduleKey of modulesCoveredByRole(role)) {
            if (moduleKey in assignments) {
                assignments[moduleKey] = roleId;
            }
        }
    }
};

watch(
    () => [props.modelValue, props.roles, props.modules, props.moduleMatrix],
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
    },
    { deep: true },
);

const onModuleRoleChange = (moduleKey: string, roleId: number | null) => {
    const previousId = assignments[moduleKey] ?? null;

    if (roleId === null) {
        if (previousId !== null) {
            const previousRole = props.roles.find((item) => item.id === previousId);
            const keysToClear = previousRole
                ? modulesCoveredByRole(previousRole)
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

    // Un rôle peut couvrir plusieurs modules (ex. OD + Réconciliation) :
    // on aligne les sélecteurs concernés sur le même rôle.
    for (const key of modulesCoveredByRole(role)) {
        if (key in assignments) {
            assignments[key] = roleId;
        }
    }
};

const descriptionForModule = (moduleKey: string) => {
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
            Attribuez un rôle par module. L'utilisateur aura accès à tous les modules configurés ci-dessous.
        </p>

        <div
            v-for="module in modulesWithRoles"
            :key="module.key"
            class="rounded-lg border border-gray-200 p-4"
        >
            <Label :for="`role-${module.key}`" class="text-base font-medium text-gray-700">
                {{ module.label }}
            </Label>
            <select
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

        <p v-if="selectedCount === 0" class="text-sm text-amber-700">
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
