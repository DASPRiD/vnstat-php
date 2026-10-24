const BYTE_UNITS = ["B", "KiB", "MiB", "GiB", "TiB"];

/**
 * Mirrors Vnstat\Formatter::bytes for the chart axis.
 *
 * Chart.js picks its own tick values, so they cannot be formatted on the
 * server. Any change to the band rule has to be made in both places.
 */
const formatBytes = (bytes) => {
    const unitIndex = Math.max(
        0,
        Math.min(Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024)), BYTE_UNITS.length - 1),
    );

    if (unitIndex === 0) {
        return `${Math.round(bytes)} B`;
    }

    const decimals = (value) => (value >= 100 ? 0 : value >= 10 ? 1 : 2);
    const scaled = bytes / Math.pow(1024, unitIndex);
    const rounded = Number(scaled.toFixed(decimals(scaled)));

    return `${rounded.toFixed(decimals(rounded))} ${BYTE_UNITS[unitIndex]}`;
};

const STEP_MULTIPLES = [0.25, 0.5, 1, 2, 2.5, 5, 10, 20, 25, 50, 100, 200, 250, 500];
const LARGEST_UNIT = Math.pow(1024, BYTE_UNITS.length - 1);
const EMPTY_AXIS_MAX = 1024;

const unitFor = (value) =>
    Math.pow(1024, Math.min(Math.floor(Math.log(value) / Math.log(1024)), BYTE_UNITS.length - 1));

const stepWithin = (max, unit, targetTicks) => {
    const roughStep = max / targetTicks / unit;

    return (STEP_MULTIPLES.find((candidate) => candidate >= roughStep) ?? STEP_MULTIPLES.at(-1)) * unit;
};

/**
 * Picks a y axis step that is round in the unit the labels use.
 *
 * Left alone, Chart.js chooses steps that are round in bytes, so an axis in
 * mebibytes reads 9.54, 19.1, 28.6 rather than 10, 20, 30.
 */
const binaryAxis = (max, targetTicks) => {
    // Chart.js turns an all zero series into an axis of 0 to 1, which this
    // formatter then labels "0 B" five times and "1 B" six times.
    if (max <= 0) {
        return { stepSize: EMPTY_AXIS_MAX / 4, suggestedMax: EMPTY_AXIS_MAX };
    }

    let unit = unitFor(max);
    let stepSize = stepWithin(max, unit, targetTicks);

    // Rounding the top tick up can carry it past the unit, where it is
    // relabelled in the next one and stops being round.
    if (Math.ceil(max / stepSize) * stepSize >= 1024 * unit && unit < LARGEST_UNIT) {
        unit *= 1024;
        stepSize = stepWithin(max, unit, targetTicks);
    }

    return { stepSize, suggestedMax: undefined };
};

/**
 * Resolves the theme colors to values a canvas can use.
 *
 * light-dark() is only resolved once a property consumes it, so reading the
 * custom property itself hands back the unresolved function call.
 */
const palette = () => {
    const probe = document.createElement("span");
    probe.style.display = "none";
    document.body.append(probe);

    const readColor = (name) => {
        probe.style.color = `var(${name})`;

        return getComputedStyle(probe).color;
    };

    const colors = {
        received: readColor("--received"),
        sent: readColor("--sent"),
        text: readColor("--text"),
        muted: readColor("--text-muted"),
        border: readColor("--border"),
    };

    probe.remove();

    return colors;
};

const THEME_KEY = "vnstat-php-theme";
const THEMES = ["auto", "light", "dark"];

const storedTheme = () => {
    try {
        const theme = localStorage.getItem(THEME_KEY);

        return THEMES.includes(theme) ? theme : "auto";
    } catch {
        return "auto";
    }
};

const applyTheme = (theme) => {
    if (theme === "auto") {
        document.documentElement.style.removeProperty("color-scheme");
    } else {
        document.documentElement.style.colorScheme = theme;
    }

    try {
        if (theme === "auto") {
            localStorage.removeItem(THEME_KEY);
        } else {
            localStorage.setItem(THEME_KEY, theme);
        }
    } catch {
        // The theme still applies for this page view.
    }
};

const colorize = (chart, colors) => {
    chart.data.datasets[0].backgroundColor = colors.received;
    chart.data.datasets[1].backgroundColor = colors.sent;
    chart.options.scales.x.border.color = colors.border;
    chart.options.scales.x.ticks.color = colors.muted;
    chart.options.scales.y.grid.color = colors.border;
    chart.options.scales.y.ticks.color = colors.muted;
    chart.options.plugins.legend.labels.color = colors.text;
};

const series = JSON.parse(document.getElementById("chart-data").textContent);

// The script in the head applies this too, but only to stop a flash. Doing
// it here as well is what keeps the page honest when that script is blocked,
// by a content security policy without unsafe-inline for instance.
const theme = storedTheme();
applyTheme(theme);

const initial = palette();

const charts = Object.entries(series).map(([chartName, points]) => {
    const axis = binaryAxis(Math.max(...points.received, ...points.sent), 8);

    return new Chart(document.getElementById(`${chartName}-chart`), {
        type: "bar",
        data: {
            labels: points.labels,
            datasets: [
                { label: "Received", data: points.received, backgroundColor: initial.received },
                { label: "Sent", data: points.sent, backgroundColor: initial.sent },
            ],
        },
        options: {
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: "index", intersect: false },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: initial.border },
                    ticks: { color: initial.muted, maxRotation: 0, autoSkipPadding: 12 },
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: axis.suggestedMax,
                    grid: { color: initial.border },
                    border: { display: false },
                    ticks: { color: initial.muted, callback: formatBytes, stepSize: axis.stepSize },
                },
            },
            plugins: {
                legend: { labels: { color: initial.text, boxWidth: 12, boxHeight: 12 } },
                tooltip: {
                    callbacks: {
                        label: (item) => `${item.dataset.label}: ${formatBytes(item.parsed.y)}`,
                    },
                },
            },
        },
    });
});

const repaint = () => {
    const colors = palette();

    for (const chart of charts) {
        colorize(chart, colors);
        chart.update();
    }
};

const themeSelect = document.getElementById("theme");
themeSelect.value = theme;
themeSelect.addEventListener("change", () => {
    applyTheme(themeSelect.value);
    repaint();
});

// The system setting can change while the page is open.
window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => {
    if (storedTheme() === "auto") {
        repaint();
    }
});
