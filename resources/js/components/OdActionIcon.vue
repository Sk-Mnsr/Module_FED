<script setup lang="ts">
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Link } from '@inertiajs/vue3';
import { Loader2, type LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        label: string;
        icon: LucideIcon;
        href?: string | null;
        disabled?: boolean;
        loading?: boolean;
        variant?: 'neutral' | 'primary' | 'success' | 'warning' | 'danger';
    }>(),
    {
        href: null,
        disabled: false,
        loading: false,
        variant: 'neutral',
    },
);

const emit = defineEmits<{
    click: [event: MouseEvent];
}>();

const baseClass =
    'inline-flex size-9 items-center justify-center rounded-xl transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 disabled:pointer-events-none disabled:opacity-40';

const toneClass = computed(() => {
    switch (props.variant) {
        case 'primary':
            return 'bg-primary text-primary-foreground shadow-sm hover:bg-primary/90';
        case 'success':
            return 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700';
        case 'warning':
            return 'text-amber-700 hover:bg-amber-100 hover:text-amber-900 dark:text-amber-300 dark:hover:bg-amber-950/50';
        case 'danger':
            return 'text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40';
        default:
            return 'text-slate-600 hover:bg-white hover:text-primary hover:shadow-sm dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-primary';
    }
});

const isDisabled = computed(() => props.disabled || props.loading);

function onClick(e: MouseEvent) {
    if (isDisabled.value) {
        e.preventDefault();
        return;
    }
    emit('click', e);
}
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <Link
                    v-if="href"
                    :href="href"
                    :aria-label="label"
                    :class="[baseClass, toneClass, isDisabled ? 'pointer-events-none opacity-40' : '']"
                    :tabindex="isDisabled ? -1 : 0"
                >
                    <Loader2 v-if="loading" class="size-4 animate-spin" />
                    <component :is="icon" v-else class="size-4" stroke-width="2" />
                    <span class="sr-only">{{ label }}</span>
                </Link>
                <button
                    v-else
                    type="button"
                    :aria-label="label"
                    :disabled="isDisabled"
                    :class="[baseClass, toneClass]"
                    @click="onClick"
                >
                    <Loader2 v-if="loading" class="size-4 animate-spin" />
                    <component :is="icon" v-else class="size-4" stroke-width="2" />
                    <span class="sr-only">{{ label }}</span>
                </button>
            </TooltipTrigger>
            <TooltipContent side="top">
                <p>{{ loading ? `${label}…` : label }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
