import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

interface WidgetCardProps {
  /** Optional title for the widget card header */
  title?: string;
  /** Widget content */
  children: React.ReactNode;
}

export function WidgetCard({ title, children }: WidgetCardProps) {
  return (
    <Card>
      {title && (
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium">{title}</CardTitle>
        </CardHeader>
      )}
      <CardContent className={title ? undefined : "pt-6"}>{children}</CardContent>
    </Card>
  );
}
