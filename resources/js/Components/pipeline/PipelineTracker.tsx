import StageCard from './StageCard';

interface StageInfo {
    stage: string;
    status: string;
}

interface PipelineTrackerProps {
    stages: StageInfo[];
    canRevert?: boolean;
    onRevert?: (stage: string) => void;
}

export default function PipelineTracker({ stages, canRevert, onRevert }: PipelineTrackerProps) {
    return (
        <div className="space-y-3">
            {stages.map((stage, index) => (
                <div key={stage.stage} className="relative">
                    {index > 0 && (
                        <div className="absolute -top-3 left-6 w-0.5 h-3 bg-gray-300"></div>
                    )}
                    <StageCard
                        stage={stage}
                        canRevert={canRevert}
                        onRevert={onRevert}
                    />
                </div>
            ))}
        </div>
    );
}
