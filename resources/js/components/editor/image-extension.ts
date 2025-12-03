import Image from '@tiptap/extension-image';
import { ReactNodeViewRenderer } from '@tiptap/react';
import { defaultMarkdownSerializer } from 'prosemirror-markdown';
import { ImageNodeView } from './image-node-view';

export interface ImageOptions {
    inline: boolean;
    allowBase64: boolean;
    HTMLAttributes: Record<string, unknown>;
}

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        customImage: {
            setImage: (options: {
                src: string;
                alt?: string;
                title?: string;
            }) => ReturnType;
            updateImageAlt: (alt: string) => ReturnType;
        };
    }
}

export const CustomImage = Image.extend({
    name: 'image',

    addAttributes() {
        return {
            ...this.parent?.(),
            alt: {
                default: '',
            },
            title: {
                default: null,
            },
            'data-image-id': {
                default: null,
            },
            'data-style': {
                default: 'illustration',
            },
        };
    },

    addStorage() {
        return {
            markdown: {
                serialize: defaultMarkdownSerializer.nodes.image,
                parse: {
                    // handled by markdown-it
                },
            },
        };
    },

    addNodeView() {
        return ReactNodeViewRenderer(ImageNodeView);
    },

    addCommands() {
        return {
            ...this.parent?.(),
            updateImageAlt:
                (alt: string) =>
                ({ commands, state }) => {
                    const { selection } = state;
                    const node = state.doc.nodeAt(selection.from);

                    if (node?.type.name === 'image') {
                        return commands.updateAttributes('image', { alt });
                    }

                    return false;
                },
        };
    },
});
