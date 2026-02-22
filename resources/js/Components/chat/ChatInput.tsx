import { FormEventHandler, KeyboardEventHandler, useState } from 'react';

interface Props {
    onSend: (message: string) => void;
    disabled?: boolean;
}

export default function ChatInput({ onSend, disabled = false }: Props) {
    const [message, setMessage] = useState('');

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        if (message.trim() && !disabled) {
            onSend(message.trim());
            setMessage('');
        }
    };

    const handleKeyDown: KeyboardEventHandler<HTMLTextAreaElement> = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (message.trim() && !disabled) {
                onSend(message.trim());
                setMessage('');
            }
        }
    };

    return (
        <form onSubmit={handleSubmit} className="flex gap-2">
            <textarea
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder="Type your message... (Enter to send, Shift+Enter for new line)"
                className="flex-1 resize-none rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                rows={2}
                disabled={disabled}
            />
            <button
                type="submit"
                disabled={disabled || !message.trim()}
                className="self-end rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            >
                Send
            </button>
        </form>
    );
}
