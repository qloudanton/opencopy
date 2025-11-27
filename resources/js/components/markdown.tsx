import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { cn } from '@/lib/utils';

interface MarkdownProps {
    content: string;
    className?: string;
}

export default function Markdown({ content, className }: MarkdownProps) {
    return (
        <div className={cn('prose prose-neutral dark:prose-invert max-w-none', className)}>
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                components={{
                    h1: ({ children }) => (
                        <h1 className="text-3xl font-bold mt-8 mb-4 first:mt-0">{children}</h1>
                    ),
                    h2: ({ children }) => (
                        <h2 className="text-2xl font-semibold mt-8 mb-3 border-b pb-2">{children}</h2>
                    ),
                    h3: ({ children }) => (
                        <h3 className="text-xl font-semibold mt-6 mb-2">{children}</h3>
                    ),
                    h4: ({ children }) => (
                        <h4 className="text-lg font-medium mt-4 mb-2">{children}</h4>
                    ),
                    p: ({ children }) => (
                        <p className="mb-4 leading-7">{children}</p>
                    ),
                    ul: ({ children }) => (
                        <ul className="list-disc pl-6 mb-4 space-y-2">{children}</ul>
                    ),
                    ol: ({ children }) => (
                        <ol className="list-decimal pl-6 mb-4 space-y-2">{children}</ol>
                    ),
                    li: ({ children }) => (
                        <li className="leading-7">{children}</li>
                    ),
                    blockquote: ({ children }) => (
                        <blockquote className="border-l-4 border-muted-foreground/30 pl-4 italic my-4">
                            {children}
                        </blockquote>
                    ),
                    code: ({ className, children, ...props }) => {
                        const isInline = !className;
                        if (isInline) {
                            return (
                                <code className="bg-muted px-1.5 py-0.5 rounded text-sm font-mono" {...props}>
                                    {children}
                                </code>
                            );
                        }
                        return (
                            <code className={cn('block bg-muted p-4 rounded-lg overflow-x-auto text-sm font-mono', className)} {...props}>
                                {children}
                            </code>
                        );
                    },
                    pre: ({ children }) => (
                        <pre className="bg-muted rounded-lg overflow-x-auto my-4">{children}</pre>
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
                        <div className="overflow-x-auto my-4">
                            <table className="w-full border-collapse border border-border">{children}</table>
                        </div>
                    ),
                    th: ({ children }) => (
                        <th className="border border-border bg-muted px-4 py-2 text-left font-semibold">{children}</th>
                    ),
                    td: ({ children }) => (
                        <td className="border border-border px-4 py-2">{children}</td>
                    ),
                    hr: () => <hr className="my-8 border-border" />,
                    img: ({ src, alt }) => (
                        <img src={src} alt={alt} className="rounded-lg max-w-full h-auto my-4" />
                    ),
                }}
            >
                {content}
            </ReactMarkdown>
        </div>
    );
}
