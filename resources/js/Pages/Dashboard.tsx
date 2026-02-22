import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PageProps, Story } from '@/types';
import StoryCard from '@/Components/stories/StoryCard';

interface Props extends PageProps {
    recentStories: Story[];
    stats: {
        total: number;
        in_progress: number;
        completed: number;
    };
}

export default function Dashboard({ recentStories, stats }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Stats */}
                    <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <p className="text-sm font-medium text-gray-500">Total Stories</p>
                            <p className="mt-1 text-3xl font-bold text-gray-900">{stats.total}</p>
                        </div>
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <p className="text-sm font-medium text-gray-500">In Progress</p>
                            <p className="mt-1 text-3xl font-bold text-blue-600">{stats.in_progress}</p>
                        </div>
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <p className="text-sm font-medium text-gray-500">Completed</p>
                            <p className="mt-1 text-3xl font-bold text-green-600">{stats.completed}</p>
                        </div>
                    </div>

                    {/* Recent Stories */}
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-lg font-medium text-gray-900">Recent Stories</h3>
                        <Link
                            href={route('stories.create')}
                            className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            New Story
                        </Link>
                    </div>

                    {recentStories.length > 0 ? (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {recentStories.map((story) => (
                                <StoryCard key={story.id} story={story} />
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-lg bg-white p-12 text-center shadow-sm">
                            <p className="text-gray-500">No stories yet. Create your first one!</p>
                            <Link
                                href={route('stories.create')}
                                className="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                Create Story
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
