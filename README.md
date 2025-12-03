# OpenCopy.AI

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel" alt="Laravel 12">
  <img src="https://img.shields.io/badge/React-19-61DAFB?style=flat-square&logo=react" alt="React 19">
  <img src="https://img.shields.io/badge/PHP-8.3+-777BB4?style=flat-square&logo=php" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License">
</p>

**Self-hosted, open source AI content generation platform.** Generate SEO-optimized articles using OpenAI, Claude, Ollama, or other AI providers. Bring your own API key - no monthly fees, no vendor lock-in.

## Why OpenCopy?

- **No Subscription Fees**: Pay only for the AI tokens you use
- **Self-Hosted**: Your data stays on your server
- **Multi-Provider**: Works with OpenAI, Anthropic Claude, Ollama (local), Groq, Mistral, OpenRouter
- **Full Control**: Customize prompts, tone, and output to match your brand
- **Open Source**: MIT licensed, fork and modify as needed

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage Guide](#usage-guide)
- [Architecture](#architecture)
- [API Providers](#api-providers)
- [Publishing Integrations](#publishing-integrations)
- [Development](#development)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

---

## Features

### Content Generation

- **AI-Powered Writing**: Generate long-form SEO articles from keywords
- **Multiple AI Providers**: OpenAI GPT-4/3.5, Anthropic Claude, Ollama (local models), Groq, Mistral, OpenRouter
- **SEO Optimization**: Automatic keyword density, meta descriptions, heading structure
- **SEO Scoring**: Real-time 0-100 score with actionable improvement suggestions
- **AI Improvements**: One-click fixes for keyword placement, FAQ sections, tables, and more

### Content Planning

- **Visual Calendar**: Month/week/day views with drag-and-drop scheduling
- **Content Pipeline**: Full workflow from backlog → scheduled → generating → review → published
- **Content Runway**: See how many days of content you have scheduled ahead
- **Batch Scheduling**: Schedule multiple articles at once

### Media Generation

- **Featured Images**: AI-generated images with brand colors and text overlay
- **Inline Images**: Generate contextual images within articles
- **Image Styles**: Illustration, sketch, watercolor, cinematic, photo-realistic
- **YouTube Integration**: Search and embed relevant videos

### Internal Linking

- **Sitemap Crawling**: Import pages from your sitemap URL
- **Smart Link Selection**: AI chooses relevant internal links based on content
- **Link Database**: Manage anchor text, categories, and usage limits
- **Page Prioritization**: Control which pages get linked most often

### Publishing

- **Webhook Integration**: POST articles to any endpoint (Zapier, Make, n8n, custom APIs)
- **Auto-Publishing**: Schedule articles to publish automatically
- **Multi-Platform**: WordPress, Webflow, Shopify, Wix (coming soon)
- **Publication Tracking**: Monitor publish status across all integrations

### Business Tools

- **Website Analysis**: AI analyzes your site to understand your business
- **Audience Generation**: Auto-generate target audience personas
- **Competitor Analysis**: Identify and track competitors
- **Keyword Research**: AI-suggested keywords based on your niche

### Security & Multi-Tenancy

- **Encrypted Credentials**: API keys stored with Laravel encryption
- **Project Isolation**: Each user's data is completely separate
- **Two-Factor Authentication**: Optional 2FA via TOTP
- **Usage Tracking**: Monitor API costs per article and provider

---

## Requirements

- PHP 8.3 or higher
- Composer 2.x
- Node.js 20+ and npm
- SQLite or MySQL 8.0+
- At least one AI provider API key (OpenAI, Anthropic, etc.) or local Ollama

---

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/qloudanton/opencopy.git
cd opencopy
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Database Setup

For SQLite (simplest):

```bash
touch database/database.sqlite
php artisan migrate
```

For MySQL, update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=opencopy
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Then run migrations:

```bash
php artisan migrate
```

### 5. Build Frontend Assets

```bash
npm run build
```

### 6. Start the Application

For development:

```bash
composer run dev
# or
php artisan serve & npm run dev
```

For production, configure your web server (Nginx/Apache) to point to the `public` directory.

### 7. Create Your Account

Visit `http://localhost:8000` and register your first account.

---

## Configuration

### Environment Variables

Key variables in your `.env` file:

```env
# Application
APP_NAME=OpenCopy
APP_ENV=production
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=sqlite
# or mysql with full credentials

# Queue (required for background jobs)
QUEUE_CONNECTION=database

# Cache & Sessions
CACHE_STORE=database
SESSION_DRIVER=database

# Mail (for password resets, notifications)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password

# Optional: Default AI provider keys (users can add their own)
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
```

### Queue Worker

Article generation runs in the background. Start the queue worker:

```bash
php artisan queue:work --timeout=1800
```

For production, use Supervisor or systemd to keep the worker running. Example Supervisor config:

```ini
[program:opencopy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/opencopy/artisan queue:work --sleep=3 --tries=3 --timeout=1800
autostart=true
autorestart=true
numprocs=2
user=www-data
```

### Scheduled Tasks

Add to your crontab for automatic content processing:

```bash
* * * * * cd /path/to/opencopy && php artisan schedule:run >> /dev/null 2>&1
```

This enables:

- `content:process-scheduled`: Queues articles for generation (hourly)
- `content:publish-scheduled`: Publishes articles at scheduled times (every minute)

---

## Usage Guide

### For Users

#### 1. Add an AI Provider

1. Go to **Settings → AI Providers**
2. Click **Add Provider**
3. Select your provider (OpenAI, Anthropic, etc.)
4. Enter your API key
5. Choose a model (e.g., `gpt-4o`, `claude-sonnet-4-20250514`)
6. Set as default if desired

#### 2. Create a Project

1. Click **New Project**
2. Enter project name and website URL
3. Configure settings:
    - **Content**: Word count, tone, target audiences
    - **Localization**: Language and region
    - **Internal Linking**: Import sitemap, configure link behavior
    - **Media**: Enable featured images, inline images, YouTube embeds
    - **Publishing**: Set up integrations and auto-publish rules

#### 3. Add Keywords

1. Navigate to your project
2. Go to **Keywords → Add Keyword**
3. Enter:
    - Primary keyword
    - Secondary keywords (optional)
    - Search intent
    - Target word count (optional override)
4. Save and optionally generate immediately

#### 4. Schedule Content

1. Go to **Content Planner**
2. View your calendar (month/week/day)
3. Drag keywords from backlog to calendar dates
4. Or click a date to schedule new content
5. Articles will generate automatically at scheduled times

#### 5. Review & Publish

1. Generated articles appear in **In Review** status
2. Click to open the article editor
3. Review SEO score and suggestions
4. Make edits using the rich text editor
5. Click **Approve** to move to publishing
6. Articles publish via configured integrations

### For Developers

#### Project Structure

```
app/
├── Console/Commands/     # Artisan commands
├── Enums/               # Status enums (ContentStatus, IntegrationType, etc.)
├── Http/Controllers/    # Request handlers
├── Jobs/                # Background jobs (GenerateArticleJob, etc.)
├── Models/              # Eloquent models
├── Policies/            # Authorization policies
└── Services/            # Business logic
    ├── ArticleGenerationService.php
    ├── SeoScoreService.php
    ├── ArticleImageService.php
    ├── FeaturedImageService.php
    ├── ArticleImprovementService.php
    ├── BusinessAnalyzerService.php
    ├── SitemapService.php
    ├── YouTubeService.php
    ├── UsageTrackingService.php
    └── Publishing/      # Integration publishers

resources/js/
├── components/          # Reusable React components
├── layouts/             # Page layouts
├── pages/               # Inertia page components
└── types/               # TypeScript definitions
```

#### Key Models

| Model              | Purpose                                               |
| ------------------ | ----------------------------------------------------- |
| `User`             | Authentication, owns projects and AI providers        |
| `Project`          | Container for all content, settings, and integrations |
| `Keyword`          | Target keyword for article generation                 |
| `Article`          | Generated content with SEO data                       |
| `ScheduledContent` | Content pipeline state machine                        |
| `Image`            | Featured and inline images                            |
| `InternalLink`     | Link database for internal linking                    |
| `Integration`      | Publishing destinations (webhooks, CMS)               |
| `Publication`      | Tracks publish attempts per article/integration       |
| `AiProvider`       | User's AI provider credentials                        |
| `UsageLog`         | Token and cost tracking                               |
| `ProjectPage`      | Crawled sitemap pages                                 |

#### Content Pipeline States

```
Backlog → Scheduled → Queued → Generating → Enriching → InReview → Approved → Publishing → Published
                                    ↓                                              ↓
                                  Failed ←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←←
```

Each state transition is validated. Use `ContentStatus` enum for all status operations.

#### Services Overview

**ArticleGenerationService**

```php
// Generate an article from scheduled content
$service = app(ArticleGenerationService::class);
$article = $service->generateFromScheduledContent($scheduledContent, $aiProvider);
```

**SeoScoreService**

```php
// Calculate SEO score
$service = app(SeoScoreService::class);
$result = $service->calculateScore($article);
// Returns: ['score' => 75, 'analysis' => [...]]
```

**ArticleImprovementService**

```php
// Apply an improvement
$service = app(ArticleImprovementService::class);
$updated = $service->applyImprovement($article, 'add_keyword_to_title', $aiProvider);
```

#### Background Jobs

| Job                        | Timeout | Retries | Purpose                   |
| -------------------------- | ------- | ------- | ------------------------- |
| `GenerateArticleJob`       | 30 min  | 3       | Main article generation   |
| `EnrichArticleJob`         | 30 min  | 3       | Add images, links, videos |
| `GenerateFeaturedImageJob` | 15 min  | 3       | Generate featured image   |
| `PublishArticleJob`        | 5 min   | 3       | Publish to integrations   |

#### Wayfinder Routes

Frontend uses Wayfinder for type-safe routing:

```typescript
import { store, show } from '@/actions/App/Http/Controllers/ArticleController';

// Get route object
store(projectId); // { url: '/projects/1/articles', method: 'post' }

// Get just URL
show.url(projectId, articleId); // '/projects/1/articles/5'
```

---

## API Providers

### OpenAI

```env
# In user's AI Provider settings or .env
OPENAI_API_KEY=sk-...
```

Supported models: `gpt-4o`, `gpt-4o-mini`, `gpt-4-turbo`, `gpt-3.5-turbo`

Image generation: DALL-E 3, GPT-Image-1

### Anthropic Claude

```env
ANTHROPIC_API_KEY=sk-ant-...
```

Supported models: `claude-sonnet-4-20250514`, `claude-3-5-sonnet`, `claude-3-opus`, `claude-3-haiku`

### Ollama (Local)

Run models locally with no API costs:

```bash
# Install Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Pull a model
ollama pull llama3.1
ollama pull mistral
```

Configure in AI Providers with URL: `http://localhost:11434`

### Other Providers

- **Groq**: Fast inference, requires API key
- **Mistral**: European AI provider
- **OpenRouter**: Access multiple models through one API

---

## Publishing Integrations

### Webhook (Available Now)

Send articles to any HTTP endpoint:

1. Go to Project → Integrations → Add Integration
2. Select "Webhook"
3. Enter your endpoint URL
4. Configure headers (optional)
5. Test connection

Webhook payload:

```json
{
    "title": "Article Title",
    "slug": "article-slug",
    "content": "<p>HTML content...</p>",
    "content_markdown": "# Markdown content...",
    "meta_description": "SEO meta description",
    "excerpt": "Short excerpt",
    "image_url": "https://...",
    "word_count": 1500,
    "reading_time": 7,
    "keyword": "primary keyword",
    "secondary_keywords": ["keyword2", "keyword3"],
    "seo_score": 85
}
```

### WordPress (Coming Soon)

Direct REST API integration with WordPress sites.

### Webflow (Coming Soon)

Sync articles to Webflow CMS collections.

### Shopify (Coming Soon)

Create blog posts in Shopify stores.

### Wix (Coming Soon)

Publish to Wix blog.

---

## Development

### Local Development

```bash
# Start all services
composer run dev

# Or individually:
php artisan serve        # Backend
npm run dev              # Frontend with HMR
php artisan queue:work   # Queue worker
```

### Code Style

```bash
# PHP (Laravel Pint)
vendor/bin/pint

# JavaScript/TypeScript (Prettier)
npm run format
```

### Type Generation

Wayfinder generates TypeScript types from Laravel routes:

```bash
php artisan wayfinder:generate
```

This runs automatically with `npm run dev`.

---

## Testing

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ArticleGenerationServiceTest.php

# Run with filter
php artisan test --filter="generates article from keyword"

# Run with coverage
php artisan test --coverage
```

### Test Structure

```
tests/
├── Feature/           # Integration tests
│   ├── ArticleControllerTest.php
│   ├── ArticleGenerationServiceTest.php
│   ├── KeywordControllerTest.php
│   └── ...
└── Unit/              # Unit tests
    └── ...
```

---

## Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes
4. Run tests: `php artisan test`
5. Run code style: `vendor/bin/pint && npm run format`
6. Commit: `git commit -m "Add amazing feature"`
7. Push: `git push origin feature/amazing-feature`
8. Open a Pull Request

### Development Guidelines

- Follow existing code conventions
- Write tests for new features
- Update documentation as needed
- Keep PRs focused on a single feature/fix

---

## Roadmap

- [ ] WordPress REST API integration
- [ ] Webflow CMS integration
- [ ] Shopify blog integration
- [ ] Content templates
- [ ] Bulk keyword import (CSV)
- [ ] Analytics dashboard
- [ ] Team collaboration features
- [ ] API for external integrations

---

## License

OpenCopy.AI is open-source software licensed under the [MIT License](LICENSE).

---

## Support

- **Issues**: [GitHub Issues](https://github.com/qloudanton/opencopy/issues)
- **Discussions**: [GitHub Discussions](https://github.com/qloudanton/opencopy/discussions)

---

Built with Laravel, React, and AI by the open source community.
