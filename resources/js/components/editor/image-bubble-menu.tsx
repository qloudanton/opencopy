import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { type Editor } from '@tiptap/react';
import { BubbleMenu } from '@tiptap/react/menus';
import { ImageIcon, Loader2, Pencil, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface ImageBubbleMenuProps {
    editor: Editor;
    onRegenerate?: (options: {
        src: string;
        alt: string;
        style: string;
        prompt: string;
    }) => Promise<string | null>;
    isRegenerating?: boolean;
}

const IMAGE_STYLES = [
    { value: 'illustration', label: 'Illustration' },
    { value: 'realistic', label: 'Realistic / Photo' },
    { value: 'sketch', label: 'Sketch' },
    { value: 'watercolor', label: 'Watercolor' },
    { value: 'cinematic', label: 'Cinematic' },
    { value: 'brand_text', label: 'Brand Style' },
] as const;

export function ImageBubbleMenu({
    editor,
    onRegenerate,
    isRegenerating = false,
}: ImageBubbleMenuProps) {
    const [editAltOpen, setEditAltOpen] = useState(false);
    const [regenerateOpen, setRegenerateOpen] = useState(false);
    const [altText, setAltText] = useState('');
    const [style, setStyle] = useState('illustration');
    const [prompt, setPrompt] = useState('');

    // Get current image attributes when bubble menu shows
    const getCurrentImageAttrs = () => {
        const { selection } = editor.state;
        const node = editor.state.doc.nodeAt(selection.from);
        if (node?.type.name === 'image') {
            return node.attrs;
        }
        return null;
    };

    const handleEditAltOpen = (open: boolean) => {
        if (open) {
            const attrs = getCurrentImageAttrs();
            if (attrs) {
                setAltText(attrs.alt || '');
            }
        }
        setEditAltOpen(open);
    };

    const handleRegenerateOpen = (open: boolean) => {
        if (open) {
            const attrs = getCurrentImageAttrs();
            if (attrs) {
                setStyle(attrs['data-style'] || 'illustration');
                setPrompt(attrs.alt || '');
            }
        }
        setRegenerateOpen(open);
    };

    const handleSaveAlt = () => {
        editor
            .chain()
            .focus()
            .updateAttributes('image', { alt: altText })
            .run();
        setEditAltOpen(false);
    };

    const handleRegenerate = async () => {
        if (!onRegenerate) return;

        const attrs = getCurrentImageAttrs();
        if (!attrs) return;

        const newSrc = await onRegenerate({
            src: attrs.src,
            alt: attrs.alt || '',
            style,
            prompt: prompt || attrs.alt || 'Generate an image',
        });

        if (newSrc) {
            editor
                .chain()
                .focus()
                .updateAttributes('image', {
                    src: newSrc,
                    'data-style': style,
                    alt: prompt || attrs.alt,
                })
                .run();
        }

        setRegenerateOpen(false);
    };

    const handleDelete = () => {
        editor.chain().focus().deleteSelection().run();
    };

    return (
        <BubbleMenu
            editor={editor}
            tippyOptions={{
                duration: 100,
                placement: 'top',
            }}
            shouldShow={({ editor, state }) => {
                const { selection } = state;
                const node = state.doc.nodeAt(selection.from);
                return node?.type.name === 'image' && editor.isEditable;
            }}
            className="flex items-center gap-1 rounded-lg border bg-background p-1 shadow-lg"
        >
            {/* Edit Alt Text */}
            <Popover open={editAltOpen} onOpenChange={handleEditAltOpen}>
                <PopoverTrigger asChild>
                    <Button variant="ghost" size="sm" className="h-8 px-2">
                        <Pencil className="mr-1 h-4 w-4" />
                        Alt Text
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-80" align="start">
                    <div className="space-y-3">
                        <div className="space-y-2">
                            <Label htmlFor="alt-text">Alt Text</Label>
                            <Input
                                id="alt-text"
                                value={altText}
                                onChange={(e) => setAltText(e.target.value)}
                                placeholder="Describe the image..."
                            />
                            <p className="text-xs text-muted-foreground">
                                Describes the image for accessibility and SEO
                            </p>
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setEditAltOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button size="sm" onClick={handleSaveAlt}>
                                Save
                            </Button>
                        </div>
                    </div>
                </PopoverContent>
            </Popover>

            {/* Regenerate Image */}
            {onRegenerate && (
                <Popover
                    open={regenerateOpen}
                    onOpenChange={handleRegenerateOpen}
                >
                    <PopoverTrigger asChild>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 px-2"
                            disabled={isRegenerating}
                        >
                            {isRegenerating ? (
                                <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                            ) : (
                                <RefreshCw className="mr-1 h-4 w-4" />
                            )}
                            Regenerate
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-96" align="start">
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <ImageIcon className="h-5 w-5 text-muted-foreground" />
                                <h4 className="font-medium">
                                    Regenerate Image
                                </h4>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="image-style">Style</Label>
                                <Select value={style} onValueChange={setStyle}>
                                    <SelectTrigger id="image-style">
                                        <SelectValue placeholder="Select style" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {IMAGE_STYLES.map((s) => (
                                            <SelectItem
                                                key={s.value}
                                                value={s.value}
                                            >
                                                {s.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="image-prompt">
                                    AI Instructions
                                </Label>
                                <Textarea
                                    id="image-prompt"
                                    value={prompt}
                                    onChange={(e) => setPrompt(e.target.value)}
                                    placeholder="Describe what the image should show..."
                                    rows={3}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Describe the image you want to generate.
                                    This will also update the alt text.
                                </p>
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setRegenerateOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={handleRegenerate}
                                    disabled={isRegenerating || !prompt.trim()}
                                >
                                    {isRegenerating ? (
                                        <>
                                            <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                                            Generating...
                                        </>
                                    ) : (
                                        <>
                                            <RefreshCw className="mr-1 h-4 w-4" />
                                            Generate
                                        </>
                                    )}
                                </Button>
                            </div>
                        </div>
                    </PopoverContent>
                </Popover>
            )}

            {/* Delete */}
            <Button
                variant="ghost"
                size="sm"
                className="h-8 px-2 text-destructive hover:text-destructive"
                onClick={handleDelete}
            >
                <Trash2 className="h-4 w-4" />
            </Button>
        </BubbleMenu>
    );
}
