import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageProps, Story, ApiUsageSummary } from '@/types';
import StoryStatusBadge from '@/Components/stories/StoryStatusBadge';
import CharacterGallery from '@/Components/characters/CharacterGallery';
import VideoStatusCard from '@/Components/video/VideoStatusCard';
import axios from 'axios';
import { useCallback } from 'react';

interface Props extends PageProps {
    story: Story;
    usageSummary: ApiUsageSummary;
}

export default function Show({ story, usageSummary }: Props) {
    const canChat = story.status === 'pending' || story.status === 'scripting';
    const canViewPipeline = story.full_script !== null;
    const isCharacterReview = story.status === 'character_review';

    const handleDeleteCharacter = useCallback(
        (characterId: number) => {
            axios
                .delete(route('stories.characters.destroy', { story: story.id, character: characterId }))
                .then(() => router.reload({ only: ['story'] }));
        },
        [story.id],
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {story.title}
                        </h2>
                        <StoryStatusBadge status={story.status} />
                    </div>
                    <div className="flex gap-2">
                        {canChat && (
                            <Link
                                href={route('stories.chat', story.id)}
                                className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                {story.status === 'pending' ? 'Start Chat' : 'Continue Chat'}
                            </Link>
                        )}
                        {canViewPipeline && (
                            <Link
                                href={route('stories.pipeline', story.id)}
                                className="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 print:hidden"
                            >
                                View Pipeline
                            </Link>
                        )}
                        <button
                            onClick={() => window.print()}
                            className="inline-flex items-center rounded-md bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 print:hidden"
                        >
                            Download Report
                        </button>
                    </div>
                </div>
            }
        >
            <Head title={story.title} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    {/* Print-only title */}
                    <h1 className="hidden print:block text-2xl font-bold text-gray-900">
                        {story.title}
                    </h1>

                    {/* API Usage */}
                    {(usageSummary.anthropic.call_count > 0 ||
                        usageSummary.nano_banana.call_count > 0 ||
                        usageSummary.kling.call_count > 0) && (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">API Usage</h3>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                {usageSummary.anthropic.call_count > 0 && (
                                    <div className="rounded-lg border border-gray-200 p-4">
                                        <h4 className="text-sm font-medium text-gray-500 mb-2">Anthropic</h4>
                                        <p className="text-2xl font-bold text-gray-900">
                                            ${(usageSummary.anthropic.cost_cents / 100).toFixed(4)}
                                        </p>
                                        <div className="mt-2 text-sm text-gray-500 space-y-1">
                                            <p>{usageSummary.anthropic.call_count} API calls</p>
                                            <p>{usageSummary.anthropic.input_tokens.toLocaleString()} input tokens</p>
                                            <p>{usageSummary.anthropic.output_tokens.toLocaleString()} output tokens</p>
                                        </div>
                                    </div>
                                )}
                                {usageSummary.nano_banana.call_count > 0 && (
                                    <div className="rounded-lg border border-gray-200 p-4">
                                        <h4 className="text-sm font-medium text-gray-500 mb-2">Nano Banana</h4>
                                        <p className="text-2xl font-bold text-gray-900">
                                            ${(usageSummary.nano_banana.cost_cents / 100).toFixed(2)}
                                        </p>
                                        <div className="mt-2 text-sm text-gray-500 space-y-1">
                                            <p>{usageSummary.nano_banana.call_count} API calls</p>
                                            <p>{usageSummary.nano_banana.call_count * 18} tokens (18/call)</p>
                                        </div>
                                    </div>
                                )}
                                {usageSummary.kling.call_count > 0 && (
                                    <div className="rounded-lg border border-gray-200 p-4">
                                        <h4 className="text-sm font-medium text-gray-500 mb-2">Kling</h4>
                                        <p className="text-2xl font-bold text-gray-900">
                                            ${(usageSummary.kling.cost_cents / 100).toFixed(2)}
                                        </p>
                                        <div className="mt-2 text-sm text-gray-500 space-y-1">
                                            <p>{usageSummary.kling.call_count} API calls</p>
                                            <p>~$0.50/call</p>
                                        </div>
                                    </div>
                                )}
                            </div>
                            {usageSummary.total_cost_cents > 0 && (
                                <div className="mt-4 pt-4 border-t border-gray-200">
                                    <p className="text-sm text-gray-500">
                                        Total estimated cost:{' '}
                                        <span className="font-semibold text-gray-900">
                                            ${(usageSummary.total_cost_cents / 100).toFixed(4)}
                                        </span>
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Synopsis */}
                    {story.synopsis && (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-2">Synopsis</h3>
                            <p className="text-gray-600">{story.synopsis}</p>
                        </div>
                    )}

                    {/* Script */}
                    {story.full_script && (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-2">Script</h3>
                            <div className="prose max-w-none text-gray-600 whitespace-pre-wrap">
                                {story.full_script}
                            </div>
                        </div>
                    )}

                    {/* Characters */}
                    {story.characters && story.characters.length > 0 && (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Characters</h3>
                            {isCharacterReview ? (
                                <CharacterGallery
                                    characters={story.characters}
                                    reviewMode
                                    onDeleteCharacter={handleDeleteCharacter}
                                />
                            ) : (
                                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                    {story.characters.map((character) => (
                                        <div
                                            key={character.id}
                                            className="rounded-lg border border-gray-200 overflow-hidden"
                                        >
                                            {character.image_url ? (
                                                <img
                                                    src={character.image_url}
                                                    alt={character.name}
                                                    className="w-full h-auto object-contain bg-gray-50"
                                                />
                                            ) : (
                                                <div className="w-full aspect-square bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center">
                                                    <span className="text-3xl font-bold text-indigo-300">
                                                        {character.name
                                                            .split(/\s+/)
                                                            .slice(0, 2)
                                                            .map((w) => w[0]?.toUpperCase())
                                                            .join('')}
                                                    </span>
                                                </div>
                                            )}
                                            <div className="p-3">
                                                <h4 className="font-medium text-gray-900 text-sm">
                                                    {character.name}
                                                </h4>
                                                {character.description && (
                                                    <p className="text-xs text-gray-500 mt-1 line-clamp-2">
                                                        {character.description}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Storyboard */}
                    {story.storyboard_frames && story.storyboard_frames.length > 0 && (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Storyboard</h3>
                            <div className="space-y-4">
                                {story.storyboard_frames.map((frame) => (
                                    <div
                                        key={frame.id}
                                        className="flex items-start gap-4 rounded-lg border border-gray-200 overflow-hidden"
                                    >
                                        {/* Image */}
                                        <div className="w-1/3 shrink-0 bg-gray-50">
                                            {frame.image_url ? (
                                                <img
                                                    src={frame.image_url}
                                                    alt={`Frame ${frame.sequence_number}`}
                                                    className="w-full h-auto object-contain"
                                                />
                                            ) : (
                                                <div className="w-full aspect-video flex items-center justify-center">
                                                    <span className="text-sm text-gray-400">No image</span>
                                                </div>
                                            )}
                                        </div>
                                        {/* Description */}
                                        <div className="flex-1 py-4 pr-4">
                                            <p className="text-xs font-semibold text-gray-500 mb-2">
                                                Frame {frame.sequence_number}
                                            </p>
                                            <p className="text-sm text-gray-700 whitespace-pre-wrap">
                                                {frame.scene_description}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Clips */}
                    {story.mini_videos && story.mini_videos.length > 0 && (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Clips</h3>
                            <div className="space-y-6">
                                {story.mini_videos.map((video) => (
                                    <div
                                        key={video.id}
                                        className="rounded-lg border border-gray-200 overflow-hidden p-4"
                                    >
                                        <div className="flex items-center gap-2 mb-3">
                                            <h4 className="text-sm font-semibold text-gray-700">
                                                Clip {video.sequence_number}
                                            </h4>
                                            <span className="text-xs text-gray-400">
                                                Frame {video.frame_from?.sequence_number ?? '?'} → Frame {video.frame_to?.sequence_number ?? '?'}
                                            </span>
                                        </div>
                                        <div className="flex items-start gap-4">
                                            {/* Start frame */}
                                            <div className="w-1/3 shrink-0">
                                                {video.frame_from?.image_url ? (
                                                    <img
                                                        src={video.frame_from.image_url}
                                                        alt={`Frame ${video.frame_from.sequence_number}`}
                                                        className="w-full h-auto object-contain rounded bg-gray-50"
                                                    />
                                                ) : (
                                                    <div className="w-full aspect-video bg-gray-100 rounded flex items-center justify-center">
                                                        <span className="text-xs text-gray-400">No image</span>
                                                    </div>
                                                )}
                                            </div>
                                            {/* End frame */}
                                            <div className="w-1/3 shrink-0">
                                                {video.frame_to?.image_url ? (
                                                    <img
                                                        src={video.frame_to.image_url}
                                                        alt={`Frame ${video.frame_to.sequence_number}`}
                                                        className="w-full h-auto object-contain rounded bg-gray-50"
                                                    />
                                                ) : (
                                                    <div className="w-full aspect-video bg-gray-100 rounded flex items-center justify-center">
                                                        <span className="text-xs text-gray-400">No image</span>
                                                    </div>
                                                )}
                                            </div>
                                            {/* Prompt */}
                                            <div className="w-1/3">
                                                <label className="text-xs font-medium text-gray-400 mb-1 block">Prompt</label>
                                                <p className="text-sm text-gray-600 whitespace-pre-wrap">
                                                    {video.prompt || '—'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Final Video */}
                    {story.latest_video && (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6 print:hidden">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Final Video</h3>
                            <VideoStatusCard video={story.latest_video} />
                        </div>
                    )}

                    {/* Error */}
                    {story.error_message && (
                        <div className="overflow-hidden bg-red-50 shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-red-800 mb-2">Error</h3>
                            <p className="text-red-600">{story.error_message}</p>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
