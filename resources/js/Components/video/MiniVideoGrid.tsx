import { Video } from '@/types';
import MiniVideoCard, { ClipGenerationParams } from './MiniVideoCard';

interface Props {
    miniVideos: Video[];
    regeneratingIds: Set<number>;
    onRegenerate: (videoId: number, params: ClipGenerationParams) => void;
    onUpload?: (videoId: number, file: File) => void;
}

export default function MiniVideoGrid({ miniVideos, regeneratingIds, onRegenerate, onUpload }: Props) {
    if (miniVideos.length === 0) {
        return (
            <div className="text-center py-8 text-gray-400 text-sm">
                No video clips generated yet.
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {miniVideos.map((video) => (
                <MiniVideoCard
                    key={video.id}
                    video={video}
                    regenerating={regeneratingIds.has(video.id)}
                    onRegenerate={onRegenerate}
                    onUpload={onUpload}
                />
            ))}
        </div>
    );
}
