"use client";

import { type ReactNode } from "react";
import { usePermission } from "@/lib/use-permission";

export interface PermissionGateProps {
  /** Permission string (e.g. "users.view", "settings.edit") */
  permission: string;
  /** Content to render when user has the permission */
  children: ReactNode;
  /** Optional content to render when user lacks the permission (default: null) */
  fallback?: ReactNode;
}

/**
 * Conditionally renders children based on the current user's permissions.
 * Admin group users pass all permission checks.
 */
export function PermissionGate({
  permission,
  children,
  fallback = null,
}: PermissionGateProps) {
  const hasPermission = usePermission(permission);
  return hasPermission ? <>{children}</> : <>{fallback}</>;
}
