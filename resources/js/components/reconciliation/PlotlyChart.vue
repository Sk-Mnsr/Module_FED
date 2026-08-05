<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Plotly from 'plotly.js-dist-min';

type PlotlyFigure = {
    data?: unknown[];
    layout?: Record<string, unknown>;
    config?: Record<string, unknown>;
};

const props = defineProps<{
    figure: PlotlyFigure | null;
    height?: number;
}>();

const el = ref<HTMLDivElement | null>(null);

async function render() {
    if (!el.value) return;

    if (!props.figure?.data) {
        Plotly.purge(el.value);
        return;
    }

    const layout = {
        ...(props.figure.layout ?? {}),
        autosize: true,
        height: props.height ?? (props.figure.layout?.height as number | undefined) ?? 380,
        paper_bgcolor: 'rgba(0,0,0,0)',
        plot_bgcolor: 'rgba(0,0,0,0)',
    };

    await Plotly.react(el.value, props.figure.data, layout, {
        displayModeBar: false,
        responsive: true,
        ...(props.figure.config ?? {}),
    });
}

function onResize() {
    if (el.value) {
        void Plotly.Plots.resize(el.value);
    }
}

onMounted(() => {
    void render();
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    if (el.value) {
        Plotly.purge(el.value);
    }
});

watch(
    () => props.figure,
    () => {
        void render();
    },
    { deep: true },
);
</script>

<template>
    <div ref="el" class="w-full min-h-[280px]" />
</template>
