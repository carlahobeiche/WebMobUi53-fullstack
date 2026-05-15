<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiPollController extends Controller
{
    // Retourne la liste des sondages de la personne connectée
    public function index(Request $request)
    {
        $polls = $request->user()
            ->polls()
            ->with(['options' => fn($q) => $q->withCount('votes')])
            ->orderBy('created_at', 'desc')
            ->get();

        return $polls;
    }

    // Crée un nouveau sondage avec ses options
    public function store(Request $request)
    {
        // On valide les données envoyées par le frontend
        $validated = $request->validate([
            'title'                  => 'nullable|string|max:255',
            'question'               => 'required|string|max:255',
            'options'                => 'required|array|min:2',
            'options.*'              => 'required|string|max:255',
            'is_draft'               => 'boolean',
            'allow_multiple_choices' => 'boolean',
            'allow_vote_change'      => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1',
        ]);

        // On crée le sondage et on remplit ses champs
        $poll = new Poll();
        $poll->user_id                = $request->user()->id;
        $poll->title                  = $validated['title'] ?? null;
        $poll->question               = $validated['question'];
        $poll->secret_token           = Str::random(32); // token aléatoire unique pour le lien de partage
        $poll->is_draft               = $validated['is_draft'] ?? true;
        $poll->allow_multiple_choices = $validated['allow_multiple_choices'] ?? false;
        $poll->allow_vote_change      = $validated['allow_vote_change'] ?? false;
        $poll->results_public         = $validated['results_public'] ?? false;
        $poll->duration               = $validated['duration'] ?? null;

        // Si le sondage est lancé directement (pas en brouillon), on note l'heure de début et de fin
        if (!$poll->is_draft) {
            $poll->started_at = now();
            if ($poll->duration) {
                $poll->ends_at = now()->addSeconds($poll->duration);
            }
        }

        $poll->save();

        // On crée chaque option du sondage en base de données
        foreach ($validated['options'] as $label) {
            $poll->options()->create(['label' => $label]);
        }

        return response()->json($poll->load('options'), 201);
    }

    // Affiche un sondage via son token (accessible sans être connecté)
    public function show(Request $request, string $token)
    {
        $poll = Poll::with(['options' => fn($q) => $q->withCount('votes')])
            ->where('secret_token', $token)
            ->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $user        = $request->user();
        $isOwner     = $user && $user->id === $poll->user_id;

        // Les résultats (nombre de votes) ne sont visibles que si c'est public ou si c'est le créateur
        $showResults = $poll->results_public || $isOwner;

        $options = $poll->options->map(fn($option) => [
            'id'          => $option->id,
            'label'       => $option->label,
            'votes_count' => $showResults ? $option->votes_count : null,
        ]);

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
        $poll = Poll::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

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

        $wasDraft   = $poll->is_draft;
        $isNowDraft = $validated['is_draft'] ?? $poll->is_draft;

        $poll->title                  = $validated['title'] ?? null;
        $poll->question               = $validated['question'];
        $poll->is_draft               = $isNowDraft;
        $poll->allow_multiple_choices = $validated['allow_multiple_choices'] ?? $poll->allow_multiple_choices;
        $poll->allow_vote_change      = $validated['allow_vote_change'] ?? $poll->allow_vote_change;
        $poll->results_public         = $validated['results_public'] ?? $poll->results_public;
        $poll->duration               = $validated['duration'] ?? null;

        // Si le sondage vient d'être lancé pour la première fois, on note l'heure
        if ($wasDraft && !$isNowDraft) {
            $poll->started_at = now();
            if ($poll->duration) {
                $poll->ends_at = now()->addSeconds($poll->duration);
            }
        }

        $poll->save();

        // Les options ne peuvent être modifiées que si le sondage était encore en brouillon.
        // Une fois lancé, des gens ont peut-être voté sur ces options, on ne peut plus les changer.
        if ($wasDraft && isset($validated['options'])) {
            $poll->options()->delete();
            foreach ($validated['options'] as $label) {
                $poll->options()->create(['label' => $label]);
            }
        }

        return $poll->load('options');
    }

    // Enregistre le vote d'un utilisateur sur un sondage
    public function vote(Request $request, string $token)
    {
        $poll = Poll::with('options')->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        // On refuse le vote si le sondage n'est pas encore lancé
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

        if ($existingVotes->isNotEmpty()) {
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
        foreach ($validated['option_ids'] as $optionId) {
            if (!in_array($optionId, $validOptionIds)) {
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