<x-vue-app-layout>
    <x-slot:title>Sondage</x-slot>

    {{-- Charge le fichier JavaScript de l'app Vue de vote --}}
    <x-slot:scripts>
        @vite(['resources/js/poll-vote.js'])
    </x-slot>

    {{-- Le div que Vue va cibler pour monter l'app.
         data-token contient le token du sondage, Vue va le lire pour appeler l'API --}}
    <div id="app-vote" data-token="{{ $token }}"></div>
</x-vue-app-layout>