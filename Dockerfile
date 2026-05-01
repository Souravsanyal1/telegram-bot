FROM php:8.3-cli

# Install required PHP extensions
RUN docker-php-ext-install bcmath sockets

# Install curl extension dependencies
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Create data directory
RUN mkdir -p /app/data

# Expose the health check port (Render sets PORT env var)
EXPOSE 10000

# Run the bot
CMD ["php", "bot.php"]
