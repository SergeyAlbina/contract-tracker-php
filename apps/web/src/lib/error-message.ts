const LATIN_PATTERN = /[A-Za-z]/;

interface ApiErrorPayload {
  response?: {
    data?: {
      message?: unknown;
    };
  };
}

function sanitizeMessage(raw: unknown): string | null {
  if (typeof raw !== 'string') return null;
  const text = raw.trim();
  if (!text) return null;
  if (LATIN_PATTERN.test(text)) return null;
  return text;
}

export function getUiErrorMessage(error: unknown, fallback: string): string {
  const payload = error as ApiErrorPayload;
  const raw = payload.response?.data?.message;
  const values = Array.isArray(raw) ? raw : [raw];
  const translated = values
    .map(sanitizeMessage)
    .filter((item): item is string => Boolean(item));

  return translated.length ? translated.join(', ') : fallback;
}
