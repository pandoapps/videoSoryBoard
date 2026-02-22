import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ApiUsageSummary, PageProps } from '@/types';

interface StoryUsage extends ApiUsageSummary {
    story_id: number;
    story_title: string;
}

interface Props extends PageProps {
    stories: StoryUsage[];
    totals: ApiUsageSummary;
}

function formatCost(cents: number): string {
    return `$${(cents / 100).toFixed(4)}`;
}

export default function Index({ stories, totals }: Props) {
    const handleDownloadReport = () => {
        window.print();
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        API Costs
                    </h2>
                    <button
                        onClick={handleDownloadReport}
                        className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 print:hidden"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            className="h-4 w-4 mr-2"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fillRule="evenodd"
                                d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"
                                clipRule="evenodd"
                            />
                        </svg>
                        Download Report
                    </button>
                </div>
            }
        >
            <Head title="API Costs" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    {/* Summary Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-sm font-medium text-gray-500">Total Estimated Cost</h3>
                            <p className="text-3xl font-bold text-gray-900 mt-1">
                                {formatCost(totals.total_cost_cents)}
                            </p>
                        </div>
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-sm font-medium text-gray-500">Anthropic Calls</h3>
                            <p className="text-3xl font-bold text-gray-900 mt-1">
                                {totals.anthropic.call_count}
                            </p>
                        </div>
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-sm font-medium text-gray-500">Nano Banana</h3>
                            <p className="text-3xl font-bold text-gray-900 mt-1">
                                {formatCost(totals.nano_banana.cost_cents)}
                            </p>
                            <p className="text-xs text-gray-400 mt-1">
                                {totals.nano_banana.call_count} calls
                            </p>
                        </div>
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-sm font-medium text-gray-500">Kling</h3>
                            <p className="text-3xl font-bold text-gray-900 mt-1">
                                {formatCost(totals.kling.cost_cents)}
                            </p>
                            <p className="text-xs text-gray-400 mt-1">
                                {totals.kling.call_count} calls
                            </p>
                        </div>
                    </div>

                    {/* Per-Story Table */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Cost by Story</h3>
                            {stories.length === 0 ? (
                                <p className="text-gray-500">No API usage recorded yet.</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr>
                                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Story
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Anthropic Cost
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    NanoBanana Cost
                                                </th>
                                                <th className="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Kling Cost
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200">
                                            {stories.map((story) => (
                                                <tr key={story.story_id} className="hover:bg-gray-50">
                                                    <td className="px-4 py-3 text-sm">
                                                        <Link
                                                            href={route('stories.show', story.story_id)}
                                                            className="text-indigo-600 hover:text-indigo-900"
                                                        >
                                                            {story.story_title}
                                                        </Link>
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-right text-gray-900">
                                                        {formatCost(story.anthropic.cost_cents)}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-right text-gray-900">
                                                        {formatCost(story.nano_banana.cost_cents)}
                                                        <span className="text-gray-400 ml-1">({story.nano_banana.call_count})</span>
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-right text-gray-900">
                                                        {formatCost(story.kling.cost_cents)}
                                                        <span className="text-gray-400 ml-1">({story.kling.call_count})</span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot>
                                            <tr className="bg-gray-50 font-semibold">
                                                <td className="px-4 py-3 text-sm text-gray-900">
                                                    Total
                                                </td>
                                                <td className="px-4 py-3 text-sm text-right text-gray-900">
                                                    {formatCost(totals.total_cost_cents)}
                                                </td>
                                                <td className="px-4 py-3 text-sm text-right text-gray-900">
                                                    {formatCost(totals.nano_banana.cost_cents)}
                                                    <span className="text-gray-400 ml-1">({totals.nano_banana.call_count})</span>
                                                </td>
                                                <td className="px-4 py-3 text-sm text-right text-gray-900">
                                                    {formatCost(totals.kling.cost_cents)}
                                                    <span className="text-gray-400 ml-1">({totals.kling.call_count})</span>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
