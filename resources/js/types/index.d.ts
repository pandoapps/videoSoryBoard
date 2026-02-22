export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash?: {
        success?: string;
        warning?: string;
        error?: string;
    };
};

// Story types
export type StoryStatus = 'pending' | 'scripting' | 'characters' | 'character_review' | 'storyboard' | 'storyboard_review' | 'producing' | 'completed' | 'failed';
export type PipelineStage = 'script' | 'characters' | 'storyboard' | 'video';
export type MessageRole = 'user' | 'assistant';

export interface Story {
    id: number;
    user_id: number;
    title: string;
    synopsis: string | null;
    full_script: string | null;
    status: StoryStatus;
    current_stage: PipelineStage | null;
    error_message: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
    chat_messages?: ChatMessage[];
    characters?: Character[];
    storyboard_frames?: StoryboardFrame[];
    videos?: Video[];
    latest_video?: Video | null;
    mini_videos?: Video[];
    final_video?: Video | null;
    total_cost_cents?: number;
}

export interface ChatMessage {
    id: number;
    story_id: number;
    role: MessageRole;
    content: string;
    token_count: number | null;
    created_at: string;
}

export interface Character {
    id: number;
    story_id: number;
    name: string;
    description: string | null;
    image_path: string | null;
    image_url: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}

export interface StoryboardFrame {
    id: number;
    story_id: number;
    sequence_number: number;
    scene_description: string;
    prompt: string | null;
    image_path: string | null;
    image_url: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}

export interface Video {
    id: number;
    story_id: number;
    sequence_number: number | null;
    is_final: boolean;
    prompt: string | null;
    frame_from_id: number | null;
    frame_to_id: number | null;
    frame_from?: StoryboardFrame;
    frame_to?: StoryboardFrame;
    external_job_id: string | null;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    video_path: string | null;
    video_url: string | null;
    duration_seconds: number | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}

export interface ApiKeyStatus {
    configured: boolean;
    is_active: boolean;
    last_verified_at: string | null;
}

export interface ApiKeysStatus {
    anthropic: ApiKeyStatus;
    nano_banana: ApiKeyStatus;
    kling: ApiKeyStatus;
}

export interface ApiUsageSummary {
    anthropic: {
        call_count: number;
        input_tokens: number;
        output_tokens: number;
        cost_cents: number;
    };
    nano_banana: {
        call_count: number;
        cost_cents: number;
    };
    kling: {
        call_count: number;
        cost_cents: number;
    };
    total_cost_cents: number;
}
