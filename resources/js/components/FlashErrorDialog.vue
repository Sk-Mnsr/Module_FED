<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePage } from '@inertiajs/vue3';
import { AlertCircle, ChevronDown, ChevronUp } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type FlashErrorPayload = {
    title?: string;
    message: string;
    log?: string;
};

const page = usePage();
const open = ref(false);
const showLog = ref(false);

const flashError = computed(() => page.props.flash?.error as string | FlashErrorPayload | undefined);

const errorTitle = computed(() => {
    const error = flashError.value;
    if (!error) return '';
    if (typeof error === 'string') return 'Erreur';
    return error.title ?? 'Erreur';
});

const errorMessage = computed(() => {
    const error = flashError.value;
    if (!error) return '';
    if (typeof error === 'string') return error;
    return error.message;
});

const errorLog = computed(() => {
    const error = flashError.value;
    if (!error || typeof error === 'string') return null;
    return error.log?.trim() || null;
});

watch(
    flashError,
    (error) => {
        if (error) {
            open.value = true;
            showLog.value = false;
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <div class="flex items-start gap-3">
                    <div class="rounded-full bg-red-100 p-2 text-red-700 dark:bg-red-950 dark:text-red-300">
                        <AlertCircle class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <DialogTitle>{{ errorTitle }}</DialogTitle>
                        <DialogDescription class="mt-2 text-sm leading-relaxed text-foreground">
                            {{ errorMessage }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <Collapsible v-if="errorLog" v-model:open="showLog" class="rounded-lg border border-border bg-muted/30">
                <CollapsibleTrigger as-child>
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-sm font-medium text-muted-foreground hover:text-foreground"
                    >
                        <span>Détails techniques</span>
                        <ChevronUp v-if="showLog" class="size-4 shrink-0" />
                        <ChevronDown v-else class="size-4 shrink-0" />
                    </button>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <pre class="max-h-48 overflow-auto border-t border-border px-3 py-2.5 text-xs leading-relaxed whitespace-pre-wrap break-words text-muted-foreground">{{ errorLog }}</pre>
                </CollapsibleContent>
            </Collapsible>

            <DialogFooter>
                <Button type="button" @click="open = false">
                    Fermer
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
