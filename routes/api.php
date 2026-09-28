<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AddressBookController;
use App\Http\Controllers\Api\AliasController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\MailFolderController;
use App\Http\Controllers\Api\MailMessageController;
use App\Http\Controllers\Api\MailSendController;
use App\Http\Controllers\Api\ScheduledMailController;
use App\Http\Controllers\Api\SnoozeController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('me', [AccountController::class, 'me']);

    Route::get('me/mailbox', [AccountController::class, 'mailbox']);
    Route::put('me/mailbox/settings', [AccountController::class, 'updateSettings']);
    Route::get('me/mailbox/rules', [AccountController::class, 'rules']);
    Route::put('me/mailbox/rules', [AccountController::class, 'updateRules']);
    Route::put('me/password', [AccountController::class, 'changePassword']);
    Route::get('me/webmail', [AccountController::class, 'webmail']);

    Route::get('me/aliases', [AliasController::class, 'index']);
    Route::post('me/aliases', [AliasController::class, 'store']);
    Route::delete('me/aliases/{alias}', [AliasController::class, 'destroy']);

    Route::get('contacts', [ContactController::class, 'index']);
    Route::post('contacts/sync', [ContactController::class, 'sync']);

    Route::prefix('calendar')->group(function () {
        Route::get('calendars', [CalendarController::class, 'calendars']);
        Route::get('events', [CalendarController::class, 'events']);
        Route::post('events', [CalendarController::class, 'store']);
        Route::put('events/{event}', [CalendarController::class, 'update']);
        Route::delete('events/{event}', [CalendarController::class, 'destroy']);
    });

    Route::prefix('addressbook')->group(function () {
        Route::get('books', [AddressBookController::class, 'books']);
        Route::get('contacts', [AddressBookController::class, 'contacts']);
        Route::post('contacts', [AddressBookController::class, 'store']);
        Route::put('contacts/{contact}', [AddressBookController::class, 'update']);
        Route::delete('contacts/{contact}', [AddressBookController::class, 'destroy']);
    });

    Route::prefix('mail')->group(function () {
        Route::get('folders', [MailFolderController::class, 'index']);
        Route::post('folders', [MailFolderController::class, 'store']);
        Route::put('folders', [MailFolderController::class, 'update']);
        Route::delete('folders', [MailFolderController::class, 'destroy']);

        Route::get('messages', [MailMessageController::class, 'index']);
        Route::get('messages/{uid}', [MailMessageController::class, 'show'])->whereNumber('uid');
        Route::get('messages/{uid}/attachments/{attachment}', [MailMessageController::class, 'attachment'])->whereNumber('uid');
        Route::post('messages/flags', [MailMessageController::class, 'flags']);
        Route::post('messages/move', [MailMessageController::class, 'move']);
        Route::post('messages/delete', [MailMessageController::class, 'destroy']);

        Route::post('send', [MailSendController::class, 'send']);
        Route::post('drafts', [MailSendController::class, 'saveDraft']);

        Route::get('scheduled', [ScheduledMailController::class, 'index']);
        Route::post('scheduled', [ScheduledMailController::class, 'store']);
        Route::delete('scheduled/{scheduled}', [ScheduledMailController::class, 'destroy']);

        Route::get('snoozed', [SnoozeController::class, 'index']);
        Route::post('snooze', [SnoozeController::class, 'store']);
    });
});
