interface Props {
    icon: React.ReactNode;
    title: string;
    description: string;
    count?: number;
}

export default function SectionPlaceholder({ icon, title, description, count }: Props) {
    const items = count ?? 3;

    return (
        <div className="space-y-4">
            <div className="flex flex-col items-center justify-center py-8 text-gray-300">
                {icon}
                <p className="mt-3 text-sm font-medium text-gray-400">{title}</p>
                <p className="text-xs text-gray-300">{description}</p>
            </div>
            {/* Skeleton placeholders */}
            <div className="space-y-3">
                {Array.from({ length: items }).map((_, i) => (
                    <div
                        key={i}
                        className="rounded-lg border border-dashed border-gray-200 overflow-hidden"
                    >
                        <div className="flex flex-col md:flex-row">
                            <div className="md:w-1/3 shrink-0">
                                <div className="w-full h-32 bg-gray-50 animate-pulse" />
                            </div>
                            <div className="md:w-2/3 p-4 space-y-2">
                                <div className="h-3 w-24 bg-gray-100 rounded animate-pulse" />
                                <div className="h-2 w-full bg-gray-50 rounded animate-pulse" />
                                <div className="h-2 w-3/4 bg-gray-50 rounded animate-pulse" />
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
