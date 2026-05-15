<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        expiration?: string;
        dateExpiration?: string;
    }>(),
    {
        expiration: '—',
        dateExpiration: '',
    },
);

const getExpirationProgress = (isoDate?: string) => {
    if (!isoDate) return 0;
    const expiration = new Date(`${isoDate}T00:00:00`);
    if (Number.isNaN(expiration.getTime())) return 0;

    const now = new Date();
    const diffMs = expiration.getTime() - now.getTime();
    const totalWindowMs = 365 * 24 * 60 * 60 * 1000;
    const ratio = Math.min(Math.max(diffMs / totalWindowMs, 0), 1);

    return Math.round(ratio * 100);
};

const getExpirationColor = (progress: number) => {
    // 100% = vert, 0% = rouge
    const hue = Math.round((progress / 100) * 120);
    return `hsl(${hue}, 85%, 47%)`;
};
</script>

<template>
    <div class="flex items-center gap-3 w-full min-w-0">
        <div class="h-2 flex-1 min-w-[6rem] max-w-none rounded-full bg-gray-200 overflow-hidden">
            <div
                class="h-full rounded-full transition-all duration-300"
                :style="{
                    width: `${getExpirationProgress(props.dateExpiration)}%`,
                    backgroundColor: getExpirationColor(getExpirationProgress(props.dateExpiration)),
                }"
            />
        </div>
        <span class="text-sm text-gray-700">{{ props.expiration }}</span>
    </div>
</template>
