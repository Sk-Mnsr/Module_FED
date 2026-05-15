<script setup lang="ts">
import type { CheckboxRootEmits, CheckboxRootProps } from 'reka-ui'
import { cn } from '@/lib/utils'
import { Check } from 'lucide-vue-next'
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui'
import { computed, ref, type HTMLAttributes } from 'vue'

type Props = CheckboxRootProps & {
    class?: HTMLAttributes['class']
    /**
     * Compat shadcn / anciennes vues : lier avec :checked et @update:checked.
     * reka-ui n’expose que modelValue ; sans ce pont, la case bouge visuellement mais le parent ne reçoit jamais l’événement.
     */
    checked?: boolean
    /** Compat en-tête « sélection partielle » : correspond à modelValue === 'indeterminate'. */
    indeterminate?: boolean
}

const props = defineProps<Props>()
const emits = defineEmits<
    CheckboxRootEmits & {
        'update:checked': [value: boolean]
    }
>()

const isModelControlled = computed(
    () => props.modelValue !== undefined && props.modelValue !== null,
)
const isLegacyCheckedControlled = computed(() => props.checked !== undefined)

const uncontrolled = ref<boolean | 'indeterminate'>(
    (props.defaultValue !== undefined ? props.defaultValue : (props.falseValue ?? false)) as boolean | 'indeterminate',
)

const rootModelValue = computed(() => {
    if (props.indeterminate) {
        return 'indeterminate' as const
    }
    if (isModelControlled.value) {
        return props.modelValue!
    }
    if (isLegacyCheckedControlled.value) {
        return props.checked ? (props.trueValue ?? true) : (props.falseValue ?? false)
    }
    return uncontrolled.value
})

function onUpdateModelValue(value: boolean | 'indeterminate') {
    emits('update:modelValue', value as never)

    const isChecked =
        value !== 'indeterminate' && (value === true || value === props.trueValue)
    emits('update:checked', isChecked)

    if (!isModelControlled.value && !isLegacyCheckedControlled.value) {
        uncontrolled.value = value
    }
}

const passthrough = computed(() => {
    const { class: _c, checked: _ch, indeterminate: _i, modelValue: _m, ...rest } = props
    return rest
})
</script>

<template>
    <CheckboxRoot
        data-slot="checkbox"
        v-bind="passthrough"
        :model-value="rootModelValue"
        @update:model-value="onUpdateModelValue"
        :class="
            cn(
                'peer border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
                props.class,
            )
        "
    >
        <CheckboxIndicator
            data-slot="checkbox-indicator"
            class="flex items-center justify-center text-current transition-none"
        >
            <slot>
                <Check class="size-3.5" />
            </slot>
        </CheckboxIndicator>
    </CheckboxRoot>
</template>
