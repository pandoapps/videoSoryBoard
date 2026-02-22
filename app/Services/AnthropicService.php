<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Story;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-sonnet-4-20250514';
    private const MAX_TOKENS = 4096;

    private string $apiKey = '';
    private ?int $userId = null;

    public function __construct(private ApiKeyVault $vault) {}

    public function forUser(int $userId): static
    {
        $this->userId = $userId;
        $this->apiKey = $this->vault->get('anthropic', $userId) ?? '';

        return $this;
    }

    public function ensureConfigured(): void
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Anthropic API key is not configured. Please add it in Settings.');
        }
    }

    public function chat(Story $story, string $userMessage): array
    {
        $this->ensureConfigured();

        $messages = $this->buildMessageHistory($story);
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::API_URL, [
            'model' => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'system' => $this->getSystemPrompt($story),
            'messages' => $messages,
        ]);

        if (! $response->successful()) {
            Log::error('Anthropic API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to communicate with Anthropic API: ' . $response->body());
        }

        $data = $response->json();
        $assistantContent = $data['content'][0]['text'] ?? '';
        $inputTokens = $data['usage']['input_tokens'] ?? null;
        $outputTokens = $data['usage']['output_tokens'] ?? null;

        return [
            'content' => $assistantContent,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ];
    }

    /**
     * Extract characters from a script via Claude.
     *
     * @return array{characters: array<array{name: string, description: string}>, input_tokens: int, output_tokens: int}
     */
    public function extractCharacters(string $script): array
    {
        $this->ensureConfigured();

        $systemPrompt = <<<'SYSTEM'
You are a script analyst. Extract all characters (people, creatures, named entities that appear visually) from the script.

Rules:
- Only include actual characters that appear visually in the story (people, animals, creatures, gods, etc.)
- Do NOT include camera directions, sound effects, locations, props, or abstract concepts.
- For each character, provide their name and a detailed visual description (physical appearance, clothing, distinguishing features).
- If the script has a CHARACTERS section, use it but verify against the scenes.
- If a character is mentioned in scenes but not in the characters section, include them too.

Return ONLY a valid JSON array, no markdown fences, no extra text.
SYSTEM;

        $userPrompt = <<<PROMPT
Here is the full script:

{$script}

Extract all characters. Return a JSON array:
[
  {"name": "Character Name", "description": "Detailed visual description of the character..."},
  ...
]
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::API_URL, [
            'model' => self::MODEL,
            'max_tokens' => 4096,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Anthropic API error during character extraction', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to extract characters: ' . $response->body());
        }

        $data = $response->json();
        $text = $data['content'][0]['text'] ?? '';
        $inputTokens = $data['usage']['input_tokens'] ?? 0;
        $outputTokens = $data['usage']['output_tokens'] ?? 0;

        $text = preg_replace('/^```(?:json)?\s*/', '', trim($text));
        $text = preg_replace('/\s*```$/', '', $text);

        $characters = json_decode($text, true);

        if (! is_array($characters)) {
            Log::error('Failed to parse characters JSON', ['raw' => $text]);
            throw new \RuntimeException('Failed to parse characters from Anthropic response.');
        }

        // Filter to ensure valid entries
        $characters = array_filter($characters, fn ($c) => ! empty($c['name']) && ! empty($c['description']));

        return [
            'characters' => array_values($characters),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ];
    }

    public function extractScript(Story $story): array
    {
        $this->ensureConfigured();

        $messages = $this->buildMessageHistory($story);
        $messages[] = [
            'role' => 'user',
            'content' => 'Based on our entire conversation, please produce the final structured script. Include: title, synopsis, list of characters (name + description), and scene-by-scene breakdown with scene number, description, and dialogue. Format it clearly with sections.',
        ];

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(90)->post(self::API_URL, [
            'model' => self::MODEL,
            'max_tokens' => 8192,
            'system' => 'You are a professional screenwriter. Extract and format a complete, structured script from the conversation. The output should be the final production-ready script with clear sections for: TITLE, SYNOPSIS, CHARACTERS (name and visual description), and SCENES (numbered, with descriptions and any dialogue).',
            'messages' => $messages,
        ]);

        if (! $response->successful()) {
            Log::error('Anthropic API error during script extraction', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to extract script: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'input_tokens' => $data['usage']['input_tokens'] ?? 0,
            'output_tokens' => $data['usage']['output_tokens'] ?? 0,
        ];
    }

    /**
     * Step 1: Ask Claude to identify scenes and estimate durations.
     *
     * @return array{scenes: array<array{scene: int, duration_seconds: int, summary: string}>, input_tokens: int, output_tokens: int}
     */
    public function estimateSceneDurations(string $script): array
    {
        $this->ensureConfigured();

        $systemPrompt = <<<'SYSTEM'
You are a professional video director and timing expert. Analyze the script and break it into its scenes. For each scene, estimate a realistic duration in seconds based on the action, dialogue, pacing, and emotional beats.

Rules:
- Each scene should be at least 5 seconds.
- Consider dialogue length (roughly 3 words per second), action complexity, and dramatic pauses.
- Provide a brief 1-sentence summary of each scene's content.

Return ONLY a valid JSON array, no markdown fences, no extra text.
SYSTEM;

        $userPrompt = <<<PROMPT
Here is the full script:

{$script}

Identify every scene, estimate its duration in seconds, and provide a brief summary. Return JSON:
[
  {"scene": 1, "duration_seconds": 12, "summary": "Brief scene summary..."},
  {"scene": 2, "duration_seconds": 8, "summary": "Brief scene summary..."}
]
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::API_URL, [
            'model' => self::MODEL,
            'max_tokens' => 4096,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Anthropic API error during scene duration estimation', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to estimate scene durations: ' . $response->body());
        }

        $data = $response->json();
        $text = $data['content'][0]['text'] ?? '';
        $inputTokens = $data['usage']['input_tokens'] ?? 0;
        $outputTokens = $data['usage']['output_tokens'] ?? 0;

        $text = preg_replace('/^```(?:json)?\s*/', '', trim($text));
        $text = preg_replace('/\s*```$/', '', $text);

        $scenes = json_decode($text, true);

        if (! is_array($scenes) || empty($scenes)) {
            Log::error('Failed to parse scene durations JSON', ['raw' => $text]);
            throw new \RuntimeException('Failed to parse scene durations from Anthropic response.');
        }

        return [
            'scenes' => $scenes,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ];
    }

    /**
     * Step 2: Generate visual descriptions for specific frame timestamps.
     *
     * @param  array<array{scene: int, duration_seconds: int, summary: string, frames: int[]}>  $frameStructure
     * @return array{panels: array<array{panel_number: int, description: string, scene: int, second: int}>, input_tokens: int, output_tokens: int}
     */
    public function generateFrameDescriptions(string $script, array $frameStructure, array $characterNames = []): array
    {
        $this->ensureConfigured();

        // Build the frame structure text for the prompt
        $structureText = '';
        $totalFrames = 0;
        foreach ($frameStructure as $s) {
            $frameCount = count($s['frames']);
            $totalFrames += $frameCount;
            $timestamps = implode(', ', array_map(fn ($t) => "{$t}s", $s['frames']));
            $structureText .= "Scene {$s['scene']} ({$s['duration_seconds']}s, {$frameCount} frames at: {$timestamps}): {$s['summary']}\n";
        }

        $characterNamesText = '';
        if (! empty($characterNames)) {
            $namesList = implode(', ', $characterNames);
            $characterNamesText = <<<CHARS

IMPORTANT — Character identification:
The story has these characters: {$namesList}.
For each frame, you MUST also list which of these characters (by exact name) are visually present in that frame. Use only names from the list above.
CHARS;
        }

        $systemPrompt = <<<SYSTEM
You are a professional storyboard artist. You will receive a complete video script and a frame structure that specifies exactly which frames are needed (scene, timestamp). Your job is to write a detailed visual description for each frame.

Rules:
- Describe only what is VISIBLE — no dialogue, no speech bubbles, no text overlays.
- Each description must be a self-contained, detailed visual snapshot: characters, poses, expressions, camera angle, lighting, background, and mood.
- Stay FAITHFUL to the script — every frame must accurately depict what happens at that moment in the story.
- Maintain visual continuity between frames (consistent character appearance, setting, clothing).
- Frames within a scene should show clear visual progression over time.
- Use the scene summary and the timestamp to determine exactly what should be happening at that moment.
{$characterNamesText}

Return ONLY a valid JSON array, no markdown fences, no extra text.
SYSTEM;

        $userPrompt = <<<PROMPT
FULL SCRIPT:
{$script}

FRAME STRUCTURE (scene, duration, timestamps, summary):
{$structureText}

For each frame listed above, write a detailed visual description of what should be shown at that exact timestamp. Return a JSON array:
[
  {
    "scene": 1,
    "frames": [
      {"second": 0, "description": "Detailed visual description...", "characters": ["CharName1", "CharName2"]},
      {"second": 5, "description": "Detailed visual description...", "characters": ["CharName1"]}
    ]
  },
  ...
]
Note: the "characters" array should list the exact names of characters visually present in that frame. Use an empty array [] if no named characters appear.

Total frames expected: {$totalFrames}
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::API_URL, [
            'model' => self::MODEL,
            'max_tokens' => 8192,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Anthropic API error during frame description generation', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to generate frame descriptions: ' . $response->body());
        }

        $data = $response->json();
        $text = $data['content'][0]['text'] ?? '';
        $inputTokens = $data['usage']['input_tokens'] ?? 0;
        $outputTokens = $data['usage']['output_tokens'] ?? 0;

        $text = preg_replace('/^```(?:json)?\s*/', '', trim($text));
        $text = preg_replace('/\s*```$/', '', $text);

        $scenes = json_decode($text, true);

        if (! is_array($scenes) || empty($scenes)) {
            Log::error('Failed to parse frame descriptions JSON', ['raw' => $text]);
            throw new \RuntimeException('Failed to parse frame descriptions from Anthropic response.');
        }

        // Flatten into sequential panels
        $panels = [];
        $panelNumber = 1;

        foreach ($scenes as $scene) {
            $sceneNumber = $scene['scene'] ?? 0;
            foreach ($scene['frames'] ?? [] as $frame) {
                $panels[] = [
                    'panel_number' => $panelNumber++,
                    'description' => $frame['description'] ?? '',
                    'scene' => $sceneNumber,
                    'second' => $frame['second'] ?? 0,
                    'characters' => $frame['characters'] ?? [],
                ];
            }
        }

        if (empty($panels)) {
            Log::error('No panels extracted from frame descriptions', ['scenes' => $scenes]);
            throw new \RuntimeException('Failed to extract panels from Anthropic response.');
        }

        return [
            'panels' => $panels,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ];
    }

    /**
     * Generate video transition prompts for consecutive storyboard frame pairs.
     *
     * @param  string  $script  The full story script
     * @param  array<array{from_seq: int, to_seq: int, from_desc: string, to_desc: string}>  $transitions
     * @return array{prompts: string[], input_tokens: int, output_tokens: int}
     */
    public function generateVideoTransitionPrompts(string $script, array $transitions): array
    {
        $this->ensureConfigured();

        $transitionsText = '';
        foreach ($transitions as $i => $t) {
            $num = $i + 1;
            $transitionsText .= "Transition {$num} (Frame {$t['from_seq']} → Frame {$t['to_seq']}):\n";
            $transitionsText .= "  FROM: {$t['from_desc']}\n";
            $transitionsText .= "  TO:   {$t['to_desc']}\n\n";
        }

        $systemPrompt = <<<'SYSTEM'
You are a professional video director creating prompts for an AI video generator (image-to-video).
You will receive a story script and a list of transitions between consecutive storyboard frames.
For each transition, write a concise, vivid prompt that describes the MOTION and ACTION that should happen between the two frames.

Rules:
- Focus on movement, camera motion, character actions, and visual flow — NOT static descriptions.
- Reference the story context to make each transition narratively meaningful.
- Keep each prompt between 1-3 sentences, direct and visual.
- Do NOT include technical jargon or mention "AI" or "generation".
- Write as if directing a cinematographer: describe what HAPPENS, not what things look like.

Return ONLY a valid JSON array of strings, one prompt per transition, in the same order. No markdown fences.
SYSTEM;

        $userPrompt = <<<PROMPT
SCRIPT:
{$script}

TRANSITIONS:
{$transitionsText}
Generate a video prompt for each transition above. Return a JSON array of {$this->countTransitions($transitions)} strings.
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(90)->post(self::API_URL, [
            'model' => self::MODEL,
            'max_tokens' => 4096,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Anthropic API error during video prompt generation', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to generate video prompts: ' . $response->body());
        }

        $data = $response->json();
        $text = $data['content'][0]['text'] ?? '';
        $inputTokens = $data['usage']['input_tokens'] ?? 0;
        $outputTokens = $data['usage']['output_tokens'] ?? 0;

        // Strip markdown fences if present
        $text = preg_replace('/^```(?:json)?\s*/', '', trim($text));
        $text = preg_replace('/\s*```$/', '', $text);

        $prompts = json_decode($text, true);

        if (! is_array($prompts) || count($prompts) !== count($transitions)) {
            Log::warning('Video prompt count mismatch, using fallback', [
                'expected' => count($transitions),
                'got' => is_array($prompts) ? count($prompts) : 0,
                'raw' => $text,
            ]);

            // Pad or fallback
            $prompts = is_array($prompts) ? $prompts : [];
            while (count($prompts) < count($transitions)) {
                $idx = count($prompts);
                $prompts[] = "Smooth cinematic transition from frame {$transitions[$idx]['from_seq']} to frame {$transitions[$idx]['to_seq']}";
            }
        }

        return [
            'prompts' => $prompts,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ];
    }

    private function countTransitions(array $transitions): int
    {
        return count($transitions);
    }

    private function buildMessageHistory(Story $story): array
    {
        return $story->chatMessages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (ChatMessage $msg) => [
                'role' => $msg->role->value,
                'content' => $msg->content,
            ])
            ->toArray();
    }

    private function getSystemPrompt(Story $story): string
    {
        $context = $story->synopsis
            ? "The user has provided this initial synopsis: \"{$story->synopsis}\""
            : 'The user has not yet provided a synopsis.';

        return <<<PROMPT
You are a creative screenwriter and story consultant working with a client to develop a video script. Your role is to:

1. Guide the client through developing their story idea into a complete script
2. Ask clarifying questions about characters, setting, tone, and plot
3. Suggest creative improvements and alternatives
4. Help structure the narrative with clear scenes
5. Ensure characters are well-defined with distinct visual descriptions (important for later image generation)

Story title: "{$story->title}"
{$context}

Keep responses conversational and collaborative. Focus on building a compelling narrative that can be visualized as a short video. When the user is satisfied with the story direction, help them refine the final details before they finalize the script.
PROMPT;
    }
}
