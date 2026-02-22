import { Video } from '@/types';
import VideoPlayer from './VideoPlayer';
import { useEffect, useState } from 'react';

export interface ClipGenerationParams {
    prompt: string;
    duration: string;
    model_name: string;
    mode: string;
    camera_control: string;
}

interface Props {
    video: Video;
    regenerating: boolean;
    onRegenerate?: (videoId: number, params: ClipGenerationParams) => void;
    onUpload?: (videoId: number, file: File) => void;
}

const statusLabels: Record<string, { label: string; className: string }> = {
    queued: { label: 'Queued', className: 'bg-gray-100 text-gray-800' },
    pending: { label: 'Pending', className: 'bg-gray-100 text-gray-800' },
    processing: { label: 'Processing', className: 'bg-yellow-100 text-yellow-800' },
    completed: { label: 'Completed', className: 'bg-green-100 text-green-800' },
    failed: { label: 'Failed', className: 'bg-red-100 text-red-800' },
};

const cameraOptions = [
    { value: '', label: 'None' },
    { value: 'simple', label: 'Simple (Custom)' },
    { value: 'down_back', label: 'Down & Back' },
    { value: 'forward_up', label: 'Forward & Up' },
    { value: 'right_turn_forward', label: 'Right Turn Forward' },
    { value: 'left_turn_forward', label: 'Left Turn Forward' },
];

const modelOptions = [
    { value: 'kling-v2-6', label: 'Kling v2.6' },
    { value: 'kling-v2-5-turbo', label: 'Kling v2.5 Turbo' },
    { value: 'kling-v2-1-master', label: 'Kling v2.1 Master' },
    { value: 'kling-v2-master', label: 'Kling v2 Master' },
    { value: 'kling-v1-6', label: 'Kling v1.6' },
    { value: 'kling-v1', label: 'Kling v1' },
];

const modeOptions = [
    { value: 'pro', label: 'Pro' },
    { value: 'std', label: 'Standard' },
];

const durationOptions = [
    { value: '5', label: '5s' },
    { value: '10', label: '10s' },
];

