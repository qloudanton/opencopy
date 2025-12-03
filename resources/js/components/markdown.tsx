import { cn } from '@/lib/utils';
import ReactMarkdown from 'react-markdown';
import rehypeRaw from 'rehype-raw';
import remarkGfm from 'remark-gfm';

interface MarkdownProps {
    content: string;
    className?: string;
}

/**
 * Clean up escaped markdown characters that may have been
 * added by TipTap's markdown serialization.
 */
function preprocessMarkdown(content: string): string {
    return (
        content
            // Fix escaped headings: \## -> ##
            .replace(/\\(#{1,6}\s)/g, '$1')
            // Fix escaped brackets: \[ -> [
            .replace(/\\\[/g, '[')
            .replace(/\\\]/g, ']')
    );
}

export default function Markdown({ content, className }: MarkdownProps) {
    const processedContent = preprocessMarkdown(content);
    return (
        <div
            className={cn(
                'prose max-w-none prose-neutral dark:prose-invert',
                className,
            )}
        >
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                rehypePlugins={[rehypeRaw]}
                components={{
                    h1: ({ children }) => (
                        <h1 className="mt-8 mb-4 text-3xl font-bold first:mt-0">
                            {children}
                        </h1>
                    ),
                    h2: ({ children }) => (
                        <h2 className="mt-8 mb-3 border-b pb-2 text-2xl font-semibold">
                            {children}
                        </h2>
                    ),
                    h3: ({ children }) => (
                        <h3 className="mt-6 mb-2 text-xl font-semibold">
                            {children}
                        </h3>
                    ),
                    h4: ({ children }) => (
                        <h4 className="mt-4 mb-2 text-lg font-medium">
                            {children}
                        </h4>
                    ),
                    p: ({ children }) => (
                        <p className="mb-4 leading-7">{children}</p>
                    ),
                    ul: ({ children }) => (
                        <ul className="mb-4 list-disc space-y-2 pl-6">
                            {children}
                        </ul>
                    ),
                    ol: ({ children }) => (
                        <ol className="mb-4 list-decimal space-y-2 pl-6">
                            {children}
                        </ol>
                    ),
                    li: ({ children }) => (
                        <li className="leading-7">{children}</li>
                    ),
                    blockquote: ({ children }) => (
                        <blockquote className="my-4 border-l-4 border-muted-foreground/30 pl-4 italic">
                            {children}
                        </blockquote>
                    ),
                    code: ({ className, children, ...props }) => {
                        const isInline = !className;
                        if (isInline) {
                            return (
                                <code
                                    className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm"
                                    {...props}
                                >
                                    {children}
                                </code>
                            );
                        }
                        return (
                            <code
                                className={cn(
                                    'block overflow-x-auto rounded-lg bg-muted p-4 font-mono text-sm',
                                    className,
                                )}
                                {...props}
                            >
                                {children}
                            </code>
                        );
                    },
                    pre: ({ children }) => (
                        <pre className="my-4 overflow-x-auto rounded-lg bg-muted">
                            {children}
                        </pre>
                    ),
                    a: ({ href, children }) => (
                        <a
                            href={href}
                            className="text-primary underline underline-offset-4 hover:text-primary/80"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {children}
                        </a>
                    ),
                    table: ({ children }) => (
                        <div className="my-4 overflow-x-auto">
                            <table className="w-full border-collapse border border-border">
                                {children}
                            </table>
                        </div>
                    ),
                    th: ({ children }) => (
                        <th className="border border-border bg-muted px-4 py-2 text-left font-semibold">
                            {children}
                        </th>
                    ),
                    td: ({ children }) => (
                        <td className="border border-border px-4 py-2">
                            {children}
                        </td>
                    ),
                    hr: () => <hr className="my-8 border-border" />,
                    img: ({ src, alt }) => (
                        <img
                            src={src}
                            alt={alt}
                            className="my-4 h-auto max-w-full rounded-lg"
                        />
                    ),
                    // Handle YouTube embed divs
                    div: ({ children, ...props }) => {
                        // Check if it's a YouTube embed container
                        if ('data-youtube-video' in props) {
                            return (
                                <div className="my-6 aspect-video overflow-hidden rounded-lg">
                                    {children}
                                </div>
                            );
                        }
                        return <div {...props}>{children}</div>;
                    },
                    iframe: ({ src, ...props }) => (
                        <iframe
                            src={src}
                            className="h-full w-full"
                            allowFullScreen
                            {...props}
                        />
                    ),
                }}
            >
                {processedContent}
            </ReactMarkdown>
        </div>
    );
}
