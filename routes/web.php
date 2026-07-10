<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WccAttachmentController;
use App\Http\Controllers\WccController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

/*
|--------------------------------------------------------------------------
| Guest — sign in, register, passwordless, password reset
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');

    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1');

    // Passwordless sign-in: 6-digit code or one-click signed link.
    Route::get('otp', [OtpLoginController::class, 'create'])->name('otp.create');
    Route::post('otp', [OtpLoginController::class, 'send'])->middleware('throttle:5,1')->name('otp.send');
    Route::post('otp/verify', [OtpLoginController::class, 'verify'])->middleware('throttle:10,1')->name('otp.verify');
    Route::get('otp/magic', [OtpLoginController::class, 'magic'])->middleware('signed')->name('otp.magic');

    // Password reset
    Route::get('forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Email verification — reachable while signed out, because the emailed link
| both proves the address and signs the user in.
|--------------------------------------------------------------------------
*/
Route::get('verify-email', [EmailVerificationNotificationController::class, 'notice'])->name('verification.notice');
Route::post('verify-email/resend', [EmailVerificationNotificationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('verification.send');
Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware('signed')
    ->name('verification.verify');

/*
|--------------------------------------------------------------------------
| Authenticated application
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'idle'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('analytics', AnalyticsController::class)->name('analytics');
    Route::view('about', 'pages.about')->name('about');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // WCC workspace
    Route::get('wcc', [WccController::class, 'create'])->name('wcc.create');
    Route::post('wcc', [WccController::class, 'store'])->name('wcc.store');

    // Signature / stamp images. Declared before wcc/{record} so "attachments"
    // is never mistaken for a record id.
    Route::post('wcc/attachments', [WccAttachmentController::class, 'store'])->name('wcc.attachments.store');
    Route::get('wcc/attachments/{attachment}', [WccAttachmentController::class, 'show'])
        ->where('attachment', '[a-f0-9]{64}')
        ->name('wcc.attachments.show');

    Route::get('wcc/{record}', [WccController::class, 'open'])->whereNumber('record')->name('wcc.open');
    Route::put('wcc/{record}', [WccController::class, 'update'])->whereNumber('record')->name('wcc.update');

    // Records
    Route::get('records', [RecordController::class, 'index'])->name('records.index');
    Route::post('records/{record}/transition', [RecordController::class, 'transition'])->name('records.transition');
    Route::delete('records/{record}', [RecordController::class, 'destroy'])->name('records.destroy');

    // Governance
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    Route::delete('audit', [AuditController::class, 'destroy'])->name('audit.destroy');

    // IT-only administration
    Route::middleware('can:manage-users')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
        Route::post('users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
