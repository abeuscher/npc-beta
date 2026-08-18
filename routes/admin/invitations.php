<?php

use App\Http\Controllers\Admin\InvitationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes — user invitations
|--------------------------------------------------------------------------
|
| Token-keyed invitation acceptance. Unauthenticated by design — the invitee
| has no account yet; the token is the credential.
|
*/

Route::get('/invitation/{token}', [InvitationController::class, 'show'])
    ->name('invitation.show');
Route::post('/invitation/{token}', [InvitationController::class, 'store'])
    ->name('invitation.store');
