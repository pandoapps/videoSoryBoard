# VideoGenerator - Multi-Agent Video Creation System

## Project Overview
A Laravel application that orchestrates a video creation workflow through 3 AI agents:
1. **Agent 1 (Anthropic Claude)** - Chat-based script/story development with the client
2. **Agent 2 (Nano Banana API)** - Character and storyboard image generation
3. **Agent 3 (Higgsfield)** - Final video production

## Tech Stack
- **Backend**: Laravel 12 (PHP 8.4)
- **Frontend**: React + TypeScript + Inertia.js + Tailwind CSS
- **Database**: PostgreSQL 16
- **Cache/Queue**: Redis 7
- **Infrastructure**: Docker Compose

## Architecture
- Services in `app/Services/` handle external API calls
- Jobs in `app/Jobs/` process pipeline stages asynchronously
- Pipeline orchestration via events/listeners pattern
- API keys stored encrypted in database via `ApiKeyVault` service

## Key Commands
- `make install` - Full project setup
- `make up/down/restart` - Container management
- `make migrate` - Run migrations
- `make test` - Run tests
- `make shell` - App container shell
- `make queue` - Queue worker foreground

## Pipeline Flow
Story → Chat (script) → Characters → Storyboard → Video

## Conventions
- Enums for status values (not string constants)
- Form Requests for validation
- Resource controllers where applicable
- TypeScript strict mode on frontend
- Inertia.js for SPA-like navigation
