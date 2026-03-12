"use client";

import { useGroups } from "@/lib/use-groups";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Loader2 } from "lucide-react";

interface UserGroupPickerProps {
  selectedGroupIds: number[];
  onChange: (groupIds: number[]) => void;
  disabled?: boolean;
  /** Current logged-in user id – prevents removing self from admin group */
  currentUserId?: number;
  /** User being edited – when same as currentUserId, cannot remove self from admin */
  editedUserId?: number;
}

export function UserGroupPicker({
  selectedGroupIds,
  onChange,
  disabled,
  currentUserId,
  editedUserId,
}: UserGroupPickerProps) {
  const { groups, isLoading } = useGroups();

  const toggleGroup = (groupId: number) => {
    if (selectedGroupIds.includes(groupId)) {
      onChange(selectedGroupIds.filter((id) => id !== groupId));
    } else {
      onChange([...selectedGroupIds, groupId]);
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center gap-2 py-2 text-sm text-muted-foreground">
        <Loader2 className="h-4 w-4 animate-spin" />
        Loading groups…
      </div>
    );
  }

  if (groups.length === 0) {
    return (
      <p className="py-2 text-sm text-muted-foreground">No groups available.</p>
    );
  }

  return (
    <div className="space-y-3">
      {groups.map((group) => {
        const isSelected = selectedGroupIds.includes(group.id);
        const isDisabled =
          disabled ||
          (group.slug === "admin" &&
            currentUserId !== undefined &&
            editedUserId === currentUserId &&
            isSelected);

        return (
          <div key={group.id} className="flex items-center space-x-2">
            <Checkbox
              id={`group-${group.id}`}
              checked={isSelected}
              onCheckedChange={() => toggleGroup(group.id)}
              disabled={isDisabled}
              aria-label={`Select group ${group.name}`}
            />
            <Label
              htmlFor={`group-${group.id}`}
              className="flex cursor-pointer items-center gap-2 font-normal"
            >
              {group.name}
              {group.is_system && (
                <Badge variant="outline" className="text-xs">
                  System
                </Badge>
              )}
            </Label>
          </div>
        );
      })}
    </div>
  );
}
