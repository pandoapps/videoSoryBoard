import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageProps, Story } from '@/types';
import SectionHeader from '@/Components/pipeline/SectionHeader';
import SectionPlaceholder from '@/Components/pipeline/SectionPlaceholder';
import CharacterGallery from '@/Components/characters/CharacterGallery';
import StoryboardGrid from '@/Components/storyboard/StoryboardGrid';
import MiniVideoGrid from '@/Components/video/MiniVideoGrid';
import { ClipGenerationParams } from '@/Components/video/MiniVideoCard';
import VideoStatusCard from '@/Components/video/VideoStatusCard';
import { useCallback, useEffect, useMemo, useState } from 'react';
import axios from 'axios';

interface StageInfo {
    stage: string;
    status: string;
}

interface Props extends PageProps {
    story: Story;
    stages: StageInfo[];
    klingConfigured: boolean;
}

function getStageStatus(stages: StageInfo[], stage: string): string {
    return stages.find((s) => s.stage === stage)?.status ?? 'pending';
}

export default function PipelineView({ story, stages, klingConfigured }: Props) {
    const isProcessing = stages.some((s) => s.status === 'in_progress');
    const isCharacterReview = story.status === 'character_review';
    const isStoryboardReview = story.status === 'storyboard_review';

    const scriptStatus = getStageStatus(stages, 'script');
    const characterStatus = getStageStatus(stages, 'characters');
    const storyboardStatus = getStageStatus(stages, 'storyboard');
    const videoStatus = getStageStatus(stages, 'video');

    // Derive "scenes" status from mini-videos existing but final not yet done
    const miniVideos = story.mini_videos ?? [];
    const finalVideo = story.final_video ?? null;
    const scenesStatus = useMemo(() => {
        if (miniVideos.length === 0) return 'pending';
        if (miniVideos.some((v) => v.status === 'failed')) return 'failed';
        if (miniVideos.every((v) => v.status === 'completed')) return 'completed';
        if (miniVideos.some((v) => v.status === 'processing')) return 'in_progress';
        return 'pending';
    }, [miniVideos]);

    const finalVideoStatus = useMemo(() => {
        if (!finalVideo) return 'pending';
        if (finalVideo.status === 'completed') return 'completed';
        if (finalVideo.status === 'failed') return 'failed';
        return 'in_progress';
    }, [finalVideo]);

    const [regeneratingIds, setRegeneratingIds] = useState<Set<number>>(() => {
        const ids = new Set<number>();
        story.characters?.forEach((c) => {
            if (c.metadata?.regenerating) ids.add(c.id);
        });
        return ids;
    });
    const [regeneratingFrameIds, setRegeneratingFrameIds] = useState<Set<number>>(() => {
        const ids = new Set<number>();
        story.storyboard_frames?.forEach((f) => {
            if (f.metadata?.regenerating) ids.add(f.id);
        });
        return ids;
    });
    const [regeneratingVideoIds, setRegeneratingVideoIds] = useState<Set<number>>(new Set());
    const [concatenating, setConcatenating] = useState(false);
    const [approving, setApproving] = useState(false);
    const [approvingStoryboard, setApprovingStoryboard] = useState(false);

    // Sequential "Generate All" queues
    const [charGenerateQueue, setCharGenerateQueue] = useState<Array<{id: number, prompt: string}>>([]);
    const [charQueueTotal, setCharQueueTotal] = useState(0);
    const [frameGenerateQueue, setFrameGenerateQueue] = useState<Array<{id: number, prompt: string}>>([]);
    const [frameQueueTotal, setFrameQueueTotal] = useState(0);
    const [videoGenerateQueue, setVideoGenerateQueue] = useState<Array<{id: number, params: ClipGenerationParams}>>([]);
    const [videoQueueTotal, setVideoQueueTotal] = useState(0);

    const allMiniVideosCompleted =
        miniVideos.length > 0 && miniVideos.every((v) => v.status === 'completed');
    const anyMiniVideoProcessing = miniVideos.some(
        (v) => v.status === 'processing' || regeneratingVideoIds.has(v.id),
    );
    const showConcatenateButton = allMiniVideosCompleted && !finalVideo && !concatenating;

    const canStart =
        story.full_script &&
        story.status !== 'completed' &&
        !isProcessing &&
        story.status !== 'failed' &&
        story.status !== 'character_review' &&
        story.status !== 'storyboard_review' &&
        stages.filter((s) => s.status === 'completed').length <= 1;

    // Initial prompt = first user message in chat
    const initialPrompt = story.chat_messages?.find((m) => m.role === 'user')?.content ?? '';

    // Poll for updates while processing or regenerating
    useEffect(() => {
        if (
            !isProcessing &&
            regeneratingIds.size === 0 &&
            regeneratingFrameIds.size === 0 &&
            !anyMiniVideoProcessing &&
            !concatenating &&
            charGenerateQueue.length === 0 &&
            frameGenerateQueue.length === 0 &&
            videoGenerateQueue.length === 0
        )
            return;

        const interval = setInterval(() => {
            router.reload({ only: ['story', 'stages'] });
        }, 5000);

        return () => clearInterval(interval);
    }, [
        isProcessing,
        regeneratingIds.size,
        regeneratingFrameIds.size,
        anyMiniVideoProcessing,
        concatenating,
        charGenerateQueue.length,
        frameGenerateQueue.length,
        videoGenerateQueue.length,
    ]);

    // Sync regenerating state from server data
    useEffect(() => {
        if (!story.characters) return;
        const ids = new Set<number>();
        story.characters.forEach((c) => {
            if (c.metadata?.regenerating) ids.add(c.id);
        });
        setRegeneratingIds(ids);
    }, [story.characters]);

    useEffect(() => {
        if (!story.storyboard_frames) return;
        const ids = new Set<number>();
        story.storyboard_frames.forEach((f) => {
            if (f.metadata?.regenerating) ids.add(f.id);
        });
        setRegeneratingFrameIds(ids);
    }, [story.storyboard_frames]);

    useEffect(() => {
        const ids = new Set<number>();
        miniVideos.forEach((v) => {
            if (v.status === 'processing') ids.add(v.id);
        });
        setRegeneratingVideoIds(ids);
        if (finalVideo?.status === 'completed') setConcatenating(false);
    }, [miniVideos, finalVideo]);

    // --- Handlers ---
    const handleStart = () => {
        router.post(route('stories.pipeline.start', story.id));
    };

    const handleRegenerate = useCallback(
        (characterId: number, prompt: string) => {
            setRegeneratingIds((prev) => new Set(prev).add(characterId));
            axios
                .post(route('stories.characters.regenerate', { story: story.id, character: characterId }), { prompt })
                .catch(() => {
                    setRegeneratingIds((prev) => { const n = new Set(prev); n.delete(characterId); return n; });
                });
        },
        [story.id],
    );

    const handleCreateCharacter = useCallback(
        (name: string, description: string) => {
            axios
                .post(route('stories.characters.store', story.id), { name, description })
                .then((res) => {
                    setRegeneratingIds((prev) => new Set(prev).add(res.data.character.id));
                    router.reload({ only: ['story', 'stages'] });
                });
        },
        [story.id],
    );

    const handleDeleteCharacter = useCallback(
        (characterId: number) => {
            axios
                .delete(route('stories.characters.destroy', { story: story.id, character: characterId }))
                .then(() => router.reload({ only: ['story', 'stages'] }));
        },
        [story.id],
    );

    const handleRegenerateFrame = useCallback(
        (frameId: number, prompt: string) => {
            setRegeneratingFrameIds((prev) => new Set(prev).add(frameId));
            axios
                .post(route('stories.frames.regenerate', { story: story.id, frame: frameId }), { prompt })
                .catch(() => {
                    setRegeneratingFrameIds((prev) => { const n = new Set(prev); n.delete(frameId); return n; });
                });
        },
        [story.id],
    );

    const handleRegenerateMiniVideo = useCallback(
        (videoId: number, params: ClipGenerationParams) => {
            setRegeneratingVideoIds((prev) => new Set(prev).add(videoId));
            axios
                .post(route('stories.videos.regenerate', { story: story.id, video: videoId }), params)
                .catch(() => {
                    setRegeneratingVideoIds((prev) => { const n = new Set(prev); n.delete(videoId); return n; });
                });
        },
        [story.id],
    );

    const handleGenerateAllCharacters = useCallback(() => {
        const characters = story.characters ?? [];
        const queue = characters
            .filter((c) => !c.image_url && !regeneratingIds.has(c.id))
            .map((c) => ({
                id: c.id,
                prompt: `Professional character design, full body portrait: ${c.name}. ${c.description ?? ''}. High quality, detailed, consistent art style, suitable for animation.`,
            }));
        setCharGenerateQueue(queue);
        setCharQueueTotal(queue.length);
    }, [story.characters, regeneratingIds]);

    const handleGenerateAllFrames = useCallback(() => {
        const frames = story.storyboard_frames ?? [];
        const queue = frames
            .filter((f) => !f.image_url && !regeneratingFrameIds.has(f.id))
            .map((f) => ({
                id: f.id,
                prompt: f.prompt ?? `Comic book panel, sequential art style, no text or speech bubbles: ${f.scene_description}. Cinematic composition, vivid colors, detailed illustration.`,
            }));
        setFrameGenerateQueue(queue);
        setFrameQueueTotal(queue.length);
    }, [story.storyboard_frames, regeneratingFrameIds]);

    const handleGenerateAllMiniVideos = useCallback(() => {
        const queue = miniVideos
            .filter((v) => !v.video_url && !regeneratingVideoIds.has(v.id) && v.status !== 'processing')
            .map((v) => ({
                id: v.id,
                params: {
                    prompt: v.prompt ?? '',
                    duration: '5',
                    model_name: 'kling-v2-6',
                    mode: 'pro',
                    camera_control: '',
                } as ClipGenerationParams,
            }));
        setVideoGenerateQueue(queue);
        setVideoQueueTotal(queue.length);
    }, [miniVideos, regeneratingVideoIds]);

    // Sequential queue processing: dispatch next item only when current one finishes
    useEffect(() => {
        if (charGenerateQueue.length === 0 || regeneratingIds.size > 0) return;
        const next = charGenerateQueue[0];
        setCharGenerateQueue((prev) => prev.slice(1));
        handleRegenerate(next.id, next.prompt);
    }, [charGenerateQueue, regeneratingIds.size, handleRegenerate]);

    useEffect(() => {
        if (frameGenerateQueue.length === 0 || regeneratingFrameIds.size > 0) return;
        const next = frameGenerateQueue[0];
        setFrameGenerateQueue((prev) => prev.slice(1));
        handleRegenerateFrame(next.id, next.prompt);
    }, [frameGenerateQueue, regeneratingFrameIds.size, handleRegenerateFrame]);

    useEffect(() => {
        if (videoGenerateQueue.length === 0 || regeneratingVideoIds.size > 0) return;
        const next = videoGenerateQueue[0];
        setVideoGenerateQueue((prev) => prev.slice(1));
        handleRegenerateMiniVideo(next.id, next.params);
    }, [videoGenerateQueue, regeneratingVideoIds.size, handleRegenerateMiniVideo]);

    // Reset totals when queue finishes
    useEffect(() => {
        if (charQueueTotal > 0 && charGenerateQueue.length === 0 && regeneratingIds.size === 0) {
            setCharQueueTotal(0);
        }
    }, [charQueueTotal, charGenerateQueue.length, regeneratingIds.size]);

    useEffect(() => {
        if (frameQueueTotal > 0 && frameGenerateQueue.length === 0 && regeneratingFrameIds.size === 0) {
            setFrameQueueTotal(0);
        }
    }, [frameQueueTotal, frameGenerateQueue.length, regeneratingFrameIds.size]);

    useEffect(() => {
        if (videoQueueTotal > 0 && videoGenerateQueue.length === 0 && regeneratingVideoIds.size === 0) {
            setVideoQueueTotal(0);
        }
    }, [videoQueueTotal, videoGenerateQueue.length, regeneratingVideoIds.size]);

    const handleUploadCharacterImage = useCallback(
        (characterId: number, file: File) => {
            const formData = new FormData();
            formData.append('image', file);
            axios
                .post(route('stories.characters.upload', { story: story.id, character: characterId }), formData)
                .then(() => router.reload({ only: ['story', 'stages'] }));
        },
        [story.id],
    );

    const handleUploadFrameImage = useCallback(
        (frameId: number, file: File) => {
            const formData = new FormData();
            formData.append('image', file);
            axios
                .post(route('stories.frames.upload', { story: story.id, frame: frameId }), formData)
                .then(() => router.reload({ only: ['story', 'stages'] }));
        },
        [story.id],
    );

    const handleDeleteFrame = useCallback(
        (frameId: number) => {
            axios
                .delete(route('stories.frames.destroy', { story: story.id, frame: frameId }))
                .then(() => router.reload({ only: ['story', 'stages'] }));
        },
        [story.id],
    );

    const handleUploadClipVideo = useCallback(
        (videoId: number, file: File) => {
            const formData = new FormData();
            formData.append('video', file);
            axios
                .post(route('stories.videos.upload', { story: story.id, video: videoId }), formData)
                .then(() => router.reload({ only: ['story', 'stages'] }));
        },
        [story.id],
    );

    const handleConcatenate = useCallback(() => {
        setConcatenating(true);
        axios.post(route('stories.videos.concatenate', story.id)).catch(() => setConcatenating(false));
    }, [story.id]);

    const handleApproveCharacters = () => {
        setApproving(true);
        router.post(route('stories.pipeline.approve-characters', story.id), {}, {
            onFinish: () => setApproving(false),
        });
    };

    const handleApproveStoryboard = () => {
        setApprovingStoryboard(true);
        router.post(route('stories.pipeline.approve-storyboard', story.id), {}, {
            onFinish: () => setApprovingStoryboard(false),
        });
    };

    const canRevert = !isProcessing && story.status !== 'pending';

    const handleRevert = (stage: string) => {
        const warnings: Record<string, string> = {
            script: 'This will delete all characters, storyboard frames, and videos. You will return to the chat to continue editing the script.',
            characters: 'This will delete all storyboard frames and videos. You will return to character review.',
            storyboard: 'This will delete all videos and regenerate the storyboard.',
        };
        if (window.confirm(`Are you sure you want to redo this stage?\n\n${warnings[stage] || 'Downstream data will be deleted.'}`)) {
            router.post(route('stories.pipeline.revert', { story: story.id, stage }));
        }
    };

    // --- Icon helpers ---
    const scriptIcon = (
        <svg xmlns="http://www.w3.org/2000/svg" className="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
    );

    const characterIcon = (
        <svg xmlns="http://www.w3.org/2000/svg" className="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
    );

    const storyboardIcon = (
        <svg xmlns="http://www.w3.org/2000/svg" className="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
        </svg>
    );

    const scenesIcon = (
        <svg xmlns="http://www.w3.org/2000/svg" className="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0118 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-3.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 7.746 6 7.125v-1.5M4.875 8.25C5.496 8.25 6 8.754 6 9.375v1.5m0-5.25v5.25m0-5.25C6 5.004 6.504 4.5 7.125 4.5h9.75c.621 0 1.125.504 1.125 1.125m1.125 2.625h1.5m-1.5 0A1.125 1.125 0 0118 7.125v-1.5m1.125 2.625c-.621 0-1.125.504-1.125 1.125v1.5m2.625-2.625c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M18 5.625v5.25M7.125 12h9.75m-9.75 0A1.125 1.125 0 016 10.875M7.125 12C6.504 12 6 12.504 6 13.125m0-2.25C6 11.496 5.496 12 4.875 12M18 10.875c0 .621-.504 1.125-1.125 1.125M18 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m-12 5.25v-5.25m0 5.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125m-12 0v-1.5c0-.621-.504-1.125-1.125-1.125M18 18.375v-5.25m0 5.25v-1.5c0-.621.504-1.125 1.125-1.125M18 13.125v1.5c0 .621.504 1.125 1.125 1.125M18 13.125c0-.621.504-1.125 1.125-1.125M6 13.125v1.5c0 .621-.504 1.125-1.125 1.125M6 13.125C6 12.504 5.496 12 4.875 12m-1.5 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 12 6 12.504 6 13.125" />
        </svg>
    );

    const videoIcon = (
        <svg xmlns="http://www.w3.org/2000/svg" className="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
        </svg>
    );

    // Count data
    const characterCount = story.characters?.length ?? 0;
    const frameCount = story.storyboard_frames?.length ?? 0;
    const expectedScenes = frameCount > 1 ? frameCount - 1 : 0;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Pipeline: {story.title}
                    </h2>
                    <div className="flex gap-2">
                        <Link
                            href={route('stories.show', story.id)}
                            className="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
                        >
                            Back to Story
                        </Link>
                        {canStart && (
                            <button
                                onClick={handleStart}
                                className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                Start Pipeline
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`Pipeline: ${story.title}`} />

            <div className="py-8">
                <div className="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">

                    {/* Error banner */}
                    {story.error_message && (
                        <div className="bg-red-50 border border-red-200 shadow-sm sm:rounded-lg p-4">
                            <div className="flex gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-red-500 shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                </svg>
                                <div>
                                    <h4 className="text-sm font-medium text-red-800">Error</h4>
                                    <p className="text-sm text-red-600 mt-0.5">{story.error_message}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* ═══════════════════════════════════
                        1. INITIAL PROMPT
                    ═══════════════════════════════════ */}
                    <section className="bg-white shadow-sm sm:rounded-lg p-6">
                        <SectionHeader number={1} title="Initial Prompt" status={initialPrompt ? 'completed' : 'pending'} />
                        {initialPrompt ? (
                            <div className="rounded-lg bg-gray-50 border border-gray-200 p-4">
                                <p className="text-sm text-gray-700 whitespace-pre-wrap">{initialPrompt}</p>
                            </div>
                        ) : (
                            <div className="rounded-lg border border-dashed border-gray-200 p-8 text-center">
                                <p className="text-sm text-gray-400">No synopsis provided yet.</p>
                            </div>
                        )}
                    </section>

                    {/* ═══════════════════════════════════
                        2. SCRIPT
                    ═══════════════════════════════════ */}
                    <section className="bg-white shadow-sm sm:rounded-lg p-6">
                        <SectionHeader number={2} title="Script" status={scriptStatus}>
                            {scriptStatus === 'completed' && canRevert && (
                                <button
                                    onClick={() => handleRevert('script')}
                                    className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-amber-700 bg-amber-100 hover:bg-amber-200"
                                >
                                    Edit Script
                                </button>
                            )}
                        </SectionHeader>

                        {story.full_script ? (
                            <div className="rounded-lg bg-gray-50 border border-gray-200 p-4 max-h-96 overflow-y-auto">
                                <pre className="text-sm text-gray-700 whitespace-pre-wrap font-sans">{story.full_script}</pre>
                            </div>
                        ) : scriptStatus === 'in_progress' ? (
                            <div className="flex items-center gap-3 py-8 justify-center">
                                <svg className="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span className="text-sm text-gray-500">Developing script through chat...</span>
                                <Link
                                    href={route('stories.chat', story.id)}
                                    className="text-sm text-indigo-600 hover:text-indigo-700 font-medium"
                                >
                                    Open Chat
                                </Link>
                            </div>
                        ) : (
                            <SectionPlaceholder
                                icon={scriptIcon}
                                title="Script not yet created"
                                description="Chat with the AI to develop your story script"
                                count={1}
                            />
                        )}
                    </section>

                    {/* ═══════════════════════════════════
                        3. CHARACTERS
                    ═══════════════════════════════════ */}
                    <section className="bg-white shadow-sm sm:rounded-lg p-6">
                        <SectionHeader
                            number={3}
                            title="Characters"
                            status={isCharacterReview ? 'review' : characterStatus}
                        >
                            <div className="flex items-center gap-2">
                                {characterCount > 0 && (
                                    <span className="text-xs text-gray-400">{characterCount} character{characterCount !== 1 ? 's' : ''}</span>
                                )}
                                {(story.characters ?? []).some((c) => !c.image_url) && (() => {
                                    const isRunning = charQueueTotal > 0 && (charGenerateQueue.length > 0 || regeneratingIds.size > 0);
                                    const progress = charQueueTotal - charGenerateQueue.length;
                                    return (
                                        <button
                                            onClick={handleGenerateAllCharacters}
                                            disabled={regeneratingIds.size > 0 || charGenerateQueue.length > 0}
                                            className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            {isRunning ? `Generating ${progress}/${charQueueTotal}...` : 'Generate All'}
                                        </button>
                                    );
                                })()}
                                {characterStatus === 'completed' && canRevert && !isCharacterReview && (
                                    <button
                                        onClick={() => handleRevert('characters')}
                                        className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-amber-700 bg-amber-100 hover:bg-amber-200"
                                    >
                                        Redo Characters
                                    </button>
                                )}
                            </div>
                        </SectionHeader>

                        {isCharacterReview && (
                            <div className="mb-4 rounded-md bg-amber-50 border border-amber-200 px-4 py-3">
                                <p className="text-sm text-amber-800">
                                    Review your characters below. You can regenerate, add, or delete characters before continuing.
                                </p>
                            </div>
                        )}

                        {characterCount > 0 || isCharacterReview ? (
                            <>
                                <CharacterGallery
                                    characters={story.characters ?? []}
                                    reviewMode={isCharacterReview}
                                    regeneratingIds={regeneratingIds}
                                    onRegenerate={handleRegenerate}
                                    onUploadImage={handleUploadCharacterImage}
                                    onCreateCharacter={handleCreateCharacter}
                                    onDeleteCharacter={handleDeleteCharacter}
                                />
                                {isCharacterReview && (
                                    <div className="mt-6 flex justify-end">
                                        <button
                                            onClick={handleApproveCharacters}
                                            disabled={approving || regeneratingIds.size > 0}
                                            className="inline-flex items-center rounded-md bg-green-600 px-6 py-3 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            {approving ? 'Approving...' : 'Approve Characters & Continue'}
                                        </button>
                                    </div>
                                )}
                            </>
                        ) : characterStatus === 'in_progress' ? (
                            <div className="flex items-center gap-3 py-8 justify-center">
                                <svg className="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span className="text-sm text-gray-500">Generating characters...</span>
                            </div>
                        ) : (
                            <SectionPlaceholder
                                icon={characterIcon}
                                title="Characters not yet generated"
                                description="Characters will be extracted from your script"
                                count={3}
                            />
                        )}
                    </section>

                    {/* ═══════════════════════════════════
                        4. STORYBOARD
                    ═══════════════════════════════════ */}
                    <section className="bg-white shadow-sm sm:rounded-lg p-6">
                        <SectionHeader
                            number={4}
                            title="Storyboard"
                            status={isStoryboardReview ? 'review' : storyboardStatus}
                        >
                            <div className="flex items-center gap-2">
                                {frameCount > 0 && (
                                    <span className="text-xs text-gray-400">{frameCount} frame{frameCount !== 1 ? 's' : ''}</span>
                                )}
                                {(story.storyboard_frames ?? []).some((f) => !f.image_url) && (() => {
                                    const isRunning = frameQueueTotal > 0 && (frameGenerateQueue.length > 0 || regeneratingFrameIds.size > 0);
                                    const progress = frameQueueTotal - frameGenerateQueue.length;
                                    return (
                                        <button
                                            onClick={handleGenerateAllFrames}
                                            disabled={regeneratingFrameIds.size > 0 || frameGenerateQueue.length > 0}
                                            className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            {isRunning ? `Generating ${progress}/${frameQueueTotal}...` : 'Generate All'}
                                        </button>
                                    );
                                })()}
                                {storyboardStatus === 'completed' && canRevert && !isStoryboardReview && (
                                    <button
                                        onClick={() => handleRevert('storyboard')}
                                        className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-amber-700 bg-amber-100 hover:bg-amber-200"
                                    >
                                        Redo Storyboard
                                    </button>
                                )}
                            </div>
                        </SectionHeader>

                        {isStoryboardReview && (
                            <div className="mb-4 rounded-md bg-amber-50 border border-amber-200 px-4 py-3">
                                <p className="text-sm text-amber-800">
                                    Review your storyboard below. You can regenerate individual frames before approving.
                                </p>
                            </div>
                        )}

                        {!klingConfigured && isStoryboardReview && (
                            <div className="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3">
                                <p className="text-sm text-red-800">
                                    Kling API key is not configured.{' '}
                                    <Link
                                        href={route('admin.settings')}
                                        className="font-medium underline hover:text-red-900"
                                    >
                                        Add it in Settings
                                    </Link>{' '}
                                    before starting video generation.
                                </p>
                            </div>
                        )}

                        {frameCount > 0 ? (
                            <>
                                <StoryboardGrid
                                    frames={story.storyboard_frames ?? []}
                                    storyId={story.id}
                                    regeneratingIds={regeneratingFrameIds}
                                    onRegenerate={handleRegenerateFrame}
                                    onUploadImage={handleUploadFrameImage}
                                    onDelete={handleDeleteFrame}
                                />
                                {isStoryboardReview && (
                                    <div className="mt-6 flex justify-end">
                                        <button
                                            onClick={handleApproveStoryboard}
                                            disabled={approvingStoryboard || regeneratingFrameIds.size > 0 || !klingConfigured}
                                            className="inline-flex items-center rounded-md bg-green-600 px-6 py-3 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            {approvingStoryboard ? 'Approving...' : 'Approve Storyboard & Start Video'}
                                        </button>
                                    </div>
                                )}
                            </>
                        ) : storyboardStatus === 'in_progress' ? (
                            <div className="flex items-center gap-3 py-8 justify-center">
                                <svg className="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span className="text-sm text-gray-500">Generating storyboard frames...</span>
                            </div>
                        ) : (
                            <SectionPlaceholder
                                icon={storyboardIcon}
                                title="Storyboard not yet generated"
                                description="Scene-by-scene frames will be created from your script and characters"
                                count={4}
                            />
                        )}
                    </section>

                    {/* ═══════════════════════════════════
                        5. SCENES (Mini-Videos / Clips)
                    ═══════════════════════════════════ */}
                    <section className="bg-white shadow-sm sm:rounded-lg p-6">
                        <SectionHeader number={5} title="Clips" status={scenesStatus}>
                            <div className="flex items-center gap-2">
                                {miniVideos.length > 0 && (
                                    <span className="text-xs text-gray-400">
                                        {miniVideos.filter((v) => v.status === 'completed').length} / {miniVideos.length} clips completed
                                    </span>
                                )}
                                {miniVideos.some((v) => !v.video_url && v.status !== 'processing') && (() => {
                                    const isRunning = videoQueueTotal > 0 && (videoGenerateQueue.length > 0 || regeneratingVideoIds.size > 0);
                                    const progress = videoQueueTotal - videoGenerateQueue.length;
                                    return (
                                        <button
                                            onClick={handleGenerateAllMiniVideos}
                                            disabled={regeneratingVideoIds.size > 0 || videoGenerateQueue.length > 0}
                                            className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            {isRunning ? `Generating ${progress}/${videoQueueTotal}...` : 'Generate All'}
                                        </button>
                                    );
                                })()}
                            </div>
                        </SectionHeader>

                        {miniVideos.length > 0 ? (
                            <MiniVideoGrid
                                miniVideos={miniVideos}
                                regeneratingIds={regeneratingVideoIds}
                                onRegenerate={handleRegenerateMiniVideo}
                                onUpload={handleUploadClipVideo}
                            />
                        ) : scenesStatus === 'in_progress' || videoStatus === 'in_progress' ? (
                            <div className="flex items-center gap-3 py-8 justify-center">
                                <svg className="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span className="text-sm text-gray-500">Creating video clips from consecutive frames...</span>
                            </div>
                        ) : (
                            <SectionPlaceholder
                                icon={scenesIcon}
                                title="Scenes not yet generated"
                                description={expectedScenes > 0
                                    ? `${expectedScenes} clip${expectedScenes !== 1 ? 's' : ''} will be created from consecutive storyboard frame pairs`
                                    : 'Video clips will be generated from consecutive storyboard frame pairs'
                                }
                                count={expectedScenes > 0 ? Math.min(expectedScenes, 4) : 3}
                            />
                        )}
                    </section>

                    {/* ═══════════════════════════════════
                        6. FINAL VIDEO
                    ═══════════════════════════════════ */}
                    <section className="bg-white shadow-sm sm:rounded-lg p-6">
                        <SectionHeader number={6} title="Final Video" status={finalVideoStatus} />

                        {finalVideo ? (
                            <VideoStatusCard video={finalVideo} />
                        ) : allMiniVideosCompleted && !concatenating ? (
                            <div className="flex flex-col items-center gap-4 py-8">
                                <p className="text-sm text-gray-500">All clips are ready. Combine them into the final video.</p>
                                <button
                                    onClick={handleConcatenate}
                                    className="inline-flex items-center rounded-md bg-green-600 px-6 py-3 text-sm font-medium text-white hover:bg-green-700"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clipRule="evenodd" />
                                    </svg>
                                    Concatenate All Clips
                                </button>
                            </div>
                        ) : concatenating ? (
                            <div className="flex items-center gap-3 py-8 justify-center">
                                <svg className="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span className="text-sm text-gray-500">Concatenating clips into final video...</span>
                            </div>
                        ) : (
                            <SectionPlaceholder
                                icon={videoIcon}
                                title="Final video not yet produced"
                                description="All scene clips will be concatenated into the final video"
                                count={1}
                            />
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
