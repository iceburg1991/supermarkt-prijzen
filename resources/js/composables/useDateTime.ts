/**
 * DateTime formatting composable for converting UTC timestamps to local time.
 *
 * This composable provides utilities for formatting dates and times in the user's
 * local timezone. All timestamps from the backend are stored in UTC and converted
 * to the user's browser timezone automatically.
 *
 * @example
 * const { formatDateTime, formatDate, formatTime, formatRelative } = useDateTime();
 *
 * // Full date and time: "24 mrt 2026, 22:20"
 * formatDateTime('2024-03-24T21:20:00Z');
 *
 * // Date only: "24 maart 2026"
 * formatDate('2024-03-24T21:20:00Z');
 *
 * // Time only: "22:20"
 * formatTime('2024-03-24T21:20:00Z');
 *
 * // Relative time: "2 uur geleden"
 * formatRelative('2024-03-24T20:20:00Z');
 */

export type UseDateTimeReturn = {
    formatDateTime: (date: string | Date | null | undefined, options?: Intl.DateTimeFormatOptions) => string;
    formatDate: (date: string | Date | null | undefined, options?: Intl.DateTimeFormatOptions) => string;
    formatTime: (date: string | Date | null | undefined, options?: Intl.DateTimeFormatOptions) => string;
    formatRelative: (date: string | Date | null | undefined) => string;
    parseDate: (date: string | Date | null | undefined) => Date | null;
};

/**
 * Default locale for date formatting.
 * Uses browser locale or falls back to Dutch.
 */
const DEFAULT_LOCALE = typeof navigator !== 'undefined' 
    ? navigator.language 
    : 'nl-NL';

/**
 * Parse a date string or Date object into a Date instance.
 */
function parseDate(date: string | Date | null | undefined): Date | null {
    if (!date) {
        return null;
    }

    if (date instanceof Date) {
        return date;
    }

    try {
        const parsed = new Date(date);
        return isNaN(parsed.getTime()) ? null : parsed;
    } catch {
        return null;
    }
}

/**
 * Format a date and time in the user's local timezone.
 *
 * @param date - UTC timestamp string or Date object
 * @param options - Intl.DateTimeFormat options
 * @returns Formatted date and time string
 */
function formatDateTime(
    date: string | Date | null | undefined,
    options?: Intl.DateTimeFormatOptions,
): string {
    const parsed = parseDate(date);
    if (!parsed) {
        return '-';
    }

    const defaultOptions: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        ...options,
    };

    return new Intl.DateTimeFormat(DEFAULT_LOCALE, defaultOptions).format(parsed);
}

/**
 * Format a date (without time) in the user's local timezone.
 *
 * @param date - UTC timestamp string or Date object
 * @param options - Intl.DateTimeFormat options
 * @returns Formatted date string
 */
function formatDate(
    date: string | Date | null | undefined,
    options?: Intl.DateTimeFormatOptions,
): string {
    const parsed = parseDate(date);
    if (!parsed) {
        return '-';
    }

    const defaultOptions: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        ...options,
    };

    return new Intl.DateTimeFormat(DEFAULT_LOCALE, defaultOptions).format(parsed);
}

/**
 * Format a time (without date) in the user's local timezone.
 *
 * @param date - UTC timestamp string or Date object
 * @param options - Intl.DateTimeFormat options
 * @returns Formatted time string
 */
function formatTime(
    date: string | Date | null | undefined,
    options?: Intl.DateTimeFormatOptions,
): string {
    const parsed = parseDate(date);
    if (!parsed) {
        return '-';
    }

    const defaultOptions: Intl.DateTimeFormatOptions = {
        hour: '2-digit',
        minute: '2-digit',
        ...options,
    };

    return new Intl.DateTimeFormat(DEFAULT_LOCALE, defaultOptions).format(parsed);
}

/**
 * Format a date as relative time (e.g., "2 hours ago", "in 3 days").
 *
 * @param date - UTC timestamp string or Date object
 * @returns Relative time string
 */
function formatRelative(date: string | Date | null | undefined): string {
    const parsed = parseDate(date);
    if (!parsed) {
        return '-';
    }

    const now = new Date();
    const diffMs = now.getTime() - parsed.getTime();
    const diffSeconds = Math.floor(diffMs / 1000);
    const diffMinutes = Math.floor(diffSeconds / 60);
    const diffHours = Math.floor(diffMinutes / 60);
    const diffDays = Math.floor(diffHours / 24);

    // Use Intl.RelativeTimeFormat for proper localization
    const rtf = new Intl.RelativeTimeFormat(DEFAULT_LOCALE, { numeric: 'auto' });

    if (Math.abs(diffSeconds) < 60) {
        return rtf.format(-diffSeconds, 'second');
    } else if (Math.abs(diffMinutes) < 60) {
        return rtf.format(-diffMinutes, 'minute');
    } else if (Math.abs(diffHours) < 24) {
        return rtf.format(-diffHours, 'hour');
    } else if (Math.abs(diffDays) < 30) {
        return rtf.format(-diffDays, 'day');
    } else {
        // For dates older than 30 days, show the actual date
        return formatDate(parsed);
    }
}

/**
 * DateTime formatting composable.
 *
 * Provides utilities for formatting UTC timestamps to the user's local timezone.
 * All functions handle null/undefined gracefully and return '-' for invalid dates.
 */
export function useDateTime(): UseDateTimeReturn {
    return {
        formatDateTime,
        formatDate,
        formatTime,
        formatRelative,
        parseDate,
    };
}
