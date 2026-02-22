import { ChatMessage } from '@/types';
import { useEffect, useRef } from 'react';
import ChatBubble from './ChatBubble';
import TypingIndicator from './TypingIndicator';

interface Props {
    messages: ChatMessage[];
    isTyping?: boolean;
}

export default function ChatWindow({ messages, isTyping = false }: Props) {
    const bottomRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, isTyping]);

    return (
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {messages.length === 0 && (
                <div className="flex items-center justify-center h-full">
                    <p className="text-gray-400 text-sm">
                        Start the conversation to develop your story script.
                    </p>
                </div>
            )}
            {messages.map((message) => (
                <ChatBubble key={message.id} message={message} />
            ))}
            {isTyping && <TypingIndicator />}
            <div ref={bottomRef} />
        </div>
    );
}
