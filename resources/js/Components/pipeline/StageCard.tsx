import StageStatusBadge from './StageStatusBadge';

interface StageInfo {
    stage: string;
    status: string;
}

const stageLabels: Record<string, string> = {
    script: 'Script Writing',
    characters: 'Character Generation',
    storyboard: 'Storyboard Creation',
    video: 'Video Production',
};

const stageDescriptions: Record<string, string> = {
    script: 'Collaborate with AI to develop your story script',
    characters: 'Generate visual character designs from your script',
    storyboard: 'Create scene-by-scene visual storyboard',
    video: 'Produce the final video from storyboard frames',
};

const redoLabels: Record<string, string> = {
    script: 'Edit Script',
    characters: 'Redo Characters',
    storyboard: 'Redo Storyboard',
};

interface StageCardProps {
    stage: StageInfo;
    canRevert?: boolean;
    onRevert?: (stage: string) => void;
}

export default function StageCard({ stage, canRevert, onRevert }: StageCardProps) {
    const showRedo =
        canRevert &&
        onRevert &&
        stage.status === 'completed' &&
        stage.stage !== 'video' &&
        redoLabels[stage.stage];

    return (
        <div
            className={`rounded-lg border p-4 ${
                stage.status === 'in_progress'
                    ? 'border-blue-300 bg-blue-50'
                    : stage.status === 'completed'
                      ? 'border-green-300 bg-green-50'
                      : stage.status === 'failed'
                        ? 'border-red-300 bg-red-50'
                        : 'border-gray-200 bg-white'
            }`}
        >
            <div className="flex items-center justify-between mb-2">
                <h4 className="font-medium text-gray-900">
                    {stageLabels[stage.stage] || stage.stage}
                </h4>
                <div className="flex items-center gap-2">
                    {showRedo && (
                        <button
                            onClick={() => onRevert(stage.stage)}
                            className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-amber-700 bg-amber-100 hover:bg-amber-200 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" className="w-3 h-3">
                                <path fillRule="evenodd" d="M12.5 9.75A2.75 2.75 0 0 0 9.75 7H4.56l2.22 2.22a.75.75 0 1 1-1.06 1.06l-3.5-3.5a.75.75 0 0 1 0-1.06l3.5-3.5a.75.75 0 0 1 1.06 1.06L4.56 5.5h5.19a4.25 4.25 0 0 1 0 8.5h-1a.75.75 0 0 1 0-1.5h1a2.75 2.75 0 0 0 2.75-2.75Z" clipRule="evenodd" />
                            </svg>
                            {redoLabels[stage.stage]}
                        </button>
                    )}
                    <StageStatusBadge status={stage.status} />
                </div>
            </div>
            <p className="text-sm text-gray-500">
                {stageDescriptions[stage.stage] || ''}
            </p>
            {stage.status === 'in_progress' && (
                <div className="mt-3">
                    <div className="h-1.5 w-full bg-blue-200 rounded-full overflow-hidden">
                        <div className="h-full bg-blue-500 rounded-full animate-pulse w-2/3"></div>
                    </div>
                </div>
            )}
        </div>
    );
}
