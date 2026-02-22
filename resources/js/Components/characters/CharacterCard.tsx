import { Character } from '@/types';
import { useEffect, useRef, useState } from 'react';

interface CharacterCardProps {
    character: Character;
    reviewMode?: boolean;
    isRegenerating?: boolean;
    onRegenerate?: (characterId: number, prompt: string) => void;
    onUploadImage?: (characterId: number, file: File) => void;
    onDelete?: (characterId: number) => void;
}

export default function CharacterCard({
    character,
    reviewMode = false,
    isRegenerating = false,
    onRegenerate,
    onUploadImage,
    onDelete,
}: CharacterCardProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const defaultPrompt = `Professional character design, full body portrait: ${character.name}. ${character.description ?? ''}. High quality, detailed, consistent art style, suitable for animation.`;
    const [promptText, setPromptText] = useState(defaultPrompt);
    const [modalOpen, setModalOpen] = useState(false);

    useEffect(() => {
        setPromptText(`Professional character design, full body portrait: ${character.name}. ${character.description ?? ''}. High quality, detailed, consistent art style, suitable for animation.`);
    }, [character.name, character.description]);

    useEffect(() => {
        if (!modalOpen) return;
        const handleKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setModalOpen(false);
        };
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [modalOpen]);

    const handleRegenerate = () => {
        if (!promptText.trim() || !onRegenerate) return;
        onRegenerate(character.id, promptText.trim());
    };

    const handleDelete = () => {
        if (!onDelete) return;
        if (window.confirm(`Delete character "${character.name}"? This cannot be undone.`)) {
            onDelete(character.id);
        }
    };

    const error = character.metadata?.error as string | undefined;

    return (
        <>
            <div className={`rounded-lg border overflow-hidden ${error && !isRegenerating ? 'border-red-300' : 'border-gray-200'}`}>
                {error && !isRegenerating && (
                    <div className="bg-red-50 border-b border-red-200 px-4 py-2">
                        <p className="text-xs text-red-700">{error}</p>
                    </div>
                )}
                <div className="flex flex-col md:flex-row">
                    {/* Image */}
                    <div className="md:w-1/3 shrink-0 relative bg-gray-50">
                        {character.image_url ? (
                            <img
                                src={character.image_url}
                                alt={character.name}
                                className="w-full h-auto max-h-[28rem] object-contain cursor-pointer"
                                onClick={() => setModalOpen(true)}
                            />
                        ) : (
                            <div className="w-full h-64 bg-gradient-to-br from-indigo-100 to-purple-100 flex flex-col items-center justify-center gap-2">
                                <span className="text-4xl font-bold text-indigo-300">
                                    {character.name
                                        .split(/\s+/)
                                        .slice(0, 2)
                                        .map((w) => w[0]?.toUpperCase())
                                        .join('')}
                                </span>
                                <span className="text-xs text-indigo-400">No image yet</span>
                            </div>
                        )}
                        {isRegenerating && (
                            <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                                <div className="flex flex-col items-center gap-2">
                                    <svg
                                        className="h-8 w-8 animate-spin text-white"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                    </svg>
                                    <span className="text-white text-sm font-medium">Regenerating...</span>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Details */}
                    <div className="md:w-2/3 p-4 flex flex-col">
                        <div className="flex items-center justify-between mb-2">
                            <h4 className="font-medium text-gray-900">{character.name}</h4>
                            {reviewMode && !isRegenerating && onDelete && (
                                <button
                                    onClick={handleDelete}
                                    className="inline-flex items-center rounded-md bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100"
                                >
                                    Delete
                                </button>
                            )}
                        </div>

                        {character.description && !onRegenerate && (
                            <p className="text-sm text-gray-600 mb-2">{character.description}</p>
                        )}

                        {/* Prompt — only shown when regeneration is available */}
                        {onRegenerate && (
                            <div className="flex-1 flex flex-col">
                                <label className="text-xs font-medium text-gray-400 mb-1">Prompt</label>
                                <textarea
                                    value={promptText}
                                    onChange={(e) => setPromptText(e.target.value)}
                                    rows={5}
                                    className="w-full flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 resize-y"
                                />
                                {!isRegenerating && (
                                    <div className="mt-2 flex items-center gap-2">
                                        <button
                                            onClick={handleRegenerate}
                                            disabled={!promptText.trim()}
                                            className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clipRule="evenodd" />
                                            </svg>
                                            {character.image_url ? 'Regenerate' : 'Generate'}
                                        </button>
                                        {onUploadImage && (
                                            <>
                                                <input
                                                    ref={fileInputRef}
                                                    type="file"
                                                    accept="image/*"
                                                    className="hidden"
                                                    onChange={(e) => {
                                                        const file = e.target.files?.[0];
                                                        if (file) onUploadImage(character.id, file);
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
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Image Modal */}
            {modalOpen && character.image_url && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                    onClick={() => setModalOpen(false)}
                >
                    <div
                        className="relative max-h-[90vh] max-w-3xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <button
                            onClick={() => setModalOpen(false)}
                            className="absolute -top-3 -right-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-600 shadow-lg hover:text-gray-900"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                            </svg>
                        </button>
                        <img
                            src={character.image_url}
                            alt={character.name}
                            className="max-h-[85vh] w-auto rounded-lg shadow-2xl"
                        />
                        <p className="mt-2 text-center text-sm font-medium text-white">
                            {character.name}
                        </p>
                    </div>
                </div>
            )}
        </>
    );
}
