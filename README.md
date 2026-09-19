# MCP Server

A starter MCP server for WordPress. It turns WordPress abilities into tools an AI assistant can call. Read it, run it, then add your own.

## What is in the box

MCP has three kinds of component. The difference is who decides to use them.

| Kind | Who decides | In this plugin |
|---|---|---|
| Tool | The model | `post-search`, `post-save`, `pattern-search`, `menu-search`, `menu-save`, `setting-get`, `setting-save` |
| Resource | The client app | `site://info` |
| Prompt | You, from a menu | `draft-post`, `draft-page` |

Clients see each tool prefixed with `mcp-server-`, for example `mcp-server-post-search`.

Tools and resources are WordPress abilities, registered with `wp_register_ability()`. Prompts are not. A prompt does nothing on the site, it is only a message template, so it uses the adapter's own prompt class.

## Install

1. Install and activate the [MCP Adapter](https://github.com/WordPress/mcp-adapter/releases) plugin.
2. Download `mcp-server.zip` from this repo's releases and upload it under Plugins, Add New.

WordPress 6.9 or newer and PHP 8.2 or newer.

## Connect a client

There are two ways in. An application password works anywhere the site can be reached over HTTP. WP-CLI needs no password, but only works on the machine the site runs on.

### With an application password

The endpoint is `https://your-site/wp-json/mcp-server/v1/mcp`.

1. In wp-admin, open Users, Profile, and scroll to Application Passwords. Name one, for example `claude`, and click Add. WordPress shows the password once, so copy it now. WP-CLI can do the same: `wp user application-password create admin claude --porcelain`.
2. Connect as a user who can edit content. See the note on capabilities below.

WordPress only offers application passwords over HTTPS, unless `WP_ENVIRONMENT_TYPE` is `local`.

#### Claude Code

Claude Code speaks HTTP itself. It needs the username and password as a Basic auth header, base64 encoded:

```bash
claude mcp add --transport http wordpress https://your-site/wp-json/mcp-server/v1/mcp \
	--header "Authorization: Basic $(printf '%s' 'admin:xxxx xxxx xxxx xxxx xxxx xxxx' | base64)"
```

Add `--scope project` to write it to `.mcp.json` for the whole team instead. Keep the password out of git by referencing an environment variable there:

```json
{
	"mcpServers": {
		"wordpress": {
			"type": "http",
			"url": "https://your-site/wp-json/mcp-server/v1/mcp",
			"headers": { "Authorization": "Basic ${WP_MCP_AUTH}" }
		}
	}
}
```

Run `/mcp` inside Claude Code to check the connection. On a local site with a self-signed certificate, start Claude Code with `NODE_EXTRA_CA_CERTS` pointing at the certificate authority, or the connection is refused.

#### Claude chat

The Claude desktop app reads servers from `claude_desktop_config.json`, under Settings, Developer, Edit Config. It only launches local commands, so [`mcp-wordpress-remote`](https://github.com/Automattic/mcp-wordpress-remote) sits in between and forwards to the site. It needs Node.js.

```json
{
	"mcpServers": {
		"wordpress": {
			"command": "npx",
			"args": [ "-y", "@automattic/mcp-wordpress-remote@latest" ],
			"env": {
				"WP_API_URL": "https://your-site/wp-json/mcp-server/v1/mcp",
				"WP_API_USERNAME": "admin",
				"WP_API_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
			}
		}
	}
}
```

Quit and reopen the app. The tools appear under the tools icon in the message box.

Connectors added on claude.ai, under Settings, Connectors, will not work with an application password. They sign in with OAuth or nothing, cannot send a Basic auth header, and call from Anthropic's servers, so they cannot reach a local site either. Use the desktop app.

### With WP-CLI

```json
{
	"mcpServers": {
		"wordpress": {
			"command": "php",
			"args": [ "-d", "default_socket_timeout=-1", "/usr/local/bin/wp", "mcp-adapter", "serve", "--server=mcp-server", "--user=admin", "--path=/path/to/site" ]
		}
	}
}
```

Use the path from `which wp`. Calling `wp` directly also works, until the client is idle for a minute. Clients hand the server a socket for stdin, PHP gives up reading a socket after `default_socket_timeout`, 60 seconds by default, and the adapter takes that for the client hanging up and exits. The `-d` flag turns the timeout off.

### What the connected user can do

Every tool checks the capabilities of the user you connect as. The floor is being able to edit content: a subscriber gets nothing, not even `site://info`. An author cannot edit an editor's post over MCP any more than in wp-admin.

## Add your own ability

1. Copy `src/Abilities/PostSearch.php` to a new file in the same folder, for example `CommentSearch.php`. The file name must match the class name.
2. Fill in the seven methods: `name`, `label`, `description`, `input_schema`, `output_schema`, `permission`, `execute`. `PostSearch` also overrides `readonly()` to return `true`. Delete that method if your new ability changes anything, or it will ship marked safe to auto approve.
3. Done. Every class in `src/Abilities/` registers itself and appears as a tool, unless it overrides `type()` to become a resource.

Guidelines that keep tools easy for a model to use:

- Name it noun first: `comment-search`, `comment-save`.
- One ability to read, one to write. Give `*-save` an optional `id`: with it, update, without it, create.
- Write schemas out in full and keep the output an object. Avoid `oneOf` and `anyOf`, many clients cannot read them.
- When two abilities return the same kind of thing, shape it in one place, the way `Format::post()` and `Format::menu()` do for posts and menus.
- Override `readonly()` to return `true` on tools that change nothing, so clients can skip the confirmation. Resources are read only by nature, so this has no effect there.
- `permission()` must return `true` or `false` only. WordPress core replaces any failed permission result with one generic error, so a helpful message from `permission()` never reaches the model. Put actionable errors in `execute()` instead, as a `WP_Error` whose message says how to fix the call.
- To make a resource, override `type()` to return `'resource'` and give it a `uri` in `mcp_meta()`. See `SiteInfo.php`.

To add a prompt, copy `src/Prompts/DraftPost.php`.

## Why two abilities per noun

Every tool's name, description, and schema is sent to the model on every request. One ability per action (`get`, `list`, `create`, `update`) grows that cost quickly. One ability per noun with an `action` argument keeps it small but forces every field to be optional, and the model starts guessing. Read and write is the middle: create and update share an input, get and list share an output, and reads can still be marked read-only.

## Uniform posts with patterns

Left alone, a model invents a new layout for every post. A block pattern fixes the layout, so the model only writes the words:

1. `pattern-search` lists the site's patterns. With a `name` it returns that pattern's slots: the text, images, and links it lets you change.
2. The model writes a new value for each slot.
3. `post-save` takes `sections`, a list of pattern names and slot values, fills the patterns, and stores the result as a draft.

The model never sees or sends block markup, so it cannot break a block, and a page costs it a fraction of the output. A slot is any block attribute that `block.json` sources from the saved HTML, the same rule block bindings use, so blocks from plugins work without being listed anywhere. `src/PatternSlots.php` does the filling. It borrows a private technique from `WP_Block::replace_html()` until core ships `WP_HTML_Processor::set_inner_html()`, and a test runs every registered pattern through it to catch a core change.

The `draft-post` prompt does all three when you give it a `pattern` argument. `draft-page` always does: a page is a stack of sections, so it takes a list of `patterns`, or picks its own, and saves with `post_type` set to `page`. `pattern-search` has no write twin: patterns come from the theme and from plugins, and the model only reads them.

## Settings are allowlisted

`setting-get` and `setting-save` only touch a short list of options. Extend it with a filter:

```php
add_filter( 'mcp_server_allowed_settings', fn( $names ) => array_merge( $names, array( 'start_of_week' ) ) );
```

## Develop

```bash
composer install
composer lint       # WordPress Coding Standards
composer lint:fix
composer test       # Needs the plugin inside a WordPress Studio site, next to the MCP Adapter
composer package    # Builds dist/mcp-server.zip
```

Tests run against a throwaway SQLite database in your temp folder. Your site's data is never touched.

`composer test` needs the plugin at `wp-content/plugins/mcp-server` inside a WordPress install that uses the SQLite drop-in (`wp-content/db.php`), with the MCP Adapter installed alongside it at `wp-content/plugins/mcp-adapter`. A WordPress Studio site already has all of that.
