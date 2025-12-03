import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { CircleHelp, type LucideIcon } from 'lucide-react';

interface FieldLabelProps {
    htmlFor: string;
    label: string;
    tooltip: string;
    icon?: LucideIcon;
}

export default function FieldLabel({
    htmlFor,
    label,
    tooltip,
    icon: Icon,
}: FieldLabelProps) {
    return (
        <div className="flex items-center gap-1.5">
            <Label htmlFor={htmlFor} className="flex items-center gap-2">
                {Icon && <Icon className="h-4 w-4" />}
                {label}
            </Label>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        type="button"
                        className="inline-flex text-muted-foreground hover:text-foreground"
                    >
                        <CircleHelp className="h-3.5 w-3.5" />
                        <span className="sr-only">Help</span>
                    </button>
                </TooltipTrigger>
                <TooltipContent side="right" className="max-w-xs">
                    <p>{tooltip}</p>
                </TooltipContent>
            </Tooltip>
        </div>
    );
}
