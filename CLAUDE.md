# VideoGenerator - Multi-Agent Video Creation System

## Project Overview
Laravel app that orchestrates video creation through 3 AI agents:
1. **Agent 1 (Anthropic Claude)** - Chat-based script/story development
2. **Agent 2 (Nano Banana API)** - Character and storyboard image generation
3. **Agent 3 (Kling AI)** - Video clip generation (image-to-video)

## Tech Stack
- **Backend**: Laravel 12 (PHP 8.4)
- **Frontend**: React + TypeScript + Inertia.js + Tailwind CSS
- **Database**: PostgreSQL 16
- **Cache/Queue**: Redis 7
- **Storage**: AWS S3 (`FILESYSTEM_DISK=s3`)
- **Infrastructure**: Docker Compose

## Docker Services
| Service | Port | Notes |
|---------|------|-------|
| app | internal | PHP 8.4-fpm + ffmpeg |
| nginx | 8080 | Web server |
| postgres | 5432 | `DB_USERNAME=laravel`, `DB_DATABASE=video_generator` |
| redis | 6379 | Cache + queue broker |
| queue | internal | Background job worker (`pipeline` + `default` queues) |
| node | 5173 | Vite dev server |

## Key Commands
- `make install` - Full project setup
- `make up/down/restart` - Container management
- `make migrate` - Run migrations
- `make test` - Run tests
- `make shell` - App container shell
- `make queue` - Queue worker foreground
- `make tinker` - Laravel Tinker REPL
- `make format` - Format PHP with Pint

## Pipeline Flow
```
Story Created (Pending)
  → Chat with Claude (Scripting)
  → [Finalize Script]
  → GenerateCharacters → CharacterReview (user approves/edits/regenerates)
  → GenerateStoryboard → StoryboardReview (user approves/edits/regenerates)
  → ProduceVideo → SubmitMiniVideo → PollMiniVideoStatus (30s polling)
  → All clips complete → ConcatenateVideos (ffmpeg)
  → Final Video (Completed)
```

## S3 Storage Layout
All media stored under per-story folders:
```
stories/{story_id}/characters/{uuid}.jpg
stories/{story_id}/storyboard-frames/{uuid}.jpg
stories/{story_id}/videos/{uuid}.mp4
```
`MediaStorageService` handles all uploads/downloads using the configured default disk.

## Architecture

### Backend Key Files
| Layer | Directory | Purpose |
|-------|-----------|---------|
| Controllers | `app/Http/Controllers/` | `StoryController`, `ChatController`, `PipelineController`, `AdminSettingsController`, `CostsController`, `DashboardController` |
| Services | `app/Services/` | `AnthropicService`, `NanaBananaService`, `KlingService`, `ApiKeyVault`, `ApiUsageTracker`, `MediaStorageService`, `VideoConcatenationService`, `PipelineOrchestrator` |
| Jobs | `app/Jobs/` | `GenerateCharacters`, `GenerateStoryboard`, `ProduceVideo`, `SubmitMiniVideo`, `PollMiniVideoStatus`, `RegenerateMiniVideo`, `RegenerateCharacterImage`, `RegenerateStoryboardFrame`, `ConcatenateVideos` |
| Models | `app/Models/` | `Story`, `Character`, `ChatMessage`, `StoryboardFrame`, `Video`, `ApiKey`, `ApiUsage` |
| Enums | `app/Enums/` | `StoryStatus`, `PipelineStage`, `MessageRole` |
| Events | `app/Events/` | `StoryStageCompleted` → `AdvancePipeline` listener |
| Middleware | `app/Http/Middleware/` | `EnsureApiKeysConfigured` (checks per-user API keys) |

