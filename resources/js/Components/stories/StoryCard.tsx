import { Link } from '@inertiajs/react';
import { Story } from '@/types';
import StoryStatusBadge from './StoryStatusBadge';

export default function StoryCard({ story }: { story: Story }) {
    return (
        <Link
            href={route('stories.show', story.id)}
            className="block overflow-hidden rounded-lg bg-white shadow-sm hover:shadow-md transition-shadow"
        >
            {story.final_video?.status === 'completed' && story.final_video.video_url && (
                <video
                    src={story.final_video.video_url}
                    controls
                    muted
                    playsInline
                    className="w-full aspect-video object-cover"
                />
            )}
            <div className="p-6">
                <div className="flex items-start justify-between">
                    <h3 className="text-lg font-semibold text-gray-900 truncate">
                        {story.title}
                    </h3>
                    <div className="flex items-center gap-2 shrink-0 ml-2">
                        {(story.total_cost_cents ?? 0) > 0 && (
                            <span className="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                ${((story.total_cost_cents ?? 0) / 100).toFixed(2)}
                            </span>
                        )}
                        <StoryStatusBadge status={story.status} />
                    </div>
                </div>
                {story.synopsis && (
                    <p className="mt-2 text-sm text-gray-600 line-clamp-2">
                        {story.synopsis}
                    </p>
                )}
                <p className="mt-4 text-xs text-gray-400">
                    Created {new Date(story.created_at).toLocaleDateString()}
                </p>
            </div>
        </Link>
    );
}
