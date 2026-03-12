import { describe, it, expect, vi, beforeEach } from "vitest";
import { renderHook } from "@testing-library/react";

// Mock the auth module
const mockUseAuth = vi.fn();
const mockIsAdminUser = vi.fn();

vi.mock("@/lib/auth", () => ({
  useAuth: () => mockUseAuth(),
  isAdminUser: (user: unknown) => mockIsAdminUser(user),
}));

import { usePermission, usePermissions } from "@/lib/use-permission";

beforeEach(() => {
  vi.clearAllMocks();
});

describe("usePermission", () => {
  it("returns false when no user", () => {
    mockUseAuth.mockReturnValue({ user: null });
    const { result } = renderHook(() => usePermission("users.view"));
    expect(result.current).toBe(false);
  });

  it("returns true for admin user regardless of permission", () => {
    const user = { id: 1, permissions: [] };
    mockUseAuth.mockReturnValue({ user });
    mockIsAdminUser.mockReturnValue(true);
    const { result } = renderHook(() => usePermission("any.permission"));
    expect(result.current).toBe(true);
  });

  it("returns true when user has the permission", () => {
    const user = { id: 2, permissions: ["users.view", "users.edit"] };
    mockUseAuth.mockReturnValue({ user });
    mockIsAdminUser.mockReturnValue(false);
    const { result } = renderHook(() => usePermission("users.view"));
    expect(result.current).toBe(true);
  });

  it("returns false when user lacks the permission", () => {
    const user = { id: 2, permissions: ["users.view"] };
    mockUseAuth.mockReturnValue({ user });
    mockIsAdminUser.mockReturnValue(false);
    const { result } = renderHook(() => usePermission("users.edit"));
    expect(result.current).toBe(false);
  });

  it("returns false when user has no permissions array", () => {
    const user = { id: 2 };
    mockUseAuth.mockReturnValue({ user });
    mockIsAdminUser.mockReturnValue(false);
    const { result } = renderHook(() => usePermission("users.view"));
    expect(result.current).toBe(false);
  });
});

describe("usePermissions", () => {
  it("returns all false when no user", () => {
    mockUseAuth.mockReturnValue({ user: null });
    const { result } = renderHook(() =>
      usePermissions(["users.view", "users.edit"])
    );
    expect(result.current).toEqual([false, false]);
  });

  it("returns all true for admin user", () => {
    const user = { id: 1, permissions: [] };
    mockUseAuth.mockReturnValue({ user });
    mockIsAdminUser.mockReturnValue(true);
    const { result } = renderHook(() =>
      usePermissions(["a", "b", "c"])
    );
    expect(result.current).toEqual([true, true, true]);
  });

  it("returns correct booleans per permission", () => {
    const user = { id: 2, permissions: ["users.view", "logs.view"] };
    mockUseAuth.mockReturnValue({ user });
    mockIsAdminUser.mockReturnValue(false);
    const { result } = renderHook(() =>
      usePermissions(["users.view", "users.edit", "logs.view"])
    );
    expect(result.current).toEqual([true, false, true]);
  });
});
