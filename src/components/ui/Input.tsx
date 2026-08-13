import { useState, type InputHTMLAttributes, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';
import { cn } from './cn';

interface FieldWrapperProps {
  label?: string;
  error?: string;
  hint?: string;
  required?: boolean;
  htmlFor?: string;
  children: React.ReactNode;
  className?: string;
}

export function Field({ label, error, hint, required, htmlFor, children, className }: FieldWrapperProps) {
  return (
    <div className={className}>
      {label && (
        <label className="label-base" htmlFor={htmlFor}>
          {label}
          {required && <span className="ml-0.5 text-red-500">*</span>}
        </label>
      )}
      {children}
      {error ? (
        <p className="mt-1 text-xs text-red-600" role="alert">{error}</p>
      ) : hint ? (
        <p className="mt-1 text-xs text-ink-500">{hint}</p>
      ) : null}
    </div>
  );
}

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  hint?: string;
  wrapperClassName?: string;
}

export function Input({ label, error, hint, required, wrapperClassName, className, id, type, ...rest }: InputProps) {
  const inputId = id ?? rest.name;
  const [revealed, setRevealed] = useState(false);
  const isPassword = type === 'password';

  return (
    <Field label={label} error={error} hint={hint} required={required} htmlFor={inputId} className={wrapperClassName}>
      <div className={isPassword ? 'relative' : undefined}>
        <input
          id={inputId}
          type={isPassword ? (revealed ? 'text' : 'password') : type}
          aria-invalid={Boolean(error)}
          className={cn('input-base', isPassword && 'pr-10', error && 'input-error', className)}
          {...rest}
        />
        {isPassword && (
          <button
            type="button"
            onClick={() => setRevealed((v) => !v)}
            tabIndex={-1}
            aria-label={revealed ? 'Hide password' : 'Show password'}
            className="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-ink-400 hover:text-ink-700"
          >
            {revealed ? (
              <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4" stroke="currentColor" strokeWidth="1.8">
                <path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.4 5.5A9.6 9.6 0 0 1 12 5c5 0 9 4 10 7-.4 1.2-1.2 2.5-2.3 3.7M6.3 6.9C4.2 8.3 2.7 10.2 2 12c1 3 5 7 10 7 1.2 0 2.4-.2 3.5-.6"
                      strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            ) : (
              <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4" stroke="currentColor" strokeWidth="1.8">
                <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z" strokeLinecap="round" strokeLinejoin="round" />
                <circle cx="12" cy="12" r="3" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            )}
          </button>
        )}
      </div>
    </Field>
  );
}

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label?: string;
  error?: string;
  hint?: string;
  wrapperClassName?: string;
  options: { value: string | number; label: string }[];
  placeholder?: string;
}

export function Select({
  label, error, hint, required, wrapperClassName, className, id, options, placeholder, ...rest
}: SelectProps) {
  const inputId = id ?? rest.name;
  return (
    <Field label={label} error={error} hint={hint} required={required} htmlFor={inputId} className={wrapperClassName}>
      <select
        id={inputId}
        aria-invalid={Boolean(error)}
        className={cn('input-base', error && 'input-error', className)}
        {...rest}
      >
        {placeholder !== undefined && <option value="">{placeholder}</option>}
        {options.map((option) => (
          <option key={option.value} value={option.value}>{option.label}</option>
        ))}
      </select>
    </Field>
  );
}

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string;
  error?: string;
  hint?: string;
  wrapperClassName?: string;
}

export function Textarea({ label, error, hint, required, wrapperClassName, className, id, ...rest }: TextareaProps) {
  const inputId = id ?? rest.name;
  return (
    <Field label={label} error={error} hint={hint} required={required} htmlFor={inputId} className={wrapperClassName}>
      <textarea
        id={inputId}
        rows={rest.rows ?? 3}
        aria-invalid={Boolean(error)}
        className={cn('input-base resize-y', error && 'input-error', className)}
        {...rest}
      />
    </Field>
  );
}

export function Checkbox({
  label, className, id, ...rest
}: InputHTMLAttributes<HTMLInputElement> & { label: string }) {
  const inputId = id ?? rest.name;
  return (
    <label htmlFor={inputId} className={cn('flex cursor-pointer items-center gap-2 text-sm text-ink-800', className)}>
      <input
        id={inputId}
        type="checkbox"
        className="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500"
        {...rest}
      />
      {label}
    </label>
  );
}
