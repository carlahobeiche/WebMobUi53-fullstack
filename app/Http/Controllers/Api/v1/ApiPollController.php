<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiPollController extends Controller
{
    //liste tous les sondages de l'utilisateur connecté
    public function index(Request $request)
    {
        $polls = $request->user()//on récupère l'utilisateur
            ->polls()//apelle la relation définie dans user.php
            ->with(['options' => fn($q) => $q->withCount('votes')])
            //with(['options' charge les options de chaque sondage
            //$q->withCount('votes') dit : en plus de charger les options, compte aussi le nombre de votes pour chacune et ajoute ce nombre comme un champ votes_count
            //= permet de récupérer le nombre de votes de chaque option en une seule requête SQL globale, plutôt que de devoir compter en boucle
            ->orderBy('created_at', 'desc')
            ->get();//exécute la requête

        return $polls;
        //on a utilisé with() psq ca fait qu'1 requête qui va charger toutes les options des sondages d'un coup ( eager loading )
    }

    // Crée un nouveau sondage avec ses options
    public function store(Request $request) // quand on utilise store on s'assure que ces 3 critères sont présents 
    {// on a ajouté store parce que sinon quand l'utilisateur appuie sur créer les données ne peuvent pas être envoyées correctement 
        // 1. On valide les données envoyées par le frontend
        $validated = $request->validate([
            'title'                  => 'nullable|string|max:255',
            'question'               => 'required|string|max:255',
            'options'                => 'required|array|min:2',//le champ options doit exister, être un tableau, et contenir au moins 2 éléments
            'options.*'              => 'required|string|max:255',//l'étoile (*) veut dire "chaque élément du tableau options
            //chacun doit être une chaîne de texte non vide, max 255 caractères
            'is_draft'               => 'boolean',
            'allow_multiple_choices' => 'boolean',
            'allow_vote_change'      => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1',
        ]);

        // On crée le sondage et on remplit ses champs
        $poll = new Poll();//dabord un crée un objet Poll vide puis on assigne chaque colonne 1 par 1
        $poll->user_id                = $request->user()->id;
        $poll->title                  = $validated['title'] ?? null;
        $poll->question               = $validated['question'];
        $poll->secret_token           = Str::random(32); // 2. token aléatoire unique pour le lien de partage
        $poll->is_draft               = $validated['is_draft'] ?? true;//?? veut dire : si le frontend n'a pas envoyé is_draft du tout, on met true par défaut
        $poll->allow_multiple_choices = $validated['allow_multiple_choices'] ?? false;
        $poll->allow_vote_change      = $validated['allow_vote_change'] ?? false;
        $poll->results_public         = $validated['results_public'] ?? false;
        $poll->duration               = $validated['duration'] ?? null;

        // 3. Si le sondage est lancé directement (pas en brouillon), on note l'heure de début et de fin
        if (!$poll->is_draft) {
            $poll->started_at = now();
            if ($poll->duration) {
                $poll->ends_at = now()->addSeconds($poll->duration);
            }
        }//si l'util a décoché brouillon dès la création on va calculer direct started at et si ya la durée le ends at 

        $poll->save();

        // On crée chaque option du sondage en base de données
        foreach ($validated['options'] as $label) {//on boucle sur le tableau d'options et pour chacune on appelle create()
            $poll->options()->create(['label' => $label]);
        }

        return response()->json($poll->load('options'), 201);
    }//$poll->load('options') recharge le sondage avec ses options fraîchement créées

    // Affiche un sondage via son token (accessible sans être connecté)
    public function show(Request $request, string $token)
    {
        //là on commence par trouver le sondage par token
        $poll = Poll::with(['options' => fn($q) => $q->withCount('votes')])//Poll:: cv dire on part direct de la classe
        //`fn($q) => $q->withCount('votes')` veut dire :"pendant que tu charges les options, compte aussi les votes de chacune et attache ce nombre
            ->where('secret_token', $token)//filtre sur le token reçu dans l'url
            ->first();//va prendre le premier résultat

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $user        = $request->user();//retourne null si personne n'est connecté
        $isOwner     = $user && $user->id === $poll->user_id;
        // Les résultats (nombre de votes) ne sont visibles que si c'est public ou si c'est le créateur
        $showResults = $poll->results_public || $isOwner; //soit c'est public, soit c'est le créateur qui regarde, sinon les votes restent cachés (null).

        $options = $poll->options->map(fn($option) => [//transforme chaque élément d'une collection en quelque chose d'autre
        //ici, chaque $option devient un petit tableau avec 3 champs choisis 
            'id'          => $option->id,
            'label'       => $option->label,
            'votes_count' => $showResults ? $option->votes_count : null,
        ]);//donc map()pour remplacer manuellement votes_count par null si showResults est false

        // Les options pour lesquelles l'utilisateur connecté a déjà voté (null si pas connecté)
        $userVoteOptionIds = null;
        if ($user) {
            $userVoteOptionIds = PollVote::where('poll_id', $poll->id)
                ->where('user_id', $user->id)
                ->pluck('poll_option_id')
                ->toArray();
        }

        return response()->json([
            'id'                     => $poll->id,
            'title'                  => $poll->title,
            'question'               => $poll->question,
            'secret_token'           => $poll->secret_token,
            'is_draft'               => $poll->is_draft,
            'allow_multiple_choices' => $poll->allow_multiple_choices,
            'allow_vote_change'      => $poll->allow_vote_change,
            'results_public'         => $poll->results_public,
            'duration'               => $poll->duration,
            'started_at'             => $poll->started_at,
            'ends_at'                => $poll->ends_at,
            'user_id'                => $poll->user_id,
            'is_owner'               => $isOwner,
            'options'                => $options,
            'user_vote_option_ids'   => $userVoteOptionIds,
        ]);
    }

    // Modifie un sondage existant
    public function update(Request $request, int $id)
    {
        // On cherche le sondage ET on vérifie que c'est bien le créateur qui fait la demande
        $poll = Poll::where('id', $id)//le fait d'avoir mis 2 where a la suite ca agit comme un ET
            ->where('user_id', $request->user()->id)
            ->firstOrFail();//lève auto une erreur 404 si les 2 where ont pas de résultats

        $validated = $request->validate([
            'title'                  => 'nullable|string|max:255',
            'question'               => 'required|string|max:255',
            'options'                => 'sometimes|array|min:2',
            'options.*'              => 'required|string|max:255',
            'is_draft'               => 'boolean',
            'allow_multiple_choices' => 'boolean',
            'allow_vote_change'      => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1',
        ]);
        // l'état AVANT la modification
        $wasDraft   = $poll->is_draft; //On garde la valeur avant modification.
        
        //l'état APRÈS la modification (ce que l'utilisateur vient d'envoyer)
        $isNowDraft = $validated['is_draft'] ?? $poll->is_draft;//Ça regarde ce que l'utilisateur a envoyé dans le formulaire. 
        // Si le formulaire envoie is_draft:false (il a décoché Brouillon), alors $isNowDraft vaut false.

        $poll->title                  = $validated['title'] ?? null;
        $poll->question               = $validated['question'];
        $poll->is_draft               = $isNowDraft;
        $poll->allow_multiple_choices = $validated['allow_multiple_choices'] ?? $poll->allow_multiple_choices;
        //ici dans update on veut garder la valeur existante si le champ n'est pas envoyé
        $poll->allow_vote_change      = $validated['allow_vote_change'] ?? $poll->allow_vote_change;
        $poll->results_public         = $validated['results_public'] ?? $poll->results_public;
        $poll->duration               = $validated['duration'] ?? null;

        // Si le sondage vient d'être lancé pour la première fois, on recalcule les dates heures
        if ($wasDraft && !$isNowDraft) {
            $poll->started_at = now();
            if ($poll->duration) {
                $poll->ends_at = now()->addSeconds($poll->duration);
            }
        }

        $poll->save();

        // Les options peuvent pas être modifiées que si le sondage était encore en brouillon
        // Une fois lancé, des gens ont peut-être voté sur ces options, on ne peut plus les changer
        if ($wasDraft && isset($validated['options'])) {
            //isset($validated['options']) vérifie que le frontend a bien envoyé des options dans cette requête
            $poll->options()->delete();
            foreach ($validated['options'] as $label) {
                $poll->options()->create(['label' => $label]);
            }
        }//si sondage deja lancé ( if ($wasDraft && isset($validated['options']))) devient fausse donc le bloc entier saute silencieusement  

        return $poll->load('options');//on passe directement à là si la condition est fausse
    }

    // Enregistre le vote d'un utilisateur sur un sondage
    public function vote(Request $request, string $token)
    {
        $poll = Poll::with('options')->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        // On refuse le vote si le sondage est brouillon
        if ($poll->is_draft) {
            return response()->json(['message' => 'Ce sondage n\'est pas encore ouvert.'], 403);
        }

        // On refuse le vote si la date de fin est dépassée
        if ($poll->ends_at && now()->isAfter($poll->ends_at)) {
            return response()->json(['message' => 'Ce sondage est terminé.'], 403);
        }

        $user = $request->user();

        // On vérifie que l'utilisateur n'a pas déjà voté
        $existingVotes = PollVote::where('poll_id', $poll->id)
            ->where('user_id', $user->id)
            ->get();

        if ($existingVotes->isNotEmpty()) {//isNotEmpty() vérifie si la collection contient au moins un élément
            return response()->json(['message' => 'Vous avez déjà voté.'], 403);
        }

        $validated = $request->validate([
            'option_ids'   => 'required|array|min:1',
            'option_ids.*' => 'integer',
        ]);

        // Pour un sondage à choix unique, on refuse si l'utilisateur envoie plusieurs options
        if (!$poll->allow_multiple_choices && count($validated['option_ids']) > 1) {
            return response()->json(['message' => 'Ce sondage n\'accepte qu\'un seul choix.'], 422);
        }

        // On vérifie que les options choisies appartiennent bien à ce sondage
        $validOptionIds = $poll->options->pluck('id')->toArray();
        //pluck('id') extrait juste les IDs des options qui appartiennent VRAIMENT à ce sondage
        foreach ($validated['option_ids'] as $optionId) {
            if (!in_array($optionId, $validOptionIds)) {//in_array() cherche si une valeur existe dans un tableau
                return response()->json(['message' => 'Option invalide.'], 422);
            }
        }

        // On enregistre un vote par option choisie
        foreach ($validated['option_ids'] as $optionId) {
            $vote = new PollVote();
            $vote->poll_id        = $poll->id;
            $vote->user_id        = $user->id;
            $vote->poll_option_id = $optionId;
            $vote->save();
        }
        //on fait une boucle foreach pour enregistrer les votes psq poll_option_id est un seul ID, pas un tableau. Une ligne dans cette table ne peut représenter qu'un seul choix à la fois
        //foreach :  boucle sur chaque ID dans option_ids, et crée une ligne distincte par option votée

        return response()->json(['message' => 'Vote enregistré.'], 201);
    }

    // Supprime un sondage (uniquement si on en est le créateur)
    public function remove(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $poll->delete();

        return response()->json(['message' => 'success'], 200);
    }
}