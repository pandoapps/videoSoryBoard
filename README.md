# VideoGenerator

Multi-agent video creation system that orchestrates AI services to turn story ideas into complete videos.

## How It Works

1. **Write a script** — Chat with Claude AI to develop your story, characters, and scenes
2. **Generate characters** — AI creates character images based on the script (Nano Banana API)
3. **Generate storyboard** — AI produces storyboard frames for each scene (Nano Banana API)
4. **Generate video clips** — Each consecutive frame pair becomes a video transition (Kling AI)
5. **Concatenate** — All clips are merged into a final video via ffmpeg

Each stage has a review step where you can approve, edit, or regenerate individual items before moving forward.

## Examples

Two showcase videos demonstrating the full pipeline in action — script writing, character generation, storyboard creation, video clip production, cost breakdown, and final concatenated result.

### Noah's Ark (43s)



https://github.com/user-attachments/assets/659f71c8-e92c-420c-a1a0-2514ad36b868



### Hinduism — The Battle of Lanka (50s)



https://github.com/user-attachments/assets/e443e198-a411-4286-a309-334cf6e52fdc



## Tech Stack

- **Backend**: Laravel 12 (PHP 8.4)
- **Frontend**: React + TypeScript + Inertia.js + Tailwind CSS
- **Database**: PostgreSQL 16
- **Cache/Queue**: Redis 7
- **Storage**: AWS S3
- **Infrastructure**: Docker Compose

## Getting Started

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (includes Docker Engine + Docker Compose)
- [Git](https://git-scm.com/downloads)
- `make` (pre-installed on macOS/Linux; on Windows use WSL2 or install via [chocolatey](https://chocolatey.org/): `choco install make`)

All other dependencies (PHP, Node.js, Composer, npm, PostgreSQL, Redis, ffmpeg) run inside Docker containers — nothing else needs to be installed on your machine.

### Installation

```bash
git clone <repo-url>
cd videoGenerator
cp .env.example .env
make install
```

The app will be available at `http://localhost:8080`.

### API Keys

Each user configures their own API keys in **Settings** (`/admin/settings`):

| Service | Purpose | Get your key |
|---------|---------|--------------|
| **Anthropic** | Script writing chat (Claude) | [console.anthropic.com](https://console.anthropic.com/settings/keys) |
| **Nano Banana** | Character and storyboard image generation | [nanobananaapi.ai](https://nanobananaapi.ai/) |
| **Kling AI** | Video clip generation (image-to-video) | [app.klingai.com](https://app.klingai.com/global/dev/api-key) |

Keys are stored encrypted in the database and scoped per user.

## Common Commands

```bash
make up              # Start all containers
make down            # Stop all containers
make restart         # Restart containers
make migrate         # Run database migrations
make test            # Run tests
make shell           # Open app container shell
make queue           # Run queue worker in foreground
make tinker          # Laravel Tinker REPL
make format          # Format PHP code with Pint
```

## Docker Services

| Service | Port | Description |
|---------|------|-------------|
| app | — | PHP 8.4-fpm + ffmpeg |
| nginx | 8080 | Web server |
| postgres | 5432 | PostgreSQL database |
| redis | 6379 | Cache + queue broker |
| queue | — | Background job worker |
| node | 5173 | Vite dev server |

## Video Generation Options

When generating video clips, each clip can be configured with:

| Parameter | Options | Default |
|-----------|---------|---------|
| Model | Kling v2.6, v2.5 Turbo, v2.1 Master, v2 Master, v1.6, v1 | v2.6 |
| Mode | Standard, Pro | Standard |
| Duration | 5s, 10s | 5s |
| Camera | None, Simple, Down & Back, Forward & Up, Right Turn Forward, Left Turn Forward | None |

## Project Structure

```
app/
├── Console/              # Artisan commands
├── Enums/                # StoryStatus, PipelineStage, MessageRole
├── Events/               # Pipeline event-driven orchestration
├── Http/
│   ├── Controllers/      # Story, Chat, Pipeline, Settings, Costs, Dashboard
│   ├── Middleware/        # EnsureApiKeysConfigured
│   └── Requests/         # Form request validation
├── Jobs/                 # Pipeline async jobs (characters, storyboard, video, concat)
├── Listeners/            # AdvancePipeline
├── Models/               # Story, Character, ChatMessage, StoryboardFrame, Video, ApiKey, ApiUsage
└── Services/             # AnthropicService, NanaBananaService, KlingService, ApiKeyVault, etc.

resources/js/
├── Components/
│   ├── characters/       # CharacterGallery, CharacterCard
│   ├── chat/             # ChatWindow, ChatBubble, ChatInput, TypingIndicator
│   ├── pipeline/         # PipelineTracker, StageCard
│   ├── stories/          # StoryCard, StoryStatusBadge
│   ├── storyboard/       # StoryboardGrid, FrameCard
│   └── video/            # MiniVideoGrid, MiniVideoCard, VideoPlayer, VideoStatusCard
├── Layouts/              # AuthenticatedLayout, GuestLayout
└── Pages/
    ├── Admin/            # API key settings
    ├── Chat/             # Script writing chat
    ├── Costs/            # Cost tracking
    ├── Pipeline/         # Pipeline orchestration UI
    └── Stories/          # CRUD + printable report
```
