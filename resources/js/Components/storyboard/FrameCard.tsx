import { StoryboardFrame } from '@/types';
import { useCallback, useEffect, useRef, useState } from 'react';

interface Props {
    frame: StoryboardFrame;
    frames: StoryboardFrame[];
    index: number;
    regenerating: boolean;
    onRegenerate?: (frameId: number, prompt: string) => void;
    onUploadImage?: (frameId: number, file: File) => void;
    onDelete?: (frameId: number) => void;
}

function ImageModal({
    frames,
    initialIndex,
    onClose,
}: {
    frames: StoryboardFrame[];
    initialIndex: number;
    onClose: () => void;
}) {
    const [currentIndex, setCurrentIndex] = useState(initialIndex);
    const frame = frames[currentIndex];

    const goNext = useCallback(() => {
        setCurrentIndex((prev) => {
            for (let i = prev + 1; i < frames.length; i++) {
                if (frames[i].image_url) return i;
            }
            return prev;
        });
    }, [frames]);

    const goPrev = useCallback(() => {
        setCurrentIndex((prev) => {
            for (let i = prev - 1; i >= 0; i--) {
                if (frames[i].image_url) return i;
            }
            return prev;
        });
    }, [frames]);

    useEffect(() => {
        const handleKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
            if (e.key === 'ArrowLeft') goPrev();
            if (e.key === 'ArrowRight') goNext();
        };
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [onClose, goNext, goPrev]);

    if (!frame?.image_url) return null;

    const hasPrev = frames.slice(0, currentIndex).some((f) => f.image_url);
    const hasNext = frames.slice(currentIndex + 1).some((f) => f.image_url);

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            onClick={onClose}
        >
            <div
                className="relative flex max-h-[90vh] max-w-4xl items-center gap-4"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Previous button */}
                <button
                    onClick={goPrev}
                    disabled={!hasPrev}
                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow-lg hover:bg-white disabled:opacity-30 disabled:cursor-not-allowed"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clipRule="evenodd" />
                    </svg>
                </button>

                <div className="relative">
                    {/* Close button */}
                    <button
                        onClick={onClose}
                        className="absolute -top-3 -right-3 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-600 shadow-lg hover:text-gray-900"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                        </svg>
                    </button>
                    <img
                        src={frame.image_url}
                        alt={`Scene ${frame.sequence_number}`}
                        className="max-h-[80vh] w-auto rounded-lg shadow-2xl"
                    />
                    <p className="mt-2 text-center text-sm font-medium text-white">
                        Frame {currentIndex + 1} of {frames.length}
                    </p>
                </div>

                {/* Next button */}
                <button
                    onClick={goNext}
                    disabled={!hasNext}
                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow-lg hover:bg-white disabled:opacity-30 disabled:cursor-not-allowed"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
    );
}

export default function FrameCard({ frame, frames, index, regenerating, onRegenerate, onUploadImage, onDelete }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const defaultPrompt = frame.prompt ?? `Comic book panel, sequential art style, no text or speech bubbles: ${frame.scene_description}. Cinematic composition, vivid colors, detailed illustration.`;
    const [promptText, setPromptText] = useState(defaultPrompt);

    useEffect(() => {
        setPromptText(frame.prompt ?? `Comic book panel, sequential art style, no text or speech bubbles: ${frame.scene_description}. Cinematic composition, vivid colors, detailed illustration.`);
    }, [frame.prompt, frame.scene_description]);

    const handleRegenerate = () => {
        if (onRegenerate && promptText.trim()) {
            onRegenerate(frame.id, promptText.trim());
        }
    };

    const isRegenerating = regenerating || !!frame.metadata?.regenerating;
    const error = frame.metadata?.error as string | undefined;

    return (
        <>
            <div className={`rounded-lg border overflow-hidden ${error ? 'border-red-300' : 'border-gray-200'}`}>
                {error && !isRegenerating && (
                    <div className="bg-red-50 border-b border-red-200 px-4 py-2">
                        <p className="text-xs text-red-700">{error}</p>
                    </div>
                )}
                <div className="flex flex-col md:flex-row">
                    {/* Image */}
                    <div className="md:w-1/2 shrink-0">
                        {isRegenerating ? (
                            <div className="w-full h-64 md:h-80 bg-gray-100 flex items-center justify-center">
                                <div className="text-center">
                                    <svg className="animate-spin h-8 w-8 text-indigo-500 mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <span className="text-gray-400 text-sm">Generating...</span>
                                </div>
                            </div>
                        ) : frame.image_url ? (
                            <img
                                src={frame.image_url}
                                alt={`Scene ${frame.sequence_number}`}
                                className="w-full h-64 md:h-80 object-cover cursor-pointer"
                                onClick={() => setModalOpen(true)}
                            />
                        ) : (
                            <div className="w-full h-64 md:h-80 bg-gray-100 flex items-center justify-center">
                                <span className="text-gray-400 text-sm">No image</span>
                            </div>
                        )}
                    </div>

                    {/* Details */}
                    <div className="md:w-1/2 p-4 flex flex-col">
                        <p className="text-xs font-medium text-gray-500 mb-2">
                            Frame {frame.sequence_number}
                            {frame.metadata?.second != null && (
                                <span className="ml-1 text-gray-400">
                                    @ {String(frame.metadata.second)}s
                                </span>
                            )}
                            {Array.isArray(frame.metadata?.characters) && (frame.metadata.characters as string[]).length > 0 && (
                                <span className="ml-2 text-gray-400">
                                    | {(frame.metadata.characters as string[]).join(', ')}
                                </span>
                            )}
                        </p>

                        {/* Prompt — always an editable textarea */}
                        <div className="flex-1 flex flex-col">
                            <label className="text-xs font-medium text-gray-400 mb-1">Prompt</label>
                            <textarea
                                value={promptText}
                                onChange={(e) => setPromptText(e.target.value)}
                                rows={6}
                                readOnly={!onRegenerate}
                                className="w-full flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 resize-y read-only:bg-gray-50 read-only:text-gray-500"
                            />
                            {!isRegenerating && (onRegenerate || onUploadImage) && (
                                <div className="mt-2 flex items-center gap-2">
                                    {onRegenerate && (
                                        <button
                                            onClick={handleRegenerate}
                                            disabled={!promptText.trim()}
                                            className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clipRule="evenodd" />
                                            </svg>
                                            {frame.image_url ? 'Regenerate' : 'Generate'}
                                        </button>
                                    )}
                                    {onUploadImage && (
                                        <>
                                            <input
                                                ref={fileInputRef}
                                                type="file"
                                                accept="image/*"
                                                className="hidden"
                                                onChange={(e) => {
                                                    const file = e.target.files?.[0];
                                                    if (file) onUploadImage(frame.id, file);
                                                    e.target.value = '';
                                                }}
                                            />
                                            <button
                                                onClick={() => fileInputRef.current?.click()}
                                                className="inline-flex items-center rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fillRule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clipRule="evenodd" />
                                                </svg>
                                                Upload
                                            </button>
                                        </>
                                    )}
                                    {onDelete && (
                                        <button
                                            onClick={() => {
                                                if (window.confirm('Delete this frame?')) {
                                                    onDelete(frame.id);
                                                }
                                            }}
                                            className="inline-flex items-center rounded-md bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clipRule="evenodd" />
                                            </svg>
                                            Delete
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {modalOpen && frame.image_url && (
                <ImageModal
                    frames={frames}
                    initialIndex={index}
                    onClose={() => setModalOpen(false)}
                />
            )}
        </>
    );
}
