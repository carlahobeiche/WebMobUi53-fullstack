<script setup>
import { usePollStore } from '@/stores/usePollStore';

const { polls, deletePoll } = usePollStore();
//On récupère polls et deletePoll directement depuis le store

const emit = defineEmits(['edit']); // dit au parent que le sondage veut être modifié
//defineEmits créé la fonction emit  
//defineEmits(['edit']) = c'est la liste des changements que cet enfant a reçu

// Copie le lien de partage dans le presse-papier
function copyLink(token) {
    const url = window.location.origin + '/polls/' + token;
    //window.location.origin retourne la base de l'URL actuelle
    navigator.clipboard.writeText(url);
    //navigator.clipboard.writeText() est une API du navigateur qui copie du texte dans le presse-papier
    alert('Lien copié !');
}
</script>

<template>
    <p v-if="polls.length === 0" class="text-gray-500 dark:text-gray-400">
        Aucun sondage.
    </p>

    <div v-else class="space-y-3">
        <div
            v-for="poll in polls"
            :key="poll.id"
            class="bg-white dark:bg-slate-800 rounded-lg shadow p-4"
        >
            <!-- Titre et question -->
            <div class="mb-2">
                <p class="font-semibold dark:text-white">
                    {{ poll.title || poll.question }}
                </p>
                <p v-if="poll.title" class="text-sm text-gray-500 dark:text-gray-400">
                    {{ poll.question }}
                </p>
            </div>

            <!-- Statut du sondage -->
            <p class="text-xs mb-3">
                <span :class="poll.is_draft ? 'text-yellow-500' : 'text-green-500'">
                    {{ poll.is_draft ? 'Brouillon' : 'Lancé' }}
                </span>
                <span v-if="poll.ends_at" class="text-gray-400 ml-2">
                    · Fin : {{ new Date(poll.ends_at).toLocaleString() }}
                </span>
            </p>

            <!-- Actions -->
            <div class="flex gap-2 flex-wrap">
                <!-- Modifier -->
                <button
                    @click="emit('edit', poll)" 
                    class="px-3 py-1 text-sm bg-gray-200 dark:bg-slate-700 dark:text-white rounded hover:bg-gray-300"
                >
                    Modifier
                </button>
                <!-- "emit('edit', poll)" = enfant envoie alerte au parent pour signaler l'envie de modif -->

                <!-- Copier le lien de partage -->
                <button
                    @click="copyLink(poll.secret_token)"
                    class="px-3 py-1 text-sm bg-teal-600 text-white rounded hover:bg-teal-700"
                >
                    Copier le lien
                </button>

                <!-- Supprimer -->
                <button
                    @click="deletePoll(poll.id)"
                    class="px-3 py-1 text-sm bg-red-500 text-white rounded hover:bg-red-600"
                >
                    Supprimer
                </button>
            </div>
        </div>
    </div>
</template>