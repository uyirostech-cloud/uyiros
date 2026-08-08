/**
 * Client-side validation exists purely to give fast feedback. The server re-validates
 * everything; a rule missing here is a UX gap, never a security hole.
 */
export type FieldErrors = Record<string, string>;

export function required(value: unknown, label = 'This field'): string | null {
  if (value === null || value === undefined) return `${label} is required`;
  if (typeof value === 'string' && value.trim() === '') return `${label} is required`;
  return null;
}

export function isEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value.trim());
}

export function isPhone(value: string): boolean {
  const digits = value.replace(/\D+/g, '');
  return digits.length >= 7 && digits.length <= 15;
}

export function normalizePhone(value: string): string {
  const digits = value.replace(/\D+/g, '');
  return digits.length > 10 ? digits.slice(-10) : digits;
}

export function passwordIssues(password: string): string[] {
  const issues: string[] = [];
  if (password.length < 10) issues.push('at least 10 characters');
  if (!/[A-Za-z]/.test(password)) issues.push('a letter');
  if (!/\d/.test(password)) issues.push('a number');
  return issues;
}

export function validate(
  values: Record<string, unknown>,
  rules: Record<string, (value: unknown, all: Record<string, unknown>) => string | null>,
): FieldErrors {
  const errors: FieldErrors = {};
  for (const [field, rule] of Object.entries(rules)) {
    const message = rule(values[field], values);
    if (message) errors[field] = message;
  }
  return errors;
}
