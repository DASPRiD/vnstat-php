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

const charts = JSON.parse(document.getElementById("chart-data").textContent);
const colors = palette();

for (const [chartName, series] of Object.entries(charts)) {
    const axis = binaryAxis(Math.max(...series.received, ...series.sent), 8);

    new Chart(document.getElementById(`${chartName}-chart`), {
        type: "bar",
        data: {
            labels: series.labels,
            datasets: [
                { label: "Received", data: series.received, backgroundColor: colors.received },
                { label: "Sent", data: series.sent, backgroundColor: colors.sent },
            ],
        },
        options: {
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: "index", intersect: false },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: colors.border },
                    ticks: { color: colors.muted, maxRotation: 0, autoSkipPadding: 12 },
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: axis.suggestedMax,
                    grid: { color: colors.border },
                    border: { display: false },
                    ticks: { color: colors.muted, callback: formatBytes, stepSize: axis.stepSize },
                },
            },
            plugins: {
                legend: { labels: { color: colors.text, boxWidth: 12, boxHeight: 12 } },
                tooltip: {
                    callbacks: {
                        label: (item) => `${item.dataset.label}: ${formatBytes(item.parsed.y)}`,
                    },
                },
            },
        },
    });
}