### Frontend Key Files
| Directory | Pages/Components |
|-----------|-----------------|
| `Pages/Dashboard.tsx` | Dashboard with recent stories, stats, cost badges, final video previews |
| `Pages/Stories/` | `Index`, `Create`, `Show` (printable report with API usage, characters, storyboard, clips) |
| `Pages/Chat/` | `ChatPage` |
| `Pages/Pipeline/` | `PipelineView` (main orchestration UI with clip generation params) |
| `Pages/Admin/` | `Settings` (per-user API key management with setup instructions) |
| `Pages/Costs/` | `Index` (cost tracking per story and global totals) |
| `Components/chat/` | `ChatWindow`, `ChatBubble`, `ChatInput`, `TypingIndicator` |
| `Components/characters/` | `CharacterGallery`, `CharacterCard` |
| `Components/storyboard/` | `StoryboardGrid`, `FrameCard` |
| `Components/video/` | `MiniVideoGrid`, `MiniVideoCard` (with generation params UI), `VideoPlayer`, `VideoStatusCard` |
| `Components/pipeline/` | `PipelineTracker`, `StageCard`, `SectionHeader`, `SectionPlaceholder` |
| `Components/stories/` | `StoryCard` (dashboard card with cost badge + video preview), `StoryStatusBadge` |

## Database Tables
`stories`, `chat_messages`, `characters`, `storyboard_frames`, `videos`, `api_keys` (per-user), `api_usage`

## API Integrations
| Service | Auth | Key Details |
|---------|------|-------------|
| Anthropic | `x-api-key` header | Model: `claude-sonnet-4-20250514` |
| NanoBanana | Bearer token | `generate-pro` endpoint + `record-info` polling. 18 tokens/call, $5/1000 tokens |
| Kling | JWT HS256 (access_key:secret_key) | Base: `https://api-singapore.klingai.com`, image2video endpoint |

### Per-User API Keys
API keys are **per-user** (not shared project-wide). Stored encrypted in DB via `ApiKeyVault` (providers: `anthropic`, `nano_banana`, `kling`). The `api_keys` table has a composite unique constraint on `(user_id, provider)`.

- `ApiKeyVault` methods all require `int $userId`
- Cache keys include userId: `api_key.{$userId}.{$provider}`
- External services (`AnthropicService`, `NanaBananaService`, `KlingService`) use lazy initialization via `forUser(int $userId): static` — constructor does NOT resolve keys
- All Jobs call `->forUser($story->user_id)` before using services
- Settings page shows setup instructions with links when keys are not configured

### Kling AI Parameters
| Parameter | Values | Default |
|-----------|--------|---------|
| `model_name` | `kling-v2-6`, `kling-v2-5-turbo`, `kling-v2-1-master`, `kling-v2-master`, `kling-v1-6`, `kling-v1` | `kling-v2-6` |
| `mode` | `std` (Standard), `pro` (Pro) | `std` |
| `duration` | `5` (5s), `10` (10s) | `5` |
| `camera_control` | `simple`, `down_back`, `forward_up`, `right_turn_forward`, `left_turn_forward` | none |
| `enable_audio` | `true`/`false` | `false` |

Kling key stored as `access_key:secret_key` in vault under provider `kling`. JWT tokens cached per-user for 25 minutes.

## Mini-Video Architecture
- N-1 mini-videos from consecutive storyboard frame pairs (frame 1→2, 2→3, etc.)
- Each mini-video individually regenerable with configurable generation params (model, mode, duration, camera, prompt)
- `RegenerateStoryboardFrame` sends character images + last 3 storyboard frames as NanoBanana references for style consistency
- Final video: ffmpeg concatenation of all completed clips
- `VideoConcatenationService` normalises clips (re-encode to uniform h264/fps/resolution) before concat

## Story Show Page (Printable Report)
The Story Show page doubles as a printable report (`window.print()`):
- Print-only title header, API usage summary at top, synopsis, full script, character gallery, storyboard (image left + description right), clips (start/end frames + prompt)
- Final video section hidden from print
- Print CSS in `resources/css/app.css` handles layout (forced 3-col grid, no page breaks before script, no blank pages)

## Conventions
- Enums for status values (not string constants)
- Form Requests for validation (`app/Http/Requests/`)
- Resource controllers where applicable
- TypeScript strict mode on frontend
- Inertia.js for SPA-like navigation
- Breeze starter kit: component imports use `@/Components/` (uppercase C)
- Queue jobs use `pipeline` queue for pipeline work
- All media goes through `MediaStorageService` — never write directly to `Storage::disk('public')`
- Per-user API keys: always pass `$userId` to `ApiKeyVault` and call `->forUser($userId)` on services before use
- `firebase/php-jwt` for Kling JWT token generation
