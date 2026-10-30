<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicQueueController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicQueueController::class, 'home'])->name('home');
Route::post('/tickets', [PublicQueueController::class, 'issue'])->middleware('throttle:10,1')->name('tickets.issue');
Route::get('/tickets/{ticket}', [PublicQueueController::class, 'ticket'])->name('tickets.show');
Route::get('/display', [PublicQueueController::class, 'display'])->name('display');
Route::get('/display/state', [PublicQueueController::class, 'displayState'])->middleware('throttle:120,1')->name('display.state');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendOtp'])->middleware('throttle:3,1')->name('password.otp');
    Route::get('/reset-password', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [AuthController::class, 'changePassword'])->name('profile.password');
});

Route::middleware(['auth', 'role:staff'])->prefix('staff')->name('staff')->group(function () {
    Route::get('/', [StaffController::class, 'index'])->name('.index');
    Route::get('/state', [StaffController::class, 'state'])->middleware('throttle:120,1')->name('.state');
    Route::post('/next', [StaffController::class, 'claim'])->name('.next');
    Route::post('/accept', [StaffController::class, 'accept'])->name('.accept');
    Route::post('/skip', [StaffController::class, 'skip'])->name('.skip');
    Route::post('/complete', [StaffController::class, 'complete'])->name('.complete');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('.index');
    Route::get('/sessions', [AdminController::class, 'index'])->defaults('section', 'sessions')->name('.sessions');
    Route::get('/desks', [AdminController::class, 'index'])->defaults('section', 'desks')->name('.desks.index');
    Route::get('/staff', [AdminController::class, 'index'])->defaults('section', 'staff')->name('.staff.index');
    Route::get('/settings', [AdminController::class, 'index'])->defaults('section', 'settings')->name('.settings.page');
    Route::get('/sms', [AdminController::class, 'index'])->defaults('section', 'sms')->name('.sms.index');
    Route::get('/monitor', [AdminController::class, 'index'])->defaults('section', 'monitor')->name('.monitor');
    Route::get('/history', [AdminController::class, 'index'])->defaults('section', 'history')->name('.history');
    Route::get('/audit', [AdminController::class, 'index'])->defaults('section', 'audit')->name('.audit');
    Route::post('/sessions/start', [AdminController::class, 'startSession'])->name('.session.start');
    Route::post('/sessions/end', [AdminController::class, 'endSession'])->name('.session.end');
    Route::post('/desks', [AdminController::class, 'createDesk'])->name('.desks.create');
    Route::put('/desks/{desk}', [AdminController::class, 'updateDesk'])->name('.desks.update');
    Route::post('/staff', [AdminController::class, 'createStaff'])->name('.staff.create');
    Route::put('/staff/{staff}', [AdminController::class, 'updateStaff'])->name('.staff.update');
    Route::put('/settings', [AdminController::class, 'updateSettings'])->name('.settings');
    Route::put('/sms-settings', [AdminController::class, 'updateSmsSettings'])->name('.sms.settings');
    Route::post('/sms-test', [AdminController::class, 'testSms'])->name('.sms.test');
    Route::post('/tickets/{ticket}/recover', [AdminController::class, 'recoverTicket'])->name('.tickets.recover');
    Route::post('/tickets/{ticket}/recall', [AdminController::class, 'recallSkippedTicket'])->name('.tickets.recall');
});
