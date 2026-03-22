/**
 * Format a unit price and type into a human-readable string.
 * e.g. formatUnitPrice(3.99, 'lb') → "$3.99/lb"
 */
export function formatUnitPrice(
  unitPrice: number | null | undefined,
  unitType: string | null | undefined
): string | null {
  if (unitPrice == null || unitType == null) return null;
  return `$${unitPrice.toFixed(2)}/${unitType}`;
}

/**
 * Format a regular price into a display string.
 * e.g. formatPrice(3.99) → "$3.99"
 */
export function formatPrice(price: number | null | undefined): string {
  if (price == null) return "—";
  return `$${price.toFixed(2)}`;
}
