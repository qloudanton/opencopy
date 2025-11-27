# OpenCopy.AI - System Architecture

## Overview

OpenCopy is a self-hosted Laravel application that generates SEO-optimized articles using AI and publishes them to various platforms. Users bring their own API keys.

## Key Decisions

- **Projects from Day 1**: Every resource is scoped to a Project (= a website)
- **Teams deferred**: Multi-user/team support will be added post-MVP
- **AI Providers at User level**: User's API keys are shared across all their projects

## Data Hierarchy

```
User (account)
  ├── hasMany AiProviders (user's API keys - shared across projects)
  │
  └── hasMany Projects (websites)
        ├── hasMany Keywords
        ├── hasMany InternalLinks
        ├── hasMany Articles
        ├── hasMany Integrations
        └── hasMany Prompts (project-specific)
```

## System Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              USER ACCOUNT                                    │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                         AI PROVIDERS                                 │    │
│  │              (OpenAI / Anthropic / Ollama keys)                      │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
              ┌─────────────────────┼─────────────────────┐
              ▼                     ▼                     ▼
┌─────────────────────┐ ┌─────────────────────┐ ┌─────────────────────┐
│   PROJECT A         │ │   PROJECT B         │ │   PROJECT C         │
│   (myblog.com)      │ │   (recipes.com)     │ │   (client.com)      │
├─────────────────────┤ ├─────────────────────┤ ├─────────────────────┤
│ • Keywords          │ │ • Keywords          │ │ • Keywords          │
│ • Internal Links    │ │ • Internal Links    │ │ • Internal Links    │
│ • Prompts           │ │ • Prompts           │ │ • Prompts           │
│ • Articles          │ │ • Articles          │ │ • Articles          │
│ • Integrations      │ │ • Integrations      │ │ • Integrations      │
└─────────────────────┘ └─────────────────────┘ └─────────────────────┘
```

## Generation Pipeline

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          PROJECT SCOPE                                       │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                            INPUTS                                    │    │
│  ├─────────────────┬─────────────────┬─────────────────────────────────┤    │
│  │    Keywords     │  Internal Links │         Prompts                 │    │
│  │  (what to write)│ (links to embed)│     (instructions)              │    │
│  └────────┬────────┴────────┬────────┴────────────┬────────────────────┘    │
│           │                 │                     │                          │
│           └─────────────────┴─────────────────────┘                          │
│                                   │                                          │
│                                   ▼                                          │
│           ┌──────────────────────────────────────────────────────────┐      │
│           │           ARTICLE GENERATION ENGINE                       │      │
│           │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐       │      │
│           │  │ AI Provider │  │   Prompt    │  │   Image     │       │      │
│           │  │   Service   │  │   Builder   │  │  Generator  │       │      │
│           │  └─────────────┘  └─────────────┘  └─────────────┘       │      │
│           └──────────────────────────┬───────────────────────────────┘      │
│                                      │                                       │
│                                      ▼                                       │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                            OUTPUTS                                   │    │
│  ├─────────────────────────────────┬───────────────────────────────────┤    │
│  │            Articles             │              Images                │    │
│  │  (title, content, meta, SEO)    │  (featured, in-content, AI-gen)   │    │
│  └────────────────┬────────────────┴───────────────────┬───────────────┘    │
│                   │                                    │                     │
│                   └────────────────┬───────────────────┘                     │
│                                    │                                         │
│                                    ▼                                         │
│           ┌──────────────────────────────────────────────────────────┐      │
│           │                 PUBLISHING SERVICE                        │      │
│           └──────────────────────────┬───────────────────────────────┘      │
│                                      │                                       │
│                                      ▼                                       │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                          INTEGRATIONS                                │    │
│  ├──────────┬──────────┬──────────┬──────────┬──────────┬──────────────┤    │
│  │WordPress │ Webflow  │   Wix    │ Shopify  │ Webhook  │   Future...  │    │
│  └──────────┴──────────┴──────────┴──────────┴──────────┴──────────────┘    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Data Models

### 1. Projects (Container)

A Project represents a website or content destination. All resources are scoped to a project.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Owner |
| name | string | Project name ("My Tech Blog") |
| domain | string | Website domain (myblog.com) - optional |
| description | text | Notes about this project |
| settings | json | Project-level defaults (tone, word count, etc.) |
| is_active | bool | Enable/disable project |
| created_at, updated_at | timestamps | |

**Relationships:**
- belongsTo User
- hasMany Keywords
- hasMany InternalLinks
- hasMany Prompts
- hasMany Articles
- hasMany Integrations

**Settings Structure:**
```json
{
  "default_tone": "professional",
  "default_word_count": 1500,
  "default_search_intent": "informational",
  "language": "en",
  "timezone": "UTC"
}
```

---

### 2. Keywords (Input)

The starting point for article generation. Each keyword represents a topic to write about.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| project_id | bigint | Parent project |
| keyword | string | Primary target keyword |
| secondary_keywords | json | Array of related keywords to include |
| search_intent | enum | informational, transactional, navigational, commercial |
| target_word_count | int | Desired article length (default: 1500) |
| tone | string | Writing style (professional, casual, technical, etc.) |
| additional_instructions | text | Custom instructions for this keyword |
| status | enum | pending, queued, generating, completed, failed |
| priority | int | Generation queue priority |
| created_at, updated_at | timestamps | |

**Relationships:**
- belongsTo Project
- hasMany Articles

---

### 3. Internal Links (Input)

Database of links to weave into generated content for internal SEO.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| project_id | bigint | Parent project |
| url | string | The URL to link to |
| anchor_text | string | Preferred anchor text |
| title | string | Page title (helps AI understand context) |
| description | text | What the page is about |
| category | string | Grouping for relevance matching |
| priority | int | How often to use (1-10) |
| max_uses_per_article | int | Limit links per article (default: 1) |
| is_active | bool | Enable/disable without deleting |
| created_at, updated_at | timestamps | |

**Relationships:**
- belongsTo Project
- belongsToMany Articles (pivot: article_internal_links with position, anchor_text_used)

---

### 4. Prompts (Input)

Reusable prompt templates for different content types and styles.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| project_id | bigint | Parent project (null for system defaults) |
| name | string | Human-friendly name |
| type | enum | system, structure, tone, seo, custom |
| content | text | The prompt template (supports variables) |
| variables | json | Available variables and their descriptions |
| is_default | bool | Use as default for this type |
| is_active | bool | Enable/disable |
| created_at, updated_at | timestamps | |

**Prompt Types:**
- `system` - Base system prompt for the AI
- `structure` - Article structure/outline instructions
- `tone` - Voice and style guidelines
- `seo` - SEO optimization instructions
- `custom` - User-defined templates

**Template Variables:**
- `{{keyword}}` - Primary keyword
- `{{secondary_keywords}}` - Comma-separated list
- `{{word_count}}` - Target length
- `{{tone}}` - Writing style
- `{{internal_links}}` - Formatted list of available links
- `{{additional_instructions}}` - Per-keyword custom instructions

**Relationships:**
- belongsTo Project (nullable - null means system default)

---

### 5. AI Providers (User-Level Configuration)

User's AI provider settings. Supports multiple providers. **Shared across all user's projects.**

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Owner |
| provider | enum | openai, anthropic, ollama |
| name | string | User-friendly name ("My OpenAI", "Local Ollama") |
| api_key | encrypted | API key (encrypted at rest) |
| api_endpoint | string | Custom endpoint (for Ollama/proxies) |
| model | string | Model identifier (gpt-4o, claude-sonnet-4-20250514, llama3, etc.) |
| settings | json | temperature, max_tokens, etc. |
| is_default | bool | Use as default provider |
| is_active | bool | Enable/disable |
| created_at, updated_at | timestamps | |

**Default Settings by Provider:**
```json
{
  "openai": { "temperature": 0.7, "max_tokens": 4000 },
  "anthropic": { "temperature": 0.7, "max_tokens": 4000 },
  "ollama": { "temperature": 0.7, "num_predict": 4000 }
}
```

**Relationships:**
- belongsTo User
- hasMany Articles (as generator)

---

### 6. Articles (Output)

Generated articles with full SEO metadata.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| project_id | bigint | Parent project |
| keyword_id | bigint | Source keyword (nullable for manual articles) |
| ai_provider_id | bigint | Which AI generated this |
| title | string | Article title (50-60 chars for SEO) |
| slug | string | URL-friendly identifier |
| meta_description | string | SEO meta description (150-160 chars) |
| excerpt | text | Short summary for listings |
| content | longtext | Full article content (HTML) |
| content_markdown | longtext | Markdown version (for editing) |
| outline | json | Heading structure used |
| word_count | int | Actual word count |
| reading_time_minutes | int | Estimated reading time |
| seo_score | int | Calculated SEO score (0-100) |
| seo_analysis | json | Detailed SEO breakdown |
| status | enum | draft, review, approved, scheduled, published |
| generation_metadata | json | Tokens used, time taken, model, etc. |
| generated_at | timestamp | When AI generation completed |
| created_at, updated_at | timestamps | |

**Relationships:**
- belongsTo Project
- belongsTo Keyword (nullable)
- belongsTo AiProvider
- hasMany Images
- belongsToMany InternalLinks
- hasMany Publications

**SEO Analysis Structure:**
```json
{
  "keyword_in_title": true,
  "keyword_in_meta": true,
  "keyword_in_first_paragraph": true,
  "keyword_density": 1.5,
  "heading_structure": { "h1": 1, "h2": 5, "h3": 8 },
  "internal_links_count": 3,
  "external_links_count": 2,
  "images_with_alt": 2,
  "meta_description_length": 155,
  "title_length": 58,
  "readability_score": 65
}
```

---

### 7. Images (Output)

Images for articles - AI-generated, stock, or uploaded.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| project_id | bigint | Parent project |
| article_id | bigint | Parent article (nullable) |
| type | enum | featured, content, og_image |
| source | enum | ai_generated, stock, uploaded |
| prompt | text | AI generation prompt (if applicable) |
| path | string | Storage path |
| url | string | Public URL |
| alt_text | string | SEO alt text |
| caption | string | Optional caption |
| width | int | Image width |
| height | int | Image height |
| file_size | int | Size in bytes |
| mime_type | string | image/jpeg, image/png, image/webp |
| metadata | json | EXIF, generation params, stock source, etc. |
| created_at, updated_at | timestamps | |

**Relationships:**
- belongsTo Project
- belongsTo Article (nullable)

---

### 8. Integrations (Publishing Destinations)

Configured publishing destinations. Each project can have multiple integrations.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| project_id | bigint | Parent project |
| type | enum | wordpress, webflow, wix, shopify, webhook |
| name | string | User-friendly name ("My WordPress Blog") |
| credentials | encrypted | API keys, tokens, etc. |
| settings | json | Endpoint, field mappings, defaults |
| is_active | bool | Enable/disable |
| last_connected_at | timestamp | Last successful connection |
| created_at, updated_at | timestamps | |

**Settings Structure by Type:**

```json
// WordPress
{
  "url": "https://myblog.com",
  "auth_type": "application_password",
  "username": "admin",
  "default_status": "draft",
  "default_category": 5,
  "default_author": 1
}

