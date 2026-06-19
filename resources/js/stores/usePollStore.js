import { ref } from 'vue';
import { useFetchApi } from '@/composables/useFetchApi';

// La liste des sondages, partagée entre tous les composants qui utilisent ce store
const polls = ref([]);

export function usePollStore() {
    const { fetchApi } = useFetchApi();

    // Remplace toute la liste (appelé au chargement initial)
    function setPolls(data) {
        polls.value = data;
    }

    // Crée un sondage et l'ajoute en tête de liste
    async function createPoll(data) {
        const newPoll = await fetchApi({ url: 'polls', method: 'POST', data });//envoie une requête POST vers /api/v1/polls avec les données du formulaire
        polls.value.unshift(newPoll); // unshift = ajoute au début du tableau
        return newPoll;//contient le sondage tout juste créé
    }

    // Modifie un sondage et met à jour la liste
    async function updatePoll(id, data) {
        const updated = await fetchApi({ url: `polls/${id}`, method: 'PUT', data });
        const index = polls.value.findIndex(p => p.id === id);//findIndex pour trouver directement la position du sondage modifié
        if (index !== -1) polls.value[index] = updated;
        //if (index !== -1) vérifie qu'on a bien trouvé le sondage avant de continuer
        //polls.value[index] = updated remplace l'ancien sondage par la version mise à jour, à la même position dans le tableau
        return updated;
    }

    // Supprime un sondage et le retire de la liste
    async function deletePoll(id) {
        await fetchApi({ url: `polls/${id}`, method: 'DELETE' });
        polls.value = polls.value.filter(p => p.id !== id);
    }

    return { polls, setPolls, createPoll, updatePoll, deletePoll };
}