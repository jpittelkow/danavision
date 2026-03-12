/**
 * Tests for Zod validation schemas used in auth and admin forms.
 * These mirror the inline schemas in page components to catch regressions.
 */
import { describe, it, expect } from "vitest";
import { z } from "zod";

// Mirror of login schema from app/(auth)/login/page.tsx
const loginSchema = z.object({
  email: z.string().email("Invalid email address"),
  password: z.string().min(1, "Password is required"),
  remember: z.boolean().optional(),
});

// Mirror of register schema from app/(auth)/register/page.tsx
const registerSchema = z
  .object({
    name: z.string().min(2, "Name must be at least 2 characters"),
    email: z.string().email("Invalid email address"),
    password: z.string().min(8, "Password must be at least 8 characters"),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ["password_confirmation"],
  });

// Mirror of user schema from components/admin/user-dialog.tsx
const userSchema = z.object({
  name: z.string().min(2, "Name must be at least 2 characters"),
  email: z.string().email("Invalid email address"),
  password: z
    .string()
    .min(8, "Password must be at least 8 characters")
    .optional()
    .or(z.literal("")),
  is_admin: z.boolean(),
  skip_verification: z.boolean().optional(),
});

describe("loginSchema", () => {
  it("accepts valid login", () => {
    const result = loginSchema.safeParse({
      email: "user@example.com",
      password: "secret",
    });
    expect(result.success).toBe(true);
  });

  it("rejects invalid email", () => {
    const result = loginSchema.safeParse({
      email: "not-an-email",
      password: "secret",
    });
    expect(result.success).toBe(false);
  });

  it("rejects empty password", () => {
    const result = loginSchema.safeParse({
      email: "user@example.com",
      password: "",
    });
    expect(result.success).toBe(false);
  });

  it("accepts optional remember field", () => {
    const result = loginSchema.safeParse({
      email: "user@example.com",
      password: "secret",
      remember: true,
    });
    expect(result.success).toBe(true);
  });
});

describe("registerSchema", () => {
  const valid = {
    name: "Test User",
    email: "user@example.com",
    password: "password123",
    password_confirmation: "password123",
  };

  it("accepts valid registration", () => {
    expect(registerSchema.safeParse(valid).success).toBe(true);
  });

  it("rejects short name", () => {
    expect(registerSchema.safeParse({ ...valid, name: "A" }).success).toBe(false);
  });

  it("rejects short password", () => {
    expect(
      registerSchema.safeParse({ ...valid, password: "short", password_confirmation: "short" })
        .success
    ).toBe(false);
  });

  it("rejects mismatched passwords", () => {
    const result = registerSchema.safeParse({
      ...valid,
      password_confirmation: "different",
    });
    expect(result.success).toBe(false);
  });

  it("rejects invalid email", () => {
    expect(registerSchema.safeParse({ ...valid, email: "bad" }).success).toBe(false);
  });
});

describe("userSchema (admin user dialog)", () => {
  const valid = {
    name: "Admin User",
    email: "admin@example.com",
    is_admin: false,
  };

  it("accepts valid user without password", () => {
    expect(userSchema.safeParse(valid).success).toBe(true);
  });

  it("accepts valid user with password", () => {
    expect(userSchema.safeParse({ ...valid, password: "longpassword" }).success).toBe(true);
  });

  it("accepts empty string password (for edit mode)", () => {
    expect(userSchema.safeParse({ ...valid, password: "" }).success).toBe(true);
  });

  it("rejects password shorter than 8 chars (non-empty)", () => {
    expect(userSchema.safeParse({ ...valid, password: "short" }).success).toBe(false);
  });

  it("rejects short name", () => {
    expect(userSchema.safeParse({ ...valid, name: "A" }).success).toBe(false);
  });

  it("requires is_admin boolean", () => {
    const { is_admin, ...rest } = valid;
    expect(userSchema.safeParse(rest).success).toBe(false);
  });
});