export default function MiniVideoCard({ video, regenerating, onRegenerate, onUpload }: Props) {
    const defaultPrompt = video.prompt ?? '';
    const [promptText, setPromptText] = useState(defaultPrompt);
    const [duration, setDuration] = useState('5');
    const [modelName, setModelName] = useState('kling-v2-6');
    const [mode, setMode] = useState('std');
    const [cameraControl, setCameraControl] = useState('');

    useEffect(() => {
        setPromptText(video.prompt ?? '');
    }, [video.prompt]);

    const handleRegenerate = () => {
        if (onRegenerate) {
            onRegenerate(video.id, {
                prompt: promptText.trim(),
                duration,
                model_name: modelName,
                mode,
                camera_control: cameraControl,
            });
        }
    };

    const isProcessing = regenerating || video.status === 'processing';
    const status = statusLabels[video.status] || statusLabels.pending;
    const isStale = !!(video.metadata as Record<string, unknown> | null)?.stale;
    const frameFromSeq = video.frame_from?.sequence_number ?? '?';
    const frameToSeq = video.frame_to?.sequence_number ?? '?';

    const selectClass = 'rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

    return (
        <div className="rounded-lg border border-gray-200 overflow-hidden">
            <div className="flex flex-col md:flex-row">
                {/* Video / Preview */}
                <div className="md:w-1/2 shrink-0">
                    {isProcessing ? (
                        <div className="w-full h-80 md:h-96 bg-gray-100 flex items-center justify-center">
                            <div className="text-center">
                                <svg
                                    className="animate-spin h-8 w-8 text-indigo-500 mx-auto mb-2"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        className="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        strokeWidth="4"
                                    />
                                    <path
                                        className="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                                <span className="text-gray-400 text-sm">Generating clip...</span>
                            </div>
                        </div>
                    ) : video.status === 'completed' && video.video_url ? (
                        <div className="h-80 md:h-96">
                            <VideoPlayer url={video.video_url} />
                        </div>
                    ) : (
                        <div className="w-full h-80 md:h-96 bg-gray-50 flex items-center gap-2 justify-center p-4 overflow-hidden">
                            {video.frame_from?.image_url && (
                                <img
                                    src={video.frame_from.image_url}
                                    alt={`Frame ${frameFromSeq}`}
                                    className="max-h-72 max-w-[45%] rounded object-contain"
                                />
                            )}
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                className="h-6 w-6 text-gray-400 shrink-0"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M14 5l7 7m0 0l-7 7m7-7H3"
                                />
                            </svg>
                            {video.frame_to?.image_url && (
                                <img
                                    src={video.frame_to.image_url}
                                    alt={`Frame ${frameToSeq}`}
                                    className="max-h-72 max-w-[45%] rounded object-contain"
                                />
                            )}
                        </div>
                    )}
                </div>

                {/* Details */}
                <div className="md:w-1/2 p-4 flex flex-col">
                    <div className="flex items-center gap-2 mb-2">
                        <p className="text-xs font-semibold text-gray-700">
                            Clip {video.sequence_number}: Frame {frameFromSeq} → Frame{' '}
                            {frameToSeq}
                        </p>
                        <span
                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${status.className}`}
                        >
                            {status.label}
                        </span>
                        {isStale && (
                            <span className="inline-flex items-center rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-xs font-medium">
                                Outdated
                            </span>
                        )}
                    </div>

                    {video.duration_seconds != null && video.status === 'completed' && (
                        <p className="text-xs text-gray-400 mb-2">
                            Duration: {video.duration_seconds}s
                        </p>
                    )}

                    {/* Generation settings */}
                    {onRegenerate && (
                        <div className="grid grid-cols-2 gap-2 mb-3">
                            <div>
                                <label className="text-xs font-medium text-gray-400 mb-0.5 block">Model</label>
                                <select value={modelName} onChange={(e) => setModelName(e.target.value)} className={selectClass + ' w-full'}>
                                    {modelOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="text-xs font-medium text-gray-400 mb-0.5 block">Mode</label>
                                <select value={mode} onChange={(e) => setMode(e.target.value)} className={selectClass + ' w-full'}>
                                    {modeOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="text-xs font-medium text-gray-400 mb-0.5 block">Duration</label>
                                <select value={duration} onChange={(e) => setDuration(e.target.value)} className={selectClass + ' w-full'}>
                                    {durationOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="text-xs font-medium text-gray-400 mb-0.5 block">Camera</label>
                                <select value={cameraControl} onChange={(e) => setCameraControl(e.target.value)} className={selectClass + ' w-full'}>
                                    {cameraOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                                </select>
                            </div>
                        </div>
                    )}

                    {/* Prompt textarea */}
                    <div className="flex-1 flex flex-col">
                        <label className="text-xs font-medium text-gray-400 mb-1">Prompt</label>
                        <textarea
                            value={promptText}
                            onChange={(e) => setPromptText(e.target.value)}
                            rows={5}
                            readOnly={!onRegenerate}
                            className="w-full flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 resize-y read-only:bg-gray-50 read-only:text-gray-500"
                        />
                        <div className="mt-2 flex gap-2">
                            {onRegenerate && (
                                <button
                                    onClick={handleRegenerate}
                                    disabled={isProcessing}
                                    className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        className="h-3.5 w-3.5 mr-1"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fillRule="evenodd"
                                            d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"
                                            clipRule="evenodd"
                                        />
                                    </svg>
                                    {video.video_url ? 'Regenerate' : 'Generate'}
                                </button>
                            )}
                            {onUpload && (
                                <label className="inline-flex items-center rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200 cursor-pointer">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        className="h-3.5 w-3.5 mr-1"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fillRule="evenodd"
                                            d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"
                                            clipRule="evenodd"
                                        />
                                    </svg>
                                    Upload
                                    <input
                                        type="file"
                                        accept="video/mp4,video/quicktime,video/webm"
                                        className="hidden"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0];
                                            if (file) onUpload(video.id, file);
                                            e.target.value = '';
                                        }}
                                    />
                                </label>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
