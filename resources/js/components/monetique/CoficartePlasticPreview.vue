<script setup lang="ts">
/**
 * Aperçu décoratif type carte physique Cofina / Visa (grand format réseau).
 * Les logos partenaires sont indicatifs ; le numéro et la date proviennent des props.
 */
import { formatCardNumberDisplay } from '@/lib/utils';
import { Bird } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        numeroCarte: string;
        expiration?: string | null;
        /** Afficher la mention légale sous la carte (détail / fiche). */
        showFooterNote?: boolean;
        /** Grilles d’aperçus : padding et logos légèrement réduits. */
        compact?: boolean;
        /** Remplace le bloc UBA (ex. statut « Livré » + date). */
        tagTopRight?: { title: string; subtitle?: string } | null;
        /** Ligne facture sous le numéro (aperçu transfert, saisie stock…). */
        referenceFacture?: string | null;
    }>(),
    {
        expiration: null,
        showFooterNote: true,
        compact: false,
        tagTopRight: null,
        referenceFacture: null,
    },
);

const numberDisplay = computed(() => {
    const raw = String(props.numeroCarte ?? '').trim();
    if (!raw.replace(/\D/g, '')) {
        return '•••• •••• •••• ••••';
    }
    return formatCardNumberDisplay(raw);
});

const expireDisplay = computed(() => {
    if (props.expiration && props.expiration.trim() !== '') {
        return props.expiration.trim();
    }
    return '—/—';
});

const referenceFactureAffiche = computed(() => {
    const r = props.referenceFacture?.trim();
    return r ? r : null;
});
</script>