// Webhook
{
  "url": "https://api.example.com/articles",
  "method": "POST",
  "headers": { "X-API-Key": "..." },
  "payload_format": "json",
  "field_mapping": {
    "title": "article.title",
    "body": "article.content"
  }
}
```

**Relationships:**
- belongsTo Project
- hasMany Publications

---

### 9. Publications (Publishing Records)

Tracks where articles have been published.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| article_id | bigint | The published article |
| integration_id | bigint | Where it was published |
| status | enum | pending, publishing, published, failed, updated |
| external_id | string | ID on the external platform |
| external_url | string | Public URL on the platform |
| payload_sent | json | What we sent (for debugging) |
| response_received | json | What we got back |
| error_message | text | Error details if failed |
| published_at | timestamp | When successfully published |
| created_at, updated_at | timestamps | |

**Relationships:**
- belongsTo Article
- belongsTo Integration

---

## Services Architecture

### Core Services

```
app/
├── Services/
│   ├── AI/
│   │   ├── AIProviderInterface.php      # Contract for AI providers
│   │   ├── AIProviderFactory.php        # Creates provider instances
│   │   ├── OpenAIProvider.php           # OpenAI implementation
│   │   ├── AnthropicProvider.php        # Claude implementation
│   │   └── OllamaProvider.php           # Ollama implementation
│   │
│   ├── Article/
│   │   ├── ArticleGenerationService.php # Orchestrates generation
│   │   ├── PromptBuilder.php            # Builds prompts from templates
│   │   ├── ContentParser.php            # Parses AI response into structure
│   │   └── SEOAnalyzer.php              # Calculates SEO scores
│   │
│   ├── Image/
│   │   ├── ImageGenerationService.php   # Orchestrates image generation
│   │   ├── DALLEProvider.php            # OpenAI DALL-E
│   │   └── StockPhotoProvider.php       # Unsplash/Pexels
│   │
│   └── Publishing/
│       ├── PublishingService.php        # Orchestrates publishing
│       ├── IntegrationInterface.php     # Contract for integrations
│       ├── WordPressIntegration.php     # WordPress REST API
│       ├── WebflowIntegration.php       # Webflow API
│       ├── ShopifyIntegration.php       # Shopify Blog API
│       └── WebhookIntegration.php       # Generic webhook
```

### Jobs (Queue)

```
app/
├── Jobs/
│   ├── GenerateArticleJob.php           # Full article generation
│   ├── GenerateArticleOutlineJob.php    # Just the outline (for preview)
│   ├── GenerateImageJob.php             # Single image generation
│   ├── AnalyzeArticleSEOJob.php         # Recalculate SEO score
│   ├── PublishArticleJob.php            # Publish to integration
│   └── TestIntegrationJob.php           # Test connection
```

---

## Generation Flow

### Step-by-Step Process

```
1. USER creates Keyword
   └─► Keyword saved with status: "pending"

