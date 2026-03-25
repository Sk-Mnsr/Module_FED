<template>
  <AppLayout title="Écritures Comptables">
    <div class="flex flex-col gap-6 p-6">
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
              <p class="text-sm text-gray-500">Consultez et exportez les écritures comptables générées.</p>
            </div>
          </div>
          <div class="flex gap-3">
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
          </div>
        </div>
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
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">user_id</th>
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
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ ecriture.user_id || '-' }}
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
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

const props = defineProps({
  ecritures: {
    type: Object,
    required: true,
  },
})

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
