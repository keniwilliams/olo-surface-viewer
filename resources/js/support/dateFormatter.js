const dateTimeFormatter = new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export function formatDateTime(value, fallback = 'unknown') {
    if (!value) {
        return fallback;
    }

    const date = new Date(normaliseDateValue(String(value)));

    if (Number.isNaN(date.getTime())) {
        return fallback;
    }

    return dateTimeFormatter.format(date);
}

function normaliseDateValue(value) {
    return value
        .trim()
        .replace(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})/, '$1T$2')
        .replace(/\.(\d{3})\d+/, '.$1')
        .replace(/([+-]\d{2})$/, '$1:00');
}
