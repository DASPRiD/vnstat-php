#!/bin/bash
# Generates a vnStat JSON fixture from a real vnstat binary against a
# synthetic database. Run inside a Debian container with vnstat installed:
# bullseye gives 2.6, trixie gives 2.13.
#
#   generate-fixture.sh > tests/fixtures/vnstat-<version>.json
#
# Writes the JSON to stdout and the vnStat version to stderr. Name the file
# after that version, because a distribution point release can bump vnstat
# and silently change what this produces.
#
# Row ids are deliberately high so that fixtures reproduce the "id is a
# rowid, not an hour or a rank" shape of a long-running install.
#
# vnStat stores dates as UTC and renders them in local time, so the run is
# pinned to UTC to keep the output reproducible.
set -euo pipefail

export TZ=UTC

DB=/var/lib/vnstat/vnstat.db
IFACE=eth0

rm -f "$DB"
# vnStat gained --initdb in 2.7; before that only the daemon creates the file.
vnstatd --initdb >/dev/null 2>&1 || timeout 5 vnstatd -n --noadd >/dev/null 2>&1 || true
[ -f "$DB" ] || { echo "could not create $DB" >&2; exit 1; }

# Anchor every entry to a fixed moment so fixtures are reproducible.
# 2026-03-15 is clear of both DST transitions in Europe and North America.
ANCHOR="2026-03-15 14:00:00"
UPDATED="2026-03-15 14:37:00"
CREATED="2025-09-02 08:14:00"

sqlite3 "$DB" <<SQL
DELETE FROM interface;

INSERT INTO interface (id, name, alias, active, created, updated,
                       rxcounter, txcounter, rxtotal, txtotal)
VALUES (1, '$IFACE', 'Uplink', 1, '$CREATED', '$UPDATED',
        0, 0, 4294967296000, 1073741824000);

-- 24 hourly entries ending at the anchor hour. Row ids start at 5001 so the
-- id can never be mistaken for an hour of the day.
WITH RECURSIVE seq(n) AS (SELECT 0 UNION ALL SELECT n+1 FROM seq WHERE n < 23)
INSERT INTO hour (id, interface, date, rx, tx)
SELECT 5001 + n,
       1,
       datetime('$ANCHOR', '-' || (23 - n) || ' hours'),
       1048576 * (12 + (n * 7) % 53),
       1048576 * (3 + (n * 5) % 19)
FROM seq;

WITH RECURSIVE seq(n) AS (SELECT 0 UNION ALL SELECT n+1 FROM seq WHERE n < 29)
INSERT INTO day (id, interface, date, rx, tx)
SELECT 900 + n,
       1,
       date('$ANCHOR', '-' || (29 - n) || ' days'),
       1048576 * (400 + (n * 131) % 2200),
       1048576 * (90 + (n * 57) % 480)
FROM seq;

WITH RECURSIVE seq(n) AS (SELECT 0 UNION ALL SELECT n+1 FROM seq WHERE n < 5)
INSERT INTO month (id, interface, date, rx, tx)
SELECT 70 + n,
       1,
       date('$ANCHOR', 'start of month', '-' || (5 - n) || ' months'),
       1048576 * (18000 + (n * 4100) % 31000),
       1048576 * (4200 + (n * 900) % 7000)
FROM seq;

INSERT INTO year (id, interface, date, rx, tx) VALUES
  (11, 1, '2025-01-01', 1048576 * 183000, 1048576 * 41000),
  (12, 1, '2026-01-01', 1048576 * 96000,  1048576 * 23000);

-- Top 10 days, descending by total. Row ids start at 801: if the frontend
-- prints id + 1 as the rank it will show 802..811 instead of 1..10.
WITH RECURSIVE seq(n) AS (SELECT 0 UNION ALL SELECT n+1 FROM seq WHERE n < 9)
INSERT INTO top (id, interface, date, rx, tx)
SELECT 801 + n,
       1,
       date('$ANCHOR', '-' || (3 + n * 11) || ' days'),
       1048576 * (2600 - n * 180),
       1048576 * (560 - n * 37)
FROM seq;

-- vnstatd writes five minute rows continuously, so a gap between the newest
-- one and "updated" would not occur in practice.
WITH RECURSIVE seq(n) AS (SELECT 0 UNION ALL SELECT n+1 FROM seq WHERE n < 35)
INSERT INTO fiveminute (id, interface, date, rx, tx)
SELECT 60001 + n,
       1,
       datetime('$UPDATED', '-' || (strftime('%M', '$UPDATED') % 5) || ' minutes',
                            '-' || ((35 - n) * 5) || ' minutes'),
       1048576 * (2 + (n * 3) % 11),
       1048576 * (1 + (n * 2) % 5)
FROM seq;
SQL

vnstat --version | head -1 >&2
vnstat --json -i "$IFACE"
