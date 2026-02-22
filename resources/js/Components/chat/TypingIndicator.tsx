export default function TypingIndicator() {
    return (
        <div className="flex justify-start">
            <div className="bg-gray-100 rounded-2xl px-4 py-3">
                <div className="flex space-x-1">
                    <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
                    <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
                    <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                </div>
            </div>
        </div>
    );
}
