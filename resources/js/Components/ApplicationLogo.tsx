import { ImgHTMLAttributes } from 'react';

export default function ApplicationLogo(props: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            {...props}
            src="/apple-touch-icon.png"
            alt="VideoGenerator"
        />
    );
}
