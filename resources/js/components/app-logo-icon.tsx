import type { SVGAttributes } from 'react';

// recibo com três linhas: o mesmo desenho do public/favicon.svg
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M6 2h12a2 2 0 0 1 2 2v18l-3-2-2.5 2-2.5-2-2.5 2L7 20l-3 2V4a2 2 0 0 1 2-2Zm2 5v2h8V7H8Zm0 4v2h8v-2H8Zm0 4v2h5v-2H8Z"
            />
        </svg>
    );
}
