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
        /** Lien fichier / nouvel onglet (pas Inertia). */
        external?: boolean;
        target?: string | null;
        disabled?: boolean;
        loading?: boolean;
        variant?: 'neutral' | 'primary' | 'success' | 'warning' | 'danger';
    }>(),
    {
        href: null,
        external: false,
        target: null,
        disabled: false,
        loading: false,
        variant: 'neutral',
    },
);

const emit = defineEmits<{
    click: [event: MouseEvent];
}>();

const baseClass =
    'inline-flex size-9 items-center justify-center rounded-xl border border-transparent transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 disabled:pointer-events-none disabled:opacity-40';

const toneClass = computed(() => {
    switch (props.variant) {
        case 'primary':
            return 'bg-primary/10 text-primary hover:bg-primary/15 dark:bg-primary/20';
        case 'success':
            return 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300';
        case 'warning':
            return 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300';
        case 'danger':
            return 'bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-400';
        default:
            return 'bg-slate-50 text-slate-600 hover:bg-white hover:text-primary hover:shadow-sm dark:bg-slate-800/60 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-primary';
    }
});

const isDisabled = computed(() => props.disabled || props.loading);
const useAnchor = computed(() => Boolean(props.href) && props.external);
const useInertiaLink = computed(() => Boolean(props.href) && !props.external);

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
                <a
                    v-if="useAnchor"
                    :href="href!"
                    :target="target ?? undefined"
                    :rel="target === '_blank' ? 'noopener noreferrer' : undefined"
                    :aria-label="label"
                    :class="[baseClass, toneClass, isDisabled ? 'pointer-events-none opacity-40' : '']"
                    :tabindex="isDisabled ? -1 : 0"
                    @click="onClick"
                >
                    <Loader2 v-if="loading" class="size-4 animate-spin" />
                    <component :is="icon" v-else class="size-4" stroke-width="2" />
                    <span class="sr-only">{{ label }}</span>
                </a>
                <Link
                    v-else-if="useInertiaLink"
                    :href="href!"
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
