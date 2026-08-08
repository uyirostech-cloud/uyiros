import { env } from '@/config/env';
import { ApiError, type ApiSuccess, type Paginated } from '@/types/api';

/**
 * The session token lives in an HttpOnly cookie the browser attaches automatically;
 * JavaScript can neither read nor forge it. What we DO read is the non-HttpOnly CSRF
 * cookie, which must be echoed back as a header on every state-changing request.
 */
function readCsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)clinicflow_csrf=([^;]+)/);
  return match?.[1] ? decodeURIComponent(match[1]) : null;
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

interface RequestOptions {
  query?: Record<string, string | number | boolean | undefined | null>;
  signal?: AbortSignal;
}

/** Called when the API reports the session is gone, so the app can bounce to the login screen. */
let onUnauthenticated: (() => void) | null = null;
export function setUnauthenticatedHandler(handler: (() => void) | null): void {
  onUnauthenticated = handler;
}

function buildUrl(path: string, query?: RequestOptions['query']): string {
  const url = `${env.apiUrl}${path.startsWith('/') ? path : `/${path}`}`;
  if (!query) return url;
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null && value !== '') {
      params.append(key, String(value));
    }
  }
  const qs = params.toString();
  return qs ? `${url}?${qs}` : url;
}

async function request<T>(
  method: Method,
  path: string,
  body?: unknown,
  options: RequestOptions = {},
): Promise<ApiSuccess<T>> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  if (method !== 'GET') {
    const csrf = readCsrfToken();
    if (csrf) headers['X-CSRF-Token'] = csrf;
  }

  let response: Response;
  try {
    response = await fetch(buildUrl(path, options.query), {
      method,
      headers,
      credentials: 'include', // send the HttpOnly session cookie
      body: body === undefined ? undefined : JSON.stringify(body),
      signal: options.signal,
    });
  } catch (cause) {
    if ((cause as Error)?.name === 'AbortError') throw cause;
    throw new ApiError(0, 'NETWORK_ERROR', 'Could not reach the server. Check your connection.');
  }

  if (response.status === 204) {
    return { data: undefined as T };
  }

  let payload: unknown = null;
  const text = await response.text();
  if (text) {
    try {
      payload = JSON.parse(text);
    } catch {
      payload = null;
    }
  }

  if (!response.ok) {
    const error = (payload as { error?: { code?: string; message?: string; fields?: Record<string, string> } })?.error;
    if ((response.status === 401 || response.status === 419) && !path.startsWith('/auth/login')) {
      onUnauthenticated?.();
    }
    throw new ApiError(
      response.status,
      error?.code ?? 'REQUEST_FAILED',
      error?.message ?? `Request failed (${response.status})`,
      error?.fields ?? {},
    );
  }

  return payload as ApiSuccess<T>;
}

export const http = {
  async get<T>(path: string, options?: RequestOptions): Promise<T> {
    return (await request<T>('GET', path, undefined, options)).data;
  },
  async getPaginated<T>(path: string, options?: RequestOptions): Promise<Paginated<T>> {
    const result = await request<T[]>('GET', path, undefined, options);
    return {
      data: result.data,
      meta: result.meta ?? { page: 1, per_page: result.data.length, total: result.data.length, last_page: 1 },
    };
  },
  async post<T>(path: string, body?: unknown, options?: RequestOptions): Promise<T> {
    return (await request<T>('POST', path, body ?? {}, options)).data;
  },
  async put<T>(path: string, body?: unknown, options?: RequestOptions): Promise<T> {
    return (await request<T>('PUT', path, body ?? {}, options)).data;
  },
  async patch<T>(path: string, body?: unknown, options?: RequestOptions): Promise<T> {
    return (await request<T>('PATCH', path, body ?? {}, options)).data;
  },
  async delete<T>(path: string, options?: RequestOptions): Promise<T> {
    return (await request<T>('DELETE', path, undefined, options)).data;
  },
};
