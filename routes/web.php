<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DeviceRegistrationController;
use App\Http\Controllers\ParentReportController;
use App\Http\Controllers\PlayController;
use App\Http\Controllers\ServiceWorkerController;
use App\Livewire\Admin\Account\ChangePassword;
use App\Livewire\Admin\Analytics\ParticipantReport;
use App\Livewire\Admin\Analytics\SessionDetail;
use App\Livewire\Admin\Analytics\StudyAnalytics;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Devices\DeviceIndex;
use App\Livewire\Admin\Participants\ParticipantIndex;
use App\Livewire\Admin\Questions\QuestionForm;
use App\Livewire\Admin\Questions\QuestionIndex;
use App\Livewire\Admin\Studies\StudyForm;
use App\Livewire\Admin\Studies\StudyIndex;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Admin\Users\UserIndex;
use App\Models\Question;
use App\Models\Study;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/r/{token}', [ParentReportController::class, 'show'])
    ->middleware('throttle:30,1')
    ->where('token', '[A-Za-z0-9]{1,64}')
    ->name('parent-report.show');

Route::middleware('auth')->prefix('play')->name('play.')->group(function (): void {
    Route::get('/', [PlayController::class, 'show'])->name('show');
    Route::get('/studies/{study}/bundle', [PlayController::class, 'bundle'])->name('bundle');
    Route::post('/sync', [PlayController::class, 'sync'])->middleware('throttle:120,1')->name('sync');
    Route::post('/devices', [DeviceRegistrationController::class, 'store'])->middleware('throttle:10,1')->name('devices.store');
});

Route::get('/sw.js', [ServiceWorkerController::class, 'worker'])->name('service-worker');
Route::get('/manifest.webmanifest', [ServiceWorkerController::class, 'manifest'])->name('web-manifest');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('/studies', StudyIndex::class)->name('studies.index');
    Route::get('/studies/create', StudyForm::class)->name('studies.create')->can('create', Study::class);
    Route::get('/studies/{study}/edit', StudyForm::class)->name('studies.edit')->can('update', 'study');

    Route::get('/questions', QuestionIndex::class)->name('questions.index')->can('viewAny', Question::class);
    Route::get('/questions/create', QuestionForm::class)->name('questions.create')->can('create', Question::class);
    Route::get('/questions/{question}/edit', QuestionForm::class)->name('questions.edit')->can('update', 'question');

    Route::get('/participants', ParticipantIndex::class)->name('participants.index');
    Route::get('/participants/{participant}/report', ParticipantReport::class)->name('participants.report');

    Route::get('/analytics', StudyAnalytics::class)->name('analytics');
    Route::get('/sessions/{session:uuid}', SessionDetail::class)->name('sessions.show');

    Route::get('/users', UserIndex::class)->name('users.index')->can('viewAny', User::class);
    Route::get('/users/create', UserForm::class)->name('users.create')->can('create', User::class);
    Route::get('/users/{user}/edit', UserForm::class)->name('users.edit')->can('update', 'user');

    Route::get('/devices', DeviceIndex::class)->name('devices.index');

    Route::get('/account/password', ChangePassword::class)->name('account.password');
});