<template>
    <div class="CoficartePlastic w-full select-none" aria-hidden="true">
        <div
            class="relative aspect-[1.586] w-full overflow-hidden rounded-2xl shadow-[0_25px_50px_-12px_rgba(0,0,0,0.35),0_0_0_1px_rgba(0,0,0,0.06)] ring-1 ring-black/5"
            :class="compact ? 'rounded-xl' : ''"
        >
            <!-- Fond principal gris + zone blanche / rouge (style carte réelle) -->
            <div class="absolute inset-0 bg-[#5b5b5b]" />

            <!-- Panneau blanc + courbe (séparation douce) -->
            <div
                class="absolute inset-y-0 right-0 w-[52%] bg-white z-[1]"
                style="clip-path: ellipse(72% 88% at 100% 50%)"
            />

            <!-- Arc rouge (empreinte Visa / partenaire) -->
            <div
                class="absolute z-[2] -right-[18%] -bottom-[42%] h-[115%] w-[92%] rounded-full bg-[#c8102e] opacity-[0.98]"
                aria-hidden="true"
            />

            <!-- Voile léger sur le gris pour lisibilité -->
            <div
                class="pointer-events-none absolute inset-y-0 left-0 z-[3] w-[56%] bg-gradient-to-br from-white/5 to-transparent"
            />

            <!-- Contenu -->
            <div
                class="relative z-[4] flex h-full flex-col text-white"
                :class="compact ? 'p-3 sm:p-4' : 'p-4 sm:p-5'"
            >
                <!-- Rangée logos -->
                <div class="flex items-start justify-between gap-2">
                    <div class="flex max-w-[58%] flex-col gap-1 pr-2">
                        <img
                            src="/logo_Cofina.png"
                            alt=""
                            class="h-7 w-auto max-w-[132px] object-contain object-left brightness-0 invert opacity-95 sm:h-8"
                            :class="compact ? '!h-6 sm:!h-7' : ''"
                        />
                        <p
                            class="font-medium leading-tight tracking-wide text-white/80"
                            :class="compact ? 'text-[0.5rem] sm:text-[0.52rem]' : 'text-[0.55rem] sm:text-[0.6rem]'"
                        >
                            Compagnie Financière Africaine
                        </p>
                    </div>
                    <div v-if="tagTopRight" class="flex max-w-[48%] flex-col items-end gap-0.5 pt-0.5 text-right">
                        <span
                            class="font-bold text-white drop-shadow-sm"
                            :class="compact ? 'text-[9px] sm:text-[10px]' : 'text-[10px] sm:text-xs'"
                        >
                            {{ tagTopRight.title }}
                        </span>
                        <span
                            v-if="tagTopRight.subtitle"
                            class="font-medium text-white/85 tabular-nums leading-tight"
                            :class="compact ? 'text-[8px] sm:text-[9px]' : 'text-[9px] sm:text-[10px]'"
                        >
                            {{ tagTopRight.subtitle }}
                        </span>
                    </div>
                    <div v-else class="flex flex-col items-end gap-1 pt-0.5 text-right">
                        <span
                            class="font-bold tracking-[0.2em] text-white drop-shadow-sm sm:text-sm"
                            :class="compact ? 'text-[10px]' : 'text-xs'"
                        >
                            UBA
                        </span>
                        <Bird
                            class="text-amber-300 drop-shadow-md"
                            :class="compact ? 'h-4 w-4 sm:h-5 sm:w-5' : 'h-5 w-5 sm:h-6 sm:w-6'"
                            stroke-width="1.5"
                            aria-hidden="true"
                        />
                    </div>
                </div>

                <!-- Puce + sans contact -->
                <div class="flex items-center gap-2.5" :class="compact ? 'mt-3' : 'mt-5'">
                    <div
                        class="rounded-md bg-gradient-to-br from-amber-200 via-amber-400 to-amber-700 shadow-[inset_0_1px_0_rgba(255,255,255,0.45),0_2px_4px_rgba(0,0,0,0.25)]"
                        :class="compact ? 'h-8 w-10 sm:h-9 sm:w-[3rem]' : 'h-9 w-11 sm:h-10 sm:w-[3.25rem]'"
                        aria-hidden="true"
                    />
                    <svg
                        class="h-6 w-6 text-white/90"
                        :class="compact ? '!h-5 !w-5' : ''"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.4"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            d="M8 10c1.5 2.5 1.5 5.5 0 8M11.5 7.5c2.3 3.5 2.3 9.5 0 13M15 5c3 4.5 3 10.5 0 15"
                        />
                    </svg>
                </div>

                <!-- Numéro (sur chevauchement gris / blanc — texte foncé pour contraste) -->
                <div class="mt-auto space-y-2 sm:space-y-3">
                    <p
                        v-if="referenceFactureAffiche"
                        class="text-center font-medium tracking-wide text-gray-800/95"
                        :class="compact ? 'text-[9px] sm:text-[10px]' : 'text-[10px] sm:text-[11px]'"
                    >
                        <span class="text-gray-600">Fact.</span>
                        {{ referenceFactureAffiche }}
                    </p>
                    <p
                        class="text-center font-mono font-semibold tracking-[0.14em] text-gray-900 drop-shadow-sm"
                        :class="
                            compact
                                ? 'text-base sm:text-lg md:text-[1.15rem]'
                                : 'text-lg sm:text-xl md:text-[1.35rem]'
                        "
                    >
                        {{ numberDisplay }}
                    </p>

                    <div class="flex items-end justify-between gap-2">
                        <span
                            class="mb-1 inline-block h-0 w-0 border-y-[6px] border-r-[9px] border-y-transparent border-r-white/85"
                            aria-hidden="true"
                        />
                        <div class="flex items-end gap-2">
                            <div
                                class="flex flex-col items-end pb-0.5 font-extrabold uppercase leading-[1.15] tracking-[0.12em] text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]"
                                :class="compact ? 'text-[0.38rem]' : 'text-[0.42rem]'"
                            >
                                <span>Expire</span>
                                <span>fin</span>
                            </div>
                            <p
                                class="font-mono font-semibold tabular-nums text-gray-900"
                                :class="compact ? 'text-sm sm:text-base' : 'text-base sm:text-lg'"
                            >
                                {{ expireDisplay }}
                            </p>
                        </div>
                        <span
                            class="mb-0.5 font-sans font-black italic tracking-tight text-white drop-shadow-md"
                            :class="compact ? 'text-xl sm:text-2xl' : 'text-2xl sm:text-3xl'"
                        >
                            VISA
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <p v-if="showFooterNote" class="mt-3 text-center text-xs text-gray-500">
            Aperçu non contractuel — illustration du support carte
        </p>
    </div>
</template>
