export const env = {
  apiUrl: import.meta.env.VITE_API_URL ?? '/api',
  appName: import.meta.env.VITE_APP_NAME ?? 'ClinicFlow',
  isProduction: import.meta.env.PROD,
} as const;
