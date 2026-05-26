# YouTube Channel Free-License Finder

A small PHP/JavaScript Toolforge-style utility for finding Creative Commons licensed videos in a YouTube channel.

Hosted version: <https://youtube-channel.toolforge.org/>

The tool accepts a YouTube channel URL, lists matching free-license videos, checks for possible Wikimedia Commons matches, and links each video to Video2Commons for upload.

## Features

- Supports modern YouTube channel URLs, including `/channel/...`, `/user/...`, `/c/...`, bare custom URLs, and `@handle` URLs.
- Finds Creative Commons videos by scanning the channel uploads and checking video license metadata.
- Shows progress and explains when YouTube stops returning more paginated results.
- Generates an RSS feed of matching videos.
- Offers upload links to Video2Commons.
- Supports English, Dutch, French, German, and Spanish UI text.

## Configuration

Copy `config.example.php` to `config.php` and add your YouTube API key:

```php
return [
	'youtube_api_key' => 'your-key-here',
];
```

You can also set the `YOUTUBE_API_KEY` environment variable instead.

`config.php` is ignored by Git and should not be committed.

## Install

Install PHP dependencies with Composer:

```sh
composer install
```

The project expects PHP 8.1 or newer.

## Hosted Usage

Most users should use the hosted Toolforge instance:

```text
https://youtube-channel.toolforge.org/
```

The app accepts YouTube channel URLs such as:

```text
https://www.youtube.com/@example
https://www.youtube.com/channel/UC...
youtube.com/user/example
youtube.com/c/example
```

Direct channel links also work:

```text
https://youtube-channel.toolforge.org/?channelId=UC...
```

## Local Development

Run the project through a local PHP-capable web server, for example:

```text
http://localhost/youtube-channel/
```

## RSS

The hosted RSS endpoint returns matching free-license videos:

```text
https://youtube-channel.toolforge.org/rss.php?channel_id=UC...
```

For local development, use the same path on your local host:

```text
http://localhost/youtube-channel/rss.php?channel_id=UC...
```

By default, RSS scans all available matching results. You can limit it:

```text
https://youtube-channel.toolforge.org/rss.php?channel_id=UC...&limit=500
```

## Language

Use the language picker in the header, or set `lang` manually:

```text
?lang=en
?lang=nl
?lang=fr
?lang=de
?lang=es
```

## Notes

YouTube may report more matching videos than it will continue returning through pagination. When that happens, the app keeps the videos it could verify and explains why the load-more button disappeared.
