import StageStatusBadge from './StageStatusBadge';

interface Props {
    number: number;
    title: string;
    status: string;
    children?: React.ReactNode;
}

export default function SectionHeader({ number, title, status, children }: Props) {
    return (
        <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3">
                <span
                    className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold ${
                        status === 'completed'
                            ? 'bg-green-100 text-green-700'
                            : status === 'in_progress' || status === 'review'
                              ? 'bg-indigo-100 text-indigo-700'
                              : status === 'failed'
                                ? 'bg-red-100 text-red-700'
                                : 'bg-gray-100 text-gray-400'
                    }`}
                >
                    {status === 'completed' ? (
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                    ) : (
                        number
                    )}
                </span>
                <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                <StageStatusBadge status={status} />
            </div>
            {children}
        </div>
    );
}
