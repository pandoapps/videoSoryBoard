<?php

namespace App\Http\Controllers;

use App\Enums\MessageRole;
use App\Enums\StoryStatus;
use App\Http\Requests\SendChatMessageRequest;
use App\Models\Story;
use App\Services\AnthropicService;
use App\Services\ApiUsageTracker;
use App\Services\PipelineOrchestrator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(
        private AnthropicService $anthropic,
        private PipelineOrchestrator $pipeline,
        private ApiUsageTracker $usageTracker,
    ) {}

    public function index(Request $request, Story $story): Response
    {
        abort_unless($story->user_id === $request->user()->id, 403);

        if ($story->status === StoryStatus::Pending) {
            $story->update(['status' => StoryStatus::Scripting]);
        }

        $story->load('characters');

        return Inertia::render('Chat/ChatPage', [
            'story' => $story,
            'messages' => $story->chatMessages()->orderBy('created_at')->get(),
        ]);
    }

    public function store(SendChatMessageRequest $request, Story $story)
    {
        $this->anthropic->forUser($request->user()->id);

        // Save user message
        $userMessage = $story->chatMessages()->create([
            'role' => MessageRole::User,
            'content' => $request->message,
        ]);

        try {
            $response = $this->anthropic->chat($story, $request->message);
        } catch (\RuntimeException $e) {
            // Remove the user message since the AI failed to respond
            $userMessage->delete();

            $errorMessage = $this->friendlyError($e->getMessage());

            if ($request->wantsJson()) {
                return response()->json(['error' => $errorMessage], 422);
            }

            return redirect()->route('stories.chat', $story)
                ->with('error', $errorMessage);
        }

        $this->usageTracker->recordAnthropic(
            $story->id,
            'chat',
            $response['input_tokens'],
            $response['output_tokens'],
        );

        $assistantMessage = $story->chatMessages()->create([
            'role' => MessageRole::Assistant,
            'content' => $response['content'],
            'token_count' => $response['output_tokens'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $assistantMessage,
            ]);
        }

        return redirect()->route('stories.chat', $story);
    }

    public function finalize(Request $request, Story $story)
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        $this->anthropic->forUser($request->user()->id);

        try {
            $result = $this->anthropic->extractScript($story);
        } catch (\RuntimeException $e) {
            return redirect()->route('stories.chat', $story)
                ->with('error', $this->friendlyError($e->getMessage()));
        }

        $script = $result['content'];

        $this->usageTracker->recordAnthropic(
            $story->id,
            'extract_script',
            $result['input_tokens'],
            $result['output_tokens'],
        );

        $story->update(['full_script' => $script]);

        $story->chatMessages()->create([
            'role' => MessageRole::Assistant,
            'content' => "Script finalized! Moving to character generation phase.\n\n---\n\n" . $script,
        ]);

        $this->pipeline->startPipeline($story);

        return redirect()->route('stories.pipeline', $story)
            ->with('success', 'Script finalized! Pipeline started.');
    }

    private function friendlyError(string $raw): string
    {
        if (str_contains($raw, 'not configured')) {
            return 'API key da Anthropic nao esta configurada. Acesse Settings para adiciona-la.';
        }

        if (str_contains($raw, 'authentication_error') || str_contains($raw, '401')) {
            return 'API key da Anthropic e invalida. Verifique a chave em Settings.';
        }

        if (str_contains($raw, 'rate_limit') || str_contains($raw, '429')) {
            return 'Limite de requisicoes atingido na Anthropic. Aguarde um momento e tente novamente.';
        }

        if (str_contains($raw, 'overloaded') || str_contains($raw, '529')) {
            return 'A API da Anthropic esta sobrecarregada. Tente novamente em alguns minutos.';
        }

        if (str_contains($raw, 'Could not resolve host') || str_contains($raw, 'Connection refused')) {
            return 'Nao foi possivel conectar a API da Anthropic. Verifique sua conexao com a internet.';
        }

        return 'Erro ao comunicar com a IA. Tente novamente. Detalhes: ' . \Illuminate\Support\Str::limit($raw, 150);
    }
}
