import { StoryboardFrame } from '@/types';
import FrameCard from './FrameCard';
import { useMemo } from 'react';

interface Props {
    frames: StoryboardFrame[];
    storyId?: number;
    regeneratingIds?: Set<number>;
    onRegenerate?: (frameId: number, prompt: string) => void;
    onUploadImage?: (frameId: number, file: File) => void;
    onDelete?: (frameId: number) => void;
}

interface SceneGroup {
    scene: number | string;
    frames: StoryboardFrame[];
}

export default function StoryboardGrid({ frames, storyId, regeneratingIds, onRegenerate, onUploadImage, onDelete }: Props) {
    const scenes = useMemo(() => {
        const groups: SceneGroup[] = [];
        let currentScene: number | string | null = null;
        let currentFrames: StoryboardFrame[] = [];

        for (const frame of frames) {
            const scene = (frame.metadata?.scene as number | undefined) ?? 'ungrouped';

            if (scene !== currentScene) {
                if (currentFrames.length > 0) {
                    groups.push({ scene: currentScene!, frames: currentFrames });
                }
                currentScene = scene;
                currentFrames = [frame];
            } else {
                currentFrames.push(frame);
            }
        }

        if (currentFrames.length > 0 && currentScene !== null) {
            groups.push({ scene: currentScene, frames: currentFrames });
        }

        return groups;
    }, [frames]);

    // If no scene metadata, render flat list
    if (scenes.length <= 1 && scenes[0]?.scene === 'ungrouped') {
        return (
            <div className="space-y-6">
                {frames.map((frame, index) => (
                    <FrameCard
                        key={frame.id}
                        frame={frame}
                        frames={frames}
                        index={index}
                        regenerating={regeneratingIds?.has(frame.id) ?? false}
                        onRegenerate={onRegenerate}
                        onUploadImage={onUploadImage}
                        onDelete={onDelete}
                    />
                ))}
            </div>
        );
    }

    // Build a flat list of all frames for modal navigation
    const allFrames = frames;

    return (
        <div className="space-y-8">
            {scenes.map((group) => (
                <div key={group.scene}>
                    <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3 border-b border-gray-200 pb-2">
                        Scene {group.scene}
                        <span className="ml-2 text-xs font-normal text-gray-400 normal-case">
                            {group.frames.length} {group.frames.length === 1 ? 'frame' : 'frames'}
                        </span>
                    </h4>
                    <div className="space-y-4">
                        {group.frames.map((frame) => {
                            const globalIndex = allFrames.findIndex((f) => f.id === frame.id);
                            return (
                                <FrameCard
                                    key={frame.id}
                                    frame={frame}
                                    frames={allFrames}
                                    index={globalIndex}
                                    regenerating={regeneratingIds?.has(frame.id) ?? false}
                                    onRegenerate={onRegenerate}
                                    onUploadImage={onUploadImage}
                                    onDelete={onDelete}
                                />
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}
