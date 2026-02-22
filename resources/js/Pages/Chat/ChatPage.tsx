import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChatMessage, PageProps, Story } from '@/types';
import ChatWindow from '@/Components/chat/ChatWindow';
import ChatInput from '@/Components/chat/ChatInput';
import axios from 'axios';
import { useState } from 'react';

interface Props extends PageProps {
    story: Story;
    messages: ChatMessage[];
}

export default function ChatPage({ story, messages: initialMessages }: Props) {
    const { flash } = usePage<PageProps>().props;
    const [messages, setMessages] = useState<ChatMessage[]>(initialMessages);
    const [isTyping, setIsTyping] = useState(false);
    const [isFinalizing, setIsFinalizing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleSend = async (content: string) => {
        setError(null);

        const tempUserMsg: ChatMessage = {
            id: Date.now(),
            story_id: story.id,
            role: 'user',
            content,
            token_count: null,
            created_at: new Date().toISOString(),
        };
        setMessages((prev) => [...prev, tempUserMsg]);
        setIsTyping(true);

        try {
            const { data } = await axios.post(route('stories.chat.store', story.id), {
                message: content,
            });

            setMessages((prev) => [...prev, data.message]);
        } catch (err: unknown) {
            let msg = 'Erro inesperado. Tente novamente.';

            if (axios.isAxiosError(err) && err.response) {
                msg = err.response.data?.error
                    || `Erro do servidor (${err.response.status}). Tente novamente.`;
            } else if (axios.isAxiosError(err) && !err.response) {
                msg = 'Falha na conexao. Verifique sua internet e tente novamente.';
            }

            setError(msg);
            setMessages((prev) => prev.filter((m) => m.id !== tempUserMsg.id));
        } finally {
            setIsTyping(false);
        }
    };

    const handleFinalize = () => {
        if (confirm('Finalize the script? This will end the chat and start the production pipeline.')) {
            setIsFinalizing(true);
            router.post(route('stories.chat.finalize', story.id));
        }
    };

    const displayError = error || flash?.error;
    const isApiKeyError = displayError?.toLowerCase().includes('settings') ||
                          displayError?.toLowerCase().includes('configurada');

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Chat: {story.title}
                    </h2>
                    <button
                        onClick={handleFinalize}
                        disabled={isFinalizing || messages.length < 2}
                        className="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                    >
                        {isFinalizing ? 'Finalizing...' : 'Finalize Script'}
                    </button>
                </div>
            }
        >
            <Head title={`Chat: ${story.title}`} />

            <div className="py-6">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    {displayError && (
                        <div className="mb-4 rounded-lg bg-red-50 border border-red-200 p-4">
                            <div className="flex items-start">
                                <div className="flex-shrink-0">
                                    <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clipRule="evenodd" />
                                    </svg>
                                </div>
                                <div className="ml-3 flex-1">
                                    <p className="text-sm text-red-700">{displayError}</p>
                                    {isApiKeyError && (
                                        <Link
                                            href={route('admin.settings')}
                                            className="mt-2 inline-flex items-center text-sm font-medium text-red-700 underline hover:text-red-800"
                                        >
                                            Ir para Settings
                                        </Link>
                                    )}
                                </div>
                                <button
                                    onClick={() => setError(null)}
                                    className="ml-3 flex-shrink-0 text-red-400 hover:text-red-600"
                                >
                                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    )}

                    {story.characters && story.characters.length > 0 && (
                        <div className="mb-4 bg-white shadow-sm sm:rounded-lg p-4">
                            <h4 className="text-sm font-medium text-gray-500 mb-3">Characters</h4>
                            <div className="flex gap-4 overflow-x-auto pb-1">
                                {story.characters.map((character) => (
                                    <div key={character.id} className="flex flex-col items-center gap-1.5 flex-shrink-0">
                                        {character.image_url ? (
                                            <img
                                                src={character.image_url}
                                                alt={character.name}
                                                className="w-14 h-14 rounded-full object-cover ring-2 ring-gray-200"
                                            />
                                        ) : (
                                            <div className="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center ring-2 ring-gray-200">
                                                <span className="text-gray-400 text-xs">?</span>
                                            </div>
                                        )}
                                        <span className="text-xs font-medium text-gray-700 max-w-[4.5rem] truncate">
                                            {character.name}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex flex-col h-[calc(100vh-200px)] bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <ChatWindow messages={messages} isTyping={isTyping} />
                        <div className="border-t p-4">
                            <ChatInput onSend={handleSend} disabled={isTyping} />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
