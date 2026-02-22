import { Video } from '@/types';
import VideoPlayer from './VideoPlayer';

const statusLabels: Record<string, { label: string; className: string }> = {
    queued: { label: 'Queued', className: 'bg-gray-100 text-gray-800' },
    processing: { label: 'Processing', className: 'bg-yellow-100 text-yellow-800' },
    completed: { label: 'Completed', className: 'bg-green-100 text-green-800' },
    failed: { label: 'Failed', className: 'bg-red-100 text-red-800' },
};

export default function VideoStatusCard({ video }: { video: Video }) {
    const status = statusLabels[video.status] || statusLabels.queued;

    return (
        <div>
            <div className="flex items-center gap-3 mb-4">
                <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${status.className}`}
                >
                    {status.label}
                </span>
                {video.duration_seconds && (
                    <span className="text-sm text-gray-500">
                        Duration: {Math.floor(video.duration_seconds / 60)}:{String(video.duration_seconds % 60).padStart(2, '0')}
                    </span>
                )}
            </div>

            {video.status === 'completed' && video.video_url && (
                <>
                    <VideoPlayer url={video.video_url} />
                    <div className="mt-3 flex justify-end">
                        <button
                            onClick={async () => {
                                try {
                                    const res = await fetch(video.video_url!);
                                    const blob = await res.blob();
                                    const url = URL.createObjectURL(blob);
                                    const a = document.createElement('a');
                                    a.href = url;
                                    a.download = `video-${video.id}.mp4`;
                                    document.body.appendChild(a);
                                    a.click();
                                    a.remove();
                                    URL.revokeObjectURL(url);
                                } catch {
                                    window.open(video.video_url!, '_blank');
                                }
                            }}
                            className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clipRule="evenodd" />
                            </svg>
                            Download
                        </button>
                    </div>
                </>
            )}

            {video.status === 'processing' && (
                <div className="flex items-center justify-center h-48 bg-gray-50 rounded-lg">
                    <div className="text-center">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto mb-2"></div>
                        <p className="text-sm text-gray-500">Video is being produced...</p>
                    </div>
                </div>
            )}

            {video.status === 'failed' && (
                <div className="p-4 bg-red-50 rounded-lg">
                    <p className="text-sm text-red-600">Video production failed. Please try again.</p>
                </div>
            )}
        </div>
    );
}
