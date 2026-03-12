/**
 * Shared timezone list used by both the admin system settings page
 * and the user preferences timezone picker.
 */
export const TIMEZONES = [
  // UTC
  { value: "UTC", label: "UTC" },

  // Americas
  { value: "America/New_York", label: "Eastern Time (US)" },
  { value: "America/Chicago", label: "Central Time (US)" },
  { value: "America/Denver", label: "Mountain Time (US)" },
  { value: "America/Los_Angeles", label: "Pacific Time (US)" },
  { value: "America/Anchorage", label: "Alaska" },
  { value: "Pacific/Honolulu", label: "Hawaii" },
  { value: "America/Toronto", label: "Toronto" },
  { value: "America/Vancouver", label: "Vancouver" },
  { value: "America/Mexico_City", label: "Mexico City" },
  { value: "America/Bogota", label: "Bogota" },
  { value: "America/Lima", label: "Lima" },
  { value: "America/Santiago", label: "Santiago" },
  { value: "America/Sao_Paulo", label: "Sao Paulo" },
  { value: "America/Buenos_Aires", label: "Buenos Aires" },

  // Europe
  { value: "Europe/London", label: "London" },
  { value: "Europe/Paris", label: "Paris" },
  { value: "Europe/Berlin", label: "Berlin" },
  { value: "Europe/Rome", label: "Rome" },
  { value: "Europe/Madrid", label: "Madrid" },
  { value: "Europe/Amsterdam", label: "Amsterdam" },
  { value: "Europe/Brussels", label: "Brussels" },
  { value: "Europe/Zurich", label: "Zurich" },
  { value: "Europe/Stockholm", label: "Stockholm" },
  { value: "Europe/Oslo", label: "Oslo" },
  { value: "Europe/Helsinki", label: "Helsinki" },
  { value: "Europe/Warsaw", label: "Warsaw" },
  { value: "Europe/Prague", label: "Prague" },
  { value: "Europe/Vienna", label: "Vienna" },
  { value: "Europe/Lisbon", label: "Lisbon" },
  { value: "Europe/Athens", label: "Athens" },
  { value: "Europe/Bucharest", label: "Bucharest" },
  { value: "Europe/Moscow", label: "Moscow" },
  { value: "Europe/Istanbul", label: "Istanbul" },

  // Asia
  { value: "Asia/Dubai", label: "Dubai" },
  { value: "Asia/Kolkata", label: "India (Kolkata)" },
  { value: "Asia/Colombo", label: "Sri Lanka" },
  { value: "Asia/Dhaka", label: "Dhaka" },
  { value: "Asia/Bangkok", label: "Bangkok" },
  { value: "Asia/Jakarta", label: "Jakarta" },
  { value: "Asia/Singapore", label: "Singapore" },
  { value: "Asia/Hong_Kong", label: "Hong Kong" },
  { value: "Asia/Shanghai", label: "Shanghai" },
  { value: "Asia/Taipei", label: "Taipei" },
  { value: "Asia/Seoul", label: "Seoul" },
  { value: "Asia/Tokyo", label: "Tokyo" },

  // Oceania
  { value: "Australia/Perth", label: "Perth" },
  { value: "Australia/Adelaide", label: "Adelaide" },
  { value: "Australia/Sydney", label: "Sydney" },
  { value: "Australia/Melbourne", label: "Melbourne" },
  { value: "Australia/Brisbane", label: "Brisbane" },
  { value: "Pacific/Auckland", label: "Auckland" },
  { value: "Pacific/Fiji", label: "Fiji" },

  // Africa
  { value: "Africa/Cairo", label: "Cairo" },
  { value: "Africa/Lagos", label: "Lagos" },
  { value: "Africa/Nairobi", label: "Nairobi" },
  { value: "Africa/Johannesburg", label: "Johannesburg" },
  { value: "Africa/Casablanca", label: "Casablanca" },
] as const;

export type TimezoneValue = (typeof TIMEZONES)[number]["value"];

/**
 * Detect the browser's current timezone using the Intl API.
 * Returns a valid IANA timezone identifier (e.g. "America/New_York").
 */
export function detectBrowserTimezone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
  } catch {
    return "UTC";
  }
}

/**
 * Get a human-readable label for a timezone value.
 * Falls back to the raw value if not found in our list.
 */
export function getTimezoneLabel(value: string): string {
  const tz = TIMEZONES.find((t) => t.value === value);
  return tz ? tz.label : value;
}