2. USER triggers generation (or scheduled)
   └─► GenerateArticleJob dispatched
   └─► Keyword status: "queued"

3. JOB: Build Prompt
   ├─► Fetch active prompts (system, structure, tone, seo)
   ├─► Fetch relevant internal links (by category/keyword match)
   ├─► Merge keyword data with prompt templates
   └─► Keyword status: "generating"

4. JOB: Call AI Provider
   ├─► AIProviderFactory creates correct provider
   ├─► Provider makes API call with built prompt
   └─► Raw response received

5. JOB: Parse Response
   ├─► ContentParser extracts title, meta, content, headings
   ├─► Validate structure and length
   └─► Handle any parsing errors

6. JOB: Enhance Content
   ├─► Insert internal links at appropriate places
   ├─► Generate images if configured
   └─► Optimize formatting

7. JOB: Analyze SEO
   ├─► SEOAnalyzer calculates all metrics
   ├─► Generate recommendations
   └─► Calculate overall score

8. JOB: Save Article
   ├─► Create Article record with all data
   ├─► Link to Keyword, Images, InternalLinks
   ├─► Keyword status: "completed"
   └─► Article status: "draft"

9. USER reviews article
   └─► Edit content, approve, or regenerate

10. USER publishes
    ├─► Select integration(s)
    ├─► PublishArticleJob dispatched
    └─► Article status: "published"
