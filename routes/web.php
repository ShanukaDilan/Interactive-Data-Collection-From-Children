<?php

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\Admin\Account\ChangePassword;
use App\Livewire\Admin\Dashboard;
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

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('/studies', StudyIndex::class)->name('studies.index');
    Route::get('/studies/create', StudyForm::class)->name('studies.create')->can('create', Study::class);
    Route::get('/studies/{study}/edit', StudyForm::class)->name('studies.edit')->can('update', 'study');

    Route::get('/questions', QuestionIndex::class)->name('questions.index')->can('viewAny', Question::class);
    Route::get('/questions/create', QuestionForm::class)->name('questions.create')->can('create', Question::class);
    Route::get('/questions/{question}/edit', QuestionForm::class)->name('questions.edit')->can('update', 'question');

    Route::get('/participants', ParticipantIndex::class)->name('participants.index');

    Route::get('/users', UserIndex::class)->name('users.index')->can('viewAny', User::class);
    Route::get('/users/create', UserForm::class)->name('users.create')->can('create', User::class);
    Route::get('/users/{user}/edit', UserForm::class)->name('users.edit')->can('update', 'user');

    Route::get('/account/password', ChangePassword::class)->name('account.password');
});
