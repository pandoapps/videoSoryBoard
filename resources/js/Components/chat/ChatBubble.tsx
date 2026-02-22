import { ChatMessage } from '@/types';

export default function ChatBubble({ message }: { message: ChatMessage }) {
    const isUser = message.role === 'user';

    return (
        <div className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
            <div
                className={`max-w-[75%] rounded-2xl px-4 py-3 ${
                    isUser
                        ? 'bg-indigo-600 text-white'
                        : 'bg-gray-100 text-gray-900'
                }`}
            >
                <div className="text-sm whitespace-pre-wrap">{message.content}</div>
                <p
                    className={`text-xs mt-1 ${
                        isUser ? 'text-indigo-200' : 'text-gray-400'
                    }`}
                >
                    {new Date(message.created_at).toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit',
                    })}
                </p>
            </div>
        </div>
    );
}
