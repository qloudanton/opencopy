import { cn } from '@/lib/utils';
import { NodeViewWrapper, type NodeViewProps } from '@tiptap/react';

export function ImageNodeView({ node, selected }: NodeViewProps) {
    const { src, alt, title } = node.attrs;

    return (
        <NodeViewWrapper
            className={cn(
                'relative my-4 inline-block max-w-full',
                selected && 'rounded-lg ring-2 ring-primary ring-offset-2',
            )}
            data-drag-handle
        >
            <img
                src={src}
                alt={alt || ''}
                title={title || undefined}
                className="h-auto max-w-full rounded-lg"
                draggable={false}
            />
            {selected && alt && (
                <div className="absolute right-0 bottom-0 left-0 truncate rounded-b-lg bg-black/70 px-2 py-1 text-xs text-white">
                    Alt: {alt}
                </div>
            )}
        </NodeViewWrapper>
    );
}
