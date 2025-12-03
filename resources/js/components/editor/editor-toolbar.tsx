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
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { type Editor } from '@tiptap/react';
import {
    Bold,
    Code,
    ImageIcon,
    Italic,
    Link,
    List,
    ListOrdered,
    Loader2,
    Quote,
    Redo,
    Sparkles,
    Strikethrough,
    Undo,
    Youtube,
} from 'lucide-react';
import { useState } from 'react';

const IMAGE_STYLES = [
    { value: 'illustration', label: 'Illustration' },
    { value: 'realistic', label: 'Realistic / Photo' },
    { value: 'sketch', label: 'Sketch' },
    { value: 'watercolor', label: 'Watercolor' },
    { value: 'cinematic', label: 'Cinematic' },
    { value: 'brand_text', label: 'Brand Style' },
] as const;

interface YouTubeVideo {
    id: string;
    title: string;
    description: string;
    thumbnail: string;
    channelTitle: string;
    url: string;
}

interface EditorToolbarProps {
    editor: Editor;
    onGenerateImage?: (options: {
        style: string;
        prompt: string;
    }) => Promise<string | null>;
    onSearchYouTube?: (query: string) => Promise<YouTubeVideo[]>;
}

export function EditorToolbar({
    editor,
    onGenerateImage,
    onSearchYouTube,
}: EditorToolbarProps) {
    const [imageOpen, setImageOpen] = useState(false);
    const [youtubeOpen, setYoutubeOpen] = useState(false);
    const [imageUrl, setImageUrl] = useState('');
    const [imageAlt, setImageAlt] = useState('');
    const [youtubeUrl, setYoutubeUrl] = useState('');
    const [youtubeQuery, setYoutubeQuery] = useState('');
    const [youtubeResults, setYoutubeResults] = useState<YouTubeVideo[]>([]);
    const [isSearchingYouTube, setIsSearchingYouTube] = useState(false);
    const [aiPrompt, setAiPrompt] = useState('');
    const [aiStyle, setAiStyle] = useState('illustration');
    const [isGenerating, setIsGenerating] = useState(false);

    const addLink = () => {
        const url = window.prompt('Enter URL:');
        if (url) {
            editor
                .chain()
                .focus()
                .extendMarkRange('link')
                .setLink({ href: url })
                .run();
        }
    };

    const insertImageFromUrl = () => {
        if (imageUrl) {
            editor
                .chain()
                .focus()
                .setImage({ src: imageUrl, alt: imageAlt || '' })
                .run();
            setImageUrl('');
            setImageAlt('');
            setImageOpen(false);
        }
    };

    const generateAndInsertImage = async () => {
        if (!onGenerateImage || !aiPrompt.trim()) return;

        setIsGenerating(true);
        try {
            const url = await onGenerateImage({
                style: aiStyle,
                prompt: aiPrompt,
            });

            if (url) {
                editor
                    .chain()
                    .focus()
                    .setImage({ src: url, alt: aiPrompt })
                    .run();
                setAiPrompt('');
                setImageOpen(false);
            }
        } finally {
            setIsGenerating(false);
        }
    };

    const insertYoutubeVideo = (url?: string) => {
        const videoUrl = url || youtubeUrl;
        if (videoUrl) {
            editor.chain().focus().setYoutubeVideo({ src: videoUrl }).run();
            setYoutubeUrl('');
            setYoutubeQuery('');
            setYoutubeResults([]);
            setYoutubeOpen(false);
        }
    };

    const searchYouTubeVideos = async () => {
        if (!onSearchYouTube || !youtubeQuery.trim()) return;

        setIsSearchingYouTube(true);
        try {
            const videos = await onSearchYouTube(youtubeQuery);
            setYoutubeResults(videos);
        } finally {
            setIsSearchingYouTube(false);
        }
    };

    return (
        <div className="flex flex-wrap items-center gap-1 bg-muted/50 p-2">
            {/* Undo/Redo */}
            <ToolbarButton
                onClick={() => editor.chain().focus().undo().run()}
                disabled={!editor.can().undo()}
                title="Undo"
            >
                <Undo className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => editor.chain().focus().redo().run()}
                disabled={!editor.can().redo()}
                title="Redo"
            >
                <Redo className="h-4 w-4" />
            </ToolbarButton>

            <Separator orientation="vertical" className="mx-1 h-6" />

            {/* Headings */}
            <Select
                value={
                    editor.isActive('heading', { level: 1 })
                        ? '1'
                        : editor.isActive('heading', { level: 2 })
                          ? '2'
                          : editor.isActive('heading', { level: 3 })
                            ? '3'
                            : 'p'
                }
                onValueChange={(value) => {
                    if (value === 'p') {
                        editor.chain().focus().setParagraph().run();
                    } else {
                        editor
                            .chain()
                            .focus()
                            .toggleHeading({
                                level: parseInt(value) as 1 | 2 | 3,
                            })
                            .run();
                    }
                }}
            >
                <SelectTrigger className="h-8 w-[130px]">
                    <SelectValue placeholder="Paragraph" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="p">Paragraph</SelectItem>
                    <SelectItem value="1">Heading 1</SelectItem>
                    <SelectItem value="2">Heading 2</SelectItem>
                    <SelectItem value="3">Heading 3</SelectItem>
                </SelectContent>
            </Select>

            <Separator orientation="vertical" className="mx-1 h-6" />

            {/* Text formatting */}
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleBold().run()}
                active={editor.isActive('bold')}
                title="Bold"
            >
                <Bold className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleItalic().run()}
                active={editor.isActive('italic')}
                title="Italic"
            >
                <Italic className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleStrike().run()}
                active={editor.isActive('strike')}
                title="Strikethrough"
            >
                <Strikethrough className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleCode().run()}
                active={editor.isActive('code')}
                title="Inline Code"
            >
                <Code className="h-4 w-4" />
            </ToolbarButton>

            <Separator orientation="vertical" className="mx-1 h-6" />

            {/* Lists */}
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleBulletList().run()}
                active={editor.isActive('bulletList')}
                title="Bullet List"
            >
                <List className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
                active={editor.isActive('orderedList')}
                title="Numbered List"
            >
                <ListOrdered className="h-4 w-4" />
            </ToolbarButton>
            <ToolbarButton
                onClick={() => editor.chain().focus().toggleBlockquote().run()}
                active={editor.isActive('blockquote')}
                title="Quote"
            >
                <Quote className="h-4 w-4" />
            </ToolbarButton>

            <Separator orientation="vertical" className="mx-1 h-6" />

            {/* Link */}
            <ToolbarButton
                onClick={addLink}
                active={editor.isActive('link')}
                title="Add Link"
            >
                <Link className="h-4 w-4" />
            </ToolbarButton>

            {/* Image */}
            <Popover open={imageOpen} onOpenChange={setImageOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        title="Insert Image"
                        className={cn('h-8 w-8 p-0')}
                    >
                        <ImageIcon className="h-4 w-4" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-96" align="start">
                    <Tabs defaultValue={onGenerateImage ? 'generate' : 'url'}>
                        <TabsList className="grid w-full grid-cols-2">
                            {onGenerateImage && (
                                <TabsTrigger value="generate">
                                    <Sparkles className="mr-1 h-4 w-4" />
                                    AI Generate
                                </TabsTrigger>
                            )}
                            <TabsTrigger value="url">
                                <ImageIcon className="mr-1 h-4 w-4" />
                                From URL
                            </TabsTrigger>
                        </TabsList>

                        {onGenerateImage && (
                            <TabsContent value="generate" className="space-y-3">
                                <div className="space-y-2">
                                    <Label>Style</Label>
                                    <Select
                                        value={aiStyle}
                                        onValueChange={setAiStyle}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select style" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {IMAGE_STYLES.map((style) => (
                                                <SelectItem
                                                    key={style.value}
                                                    value={style.value}
                                                >
                                                    {style.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Description</Label>
                                    <Textarea
                                        placeholder="Describe the image you want to generate..."
                                        value={aiPrompt}
                                        onChange={(e) =>
                                            setAiPrompt(e.target.value)
                                        }
                                        rows={3}
                                    />
                                </div>
                                <Button
                                    className="w-full"
                                    onClick={generateAndInsertImage}
                                    disabled={isGenerating || !aiPrompt.trim()}
                                >
                                    {isGenerating ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Generating...
                                        </>
                                    ) : (
                                        <>
                                            <Sparkles className="mr-2 h-4 w-4" />
                                            Generate Image
                                        </>
                                    )}
                                </Button>
                            </TabsContent>
                        )}

                        <TabsContent value="url" className="space-y-3">
                            <div className="space-y-2">
                                <Label>Image URL</Label>
                                <Input
                                    placeholder="https://example.com/image.jpg"
                                    value={imageUrl}
                                    onChange={(e) =>
                                        setImageUrl(e.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Alt Text (optional)</Label>
                                <Input
                                    placeholder="Describe the image..."
                                    value={imageAlt}
                                    onChange={(e) =>
                                        setImageAlt(e.target.value)
                                    }
                                />
                            </div>
                            <Button
                                className="w-full"
                                onClick={insertImageFromUrl}
                                disabled={!imageUrl.trim()}
                            >
                                Insert Image
                            </Button>
                        </TabsContent>
                    </Tabs>
                </PopoverContent>
            </Popover>

            {/* YouTube */}
            <Popover
                open={youtubeOpen}
                onOpenChange={(open) => {
                    setYoutubeOpen(open);
                    if (!open) {
                        setYoutubeResults([]);
                        setYoutubeQuery('');
                    }
                }}
            >
                <PopoverTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        title="Insert YouTube Video"
                        className={cn('h-8 w-8 p-0')}
                    >
                        <Youtube className="h-4 w-4" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-96" align="start">
                    <Tabs defaultValue={onSearchYouTube ? 'search' : 'url'}>
                        <TabsList className="grid w-full grid-cols-2">
                            {onSearchYouTube && (
                                <TabsTrigger value="search">
                                    <Sparkles className="mr-1 h-4 w-4" />
                                    Search
                                </TabsTrigger>
                            )}
                            <TabsTrigger value="url">
                                <Youtube className="mr-1 h-4 w-4" />
                                From URL
                            </TabsTrigger>
                        </TabsList>

                        {onSearchYouTube && (
                            <TabsContent value="search" className="space-y-3">
                                <div className="space-y-2">
                                    <Label>Search YouTube</Label>
                                    <div className="flex gap-2">
                                        <Input
                                            placeholder="Search for videos..."
                                            value={youtubeQuery}
                                            onChange={(e) =>
                                                setYoutubeQuery(e.target.value)
                                            }
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    e.preventDefault();
                                                    searchYouTubeVideos();
                                                }
                                            }}
                                        />
                                        <Button
                                            size="sm"
                                            onClick={searchYouTubeVideos}
                                            disabled={
                                                isSearchingYouTube ||
                                                !youtubeQuery.trim()
                                            }
                                        >
                                            {isSearchingYouTube ? (
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                            ) : (
                                                'Search'
                                            )}
                                        </Button>
                                    </div>
                                </div>

                                {youtubeResults.length > 0 && (
                                    <div className="max-h-64 space-y-2 overflow-y-auto">
                                        {youtubeResults.map((video) => (
                                            <button
                                                key={video.id}
                                                type="button"
                                                onClick={() =>
                                                    insertYoutubeVideo(
                                                        video.url,
                                                    )
                                                }
                                                className="flex w-full gap-3 rounded-lg p-2 text-left transition-colors hover:bg-muted"
                                            >
                                                <img
                                                    src={video.thumbnail}
                                                    alt={video.title}
                                                    className="h-16 w-24 shrink-0 rounded object-cover"
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <p className="line-clamp-2 text-sm font-medium">
                                                        {video.title}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {video.channelTitle}
                                                    </p>
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                )}

                                {youtubeResults.length === 0 &&
                                    !isSearchingYouTube &&
                                    youtubeQuery && (
                                        <p className="py-4 text-center text-sm text-muted-foreground">
                                            No videos found. Try a different
                                            search.
                                        </p>
                                    )}
                            </TabsContent>
                        )}

                        <TabsContent value="url" className="space-y-3">
                            <div className="space-y-2">
                                <Label>YouTube URL</Label>
                                <Input
                                    placeholder="https://www.youtube.com/watch?v=..."
                                    value={youtubeUrl}
                                    onChange={(e) =>
                                        setYoutubeUrl(e.target.value)
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Paste a YouTube video URL or video ID
                                </p>
                            </div>
                            <Button
                                className="w-full"
                                onClick={() => insertYoutubeVideo()}
                                disabled={!youtubeUrl.trim()}
                            >
                                <Youtube className="mr-2 h-4 w-4" />
                                Insert Video
                            </Button>
                        </TabsContent>
                    </Tabs>
                </PopoverContent>
            </Popover>
        </div>
    );
}

interface ToolbarButtonProps {
    onClick: () => void;
    disabled?: boolean;
    active?: boolean;
    title: string;
    children: React.ReactNode;
}

function ToolbarButton({
    onClick,
    disabled,
    active,
    title,
    children,
}: ToolbarButtonProps) {
    return (
        <Button
            variant="ghost"
            size="sm"
            onClick={onClick}
            disabled={disabled}
            title={title}
            className={cn('h-8 w-8 p-0', active && 'bg-muted')}
        >
            {children}
        </Button>
    );
}
