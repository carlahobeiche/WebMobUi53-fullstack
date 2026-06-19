<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { useFetchApi } from './composables/useFetchApi';

// Le token est passé par poll-vote.js depuis le HTML
const props = defineProps({
    token: { type: String, required: true }
});

const { fetchApi } = useFetchApi();

const poll = ref(null);// Les données du sondage chargées depuis l'API
const selectedOptionIds = ref([]);// Les options cochées par l'utilisateur avant de voter
const message = ref(null);// Message d'erreur ou de succès à afficher
const isSubmitting = ref(false);// Est-ce qu'on est en train d'envoyer le vote ?

// URL de partage du sondage, construite à partir de l'URL actuelle
const shareUrl = window.location.href;//on le calcule pas avec un computed psq window.location.href ne changera jamais pendant qu'on est sur la page l'url est fixe
//pas un ref c une constante

// Charge le sondage depuis l'API via son token
async function loadPoll() {
    try {
        const data = await fetchApi({ url: `/polls/${props.token}` });
        poll.value = data;
    } catch (err) {
        message.value = { type: 'error', text: 'Sondage introuvable.' };
    }
}

// Le sondage est-il terminé ? (date de fin dépassée)
const isEnded = computed(() => {
    if (!poll.value?.ends_at) return false;//si poll n'est pas encore chargé (null) OU si le sondage n'a pas de date de fin (ends_at est null), on retourne false
    return new Date(poll.value.ends_at) < new Date();
});

// L'utilisateur a-t-il déjà voté ?
const hasVoted = computed(() => {
    return poll.value?.user_vote_option_ids?.length > 0;
});

// Peut-on encore voter ?
const canVote = computed(() => {//canVote est computed pour que le résultat soit en cache 
    if (!poll.value) return false;
    if (poll.value.is_draft) return false;
    if (isEnded.value) return false;
    if (hasVoted.value) return false;
    return true;
});//canVote utilise d'autres computed


// Gère la sélection d'une option
function toggleOption(optionId) {
    if (!poll.value.allow_multiple_choices) {
        // si choix unique : on remplace entièrement selectedOptionIds par un tableau ne contenant que cette seule option qu'on vient de cliquer
        selectedOptionIds.value = [optionId];
    } else {
        // Choix multiple : on ajoute ou retire l'option
        // on vérifie si l'option est déjà dans le tableau
        const index = selectedOptionIds.value.indexOf(optionId);
        if (index === -1) {
            selectedOptionIds.value.push(optionId);//Si absente, on l'ajoute (push)
        } else {
            selectedOptionIds.value.splice(index, 1);//Si déjà présente, on la retire (splice)
        }
    }
}

// Envoie le vote à l'API
async function submitVote() {
    if (selectedOptionIds.value.length === 0) {
        message.value = { type: 'error', text: 'Sélectionne au moins une option.' };
        return;//Avant même d'appeler l'API, on vérifie côté frontend que l'utilisateur a coché au moins une option
    }

    isSubmitting.value = true;
    message.value = null;

    try {//Appel API
        await fetchApi({
            url: `/polls/${props.token}/vote`,
            method: 'POST',
            data: { option_ids: selectedOptionIds.value }
        });
        message.value = { type: 'success', text: 'Vote enregistré !' };
        await loadPoll(); // recharge le sondage pour voir les résultats
    } catch (err) {
        message.value = { type: 'error', text: err.data?.message ?? 'Une erreur est survenue.' };
    } finally {
        isSubmitting.value = false;
    }
}

// Calcule le total des votes pour le graphique
const totalVotes = computed(() => {
    if (!poll.value) return 0;
    return poll.value.options.reduce((sum, o) => sum + (o.votes_count ?? 0), 0);
});

// Polling : recharge les résultats toutes les 5 secondes
const pollingInterval = setInterval(() => {
//pollingInterval stocke un identifiant numérique unique pour CET interval précis
    if (poll.value) loadPoll();
}, 5000);

// Arrête le polling quand on quitte la page
onUnmounted(() => clearInterval(pollingInterval));//si on enlève ca, l'interval continue de tourner après que l'utilisateur ait quitté la page

// Chargement initial
loadPoll();
</script>

