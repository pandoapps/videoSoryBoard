import { StoryStatus } from '@/types';

const statusConfig: Record<StoryStatus, { label: string; className: string }> = {
    pending: { label: 'Pending', className: 'bg-gray-100 text-gray-800' },
    scripting: { label: 'Writing Script', className: 'bg-blue-100 text-blue-800' },
    characters: { label: 'Generating Characters', className: 'bg-purple-100 text-purple-800' },
    character_review: { label: 'Reviewing Characters', className: 'bg-amber-100 text-amber-800' },
    storyboard: { label: 'Creating Storyboard', className: 'bg-indigo-100 text-indigo-800' },
    storyboard_review: { label: 'Reviewing Storyboard', className: 'bg-amber-100 text-amber-800' },
    producing: { label: 'Producing Video', className: 'bg-yellow-100 text-yellow-800' },
    completed: { label: 'Completed', className: 'bg-green-100 text-green-800' },
    failed: { label: 'Failed', className: 'bg-red-100 text-red-800' },
};

export default function StoryStatusBadge({ status }: { status: StoryStatus }) {
    const config = statusConfig[status];

    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${config.className}`}
        >
            {config.label}
        </span>
    );
}
