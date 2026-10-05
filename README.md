# FujiNet BBS Directory

A self-contained WordPress plugin for managing BBS destinations and serving
compact plain-text lists to FujiNet terminal clients. The public lists contain
only published BBS records marked **active**.

## Installation

Copy this directory to `wp-content/plugins/fujinet-bbs-directory/` and activate
**FujiNet BBS Directory** in WordPress. Use pretty permalinks so the exact
`/bbs/list/...` routes reach WordPress. Activation creates the custom post type,
roles, capabilities, default taxonomy terms, and rewrite rules. Deactivation
flushes rewrite rules but does not delete BBS data or role assignments.

Requires WordPress 6.5 or newer and PHP 8.0 or newer. No Composer, Node.js, or
external services are required.

## Managing entries

Administrators can manage BBS records and the platform and terminal type terms.
Users assigned only the **BBS Directory Manager** role can create, edit,
publish, and delete BBS records and assign existing terms. They cannot manage
terms or general site content and settings.

Each BBS has a name (the WordPress title), hostname or IP address, port from
1–65535, active/inactive status, at least one platform, and at least one
terminal type. Names and terminal names must not contain output delimiters or
line breaks. Invalid or incomplete entries cannot remain published through
the normal edit flow and are excluded from the client API.

Platform slugs initially include `atari8`, `c64`, `apple2`, `coco`, and `pc`.
Terminal slugs initially include `atascii`, `ascii`, `petscii`, `ansi`, `vt100`,
and `vt52`. Administrators can add more terms. Platform and terminal type are
independent assignments.

## Client API

| Request | Result |
| --- | --- |
| `/bbs/list/atari8` | Every active Atari-compatible BBS |
| `/bbs/list/atari8/atascii` | Active Atari BBSes supporting ATASCII |
| `/bbs/list/c64/petscii` | Active C64 BBSes supporting PETSCII |

The response is `text/plain; charset=UTF-8`, with one record per line:

```text
BBS Name|hostname|port|active|ASCII,ATASCII
```

Records are ordered by name without regard to case. Terminal names use their
canonical display names, sorted without regard to case. There is no header.
An unknown platform or terminal slug returns HTTP 404 with a short plain-text
message. A valid request with no matching BBSes returns HTTP 200 and an empty
body. Clients connect directly to the listed hostname and port; the plugin is
not a gateway.

## Local verification

After activation, create one active and one inactive Atari BBS, then request:

```text
/bbs/list/atari8
/bbs/list/atari8/atascii
/bbs/list/atari8/ascii
/bbs/list/c64/petscii
```

Verify the inactive record is absent, the active records are alphabetical, and
the platform/terminal intersection works. Use a test account assigned **only**
the BBS Directory Manager role to check that it can maintain BBS records and
assign terms, but cannot manage terms, posts, pages, users, or site settings.

## Future uptime monitoring

The meta keys `_fnbbs_last_checked`, `_fnbbs_last_seen_online`, and
`_fnbbs_failure_since` are reserved for a later monitoring phase. Version 1
does not probe BBS hosts or change status automatically.
