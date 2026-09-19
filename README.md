# MCP Server

A starter MCP server for WordPress. It turns WordPress abilities into tools an AI assistant can call. Read it, run it, then add your own.

## What is in the box

While the plugin is intended to be extended, we have tools, prompts, and a resource. It also has the philosophy that pages should be built from patterns. 

| Kind | Who decides | In this plugin |
|---|---|---|
| Tool | The model | `post-search`, `post-save`, `pattern-search`, `menu-search`, `menu-save`, `setting-get`, `setting-save` |
| Resource | The client app | `site://info` |
| Prompt | You, from a menu | `draft-post`, `draft-page` |

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

Connectors added on claude.ai, under Settings, Connectors, will not work with an application password. You will need to set up OAuth if you'd like to use connectors.

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

Use the path from `which wp`. 

## Add your own ability

1. Copy `src/Abilities/PostSearch.php` to a new file in the same folder, for example `CommentSearch.php`. The file name must match the class name.
2. Fill in the seven methods: `name`, `label`, `description`, `input_schema`, `output_schema`, `permission`, `execute`. `PostSearch` also overrides `readonly()` to return `true`. Delete that method if your new ability changes anything, or it will ship marked safe to auto approve.
3. Done. Every class in `src/Abilities/` registers itself and appears as a tool, unless it overrides `type()` to become a resource.

To add a prompt, copy `src/Prompts/DraftPost.php`.

## Pages from patterns

Left alone, a model writes block markup by hand, which is slow, easy to break, and gives a new layout every time. Here it fills patterns instead:

1. `pattern-search` lists the site's patterns. With a `name`, it returns that pattern's slots: the text, images, and links you can change.
2. `post-save` takes `sections`, a list of pattern names and slot values, and saves the filled patterns as a draft.

The model never sees or sends markup, so it cannot break a block and writes far fewer tokens.

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
composer test       # Currently designed to work on WordPress Studio. Requires the SQLite drop-in and MCP Adapter plugin.
composer package    # Builds dist/mcp-server.zip
```
