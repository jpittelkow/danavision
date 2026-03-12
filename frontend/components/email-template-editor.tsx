"use client";

import { useCallback, useEffect } from "react";
import { useEditor, EditorContent } from "@tiptap/react";
import StarterKit from "@tiptap/starter-kit";
import Link from "@tiptap/extension-link";
import Image from "@tiptap/extension-image";
import Placeholder from "@tiptap/extension-placeholder";
import { Button } from "@/components/ui/button";
import {
  Bold,
  Italic,
  List,
  ListOrdered,
  Heading1,
  Heading2,
  Heading3,
  Link as LinkIcon,
  ImageIcon,
} from "lucide-react";
import { VariablePicker } from "@/components/variable-picker";
import { cn } from "@/lib/utils";

export interface EmailTemplateEditorProps {
  content: string;
  onChange: (html: string) => void;
  variables: string[];
  placeholder?: string;
  disabled?: boolean;
  className?: string;
}

export function EmailTemplateEditor({
  content,
  onChange,
  variables,
  placeholder = "Write email content…",
  disabled = false,
  className,
}: EmailTemplateEditorProps) {
  const editor = useEditor({
    extensions: [
      StarterKit,
      Link.configure({
        openOnClick: false,
        HTMLAttributes: { target: "_blank", rel: "noopener noreferrer" },
      }),
      Image.configure({ inline: true }),
      Placeholder.configure({ placeholder }),
    ],
    content: content || "<p></p>",
    editable: !disabled,
    onUpdate: ({ editor }) => {
      onChange(editor.getHTML());
    },
    editorProps: {
      attributes: {
        class:
          "min-h-[200px] max-w-none px-3 py-2 text-sm focus:outline-none [&_h1]:text-xl [&_h1]:font-bold [&_h2]:text-lg [&_h2]:font-bold [&_h3]:text-base [&_h3]:font-bold [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_a]:text-primary [&_a]:underline",
      },
    },
  });

  // Sync content when it changes from parent (e.g. after loading a template)
  useEffect(() => {
    if (!editor) return;
    const current = editor.getHTML();
    const normalized = content || "<p></p>";
    if (current !== normalized) {
      editor.commands.setContent(normalized, false);
    }
  }, [editor, content]);

  const insertVariable = useCallback(
    (variable: string) => {
      if (!editor) return;
      const text = `{{${variable}}}`;
      editor.chain().focus().insertContent(text).run();
    },
    [editor]
  );

  const setLink = useCallback(() => {
    if (!editor) return;
    const url = window.prompt("Enter URL:", "https://");
    if (url) {
      editor.chain().focus().setLink({ href: url }).run();
    }
  }, [editor]);

  const setImage = useCallback(() => {
    if (!editor) return;
    const url = window.prompt("Enter image URL:", "https://");
    if (url) {
      editor.chain().focus().setImage({ src: url }).run();
    }
  }, [editor]);

  if (!editor) {
    return (
      <div
        className={cn(
          "min-h-[200px] rounded-md border bg-muted/30 animate-pulse",
          className
        )}
      />
    );
  }

  return (
    <div
      className={cn(
        "rounded-md border bg-background overflow-hidden",
        className
      )}
    >
      <div className="flex flex-wrap items-center gap-1 border-b bg-muted/30 p-1">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-8 w-8 p-0"
          onClick={() => editor.chain().focus().toggleBold().run()}
          disabled={disabled}
          title="Bold"
        >
          <Bold className="h-4 w-4" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-8 w-8 p-0"
          onClick={() => editor.chain().focus().toggleItalic().run()}
          disabled={disabled}
          title="Italic"
        >
          <Italic className="h-4 w-4" />
        </Button>
        <span className="mx-1 h-4 w-px bg-border" />
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-8 w-8 p-0"
          onClick={() => editor.chain().focus().toggleHeading({ level: 1 }).run()}
          disabled={disabled}
          title="Heading 1"
        >
          <Heading1 className="h-4 w-4" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-8 w-8 p-0"
          onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
          disabled={disabled}
          title="Heading 2"
        >
          <Heading2 className="h-4 w-4" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-8 w-8 p-0"
          onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
          disabled={disabled}
          title="Heading 3"
        >
          <Heading3 className="h-4 w-4" />
        </Button>
        <span className="mx-1 h-4 w-px bg-border" />
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-8 w-8 p-0"
          onClick={() => editor.chain().focus().toggleBulletList().run()}
          disabled={disabled}
          title="Bullet list"
        >
          <List className="h-4 w-4" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-8 w-8 p-0"
          onClick={() => editor.chain().focus().toggleOrderedList().run()}
          disabled={disabled}
          title="Numbered list"
        >
          <ListOrdered className="h-4 w-4" />
        </Button>
        <span className="mx-1 h-4 w-px bg-border" />
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-8 w-8 p-0"
          onClick={setLink}
          disabled={disabled}
          title="Insert link"
        >
          <LinkIcon className="h-4 w-4" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-8 w-8 p-0"
          onClick={setImage}
          disabled={disabled}
          title="Insert image"
        >
          <ImageIcon className="h-4 w-4" />
        </Button>
        {variables.length > 0 && (
          <>
            <span className="mx-1 h-4 w-px bg-border" />
            <VariablePicker
              variables={variables}
              onSelect={insertVariable}
              disabled={disabled}
            />
          </>
        )}
      </div>
      <EditorContent editor={editor} />
    </div>
  );
}
