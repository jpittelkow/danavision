"use client";

import { useState, useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { FormField } from "@/components/ui/form-field";
import { Loader2 } from "lucide-react";
import type { Group } from "./group-table";

const groupSchema = z.object({
  name: z.string().min(2, "Name must be at least 2 characters"),
  slug: z
    .string()
    .min(2, "Slug must be at least 2 characters")
    .regex(/^[a-z0-9-]+$/, "Slug must be lowercase alphanumeric with hyphens"),
  description: z.string().optional(),
  is_default: z.boolean().default(false),
});

type GroupForm = z.infer<typeof groupSchema>;

function nameToSlug(name: string): string {
  return name
    .toLowerCase()
    .replace(/\s+/g, "-")
    .replace(/[^a-z0-9-]/g, "");
}

interface GroupDialogProps {
  group: Group | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess: () => void;
}

export function GroupDialog({
  group,
  open,
  onOpenChange,
  onSuccess,
}: GroupDialogProps) {
  const [isSaving, setIsSaving] = useState(false);
  const isEditing = !!group;

  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
    setValue,
    watch,
  } = useForm<GroupForm>({
    resolver: zodResolver(groupSchema),
    mode: "onBlur",
    defaultValues: {
      name: "",
      slug: "",
      description: "",
      is_default: false,
    },
  });

  const nameValue = watch("name");

  useEffect(() => {
    if (!open) return;
    if (group) {
      reset({
        name: group.name,
        slug: group.slug,
        description: group.description || "",
        is_default: group.is_default,
      });
    } else {
      reset({
        name: "",
        slug: "",
        description: "",
        is_default: false,
      });
    }
  }, [open, group, reset]);

  useEffect(() => {
    if (!isEditing && nameValue) {
      setValue("slug", nameToSlug(nameValue), { shouldDirty: true });
    }
  }, [nameValue, isEditing, setValue]);

  const onSubmit = async (data: GroupForm) => {
    setIsSaving(true);
    try {
      const payload = {
        name: data.name,
        slug: data.slug,
        description: data.description || null,
        is_default: data.is_default,
      };
      if (isEditing) {
        await api.put(`/groups/${group.id}`, payload);
        toast.success("Group updated successfully");
      } else {
        await api.post("/groups", payload);
        toast.success("Group created successfully");
      }
      onOpenChange(false);
      onSuccess();
    } catch (error: unknown) {
      const err = error as Error & { response?: { data?: { message?: string } } };
      toast.error(
        err.message ||
          err.response?.data?.message ||
          `Failed to ${isEditing ? "update" : "create"} group`
      );
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>{isEditing ? "Edit Group" : "Create Group"}</DialogTitle>
          <DialogDescription>
            {isEditing
              ? "Update group name, description, and default assignment."
              : "Create a new user group for permissions."}
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit(onSubmit)}>
          <div className="space-y-4 py-4">
            <FormField
              id="name"
              label="Name"
              error={errors.name?.message}
            >
              <Input
                id="name"
                {...register("name")}
                placeholder="e.g. Editors"
              />
            </FormField>

            <FormField
              id="slug"
              label="Slug"
              description="Lowercase, alphanumeric and hyphens only (used in code)."
              error={errors.slug?.message}
            >
              <Input
                id="slug"
                {...register("slug")}
                placeholder="e.g. editors"
                disabled={isEditing && group?.is_system}
              />
            </FormField>

            <FormField
              id="description"
              label="Description"
              error={errors.description?.message}
            >
              <Input
                id="description"
                {...register("description")}
                placeholder="Optional description"
              />
            </FormField>

            <div className="flex items-center justify-between">
              <div className="space-y-0.5">
                <Label>Default group</Label>
                <p className="text-sm text-muted-foreground">
                  New users are assigned to this group by default
                </p>
              </div>
              <Switch
                checked={watch("is_default")}
                onCheckedChange={(checked) =>
                  setValue("is_default", checked, { shouldDirty: true })
                }
              />
            </div>
          </div>
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={isSaving}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={isSaving}>
              {isSaving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {isEditing ? "Update" : "Create"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
