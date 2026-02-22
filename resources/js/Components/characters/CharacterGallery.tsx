import { Character } from '@/types';
import CharacterCard from './CharacterCard';
import { useState } from 'react';

interface CharacterGalleryProps {
    characters: Character[];
    reviewMode?: boolean;
    regeneratingIds?: Set<number>;
    onRegenerate?: (characterId: number, prompt: string) => void;
    onUploadImage?: (characterId: number, file: File) => void;
    onCreateCharacter?: (name: string, description: string) => void;
    onDeleteCharacter?: (characterId: number) => void;
}

export default function CharacterGallery({
    characters,
    reviewMode = false,
    regeneratingIds,
    onRegenerate,
    onUploadImage,
    onCreateCharacter,
    onDeleteCharacter,
}: CharacterGalleryProps) {
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [newName, setNewName] = useState('');
    const [newDescription, setNewDescription] = useState('');

    const handleCreate = () => {
        if (!newName.trim() || !newDescription.trim() || !onCreateCharacter) return;
        onCreateCharacter(newName.trim(), newDescription.trim());
        setNewName('');
        setNewDescription('');
        setShowCreateForm(false);
    };

    return (
        <div className="space-y-6">
            {characters.map((character) => (
                <CharacterCard
                    key={character.id}
                    character={character}
                    reviewMode={reviewMode}
                    isRegenerating={regeneratingIds?.has(character.id) ?? false}
                    onRegenerate={onRegenerate}
                    onUploadImage={onUploadImage}
                    onDelete={onDeleteCharacter}
                />
            ))}
            {reviewMode && onCreateCharacter && (
                <div className="rounded-lg border-2 border-dashed border-gray-300 overflow-hidden">
                    {showCreateForm ? (
                        <div className="p-4 space-y-2">
                            <input
                                type="text"
                                value={newName}
                                onChange={(e) => setNewName(e.target.value)}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Character name"
                            />
                            <textarea
                                value={newDescription}
                                onChange={(e) => setNewDescription(e.target.value)}
                                className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                rows={3}
                                placeholder="Describe the character's appearance..."
                            />
                            <div className="flex gap-2">
                                <button
                                    onClick={handleCreate}
                                    disabled={!newName.trim() || !newDescription.trim()}
                                    className="flex-1 inline-flex justify-center items-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Create
                                </button>
                                <button
                                    onClick={() => {
                                        setShowCreateForm(false);
                                        setNewName('');
                                        setNewDescription('');
                                    }}
                                    className="flex-1 inline-flex justify-center items-center rounded-md bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    ) : (
                        <button
                            onClick={() => setShowCreateForm(true)}
                            className="w-full h-full min-h-[12rem] flex flex-col items-center justify-center gap-2 text-gray-400 hover:text-gray-600 hover:border-gray-400 transition-colors"
                        >
                            <svg
                                className="h-10 w-10"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                strokeWidth={1.5}
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M12 4.5v15m7.5-7.5h-15"
                                />
                            </svg>
                            <span className="text-sm font-medium">Add Character</span>
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
