# Use PHP CLI image
FROM php:8.2-cli-alpine

# Install system dependencies for PHP extensions
RUN apk add --no-cache \
    libcurl \
    curl-dev \
    gmp-dev \
    libxml2-dev \
    openssl-dev \
    bash

# Install PHP extensions
RUN docker-php-ext-install \
    curl \
    bcmath \
    gmp

# Install MongoDB extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apk del .build-deps

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Create data directory and set permissions
RUN mkdir -p data && chmod -R 777 data

# Start the bot (runs both Telegram polling and Blockchain event monitoring)
CMD ["php", "bot.php", "--both"]
