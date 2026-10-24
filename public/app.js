const BYTE_UNITS = ["B", "KiB", "MiB", "GiB", "TiB"];
const BITRATE_UNITS = ["bit/s", "kbit/s", "Mbit/s", "Gbit/s", "Tbit/s"];

const decimalsFor = (value) => (value >= 100 ? 0 : value >= 10 ? 1 : 2);

const toThreeFigures = (value) => {
    const rounded = Number(value.toFixed(decimalsFor(value)));

    return rounded.toFixed(decimalsFor(rounded));
};

/**
 * Mirrors Vnstat\Formatter::bytes for the chart axis.
 *
 * Chart.js picks its own tick values, so they cannot be formatted on the
 * server. Any change to the band rule has to be made in both places.
 */
const formatBytes = (bytes) => {
    const unitIndex = Math.max(
        0,
        Math.min(
            Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(BINARY_BASE)),
            BYTE_UNITS.length - 1,
        ),
    );

    if (unitIndex === 0) {
        return `${Math.round(bytes)} B`;
    }

    return `${toThreeFigures(bytes / Math.pow(BINARY_BASE, unitIndex))} ${BYTE_UNITS[unitIndex]}`;
};

/**
 * Mirrors Vnstat\Formatter::bitrate, which uses SI decimal prefixes because
 * that is what vnStat itself reports rates in.
 */
const formatBitrate = (bitsPerSecond) => {
    const unitIndex = Math.max(
        0,
        Math.min(
            Math.floor((bitsPerSecond ? Math.log(bitsPerSecond) : 0) / Math.log(DECIMAL_BASE)),
            BITRATE_UNITS.length - 1,
        ),
    );

    const scaled = bitsPerSecond / Math.pow(DECIMAL_BASE, unitIndex);

    return `${toThreeFigures(scaled)} ${BITRATE_UNITS[unitIndex]}`;
};

const STEP_MULTIPLES = [0.25, 0.5, 1, 2, 2.5, 5, 10, 20, 25, 50, 100, 200, 250, 500];
const BINARY_BASE = 1024;
const DECIMAL_BASE = 1000;

// Both unit lists stop at tera, and the axis helper is shared between them.
const AXIS_UNIT_COUNT = 5;

const unitFor = (value, base) =>
    Math.pow(base, Math.min(Math.floor(Math.log(value) / Math.log(base)), AXIS_UNIT_COUNT - 1));

const stepWithin = (max, unit, targetTicks) => {
    const roughStep = max / targetTicks / unit;

    return (STEP_MULTIPLES.find((candidate) => candidate >= roughStep) ?? STEP_MULTIPLES.at(-1)) * unit;
};

/**
 * Picks a y axis step that is round in the unit the labels use.
 *
 * Left alone, Chart.js chooses steps that are round in the underlying count,
 * so an axis in mebibytes reads 9.54, 19.1, 28.6 rather than 10, 20, 30.
 */
