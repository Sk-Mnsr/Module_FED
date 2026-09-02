<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description: string;
        confirmLabel?: string;
        cancelLabel?: string;
        loading?: boolean;
        disabled?: boolean;
        variant?: 'default' | 'danger' | 'warning' | 'success';
    }>(),
    {
        confirmLabel: 'Confirmer',
        cancelLabel: 'Annuler',
        loading: false,
        disabled: false,
        variant: 'default',
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [];
    cancel: [];
}>();

const openModel = computed({
    get: () => props.open,
    set: (v: boolean) => emit('update:open', v),
});

const confirmClass = computed(() => {
    switch (props.variant) {
        case 'danger':
            return 'bg-red-600 text-white hover:bg-red-700';
        case 'warning':
            return 'border-amber-300 bg-amber-600 text-white hover:bg-amber-700';
        case 'success':
            return 'bg-emerald-600 text-white hover:bg-emerald-700';
        default:
            return '';
    }
});

function onCancel() {
    if (props.loading) return;
    openModel.value = false;
    emit('cancel');
}

function onConfirm() {
    if (props.loading || props.disabled) return;
    emit('confirm');
}
</script>

<template>
    <Dialog v-model:open="openModel">
        <DialogContent class="sm:max-w-md" :hide-close="loading">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription class="whitespace-pre-line">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>
            <div v-if="$slots.default" class="py-1">
                <slot />
            </div>
            <DialogFooter class="gap-2 sm:gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="border-slate-300"
                    :disabled="loading"
                    @click="onCancel"
                >
                    {{ cancelLabel }}
                </Button>
                <Button
                    type="button"
                    :class="confirmClass"
                    :disabled="loading || disabled"
                    @click="onConfirm"
                >
                    {{ loading ? 'Patientez…' : confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
