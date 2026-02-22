import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PageProps, Story } from '@/types';
import StoryCard from '@/Components/stories/StoryCard';

interface PaginatedStories {
    data: Story[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props extends PageProps {
    stories: PaginatedStories;
}

export default function Index({ stories }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Stories
                    </h2>
                    <Link
                        href={route('stories.create')}
                        className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        New Story
                    </Link>
                </div>
            }
        >
            <Head title="Stories" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {stories.data.length > 0 ? (
                        <>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {stories.data.map((story) => (
                                    <StoryCard key={story.id} story={story} />
                                ))}
                            </div>

                            {/* Pagination */}
                            {stories.last_page > 1 && (
                                <div className="mt-8 flex justify-center gap-2">
                                    {stories.links.map((link, index) => (
                                        <Link
                                            key={index}
                                            href={link.url || '#'}
                                            className={`rounded-md px-3 py-2 text-sm ${
                                                link.active
                                                    ? 'bg-indigo-600 text-white'
                                                    : link.url
                                                      ? 'bg-white text-gray-700 hover:bg-gray-50'
                                                      : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </>
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
