export default function VideoPlayer({ url }: { url: string }) {
    return (
        <div className="aspect-video w-full rounded-lg overflow-hidden bg-black">
            <video
                src={url}
                controls
                className="w-full h-full"
            >
                Your browser does not support the video tag.
            </video>
        </div>
    );
}
