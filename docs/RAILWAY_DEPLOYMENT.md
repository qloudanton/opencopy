# Railway Deployment Guide

This guide walks you through deploying OpenCopy on Railway.app.

## Prerequisites

- A [Railway account](https://railway.app/)
- At least one AI provider API key (OpenAI, Anthropic, etc.)

## Quick Deploy

1. **Fork or clone this repository** to your GitHub account

2. **Create a new project on Railway**
   - Go to [Railway](https://railway.app/)
   - Click "New Project"
   - Select "Deploy from GitHub repo"
   - Select your forked repository

3. **Configure Environment Variables**

   Railway will automatically detect the Dockerfile. You need to set these environment variables:

   ### Required Variables

   ```bash
   # Application
   APP_NAME=OpenCopy
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:YOUR_32_CHAR_BASE64_KEY_HERE
   APP_URL=https://your-app.up.railway.app
   
   # Database (SQLite - default)
   DB_CONNECTION=sqlite
   
   # Session
   SESSION_DRIVER=database
   
   # Queue
   QUEUE_CONNECTION=database
   
   # Cache
   CACHE_STORE=database
   ```

   **To generate APP_KEY:**
   ```bash
   # Run locally or use an online base64 encoder for a 32-character random string
   php artisan key:generate --show
   ```

   ### Optional Variables

   If you want to use PostgreSQL instead of SQLite (recommended for production):

   ```bash
   DB_CONNECTION=pgsql
   DB_HOST=${PGHOST}
   DB_PORT=${PGPORT}
   DB_DATABASE=${PGDATABASE}
   DB_USERNAME=${PGUSER}
   DB_PASSWORD=${PGPASSWORD}
   ```

   Railway will automatically provide the `PG*` variables if you add a PostgreSQL database.

4. **Add a PostgreSQL Database (Optional but Recommended)**
   - In your Railway project, click "New"
   - Select "Database" → "PostgreSQL"
   - Railway will automatically connect it and provide environment variables

5. **Deploy**
   - Railway will automatically build and deploy your application
   - The build process will:
     - Install PHP and Node.js dependencies
     - Build frontend assets with Vite
     - Create the startup script
   - On first startup, the container will:
     - Create the SQLite database (if using SQLite)
     - Run database migrations
     - Cache Laravel configuration
     - Start the web server and queue worker

6. **Access Your Application**
   - Once deployed, Railway will provide a public URL
   - Visit the URL to see your OpenCopy installation
   - Create your first account (first user becomes admin)

## Environment Variables Reference

### Application Settings

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_NAME` | No | OpenCopy | Application name |
| `APP_ENV` | Yes | production | Environment (production/local) |
| `APP_DEBUG` | No | false | Enable debug mode (never true in production) |
| `APP_KEY` | Yes | - | 32-character encryption key |
| `APP_URL` | Yes | - | Your Railway app URL |

### Database Settings

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `DB_CONNECTION` | No | sqlite | Database driver (sqlite, pgsql, mysql) |
| `DB_HOST` | No* | - | Database host (*required for non-SQLite) |
| `DB_PORT` | No* | - | Database port (*required for non-SQLite) |
| `DB_DATABASE` | No* | - | Database name (*required for non-SQLite) |
| `DB_USERNAME` | No* | - | Database username (*required for non-SQLite) |
| `DB_PASSWORD` | No* | - | Database password (*required for non-SQLite) |

### AI Provider API Keys

Configure at least one AI provider:

| Variable | Description |
|----------|-------------|
| `OPENAI_API_KEY` | OpenAI API key for GPT models |
| `ANTHROPIC_API_KEY` | Anthropic API key for Claude models |
| `GROQ_API_KEY` | Groq API key for fast inference |
| `MISTRAL_API_KEY` | Mistral AI API key |
| `OPENROUTER_API_KEY` | OpenRouter API key (access multiple models) |
| `OLLAMA_URL` | Ollama server URL for local models |

Users can also add their own API keys through the application UI.

### Storage Settings (Optional)

For production image storage using Cloudflare R2:

| Variable | Description |
|----------|-------------|
| `FILESYSTEM_IMAGES_DISK` | Set to `r2` to use R2 |
| `CLOUDFLARE_R2_ACCESS_KEY_ID` | R2 access key |
| `CLOUDFLARE_R2_SECRET_ACCESS_KEY` | R2 secret key |
| `CLOUDFLARE_R2_BUCKET` | R2 bucket name |
| `CLOUDFLARE_R2_ENDPOINT` | R2 endpoint URL |
| `CLOUDFLARE_R2_URL` | Public R2 URL |

## Troubleshooting

### Application shows blank page

If you see a blank page:

1. Check Railway logs for errors
2. Ensure `APP_KEY` is set and valid
3. Verify database is created and migrations ran
4. Check that `APP_URL` matches your Railway domain

### Database connection errors

If you see database errors:

- **SQLite**: Ensure `DB_CONNECTION=sqlite` and migrations run at startup
- **PostgreSQL**: Verify PostgreSQL service is added and environment variables are set
- Check Railway logs for migration output

### 500 Internal Server Error

Common causes:

1. **Missing APP_KEY**: Generate and set it in environment variables
2. **Database not initialized**: Check startup logs for migration errors
3. **Permission issues**: The startup script should handle this, but check logs

### Build fails

If the Docker build fails:

1. Check that `package.json` and `composer.json` are valid
2. Ensure Node.js 20+ and PHP 8.3+ are supported
3. Review Railway build logs for specific errors

## Performance Optimization

### Enable PostgreSQL

SQLite works for small deployments, but PostgreSQL is recommended for:
- Multiple concurrent users
- Heavy write operations
- Better reliability

### Configure Queue Workers

The default deployment runs one queue worker via Supervisor. To scale:

1. Add more queue workers in Railway settings (adjust supervisor config)
2. Consider using Redis for queue driver instead of database

### Enable Caching

For better performance, consider:
- Redis for cache and sessions
- Horizon for queue management (requires Redis)

## Scaling

Railway makes scaling easy:

1. **Vertical Scaling**: Increase RAM/CPU in Railway settings
2. **Horizontal Scaling**: Add more services (requires Redis for sessions)
3. **Database Scaling**: Upgrade PostgreSQL plan

## Monitoring

- Use Railway's built-in logs and metrics
- Enable `APP_DEBUG=false` in production
- Set up error tracking (Sentry, Bugsnag, etc.)

## Support

- [GitHub Issues](https://github.com/WebFoundryAI/nexus_opencopy/issues)
- [Documentation](https://github.com/WebFoundryAI/nexus_opencopy)

## Security Notes

1. **Always set APP_DEBUG=false in production**
2. **Use strong APP_KEY** (32 characters, base64 encoded)
3. **Enable 2FA** for admin accounts
4. **Keep dependencies updated**: Run `composer update` and `npm update` regularly
5. **Use HTTPS**: Railway provides this by default
6. **Secure API keys**: Store them in environment variables, never commit them

## Cost Estimation

Railway pricing:
- Check [Railway Pricing](https://railway.app/pricing) for current rates
- **Free tier**: Includes monthly credits (sufficient for small projects)
- **Pro plan**: Starts at $20/month + usage
- **PostgreSQL**: Included in plans or pay-as-you-go

Plus AI costs:
- You pay directly to AI providers (OpenAI, Anthropic, etc.)
- Typical article: $0.10-$0.50 depending on length and model
