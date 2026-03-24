# Vue Composables

This directory contains reusable Vue composables for the application.

## useDateTime

Provides utilities for formatting UTC timestamps to the user's local timezone.

### Why UTC in Backend?

The application stores all timestamps in UTC in the database for international scalability. This composable automatically converts UTC timestamps to the user's browser timezone for display.

### Usage

```typescript
import { useDateTime } from '@/composables/useDateTime';

const { formatDateTime, formatDate, formatTime, formatRelative } = useDateTime();

// Full date and time: "24 mrt 2026, 22:20"
const fullDateTime = formatDateTime('2024-03-24T21:20:00Z');

// Date only: "24 maart 2026"
const dateOnly = formatDate('2024-03-24T21:20:00Z');

// Time only: "22:20"
const timeOnly = formatTime('2024-03-24T21:20:00Z');

// Relative time: "2 uur geleden"
const relative = formatRelative('2024-03-24T20:20:00Z');
```

### Custom Formatting

All formatting functions accept optional `Intl.DateTimeFormatOptions`:

```typescript
// Custom date format
formatDate('2024-03-24T21:20:00Z', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
});
// Output: "zondag 24 maart 2026"

// Custom time format with seconds
formatTime('2024-03-24T21:20:30Z', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
});
// Output: "22:20:30"
```

### Null Safety

All functions handle `null` and `undefined` gracefully:

```typescript
formatDateTime(null); // Returns: "-"
formatDate(undefined); // Returns: "-"
formatRelative('invalid-date'); // Returns: "-"
```

### Locale

The composable automatically uses the browser's locale (e.g., `nl-NL` for Dutch users, `en-US` for English users). This ensures dates are formatted according to the user's preferences.

## Other Composables

- `useAppearance` - Manage light/dark theme
- `useCurrentUrl` - URL matching and navigation helpers
- `useInitials` - Generate user initials from name
- `useTwoFactorAuth` - Two-factor authentication utilities
