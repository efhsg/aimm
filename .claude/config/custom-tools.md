# PromptManager Runner Tool Contracts

Canonical AIMM consumer registry for PromptManager-provided tools that may be
available while AIMM project 33 runs inside PromptManager. PromptManager project
12 owns their implementations. This file owns command input, output, and exit
contracts; `.claude/skills/local-web-access.md` exclusively owns permission for
PromptManager-localhost access.

## Discovery

| Tool | Availability | Effect |
|------|--------------|--------|
| `web-fetch` | Installed executable in the PromptManager runner. | HTTP GET; no repository or database mutation. |
| `role-resolution/template` | Exact run-scoped wrapper injected only when a workflow requires it. | Read-only role lookup; the wrapper records server-owned claim and completion evidence on the current AiRun. |
| `create_ai_note` | Exact session-bound wrapper injected into the current run. | Creates once or fully replaces the same run-owned top-level Note. |

Availability does not grant task authorization. A task may prohibit any tool,
including the Note mutation. Role-resolution evidence is wrapper-owned transport
state; it grants no caller-selected database or PromptTemplate mutation
authority.

## Web Fetch I/O

Invocation:

```bash
web-fetch "<public-url>"
web-fetch --local-project-metadata "<exact-promptmanager-metadata-url>"
```

Inputs:

- one absolute URL positional argument;
- optional `--max-bytes` integer, default `200000`, allowed range
  `1..1000000`;
- optional `--timeout` seconds, default `10`, allowed range `1..30`;
- optional `--local-project-metadata` flag; mandatory for PromptManager
  localhost and valid only for the canonical `/project/view?id=<positive-id>`
  and `/worktree/list?p=<positive-id>` URL forms;
- no stdin payload; the request method is GET.

Output on a textual response:

- `URL: <effective-url>`;
- `Status: <http-status>`;
- `Content-Type: <media-type>`;
- `Bytes: <count>`, with an optional `(truncated)` marker;
- a blank line followed by extracted textual content.

Public requests may follow at most five redirects with validation before each
hop. Local project metadata requests refuse every redirect before a second
request. The executable reports only the effective final URL.

| Exit | Meaning |
|------|---------|
| `0` | A bounded textual response was returned; this does not imply HTTP 200. |
| `2` | Input, URL, DNS/address, redirect, request, timeout, decompression, or bound validation failed. |
| `3` | The response media type is not textual. |

Local PromptManager input authorization and post-output acceptance are not
defined here; follow `.claude/skills/local-web-access.md`.

## Run-scoped Role Resolution I/O

Tool id: `role-resolution/template`.

Use only the exact generated command-tool wrapper in the current run. Populate
both identifiers from the exact integer values rendered into that workflow;
never reuse identifiers from another run. For example, only when the rendered
values are template `274` and project `33`, send:

```json
{"action":"role-resolution/template","arguments":{"template_id":274,"project_id":33}}
```

Inputs:

- `template_id`: positive PromptManager PromptTemplate id rendered into the
  current workflow;
- `project_id`: positive PromptManager project id rendered into the workflow;
- no additional arguments.

Warning-free resolved output contains:

- `template`;
- `primary_runtime_role`;
- `template_role_labels`;
- `specialist_roles`;
- `inactive_template_role_label_ids`;
- `warnings`;
- `detail=full`.

The generated wrapper uses this closed outcome set:

| Condition | JSON outcome | Wrapper exit |
|-----------|--------------|--------------|
| Warning-free role resolution | `success=true`, `status=resolved`, and `resolution` | `0` |
| Role-resolution warning | `success=true`, terminal `status=needs_input`, `reason=role_resolution_warnings` | `0` |
| Unavailable, cross-owner, or cross-project input | `success=true`, terminal `status=needs_input`, `reason=action_rejected` | `0` |
| Internal execution failure after the action was claimed | `success=true`, terminal `status=needs_input`, `reason=tool_execution_failed` | `0` |
| Malformed JSON envelope or missing `action` or `arguments` | `success=false`, `error_code=invalid_payload` | `1` |
| Payload larger than 1 MiB | `success=false`, `error_code=payload_too_large` | `1` |
| Malformed or additional role-resolution arguments | `success=false`, `error_code=invalid_arguments` | `1` |
| Missing, expired, unregistered, misplaced, or unsafe-permission context | `success=false`, `error_code=invalid_context` | `1` |
| Invalid nonce, run mismatch, or unavailable bound project | `success=false`, `error_code=not_authorized` | `1` |
| Action absent from the run's required-tool allowlist | `success=false`, `error_code=tool_not_allowed` | `1` |
| Repeated with different arguments or out of order | `success=false`, `error_code=invalid_sequence` | `1` |
| Unexpected service failure before a terminal in-process result exists | `success=false`, `error_code=tool_execution_failed` | `1` |