<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-900 p-4">
        <div class="max-w-xl mx-auto">

            <!-- Chargement -->
            <p v-if="!poll && !message" class="text-center text-gray-500 mt-10">
                Chargement...
            </p>

            <!-- Erreur de chargement -->
            <p v-if="message && !poll" class="text-center text-red-500 mt-10">
                {{ message.text }}
            </p>

            <!-- Contenu du sondage -->
            <div v-if="poll" class="bg-white dark:bg-slate-800 rounded-lg shadow p-6">

                <!-- Titre et question -->
                <h1 v-if="poll.title" class="text-2xl font-bold dark:text-white mb-1">
                    {{ poll.title }}
                </h1>
                <p class="text-lg dark:text-gray-200 mb-6">{{ poll.question }}</p>

                <!-- Sondage en brouillon -->
                <p v-if="poll.is_draft" class="text-yellow-600 font-semibold">
                    Ce sondage n'est pas encore ouvert.
                </p>

                <!-- Sondage terminé -->
                <p v-else-if="isEnded" class="text-red-500 font-semibold">
                    Ce sondage est terminé.
                </p>

                <!-- Formulaire de vote -->
                <div v-else-if="canVote">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                        {{ poll.allow_multiple_choices ? 'Plusieurs choix possibles' : 'Un seul choix possible' }}
                    </p>

                    <div class="space-y-2 mb-4">
                        <button
                            v-for="option in poll.options"
                            :key="option.id"
                            @click="toggleOption(option.id)"
                            :class="[
                                'w-full text-left px-4 py-2 rounded border transition',
                                selectedOptionIds.includes(option.id)
                                    ? 'bg-teal-600 text-white border-teal-600'
                                    : 'bg-white dark:bg-slate-700 dark:text-white border-gray-300 dark:border-gray-600'
                            ]"
                        >
                            {{ option.label }}
                        </button>
                    </div>

                    <button
                        @click="submitVote"
                        :disabled="isSubmitting"
                        class="w-full py-2 bg-teal-600 text-white rounded hover:bg-teal-700 disabled:opacity-50"
                    >
                        {{ isSubmitting ? 'Envoi...' : 'Voter' }}
                    </button>
                </div>

                <!-- Déjà voté -->
                <p v-else-if="hasVoted" class="text-green-600 font-semibold mb-4">
                    Vous avez déjà voté.
                </p>

                <!-- Message retour (succès ou erreur) -->
                <p v-if="message && poll" :class="[
                    'mt-4 text-sm font-semibold',
                    message.type === 'error' ? 'text-red-500' : 'text-green-600'
                ]">
                    {{ message.text }}
                </p>

                <!-- Résultats (visibles si publics ou si créateur) -->
                <div v-if="poll.options[0]?.votes_count !== null" class="mt-6"> 
                     <!-- l'api retourne votes_count : null quand les résultats sont privés et que l'util n'est pas le créateur -->
                    <h2 class="text-lg font-semibold dark:text-white mb-3">Résultats</h2>

                    <div class="space-y-2">
                        <div v-for="option in poll.options" :key="option.id">
                            <div class="flex justify-between text-sm dark:text-gray-300 mb-1">
                                <span>{{ option.label }}</span>
                                <span>{{ option.votes_count }} vote{{ option.votes_count !== 1 ? 's' : '' }}</span>
                            </div>
                            <!-- Barre de progression = graphique simple -->
                            <div class="w-full bg-gray-200 dark:bg-slate-600 rounded h-4">
                                <div
                                    class="bg-teal-500 h-4 rounded transition-all duration-500"
                                    :style="{
                                        width: totalVotes > 0
                                            ? (option.votes_count / totalVotes * 100) + '%'
                                            : '0%'
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        Total : {{ totalVotes }} vote{{ totalVotes !== 1 ? 's' : '' }}
                    </p>
                </div>

                <!-- Résultats non publics -->
                <p v-else class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                    Les résultats ne sont pas publics.
                </p>

                <!-- Lien de partage (visible uniquement par le créateur) -->
                <div v-if="poll.is_owner" class="mt-6 p-3 bg-gray-100 dark:bg-slate-700 rounded">
                    <p class="text-sm font-semibold dark:text-white mb-1">Lien de partage :</p>
<p class="text-sm text-teal-600 dark:text-teal-400 break-all">
    {{ shareUrl }}
</p>
                </div>

            </div>
        </div>
    </div>
</template>