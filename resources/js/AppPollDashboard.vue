<script setup>
import { ref } from 'vue';
import PollTable from './components/PollTable.vue';
import PollForm from './components/PollForm.vue';
import { usePollStore } from '@/stores/usePollStore';

const props = defineProps({
    polls: { type: Array, default: () => [] }
});

const { setPolls } = usePollStore();
setPolls(props.polls); // charge la liste initiale des sondages

const currentView = ref('list');
//currentView est notre variable réactive qui détermine ce qui s'affiche : 'list', 'create', ou 'edit'

const pollToEdit = ref(null);
// Le sondage en cours d'édition (null si on est en mode création)


// Ouvre le formulaire de création
function showCreate() {
    pollToEdit.value = null;
    currentView.value = 'create';
}

// Ouvre le formulaire d'édition avec le sondage sélectionné
function showEdit(poll) {
    pollToEdit.value = poll;
    currentView.value = 'edit';
}//Cette fonction reçoit un poll en paramètre — c'est exactement la donnée envoyée par PollTable via emit('edit', poll)



// Retourne à la liste après création/édition/annulation
function showList() {
    currentView.value = 'list';
    pollToEdit.value = null;
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-900 p-4">
        <div class="max-w-2xl mx-auto">

            <!-- En-tête -->
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold dark:text-white">Mes sondages</h1>

                <!-- Bouton créer, visible uniquement sur la liste -->
                <button
                    v-if="currentView === 'list'"
                    @click="showCreate"
                    class="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700"
                >
                    + Créer un sondage
                </button>

                <!-- Bouton retour, visible sur le formulaire -->
                <button
                    v-else 
                    @click="showList"
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 dark:text-white rounded hover:bg-gray-300"
                >
                    ← Retour
                </button>
            </div>

            <!-- Liste des sondages -->
            <PollTable
                v-if="currentView === 'list'"
                @edit="showEdit"
            />

            <!-- Formulaire de création ou d'édition -->
            <PollForm
                v-else
                :poll="pollToEdit"
                @done="showList"
            />

        </div>
    </div>
</template>