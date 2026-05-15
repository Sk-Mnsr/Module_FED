<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Calendar, ChevronRight, FolderArchive } from 'lucide-vue-next';

const MONTHS_FR = [
    'Janvier',
    'Février',
    'Mars',
    'Avril',
    'Mai',
    'Juin',
    'Juillet',
    'Août',
    'Septembre',
    'Octobre',
    'Novembre',
    'Décembre',
] as const;

function daysInMonth(year: number, month: number): number {
    return new Date(year, month, 0).getDate();
}

const now = new Date();
const selectedYear = ref(now.getFullYear());
const selectedMonth = ref(now.getMonth() + 1);
const selectedDay = ref(now.getDate());

const startYear = 2018;
const years = computed(() => {
    const y = now.getFullYear();
    const list: number[] = [];
    for (let i = y; i >= startYear; i--) {
        list.push(i);
    }
    return list;
});

const months = computed(() =>
    MONTHS_FR.map((label, i) => ({
        value: i + 1,
        label,
    })),
);

const days = computed(() => {
    const dim = daysInMonth(selectedYear.value, selectedMonth.value);
    return Array.from({ length: dim }, (_, i) => i + 1);
});

watch([selectedYear, selectedMonth], () => {
    const dim = daysInMonth(selectedYear.value, selectedMonth.value);
    if (selectedDay.value > dim) {
        selectedDay.value = dim;
    }
});

const breadcrumbPath = computed(() => {
    const m = MONTHS_FR[selectedMonth.value - 1];
    return `${selectedYear.value} › ${m} › ${String(selectedDay.value).padStart(2, '0')}`;
});

const isoDateKey = computed(
    () =>
        `${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}-${String(selectedDay.value).padStart(2, '0')}`,
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <div
            class="rounded-lg border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="border-b border-border px-4 py-3">
                <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <FolderArchive class="size-4 shrink-0" />
                    <span>Classement à l’usage OPS :</span>
                    <span class="font-medium text-foreground">{{ breadcrumbPath }}</span>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Parcourir les dossiers par année, puis mois, puis jour d’archivage.
                </p>
            </div>

            <div class="grid divide-border md:grid-cols-3 md:divide-x">
                <!-- Années -->
                <div class="flex max-h-72 flex-col border-b border-border md:border-b-0">
                    <div
                        class="sticky top-0 flex items-center gap-1 border-b border-border bg-muted/50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                    >
                        <Calendar class="size-3.5" />
                        Année
                    </div>
                    <div class="max-h-60 overflow-y-auto p-2">
                        <Button
                            v-for="y in years"
                            :key="y"
                            variant="ghost"
                            size="sm"
                            class="mb-0.5 h-9 w-full justify-between px-3 font-normal"
                            :class="
                                selectedYear === y
                                    ? 'bg-primary/10 font-medium text-primary hover:bg-primary/15'
                                    : ''
                            "
                            @click="selectedYear = y"
                        >
                            {{ y }}
                            <ChevronRight v-if="selectedYear === y" class="size-4 opacity-70" />
                        </Button>
                    </div>
                </div>

                <!-- Mois -->
                <div class="flex max-h-72 flex-col border-b border-border md:border-b-0">
                    <div
                        class="sticky top-0 flex items-center border-b border-border bg-muted/50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                    >
                        Mois
                    </div>
                    <div class="max-h-60 overflow-y-auto p-2">
                        <Button
                            v-for="m in months"
                            :key="m.value"
                            variant="ghost"
                            size="sm"
                            class="mb-0.5 h-9 w-full justify-between px-3 font-normal"
                            :class="
                                selectedMonth === m.value
                                    ? 'bg-primary/10 font-medium text-primary hover:bg-primary/15'
                                    : ''
                            "
                            @click="selectedMonth = m.value"
                        >
                            {{ m.label }}
                            <ChevronRight v-if="selectedMonth === m.value" class="size-4 opacity-70" />
                        </Button>
                    </div>
                </div>

                <!-- Jours -->
                <div class="flex max-h-72 flex-col">
                    <div
                        class="sticky top-0 flex items-center border-b border-border bg-muted/50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                    >
                        Jour
                    </div>
                    <div class="max-h-60 overflow-y-auto p-2">
                        <div class="grid grid-cols-7 gap-1">
                            <Button
                                v-for="d in days"
                                :key="d"
                                variant="ghost"
                                size="sm"
                                class="h-9 p-0 font-normal tabular-nums"
                                :class="
                                    selectedDay === d
                                        ? 'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground'
                                        : ''
                                "
                                @click="selectedDay = d"
                            >
                                {{ String(d).padStart(2, '0') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zone dossiers pour la date sélectionnée (branchement futur API / pièces OD) -->
        <div class="rounded-lg border border-dashed border-border bg-muted/20 p-6">
            <p class="text-sm font-medium text-foreground">
                Dossiers du {{ selectedDay.toString().padStart(2, '0') }} /
                {{ String(selectedMonth).padStart(2, '0') }} / {{ selectedYear }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Clé de classement : <code class="rounded bg-muted px-1.5 py-0.5 text-xs">{{ isoDateKey }}</code>
            </p>
            <p class="mt-4 text-sm text-muted-foreground">
                Aucune pièce archivée pour cette date. Les enregistrements OD seront rattachés ici une fois le stockage
                branché.
            </p>
        </div>
    </div>
</template>
