<template>
  <AppLayout title="Écritures Comptables">
    <div class="flex flex-col gap-6 p-6">
      <ComptabiliteModuleTabs />
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 flex flex-col sm:flex-row justify-between items-center gap-4">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <h1 class="text-xl font-bold text-gray-900">Écritures Comptables</h1>
              <p class="text-sm text-gray-500">Consultation  et exportations des écritures comptables générées</p>
            </div>
          </div>
          <div class="flex flex-wrap gap-3 items-center">
            <a
              href="/ecritures-comptables/export"
              class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm"
              target="_blank"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              Exporter CSV
            </a>
            <template v-if="canPushComptableImport">
              <input
                ref="forwardInput"
                type="file"
                accept=".csv,.txt,text/csv"
                class="sr-only"
                @change="onForwardFile"
              />
              <button
                v-if="comptableImportApiConfigured"
                type="button"
                class="inline-flex items-center px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm disabled:opacity-50"
                :disabled="forwarding"
                @click="() => forwardInput?.click()"
                title="Envoie le fichier tel quel à Flex, sans l’enregistrer ici"
              >
                <span v-if="forwarding" class="mr-2 inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                {{ forwarding ? 'Transfert…' : 'Envoi direct (Flex, sans base)' }}
              </button>
            </template>
            <button
              v-if="comptableImportApiConfigured && canPushComptableImport"
              type="button"
              class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm disabled:opacity-50"
              :disabled="pushing"
              @click="confirmPush"
            >
              <svg v-if="!pushing" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m-4-4l4 4m0 0l4-4" />
              </svg>
              <span v-if="pushing" class="mr-2 inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
              {{ pushing ? 'Envoi…' : 'Envoyer vers la plateforme' }}
            </button>
            <p
              v-else-if="canPushComptableImport && !comptableImportApiConfigured"
              class="self-center text-sm text-amber-700 bg-amber-50 px-3 py-1.5 rounded-md border border-amber-200"
            >
              Intégration : renseignez <code class="text-xs">ECRITURES_COMPTABLES_IMPORT_URL</code> et <code class="text-xs">ECRITURES_COMPTABLES_IMPORT_KEY</code> dans <code class="text-xs">.env</code>.
            </p>
          </div>
        </div>
      </div>

      <div v-if="page.props.flash?.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        {{ page.props.flash.success }}
      </div>
      <div v-if="page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        {{ page.props.flash.error }}
      </div>

      <!-- Data Table -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">numero</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">no_batch</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">no_compte</th>
                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">sens</th>
                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">montant</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">code_operation</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">date_de_valeur</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">code_agence</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">libelle_ecriture</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" title="IDFLEX (export / Flex)">user_id</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">annee_comptable</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">mois_comptable</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="ecriture in ecritures.data" :key="ecriture.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ ecriture.numero || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ ecriture.no_batch || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    {{ ecriture.no_compte || '-' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <span
                    class="inline-flex flex-col items-center justify-center w-6 h-6 rounded-full text-xs font-bold"
                    :class="ecriture.sens === 'D' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700'"
                  >
                    {{ ecriture.sens }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                    :class="ecriture.sens === 'D' ? 'text-gray-900' : 'text-green-600'">
                  {{ formatAmount(ecriture.montant) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ ecriture.code_operation || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(ecriture.date_de_valeur) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ ecriture.code_agence || '-' }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" :title="ecriture.libelle_ecriture">
                  {{ ecriture.libelle_ecriture || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" :title="'Id interne: ' + (ecriture.user_id ?? '-')">
                  {{ ecriture.user?.profil?.matricule || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ ecriture.annee_comptable || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ ecriture.mois_comptable || '-' }}
                </td>
              </tr>
              <tr v-if="ecritures.data.length === 0">
                <td colspan="12" class="px-6 py-8 text-center text-sm text-gray-500">
                  <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  Aucune écriture comptable trouvée.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end">
          <div v-if="ecritures.links && ecritures.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(link, key) in ecritures.links" :key="key">
              <div v-if="link.url === null" class="px-3 py-2 text-sm text-gray-400 border rounded-md" v-html="link.label" />
              <Link v-else class="px-3 py-2 text-sm border rounded-md hover:bg-gray-100 transition-colors" :class="{ 'bg-blue-600 text-white hover:bg-blue-700': link.active }" :href="link.url" v-html="link.label" preserve-scroll />
            </template>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import ComptabiliteModuleTabs from '@/components/ComptabiliteModuleTabs.vue'

const page = usePage()
const pushing = ref(false)
const forwarding = ref(false)
const forwardInput = ref(null)

const props = defineProps({
  ecritures: {
    type: Object,
    required: true,
  },
  comptableImportApiConfigured: {
    type: Boolean,
    default: false,
  },
  canPushComptableImport: {
    type: Boolean,
    default: false,
  },
})

const confirmPush = () => {
  if (!window.confirm('Envoyer toutes les écritures comptables (fichier CSV) vers l’API de la plateforme ?\n\n(Vérifiez côté manuel l’unicité des no_batch avant envoi — l’API peut renvoyer une erreur générique en cas de doublon.)')) {
    return
  }
  pushing.value = true
  router.post(
    '/ecritures-comptables/push',
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        pushing.value = false
      },
    },
  )
}

const onForwardFile = (e) => {
  const file = e.target?.files?.[0]
  e.target.value = ''
  if (!file) {
    return
  }
  if (!window.confirm('Transférer ce fichier directement vers Flex, sans l’enregistrer en base ?')) {
    return
  }
  forwarding.value = true
  router.post(
    '/ecritures-comptables/push',
    { forward_file: file },
    {
      forceFormData: true,
      preserveScroll: true,
      onFinish: () => {
        forwarding.value = false
      },
    },
  )
}

const formatAmount = (amount) => {
  if (!amount) return '0'
  return new Intl.NumberFormat('fr-FR').format(amount)
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}
</script>
