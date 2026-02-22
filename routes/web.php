<?php

use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CostsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StoryController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin Settings
    Route::get('/admin/settings', [AdminSettingsController::class, 'index'])->name('admin.settings');
    Route::post('/admin/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');

    // Costs
    Route::get('/costs', [CostsController::class, 'index'])->name('costs.index');

    // Stories
    Route::resource('stories', StoryController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

    // Chat (Agent 1)
    Route::get('/stories/{story}/chat', [ChatController::class, 'index'])->name('stories.chat');
    Route::post('/stories/{story}/chat', [ChatController::class, 'store'])->name('stories.chat.store');
    Route::post('/stories/{story}/chat/finalize', [ChatController::class, 'finalize'])->name('stories.chat.finalize');

    // Pipeline
    Route::get('/stories/{story}/pipeline', [PipelineController::class, 'show'])->name('stories.pipeline');
    Route::post('/stories/{story}/pipeline/start', [PipelineController::class, 'start'])->name('stories.pipeline.start');
    Route::get('/stories/{story}/pipeline/status', [PipelineController::class, 'status'])->name('stories.pipeline.status');
    Route::post('/stories/{story}/pipeline/approve-characters', [PipelineController::class, 'approveCharacters'])->name('stories.pipeline.approve-characters');
    Route::post('/stories/{story}/pipeline/approve-storyboard', [PipelineController::class, 'approveStoryboard'])->name('stories.pipeline.approve-storyboard');
    Route::post('/stories/{story}/pipeline/revert/{stage}', [PipelineController::class, 'revertToStage'])->name('stories.pipeline.revert');
    Route::post('/stories/{story}/characters', [PipelineController::class, 'createCharacter'])->name('stories.characters.store');
    Route::put('/stories/{story}/characters/{character}', [PipelineController::class, 'updateCharacter'])->name('stories.characters.update');
    Route::delete('/stories/{story}/characters/{character}', [PipelineController::class, 'deleteCharacter'])->name('stories.characters.destroy');
    Route::post('/stories/{story}/characters/{character}/regenerate', [PipelineController::class, 'regenerateCharacter'])->name('stories.characters.regenerate');
    Route::post('/stories/{story}/characters/{character}/upload', [PipelineController::class, 'uploadCharacterImage'])->name('stories.characters.upload');
    Route::post('/stories/{story}/frames/{frame}/regenerate', [PipelineController::class, 'regenerateFrame'])->name('stories.frames.regenerate');
    Route::post('/stories/{story}/frames/{frame}/upload', [PipelineController::class, 'uploadFrameImage'])->name('stories.frames.upload');
    Route::delete('/stories/{story}/frames/{frame}', [PipelineController::class, 'deleteFrame'])->name('stories.frames.destroy');
    Route::post('/stories/{story}/videos/concatenate', [PipelineController::class, 'concatenateVideos'])->name('stories.videos.concatenate');
    Route::post('/stories/{story}/videos/{video}/regenerate', [PipelineController::class, 'regenerateMiniVideo'])->name('stories.videos.regenerate');
    Route::post('/stories/{story}/videos/{video}/upload', [PipelineController::class, 'uploadClipVideo'])->name('stories.videos.upload');
});

require __DIR__.'/auth.php';
