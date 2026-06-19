<script setup>
import { ref } from 'vue';
import { usePollStore } from '@/stores/usePollStore';

const props = defineProps({
    // Si on passe un sondage existant, on est en mode édition
    poll: { type: Object, default: null }
});

const emit = defineEmits(['done']); // événement pour dire au parent qu'on a fini

const { createPoll, updatePoll } = usePollStore();

const isEditMode = !!props.poll; // true si on modifie, false si on crée
//!! va convertir une valeur en booléen

// Les champs du formulaire, pré-remplis si on est en mode édition
const title    = ref(props.poll?.title ?? ''); //?. c'est l'opérateur d'accès optionnel donc ca évite l'erreur si jamais props.poll est null
const question = ref(props.poll?.question ?? '');
const options  = ref(props.poll?.options?.map(o => o.label) ?? ['', '']); // minimum 2 options
const isDraft               = ref(props.poll?.is_draft ?? true);
const allowMultipleChoices  = ref(props.poll?.allow_multiple_choices ?? false);
const resultsPublic         = ref(props.poll?.results_public ?? false);
const duration              = ref(props.poll?.duration ?? null);

const error      = ref(null);
const isLoading  = ref(false);

// Ajoute une option vide à la liste
function addOption() {
    options.value.push('');//push('') ajoute un champ vide à la fin
}

// Supprime une option à l'index donné (minimum 2 options)
function removeOption(index) {
    if (options.value.length > 2) {//empêche de descendre sous 2 options
        options.value.splice(index, 1);
        //splice(index, 1) retire 1 élément à la position index
    }
}

// Soumet le formulaire
async function submit() {
    error.value = null;
    isLoading.value = true;

    // Les données qu'on envoie à l'API
    const data = {
        title:                   title.value || null,//si l'utilisateur laisse le champ vide ca va valoir champ vide donc retourne null
        question:                question.value,
        options:                 options.value,
        is_draft:                isDraft.value,
        allow_multiple_choices:  allowMultipleChoices.value,
        results_public:          resultsPublic.value,
        duration:                duration.value ? parseInt(duration.value) : null,
        //si l'utilisateur a tapé une durée, on la convertit en nombre entier
    };

    try {
        if (isEditMode) {
            await updatePoll(props.poll.id, data);
        } else {
            await createPoll(data);
        }
        emit('done'); // dit au parent qu'on a fini, il peut fermer le formulaire
    } catch (err) {
        error.value = err.data?.message ?? 'Une erreur est survenue.';
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-6">
        <h2 class="text-xl font-bold dark:text-white mb-6">
            {{ isEditMode ? 'Modifier le sondage' : 'Créer un sondage' }}
        </h2>

        <!-- Message d'erreur -->
        <p v-if="error" class="text-red-500 text-sm mb-4">{{ error }}</p>

        <!-- Titre (optionnel) -->
        <div class="mb-4">
            <label class="block text-sm font-medium dark:text-gray-300 mb-1">
                Titre (optionnel)
            </label>
            <input
                v-model="title"
                type="text"
                placeholder="Titre du sondage"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-slate-700 dark:text-white"
            />
        </div>

        <!-- Question -->
        <div class="mb-4">
            <label class="block text-sm font-medium dark:text-gray-300 mb-1">
                Question *
            </label>
            <input
                v-model="question"
                type="text"
                placeholder="Quelle est ta question ?"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-slate-700 dark:text-white"
            />
        </div>

        <!-- Options -->
        <div class="mb-4">
            <label class="block text-sm font-medium dark:text-gray-300 mb-1">
                Options (minimum 2)
            </label>

            <div
                v-for="(option, index) in options"
                :key="index"
                class="flex gap-2 mb-2"
            >
                <input
                    v-model="options[index]"
                    type="text"
                    :placeholder="'Option ' + (index + 1)"
                    class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-slate-700 dark:text-white"
                />
                <!-- Bouton supprimer option, désactivé si seulement 2 options -->
                <button
                    @click="removeOption(index)"
                    :disabled="options.length <= 2"
                    class="px-3 py-2 bg-red-500 text-white rounded disabled:opacity-30"
                >
                    ✕
                </button>
            </div>

            <button
                @click="addOption"
                class="mt-1 text-sm text-teal-600 dark:text-teal-400 hover:underline"
            >
                + Ajouter une option
            </button>
        </div>

        <!-- Paramètres -->
        <div class="mb-6 space-y-2">
            <label class="flex items-center gap-2 dark:text-gray-300">
                <input type="checkbox" v-model="isDraft" />
                Brouillon (ne pas lancer maintenant)
            </label>
            <label class="flex items-center gap-2 dark:text-gray-300">
                <input type="checkbox" v-model="allowMultipleChoices" />
                Autoriser plusieurs choix
            </label>
            <label class="flex items-center gap-2 dark:text-gray-300">
                <input type="checkbox" v-model="resultsPublic" />
                Résultats publics
            </label>
        </div>

        <!-- Durée en secondes (optionnel) -->
        <div class="mb-6">
            <label class="block text-sm font-medium dark:text-gray-300 mb-1">
                Durée en secondes (optionnel)
            </label>
            <input
                v-model="duration"
                type="number"
                min="1"
                placeholder="ex: 3600 pour 1 heure"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-slate-700 dark:text-white"
            />
        </div>

        <!-- Boutons -->
        <div class="flex justify-between">
            <button
                @click="emit('done')"
                class="px-4 py-2 bg-gray-200 dark:bg-gray-700 dark:text-white rounded hover:bg-gray-300"
            >
                Annuler
            </button>
            <button
                @click="submit"
                :disabled="isLoading"
                class="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700 disabled:opacity-50"
            >
                {{ isLoading ? 'Envoi...' : (isEditMode ? 'Sauvegarder' : 'Créer') }}
            </button>
        </div>
    </div>
</template>