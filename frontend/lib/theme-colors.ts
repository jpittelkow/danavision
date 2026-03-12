/**
 * Convert hex color to HSL values for CSS variables
 */
export function hexToHSL(hex: string): { h: number; s: number; l: number } {
  // Remove # if present
  const cleanHex = hex.replace('#', '');
  
  // Parse RGB
  const r = parseInt(cleanHex.substring(0, 2), 16) / 255;
  const g = parseInt(cleanHex.substring(2, 4), 16) / 255;
  const b = parseInt(cleanHex.substring(4, 6), 16) / 255;

  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  let h = 0;
  let s = 0;
  const l = (max + min) / 2;

  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

    switch (max) {
      case r:
        h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
        break;
      case g:
        h = ((b - r) / d + 2) / 6;
        break;
      case b:
        h = ((r - g) / d + 4) / 6;
        break;
    }
  }

  return {
    h: Math.round(h * 360),
    s: Math.round(s * 100),
    l: Math.round(l * 100),
  };
}

/**
 * Calculate appropriate foreground color (white or black) based on background brightness
 */
function getForegroundColor(hex: string): string {
  const cleanHex = hex.replace('#', '');
  const r = parseInt(cleanHex.substring(0, 2), 16);
  const g = parseInt(cleanHex.substring(2, 4), 16);
  const b = parseInt(cleanHex.substring(4, 6), 16);
  
  // Calculate relative luminance
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  
  // Use white text for dark backgrounds, black for light
  return luminance > 0.5 ? '0 0% 0%' : '0 0% 100%';
}

/**
 * Apply theme colors to CSS variables
 */
export function applyThemeColors(primary?: string, secondary?: string) {
  if (primary) {
    const hsl = hexToHSL(primary);
    document.documentElement.style.setProperty('--primary', `${hsl.h} ${hsl.s}% ${hsl.l}%`);
    const fg = getForegroundColor(primary);
    document.documentElement.style.setProperty('--primary-foreground', fg);
  }
  
  if (secondary) {
    const hsl = hexToHSL(secondary);
    document.documentElement.style.setProperty('--secondary', `${hsl.h} ${hsl.s}% ${hsl.l}%`);
    const fg = getForegroundColor(secondary);
    document.documentElement.style.setProperty('--secondary-foreground', fg);
  }
}
