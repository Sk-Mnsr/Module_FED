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

/** Afficher la signature enregistrée si elle existe */
const useSaved = ref(!!props.savedSignature);
const mode = ref<'upload' | 'draw'>('draw');
const canvasRef = ref<HTMLCanvasElement | null>(null);
const signaturePad = ref<SignaturePad | null>(null);
const uploadedPreview = ref<string | null>(null);
const uploadError = ref<string | null>(null);

const hasSignature = ref(false);

/** Signature actuellement affichée : enregistrée ou nouvelle (dessin/upload) */
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
        penColor: 'rgb(0, 0, 0)',
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
    initSignaturePad();
    setTimeout(resizeCanvas, 100);
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

const confirmDraw = () => {
    const data = getDrawSignature();
    if (data) {
        hasSignature.value = true;
        emit('update:modelValue', data);
    }
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
        <!-- Signature enregistrée affichée automatiquement -->
        <div v-if="savedSignature && useSaved" class="space-y-2">
            <Label>Votre signature</Label>
            <div class="w-full max-w-md rounded-md border border-gray-300 bg-white p-4">
                <img
                    :src="savedSignature"
                    alt="Signature enregistrée"
                    class="mx-auto max-h-32 object-contain"
                />
            </div>
            <Button type="button" variant="outline" size="sm" @click="switchToSignAgain">
                <RefreshCw class="mr-2 h-4 w-4" /> Signer à nouveau
            </Button>
        </div>

        <!-- Zone dessin / téléversement -->
        <div v-else class="space-y-4">
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :class="{ 'bg-gray-100': mode === 'draw' }"
                    @click="mode = 'draw'"
                >
                    <Pen class="mr-2 h-4 w-4" /> Signer
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :class="{ 'bg-gray-100': mode === 'upload' }"
                    @click="mode = 'upload'"
                >
                    <Upload class="mr-2 h-4 w-4" /> Téléverser
                </Button>
            </div>

            <div v-if="mode === 'draw'" class="space-y-2">
                <Label>Zone de signature</Label>
                <div class="w-full max-w-md rounded-md border border-gray-300 bg-white">
                    <canvas
                        ref="canvasRef"
                        class="block w-full touch-none"
                        style="height: 180px; width: 100%"
                    />
                </div>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" size="sm" @click="clearDraw">
                        Effacer
                    </Button>
                </div>
            </div>

            <div v-else class="space-y-2">
                <Label>Téléverser une image de signature</Label>
                <Input
                    type="file"
                    accept="image/png,image/jpeg,image/jpg,image/webp"
                    class="cursor-pointer"
                    @change="onFileChange"
                />
                <p v-if="uploadError" class="text-sm text-red-600">{{ uploadError }}</p>
                <div v-if="uploadedPreview" class="mt-2">
                    <p class="mb-1 text-sm text-gray-600">Aperçu :</p>
                    <img
                        :src="uploadedPreview"
                        alt="Signature"
                        class="max-h-32 rounded border border-gray-200 object-contain"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
