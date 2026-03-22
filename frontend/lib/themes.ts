/**
 * Theme registry — defines all available color themes.
 *
 * Each theme is a CSS file in `frontend/styles/themes/<id>.css` that sets
 * CSS custom properties under `[data-theme="<id>"]` and `[data-theme="<id>"].dark`.
 *
 * To add a new theme, see: docs/ai/recipes/add-theme.md
 */

export interface ThemePreviewColors {
  primary: string;
  secondary: string;
  background: string;
  foreground: string;
}

export interface ThemeDefinition {
  /** Unique ID — matches the CSS `[data-theme="<id>"]` selector and CSS filename */
  id: string;
  /** Display name shown in the theme picker */
  name: string;
  /** Short description for the picker tooltip */
  description: string;
  /** Hex preview colors for the theme picker swatches */
  preview: {
    light: ThemePreviewColors;
    dark: ThemePreviewColors;
  };
}

/** All registered themes. Order determines display order in the picker. */
export const themes: ThemeDefinition[] = [
  {
    id: "default",
    name: "DanaVision",
    description: "Purple and gold on warm cream",

    preview: {
      light: {
        primary: "#6B4EAB",
        secondary: "#E8DFF2",
        background: "#FAF5F0",
        foreground: "#3D2E5C",
      },
      dark: {
        primary: "#A78BCA",
        secondary: "#1E1530",
        background: "#0F0A1A",
        foreground: "#EDE6F5",
      },
    },
  },
  {
    id: "bubblegum",
    name: "Bubblegum",
    description: "Hot pink on cream — playful and loud",

    preview: {
      light: {
        primary: "#D40A72",
        secondary: "#EDD4DF",
        background: "#FAF3F5",
        foreground: "#2B0814",
      },
      dark: {
        primary: "#E854A8",
        secondary: "#3d0f2a",
        background: "#1a0812",
        foreground: "#fce4f0",
      },
    },
  },
  {
    id: "cyberpunk",
    name: "Cyberpunk",
    description: "Neon cyan on deep black — electric and futuristic",

    preview: {
      light: {
        primary: "#009AB8",
        secondary: "#DBE9EC",
        background: "#F4F8F9",
        foreground: "#031718",
      },
      dark: {
        primary: "#00e5ff",
        secondary: "#0a2a30",
        background: "#030d10",
        foreground: "#d0f8ff",
      },
    },
  },
  {
    id: "dracula",
    name: "Dracula",
    description: "Purple and green on charcoal — the iconic dev theme",

    preview: {
      light: {
        primary: "#8A3EF2",
        secondary: "#E4DDE9",
        background: "#F6F5F9",
        foreground: "#171A24",
      },
      dark: {
        primary: "#bd93f9",
        secondary: "#2d2640",
        background: "#282a36",
        foreground: "#f8f8f2",
      },
    },
  },
  {
    id: "forest",
    name: "Forest",
    description: "Deep emerald on parchment — earthy and grounded",

    preview: {
      light: {
        primary: "#126E2C",
        secondary: "#DBE6DB",
        background: "#F6F7F3",
        foreground: "#0A1E0A",
      },
      dark: {
        primary: "#34d399",
        secondary: "#14332a",
        background: "#0a1f14",
        foreground: "#d1fae5",
      },
    },
  },
  {
    id: "sunset",
    name: "Sunset",
    description: "Burnt orange on warm ivory — cozy and bold",

    preview: {
      light: {
        primary: "#C44D08",
        secondary: "#EBE2D6",
        background: "#FBF8F3",
        foreground: "#271009",
      },
      dark: {
        primary: "#F08530",
        secondary: "#3b1a08",
        background: "#1a0e04",
        foreground: "#fff1e6",
      },
    },
  },
  {
    id: "ocean",
    name: "Ocean",
    description: "Deep navy and bright blue — nautical and crisp",

    preview: {
      light: {
        primary: "#1549D9",
        secondary: "#DDE4EA",
        background: "#F4F6F8",
        foreground: "#0C1428",
      },
      dark: {
        primary: "#60a5fa",
        secondary: "#172554",
        background: "#050e1f",
        foreground: "#dbeafe",
      },
    },
  },
  {
    id: "rose",
    name: "Rose",
    description: "Deep crimson on blush — romantic and refined",

    preview: {
      light: {
        primary: "#AF1535",
        secondary: "#EDD3D6",
        background: "#FAF3F5",
        foreground: "#250E12",
      },
      dark: {
        primary: "#fb7185",
        secondary: "#4c0519",
        background: "#1a0508",
        foreground: "#ffe4e6",
      },
    },
  },
  {
    id: "lavender",
    name: "Lavender",
    description: "Rich purple on soft lilac — dreamy and elegant",

    preview: {
      light: {
        primary: "#6528D2",
        secondary: "#E4DDEB",
        background: "#F7F4FA",
        foreground: "#1A0E24",
      },
      dark: {
        primary: "#a78bfa",
        secondary: "#2e1065",
        background: "#0f0720",
        foreground: "#ede9fe",
      },
    },
  },
  {
    id: "midnight",
    name: "Midnight",
    description: "Electric indigo on pitch black — high contrast hacker",

    preview: {
      light: {
        primary: "#0B1CB4",
        secondary: "#DDE0EA",
        background: "#F4F4F9",
        foreground: "#0C0F28",
      },
      dark: {
        primary: "#8DA4F8",
        secondary: "#1e1b4b",
        background: "#050414",
        foreground: "#e0e7ff",
      },
    },
  },
  {
    id: "mono",
    name: "Mono",
    description: "Pure black and white — stark, typographic, no nonsense",

    preview: {
      light: {
        primary: "#141414",
        secondary: "#EBEBEB",
        background: "#FAFAFA",
        foreground: "#0A0A0A",
      },
      dark: {
        primary: "#ffffff",
        secondary: "#1F1F1F",
        background: "#000000",
        foreground: "#ffffff",
      },
    },
  },
  {
    id: "coffee",
    name: "Coffee",
    description: "Rich brown on warm cream — artisan cafe vibes",

    preview: {
      light: {
        primary: "#814018",
        secondary: "#E7DFD1",
        background: "#F9F6EF",
        foreground: "#271208",
      },
      dark: {
        primary: "#D4A06A",
        secondary: "#3d2815",
        background: "#1a0f08",
        foreground: "#f5e6d3",
      },
    },
  },
  {
    id: "catppuccin",
    name: "Catppuccin",
    description: "Warm pastels on creamy bases — the beloved dev palette",

    preview: {
      light: {
        primary: "#8839ef",
        secondary: "#dbd5e6",
        background: "#eff1f5",
        foreground: "#4c4f69",
      },
      dark: {
        primary: "#cba6f7",
        secondary: "#313244",
        background: "#1e1e2e",
        foreground: "#c6d0f5",
      },
    },
  },
  {
    id: "nord",
    name: "Nord",
    description: "Arctic cool blue-gray — Nordic polar frost",

    preview: {
      light: {
        primary: "#5e81ac",
        secondary: "#d8dee9",
        background: "#eceff4",
        foreground: "#2e3440",
      },
      dark: {
        primary: "#5e81ac",
        secondary: "#3b4252",
        background: "#2e3440",
        foreground: "#d8dee9",
      },
    },
  },
  {
    id: "solarized",
    name: "Solarized",
    description: "Precision-engineered palette with signature yellow accent",

    preview: {
      light: {
        primary: "#b58900",
        secondary: "#ddd0a6",
        background: "#fdf6e3",
        foreground: "#073642",
      },
      dark: {
        primary: "#b58900",
        secondary: "#194854",
        background: "#073642",
        foreground: "#fdf6e3",
      },
    },
  },
  {
    id: "sakura",
    name: "Sakura",
    description: "Soft cherry blossom pinks — serene and elegant",

    preview: {
      light: {
        primary: "#c03b6b",
        secondary: "#e8d2da",
        background: "#f7eff2",
        foreground: "#382028",
      },
      dark: {
        primary: "#d97da3",
        secondary: "#32222a",
        background: "#231820",
        foreground: "#e6d0da",
      },
    },
  },
  {
    id: "amber",
    name: "Amber",
    description: "Rich golden amber — luxury editorial warmth",

    preview: {
      light: {
        primary: "#d97706",
        secondary: "#e4d6c0",
        background: "#f5f0e8",
        foreground: "#2b2114",
      },
      dark: {
        primary: "#e5a220",
        secondary: "#2e2418",
        background: "#201a10",
        foreground: "#e8dbc6",
      },
    },
  },
  {
    id: "slate",
    name: "Slate",
    description: "Cool blue-gray professional — modern SaaS refined",

    preview: {
      light: {
        primary: "#2563a8",
        secondary: "#e1e5eb",
        background: "#f5f7fa",
        foreground: "#1e293b",
      },
      dark: {
        primary: "#5b8fd4",
        secondary: "#262f3d",
        background: "#171d28",
        foreground: "#e2e8f0",
      },
    },
  },
];

/** Look up a theme by ID. Returns undefined if not found. */
export function getThemeById(id: string): ThemeDefinition | undefined {
  return themes.find((t) => t.id === id);
}

/** The default theme ID used when no theme is selected. */
export const DEFAULT_THEME_ID = "default";

