const statusConfig: Record<string, { label: string; className: string }> = {
    pending: { label: 'Pending', className: 'bg-gray-100 text-gray-600' },
    in_progress: { label: 'In Progress', className: 'bg-blue-100 text-blue-700' },
    review: { label: 'Awaiting Review', className: 'bg-amber-100 text-amber-700' },
    completed: { label: 'Completed', className: 'bg-green-100 text-green-700' },
    failed: { label: 'Failed', className: 'bg-red-100 text-red-700' },
};

export default function StageStatusBadge({ status }: { status: string }) {
    const config = statusConfig[status] || statusConfig.pending;

    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${config.className}`}
        >
            {config.label}
        </span>
    );
}
