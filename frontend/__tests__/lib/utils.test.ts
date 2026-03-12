import { describe, it, expect, beforeEach } from "vitest";
import {
  formatBytes,
  formatDate,
  formatDateTime,
  formatTimestamp,
  getErrorMessage,
  formatCurrency,
  setUserTimezone,
  getUserTimezone,
} from "@/lib/utils";

beforeEach(() => {
  setUserTimezone(undefined);
});

describe("formatBytes", () => {
  it("returns '0 B' for 0", () => {
    expect(formatBytes(0)).toBe("0 B");
  });

  it("returns '0 B' for negative numbers", () => {
    expect(formatBytes(-100)).toBe("0 B");
  });

  it("returns '0 B' for NaN", () => {
    expect(formatBytes(NaN)).toBe("0 B");
  });

  it("returns '0 B' for Infinity", () => {
    expect(formatBytes(Infinity)).toBe("0 B");
  });

  it("formats bytes correctly", () => {
    expect(formatBytes(500)).toBe("500 B");
  });

  it("formats kilobytes", () => {
    expect(formatBytes(1024)).toBe("1 KB");
  });

  it("formats megabytes", () => {
    expect(formatBytes(1048576)).toBe("1 MB");
  });

  it("formats gigabytes", () => {
    expect(formatBytes(1073741824)).toBe("1 GB");
  });

  it("respects custom decimals", () => {
    expect(formatBytes(1536, 1)).toBe("1.5 KB");
  });
});

describe("formatDate", () => {
  it("formats a valid date string", () => {
    const result = formatDate("2026-01-15");
    expect(result).toBeTruthy();
    expect(result).not.toBe("2026-01-15"); // Should be formatted
  });

  it("returns original string for invalid date", () => {
    expect(formatDate("not-a-date")).toBe("not-a-date");
  });

  it("uses user timezone when set", () => {
    setUserTimezone("UTC");
    const result = formatDate("2026-06-15T12:00:00Z");
    expect(result).toBeTruthy();
  });
});

describe("formatDateTime", () => {
  it("formats a valid date with time", () => {
    const result = formatDateTime("2026-01-15T10:30:00Z");
    expect(result).toBeTruthy();
  });

  it("returns original string for invalid date", () => {
    expect(formatDateTime("invalid")).toBe("invalid");
  });
});

describe("formatTimestamp", () => {
  it("returns em-dash for null", () => {
    expect(formatTimestamp(null)).toBe("\u2014");
  });

  it("returns em-dash for undefined", () => {
    expect(formatTimestamp(undefined)).toBe("\u2014");
  });

  it("returns em-dash for NaN", () => {
    expect(formatTimestamp(NaN)).toBe("\u2014");
  });

  it("formats valid unix timestamp", () => {
    const result = formatTimestamp(1700000000);
    expect(result).toBeTruthy();
    expect(result).not.toBe("\u2014");
  });
});

describe("getErrorMessage", () => {
  it("extracts message from Error instance", () => {
    expect(getErrorMessage(new Error("test error"), "fallback")).toBe("test error");
  });

  it("extracts message from axios-style response", () => {
    const error = { response: { data: { message: "server error" } } };
    expect(getErrorMessage(error, "fallback")).toBe("server error");
  });

  it("extracts error field from axios-style response", () => {
    const error = { response: { data: { error: "bad request" } } };
    expect(getErrorMessage(error, "fallback")).toBe("bad request");
  });

  it("returns fallback for unknown error shape", () => {
    expect(getErrorMessage("something", "fallback")).toBe("fallback");
  });

  it("returns fallback for null", () => {
    expect(getErrorMessage(null, "fallback")).toBe("fallback");
  });
});

describe("formatCurrency", () => {
  it("formats normal value", () => {
    expect(formatCurrency(42.5)).toBe("$42.50");
  });

  it("formats zero", () => {
    expect(formatCurrency(0)).toBe("$0.00");
  });

  it("returns $0.00 for NaN", () => {
    expect(formatCurrency(NaN)).toBe("$0.00");
  });

  it("returns $0.00 for Infinity", () => {
    expect(formatCurrency(Infinity)).toBe("$0.00");
  });
});

describe("setUserTimezone / getUserTimezone", () => {
  it("defaults to undefined", () => {
    expect(getUserTimezone()).toBeUndefined();
  });

  it("stores and retrieves timezone", () => {
    setUserTimezone("America/New_York");
    expect(getUserTimezone()).toBe("America/New_York");
  });

  it("clears timezone with undefined", () => {
    setUserTimezone("UTC");
    setUserTimezone(undefined);
    expect(getUserTimezone()).toBeUndefined();
  });
});
