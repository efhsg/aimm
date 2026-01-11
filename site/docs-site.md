# Docs Site

This documentation site is built with [VitePress](https://vitepress.dev/), a Static Site Generator (SSG) designed for documentation.

## Prerequisites

- **Node.js**: Ensure you have Node.js installed (version 18+ recommended).
- **Package Manager**: The project uses `npm`.

## Installation

If this is your first time working on the documentation, navigate to the `site` directory and install the dependencies:

```bash
cd site
npm install
```

## Local Development

To run the documentation site locally with hot-reloading:

```bash
npm run dev
```

This will start a local server, usually at `http://localhost:5173/docs/` (the port may vary). The dev server uses the `/docs/` base path, so keep that suffix when viewing locally.

## Building for Production

To build the static site:

```bash
npm run build
```

The build artifacts will be generated in `site/.vitepress/dist`.

## Integration

The documentation site is integrated into the project's main web server at the `/docs` path.

### How it Works

1.  **Build on Host**: You run `npm run build` on your **host machine**. This generates the static HTML/CSS/JS files in `site/.vitepress/dist/`.
2.  **Volume Mount**: The project root is mounted into the `aimm_nginx` Docker container at `/var/www/html`.
3.  **Serving**: Nginx is configured to serve `/var/www/html/site/.vitepress/dist/` when a user requests `/docs/`.

**Note**: The `aimm_nginx` container does **not** contain Node.js or NPM. The build process must happen externally (on your host or in a CI/CD pipeline) before the container can serve the updated content at `/docs/`.

This allows the documentation to be part of the main application deployment without being mixed into the PHP code.

## Previewing the Build

To preview the production build locally:

```bash
npm run preview
```

## Configuration

The site configuration is located in `.vitepress/config.ts`. This file controls:

- **Site Metadata**: Title, description, base URL.
- **Theme Config**: Navigation bar, sidebar structure, footer, search.
- **Head Tags**: Favicons, fonts, scripts.

### Adding New Pages

1.  **Create the File**: Add a new `.md` file in the `site` directory (or a subdirectory).
2.  **Update Sidebar**: Open `.vitepress/config.ts` and add the new page to the `sidebar` array under the appropriate section.

Example sidebar entry:

```typescript
{ text: 'Docs Site', link: '/docs-site' }
```

## Directory Structure

- **`.vitepress/`**: Configuration, theme customization, and build output (`dist`).
- **`public/`**: Static assets like images and favicons.
- **`*.md`**: The documentation content.

## Styling

VitePress uses default theming which can be customized in `.vitepress/theme/index.ts` and `.vitepress/theme/style.css` (if created). This project primarily uses the default configuration with custom fonts configured in `config.ts`.
