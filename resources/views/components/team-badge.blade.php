@props(['team'])
<span class="team-badge" style="--team-color: {{ \App\Support\TeamTheme::color($team) }}; color: {{ \App\Support\TeamTheme::textColor($team) }}">{{ $team }}</span>
