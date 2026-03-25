<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Allow all authenticated users to listen to scrape-runs channel
Broadcast::channel('scrape-runs', function ($user) {
    return $user !== null;
});
