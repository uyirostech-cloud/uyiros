/** Minimal class-name joiner — no dependency needed for this. */
export function cn(...values: (string | false | null | undefined)[]): string {
  return values.filter(Boolean).join(' ');
}
