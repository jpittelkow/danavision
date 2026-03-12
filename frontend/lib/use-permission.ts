"use client";

import { useAuth, isAdminUser } from "./auth";

/**
 * Check if the current user has a single permission.
 * Admin group users have all permissions implicitly.
 */
export function usePermission(permission: string): boolean {
  const { user } = useAuth();
  if (!user) return false;
  if (isAdminUser(user)) return true;
  return user.permissions?.includes(permission) ?? false;
}

/**
 * Check if the current user has each of the given permissions.
 * Returns an array of booleans in the same order as the input permissions.
 */
export function usePermissions(permissions: string[]): boolean[] {
  const { user } = useAuth();
  if (!user) return permissions.map(() => false);
  if (isAdminUser(user)) return permissions.map(() => true);
  return permissions.map((p) => user.permissions?.includes(p) ?? false);
}