const niceAxis = (max, targetTicks, base) => {
    // Chart.js turns an all zero series into an axis of 0 to 1, which these
    // formatters then label with the same value five times and the next one
    // six times.
    if (max <= 0) {
        return { stepSize: base / 4, suggestedMax: base };
    }

    let unit = unitFor(max, base);
    let stepSize = stepWithin(max, unit, targetTicks);

    // Rounding the top tick up can carry it past the unit, where it is
    // relabelled in the next one and stops being round.
    const largestUnit = Math.pow(base, AXIS_UNIT_COUNT - 1);

    if (Math.ceil(max / stepSize) * stepSize >= base * unit && unit < largestUnit) {
        unit *= base;
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
    chart.data.datasets[0].borderColor = colors.received;
    chart.data.datasets[1].backgroundColor = colors.sent;
    chart.data.datasets[1].borderColor = colors.sent;
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
    const axis = niceAxis(Math.max(...points.received, ...points.sent), 8, BINARY_BASE);

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

const LIVE_INTERVAL = 2000;
const LIVE_POINTS = 60;
const LIVE_MAX_GAP_SECONDS = (LIVE_INTERVAL / 1000) * 3;

const liveChart = document.getElementById("live-chart");

if (liveChart) {
    const receivedOutput = document.getElementById("live-received");
    const sentOutput = document.getElementById("live-sent");
    const statusOutput = document.getElementById("live-status");
    const liveSeries = { labels: [], received: [], sent: [] };
    const emptyAxis = niceAxis(0, 5, DECIMAL_BASE);
    let previous = null;

    const chart = new Chart(liveChart, {
        type: "line",
        data: {
            labels: liveSeries.labels,
            datasets: [
                {
                    label: "Received",
                    data: liveSeries.received,
                    borderColor: initial.received,
                    backgroundColor: initial.received,
                },
                {
                    label: "Sent",
                    data: liveSeries.sent,
                    borderColor: initial.sent,
                    backgroundColor: initial.sent,
                },
            ],
        },
        options: {
            maintainAspectRatio: false,
            animation: false,
            pointRadius: 0,
            borderWidth: 2,
            tension: 0.25,
            interaction: { mode: "index", intersect: false },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: initial.border },
                    ticks: { color: initial.muted, maxRotation: 0, autoSkipPadding: 24 },
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: emptyAxis.suggestedMax,
                    grid: { color: initial.border },
                    border: { display: false },
                    ticks: {
                        color: initial.muted,
                        callback: formatBitrate,
                        stepSize: emptyAxis.stepSize,
                    },
                },
            },
            plugins: {
                legend: { labels: { color: initial.text, boxWidth: 12, boxHeight: 12 } },
                tooltip: {
                    callbacks: {
                        label: (item) => `${item.dataset.label}: ${formatBitrate(item.parsed.y)}`,
                    },
                },
            },
        },
    });

    charts.push(chart);

    const record = (sample) => {
        const elapsed = sample.at - previous.at;

        // A restarted interface starts counting from zero again, which would
        // otherwise read as a large negative rate.
        const forwards = sample.received >= previous.received && sample.sent >= previous.sent;

        // Browsers throttle timers in a background tab and this skips the
        // poll outright while one is hidden, so a wide gap is routine. The
        // mean across it is not comparable to the points either side, and
        // plotting it would also rescale the axis. The caller still takes
        // this sample as the new baseline.
        if (elapsed <= 0 || elapsed > LIVE_MAX_GAP_SECONDS || !forwards) {
            return;
        }

        liveSeries.labels.push(new Date(sample.at * 1000).toLocaleTimeString("en-GB"));
        liveSeries.received.push(((sample.received - previous.received) * 8) / elapsed);
        liveSeries.sent.push(((sample.sent - previous.sent) * 8) / elapsed);

        while (liveSeries.labels.length > LIVE_POINTS) {
            liveSeries.labels.shift();
            liveSeries.received.shift();
            liveSeries.sent.shift();
        }

        receivedOutput.textContent = formatBitrate(liveSeries.received.at(-1));
        sentOutput.textContent = formatBitrate(liveSeries.sent.at(-1));
        const axis = niceAxis(Math.max(...liveSeries.received, ...liveSeries.sent), 5, DECIMAL_BASE);

        chart.options.scales.y.suggestedMax = axis.suggestedMax;
        chart.options.scales.y.ticks.stepSize = axis.stepSize;
        chart.update();
    };

    let polling = false;

    const poll = async () => {
        // Responses can resolve out of order, which would walk previous
        // backwards and label a point with an earlier time than the one
        // before it.
        if (document.hidden || polling) {
            return;
        }

        polling = true;

        try {
            const query = encodeURIComponent(liveChart.dataset.interface);
            const response = await fetch(`live.php?interface=${query}`, { cache: "no-store" });
            const sample = await response.json();

            if (!response.ok) {
                statusOutput.textContent = sample.error ?? "No rate available.";

                return;
            }

            statusOutput.textContent = "";

            if (previous) {
                record(sample);
            }

            previous = sample;
        } catch {
            statusOutput.textContent = "No readable reply from the server.";
        } finally {
            polling = false;
        }
    };

    poll();
    setInterval(poll, LIVE_INTERVAL);
}
