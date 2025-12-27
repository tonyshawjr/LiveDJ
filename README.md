# LiveDJ

A self-hosted web app that displays "radio DJ" style commentary when you play music through Plex or Spotify. Perfect for displaying on a car screen (Tesla browser, CarPlay, etc.) or any second screen.

![Version](https://img.shields.io/badge/version-1.0.0-blue) ![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple) ![License](https://img.shields.io/badge/license-MIT-green)

**[Changelog](CHANGELOG.md)** · **[Roadmap](CHANGELOG.md#roadmap)**

## What It Does

When a song plays, LiveDJ:
1. Detects the track via Plex webhook or Spotify API
2. Uses AI to generate interesting facts about the artist, album, and song
3. Creates a spoken DJ intro (text-to-speech)
4. Displays beautiful slides with album art and commentary

Think of it like having a knowledgeable radio DJ introducing every song you play.

## Features

- **Dual Platform Support**: Works with Plex or Spotify
- **AI-Powered Notes**: Generates artist bios, album info, and song facts
- **Multiple AI Providers**: OpenAI, Google Gemini, or Groq (free)
- **DJ Voice**: Text-to-speech intros via OpenAI TTS or Groq TTS (free)
- **Beautiful Display**: Album art, artist photos, and rotating info slides
- **Smart Caching**: Notes are cached so each artist/album only costs one API call ever
- **Self-Hosted**: Your data stays on your server

## Screenshots

*Coming soon*

## Requirements

- PHP 8.0+
- MySQL/MariaDB
- Web server (Apache/Nginx)
- HTTPS (required for Spotify OAuth)
- One of: Plex Pass (for webhooks) or Spotify Premium

## Quick Install

1. Upload files to your web server
2. Visit `https://yourdomain.com/install.php`
3. Follow the setup wizard
4. Configure your AI provider and platform (Plex/Spotify)

## Configuration

### AI Providers

| Provider | Cost | Quality | Notes |
|----------|------|---------|-------|
| **Groq** | Free | Great | Recommended for notes |
| **Gemini** | Free tier | Great | May have rate limits |
| **OpenAI** | ~$0.001/song | Excellent | Most reliable |

### TTS (DJ Voice)

| Provider | Cost | Quality | Notes |
|----------|------|---------|-------|
| **Browser** | Free | Robotic | Built-in, works everywhere |
| **Groq** | Free | OK | 180 char limit, may mispronounce |
| **OpenAI** | ~$0.003/song | Excellent | Recommended |

### Plex Setup

1. Get your [Plex Token](https://support.plex.tv/articles/204059436-finding-an-authentication-token-x-plex-token/)
2. Add the webhook URL to Plex Settings → Webhooks
3. Requires Plex Pass subscription

### Spotify Setup

1. Create an app at [Spotify Developer Dashboard](https://developer.spotify.com/dashboard)
2. Add redirect URI: `https://yourdomain.com/api/spotify-callback.php`
3. Enter Client ID and Secret in dashboard
4. Click "Connect Spotify"
5. Open the display page - it automatically polls for track changes

## File Structure

```
livedj-php/
├── admin/           # Admin dashboard
├── api/             # API endpoints
├── includes/        # Core PHP functions
├── tts-cache/       # Generated audio files (auto-cleaned)
├── config.sample.php
├── display.php      # Main display page
├── index.php
└── install.php
```

## API Keys

You'll need at least one AI provider key:

- **Groq** (free): [console.groq.com/keys](https://console.groq.com/keys)
- **Gemini** (free): [aistudio.google.com/app/apikey](https://aistudio.google.com/app/apikey)
- **OpenAI** (paid): [platform.openai.com/api-keys](https://platform.openai.com/api-keys)

For TTS, OpenAI gives the best quality (~$0.003 per song intro).

## Display URL

Open `https://yourdomain.com/` in your car browser or any screen. The page auto-updates when songs change.

## Tips

- **Tesla**: Works great in the Tesla browser. Bookmark the display URL.
- **CarPlay**: Use a web browser app that supports CarPlay.
- **Caching**: First play of an artist/album generates notes. Subsequent plays are instant.
- **TTS Cache**: Audio files auto-delete after 7 days.

## Troubleshooting

**No notes appearing?**
- Check your AI API key is set correctly
- Visit the admin dashboard to verify connection

**Spotify not detecting songs?**
- Make sure the cron job is running
- Check that Spotify is connected in dashboard

**TTS not playing?**
- Click the DJ headset icon to enable
- Check browser autoplay permissions

## Contributing

Found a bug? Want to add a feature? Fork it and make it better!

Pull requests welcome. This started as a personal project so there's probably room for improvement.

## License

MIT License - do whatever you want with it.

## Support

If this saved you time, consider:
- Starring the repo
- [![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/U6U01R3X7M)

---

Made with ♥ in Wilmington, NC
