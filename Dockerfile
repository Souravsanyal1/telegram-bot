# Use PHP CLI image
FROM php:8.2-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    gmp-dev

# Install PHP Extension Installer
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions mongodb bcmath gmp curl

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Create data directory and set permissions
RUN mkdir -p data && chmod -R 777 data

# Start the bot (runs both Telegram polling and Blockchain event monitoring)
CMD ["php", "bot.php", "--both"]
