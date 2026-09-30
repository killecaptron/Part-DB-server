---
title: Settings API
layout: default
parent: Usage
---

# Settings API

Some of Part-DB's system settings can be read and written through the API, which is useful for external tools that
manage a Part-DB instance programmatically (e.g. a script that keeps the tag styles of several instances in sync, or
a deployment that configures a fresh instance).

## Which settings are available

Settings are **not** exposed by default. A settings group is only reachable through the API if it was explicitly
marked as exposed in the code, so a newly added setting - which might well hold a password or an API key - never
starts being served by accident.

`GET /api/settings` lists everything that is available:

```json
[
  {"name": "tag_styles", "writable": true},
  {"name": "external_part_links", "writable": true}
]
```

Both reading and writing require the `config.change_system_settings` permission (an API token needs at least the
"Admin" token level). Settings which are not exposed are answered with `404 Not Found`, exactly like a name that does
not exist at all.

## Reading

`GET /api/settings/{name}` returns the current values of one settings group, together with a strong `ETag`:

```
GET /api/settings/tag_styles
```

```json
{
  "name": "tag_styles",
  "writable": true,
  "parameters": {
    "rules": [
      {"match_type": "prefix", "pattern": "Risk:", "color": "info"},
      {"match_type": "exact", "pattern": "Risk:Check", "color": "warning"}
    ]
  }
}
```

The `parameters` object holds one entry per setting of that group. Its content therefore depends on the settings
group - see the documentation of the respective feature (e.g. [Tag styles](tag_styles.md) or
[External part links](external_part_links.md)) for what the values mean.

## Writing

`PUT /api/settings/{name}` replaces the values of a settings group. The body is the same `parameters` object a `GET`
returns, and it has to be complete: every parameter of the group must be given, and no unknown one may be, so a
client can not accidentally reset a parameter it did not know about.

The `ETag` of the last `GET` has to be sent back as `If-Match`, to make sure you are not overwriting a change
somebody else made in the meantime:

```
PUT /api/settings/tag_styles
If-Match: "sha256:e3b0c442..."

{"parameters": {"rules": [{"match_type": "prefix", "pattern": "Risk:", "color": "danger"}]}}
```

The answer is the new state of the settings, with the new `ETag` for your next write.

| Situation | Response |
|---|---|
| Missing `If-Match` header | `428 Precondition Required` |
| The settings changed since your `GET` | `412 Precondition Failed`, nothing is written |
| A missing, unknown or invalid parameter | `422 Unprocessable Content`, nothing is written |
| The settings are only readable (`"writable": false`) | `405 Method Not Allowed` |

A write is additionally serialized against a concurrent save of the settings page in the web interface, so neither of
the two can silently overwrite the other.

## Exposing further settings (for developers)

A settings class is exposed by adding the `#[SettingsApiExposed]` attribute to it (use
`#[SettingsApiExposed(writable: false)]` for read-only access). Only settings whose values are plain data - scalars,
arrays of them, or backed enums - can be exposed, and only settings which contain no secret should be: everything the
API returns is visible to every user who may change the system settings.
