<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import HierarchicalArticleSelector from '@/components/HierarchicalArticleSelector.vue';
import { 
    Clock, 
    CheckCircle2, 
    XCircle, 
    Truck, 
    ArrowLeft,
    Package,
    AlertTriangle,
    User,
    Calendar,
    MessageSquare,
    Link as LinkIcon
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Article {
    id: number;
    description: string;
    code: string;
    stock_actuel: number;
    sous_categorie?: {
        id: number;
        nom: string;
        categorie_id: number;
        categorie?: {
            id: number;
            nom: string;
            famille_id: number;
            famille?: {
                id: number;
                nom: string;
            }
        }
    }
}

interface Item {
    id: number;
    designation: string;
    article: Article | null;
    quantite_demandee: number;
    quantite_livree: number;
}

interface Demande {
    id: number;
    user: { name: string };
    status: string;
    motif: string;
    date_demande: string;
    date_traitement: string | null;
    traitee_par_user: { name: string } | null;
    items: Item[];
}

interface Props {
    demande: Demande;
    isResponsableStock: boolean;
    articles: Article[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Approvisionnement',
        href: '/demandes-approvisionnement',
    },
    {
        title: `Détail demande n°${props.demande?.id}`,
        href: '#',
    },
];

const statusForm = useForm({
    status: '',
    items: (props.demande?.items || []).map(item => ({
        id: item.id,
        article_id: item.article?.id || null,
        quantite_livree: item.quantite_demandee || 0
    }))
});

const updateStatus = (newStatus: string) => {
    statusForm.status = newStatus;
    
    // Si on livre, on vérifie que tous les articles sont liés
    if (newStatus === 'livree') {
        const hasUnlinked = statusForm.items.some(item => !item.article_id);
        if (hasUnlinked) {
            alert('Veuillez lier tous les articles demandés à des articles du stock avant de livrer.');
            return;
        }
    }

    statusForm.post(`/demandes-approvisionnement/${props.demande.id}/status`, {
        preserveScroll: true
    });
};

const getStatusConfig = (status: string) => {
    switch (status) {
        case 'en_attente':
            return { label: 'En attente', color: 'bg-amber-100 text-amber-700 border-amber-200', icon: Clock };
        case 'approuvee':
            return { label: 'Approuvée', color: 'bg-blue-100 text-blue-700 border-blue-200', icon: CheckCircle2 };
        case 'rejetee':
            return { label: 'Rejetée', color: 'bg-rose-100 text-rose-700 border-rose-200', icon: XCircle };
        case 'livree':
            return { label: 'Livrée', color: 'bg-emerald-100 text-emerald-700 border-emerald-200', icon: Truck };
        default:
            return { label: status || 'Inconnu', color: 'bg-gray-100 text-gray-700 border-gray-200', icon: Clock };
    }
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return '-';
    try {
        return new Date(dateString).toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return dateString;
    }
};

const getSelectedArticleStock = (articleId: number | string | null) => {
    if (!articleId) return 0;
    const art = (props.articles || []).find(a => a.id === Number(articleId));
    return art ? art.stock_actuel : 0;
};
</script>

<template>
    <Head :title="`Demande n°${demande?.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div v-if="demande" class="p-6 max-w-6xl mx-auto">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" @click="router.visit('/demandes-approvisionnement')" class="rounded-full">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-bold text-gray-900">Demande d'approvisionnement</h1>
                            <Badge :class="['px-3 py-1 border flex items-center gap-1.5 font-bold', getStatusConfig(demande.status).color]">
                                <component :is="getStatusConfig(demande.status).icon" class="h-3.5 w-3.5" />
                                {{ getStatusConfig(demande.status).label }}
                            </Badge>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 font-mono uppercase tracking-widest text-blue-600 font-bold">REQ-{{ String(demande.id).padStart(5, '0') }}</p>
                    </div>
                </div>

                <div v-if="isResponsableStock && (demande.status === 'en_attente' || demande.status === 'approuvee')" class="flex items-center gap-2">
                    <template v-if="demande.status === 'en_attente'">
                        <Button variant="outline" class="text-rose-600 hover:bg-rose-50 border-rose-200 font-bold" @click="updateStatus('rejetee')">
                            Rejeter
                        </Button>
                        <Button class="bg-blue-600 hover:bg-blue-700 font-bold" @click="updateStatus('approuvee')">
                            Approuver
                        </Button>
                    </template>
                    <template v-if="demande.status === 'approuvee' || demande.status === 'en_attente'">
                         <Button class="bg-emerald-600 hover:bg-emerald-700 flex items-center gap-2 shadow-sm font-bold" @click="updateStatus('livree')">
                            <Truck class="h-4 w-4" /> Valider la livraison
                        </Button>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-3 space-y-6">
                    <!-- Articles -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/30 flex items-center justify-between">
                            <h3 class="font-bold text-gray-900 flex items-center gap-2">
                                <Package class="h-4 w-4 text-blue-600" /> Articles demandés
                            </h3>
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ demande.items?.length || 0 }} Ligne(s)</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50/30">
                                    <tr>
                                        <th class="px-6 py-3 text-[10px] font-black text-gray-500 uppercase tracking-wider">Article / Désignation</th>
                                        <th class="px-6 py-3 text-[10px] font-black text-gray-500 uppercase tracking-wider text-center">En Stock</th>
                                        <th class="px-6 py-3 text-[10px] font-black text-gray-500 uppercase tracking-wider text-center">Qté Demandée</th>
                                        <th v-if="isResponsableStock && demande.status !== 'livree' && demande.status !== 'rejetee'" class="px-6 py-3 text-[10px] font-black text-gray-500 uppercase tracking-wider text-center w-32">Qté Livrée</th>
                                        <th v-if="demande.status === 'livree' || demande.status === 'rejetee'" class="px-6 py-3 text-[10px] font-black text-gray-500 uppercase tracking-wider text-center">Qté Livrée</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(item, index) in demande.items" :key="item.id" class="group transition-colors hover:bg-gray-50/20">
                                        <td class="px-8 py-8">
                                            <div class="flex flex-col gap-6">
                                                <!-- Libellé du demandeur -->
                                                <div class="flex items-start gap-4">
                                                    <div class="h-10 w-10 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 shrink-0 shadow-sm">
                                                        <MessageSquare class="h-5 w-5" />
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1">Article demandé par l'agent :</span>
                                                        <span class="text-base font-black text-gray-900 leading-tight">{{ item.designation }}</span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Link to Stock Article (Manager only with Hierarchy) -->
                                                <div v-if="isResponsableStock && (demande.status === 'en_attente' || demande.status === 'approuvee')" class="pl-14">
                                                    <div class="flex items-center gap-2 mb-3">
                                                        <div class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse"></div>
                                                        <span class="text-[11px] font-black text-blue-600 uppercase tracking-widest">Liaison avec l'inventaire :</span>
                                                    </div>
                                                    <HierarchicalArticleSelector 
                                                        v-model="statusForm.items[index].article_id"
                                                        :articles="props.articles"
                                                        :designation="item.designation"
                                                        class="max-w-md"
                                                    />
                                                </div>
                                                <!-- Saved Link (Read-only) -->
                                                <div v-else-if="item.article" class="pl-11">
                                                     <div class="flex items-start gap-3 p-3 bg-gray-50 border border-gray-100 rounded-lg">
                                                        <div class="h-8 w-8 rounded bg-white border border-gray-200 flex items-center justify-center text-gray-400 shrink-0">
                                                            <Package class="h-4 w-4" />
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="text-xs font-bold text-gray-900">{{ item.article.description }}</span>
                                                            <span class="text-[10px] text-gray-400 font-mono mt-0.5">{{ item.article.code }}</span>
                                                            <div class="flex items-center gap-1 mt-1">
                                                                <Badge variant="outline" class="text-[9px] h-4 px-1 bg-white border-gray-200 text-gray-500 font-medium">
                                                                    {{ item.article.sous_categorie?.categorie?.famille?.nom }}
                                                                </Badge>
                                                                <span class="text-gray-300">/</span>
                                                                <Badge variant="outline" class="text-[9px] h-4 px-1 bg-white border-gray-200 text-gray-500 font-medium">
                                                                    {{ item.article.sous_categorie?.nom }}
                                                                </Badge>
                                                            </div>
                                                        </div>
                                                     </div>
                                                </div>
                                                <div v-else-if="demande.status === 'livree'" class="pl-11 text-[11px] text-rose-500 font-bold italic">Aucun article lié lors de la livraison</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-6 text-center">
                                            <template v-if="isResponsableStock && (demande.status === 'en_attente' || demande.status === 'approuvee')">
                                                <Badge v-if="statusForm.items[index]?.article_id" variant="outline" :class="['text-xs font-black px-2 py-1', getSelectedArticleStock(statusForm.items[index]?.article_id) <= 0 ? 'bg-red-50 text-red-700 border-red-100' : 'bg-emerald-50 text-emerald-700 border-emerald-100']">
                                                    {{ getSelectedArticleStock(statusForm.items[index]?.article_id) }}
                                                </Badge>
                                                <span v-else class="text-xs text-gray-300">-</span>
                                            </template>
                                            <template v-else-if="item.article">
                                                <Badge variant="outline" class="text-xs font-black px-2 py-1 bg-gray-50 text-gray-600 border-gray-200">
                                                    {{ item.article.stock_actuel }}
                                                </Badge>
                                            </template>
                                            <span v-else class="text-xs text-gray-300">-</span>
                                        </td>
                                        <td class="px-6 py-6 text-center">
                                            <span class="text-sm font-black text-gray-700 px-4 py-2 bg-gray-100 rounded-lg">{{ item.quantite_demandee }}</span>
                                        </td>
                                        <td v-if="isResponsableStock && (demande.status === 'en_attente' || demande.status === 'approuvee')" class="px-6 py-6 text-center">
                                            <div class="flex flex-col items-center gap-1.5">
                                                <input 
                                                    v-if="statusForm.items[index]"
                                                    type="number" 
                                                    v-model.number="statusForm.items[index].quantite_livree"
                                                    min="0"
                                                    class="w-20 h-10 rounded-lg border-gray-300 text-sm text-center font-black text-emerald-600 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm"
                                                />
                                                <span v-if="statusForm.items[index]?.article_id" class="text-[10px] text-gray-400 font-bold">Max: {{ getSelectedArticleStock(statusForm.items[index]?.article_id) }}</span>
                                            </div>
                                        </td>
                                        <td v-else-if="demande.status === 'livree' || demande.status === 'rejetee'" class="px-6 py-6 text-center">
                                            <span class="text-sm font-black" :class="item.quantite_livree > 0 ? 'text-emerald-600' : 'text-gray-400'">
                                                {{ item.quantite_livree }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Motif -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2 mb-4">
                            <MessageSquare class="h-4 w-4 text-blue-600" /> Motif de la demande
                        </h3>
                        <p class="text-sm text-gray-600 leading-relaxed bg-gray-50/50 p-6 rounded-xl border border-dashed border-gray-200 italic">
                            {{ demande.motif || 'Aucun motif de demande spécifié.' }}
                        </p>
                    </div>
                </div>

                <!-- Sidebar Info -->
                <div class="space-y-6">
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-6">
                        <h3 class="font-bold text-gray-900 border-b border-gray-100 pb-3">Informations</h3>
                        
                        <div class="space-y-6">
                            <div class="flex items-start gap-4">
                                <div class="p-2.5 bg-gray-100 rounded-xl text-gray-400">
                                    <User class="h-5 w-5" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-gray-400 uppercase tracking-widest font-black mb-1">Demandeur</span>
                                    <span class="text-sm font-bold text-gray-800">{{ demande.user?.name || 'Inconnu' }}</span>
                                </div>
                            </div>

                            <div class="flex items-start gap-4">
                                <div class="p-2.5 bg-gray-100 rounded-xl text-gray-400">
                                    <Calendar class="h-5 w-5" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-gray-400 uppercase tracking-widest font-black mb-1">Date de demande</span>
                                    <span class="text-sm font-bold text-gray-800">{{ formatDate(demande.date_demande) }}</span>
                                </div>
                            </div>

                            <div v-if="demande.traitee_par_user" class="flex items-start gap-4 pt-6 border-t border-gray-50">
                                <div class="p-2.5 bg-blue-50 rounded-xl text-blue-600">
                                    <CheckCircle2 class="h-5 w-5" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-blue-400 uppercase tracking-widest font-black mb-1">Traitée par</span>
                                    <span class="text-sm font-bold text-gray-800">{{ demande.traitee_par_user.name }}</span>
                                    <span class="text-xs text-gray-400 mt-1 font-medium">{{ formatDate(demande.date_traitement) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Procédure (Commentée par l'utilisateur mais utile pour le contexte si besoin de réactiver) -->
                    <!-- <div v-if="isResponsableStock && demande.status !== 'livree' && demande.status !== 'rejetee'" class="bg-blue-600 rounded-xl p-6 text-white shadow-lg space-y-4">
                        <div class="flex items-center gap-2 font-black uppercase text-xs tracking-widest">
                            <AlertTriangle class="h-5 w-5 text-amber-300" />
                            Aide au traitement
                        </div>
                        <ul class="text-[11px] font-medium opacity-90 space-y-3">
                            <li class="flex gap-2">
                                <span class="h-4 w-4 rounded-full bg-white/20 flex items-center justify-center text-[10px] shrink-0">1</span>
                                Cliquez sur "Lier" pour chaque article.
                            </li>
                            <li class="flex gap-2">
                                <span class="h-4 w-4 rounded-full bg-white/20 flex items-center justify-center text-[10px] shrink-0">2</span>
                                Suivez la cascade Famille > Categorie > Article.
                            </li>
                            <li class="flex gap-2">
                                <span class="h-4 w-4 rounded-full bg-white/20 flex items-center justify-center text-[10px] shrink-0">3</span>
                                Ajustez la "Qté Livrée" selon le stock réel.
                            </li>
                        </ul>
                    </div> -->
                </div>
            </div>
        </div>
        <div v-else class="p-12 text-center text-gray-500">
            Chargement de la demande...
        </div>
    </AppLayout>
</template>
