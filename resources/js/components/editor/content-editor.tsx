import { cn } from '@/lib/utils';
import Placeholder from '@tiptap/extension-placeholder';
import { Table } from '@tiptap/extension-table';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { TableRow } from '@tiptap/extension-table-row';
import Typography from '@tiptap/extension-typography';
import Youtube from '@tiptap/extension-youtube';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useCallback, useEffect, useState } from 'react';
import { Markdown } from 'tiptap-markdown';
import { EditorToolbar } from './editor-toolbar';
import { ImageBubbleMenu } from './image-bubble-menu';
import { CustomImage } from './image-extension';

interface YouTubeVideo {
    id: string;
    title: string;
    description: string;
    thumbnail: string;
    channelTitle: string;
    url: string;
}

interface ContentEditorProps {
    content: string;
    onChange: (markdown: string) => void;
    onRegenerateImage?: (options: {
        src: string;
        alt: string;
        style: string;
        prompt: string;
    }) => Promise<string | null>;
    onGenerateImage?: (options: {
        style: string;
        prompt: string;
    }) => Promise<string | null>;
    onSearchYouTube?: (query: string) => Promise<YouTubeVideo[]>;
    placeholder?: string;
    className?: string;
    editable?: boolean;
}

export function ContentEditor({
    content,
    onChange,
    onRegenerateImage,
    onGenerateImage,
    onSearchYouTube,
    placeholder = 'Start writing your content...',
    className,
    editable = true,
}: ContentEditorProps) {
    const [isRegenerating, setIsRegenerating] = useState(false);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: {
                    levels: [1, 2, 3, 4],
                },
            }),
            CustomImage.configure({
                inline: false,
                allowBase64: true,
            }),
            Placeholder.configure({
                placeholder,
            }),
            Typography,
            Youtube.configure({
                controls: true,
                nocookie: true,
                modestBranding: true,
            }),
            Table.configure({
                resizable: true,
                HTMLAttributes: {
                    class: 'border-collapse table-auto w-full',
                },
            }),
            TableRow,
            TableHeader.configure({
                HTMLAttributes: {
                    class: 'border border-border bg-muted px-4 py-2 text-left font-semibold',
                },
            }),
            TableCell.configure({
                HTMLAttributes: {
                    class: 'border border-border px-4 py-2',
                },
            }),
            Markdown.configure({
                html: true,
                transformPastedText: true,
                transformCopiedText: true,
                linkify: false,
            }),
        ],
        content,
        editable,
        editorProps: {
            attributes: {
                class: cn('tiptap', 'min-h-[400px] p-4 focus:outline-none'),
            },
        },
        onUpdate: ({ editor }) => {
            const markdown = editor.storage.markdown.getMarkdown();
            onChange(markdown);
        },
    });

    // Update content when prop changes (e.g., from external source like enrichment)
    useEffect(() => {
        if (editor) {
            const currentMarkdown =
                editor.storage.markdown?.getMarkdown() || '';
            // Only update if content actually changed (avoiding infinite loops)
            if (content !== currentMarkdown) {
                // Use setContent with emitUpdate: false to avoid triggering onChange
                // The tiptap-markdown extension will parse the markdown automatically
                editor.commands.setContent(content, {
                    emitUpdate: false,
                });
            }
        }
    }, [content, editor]);

    const handleRegenerateImage = useCallback(
        async (options: {
            src: string;
            alt: string;
            style: string;
            prompt: string;
        }) => {
            if (!onRegenerateImage) return null;

            setIsRegenerating(true);
            try {
                const newSrc = await onRegenerateImage(options);
                return newSrc;
            } finally {
                setIsRegenerating(false);
            }
        },
        [onRegenerateImage],
    );

    if (!editor) {
        return (
            <div className="min-h-[400px] animate-pulse rounded-lg border bg-muted p-4" />
        );
    }

    return (
        <div className={cn('rounded-lg border', className)}>
            <div
                className="sticky top-0 z-50 rounded-t-lg border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80"
                style={{ boxShadow: '0 1px 3px 0 rgb(0 0 0 / 0.1)' }}
            >
                <EditorToolbar
                    editor={editor}
                    onGenerateImage={onGenerateImage}
                    onSearchYouTube={onSearchYouTube}
                />
            </div>
            <div className="relative">
                <EditorContent editor={editor} />
                <ImageBubbleMenu
                    editor={editor}
                    onRegenerate={
                        onRegenerateImage ? handleRegenerateImage : undefined
                    }
                    isRegenerating={isRegenerating}
                />
            </div>
        </div>
    );
}