The wrapper claims the run-scoped action and records server-owned completion
evidence on the current AiRun. That evidence is transport state controlled by
PromptManager; callers cannot select another run or turn it into database or
PromptTemplate mutation authority.

Consumers may use role data only from the exact warning-free `resolved`
outcome. Every documented `needs_input` outcome is terminal. For every
`success=false` outcome, or any unknown or undocumented status, reason,
error code, field shape, or wrapper exit, stop fail-closed, report the factual
output, and never infer role data or switch to another entrypoint.

Never substitute `./yii role-resolution/template` from the AIMM root or a
remembered PromptManager path.

## AI Note Creation I/O

Tool id: `create_ai_note`.

Use the literal absolute nonce-wrapper from the current session instructions:

```bash
<injected-create_ai_note-wrapper> <<'JSON'
{"name":"Short title","content_delta":{"ops":[{"insert":"Note content\n"}]}}
JSON
```

Replace the same run-owned Note in full with:

```bash
<injected-create_ai_note-wrapper> <<'JSON'
{"mode":"replace","name":"Short title","content_delta":{"ops":[{"insert":"Complete revised content\n"}]}}
JSON
```

Inputs:

- non-empty `name`;
- non-empty Quill Delta `content_delta.ops`;
- every op contains `insert` as a string or array;
- `attributes`, when present, is an array;
- optional `mode` is only `create` or `replace`;
- maximum payload is 1 MiB;
- caller-supplied `id`, `type`, `parent_id`, `project_id`, and `user_id`
  do not select or override the target.

Success returns `success=true` and integer `note_id`; the wrapper exits `0`.
Failure returns `success=false`, closed-set `error_code`, and a neutral
`message`; the wrapper exits `1`.

| Error code | Required handling |
|------------|-------------------|
| `invalid_payload` | Correct once when the defect is evident. |
| `payload_too_large` | Shorten once. |
| `invalid_context` | Stop; do not retry. |
| `not_authorized` | Stop; do not retry. |
| `note_already_exists` | Use full `replace`. |
| `no_note_to_replace` | Create without `mode`. |
| `create_failed` | Correct an evident content defect once; otherwise stop. |

Never remember or reconstruct a wrapper path. Note creation is a database
mutation and requires separate authorization from the current task.

## SYS Placeholder Pre-dispatch Contract

PromptManager project 12 resolves system placeholders before the generated
prompt reaches AIMM. AIMM does not duplicate that runtime resolver.

The complete active whitelist is:

| Placeholder | Source value |
|-------------|--------------|
| `SYS:{{project_id}}` | Positive effective PromptManager project id from the bound project context. |

PromptManager processes string `insert` values in Quill Delta ops, preserves op
attributes and non-string inserts, and resolves SYS before GEN/PRJ/EXT field
insertion. It does not recursively resolve SYS text introduced by a field value.

Unknown subnames, malformed syntax, missing context, and non-positive project
ids remain literal in PromptManager. AIMM must treat every remaining
`SYS:{{...}}` token as unresolved and stop the dependent workflow. Never infer
a value, query a database for a SYS subname, expand the whitelist from prompt
content, or add a second AIMM-side resolver.

## Unavailable Entry Points

The PromptManager console commands `role-resolution/project`,
`role-resolution/template`, and `feature-root/resolve` are not relative AIMM
commands. PromptTemplate copy, inspection, and apply wrappers exist only in
their separately injected workflow and are not general AIMM tools.