```

---

## Implementation Phases

### Phase 1: Foundation (MVP Core)
- [ ] Database migrations for all models (Projects, Keywords, Articles, etc.)
- [ ] Eloquent models with relationships
- [ ] Factories and seeders for testing
- [ ] Project CRUD and switching
- [ ] Basic CRUD controllers for Keywords, InternalLinks (project-scoped)
- [ ] AI Provider configuration (OpenAI first, user-level)
- [ ] Simple article generation (single prompt, no images)
- [ ] Dashboard UI: project selector, list keywords, view articles

### Phase 2: Generation Engine
- [ ] Prompt templates system
- [ ] PromptBuilder with variable substitution
- [ ] ContentParser for structured output
- [ ] SEOAnalyzer with scoring
- [ ] Internal link injection
- [ ] Generation queue with progress tracking
- [ ] Article editing UI

### Phase 3: Images
- [ ] Image model and storage
- [ ] DALL-E integration
- [ ] Stock photo integration (Unsplash)
- [ ] Image optimization
- [ ] Featured image in articles

### Phase 4: Publishing
- [ ] Integration configuration UI
- [ ] WordPress integration
- [ ] Webhook integration
- [ ] Publication tracking
- [ ] Retry failed publications

### Phase 5: Advanced Features
- [ ] Bulk keyword import (CSV)
- [ ] Scheduled generation
- [ ] Scheduled publishing
- [ ] Additional integrations (Webflow, Wix, Shopify)
- [ ] SEO recommendations and improvements
- [ ] Content versioning

---

## API Structure (Internal)

All project-scoped resources are nested under `/projects/{project}`.

### Projects
```
GET     /projects                           # List user's projects
POST    /projects                           # Create project
GET     /projects/{project}                 # View project
PUT     /projects/{project}                 # Update project
DELETE  /projects/{project}                 # Delete project
```

### Keywords (Project-scoped)
```
GET     /projects/{project}/keywords                 # List keywords
POST    /projects/{project}/keywords                 # Create keyword
GET     /projects/{project}/keywords/{keyword}       # View keyword
PUT     /projects/{project}/keywords/{keyword}       # Update keyword
DELETE  /projects/{project}/keywords/{keyword}       # Delete keyword
POST    /projects/{project}/keywords/{keyword}/generate   # Trigger generation
```

### Articles (Project-scoped)
```
GET     /projects/{project}/articles                 # List articles
GET     /projects/{project}/articles/{article}       # View article
PUT     /projects/{project}/articles/{article}       # Update article
DELETE  /projects/{project}/articles/{article}       # Delete article
POST    /projects/{project}/articles/{article}/regenerate # Regenerate
POST    /projects/{project}/articles/{article}/publish    # Publish
```

### Internal Links (Project-scoped)
```
GET     /projects/{project}/internal-links           # List links
POST    /projects/{project}/internal-links           # Create link
PUT     /projects/{project}/internal-links/{link}    # Update link
DELETE  /projects/{project}/internal-links/{link}    # Delete link
POST    /projects/{project}/internal-links/import    # Bulk import (CSV)
```

### Integrations (Project-scoped)
```
GET     /projects/{project}/integrations             # List integrations
POST    /projects/{project}/integrations             # Create integration
PUT     /projects/{project}/integrations/{id}        # Update integration
DELETE  /projects/{project}/integrations/{id}        # Delete integration
POST    /projects/{project}/integrations/{id}/test   # Test connection
```

### Prompts (Project-scoped)
```
GET     /projects/{project}/prompts                  # List prompts
POST    /projects/{project}/prompts                  # Create prompt
PUT     /projects/{project}/prompts/{prompt}         # Update prompt
DELETE  /projects/{project}/prompts/{prompt}         # Delete prompt
```

### AI Providers (User-level, not project-scoped)
```
GET     /settings/ai-providers                       # List AI providers
POST    /settings/ai-providers                       # Add provider
PUT     /settings/ai-providers/{id}                  # Update provider
DELETE  /settings/ai-providers/{id}                  # Delete provider
POST    /settings/ai-providers/{id}/test             # Test connection
```

---

## Security Considerations

1. **API Keys**: All API keys stored encrypted using Laravel's `encrypted` cast
2. **User Isolation**: All queries scoped to authenticated user via Project ownership
3. **Project Isolation**: All project-scoped queries verify user owns the project
4. **Rate Limiting**: Prevent abuse of AI generation endpoints
5. **Input Validation**: Sanitize all user inputs, especially in prompts
6. **Content Security**: Sanitize AI-generated HTML before storage
7. **Webhook Security**: Sign outgoing webhooks, validate incoming

---

## Future Considerations (Post-MVP)

### Teams / Multi-user Support

When ready to add team collaboration:

```
Current:  User → Project → Resources
Future:   User → Team → Project → Resources
```

Migration path:
1. Create Team, TeamMember models
2. Every User gets a "Personal" team automatically
3. Add team_id to Projects, migrate existing projects
4. Update authorization to check team membership
5. Build team management UI (invitations, roles)

This is intentionally deferred to keep MVP simple.

---

## Tech Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Frontend | Inertia + React | Already set up, good DX |
| Styling | Tailwind v4 | Already set up, rapid development |
| Queue | Database (default) | Simple for self-hosted, can upgrade to Redis |
| Storage | Local (default) | Simple for self-hosted, can configure S3 |
| AI Client | prism-php/prism | Unified API for multiple providers |
| HTTP | Laravel HTTP | Built-in, fallback for custom integrations |

---

## AI Provider Support (via Prism)

Prism provides a unified interface for multiple AI providers:

| Provider | Text Gen | Embeddings | Notes |
|----------|----------|------------|-------|
| OpenAI | ✓ | ✓ | GPT-4o, GPT-4o-mini, etc. |
| Anthropic | ✓ | - | Claude Sonnet, Haiku, Opus |
| Ollama | ✓ | ✓ | Local models (Llama, Mistral, etc.) |
| Groq | ✓ | - | Fast inference |
| Mistral | ✓ | ✓ | Mistral models |
| xAI | ✓ | - | Grok models |
| Gemini | ✓ | ✓ | Google's models |
| DeepSeek | ✓ | - | DeepSeek models |
| OpenRouter | ✓ | - | Access 100+ models via one API |
