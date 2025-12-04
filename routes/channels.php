<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('notifications.{id}', function ($user, $id) {
    // Handle both UUID strings and integer IDs
    return (string) $user->id === (string) $id;
});
