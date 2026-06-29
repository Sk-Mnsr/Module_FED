<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, nextTick, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import SignaturePad from 'signature_pad';
import { Upload, Pen, RefreshCw } from 'lucide-vue-next';

const props = defineProps<{
    modelValue?: string | null;
    /** Signature enregistrée à afficher automatiquement dans la zone */
    savedSignature?: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const useSaved = ref(!!props.savedSignature);
const mode = ref<'upload' | 'draw'>('draw');
const canvasRef = ref<HTMLCanvasElement | null>(null);
const signaturePad = ref<SignaturePad | null>(null);
const uploadedPreview = ref<string | null>(null);
const uploadError = ref<string | null>(null);

const hasSignature = ref(false);

const currentSignature = computed(() => {
    if (!useSaved.value && mode.value === 'upload') return uploadedPreview.value;
    if (!useSaved.value && mode.value === 'draw') {
        return signaturePad.value && !signaturePad.value.isEmpty()
            ? signaturePad.value.toDataURL('image/png')
            : null;
    }
    return props.savedSignature || null;
});

const hasValidSignature = computed(() => !!currentSignature.value);

const initSignaturePad = () => {
    if (!canvasRef.value) return;
    signaturePad.value = new SignaturePad(canvasRef.value, {
        backgroundColor: 'rgb(255, 255, 255)',
        penColor: 'rgb(17, 24, 39)',
    });
    signaturePad.value.addEventListener('endStroke', () => {
        hasSignature.value = !signaturePad.value?.isEmpty();
    });
};

const resizeCanvas = () => {
    if (!canvasRef.value || !signaturePad.value) return;
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const ctx = canvasRef.value.getContext('2d');
    if (!ctx) return;

    const rect = canvasRef.value.getBoundingClientRect();
    canvasRef.value.width = rect.width * ratio;
    canvasRef.value.height = rect.height * ratio;
    canvasRef.value.style.width = `${rect.width}px`;
    canvasRef.value.style.height = `${rect.height}px`;
    ctx.scale(ratio, ratio);
    signaturePad.value.clear();
};

onMounted(async () => {
    await nextTick();
    if (!useSaved.value) {
        initSignaturePad();
        setTimeout(resizeCanvas, 100);
    }
    window.addEventListener('resize', resizeCanvas);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', resizeCanvas);
    signaturePad.value = null;
});

watch(mode, async (newMode) => {
    uploadError.value = null;
    uploadedPreview.value = null;
    if (newMode === 'draw') {
        await nextTick();
        if (canvasRef.value && !signaturePad.value) {
            initSignaturePad();
        }
        setTimeout(resizeCanvas, 50);
    }
});

const onFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    uploadError.value = null;
    uploadedPreview.value = null;
    hasSignature.value = false;
    emit('update:modelValue', null);

    if (!file) return;

    const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        uploadError.value = 'Format accepté : PNG, JPEG, WebP';
        return;
    }

    const reader = new FileReader();
    reader.onload = () => {
        const result = reader.result as string;
        uploadedPreview.value = result;
        hasSignature.value = true;
        emit('update:modelValue', result);
    };
    reader.readAsDataURL(file);
    input.value = '';
};

const clearDraw = () => {
    signaturePad.value?.clear();
    hasSignature.value = false;
    emit('update:modelValue', null);
};

const getDrawSignature = (): string | null => {
    if (!signaturePad.value || signaturePad.value.isEmpty()) return null;
    return signaturePad.value.toDataURL('image/png');
};

const switchToSignAgain = () => {
    useSaved.value = false;
    hasSignature.value = false;
    uploadedPreview.value = null;
    signaturePad.value = null;
    emit('update:modelValue', null);
};

watch(useSaved, async (val) => {
    if (!val) {
        await nextTick();
        if (canvasRef.value && !signaturePad.value) initSignaturePad();
        setTimeout(resizeCanvas, 50);
    }
});

watch(() => props.savedSignature, (val) => {
    if (val) useSaved.value = true;
}, { immediate: true });

defineExpose({
    getSignature: (): string | null => {
        if (useSaved.value && props.savedSignature) return props.savedSignature;
        if (mode.value === 'upload') return uploadedPreview.value;
        return getDrawSignature();
    },
    hasSignature: () => hasValidSignature.value,
});
</script>

<template>
    <div class="space-y-4">
        <!-- Signature enregistrée -->
        <div v-if="savedSignature && useSaved" class="space-y-3">
            <Label class="text-sm font-medium">Signature actuelle</Label>
            <div class="flex min-h-[140px] items-center justify-center rounded-xl border border-dashed border-border bg-muted/30 p-6">
                <img
                    :src="savedSignature"
                    alt="Signature enregistrée"
                    class="max-h-28 max-w-full object-contain"
                />
            </div>
            <Button type="button" variant="outline" size="sm" @click="switchToSignAgain">
                <RefreshCw class="mr-2 size-4" /> Modifier la signature
            </Button>
        </div>

        <!-- Création / remplacement -->
        <div v-else class="space-y-4">
            <div class="inline-flex rounded-lg border border-border bg-muted/40 p-1">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="mode === 'draw'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="mode = 'draw'"
                >
                    <Pen class="size-4" /> Dessiner
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="mode === 'upload'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="mode = 'upload'"
                >
                    <Upload class="size-4" /> Importer
                </button>
            </div>

            <div v-if="mode === 'draw'" class="space-y-3">
                <Label class="text-sm font-medium">Zone de signature</Label>
                <div class="overflow-hidden rounded-xl border border-border bg-white shadow-inner dark:bg-background">
                    <canvas
                        ref="canvasRef"
                        class="block w-full touch-none"
                        style="height: 200px; width: 100%"
                    />
                </div>
                <Button type="button" variant="ghost" size="sm" class="text-muted-foreground" @click="clearDraw">
                    Effacer
                </Button>
            </div>

            <div v-else class="space-y-3">
                <Label class="text-sm font-medium">Fichier image</Label>
                <Input
                    type="file"
                    accept="image/png,image/jpeg,image/jpg,image/webp"
                    class="cursor-pointer border-dashed bg-muted/20 file:mr-3 file:rounded-md file:border-0 file:bg-violet-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-violet-800"
                    @change="onFileChange"
                />
                <p v-if="uploadError" class="text-sm text-destructive">{{ uploadError }}</p>
                <div
                    v-if="uploadedPreview"
                    class="flex min-h-[120px] items-center justify-center rounded-xl border border-dashed border-border bg-muted/30 p-4"
                >
                    <img
                        :src="uploadedPreview"
                        alt="Aperçu signature"
                        class="max-h-28 max-w-full object-contain"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
