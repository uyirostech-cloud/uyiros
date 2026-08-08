import type { InputHTMLAttributes, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';
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

export function Input({ label, error, hint, required, wrapperClassName, className, id, ...rest }: InputProps) {
  const inputId = id ?? rest.name;
  return (
    <Field label={label} error={error} hint={hint} required={required} htmlFor={inputId} className={wrapperClassName}>
      <input
        id={inputId}
        aria-invalid={Boolean(error)}
        className={cn('input-base', error && 'input-error', className)}
        {...rest}
      />
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
