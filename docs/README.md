# YTMusicAPI PHP Documentation

This directory contains the complete documentation website for YTMusicAPI PHP.

## Structure

- `index.html` - Main overview and features page
- `installation.html` - Installation and setup guide
- `authentication.html` - Authentication methods and setup
- `api-reference.html` - Complete API method reference
- `examples.html` - Practical examples and code samples
- `types.html` - Type definitions and data structures
- `styles.css` - Stylesheet for all pages
- `script.js` - JavaScript for navigation and interactions

## Features

- **Responsive Design** - Works on desktop, tablet, and mobile
- **Syntax Highlighting** - Code examples with Prism.js
- **Navigation** - Sticky sidebar navigation on reference pages
- **Copy Code** - Click-to-copy functionality for code blocks
- **Modern UI** - Clean, professional design with YouTube Music branding

## Local Development

To view the documentation locally:

1. Start a simple HTTP server in this directory:
   ```bash
   # Python 3
   python -m http.server 8000
   
   # PHP
   php -S localhost:8000
   
   # Node.js (with http-server)
   npx http-server
   ```

2. Open your browser to `http://localhost:8000`

## Deployment

These files can be deployed to any static hosting service:

- GitHub Pages
- Netlify
- Vercel
- AWS S3 + CloudFront
- Any web server

Simply upload all files maintaining the directory structure.

## Customization

- Colors can be modified in `styles.css`
- Navigation items in each HTML file's navbar
- Content sections can be added or modified
- JavaScript functionality in `script.js`

## Credits

Based on the excellent [Python ytmusicapi](https://github.com/sigma67/ytmusicapi) by sigma67.
