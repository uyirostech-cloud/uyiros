import { QueryClient } from '@tanstack/react-query';
import { ApiError } from '@/types/api';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: (failureCount, error) => {
        // Auth/permission/validation failures will not succeed on retry.
        if (error instanceof ApiError && [401, 403, 404, 419, 422].includes(error.status)) {
          return false;
        }
        return failureCount < 2;
      },
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: false,
    },
  },
});
