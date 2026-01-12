import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M12 2C12 2 8.5 7 8.5 11.5C8.5 13.5 9.16667 15.1667 10.5 16.5C9.16667 15.8333 8.16667 14.8333 7.5 13.5C7.5 17.5 9.5 21.5 12 23.5C14.5 21.5 16.5 17.5 16.5 13.5C15.8333 14.8333 14.8333 15.8333 13.5 16.5C14.8333 15.1667 15.5 13.5 15.5 11.5C15.5 7 12 2 12 2ZM12 8C12 8 10 11 10 13.5C10 14.5 10.3333 15.3333 11 16C10.3333 15.6667 9.83333 15.1667 9.5 14.5C9.5 16.5 10.5 18.5 12 20C13.5 18.5 14.5 16.5 14.5 14.5C14.1667 15.1667 13.6667 15.6667 13 16C13.6667 15.3333 14 14.5 14 13.5C14 11 12 8 12 8Z"
                fill="currentColor"
            />
        </svg>
    );
}
