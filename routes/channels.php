<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Team;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int)$user->id === (int)$id;
});

Broadcast::channel('team.{teamId}.whatsapp', function ($user, $teamId) {
    return $user->belongsToTeam(Team::find($teamId));
});

Broadcast::channel('team.{teamId}.crm', function ($user, $teamId) {
    return $user->belongsToTeam(Team::find($teamId));
});